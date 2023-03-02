module.exports = {
    apps: (function() {
        const fs = require('fs');

        // Load default PM2 compatible nodeapp configuration.
        let nodeapp = require('/usr/local/hestia/plugins/nodeapp/nodeapp.js')(__filename);
        let domain = nodeapp._domain;

        // Get url, account for SSL, subfolder
        let root = __dirname.replace(/.*\/nodeapp/, '').trim();
        if (!root.startsWith('/')) root = '/' + root;

        // Check for SSL
        let url = 'http://';
        let sslPath = '/home/' + user + '/conf/web/' + domain + '/ssl';
        if (fs.existsSync(sslPath)) {
            if (fs.readdirSync(sslPath).length !== 0) {
                url = 'https://';
            }
        }
        url += domain + root;

        // Update the config.json file
        let config = JSON.parse(fs.readFileSync(__dirname + '/config.json'));
        config.url = url;
        config.port = nodeapp._port;
        fs.writeFileSync(__dirname + '/config.json', JSON.stringify(config, null, 2));

        // Update the script based on the mode (production or debug)
        // Note: debug mode bypasses nodebb and doesn't support dynamic restarts
        if (nodeapp.hasOwnProperty('_debugPort')) {
            nodeapp.script = 'app.js';
        }
        return [nodeapp];
    })()
}
