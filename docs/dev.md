# Developer documentation

[[_TOC_]]

# Overview

GpxPod is divided 3 parts :

* The HTML/Javascript/Vue GUI which displays tracks and stats
* The Php controllers that interact with Nextcloud (files, shares, encryption etc...)
* The process controller methods used to analyse and compare tracks

The GUI uses ajax calls to controller methods to get information from the server side.
It uses JS libraries that are all included in the "js" directory. 
The map is displayed using Maplibre-gl. 
Gpx files are retrieved as GeoJson with network requests so they can be almost directly consumed by Maplibre-gl.

The Php controllers are in charge of DB communication, stats and comparison processing, NC filesystem and filesharing access.

There are 2 processes done by the controllers, get the stats of a folder's tracks and compare tracks.
The first is used to produce marker information from the gpx files.
This information is then stored in the database.
The second is used to compare a set of GPX files and produce two GeoJson objects for each GPX file pair.
The GeoJson objects are then consumed by the GUI and displayed with colors on divergent parts and local/global stats.

# GUI

## Map management

Maplibre-gl is awesome.

After having faced a few limitations and difficulties with Vue mappings for Leaflet in the past,
I decided to avoid using Vue mappings for Maplibre-gl. Map elements like track lines, area polygons, marker clusters etc...
are custom Vue components that interact with the map object while keeping the reactivity of Vue.

## Getting the data

### Track statistics

Once a directory is added in the UI, all the track it contains are processed to produce some statistics that are
stored in the database.

### Display a track

When a track is enabled in the UI, it's converted on the fly in GeoJson on the server side.

# Php controllers track processing

## track comparison

This controller method takes several gpx tracks in input and computes comparisons of all tracks pairs.

"Comparison" means comparison of divergent parts of similar tracks. This is done with two algorithms: 

* findNextConvergence
* findNextDivergence
* 
Basically, to compare a pair of tracks, the script scans them simultaneously and first looks for a convergence point,
which means a point where the two tracks start to follow the same path, where they are closer than a threshold. 
From that convergence point, it looks for a divergence point, which means a point where the two tracks are farther than a threshold. 
From this divergence point, it looks again for a convergence point and stores the zone between the divergence point 
and the following convergence point as a "divergence zone". 
It also stores stats for the divergence zone. Then it starts the same process again to find all divergence zones.

Once the zones have been identified, it produces a GeoJson object for each track of the pair.

It produces two GeoJson objects for each track pair. For two tracks A and B, it produces AB.geojson and BA.geojson.

AB.geojson is the track A including information of its comparison with B.
BA.geojson is the track B including information of its comparison with A.

The produced GeoJson objects are composed by several features. One feature is either a convergent zone
(where the track follow approximately the same path) or a divergence zone (where the tracks are far from each other).
