# GpxPod Nextcloud application

![CI](https://github.com/julien-nc/gpxpod/workflows/CI/badge.svg?branch=master&event=push)
[![coverage report](https://github.com/julien-nc/gpxpod/raw/gh-pages/coverage.svg)](https://julien-nc.github.io/gpxpod/)
[![Crowdin](https://d322cqt584bo4o.cloudfront.net/gpxpod/localized.svg)](https://crowdin.com/project/gpxpod)

Application to display gpx, kml, igc, fit and tcx files collections,
view elevation profiles and tracks stats, filter tracks,
 color tracks by speed, elevation or pace and compare divergent parts of similar tracks.

🌍 Help us to translate this app on [GpxPod Crowdin project](https://crowdin.com/project/gpxpod).

GpxPod :

* 🗺 can display gpx/kml/tcx/igc/fit files anywhere in your storage. .fit files will be converted and displayed only if **GpsBabel** is found on the server system.
* 📏 supports metric, english and nautical measure systems
* 📈  draws elevation, speed or pace interactive chart
* 🖼  displays geotagged pictures found in selected directory
* generates public links pointing to a track/folder. This link can be used if the file/folder is shared by public link
* can correct tracks elevations if SRTM.py (gpxelevations) is found on the server's system
* ⚖  can make global comparison of multiple tracks
* ⚖  can make visual pair comparison of divergent parts of similar tracks
* proudly uses MaplibreGL and Maptiler
* adds possibility to view .gpx files directly from the "Files" app

This app is tested under Nextcloud 16 with Firefox and Chromium.

Link to Nextcloud application website : https://apps.nextcloud.com/apps/gpxpod

## Donation

I develop this app during my free time.

* [Donate with Paypal : <img src="https://gitlab.com/julien-nc/gpxpod-oc/wikis/uploads/6e360ae31aa5730bfc1362e88ae791f9/paypal-donate-button.png" width="80"/>](https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=66PALMY8SF5JE) (you don't need a paypal account).
* [Donate with Liberapay : ![Donate using Liberapay](https://liberapay.com/assets/widgets/donate.svg)](https://liberapay.com/eneiluj/donate)

## Known issues

* _WARNING_, kml conversion will NOT work with recent kml files using the proprietary "gx:track" extension tag.

Any feedback will be appreciated.
