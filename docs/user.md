# GpxPod user documentation

[[_TOC_]]

# Basic usage

Basically you just need to have some GPX, KML, KMZ, FIT or TCX files in your Nextcloud files and go in GpxPod app.
You will then need to add a folder in the navigation sidebar. 
The tracks are processed and then listed.
Hovering a track item in the navigation sidebar makes it temporarily appear on the map. 
Click on a track to draw it permanently on the map .

# Advanced usage

There are many settings available in the user interface.

## Color criteria

If a criteria is selected, this will affect the color of tracks when they are drawn. 
The color goes from blue to green to yellow to red. Blue is the minimum value and red the maximum one.

## Personal tiles servers

If you add a personnal tile server, it will be available in the tile selector on the upper right corner of the map.

## Elevation correction

Elevation correction is done with [SRTM](https://en.wikipedia.org/wiki/Shuttle_Radar_Topography_Mission) data. 
Asking for an elevation correction creates a new track with "_corrected" as a name suffix.
To correct elevations of a track, click on the corresponding links in the track popup. 

## Share a track or a folder

Sharing a track or a folder in GpxPod actually creates a real file or folder share in Files but still produces
a GpxPod public link.

## Track comparison

If you select several tracks and then click on the "compare selected tracks" folder context menu item, 
the GpxPod comparison interface will be opened in a new browser tab. 
This feature does a global comparison of the selected tracks and also compares each track pair.

### Pair divergence comparison

In this comparison interface, the map shows comparison between one track pair you may select in the sidebar. 
If the tracks are very similar but have some divergent parts, 
each divergence section will be green or red if it's better or worse than the other track on the selected criteria. 
This feature might be useful to analyze every detour you made between two equivalent trips. 
If you went twice from point A to point B but the second time, you took two alternative paths along the way. 
It is possible that the global comparison between your two A->B trips shows a very small difference between those two trips. 
So it might be that the 2 alternative paths had a very small impact on the trip duration and total distance. 
But it is also possible that the two alternative paths compensate each other.
One would having a very positive impact and the other a very negative one.
GpxPod comparison shows the effect of each individual divergences between two tracks. 
This allows you, for example, to easily and precisely find out the best path between all the tracks you recorded between the same A and B points.

### Global comparison of all selected tracks

There is a table in the sidebar showing global comparison between all tracks.
