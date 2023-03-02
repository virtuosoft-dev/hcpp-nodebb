/**
 * Our shim for nodebb production mode and PM2 compatibility.
 */
const { exec } = require('child_process');
const startCmd = './nodebb start';
const stopCmd = './nodebb stop';

const start = () => {
  console.log('Starting nodebb...');
  exec(startCmd, (error, stdout, stderr) => {
    if (error) {
      console.error(`Error starting nodebb: ${error}`);
    } else {
      console.log(`nodebb started: ${stdout}`);
    }
  });
};

const stop = () => {
  console.log('Stopping nodebb...');
  exec(stopCmd, (error, stdout, stderr) => {
    if (error) {
      console.error(`Error stopping nodebb: ${error}`);
    } else {
      console.log(`nodebb stopped: ${stdout}`);
    }
    process.exit();
  });
};

// Start the nodebb process
start();

// Listen for SIGINT signal
process.on('SIGINT', () => {
  console.log('Received SIGINT signal, stopping nodebb...');
  stop();
});
