<?php
script('gpxpod', 'gpxvcomp');

style('gpxpod', 'style');
style('gpxpod', 'gpxvcomp');

?>

<div id="app">
    <div id="app-content">
            <?php print_unescaped($this->inc('gpxvcompcontent')); ?>
    </div>
</div>
