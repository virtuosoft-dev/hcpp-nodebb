<?php
/**
 * Extend the HestiaCP Pluginable object with our NodeBB object for
 * allocating NodeBB instances.
 * 
 * @version 1.0.0
 * @license GPL-3.0
 * @link https://github.com/steveorevo/hestiacp-nodebb
 * 
 */

if ( ! class_exists( 'NodeBB') ) {
    class NodeBB {
        /**
         * Constructor, listen for the priv_change_web_domain_proxy_tpl event
         */
        public function __construct() {
            global $hcpp;
            $hcpp->nodebb = $this;
            $hcpp->add_action( 'csrf_verified', [ $this, 'csrf_verified' ] );
            $hcpp->add_action( 'invoke_plugin', [ $this, 'invoke_plugin' ] );
            $hcpp->add_action( 'priv_add_database', [ $this, 'priv_add_database' ] );
            $hcpp->add_action( 'render_page', [ $this, 'render_page' ] );
        }

        // Intercept form submission to flag database creation
        public function csrf_verified() {
            if ( isset( $_REQUEST['app'] ) && $_REQUEST['app'] == 'NodeBB' && isset( $_REQUEST['webapp_database_create'] ) ) {
                if ( isset( $_SESSION['look'] ) ) {
                    touch( '/tmp/nodebb_pgsql_' . $_SESSION['look'] );
                }
            }
        }

        // Intercept database creation to specify pgsql, utf8 instead of mysql, utf8mb4
        public function priv_add_database( $args ) {
            if ( file_exists( '/tmp/nodebb_pgsql_' . $args[0]) ) {
                if ( filemtime( '/tmp/nodebb_pgsql_' . $args[0] ) > (time() - 3) ) {
                    $args[4] = 'pgsql';
                    $args[6] = 'utf8';
                }
                unlink( '/tmp/nodebb_pgsql_' . $args[0] );
            }
            return $args;
        }

        // Install NodeBB with the given user options
        public function invoke_plugin( $args ) {

            if ( $args[0] != 'nodebb_install' ) return $args;
            global $hcpp;
            // $hcpp->log( "NodeBB: Installing NodeBB" );
            $options = json_decode( $args[1], true );
            $user = $options['user'];
            $domain = $options['domain'];

            // Copy the NodeBB files to the user folder
            $nodebb_folder = $options['nodebb_folder'];
            if ( $nodebb_folder == '' || $nodebb_folder[0] != '/' ) $nodebb_folder = '/' . $nodebb_folder;
            $nodeapp_folder = "/home/$user/web/$domain/nodeapp";
            $nodebb_folder = $nodeapp_folder . $nodebb_folder;

            // Create the nodeapp folder 
            $cmd = "mkdir -p " . escapeshellarg( $nodebb_folder ) . " && ";
            $cmd .= "chown -R $user:$user " . escapeshellarg( $nodeapp_folder );
            shell_exec( $cmd );
            
            // Copy over nodebb files
            $hcpp->nodeapp->copy_folder( __DIR__ . '/nodeapp', $nodebb_folder, $user );
            
            // Fill out config.json
            $nodebb_secret = bin2hex(openssl_random_pseudo_bytes(16));
            $config = file_get_contents( $nodebb_folder . '/config.json' );
            $config = str_replace( '%nodebb_secret%', $nodebb_secret, $config );
            $config = str_replace( '%database_name%', $domain . '_' . $options['database_name'], $config );
            $config = str_replace( '%database_user%', $domain . '_' . $options['database_user'], $config );
            $config = str_replace( '%database_password%', $options['database_password'], $config );
            file_put_contents( $nodebb_folder . '/config.json', $config );

            // Run initial setup

            // $hcpp->log( $options );

            // 19:17:50.49 "NodeBB: Installing NodeBB"
            // 19:17:50.49 {
            //     "nodebb_username": "nbbadmin",
            //     "nodebb_password": "nbpassword",
            //     "nodebb_folder": "",
            //     "php_version": "7.3",
            //     "database_create": "true",
            //     "database_name": "28605",
            //     "database_user": "28605",
            //     "database_password": "d69c24e6d72067c1fc8c",
            //     "user": "homestead",
            //     "domain": "test1.openmy.info"
            // }

    # node app.js \
    #     --setup "{\"admin:username\":\"${ADMIN_USERNAME}\",\"admin:password\":\"${ADMIN_PASSWORD}\",\"admin:password:confirm\":\"${ADMIN_PASSWORD}\",\"admin:email\":\"${ADMIN_EMAIL}\"}" \
    #     --defaultPlugins "[\"nodebb-plugin-custom-homepage\", \"nodebb-plugin-custom-pages\", \"nodebb-plugin-dbsearch\", \"nodebb-plugin-emoji\", \"nodebb-plugin-emoji-android\", \"nodebb-plugin-emoji-extended\", \"nodebb-plugin-emoji-one\", \"nodebb-plugin-markdown\", \"nodebb-plugin-mentions\", \"nodebb-plugin-ns-embed\", \"nodebb-plugin-soundpack-default\", \"nodebb-plugin-spam-be-gone\", \"nodebb-rewards-essentials\", \"nodebb-theme-vanilla\", \"nodebb-widget-essentials\"${modulesToActivate}]" \
    #      || (echo "Unable to install nodebb" && exit 1)

            return $args;
        }

        // Customize the install page
        public function render_page( $args ) {
            global $hcpp;
            if ( strpos( $_SERVER['REQUEST_URI'], '/add/webapp/?app=NodeBB&' ) === false ) return $args;
            $content = $args['content'];
        
            // Suppress Data loss alert, and PHP version selector
            $content = '<style>.alert.alert-info.alert-with-icon{display:none;}</style>' . $content;
        
            if ( !is_dir('/usr/local/hestia/plugins/nodeapp') ) {
        
                // Display missing nodeapp requirement
                $content = '<style>.form-group{display:none;}</style>' . $content;
                $msg = '<div style="margin-top:-20px;width:75%;"><span>';
                $msg .= 'Cannot contiue. The NodeBB Quick Installer requires the NodeApp plugin.</span>';
                $msg .= '<script>$(function(){$(".l-unit-toolbar__buttonstrip.float-right a").css("display", "none");});</script>';
            }else{
        
                // Display install information
                $msg = '<div style="margin-top:-20px;width:75%;"><span>';
                $msg .= 'The NodeBB forum lives inside the "nodeapp" folder (adjacent to "public_html"). ';
                $msg .= 'It can be a standalone instance in the domain root, or in a subfolder using the ';
                $msg .= '<b>Install Directory</b> field below.</span><br><span style="font-style:italic;color:darkorange;">';
                $msg .= 'Files will be overwritten; be sure the specified <span style="font-weight:bold">Install Directory</span> is empty!</span></div><br>';
                
                // Enforce username and password, remove PHP version
                $msg .= '
                <script>
                    $(function() {
                        $("label[for=webapp_php_version]").parent().css("display", "none");
                        let borderColor = $("#webapp_nodebb_username").css("border-color");
                        let toolbar = $(".l-center.edit").html();
                        function nr_validate() {
                            if ( $("#webapp_nodebb_username").val().trim() == "" || $("#webapp_nodebb_password").val().trim() == "" ) {
                                $(".l-unit-toolbar__buttonstrip.float-right a").css("opacity", "0.5").css("cursor", "not-allowed");
                                if ($("#webapp_nodebb_username").val().trim() == "") {
                                    $("#webapp_nodebb_username").css("border-color", "red");
                                }else{
                                    $("#webapp_nodebb_username").css("border-color", borderColor);
                                }
                                if ($("#webapp_nodebb_password").val().trim() == "") {
                                    $("#webapp_nodebb_password").css("border-color", "red");
                                }else{
                                    $("#webapp_nodebb_password").css("border-color", borderColor);
                                }
                                return false;
                            }else{
                                $(".l-unit-toolbar__buttonstrip.float-right a").css("opacity", "1").css("cursor", "");
                                $("#webapp_nodebb_username").css("border-color", borderColor);
                                $("#webapp_nodebb_password").css("border-color", borderColor);
                                return true;
                            }
                        };
        
                        // Override the form submition
                        $(".l-unit-toolbar__buttonstrip.float-right a").removeAttr("data-action").removeAttr("data-id").click(function() {
                            if ( nr_validate() ) {
                                $(".l-sort.clearfix").html("<div class=\"l-unit-toolbar__buttonstrip\"></div><div class=\"l-unit-toolbar__buttonstrip float-right\"><div><div class=\"timer-container\" style=\"float:right;\"><div class=\"timer-button spinner\"><div class=\"spinner-inner\"></div><div class=\"spinner-mask\"></div> <div class=\"spinner-mask-two\"></div></div></div></div></div>");
                                $("#vstobjects").submit();
                            }
                        });
                        $("#vstobjects").submit(function(e) {
                            if ( !nr_validate() ) {
                                e.preventDefault();
                            }
                        });
                        $("#webapp_nodebb_username").blur(nr_validate).keyup(nr_validate);
                        $("#webapp_nodebb_password").blur(nr_validate).keyup(nr_validate);
                        $(".generate").click(function() {
                            setTimeout(function() {
                                nr_validate();
                            }, 500)
                        });
                        nr_validate();
                    });
                </script>
                ';
            }
            $content = str_replace( '<div class="app-form">', '<div class="app-form">' . $msg, $content );
            $args['content'] = $content;
            return $args;
        }
    }
    new NodeBB();
}
