<?php
if ($_['publicgpx'] === '' && $_['publicdir'] === '') {
    script('viewer', 'viewer');
}
script('gpxpod', 'gpxpod');

style('gpxpod', 'fontawesome-free/css/all.min');
style('gpxpod', 'style');
style('gpxpod', 'Leaflet.Elevation-0.0.2');
style('gpxpod', 'gpxpod');

?>

<div id="app">
    <div id="app-content">
            <?php print_unescaped($this->inc('gpxcontent')); ?>
    </div>
</div>
