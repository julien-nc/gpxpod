*0.9.14*
* chrome(ium) JS compatibility
* fixes in track comparison

*0.9.13*
* add .tcx files compatibility (convertion with gpxbabel)
* better error display in python process
* fix small distance calculation problem (negative cosinus...)

*0.9.12*
* python process interruption if multiple calls
* better marker production, speed improvement
* easier folder selection in UI

*0.9.11*
* make folder change dynamic with ajax calls
* improve python compatibility (<2.7) and error management
* display python output in UI

*0.9.10*
* fix bug : nested php calls sometimes producing 'Only variables should be passed by reference' error
* move arrows in table header to avoid text to be hidden
* fix python script : fallback to classic map is multiprocessing is not available or Pool does not work (CentOS issue)

*0.9.9*
* Fix bug with OC method
* now considers files with uppercase extensions

*0.9.8*
* ability to select track scan type (none, new tracks only, all)
* leaflet geocoder (to search for locations) works
* changed default map to openstreetmap

*0.9.7*
* display time in elevation chart label
* remove personnal import of jquery that caused menu bug
* adapt gpxvcomp to gpxpy problem with course tag

*0.9.6*
* fix latest gpxpy failure caused by float in "course" tag
* python3 compatibility
* track processing more resistent to malformed gpx files

*0.9.5*
* cleaner MVC implementation
* included automatic KML convertion if GPXBabel is found on the server system
* removed yield operator to make GpxPod compatible with more PHP versions

*0.9.4*
* Updated leaflet from 0.7.3 to 0.7.7
* fix bad url for files download links

*0.9.3*
* Works for any data folder location config

*0.9.2*
* Compatible with more php versions (in reaction to 'slapps' bug report)

*0.9.1*
* List all directories that include gpx files. No more restriction on file organization.

*0.9*
* Trying to respect coding rules
* Security might be ok
* Integration of track divergence comparison (gpxvcomp)
* Visual info during loading
