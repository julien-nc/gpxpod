<?php
script('gpxpod', 'd3.min');
script('gpxpod', 'leaflet');
script('gpxpod', 'leaflet.polylineDecorator');
script('gpxpod', 'L.Control.MousePosition');
script('gpxpod', 'Control.Geocoder');
script('gpxpod', 'ActiveLayers');
script('gpxpod', 'Control.MiniMap');
script('gpxpod', 'L.Control.Locate.min');
script('gpxpod', 'leaflet-sidebar.min');
script('gpxpod', 'leaflet.markercluster-src');
script('gpxpod', 'L.Control.Elevation');
script('gpxpod', 'jquery-ui.min');
script('gpxpod', 'jquery.mousewheel');
script('gpxpod', 'sorttable');
script('gpxpod', 'detect_timezone');
script('gpxpod', 'jquery.detect_timezone');
script('gpxpod', 'moment-timezone-with-data.min');
script('gpxpod', 'Leaflet.LinearMeasurement');
script('gpxpod', 'leaflet.hotline');
script('gpxpod', 'Leaflet.Dialog');
script('gpxpod', 'oms.min');
script('gpxpod', 'mapbox-gl');
script('gpxpod', 'leaflet-mapbox-gl');
if ($_['publicgpx'] === '' and $_['publicdir'] === '') {
    script('viewer', 'viewer');
}
script('gpxpod', 'gpxpod');

style('gpxpod', 'style');
style('gpxpod', 'leaflet');
style('gpxpod', 'L.Control.MousePosition');
style('gpxpod', 'Control.Geocoder');
style('gpxpod', 'leaflet-sidebar.min');
style('gpxpod', 'Control.MiniMap');
style('gpxpod', 'jquery-ui.min');
style('gpxpod', 'fontawesome/css/all.min');
style('gpxpod', 'Leaflet.Elevation-0.0.2');
style('gpxpod', 'MarkerCluster');
style('gpxpod', 'MarkerCluster.Default');
style('gpxpod', 'L.Control.Locate.min');
style('gpxpod', 'Leaflet.LinearMeasurement');
style('gpxpod', 'Leaflet.Dialog');
style('gpxpod', 'mapbox-gl');
style('gpxpod', 'gpxpod');

?>

<div id="app">
    <div id="app-content">
            <?php print_unescaped($this->inc('gpxcontent')); ?>
    </div>
</div>
