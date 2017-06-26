# GpxPod ownCloud/Nextcloud application

This app's purpose is to display gpx, kml, igc, fit and tcx files collections,
view elevation profiles and tracks stats, filter tracks,
 color tracks by speed or elevation and compare divergent parts of similar tracks.

If you want to help to translate this app in your language, take the english=>french files in "l10n" directory as examples.

GpxPod :

* can display gpx/kml/tcx/igc/fit files anywhere in your files, files shared with you, files in folders shared with you. tcx, igc, fit files will be converted and displayed only if **GpsBabel** is found on the server system.
* supports metric, english and nautical measure systems
* draws elevation or speed interactive chart
* displays geotagged pictures found in selected directory
* generates public links pointing to a track/folder. This link can be used if the file/folder is shared by public link
* allows you to move selected track files
* is translated in French, German and Russian
* can correct tracks elevations if SRTM.py (gpxelevations) is found on the server's system
* can make global comparison of multiple tracks
* can make visual pair comparison of divergent parts of similar tracks
* allows users to add personal map tile servers
* saves/restores user options values
* allows user to manually set track line colors
* detects browser timezone to correctly display dates and allows user to manually select another timezone
* loads extra waypoint symbols from GpxEdit if installed
* works with encrypted data folder (server side encryption)
* proudly uses Leaflet with lots of plugins to display the map
* is compatible with SQLite, MySQL and PostgreSQL databases
* adds possibility to view .gpx files directly from the "Files" app

This app is tested under Owncloud 9.0/Nextcloud 11 with Firefox and Chromium.

This app is under development.

Link to Owncloud application website : https://apps.owncloud.com/content/show.php/GpxPod+again?content=174733

Link to Nextcloud application website : https://apps.nextcloud.com/apps/gpxpod

## Donation

I develop this app during my free time. You can make a donation to me on Paypal. [Click HERE to make a donation](https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=66PALMY8SF5JE) (you don't need a paypal account)

## Install

See the [AdminDoc](https://gitlab.com/eneiluj/gpxpod-oc/wikis/admindoc) for more details (allow SRTM elevation correction, integration in "Files" app, tcx, igc, fit compatibility...)

There are no more python dependencies ! (except for extra features like elevations correction)

Put gpxpod directory in the Owncloud/Nextcloud apps to install.
There are several ways to do that :

### Clone the git repository

```
cd /path/to/owncloud/apps
git clone https://gitlab.com/eneiluj/gpxpod-oc.git gpxpod
```

### Download from https://apps.owncloud.com or https://apps.nextcloud.com

Extract gpxpod archive you just downloaded from the website :
```
cd /path/to/owncloud/apps
tar xvf 174733-gpxpod-x.x.x.tar.gz
```

## Known issues

* bad management of file names including simple or double quotes
* _WARNING_, kml conversion will NOT work with recent kml files using the proprietary "gx:track" extension tag.

Any feedback will be appreciated.
