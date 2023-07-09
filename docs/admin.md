# GpxPod admin documentation

[[_TOC_]]

## Installation

There are no more python dependencies ! (except for extra features like elevations correction)                                                                               

Put the gpxpod directory in the Nextcloud apps to install.                                
There are several ways to do that :        

### Clone the git repository               

```                                        
cd /path/to/nextcloud/apps                  
git clone https://github.com/julien-nc/gpxpod
```                                        

### Download from https://apps.nextcloud.com/apps/gpxpod                            

Extract the gpxpod archive you just downloaded from the website :                         
```                                        
cd /path/to/nextcloud/apps                  
tar xvf gpxpod-x.x.x.tar.gz                
```                                        
## Setup

You must set a custom API key in the global (or per user) settings, for at least MapTiler. The default key as shipped will not work.
You can get a free MapTiler API key on https://maptiler.com in your "API keys" account settings.

## Integration in "Files" app (optional, tested with ownCloud 10, Nextcloud 11 and 12)

For logged in users, the default action for .gpx file in "Files" app is to view it in GpxPod. The "view in GpxPod" action is also available in context menu for directories and .gpx files.

For non logged in users, when browsing a public file/directory sharing, the context file/directory menu action is also available and redirect to GpxPod public view.

## Main dependencies
GpxPod does not need python librairies anymore since v2.0.0 !

## GpsBabel for kml/tcx/igc/fit conversion
You may want to install GpsBabel if you prefer it than the Php conversion implementations.
All you have to do is to install GpsBabel and be sure its location is in the PATH env variable of the web server user.
GpxPod will detect GpsBabel location. If GpsBabel will be used to perform all conversions (kml, igc, fit, tcx) if it is installed.
Otherwise, Php scripts will handle igc, kml, kmz, fit and tcx files conversion.
