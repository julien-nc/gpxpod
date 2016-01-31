# Gpx Pod

This app is a convertion of the standalone gpxpod project into an owncloud application.

Its purpose is to display gpx, kml and tcx files collections,
view elevation profiles and tracks stats, filter tracks,
 color tracks by speed, slope, elevation and compare divergent parts of similar tracks.

It proudly uses Leaflet with many plugins to display the map.

This app is tested under Owncloud 8.2 and 9.0 with Firefox and Chromium.
This app is under development.

Link to Owncloud application website : https://apps.owncloud.com/content/show.php/GpxPod?content=174248

## Install

No special installation instruction except :
!! Server needs python2.x or 3.x "gpxpy" and "geojson" module to work !!
They may be installed with pip.

For example, on debian-like systems :

```
sudo apt-get install python-pip
sudo pip install gpxpy geojson
```

Then put this repo in the apps folder to install GpxPod :

```
cd /path/to/owncloud/apps
git checkout https://gitlab.com/eneiluj/gpxpod-oc.git gpxpod
```

## Features

If GPSBabel is found on the server system, kml and tcx files will be converted to gpx.
_WARNING_, kml conversion will NOT work with recent kml files using the proprietary "gx:track" extension tag.

You can put your files anywhere in the Owncloud files.

GpxPod looks for directories containing gpx, kml or tcx files and allow you to display them on an interactive map.

It also provides comparison between tracks which is done on alternative parts of tracks that have common parts.

Any feedback will be appreciated.

## Known issues

* bad management of file names including simple or double quotes
