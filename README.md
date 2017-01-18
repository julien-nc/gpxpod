# GpxPod owncloud/nextcloud application

If you want to help to translate this app in your language, take the english=>french files in "l10n" directory as examples.

This app's purpose is to display gpx, kml and tcx files collections,
view elevation profiles and tracks stats, filter tracks,
 color tracks by speed, slope, elevation and compare divergent parts of similar tracks.

It's compatible with SQLite, MySQL and PostgreSQL databases.

It works with gpx/kml/tcx files anywhere in your files, files shared with you, files in folders shared with you.
kml and tcx files will be displayed only if GpsBabel is found on the server system.

Elevations can be corrected for entire folders or specific track if SRTM.py (gpxelevations) is found.

Personal map tile servers can be added.

It works with encrypted data folder (server side encryption).

A public link pointing to a specific track can be shared if the corresponding gpx file is already shared by public link.

!!! GpxPod now uses the owncloud database to store meta-information. If you want to get rid of the .geojson, .geojson.colored and .markers produced by previous versions, there are two buttons at the bottom of the "Settings" tab in user interface. !!!

GpxPod proudly uses Leaflet with lots of plugins to display the map.

This app is tested under Owncloud 9.0/Nextcloud 11 with Firefox and Chromium.

This app is under development.

Link to Owncloud application website : https://apps.owncloud.com/content/show.php/GpxPod+again?content=174733

Link to Nextcloud application website : https://apps.nextcloud.com/apps/gpxpod

## Donation

I develop this app during my free time. You can make a donation to me on Paypal. [Click HERE to make a donation](https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=66PALMY8SF5JE) (you don't need a paypal account)

## Install

No more python dependencies !

Put gpxpod directory in the Owncloud/Nextcloud apps to install.
There are several ways to do that :

### Clone the git repository

```
cd /path/to/owncloud/apps
git clone https://gitlab.com/eneiluj/gpxpod-oc.git gpxpod
```

### Download from apps.owncloud.org

Extract gpxpod archive you just downloaded from apps.owncloud.org :
```
cd /path/to/owncloud/apps
tar xvf 174733-gpxpod-2.0.0.tar.gz
```

## Known issues

* bad management of file names including simple or double quotes
* _WARNING_, kml conversion will NOT work with recent kml files using the proprietary "gx:track" extension tag.

Any feedback will be appreciated.
