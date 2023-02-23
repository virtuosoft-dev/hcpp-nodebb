# hestiacp-nodebb
A plugin for Hestia Control Panel (via hestiacp-pluginable) that enables hosting a NodeBB instance.
With this plugin installed, user accounts can host their own NodeBB instance either in the root domain
or as a subfolder installation. For instance, it is possible to run WordPress in the root domain while
having NodeBB installed on the same domain in a subfolder (i.e. https://example.com/nodebb-forum).

&nbsp;
> :warning: !!! Note: this repo is in progress; when completed, a release will appear in the release tab.

## Installation
HestiaCP-NodeBB requires an Ubuntu based installation of [Hestia Control Panel](https://hestiacp.com) in addition to an installation of [HestiaCP-Pluginable](https://github.com/steveorevo/hestiacp-pluginable) *and* [HesitaCP-NodeApp](https://github.com/steveorevo/hestiacp-nodeapp) to function; please ensure that you have first installed both Pluginable and NodeApp on your Hestia Control Panel before proceeding. Switch to a root user and simply download and unzip this project and move the folder to the `/usr/local/hestia/plugins` folder. It should appear as a subfolder with the name `nodebb`, i.e. `/usr/local/hestia/plugins/nodebb`.

First, switch to root user:
```
sudo -s
```

Then simply clone the repo to your plugins folder, with the name `nodebb`:

```
cd /usr/local/hestia/plugins
git clone https://github.com/steveorevo/hestiacp-nodebb nodebb
```

Note: It is important that the destination plugin folder name is `nodebb`.

Be sure to logout and login again to your Hestia Control Panel; the plugin will immediately start installing NodeJS and NodeBB depedencies in the background. A notification will appear under the admin user account indicating *"NodeBB plugin has finished installing"* when complete. This may take awhile before the options appear in Hestia. You can force manual installation via root level SSH:

```
sudo -s
cd /usr/local/hestia/plugins/nodebb
./install
```
