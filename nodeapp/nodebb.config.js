module.exports = {
    apps: (function() {
        
        // Get the app name, user and domain name from the current directory path.
        let app = __filename.split('/').pop().replace('.config.js', '');
        let domain = __dirname.split('/')[4];
        let user = __dirname.split('/')[2];
        return [{
            name: app + '-' + domain,
            script: 'nodebb_pm2.js',
            cwd: __dirname,
            interpreter: (function() {
                /**
                 * Specify the node interpreter to use.
                 * 
                 * Read the .nvmrc file and find a suitable node version specified from it,
                 * or default to the latest node version.
                 */
    
                let file = __dirname + '/.nvmrc';
                let ver = 'current';
                const fs = require('fs');
                if (fs.existsSync(file)) {
                    ver = fs.readFileSync(file, {encoding:'utf8', flag:'r'}).trim();
                }
                const { execSync } = require('child_process');
                ver = execSync('/bin/bash -c "source /opt/nvm/nvm.sh && nvm which ' + ver + '"').toString().trim();
                if (!fs.existsSync(ver)) {
                    console.error(ver);
                    process.exit(1);
                }else{
                    return ver;
                }
            })(),       
            args: (function() {
                /**
                 * Update the allocated port number and url in nodebb's config.json file.
                 * 
                 * The port number is read from a file in /usr/local/hestia/data/hcpp/ports/%username%/%domain%.ports.
                 */

                let port = 0;
                let file = '/usr/local/hestia/data/hcpp/ports/' + user + '/' + domain + '.ports';
                const fs = require('fs');
                let ports = fs.readFileSync(file, {encoding:'utf8', flag:'r'});
                ports = ports.split(/\r?\n/);
                for( let i = 0; i < ports.length; i++) {
                    if (ports[i].indexOf(app + '_port') > -1) {
                        port = ports[i];
                        break;
                    }
                }
                port = parseInt(port.trim().split(' ').pop());
                let root = __dirname.replace(/.*\/nodeapp/, '').trim();
                if (!root.startsWith('/')) root = '/' + root;

                // Read the config.json file synchronously
                const config = JSON.parse(fs.readFileSync(__dirname + '/config.json'));

                // Update the url and port properties
                config.url = 'http://localhost:' + port + root;
                config.port = port;

                // Write the updated config object back to the file
                fs.writeFileSync('config.json', JSON.stringify(config, null, 2));
                return '';
            })(),
            watch: ['.restart'],
            ignore_watch: [],
            watch_delay: 5000,
            restart_delay: 5000
        }];
    })()
}
