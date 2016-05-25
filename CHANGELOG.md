*0.9.21*
* add buttons to delete all geojson and markers files
* now uses the owncloud database to store geojson data and markers. faster and transparent.
* fix display bug in public link

*0.9.20*
* new feature : gpxpod allows to share a public link of a track (.gpx .gpx.geojson and gpx.marker have to be publicly shared in files)

*0.9.19*
* now works with encrypted data folder (server side encryption)
* now considers files shared with you and files in folders shared with you
* few bug fixes

*0.9.18*
* add timezone support in gpxpod : automatic browser timezone detection and manual timezone change in UI. Specify current timezone in displayed dates.
* add timezone support in gpxvcomp : uses browser timezone to display dates

*0.9.17*
* add option to list tracks in left table if their bounds rectangle is partially visible
* fix bug on nested gpx directories. markers are not found recursively anymore
* design is now more compact
* text changes

*0.9.16*
* add option to choose wether the table shows all tracks or juste the one visible in current map view
* animate logo when loading

*0.9.15*
* gpx comparison revolution ;) now really compare pairs, faster display (better geojson produced), no more color mistake...
* small fixes, text, colors

*0.9.1 to 0.9.14*
* chrome(ium) JS compatibility
* fixes in track comparison
* add .tcx files compatibility (convertion with gpxbabel)
* better error display in python process
* fix small distance calculation problem (negative cosinus...)
* python process interruption if multiple calls
* better marker production, speed improvement
* easier folder selection in UI
* make folder change dynamic with ajax calls
* improve python compatibility (<2.7) and error management
* display python output in UI
* fix bug : nested php calls sometimes producing 'Only variables should be passed by reference' error
* move arrows in table header to avoid text to be hidden
* fix python script : fallback to classic map is multiprocessing is not available or Pool does not work (CentOS issue)
* Fix bug with OC method
* now considers files with uppercase extensions
* ability to select track scan type (none, new tracks only, all)
* leaflet geocoder (to search for locations) works
* changed default map to openstreetmap
* display time in elevation chart label
* remove personnal import of jquery that caused menu bug
* adapt gpxvcomp to gpxpy problem with course tag
* fix latest gpxpy failure caused by float in "course" tag
* python3 compatibility
* track processing more resistent to malformed gpx files
* cleaner MVC implementation
* included automatic KML convertion if GPXBabel is found on the server system
* removed yield operator to make GpxPod compatible with more PHP versions
* Updated leaflet from 0.7.3 to 0.7.7
* fix bad url for files download links
* Works for any data folder location config
* Compatible with more php versions (in reaction to 'slapps' bug report)
* List all directories that include gpx files. No more restriction on file organization.
* Trying to respect coding rules
* Security might be ok
* Integration of track divergence comparison (gpxvcomp)
* Visual info during loading
