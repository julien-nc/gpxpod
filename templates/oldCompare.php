<?php
$appId = OCA\Gpxpod\AppInfo\Application::APP_ID;
\OCP\Util::addScript($appId, $appId . '-gpxvcomp');
\OCP\Util::addStyle($appId, 'fontawesome-free/css/all.min');
\OCP\Util::addStyle($appId, 'style');
\OCP\Util::addStyle($appId, 'gpxvcomp');
?>

<div id="app">
    <div id="app-content">
            <?php print_unescaped($this->inc('gpxvcompcontent')); ?>
    </div>
</div>
