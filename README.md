# pods-address-maps
This repo contains two addon plugins for dev purposes: Pods Address field &amp; Pods Maps component.

The code is the same as on the official [PR at Pods GitHub](https://github.com/pods-framework/pods/pull/3634), only refactored as plugins.
I will make sure the code keeps maintained as long as this feature isn’t within Pods core.

## Installation
Upload both folders in this repo to the WordPress plugins folder (/wp-content/plugins/).
Another option is to create separate .zip files from both folders and upload them through the regular WordPress plugin installer.

### Via svn
You can use githubs SVN ("subversion") features to download the plugins.

    # Linux shell
    cd /path/to/your/wp-content/plugins
    svn export https://github.com/JoryHogeveen/pods-address-maps.git/trunk/pods-maps
    svn export https://github.com/JoryHogeveen/pods-address-maps.git/trunk/pods-adress-fields
    # modify permissions, if needed (e.g. sudo chown www-data:www-data pods*)
