# GpxPod Nextcloud application

[![Crowdin](https://d322cqt584bo4o.cloudfront.net/gpxpod/localized.svg)](https://crowdin.com/project/gpxpod)

Application to display gpx, kml, igc, fit and tcx files collections,
view elevation profiles and tracks stats, filter tracks,
 color tracks by speed, elevation or pace and compare divergent parts of similar tracks.

🌍 Help us to translate this app on [GpxPod Crowdin project](https://crowdin.com/project/gpxpod).

GpxPod :

* 🗺 can display gpx/kml/tcx/igc/fit files anywhere in your files, files shared with you, files in folders shared with you. fit files will be converted and displayed only if **GpsBabel** is found on the server system.
* 📏 supports metric, english and nautical measure systems
* 🗠  draws elevation, speed or pace interactive chart
* 🖻  displays geotagged pictures found in selected directory
* 🖧  generates public links pointing to a track/folder. This link can be used if the file/folder is shared by public link
* 🗁  allows you to move selected track files
* 🗠  can correct tracks elevations if SRTM.py (gpxelevations) is found on the server's system
* ⚖  can make global comparison of multiple tracks
* ⚖  can make visual pair comparison of divergent parts of similar tracks
* 🀆  allows users to add personal map tile servers
* ⚙  saves/restores user options values
* 🖍 allows user to manually set track line colors
* 🕑 detects browser timezone
* 🗬  loads extra marker symbols from GpxEdit if installed
* 🔒 works with encrypted data folder (server side encryption)
* 🍂 proudly uses Leaflet with lots of plugins to display the map
* 🖴  is compatible with SQLite, MySQL and PostgreSQL databases
* 🗁  adds possibility to view .gpx files directly from the "Files" app

This app is tested under Nextcloud 14 with Firefox and Chromium.

This app is under development.

Link to Nextcloud application website : https://apps.nextcloud.com/apps/gpxpod

## Donation

I develop this app during my free time.

* [Donate on Paypal](https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=66PALMY8SF5JE) (you don't need a paypal account).
* [![Donate using Liberapay](https://liberapay.com/assets/widgets/donate.svg)](https://liberapay.com/eneiluj/donate)

## Install

See the [AdminDoc](https://gitlab.com/eneiluj/gpxpod-oc/wikis/admindoc) for installation details (allow SRTM elevation correction, integration in "Files" app, tcx, igc, fit compatibility...)

## Known issues

* bad management of file names including simple or double quotes
* _WARNING_, kml conversion will NOT work with recent kml files using the proprietary "gx:track" extension tag.

Any feedback will be appreciated.
