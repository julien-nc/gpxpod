*0.9.23*
* if SRTM.py is installed and gpxelevations is found in PATH, add process options to correct elevations
* add ability to add/remove personal tile servers

*0.9.22*
* public links now work with files in a shared folder (with public link without password)
* make database queries compatible with MySQL
* add favicon for browser tab
* add cache in UI to load a geojson faster if it has already been loaded
* add global stats in a table to compare entire tracks
* fix small gaps in comparison values between divergent and non-divergent parts

*0.9.21*
* add buttons to delete all geojson and markers files
* now uses the owncloud database to store geojson data and markers. faster and transparent.
* clean database from tracks if the file was deleted
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
