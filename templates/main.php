<?php
$appId = OCA\Gpxpod\AppInfo\Application::APP_ID;
if ($_['publicgpx'] === '' && $_['publicdir'] === '') {
	\OCP\Util::addScript('viewer', 'viewer');
}
\OCP\Util::addScript($appId, $appId . '-gpxpod');

\OCP\Util::addStyle($appId, 'fontawesome-free/css/all.min');
\OCP\Util::addStyle($appId, 'style');
\OCP\Util::addStyle($appId, 'Leaflet.Elevation-0.0.2');
\OCP\Util::addStyle($appId, 'gpxpod');
?>

<div id="app">
    <div id="app-content">
            <?php print_unescaped($this->inc('gpxcontent')); ?>
    </div>
</div>
