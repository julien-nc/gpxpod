# GpxPod owncloud/nextcloud application

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

!!! GpxPod now uses the owncloud database to store meta-information. If you want to get rid of the .geojson, .geojson.colored and .markers produced by previous versions, there are two buttons at the bottom of the "Help" tab in user interface. !!!

GpxPod proudly uses Leaflet with lots of plugins to display the map.

This app is tested under Owncloud/Nextcloud 9.0 with Firefox and Chromium.
This app is under development.

Link to Owncloud application website : https://apps.owncloud.com/content/show.php/GpxPod+again?content=174733

## Install

No special installation instruction except :
!! Server needs python2.x or 3.x "gpxpy" and "geojson" module to work !!
They may be installed with pip.

For example, on Debian-like systems :

```
sudo apt-get install python-pip
sudo pip install gpxpy geojson
```
or on Redhat-like systems :
```
sudo yum install python-pip
sudo pip install gpxpy geojson
```

Then put gpxpod directory in the Owncloud/Nextcloud apps to install.
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
tar xvf 174733-gpxpod-1.0.0.tar.gz
```

### Post install precautions

Just in case, make python scripts executables :
```
cd /path/to/owncloud/apps
chmod +x gpxpod/*.py
```

## Known issues

* bad management of file names including simple or double quotes
* _WARNING_, kml conversion will NOT work with recent kml files using the proprietary "gx:track" extension tag.

Any feedback will be appreciated.
