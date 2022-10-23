# Change Log
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]

## 5.0.0 – 2022-10-23
### Added
- new UI in Vue, use maplibregl-js

### Changed
- a few adjustments in the old UI to work with NC >= 25

## 4.3.0 – 2021-11-14
### Changed
- bump max NC version
- clarify package.json
- redraw all track when any drawing option changes
  [#220](https://gitlab.com/eneiluj/gpxpod-oc/issues/220) @notEvil

### Fixed
- convert track id to string in drawing functions
  [#225](https://gitlab.com/eneiluj/gpxpod-oc/issues/225) @jmechnich

## 4.2.8 – 2021-03-15
### Changed
- improve code quality

### Fixed
- fix mistake when getting program path. it was miraculously working with Php < 8
[!187](https://gitlab.com/eneiluj/gpxpod-oc/-/merge_requests/187) @tasnad
- fix comparison geojson data injection in UI, use initial-state
[#207](https://gitlab.com/eneiluj/gpxpod-oc/issues/207) @eneiluj
- resist to invalid gpx data (missing coords or time) in comparison
[#207](https://gitlab.com/eneiluj/gpxpod-oc/issues/207) @eneiluj

## 4.2.7 – 2021-02-23
### Fixed
- jquery-ui import
[#215](https://gitlab.com/eneiluj/gpxpod-oc/issues/215) @JensErat

## 4.2.6 – 2021-02-07
### Changed
- replace $.ajax() by @nc/axios
- use NC webpack/lint configs
- bump max compatible NC version

### Fixed
- passes lint checks
- sidebar style in comparison page

## 4.2.4 – 2020-11-23
### Fixed
- bug when displaying routes
- black theme

## 4.2.3 – 2020-09-28
### Changed
- adapt to NC 20
- convert GPX speed in tooltips

### Fixed
- Dark theme in NC 20
- fix mapbox-gl loading in NC 20
- fix max speed
  [#156](https://gitlab.com/eneiluj/gpxpod-oc/issues/156) @fragadass
  [#209](https://gitlab.com/eneiluj/gpxpod-oc/issues/209) @gegeweb

## 4.2.2 – 2020-06-03
### Added

### Changed
- big improvement in cumulative elevation gain and max speed algorithms
  [#195](https://gitlab.com/eneiluj/gpxpod-oc/issues/195) @tonda2

### Fixed
- avoid crash by checking if exif functions are available
  [#192](https://gitlab.com/eneiluj/gpxpod-oc/issues/192) @IlRoccOne
- respect open_basedir Php setting
  [#193](https://gitlab.com/eneiluj/gpxpod-oc/issues/193) @IlRoccOne
- add potentially missing DB field if there was a big jump in versions
  [#196](https://gitlab.com/eneiluj/gpxpod-oc/issues/196) @luciocarreras

## 4.2.1 – 2020-03-26
### Fixed
- problem with postgresql when running migration scripts
  [#186](https://gitlab.com/eneiluj/gpxpod-oc/issues/186) @EmJothGeh @mjanssens @r100gs

## 4.2.0 – 2020-03-26
### Added

### Changed
- switched to webpack
- improve chart hover information design

### Fixed
- disable caching, hover points and chart drawing when adding all tracks at once
  [#184](https://gitlab.com/eneiluj/gpxpod-oc/issues/184) @googol42

## 4.1.1 – 2019-12-31
### Added
- option to toggle referrer sending
- display point information on hover

### Changed
- remove IGN from default tile providers
- use standalone Viewer for pictures if possible
- tooltips design
- use photos app instead of gallery if possible
- use public templates for public pages

### Fixed
- mechanism to send referrer
  [!177](https://gitlab.com/eneiluj/gpxpod-oc/merge_requests/177) @MayeulC
- dates were always displayed with UTC timezone
  [#175](https://gitlab.com/eneiluj/gpxpod-oc/issues/175) @gegeweb
- color changing
- fix dark theme compat
- sort directories by name
  [#182](https://gitlab.com/eneiluj/gpxpod-oc/issues/182) @nemihome

## 4.1.0 – 2019-11-03
### Added
- PhpUnit tests
- option to select all tracks after folder change
  [#170](https://gitlab.com/eneiluj/gpxpod-oc/issues/170) @klakla2
- vector tile support with Mapbox and OpenMapTile servers
  [#133](https://gitlab.com/eneiluj/gpxpod-oc/issues/133) @labero

### Changed
- use IQueryBuilder for DB queries
- default sort order: date desc
- able to choose what to draw: tracks, routes or waypoints or all

### Fixed
- kml fallback conversion

## 4.0.5 – 2019-07-27
### Fixed
- compatibility with PgSQL
  [!168](https://gitlab.com/eneiluj/gpxpod-oc/merge_requests/168) @doc75
  [#168](https://gitlab.com/eneiluj/gpxpod-oc/issues/168) @kaistian

## 4.0.4 – 2019-07-25
### Changed
- photos look much better now (rewritten from scratch)
- show photo dates
- store non-geotagged pics in DB to avoid parsing them on each folder load
- delete DB picture entry when file does not exist anymore
- improve picture tooltip design
- improve exif reading, make it default and use Imagick as fallback
- improve max speed calculation (accumulate 3 segments)
  [#156](https://gitlab.com/eneiluj/gpxpod-oc/issues/156) @fragadass
- improve cumulative positive elevation gain/loss (consider gain/loss only if done in more than 50m)

### Fixed
- generate metadata when visiting public pages
  [#161](https://gitlab.com/eneiluj/gpxpod-oc/issues/161) @klakla2

## 4.0.2 – 2019-07-21
### Fixed
- Deletion query problems again with SQLite
  [#162](https://gitlab.com/eneiluj/gpxpod-oc/issues/162) @tuxra

## 4.0.1 – 2019-07-13
### Fixed
- PostgreSQL compat
  [#162](https://gitlab.com/eneiluj/gpxpod-oc/issues/162) @severinkaderli and @doc75

## 4.0.0 – 2019-07-12
### Added

### Changed
- no more automatic folder list, manual adding + recursive adding + optional recursive display
  [#157](https://gitlab.com/eneiluj/gpxpod-oc/issues/157) @tropli
- optimize picture management, store coords in DB to avoid reading files on each load
  [#157](https://gitlab.com/eneiluj/gpxpod-oc/issues/157) @tropli
- make showmounted, showshared and showpicsonlyfold true by default
- improve options design
- improve padding on automatic zoom

### Fixed
- a few translatable string in UI
- fix picture names with quotes inside
  [#157](https://gitlab.com/eneiluj/gpxpod-oc/issues/157) @tropli
- fix files/folders names with quotes

## 3.0.3 – 2019-04-09
### Changed
- max NC version: 16
- app icon

## 3.0.2 – 2019-02-26
### Changed
- improve option toggle title design
- make app description translatable

### Fixed
- fix bad float formatting when generating markers information
  [#146](https://gitlab.com/eneiluj/gpxpod-oc/issues/146) @KapiteinHaak
- fix track drawing and marker stats when some lat/lon are missing
- default sort by date

## 3.0.1 – 2019-01-03
### Added
- add opentopomap tile server
  [#137](https://gitlab.com/eneiluj/gpxpod-oc/issues/137) @dmsoler

### Changed
- update max zoom for base tileservers
- change a few tile servers to https

### Fixed
- fix script loading which was leading to css conflict in spreed app
  [#139](https://gitlab.com/eneiluj/gpxpod-oc/issues/139) @lachmanfrantisek

## 3.0.0 – 2018-12-09
### Added
- add option to toggle folders with pictures only
  [#131](https://gitlab.com/eneiluj/gpxpod-oc/issues/131) @e-gor

### Changed
- replace tablesorter with sorttable
- bump to NC 15
  [#136](https://gitlab.com/eneiluj/gpxpod-oc/issues/136) @klakla2

### Fixed

## 2.3.2 – 2018-11-18
### Added
- add options to toggle shared files display and external storage exploration
  [#124](https://gitlab.com/eneiluj/gpxpod-oc/issues/124) @tavinus
- add links to tile servers and WMS (OSM wiki)

### Changed
- now able to select a folder with only pictures
- update leaflet to 1.3.4 and leaflet.polylinedecorator
- better SQL queries design
- improve style, adapt to theme with css variables
- use php-imagick in priority if available
- use NC logger for gpx parsing errors and more
- put SRTM cache in data directory
- improve option management, save only what's needed, use NC user config system

### Fixed
- correct filetypes action icon (context menu in Files app)
- avoid jpg to gpx conversion
- fix Imagick presence detection
- remove OC\_App which was here just for ownCloud compatibility which was dropped
- preserve aspect, avoid cropping of geotagged pictures except in popup
  [#51](https://gitlab.com/eneiluj/gpxpod-oc/issues/51) @hk10
- zoom on pictures when there is no track
- no more temporary directory to read pictures exif data
  [#129](https://gitlab.com/eneiluj/gpxpod-oc/issues/129) @GAS85
- no more temporary directory to convert kml and tcx files
- no more temporary directory at all
- track table overflow : scroll

## 2.3.1 – 2018-08-25
### Changed
- make notifications look like PhoneTrack's ones
### Fixed
- sidebar style problems with NC14
  [#120](https://gitlab.com/eneiluj/gpxpod-oc/issues/120) @eneiluj
- fontawesome missing icons

## 2.3.0 – 2018-08-14
### Added
- new color criteria : extension
  [#109](https://gitlab.com/eneiluj/gpxpod-oc/issues/109) @jkaberg
- add support for link tag in tracks, routes and waypoints
  [#74](https://gitlab.com/eneiluj/gpxpod-oc/issues/74) @eneiluj
- add moving average pace to popup table
  [#107](https://gitlab.com/eneiluj/gpxpod-oc/issues/107) @Speranskiy

### Changed
- improve tcx convertion, handle Activity tag
- auto zoom now includes pictures
  [#103](https://gitlab.com/eneiluj/gpxpod-oc/issues/103) @jeekajoo
- update svg icons
- set max width of images in popups : 300px
  [#115](https://gitlab.com/eneiluj/gpxpod-oc/issues/115) @geotheory
- upgrade fontawesome
- adapt to NC14, drop OC (sorry), drop NC<=13
  [#120](https://gitlab.com/eneiluj/gpxpod-oc/issues/120) @eneiluj

### Fixed
- use waypoint style for route points
  [#99](https://gitlab.com/eneiluj/gpxpod-oc/issues/99) @Robtenik
- fix pace for routes
- bug in gpx parsing, update end date after each trkseg
- in gpx content : replace xml version attribute to 1.0 when it's 1.1
  [#104](https://gitlab.com/eneiluj/gpxpod-oc/issues/104) @cbosdo
- fix bug when bad color criteria values, check for infinity
  [#110](https://gitlab.com/eneiluj/gpxpod-oc/issues/110) @Vebryn
- fix 'share button' disapearing from track table
  [#112](https://gitlab.com/eneiluj/gpxpod-oc/issues/112) @Gymnae
- fix generic 'false' value in SQL query
  [#117](https://gitlab.com/eneiluj/gpxpod-oc/issues/117) @wiwiec
- fix label width and word-wrap in right option column
  [#118](https://gitlab.com/eneiluj/gpxpod-oc/issues/118) @klakla2
- do not disable autozoom when sharing a folder with 'track=all'
  [#121](https://gitlab.com/eneiluj/gpxpod-oc/issues/121) @hellmachine2000
- use php imagick when exif_read_data fails to read geotags from images
  [#114](https://gitlab.com/eneiluj/gpxpod-oc/issues/114) @ciropom

## 2.2.2 – 2018-01-07
### Added
- option to display route points
  [#99](https://gitlab.com/eneiluj/gpxpod-oc/issues/99) @Robtenik
- many translations from Crowdin

### Changed
- NC13 compliant
- improve fallback tcx convertion, handle Activity tag
  [#102](https://gitlab.com/eneiluj/gpxpod-oc/issues/102) @pipiche

### Fixed
- bug when displaying an empty track
- pace for routes
- gpx parsing, update end date after each trkseg

## 2.2.1 – 2017-11-11
### Added
- fallback IGC parsing without GpsBabel
- follow @dadasign idea of fallback conversion for tcx and kml if gpsBabel is not installed
- ask confirmation before deleting a track
- button to zoom on specific track
- display track when hover on marker
- add button in popups to draw track

### Changed
- put track buttons (table) in dropdown menu
- change date and number inputs to HTML5 type : number and date
- manage translations with Crowdin

### Fixed
- include line weight in public links
- mistake in OSM fr definition
- correct opencyclemap and transport URLs
  [#91](https://gitlab.com/eneiluj/gpxpod-oc/issues/91) @LittleHuba
- remove BOM header (making chrom\* reject XML)
  [#93](https://gitlab.com/eneiluj/gpxpod-oc/issues/93) @fti7
- pass custom tile/overlay servers to public pages
  [#95](https://gitlab.com/eneiluj/gpxpod-oc/issues/95) @lebochequirit

## 2.2.0 – 2017-08-20
### Added
- option to choose which track (PRES or GNSS) is kept for IGC conversion
  [#78](https://gitlab.com/eneiluj/gpxpod-oc/issues/78) @tomashora
- add pace color criteria (displays the time it took to move the last km/mi/nmi)
- add buttons to reload or 'reload and process' current folder
  [#81](https://gitlab.com/eneiluj/gpxpod-oc/issues/81) @e-alfred
- add public page url option 'sidebar' which toggles sidebar apparition on page load
  [#86](https://gitlab.com/eneiluj/gpxpod-oc/issues/86) @Gymnae
- remove X-Frame-Options header for public pages to allow them to be embedded
  [#85](https://gitlab.com/eneiluj/gpxpod-oc/issues/85) @Gymnae

### Changed
- update leaflet to 1.2.0
- update sidebarv2
- replace measurecontrol with Leaflet.LinearMeasurement
- remove L.draw

### Fixed
- make call to getMeasureUnit synchronous
- adapt L.Control.Elevation to work with firefox 57
- fix all problems (afaik) related to file names : dict indexes, share links URLs and download links URLs
  [#84](https://gitlab.com/eneiluj/gpxpod-oc/issues/84) @bperel
- get rid of double quotes in gpx names/strings
  [#88](https://gitlab.com/eneiluj/gpxpod-oc/issues/88) @klakla2
- zoom issue when loading public pages

## 2.1.4 – 2017-06-27
### Added
- add nautical measure system (knot and nautical miles)
  [#71](https://gitlab.com/eneiluj/gpxpod-oc/issues/71) @eneiluj
- new button to move selected tracks
  [#73](https://gitlab.com/eneiluj/gpxpod-oc/issues/73) @eneiluj
- import from gpxedit : support for WMS tile and overlay servers. base and user servers
- display metadata-link in track popup
  [#74](https://gitlab.com/eneiluj/gpxpod-oc/issues/74) @eneiluj
- dynamic url change when subfolder changes in normal page
- button to clean all tracks metadata in database for current user
- add link to view track in GpxMotion in track table if installed (autoplay)
  [#75](https://gitlab.com/eneiluj/gpxpod-oc/issues/75) @klakla2

### Changed
- remove process type choice, modified files are now automatically processed
- update moment timezone js
- style of custom tile server management
- convert kml in Php

### Fixed
- fix leaflet.hotline when min and max values are the same, draw black line instead of failing
- fix bad json when newline in gpx "name" tag
  [#70](https://gitlab.com/eneiluj/gpxpod-oc/issues/70) @markuman
- hide custom tiles management and clean buttons when page is public
- now passing simple hover option value to public pages

## 2.1.2 – 2017-05-16
### Added
- button to delete individual track with confirmation
  [#54](https://gitlab.com/eneiluj/gpxpod-oc/issues/54) @AlterDepp
- optional direction arrows along track lines
- new feature : add personal overlay tile server
  [#66](https://gitlab.com/eneiluj/gpxpod-oc/issues/66) @Demo82

### Changed
- delete the selection now asks for user confirmation
  [#54](https://gitlab.com/eneiluj/gpxpod-oc/issues/54) @AlterDepp
- move tile/overlay server list from JS code to PHP file, now easier to modify
  [#66](https://gitlab.com/eneiluj/gpxpod-oc/issues/66) @Demo82
- remove deletion confirmation as it is possible to restore files in files app

### Fixed
- check if bounds are valid before fitBounds
- bad date formats were rejected by new moment.js
- adapt css for Nextcloud 12, still works with 11
- overlapping of xAxis title with axis values in elevation graph
- remove all synchronous ajax calls

## 2.1.1 – 2017-05-01
### Added
- new picture display mode : spiderfied popups
- option to select between preview or original picture in colorbox
  [#51](https://gitlab.com/eneiluj/gpxpod-oc/issues/51) @hk10
- timezone support in elevation time in chart
  [#52](https://gitlab.com/eneiluj/gpxpod-oc/issues/52) @RobinP_1
- support english measure system in gpxpod and gpxvcomp
  [#53](https://gitlab.com/eneiluj/gpxpod-oc/issues/53) @brianinkc
- pass many options with GET parameters to publicFolder and publicFile links
  [#56](https://gitlab.com/eneiluj/gpxpod-oc/issues/56) @klakla2
- option to toggle chart display
- add extra option to publicFolder : "track=all" to display all tracks on page load
  [#56](https://gitlab.com/eneiluj/gpxpod-oc/issues/56) @klakla2
- add buttons to select/deselect all tracks
  [#59](https://gitlab.com/eneiluj/gpxpod-oc/issues/59) @simsalabimbam
- add button to delete selected tracks files
  [#54](https://gitlab.com/eneiluj/gpxpod-oc/issues/54) @AlterDepp
- add little python script to check useless/missing translations for a specific language

### Changed
- extract pictures geotagging information with Php, no more GpsBabel needed for that
  [#50](https://gitlab.com/eneiluj/gpxpod-oc/issues/50) @hk10
- in public folder page, only show directory name instead of the whole path
- adapt makefile to include Owncloud code signing
- make different build archives for Nextcloud and Owncloud

### Fixed
- compatibility with Owncloud was broken because of appManager-getAppPath() in controllers
- small design fixes in gpxvcomp
- if file/folder is not shared in Files app, no GpxPod public link is generated
- fix publicFolder and publicFile to work with folders/files shared with the user who made the public share
  [#55](https://gitlab.com/eneiluj/gpxpod-oc/issues/55) @klakla2
- download url in public pages was wrong if "path" GET parameter was empty or absent
- make temp dirs independent from nextcloud/owncloud data dir to fix issues with LDAP users
  [#58](https://gitlab.com/eneiluj/gpxpod-oc/issues/58) @Demo82
- fix all french translations
- parse gpx content as xml to correctly read potential CDATA
- bad GET parameter name for layer name in public links

## 2.1.0 – 2017-03-30
### Added
- integration in "Files" and "File sharing" for .gpx files and directories
  [#44](https://gitlab.com/eneiluj/gpxpod-oc/issues/44) @rugk
- add gpx filetype icon for Files app
- animation when toggle option
- animation when add/remove tile server
- makefile signs the app code
- spiderfication of picture markers with OverlappingMarkerSpiderfier-Leaflet
  [#47](https://gitlab.com/eneiluj/gpxpod-oc/issues/47) @RobinP_1

### Changed
- get rid of python geotagging extraction script (pictures.py), now done by gpsbabel
- use OC alert instead of JS alert
- update french translations
- reimplement public link system with "Files" token
- favicon background color
- update geocoder
- ask for preview instead of original image : faster loading in colorbox

### Fixed
- bad use of array\_map in controller
- in some browsers, background-image for picture in popup was not shown
  [#46](https://gitlab.com/eneiluj/gpxpod-oc/issues/46) @RobinP_1
- bad decodeURI => decodeURIComponent
- do not put @NoCSRFRequired everywhere in controllers
- remove escapeshellcmd which escapes characters in file names, keep escapeshellarg

## 2.0.2 – 2017-03-07
### Added
- add support for FIT files
  [#42](https://gitlab.com/eneiluj/gpxpod-oc/issues/42) @pvanek
- click on color in track table allows user to change the track color
  [#41](https://gitlab.com/eneiluj/gpxpod-oc/issues/41) @coelner
- add loading percentage near spinner when getting tracks
- add loading percentage when hovering to get tracks

### Changed
- factorize all gpsbabel conversions in controller
- make tooltip class dynamic so they follow the track color wether it's choosed by user or no
- factorize addtrack\* code
- cleaner elevation chart, title, axis titles, margins
- stop hover ajax when removing mouse from table line

### Fixed
- app is now compliant to occ check-code
- add margin to popup title to avoid overlay with popup close button
  [#40](https://gitlab.com/eneiluj/gpxpod-oc/issues/40) @coelner
- line borders for simplified hover
- fix mess between hover and elevation correction, now impossible to hover when correction was asked
- fix bad management of gpxelevation exec failure
- better deletion of temporary directories in cache

## 2.0.1 – 2017-01-21
### Added
- IGC file type support
  [#38](https://gitlab.com/eneiluj/gpxpod-oc/issues/38) @tomashora
- add option to draw simplified track on hover instead of downloading the whole track
  [#36](https://gitlab.com/eneiluj/gpxpod-oc/issues/36) @Slipeer
- put title in elevation/speed chart to remind which track is concerned
- save/restore current selected tile layer in user options

### Changed
- replace checkbox by spinner when track is loading
  [#36](https://gitlab.com/eneiluj/gpxpod-oc/issues/36) @Slipeer
- zoom on all tracks already drawn when autozoom on a new track
- change osmfr tiles url to https, update hikebike url
  [#37](https://gitlab.com/eneiluj/gpxpod-oc/issues/37) @Slipeer

### Fixed
- support milliseconds in gpx time
  [#34](https://gitlab.com/eneiluj/gpxpod-oc/issues/34) @akki42
- bad number formatting caused wrong elevation display when >1000 
  [#33](https://gitlab.com/eneiluj/gpxpod-oc/issues/33) @FAllemandou
- remove useless libxml_use_internal_errors call to support more php versions
  [#39](https://gitlab.com/eneiluj/gpxpod-oc/issues/39) @FAllemandou
- keep showing loading animation untill there is no more track loading (when multiple)

## 2.0.0 – 2017-01-05
### Added
- option to toggle track line border
- option to choose track line width
- line borders
- display marker name in marker popup
- russian locale
  [#29](https://gitlab.com/eneiluj/gpxpod-oc/issues/28) @Slipeer

### Changed
- no more python marker processing, done in PHP by the controller
- no more python track translation to geojson, let the JS parse the GPX
- no more python track comparison, done in PHP by the controller
- display question mark image if symbol is unknown
- graph shows speed values if colored by speed
- remove slope coloration criteria
- no embeded gpx data in public dir page anymore, use ajax requests
- elevation correction creates new files with "\_corrected" in their name

### Fixed
- use moment.js just for valid dates
- escape and quote DB strings with abstract DBconnection (advised by @Slipeer)
  [#30](https://gitlab.com/eneiluj/gpxpod-oc/issues/30) @eneiluj

## 1.1.0 – 2016-12-16
### Added
- add symbol (sym tag) support
  [#28](https://gitlab.com/eneiluj/gpxpod-oc/issues/28) @eneiluj
- link to edit in GpxEdit if installed
- support for GpxEdit extra symbols

### Fixed
- clear cache if process all files
- bugs when subfolder is /
- bad initialization of default marker style in public pages
- send referrer, IGN tiles work now

## 1.0.9 – 2016-12-02
### Added
- add track/route list for a file in its popup
- show track/route/waypoint comment and description in popup
- add track/route name in line tooltip
- add elevation/slope/speed in line tooltip for colored drawing
- add leaflet.measurecontrol plugin
  [#26](https://gitlab.com/eneiluj/gpxpod-oc/issues/26) @eneiluj

### Changed
- update Control.Minimap
- don't show elevation and popup if there is no route/track but only waypoints
- highlight line on hover
- move share and elevation correction links from popup to table
- adapt share dialog text : none if verified, explain if share is impossible

### Fixed
- fix problem on parsing gpx with no track/route/waypoint
- fix huge bug when selecting root folder, getmarkers and processTrackElevations fixed
- fix bug when trying to color tracks when the values does not move or is always 0
- handle routes for colored draws and fix mistakes on min/max values
- correct date begin/end : use date comparison instead of track/route apparition order
- mistake on waypoint style for colored tracks, tooltip was always non-permanent
- python script now writes utf-8 files
- global stats in python script are now really global for all tracks/routes
- bugs in marker generation

## 1.0.8 – 2016-11-23
### Added
- save/restore options for logged user
- option to choose picture style (popup/small/big marker)
  [#25](https://gitlab.com/eneiluj/gpxpod-oc/issues/25) @eneiluj
- add average speed and average moving speed in comparison table

### Changed

### Fixed
- bug when python PIL is not available
- deletion of bad parameter given to getGeoPicsFromFolder() in controller
  [#20](https://gitlab.com/eneiluj/gpxpod-oc/issues/20) @eneiluj
- bug in file cleaning, bad use of array\_unique
  [#22](https://gitlab.com/eneiluj/gpxpod-oc/issues/22) @eneiluj
- python script do not need to be exectuable now
  [#23](https://gitlab.com/eneiluj/gpxpod-oc/issues/23) @eneiluj
- jquery.colorbox was brought by "First run wizard" app, now included
  [#21](https://gitlab.com/eneiluj/gpxpod-oc/issues/21) @eneiluj
- avoid JS error when failed to get options values by ajax

## 1.0.7 – 2016-11-14
### Added
- option to choose waypoint style
- show elevation, lat, lng in waypoint popup
- ability to display geotagged jpg pictures on the map
- pictures slideshow with colorbox
- pictures work in public dir link
- use NC/OC thumbnails to display pictures on the map
- options block hidden by default

### Fixed
- fix bug in geojson generation for waypoint-only files

## 0.9.15 to 1.0.6 - 2016-11-07
- fix marker generation for gpx files with waypoints only
- improve waypoints display : use tooltips, limit text width, same color as the track
- add option to choose whether track or waypoints or both should be displayed
- clean DB from unexisting files on main page load
- fix z-index problems in UI
- update to Leaflet 1.0
- update MarkerCluster and ActiveLayer Leaflet plugins
- remove ActiveArea Leaflet plugin
- add Leaflet tooltips to tracks and markers (in comparison too)
- add caching for colored tracks
- show elevation graph for colored tracks
- change Jquery-UI button for normal ones
- put more colors in UI
- improve time display in Elevation Leaflet plugin
- larger checkboxes, smaller popups
- keep state of autozoom, autopopup, tableUTD, activeLayer in public folder link
- fix public links with spaces
- responsive design improved (sidebar and layer control)
- add icons
- check if file/folder is shared in Files when creating the gpxpod public link
- add lots of icons
- fix small design issues
- custom tiles servers changes now take effect directly
- add german translation
- translation system ready, french and english languages available
- show elevation chart by default
- improve public link display, now shows a dialog
- use fontawesome icons for loading animation ,share links, + and - buttons etc...
- design simplification in comparison
- highlight stat table columns of selected tracks in comparison
- zoom to fit markers after folder change
- cleaner sidebar tabs content
- make SQL queries compatible with PostgreSQL
- now works if app is installed in alternative app folder
- now able to list track that cross current map view (useful to find tracks that pass through a precise area)
- add version display in UI
- split controller code in three parts
- fix bug on undefined index with some php configurations
- improve public track link (coloration added)
- add public folder link feature !
- if SRTM.py is installed and gpxelevations is found in PATH, add process options to correct elevations
- ability to ask elevation correction for one specific track in its popup
- add ability to add/remove personal tile servers
- small design changes and cleaner UI behaviour
- public links now work with files in a shared folder (with public link without password)
- make database queries compatible with MySQL
- add favicon for browser tab
- add cache in UI to load a geojson faster if it has already been loaded
- add global stats in a table to compare entire tracks
- fix small gaps in comparison values between divergent and non-divergent parts
- add buttons to delete all geojson and markers files
- now uses the owncloud database to store geojson data and markers. faster and transparent.
- clean database from tracks if the file was deleted
- fix display bug in public link
- new feature : gpxpod allows to share a public link of a track (.gpx .gpx.geojson and gpx.marker have to be publicly shared in files)
- now works with encrypted data folder (server side encryption)
- now considers files shared with you and files in folders shared with you
- few bug fixes
- add timezone support in gpxpod : automatic browser timezone detection and manual timezone change in UI. Specify current timezone in displayed dates.
- add timezone support in gpxvcomp : uses browser timezone to display dates
- add option to list tracks in left table if their bounds rectangle is partially visible
- fix bug on nested gpx directories. markers are not found recursively anymore
- design is now more compact
- text changes
- add option to choose wether the table shows all tracks or juste the one visible in current map view
- animate logo when loading
- gpx comparison revolution ;) now really compare pairs, faster display (better geojson produced), no more color mistake...
- small fixes, text, colors

## 0.9.1 to 0.9.14
- chrome(ium) JS compatibility
- fixes in track comparison
- add .tcx files compatibility (convertion with gpxbabel)
- better error display in python process
- fix small distance calculation problem (negative cosinus...)
- python process interruption if multiple calls
- better marker production, speed improvement
- easier folder selection in UI
- make folder change dynamic with ajax calls
- improve python compatibility (<2.7) and error management
- display python output in UI
- fix bug : nested php calls sometimes producing 'Only variables should be passed by reference' error
- move arrows in table header to avoid text to be hidden
- fix python script : fallback to classic map is multiprocessing is not available or Pool does not work (CentOS issue)
- Fix bug with OC method
- now considers files with uppercase extensions
- ability to select track scan type (none, new tracks only, all)
- leaflet geocoder (to search for locations) works
- changed default map to openstreetmap
- display time in elevation chart label
- remove personnal import of jquery that caused menu bug
- adapt gpxvcomp to gpxpy problem with course tag
- fix latest gpxpy failure caused by float in "course" tag
- python3 compatibility
- track processing more resistent to malformed gpx files
- cleaner MVC implementation
- included automatic KML convertion if GPXBabel is found on the server system
- removed yield operator to make GpxPod compatible with more PHP versions
- Updated leaflet from 0.7.3 to 0.7.7
- fix bad url for files download links
- Works for any data folder location config
- Compatible with more php versions (in reaction to 'slapps' bug report)
- List all directories that include gpx files. No more restriction on file organization.
- Trying to respect coding rules
- Security might be ok
- Integration of track divergence comparison (gpxvcomp)
- Visual info during loading
