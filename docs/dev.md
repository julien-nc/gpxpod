# Developer documentation

[[_TOC_]]

# Overview

This app is composed by 3 parts :

* the html/javascript/jquery GUI which displays tracks and stats
* the php controllers that interact with Owncloud/Nextcloud system (file, shares, encryption etc...)
* the process controller methods used to analyse and compare tracks

The GUI uses ajax calls to controller methods to get information from the server side. It uses JS libraries that are all included in the "js" directory. The map is displayed using Leaflet with many activated extensions. Gpx are retrieved by ajax requests and parsed with JQuery to be displayed with Leaflet.

The php controllers are in charge of DB communication, stats and comparison processing, OC/NC filesystem and filesharing access.

There are 2 process done by the controllers, get the stats of a folder's tracks and compare tracks. The first is used to produce marker information from the gpx files. Those informations are then stored in the database. The second is used to compare a set of gpx files and produce two geojson files for each gpx file pair. The geojsons are then displayed in the GUI with colors on divergent parts and local/global stats.

# GUI

## Map management

Leaflet and extensions...

## Getting the data

### In normal browsing

By ajax...

### In public browsing

By ajax also...

# Controllers overview

## Index

The "index" method of the PageController first looks for gpx/kml/tcx files using the OC/NC file API. From that file list, it determines the interesting folders for the app. Then it checks if SRTM.py is installed and accessible. Then it returns the template populated with the folder list.

## Get markers for the chosen folder

Determine which track should be processed, process, return information...
The controller first get the gpx/kml/tcx file list in the selected folder and compares it with the available meta-info in the database to know which files have to be processed. Then it converts the kml/tcx to gpx if needed. Then it gets the content of the gpx files with OC/NC file API (so that it works with server-side encryption) and put them in a temporary folder. Then it gets the markers for all gpx files in the selected folder to return it as the result of the ajax call.

## Get Gpx

It basically gets the content of a gpx track.

## Public link

### For a file

It checks if the file is shared or if any of its parent folder is shared. If so, it returns the main template with the marker and geojson corresponding to the public track. The javascript in the view is in charge of adapting the display to present the public track.

### For a folder

It checks if the folder is shared and returns the main template.

## Comparison

It gets the content of the gpx files to compare and put them in a temporary folder. Then it calls the processTempDir and getStats methods to produce comparison geojsons. Then it returns the comparison template with the geojson and stats results.

# Php controllers track processing

## track markers creation

This script is used to produce track statistics/markers which will be displayed on the map.

It uses SimpleXml to parse gpx files. A naive process is done to compute global track statistics.

## track comparison

This controller method takes several gpx tracks in input and computes comparisons of all tracks pairs.

By "comparison" i mean comparison of divergent parts of similar tracks. This is done with two algorithms : 
* findNextConvergence
* findNextDivergence
Basically, to compare a pair of tracks, the script scans them simultaneously and first looks for a convergence point, which means a point where the two tracks start to follow the same path, where they are closer than a threshold. From that convergence point, it looks for a divergence point, which means a point where the two tracks are farther than a threshold. From this divergence point, it looks again for a convergence point and stores the zone between the divergence point and the following convergence point as a "divergence zone". It also stores stats for the divergence zone. Then it starts the same process again to find all divergence zones.

Once the zones have been identified, it produces a geojson file for each track of the pair.

It produces two geojson files for each track pair. For two tracks A and B, it produces AB.geojson and BA.geojson.

AB.geojson is the track A including information of comparison with B.
BA.geojson is the track B including information of comparison with A.

More precisely, a result geojson is composed by several features. One feature is either a convergent (similar) zone or a divergence zone.
