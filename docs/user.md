# GpxPod user documentation

[[_TOC_]]

# Bugs and issues

* In some contexts (probably related to the encoding settings), special characters like mutated vowels might cause a bug in the user interface, avoiding to draw tracks.
* Do not put simple or double quotes in your file names

# Basic usage

Basically you just need to have some gpx, kml or tcx files in your Owncloud/Nextcloud files and go to GpxPod app. Then you'll need to select a folder in the list at the top of the sidebar main tab. The tracks are analyzed and then listed in a table in that same sidebar tab. Hovering a track line in the table makes it temporarily appear on the map. Checking the 'draw' checkbox draws the track permanently on the map and displays a popup at the starting point with global statistics on the track. If you draw several tracks, they appear with different colors.

## Elevation chart
When you draw a track, its elevation chart is displayed on the map. If you hover this chart, the corresponding point is shown on the track drawing. A small modification has been done on Leaflet.Elevation extension to display the time in the chart when hovering.

## Sidebar track table
When you move or zoom in the map view, the sidebar table is kept up to date to only list tracks that cross current map view area. This means you can identify all the activities you had in a precise location and also find out which part of an area you never crossed.

You can sort this table by column.

## Filter tracks

In the "Settings" sidebar tab, you'll find inputs to restrict the listed tracks with several criterions. It affects the track list table and the markers apparition on the map.

# Advanced usage

There are many options available in the user interface. A short description will appear if you hover options elements.

## Scan type

When a folder is displayed for the first time, tracks are processed and result meta-information is written in the database. So if a gpx track file is modified and the corresponding folder is displayed again, track meta-information will not be updated unless you had selected the "process all files" scan type.

## Color criterion

If a criterion is selected, this will affect the color of tracks when they are drawn. The color goes from green to red passing by yellow. Green color is affected to the minimum value and red to the maximum.

## Personal tiles servers

If you add a personnal tile server, it will be available in the tile selector on the upper right corner of the map.

## Elevation correction

If the Owncloud/Nextcloud administrator has installed SRTM.py library, "gpxelevations" is in a standard PATH and you will be able to ask GpxPod to correct your tracks elevations with [SRTM](https://en.wikipedia.org/wiki/Shuttle_Radar_Topography_Mission) data in the GUI. Asking an elevation correction creates a new track with "_corrected" added to its name. To correct elevations of a track, click on the corresponding links in the track popup. You may ask for a simple correction that simply replace elevation for each track point. You may also ask for correction with smoothing to reduce the imprecision of SRTM data.

## Share track or folder

There are two ways to share in GpxPod :
* share a track
* share a folder
To share something, it first needs to be shared in the "files" application. Then you can send the public link that you can get in GpxPod interface by clicking on the share icon. A public track share will work if the track file is inside a shared folder.

Public link may be accessed by anyone who is not logged in the Owncloud/Nextcloud instance.

## Track comparison

If you select several tracks and then click on the "compare selected tracks" button, the GpxPod comparison interface will be opened in a new browser tab. This feature does a global comparison of the selected tracks and also compares each track pair.

### Pair divergence comparison
In this comparison interface, the map shows comparison between one track pair you may select in the sidebar. If the tracks are very similar with some divergent parts, each divergence will be displayed in green or red to express the comparison with the selected criterion. This feature might be useful to analyze every detour you made between two equivalent journeys. Let's imagine you did twice the path from point A to point B but the second time you did it, you made two detours. It is possible that the global comparison between A->B journey tells you that the difference between those two rides is very small. It is possible that each of the two detours has a very small effect on the journey. But it is also possible that the two detours compensate each other. One would be very positive and the other very negative. GpxPod comparison shows you the effect of each detour between two tracks. This allows you, for example, to easily and precisely find out the best path between all the tracks you recorded between the same A and B points.

### Global comparison of all selected tracks
There is a table in the sidebar showing global comparison between all tracks.
