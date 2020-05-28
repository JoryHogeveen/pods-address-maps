# pods-address-maps
This repo contains two addon plugins for dev purposes: Pods Address field &amp; Pods Maps component.

The code is the same as on the official [PR at Pods GitHub](https://github.com/pods-framework/pods/pull/3634), only refactored as plugins.
I do my best that the code is maintained as long as this feature isn’t within Pods core.

## Installation
Upload both folders in this repo to the WordPress plugins folder (/wp-content/plugins/).
Another option is to create separate .zip files from both folders and upload them through the regular WordPress plugin installer.

## You need an Google-Maps-API Key! 
first get yours here: https://console.cloud.google.com/apis/
then paste it in the plugin settings: /wp-admin/admin.php?page=pods-component-maps

=======
### Via subversion (SVN)
Using githubs subversion (SVN) features you can download and install the plugins as follows:

    # Linux shell
    cd /path/to/wp-content/plugins # use sudo or see below if permission issues come up
    svn export https://github.com/JoryHogeveen/pods-address-maps.git/trunk/pods-maps
    svn export https://github.com/JoryHogeveen/pods-address-maps.git/trunk/pods-address-field

If you run into permission problems, download the folders to a place where you can write to, fix the permissions e.g. with `sudo chown www-data:www-data -R pods*` and move the folders in the correct place.
