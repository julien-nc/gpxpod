# Change Log
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) 
and this project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]
### Added
- add symbol (sym tag) support
  [#28](https://gitlab.com/eneiluj/gpxpod-oc/issues/28) @eneiluj
- link to edit in GpxEdit if installed

### Fixed
- clear cache if process all files
- bugs when subfolder is /
- bad initialization of default marker style in public pages

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
