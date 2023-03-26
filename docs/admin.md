# GpxPod admin documentation

[[_TOC_]]

## Installation

There are no more python dependencies ! (except for extra features like elevations correction)                                                                               

Put gpxpod directory in the Nextcloud/Owncloud apps to install.                                
There are several ways to do that :        

### Clone the git repository               

```                                        
cd /path/to/owncloud/apps                  
git clone https://gitlab.com/julien-nc/gpxpod-oc.git gpxpod                             
```                                        

### Download from https://marketplace.owncloud.com or https://apps.nextcloud.com                              

Extract gpxpod archive you just downloaded from the website :                         
```                                        
cd /path/to/owncloud/apps                  
tar xvf gpxpod-x.x.x.tar.gz                
```                                        
## Setup

You must set a custom API key in the global (or per user) settings, for at least MapTiler. The default key as shipped will not work.

## Integration in "Files" app (optional, tested with ownCloud 10, Nextcloud 11 and 12)

If you want to be able to view a folder/file in GpxPod directly from the "Files" app, you have to add a MimeType to your Nextcloud/Owncloud instance. The clean way to do it is to create ```/path/to/nextcloud/config/mimetypemapping.json``` file and set its content :

```
{
    "gpx": ["application/gpx+xml"]
}
```
This will only be effective on new .gpx files. If you want your instance to recognize all existing .gpx files with the correct mimetype, you have to make it rescan the user files (adapt path and web server user it to your context) :

```
sudo -u www-data php /var/www/html/nextcloud/occ files:scan --all
```

For logged in users, the default action for .gpx file in "Files" app is to view it in GpxPod. The "view in GpxPod" action is also available in context menu for directories and .gpx files.

For non logged in users, when browsing a public file/directory sharing, the context file/directory menu action is also available and redirect to GpxPod public view.

## Change icons of .gpx files (optional, tested with ownCloud 10, Nextcloud 11 and 12)

Copy the gpx filetype icon in the right place :
```
sudo -u www-data cp /path/to/nextcloud/apps/gpxpod/img/gpx.svg  /path/to/nextcloud/core/img/filetypes/
```

Create ```/path/to/nextcloud/config/mimetypealiases.json``` file and set its content  to :

```
{
    "application/gpx+xml": "gpx"
}
```

Then run :

```
sudo -u www-data php /path/to/nextcloud/occ maintenance:mimetype:update-js
```

## Main dependencies
GpxPod does not need python librairies anymore since v2.0.0 !

## GpsBabel for kml/tcx/igc/fit conversion
You may want to install GpsBabel to enable fit compatibility in GpxPod. All you have to do is to install GpsBabel and be sure its location is in the PATH env variable of the web server user. GpxPod will detect GpsBabel location. If GpsBabel will be used to perform all conversions (kml, igc, fit, tcx) if it is installed. Otherwise, Php scripts will handle igc, kml and tcx but not fit files conversion.

## Elevation correction with SRTM.py
You also may want to install SRTM.py python lib to enable elevation correction inside GpxPod. When installing this lib, an executable script is provided : gpxelevations. Its location also has to be in the PATH of the web server user in order to be able to use it in GpxPod. Normally it is.

### Install SRTM.py on Ubuntu/Debian*
```
sudo apt install python-pip
sudo pip install gpxpy requests SRTM.py
# Use pip3 if you use Python3 by default.
sudo apt install python3-pip
sudo pip3 install gpxpy requests SRTM.py
```
Then check if the ```gpxelevations``` script is available and working :
```
gpxelevations -h
```


