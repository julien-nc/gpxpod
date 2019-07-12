# GpxPod Nextcloud-Anwendung

Anzeige, Analyse, Vergleich und Freigabe von GPS-Steckendateien.

🌍 Helfen Sie uns, diese App auf [GpxPod Crowdin Projekt](https://crowdin.com/project/gpxpod) zu übersetzen.

GpxPod:

* 🗺 kann GPX-, KML- ,TCX- ,IGC- und FIT-Dateien überall in Ihren Dateien und in Dateien und Dateien in Ordner, die mit Ihnen geteilt werden, angezeigt werden. Fit-Dateien werden nur konvertiert und angezeigt, wenn **GpsBabel** auf dem Serversystem gefunden wird.
* 📏 supports metric, english and nautical measure systems
* 🗠 draws elevation, speed or pace interactive chart
* 🗠 can color track lines by speed, elevation or pace
* 🗠 show track statistics
* ⛛ filter tracks by date, total distance...
* 🖻 displays geotagged pictures found in selected directory
* 🖧 generates public links pointing to a track/folder. This link can be used if the file/folder is shared by public link
* 🗁 allows you to move selected track files
* 🗠 can correct tracks elevations if SRTM.py (gpxelevations) is found on the server's system
* ⚖ can make global comparison of multiple tracks
* ⚖ can make visual pair comparison of divergent parts of similar tracks
* 🀆 allows users to add personal map tile servers
* ⚙ saves/restores user options values
* 🖍 allows user to manually set track line colors
* 🕑 detects browser timezone
* 🗬 loads extra marker symbols from GpxEdit if installed
* 🔒 works with encrypted data folder (server side encryption)
* 🍂 proudly uses Leaflet with lots of plugins to display the map
* 🖴 is compatible with SQLite, MySQL and PostgreSQL databases
* 🗁 adds possibility to view .gpx files directly from the "Files" app

This app is tested under Nextcloud 16 with Firefox and Chromium.

## Install

See the [AdminDoc](https://gitlab.com/eneiluj/gpxpod-oc/wikis/admindoc) for installation details

## Known issues

* [FIXED] bad management of file names including simple or double quotes
* *WARNING*, kml conversion will NOT work with recent kml files using the proprietary "gx:track" extension tag.

Any feedback will be appreciated.