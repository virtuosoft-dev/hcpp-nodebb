<?php
/**
 * Extend the HestiaCP Pluginable object with our NodeBB object for
 * allocating NodeBB instances.
 * 
 * @author Virtuosoft/Stephen J. Carnam
 * @license AGPL-3.0, for other licensing options contact support@virtuosoft.com
 * @link https://github.com/virtuosoft-dev/hcpp-nodebb
 * 
 */

if ( ! class_exists( 'NodeBB') ) {
    class NodeBB extends HCPP_Hooks {
        public $supported = ['20'];

        /**
         * Customize NodeBB install screen.
         */ 
        public function hcpp_add_webapp_xpath( $xpath ) {
            if ( ! (isset( $_GET['app'] ) && $_GET['app'] == 'NodeBB' ) ) return $xpath;
            global $hcpp;

            // Check for bash shell user
            $user = $_SESSION["user"];
            if ($_SESSION["look"] != "") {
                $user = $_SESSION["look"];
            }
            $domain = $_GET['domain'];
            $domain = preg_replace('/[^a-zA-Z0-9\.\-]/', '', $domain);
            $shell = $hcpp->run( "v-list-user $user json")[$user]['SHELL'];
            if ( $shell != 'bash' ) {
                $style = '<style>div.u-mb10{display:none;}</style>';
                $html = '<span class="u-mb10">Cannot continue. User "' . $user . '" must have bash login ability.</span>';
            }else{
                $style = '<style>#webapp_php_version, label[for="webapp_php_version"]{display:none;}</style>';
                $html =  '<div class="u-mb10">
                              The NodeBB instance lives inside the "nodeapp" folder (next to "public_html"). It can be a
                              standalone instance in the domain root, or in a subfolder using the <b>Install Directory</b> 
                              field above.
                          </div>';
            }
            $xpath = $hcpp->insert_html( $xpath, '//div[contains(@class, "form-container")]', $html );
            $xpath = $hcpp->insert_html( $xpath, '/html/head', $style );

            // Remove existing public_html related alert if present
            $alert_div = $xpath->query('//div[@role="alert"][1]');
            if ( $alert_div->length > 0 ) {
                $alert_div = $alert_div[0];
                $alert_div->parentNode->removeChild( $alert_div );
            }

            // Insert our own alert about non-empty nodeapp folder
            $folder = "/home/$user/web/$domain/nodeapp";
            if ( file_exists( $folder ) && iterator_count(new \FilesystemIterator( $folder, \FilesystemIterator::SKIP_DOTS)) > 0 ) {
                $html = '<div class="alert alert-info u-mb10" role="alert">
                        <i class="fas fa-info"></i>
                        <div>
                            <p class="u-mb10">Data Loss Warning!</p>
                            <p class="u-mb10">Your nodeapp folder already has files uploaded to it. The installer will overwrite your files and/or the installation might fail.</p>
                            <p>Please make sure ~/web/' . $domain . '/nodeapp is empty or an empty subdirectory is specified!</p>
                        </div>
                    </div>';
                $xpath = $hcpp->insert_html( $xpath, '//div[contains(@class, "form-container")]', $html, true );
            }
            return $xpath;
        }

        /**
         * Setup NodeBB with the given options This can be invoked from
         * the command line v-invoke-plugin and is used by the webapp installer.
         */
        public function hcpp_invoke_plugin( $args ) {
            if ( count( $args ) < 0 ) return $args;
            global $hcpp;

            // Install NodeBB on supported NodeJS version
            if ( $args[0] == 'nodebb_install' ) {
                $latest = $hcpp->find_latest_repo_tag( 'https://github.com/nodebb/nodebb' );
                $installed = ltrim( trim( shell_exec( 'su -s /bin/bash nodebb -c "/opt/nodebb/nodebb -V"') ), 'v' );
                $major = $this->supported[0];
                $hcpp->log( "NodeBB on v$major: $installed vs $latest" );
                $cmd = '';

                // Wipe old version if it's not the latest
                if ( $installed != $latest ) {
                    $wipe = 'rm -rf /opt/nodebb';
                    $hcpp->log( $wipe );
                    $hcpp->log( shell_exec( $wipe ) );
                }

                // Check if NodeBB is installed
                if ( is_dir( '/opt/nodebb') ) return $args;

                // Create nodebb user if it doesn't exist
                if ( ! is_dir( '/home/nodebb') ) {
                    $cmd .= 'useradd --system --create-home --shell /usr/sbin/nologin nodebb && ';
                }
                $cmd .= 'mkdir -p /opt/nodebb && chown nodebb:nodebb /opt/nodebb && ';
                $cmd .= 'su -s /bin/bash nodebb -c "cd /opt/nodebb && git clone --depth 1 -b v' . $latest . ' https://github.com/NodeBB/NodeBB.git ./" && ';

                // Load PostgreSQL credentials from pgsql.conf
                $configFile = '/usr/local/hestia/conf/pgsql.conf';
                if (!file_exists($configFile)) {
                    die("Configuration file not found: $configFile\n");
                }
                $config = parse_ini_string(str_replace(' ', "\n", file_get_contents($configFile)));

                // Extract credentials
                $host = $config['HOST'] ?? 'localhost';
                $user = $config['USER'] ?? 'postgres';
                $password = $config['PASSWORD'] ?? '';
                $port = $config['PORT'] ?? '5432';
                $templateDb = $config['TPL'] ?? 'template1';

                // Generate a random username and password for the temporary user
                $tempUser = 'temp_nodebb';
                $tempPassword = 'temp_nodebb';
                $tempDb = $tempUser;

                // Create the temporary user
                echo "Creating temporary PostgreSQL user...\n";
                $createUserCmd = "PGPASSWORD='$password' psql -h $host -U $user -p $port -d $templateDb -c \"CREATE USER $tempUser WITH PASSWORD '$tempPassword';\"";
                exec($createUserCmd, $output, $returnVar);
                if ($returnVar !== 0) {
                    echo "Failed to create temporary user.\n";
                }

                // Create the database
                echo "Creating database for NodeBB...\n";
                $createDbCmd = "PGPASSWORD='$password' psql -h $host -U $user -p $port -d $templateDb -c \"CREATE DATABASE $tempDb OWNER $tempUser;\"";
                exec($createDbCmd, $output, $returnVar);
                if ($returnVar !== 0) {
                    echo "Failed to create database.\n";
                    // Clean up the user if the database creation fails
                    exec("PGPASSWORD='$password' psql -h $host -U $user -p $port -d $templateDb -c \"DROP USER $tempUser;\"");
                }

                // Output the credentials
                echo "Temporary PostgreSQL user and database created:\n";

                // Setup temporary NodeBB to ensure all packages are installed, faster setup
                $cmd .= 'fuser -k 4567/tcp ; ';
                $cmd .= 'su -s /bin/bash nodebb -c "cd /opt/nodebb && ';
                $cmd .= 'export NODEBB_URL=\"http://localhost\" && ';
                $cmd .= 'export NODEBB_ADMIN_USERNAME=\"temp_nodebb\" && ';
                $cmd .= 'export NODEBB_ADMIN_PASSWORD=\"' . $tempPassword . '\" && ';
                $cmd .= 'export NODEBB_ADMIN_EMAIL=\"nodebb@dev.pw\" && ';
                $cmd .= 'export NODEBB_DB=\"postgres\" && ';
                $cmd .= 'export NODEBB_DB_HOST=\"' . $host . '\" && ';
                $cmd .= 'export NODEBB_DB_PORT=\"' . $port . '\" && ';
                $cmd .= 'export NODEBB_DB_USER=\"' . $tempUser . '\" && ';
                $cmd .= 'export NODEBB_DB_NAME=\"' . $tempDb . '\" && ';
                $cmd .= 'export NODEBB_DB_PASSWORD=\"' . $tempPassword . '\" && ';
                $cmd .= './nodebb setup"';
                $hcpp->log( $cmd );
                $hcpp->log( shell_exec( $cmd ) );

                // Delete the temporary database
                echo "Deleting database...\n";
                $deleteDbCmd = "PGPASSWORD='$password' psql -h $host -U $user -p $port -d $templateDb -c \"DROP DATABASE $tempDb;\"";
                exec($deleteDbCmd, $output, $returnVar);
                if ($returnVar !== 0) {
                    echo "Failed to delete database. Please check manually.\n";
                }

                // Delete the temporary user
                echo "Deleting temporary PostgreSQL user...\n";
                $deleteUserCmd = "PGPASSWORD='$password' psql -h $host -U $user -p $port -d $templateDb -c \"DROP USER $tempUser;\"";
                exec($deleteUserCmd, $output, $returnVar);
                if ($returnVar !== 0) {
                    echo "Failed to delete temporary user. Please check manually.\n";
                    exit(1);
                }
                echo "Done.\n";
            }

            // Uninstall NodeBB
            if ( $args[0] == 'nodebb_uninstall' ) {
                global $hcpp;
                $wipe = 'rm -rf /opt/nodebb';
                $hcpp->log( $wipe );
                $hcpp->log( shell_exec( $wipe ) );
            }

            // Setup NodeBB with the supported NodeJS on the given domain 
            if ( $args[0] == 'nodebb_setup' ) {
                $options = json_decode( $args[1], true );
                $hcpp->log( $options );
                $user = $options['user'];
                $domain = $options['domain'];
                $nodebb_folder = $options['nodebb_folder'];
                if ( $nodebb_folder == '' || $nodebb_folder[0] != '/' ) $nodebb_folder = '/' . $nodebb_folder;
                $nodeapp_folder = "/home/$user/web/$domain/nodeapp";
                
                // Create parent nodeapp folder first this way to avoid CLI permissions issues
                mkdir( $nodeapp_folder, 0755, true );
                chown( $nodeapp_folder, $user );
                chgrp( $nodeapp_folder, $user );
                $nodebb_folder = $nodeapp_folder . $nodebb_folder;
                $nodered_root = $hcpp->delLeftMost( $nodebb_folder, $nodeapp_folder ); 
                $hcpp->runuser( $user, "mkdir -p $nodebb_folder" );

                // Copy over initial nodeapp files
                $hcpp->copy_folder( __DIR__ . '/nodeapp', $nodebb_folder, $user );
                chmod( $nodeapp_folder, 0755 );

                // Fill out config.json
                $nodebb_secret = bin2hex(openssl_random_pseudo_bytes(16));
                $config = file_get_contents( $nodebb_folder . '/config.json' );
                $config = str_replace( '%nodebb_secret%', $nodebb_secret, $config );
                $config = str_replace( '%database_name%', $user . '_' . $options['database_name'], $config );
                $config = str_replace( '%database_user%', $user . '_' . $options['database_user'], $config );
                $config = str_replace( '%database_password%', $options['database_password'], $config );
                $config = str_replace( '%nodebb_port%', $port, $config );
                $url = "http://$domain" . $subfolder;
                if ( is_dir( "/home/$user/conf/web/$domain/ssl") ) {
                    $url = "https://$domain" . $subfolder;
                }
                $config = str_replace( '%nodebb_url%', $url, $config );

                // Copy over pre-installed NodeBB runtime files
                $hcpp->copy_folder( '/opt/nodebb', $nodebb_folder, $user );

                // Overwrite the config.json file
                file_put_contents( $nodebb_folder . '/config.json', $config );

                // Invoke the setup script to install NodeBB
                $cmd = 'export NODEBB_ADMIN_USERNAME="' . $options['nodeBB_username'] . '" && ';
                $cmd .= 'export NODEBB_ADMIN_EMAIL="' . $options['nodeBB_email'] . '" && ';
                $cmd .= 'export NODEBB_ADMIN_PASSWORD="' . $options['nodeBB_password'] . '" && ';
                $cmd .= 'cd ' . $nodebb_folder . ' && ./nodebb setup';
                $hcpp->runuser( $user, $cmd );
                
                // Cleanup, allocate ports, prepare nginx and start services
                $hcpp->nodeapp->shutdown_apps( $nodeapp_folder );
                $hcpp->nodeapp->allocate_ports( $nodeapp_folder );

                // Update proxy and restart nginx
                if ( $nodeapp_folder . '/' == $nodebb_folder ) {
                    $ext = $hcpp->run( "v-list-web-domain '$user' '$domain' json" )[$domain]['PROXY_EXT'];
                    $ext = str_replace( ' ', ',', $ext );
                    $hcpp->run( "v-change-web-domain-proxy-tpl '$user' '$domain' 'NodeApp' '$ext' 'no'" );
                }else{
                    $hcpp->nodeapp->generate_nginx_files( $nodeapp_folder );
                    $hcpp->nodeapp->startup_apps( $nodeapp_folder );
                }
                $hcpp->run( "v-restart-proxy" );
            }
            return $args;
        }

        // Intercept form submission to flag database creation
        public function hcpp_ob_started( $args ) {
            if ( isset( $_REQUEST['app'] ) && $_REQUEST['app'] == 'NodeBB' && isset( $_REQUEST['webapp_database_create'] ) ) {
                if (session_status() == PHP_SESSION_NONE) {
                    session_start();
                }
                $dbuser = $_SESSION['user'];
                if ( isset( $_SESSION['look'] ) && trim( $_SESSION['look'] ) != "" ) $dbuser = $_SESSION['look'];
                touch( '/tmp/nodebb_pgsql_' . $dbuser );
            }
            return $args;
        }

        // Intercept database creation to specify pgsql, utf8 instead of mysql, utf8mb4
        public function v_add_database( $args ) {
            if ( file_exists( '/tmp/nodebb_pgsql_' . $args[0]) ) {
                if ( filemtime( '/tmp/nodebb_pgsql_' . $args[0] ) > (time() - 3) ) {                    
                    $args[4] = 'pgsql';
                    $args[6] = 'utf8';
                }
                unlink( '/tmp/nodebb_pgsql_' . $args[0] );
            }
            return $args;
        }

        /**
         * Check daily for NodeBB updates and install them.
         */
        public function nodeapp_autoupdate() {
            global $hcpp;
            $latest = $hcpp->find_latest_repo_tag( 'https://github.com/nodebb/nodebb' );
            $hcpp->nodeapp->do_maintenance( function( $pm2_list ) use ( $hcpp, $latest ) {
                
                // Update each user's NodeBB instance
                foreach( $pm2_list as $user => $app_ids ) {
                    if ( count( $app_ids ) > 0 ) {
                        foreach( $app_ids as $app_id ) {
                            $path = $hcpp->runuser( $user, 'pm2 env ' . $app_id );
                            $path = $hcpp->getRightMost( $path, 'script: ' );
                            $path = $hcpp->getLeftMost( $path, '/nodebb.js' );
                            if ( strpos( $path, '/nodeapp' ) !== false ) {
                                $cmd = "cd $path && git fetch && git reset --hard v$latest && ./nodebb upgrade";
                                $hcpp->runuser( $user, $cmd );
                            }
                        }
                    }
                }
            }, $this->supported, ['nodebb'] );
            $hcpp->runuser( '', 'v-invoke-plugin nodebb_install' );
        }
    }
    global $hcpp;
    $hcpp->register_plugin( NodeBB::class );
}
