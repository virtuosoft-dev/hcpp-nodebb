/**
 * Our shim for nodebb and PM2 compatibility.
 */
const { exec } = require('child_process');
const startCmd = './nodebb start';
const stopCmd = './nodebb stop';
const nvmCmd = 'cd ' + __dirname + ' && export NVM_DIR=/opt/nvm && . /opt/nvm/nvm.sh && nvm use && ';
const start = () => {
  console.log('Starting nodebb...');
  exec(nvmCmd + startCmd, (error, stdout, stderr) => {
    if (error) {
      console.error(`Error starting nodebb: ${error}`);
    } else {
      console.log(`nodebb started: ${stdout}`);
    }
  });
};

const stop = () => {
  console.log('Stopping nodebb...');
  exec(nvmCmd + stopCmd, (error, stdout, stderr) => {
    if (error) {
      console.error(`Error stopping nodebb: ${error}`);
    } else {
      console.log(`nodebb stopped: ${stdout}`);
    }
  });
};

// Start the nodebb process
start();

// Listen for custom 'shutdown' message from the parent process
process.on('message', (message) => {
  if (message === 'shutdown') {
    console.log('Received shutdown message, stopping...');
    stop();
  }
});
