<div id="sidebar" class="sidebar">
<!-- Nav tabs -->
<ul class="sidebar-tabs" role="tablist">
<li class="active" title="<?php p($l->t('Folder and tracks selection')); ?>"><a href="#ho" role="tab"><i class="fa fa-bars"></i></a></li>
<li title="<?php p($l->t('Settings and extra actions')); ?>"><a href="#gpxpodsettings" role="tab"><i class="fa fa-cogs"></i></a></li>
<li title="<?php p($l->t('About GpxPod')); ?>"><a href="#help" role="tab"><i class="fa fa-question"></i></a></li>
</ul>
<!-- Tab panes -->
<div class="sidebar-content active">
<div class="sidebar-pane active" id="ho">
    <div id="logofolder">
        <div id="logo">
        </div>
        <p class="version">v
<?php
p($_['gpxpod_version']);
?>
        </p>
        <label id="filenumberlabel"></label>
        <label for="subfolderselect"><?php p($l->t('Folder')); ?> :</label>
        <select name="subfolder" id="subfolderselect">
        <option style="color:red; font-weight:bold"><?php p($l->t('Choose a folder')); ?></option>
<?php

// populate select options
if (count($_['dirs']) > 0){
    foreach($_['dirs'] as $dir){
        echo '<option value="';
        p(encodeURIComponent($dir));
        echo '">';
        p($dir);
        echo '</option>'."\n";
    }
}

?>
        </select>
    <div id="folderbuttons">
<i class="publink fa fa-share-alt" type="folder" name="" target="_blank" href="" title=""></i>
<i id="reloadprocessfolder" class="fa fa-wrench" title="<?php p($l->t('Reload and analyze all files in current folder')); ?>"></i>
        <i id="reloadfolder" class="fa fa-sync-alt" title="<?php p($l->t('Reload current folder')); ?>"></i>
    </div>
    <div id="addRemoveButtons">
        <button id="addDirsButton" title="<?php p($l->t('Add directories recursively')); ?>"><i class="fa fa-plus-square"></i></button>
        <button id="addDirButton" title="<?php p($l->t('Add directory')); ?>"><i class="fas fa-folder-plus"></i></button>
        <button id="delDirButton" title="<?php p($l->t('Delete current directory')); ?>"><i class="fas fa-folder-minus"></i></button>
        <!-- TODO add recursive folder (only those with compatible files inside) -->
    </div>
    </div>
<?php
    echo '<div id="nofolder">';
    p($l->t('There is no directory in your list'));
    echo '</div>';
    echo '<div id="nofoldertext">';
    p($l->t('Add one to be able to see tracks'));
    echo '</div>';
?>
    <hr/>
    <div id="options">
        <div>
        <h3 id="optiontitle" class="sectiontitle">
        <b id="optiontitletext"><i class="fa fa-caret-right"></i> <?php p($l->t('Options')); ?> </b>
        </h3>
        </div>
        <div style="clear:both"></div>
        <div id="optionscontent" style="display:none;">
        <div id="optionbuttonsdiv">
        </div>
        <div id="optioncheckdiv">
            <h2><?php p($l->t('Map options')); ?></h2>
            <div>
                <input id="displayclusters" type="checkbox" checked="checked">
                <label for="displayclusters"><i class="fa fa-user" aria-hidden="true"></i>
                <?php p($l->t('Display markers'));?></label>
            </div>
            <div id="showpicsdiv" style="display:none;" title="<?php
p($l->t('Show pictures markers'));
echo "\n\n";
p($l->t('Only pictures with EXIF geolocation data are displayed')); ?>">
                <input id="showpicscheck" type="checkbox" checked="checked">
                <label for="showpicscheck">
                <i class="far fa-file-image" aria-hidden="true"></i>
                <?php p($l->t('Show pictures')); ?></label>
            </div>
            <div title=
            "<?php p($l->t('With this disabled, public page link will include option to hide sidebar')); ?>">
                <input id="enablesidebar" type="checkbox" checked="checked">
                <label for="enablesidebar">
                <i class="fa fa-bars" aria-hidden="true"></i>
                <?php p($l->t('Enable sidebar in public pages')); ?>
                </label>
            </div>
            <div title="<?php p($l->t('Open info popup when a track is drawn')); ?>">
                <input id="openpopupcheck" type="checkbox" checked="checked">
                <label for="openpopupcheck"><i class="far fa-comment" aria-hidden="true"></i>
                <?php p($l->t('Auto-popup')); ?></label>
            </div>
            <div title=
"<?php p($l->t('If enabled :'));
echo "\n- ";
p($l->t('Zoom on track when it is drawn'));
echo "\n- ";
p($l->t('Zoom to show all track markers when selecting a folder'));
echo "\n";
p($l->t('If disabled :'));
echo "\n- ";
p($l->t('Do nothing when a track is drawn'));
echo "\n- ";
p($l->t('Reset zoom to world view when selecting a folder')); ?>">
                <input id="autozoomcheck" type="checkbox" checked="checked">
                <label for="autozoomcheck"><i class="fa fa-search-plus" aria-hidden="true"></i>
                <?php p($l->t('Auto-zoom')); ?></label>
            </div>
            <div title="<?php p($l->t('Display elevation or speed chart when a track is drawn')); ?>">
                <input id="showchartcheck" type="checkbox" checked="checked">
                <label for="showchartcheck">
                <i class="fa fa-chart-area" aria-hidden="true"></i>
                <?php p($l->t('Display chart')); ?></label>
            </div>
            <div class="optionselect">
                <label for="tzselect"><?php p($l->t('Timezone')); ?> :</label>
                <select id="tzselect"></select>
            </div>
            <div style="clear:both;"></div>
            <div class="optionselect">
                <label for="measureunitselect"><?php p($l->t('Measuring units')); ?> :</label>
                <select id="measureunitselect">
                <option value="metric"><?php p($l->t('Metric')); ?></option>
                <option value="english"><?php p($l->t('English')); ?></option>
                <option value="nautical"><?php p($l->t('Nautical')); ?></option>
                </select>
            </div>
            <div style="clear:both;"></div>
            <!-- end map -->
            <hr/>
            <h2><?php p($l->t('Track drawing options')); ?></h2>
            <div title="<?php p($l->t('Use symbols defined in the gpx file')); ?>">
                <input id="symboloverwrite" type="checkbox" checked></input>
                <label for="symboloverwrite">
                <i class="fa fa-map-pin" aria-hidden="true"></i>
                <?php p($l->t('Gpx symbols')); ?>
                </label>
            </div>
            <div title="<?php p($l->t('Draw black borders around track lines')); ?>">
                <input id="linebordercheck" type="checkbox" checked="checked">
                <label for="linebordercheck">
                <i class="fa fa-pencil-alt" aria-hidden="true"></i>
                <?php p($l->t('Line borders')); ?> *</label>
            </div>
            <div class="optionselect" title="<?php p($l->t('Track line width in pixels')); ?>">
                <label for="lineweight">
                * <?php p($l->t('Line width')); ?>
                </label>
                <input id="lineweight" type="number" value="5" min="2" max="20"/>
            </div>
            <div>
                <input id="rteaswpt" type="checkbox">
                <label for="rteaswpt">
                <i class="far fa-dot-circle" aria-hidden="true"></i>
                <?php p($l->t('Display routes points')); ?>
                </label>
            </div>
            <div title="<?php p($l->t('Show direction arrows along lines')); ?>">
                <input id="arrowcheck" type="checkbox">
                <label for="arrowcheck">
                <i class="fa fa-arrow-right" aria-hidden="true"></i>
                <?php p($l->t('Direction arrows')); ?> *</label>
            </div>
            <div title="<?php p($l->t('Draw all tracks after folder selection')); ?>">
                <input id="drawallcheck" type="checkbox">
                <label for="drawallcheck">
                <i class="fa fa-check" aria-hidden="true"></i>
                <?php p($l->t('Draw all tracks')); ?></label>
            </div>
            <div class="optionselect">
                <label for="trackwaypointdisplayselect">* <?php p($l->t('Draw')); ?> :</label>
                <select id="trackwaypointdisplayselect">
                <option value="tw" selected="selected"><?php p($l->t('track+waypoints')); ?></option>
                <option value="t"><?php p($l->t('track')); ?></option>
                <option value="w"><?php p($l->t('waypoints')); ?></option>
                </select>
            </div>
            <div style="clear:both;"></div>
            <div class="optionselect">
                <label for="waypointstyleselect">* <?php p($l->t('Waypoint style')); ?> :</label>
                <select id="waypointstyleselect">
                </select>
            </div>
            <div style="clear:both;"></div>
            <div class="optionselect">
                <label for="tooltipstyleselect">* <?php p($l->t('Tooltip')); ?> :</label>
                <select id="tooltipstyleselect">
                    <option value="h"><?php p($l->t('on hover')); ?></option>
                    <option value="p"><?php p($l->t('permanent')); ?></option>
                </select>
            </div>
            <div style="clear:both;"></div>
            <div class="optionselect">
                <label for="colorcriteria" title="<?php
                p($l->t('Enables tracks coloring by the chosen criteria')); ?>">
                * <?php p($l->t('Color tracks by')); ?> :</label>
                <select name="colorcriteria" id="colorcriteria"
                title="<?php p($l->t('Enables tracks coloring by the chosen criteria')); ?>">
                <option value="none"><?php p($l->t('none')); ?></option>
                <option value="speed"><?php p($l->t('speed')); ?></option>
                <option value="elevation"><?php p($l->t('elevation')); ?></option>
                <option value="pace"><?php p($l->t('pace')); ?></option>
                <option value="extension"><?php p($l->t('extension')); ?></option>
                </select>
            </div>
            <div style="clear:both;"></div>
            <div class="optionselect">
                <label for="colorcriteriaext" title="<?php
                p($l->t('Enables tracks coloring by the chosen extension value')); ?>">
                * <?php p($l->t('Color tracks by extension value')); ?> :</label>
                <input name="colorcriteriaext" id="colorcriteriaext" type="text"
                title="<?php p($l->t('Enables tracks coloring by the chosen extension value')); ?>"/>
            </div>
            <div style="clear:both;"></div>
            <div class="optionselect">
                <label for="igctrackselect"><?php p($l->t('IGC elevation track')); ?> :</label>
                <select id="igctrackselect">
                <option value="both"><?php p($l->t('Both GNSS and pressure')); ?></option>
                <option value="pres"><?php p($l->t('Pressure')); ?></option>
                <option value="gnss"><?php p($l->t('GNSS')); ?></option>
                </select>
            </div>
            <div style="clear:both;"></div>
            <!-- end track -->
            <hr/>
            <h2><?php p($l->t('Table options')); ?></h2>
            <div title="<?php p($l->t('Table only shows tracks that are inside current map view')); ?>">
                <input id="updtracklistcheck" type="checkbox" checked="checked">
                <label for="updtracklistcheck">
                <i class="fa fa-table" aria-hidden="true"></i>
                <?php p($l->t('Dynamic table')); ?></label>
            </div>
            <div title=
            "<?php p($l->t('For slow connections or if you have huge files, a simplified version is shown when hover')); ?>">
                <input id="simplehovercheck" type="checkbox">
                <label for="simplehovercheck">
                <i class="fa fa-eye" aria-hidden="true"></i>
                <?php p($l->t('Simplified previews')); ?>
                </label>
            </div>
            <div title=
            "<?php p($l->t('Enables transparency when hover on table rows to display track overviews')); ?>">
                <input id="transparentcheck" type="checkbox">
                <label for="transparentcheck">
                <i class="far fa-eye" aria-hidden="true"></i>
                <?php p($l->t('Transparency')); ?>
                </label>
            </div>
            <!-- end table -->
            <hr/>
            <h2><?php p($l->t('Storage exploration options')); ?></h2>
            <div>
                <input id="recursivetrack" type="checkbox">
                <label for="recursivetrack">
                <i class="fas fa-folder" aria-hidden="true"></i>
                <?php p($l->t('Display tracks recursively in selected folder')); ?>
                </label>
            </div>
            <div>
                <input id="showshared" type="checkbox" checked="checked">
                <label for="showshared">
                <i class="fas fa-share-alt" aria-hidden="true"></i>
                <?php p($l->t('Display shared folders/files')); ?>
                </label>
            </div>
            <div>
                <input id="showmounted" type="checkbox" checked="checked">
                <label for="showmounted">
                <i class="fas fa-external-link-alt" aria-hidden="true"></i>
                <?php p($l->t('Explore external storages')); ?>
                </label>
            </div>
            <div>
                <input id="showpicsonlyfold" type="checkbox" checked="checked">
                <label for="showpicsonlyfold">
                <i class="fas fa-images" aria-hidden="true"></i>
                <?php p($l->t('Display folders containing pictures only')); ?>
                </label>
            </div>
            <!-- end exploration -->
            <br/>
            <p id="lastlegend">(*) <?php p($l->t('Effective on future actions')); ?></p>
            <!-- end options -->
        </div>
        </div>
    </div>
    <div style="clear:both"></div>
    <hr/>
    <h3 id="ticv" class="sectiontitle"><?php p($l->t('Tracks from current view')); ?></h3>
    <div id="tablecriteria"
    title="<?php
p($l->t('what determines if a track is shown in the table :'));
echo "\n\n- ";
p($l->t('crosses : at least one track point is inside current view'));
echo "\n- ";
p($l->t('begins : beginning point marker is inside current view'));
echo "\n- ";
p($l->t('track N,S,E,W bounds intersect current view bounds square'));
?>
">
        <label for="tablecriteriasel" id="tablecriterialabel">
            <?php p($l->t('List tracks that')); ?> :
        </label>
        <select name="tablecriteriasel" id="tablecriteriasel">
        <option value="cross"><?php p($l->t('cross current view')); ?></option>
        <option value="start"><?php p($l->t('begin in current view')); ?></option>
       <option value="bounds"><?php p($l->t('have N,S,E,W bounds crossing current view')); ?></option>
        </select>
    </div>
    <div id="tablebuttons">
        <button id="selectall" class="smallbutton"><i class="far fa-check-square" aria-hidden="true" style="color:green;"></i>
        <?php p($l->t('Select visible')); ?>
        </button>
        <button id="deselectall" class="smallbutton"><i class="far fa-square" aria-hidden="true" style="color:red;"></i>
        <?php p($l->t('Deselect all')); ?>
        </button>
        <button id="deselectallv" class="smallbutton"><i class="far fa-square" aria-hidden="true" style="color:red;"></i>
        <?php p($l->t('Deselect visible')); ?>
        </button>
        <button id="deleteselected" class="smallbutton"><i class="far fa-trash-alt" aria-hidden="true" style="color:red;"></i>
        <?php p($l->t('Delete selected')); ?>
        </button>
        <button id="moveselectedto" class="smallbutton"><i class="fas fa-external-link-alt" aria-hidden="true" style="color:blue;"></i>
        <?php p($l->t('Move selected tracks to')); ?>
        </button>
        <button id="removeelevation">
        <i class="far fa-eye-slash" style="color:red;"></i>
        <?php p($l->t('Hide elevation profile')); ?>
        </button>
        <button id="comparebutton">
            <i class="fa fa-balance-scale"></i>
            <?php p($l->t('Compare selected tracks')); ?>
        </button>
    </div>
    <div id="gpxlist"></div>
<?php

echo '<p id="gpxcomprooturl" style="display:none">';
p($_['gpxcomp_root_url']);
echo '</p>'."\n";
echo '<p id="publicgpx" style="display:none">';
p($_['publicgpx']);
echo '</p>'."\n";
echo '<p id="publicmarker" style="display:none">';
p($_['publicmarker']);
echo '</p>'."\n";
echo '<p id="publicdir" style="display:none">';
p($_['publicdir']);
echo '</p>'."\n";
echo '<p id="username" style="display:none">';
p($_['username']);
echo '</p>'."\n";
echo '<p id="pictures" style="display:none">';
p($_['pictures']);
echo '</p>'."\n";
echo '<p id="token" style="display:none">';
p($_['token']);
echo '</p>'."\n";
echo '<p id="hassrtm" style="display:none">';
if ($_['hassrtm']) {
    p('yes');
}
else {
    p('no');
}
echo '</p>'."\n";
echo '<p id="gpxedit_version" style="display:none">';
p($_['gpxedit_version']);
echo '</p>'."\n";
echo '<p id="gpxmotion_version" style="display:none">';
p($_['gpxmotion_version']);
echo '</p>'."\n";
echo '<ul id="extrasymbols" style="display:none">';
foreach($_['extrasymbols'] as $symbol){
    echo '<li name="';
    p($symbol['name']);
    echo '">';
    p($symbol['smallname']);
    echo '</li>';
}
echo '</ul>'."\n";
echo '<ul id="basetileservers" style="display:none">';
foreach($_['basetileservers'] as $ts){
    echo '<li';
    foreach (Array('name', 'type', 'url', 'token', 'layers', 'version', 'format', 'opacity', 'transparent', 'minzoom', 'maxzoom', 'attribution') as $field) {
        if (array_key_exists($field, $ts)) {
            echo ' '.$field.'="';
            p($ts[$field]);
            echo '"';
        }
    }
    echo '></li>';
}
echo '</ul>'."\n";

?>
</div>
<div class="sidebar-pane" id="gpxpodsettings">
<h1 class="sectiontitle"><?php p($l->t('Settings and extra actions')); ?></h1>
<hr/>
<br/>
<div id="filtertabtitle">
    <h3 class="sectiontitle"><?php p($l->t('Filters')); ?></h3>
    <button id="clearfilter" class="filterbutton">
        <i class="fa fa-trash" aria-hidden="true" style="color:red;"></i>
        <?php p($l->t('Clear')); ?>
    </button>
    <button id="applyfilter" class="filterbutton">
        <i class="fa fa-check" aria-hidden="true" style="color:green;"></i>
        <?php p($l->t('Apply')); ?>
    </button>
</div>
<br/>
<br/>
<ul id="filterlist" class="disclist">
    <li>
        <b><?php p($l->t('Date')); ?></b><br/>
        <?php p($l->t('min')); ?> : <input type="date" id="datemin"/><br/>
        <?php p($l->t('max')); ?> : <input type="date" id="datemax"/>
    </li>
    <li>
        <b><?php p($l->t('Distance'));?> (<i class="distanceunit">m</i>)</b><br/>
        <?php p($l->t('min')); ?> : <input id="distmin" type="number" min="0" max="500"/><br/>
        <?php p($l->t('max')); ?> : <input id="distmax" type="number" min="0" max="500"/>
    </li>
    <li>
        <b><?php p($l->t('Cumulative elevation gain')); ?> (<i class="elevationunit">m</i>)</b><br/>
        <?php p($l->t('min')); ?> : <input id="cegmin" type="number" min="0" max="500"/><br/>
        <?php p($l->t('max')); ?> : <input id="cegmax" type="number" min="0" max="500"/>
    </li>
</ul>
<br/>
<hr/>

<div id="customtilediv">
<h3 class="sectiontitle customtiletitle" for="tileserverdiv"><b><?php p($l->t('Custom tile servers')); ?></b> <i class="fa fa-angle-double-down" aria-hidden="true"></i></h3>
<div id="tileserverdiv">
    <div id="tileserveradd">
        <p><?php p($l->t('Server name')); ?> :</p>
        <input type="text" id="tileservername" title="<?php p($l->t('For example : my custom server')); ?>"/>
        <p><?php p($l->t('Server url')); ?> :</p>
        <input type="text" id="tileserverurl" title="<?php p($l->t('For example : http://tile.server.org/cycle/{z}/{x}/{y}.png')); ?>"/>
        <p><?php p($l->t('Min zoom (1-20)')); ?> :</p>
        <input type="text" id="tileminzoom" value="1"/>
        <p><?php p($l->t('Max zoom (1-20)')); ?> :</p>
        <input type="text" id="tilemaxzoom" value="18"/>
        <button id="addtileserver"><i class="fa fa-plus-circle" aria-hidden="true" style="color:green;"></i> <?php p($l->t('Add')); ?></button>
    </div>
    <div id="tileserverlist">
        <h3><?php p($l->t('Your tile servers')); ?></h3>
        <ul class="disclist">
<?php
if (count($_['usertileservers']) > 0){
    foreach($_['usertileservers'] as $ts){
        echo '<li title="'.$ts['url'].'"';
        foreach (Array('servername', 'type', 'url', 'layers', 'version', 'format', 'opacity', 'transparent', 'minzoom', 'maxzoom', 'attribution') as $field) {
            if (array_key_exists($field, $ts)) {
                echo ' '.$field.'="';
                p($ts[$field]);
                echo '"';
            }
        }
        echo '>';
        p($ts['servername']);
        echo '&nbsp <button><i class="fa fa-trash" aria-hidden="true" style="color:red;"></i> ';
        p($l->t('Delete'));
        echo '</button></li>';
    }
}
?>
        </ul>
    </div>
</div>

<hr/>
<h3 class="sectiontitle customtiletitle" for="overlayserverdiv"><b><?php p($l->t('Custom overlay tile servers')); ?></b> <i class="fa fa-angle-double-down" aria-hidden="true"></i></h3>
<div id="overlayserverdiv">
    <div id="overlayserveradd">
        <p><?php p($l->t('Server name')); ?> :</p>
        <input type="text" id="overlayservername" title="<?php p($l->t('For example : my custom server')); ?>"/>
        <p><?php p($l->t('Server url')); ?> :</p>
        <input type="text" id="overlayserverurl" title="<?php p($l->t('For example : http://overlay.server.org/cycle/{z}/{x}/{y}.png')); ?>"/>
        <p><?php p($l->t('Min zoom (1-20)')); ?> :</p>
        <input type="text" id="overlayminzoom" value="1"/>
        <p><?php p($l->t('Max zoom (1-20)')); ?> :</p>
        <input type="text" id="overlaymaxzoom" value="18"/>
        <label for="overlaytransparent"><?php p($l->t('Transparent')); ?> :</label>
        <input type="checkbox" id="overlaytransparent" checked/>
        <p><?php p($l->t('Opacity (0.0-1.0)')); ?> :</p>
        <input type="text" id="overlayopacity" value="0.4"/>
        <button id="addoverlayserver"><i class="fa fa-plus-circle" aria-hidden="true" style="color:green;"></i> <?php p($l->t('Add')); ?></button>
    </div>
    <div id="overlayserverlist">
        <h3><?php p($l->t('Your overlay tile servers')); ?></h3>
        <ul class="disclist">
<?php
if (count($_['useroverlayservers']) > 0){
    foreach($_['useroverlayservers'] as $ts){
        echo '<li title="'.$ts['url'].'"';
        foreach (Array('servername', 'type', 'url', 'layers', 'version', 'format', 'opacity', 'transparent', 'minzoom', 'maxzoom', 'attribution') as $field) {
            if (array_key_exists($field, $ts)) {
                echo ' '.$field.'="';
                p($ts[$field]);
                echo '"';
            }
        }
        echo '>';
        p($ts['servername']);
        echo '&nbsp <button><i class="fa fa-trash" aria-hidden="true" style="color:red;"></i> ';
        p($l->t('Delete'));
        echo '</button></li>';
    }
}
?>
        </ul>
    </div>
</div>
<hr/>
<h3 class="sectiontitle customtiletitle" for="tilewmsserverdiv"><b><?php p($l->t('Custom WMS tile servers')); ?></b> <i class="fa fa-angle-double-down" aria-hidden="true"></i></h3>
<div id="tilewmsserverdiv">
    <div id="tilewmsserveradd">
        <p><?php p($l->t('Server name')); ?> :</p>
        <input type="text" id="tilewmsservername" title="<?php p($l->t('For example : my custom server')); ?>"/>
        <p><?php p($l->t('Server url')); ?> :</p>
        <input type="text" id="tilewmsserverurl" title="<?php p($l->t('For example : http://tile.server.org/cycle/{z}/{x}/{y}.png')); ?>"/>
        <p><?php p($l->t('Min zoom (1-20)')); ?> :</p>
        <input type="text" id="tilewmsminzoom" value="1"/>
        <p><?php p($l->t('Max zoom (1-20)')); ?> :</p>
        <input type="text" id="tilewmsmaxzoom" value="18"/>
        <p><?php p($l->t('Format')); ?> :</p>
        <input type="text" id="tilewmsformat" value="image/jpeg"/>
        <p><?php p($l->t('WMS version')); ?> :</p>
        <input type="text" id="tilewmsversion" value="1.1.1"/>
        <p><?php p($l->t('Layers to display')); ?> :</p>
        <input type="text" id="tilewmslayers" value=""/>
        <button id="addtileserverwms"><i class="fa fa-plus-circle" aria-hidden="true" style="color:green;"></i> <?php p($l->t('Add')); ?></button>
    </div>
    <div id="tilewmsserverlist">
        <h3><?php p($l->t('Your WMS tile servers')); ?></h3>
        <ul class="disclist">
<?php
if (count($_['usertileserverswms']) > 0){
    foreach($_['usertileserverswms'] as $ts){
        echo '<li title="'.$ts['url'].'"';
        foreach (Array('servername', 'type', 'url', 'layers', 'version', 'format', 'opacity', 'transparent', 'minzoom', 'maxzoom', 'attribution') as $field) {
            if (array_key_exists($field, $ts)) {
                echo ' '.$field.'="';
                p($ts[$field]);
                echo '"';
            }
        }
        echo '>';
        p($ts['servername']);
        echo '&nbsp <button><i class="fa fa-trash" aria-hidden="true" style="color:red;"></i> ';
        p($l->t('Delete'));
        echo '</button></li>';
    }
}
?>
        </ul>
    </div>
</div>
<hr/>
<h3 class="sectiontitle customtiletitle" for="overlaywmsserverdiv"><b><?php p($l->t('Custom WMS overlay servers')); ?></b> <i class="fa fa-angle-double-down" aria-hidden="true"></i></h3>
<div id="overlaywmsserverdiv">
    <div id="overlaywmsserveradd">
        <p><?php p($l->t('Server name')); ?> :</p>
        <input type="text" id="overlaywmsservername" title="<?php p($l->t('For example : my custom server')); ?>"/>
        <p><?php p($l->t('Server url')); ?> :</p>
        <input type="text" id="overlaywmsserverurl" title="<?php p($l->t('For example : http://overlay.server.org/cycle/{z}/{x}/{y}.png')); ?>"/>
        <p><?php p($l->t('Min zoom (1-20)')); ?> :</p>
        <input type="text" id="overlaywmsminzoom" value="1"/>
        <p><?php p($l->t('Max zoom (1-20)')); ?> :</p>
        <input type="text" id="overlaywmsmaxzoom" value="18"/>
        <label for="overlaywmstransparent"><?php p($l->t('Transparent')); ?> :</label>
        <input type="checkbox" id="overlaywmstransparent" checked/>
        <p><?php p($l->t('Opacity (0.0-1.0)')); ?> :</p>
        <input type="text" id="overlaywmsopacity" value="0.4"/>
        <p><?php p($l->t('Format')); ?> :</p>
        <input type="text" id="overlaywmsformat" value="image/jpeg"/>
        <p><?php p($l->t('WMS version')); ?> :</p>
        <input type="text" id="overlaywmsversion" value="1.1.1"/>
        <p><?php p($l->t('Layers to display')); ?> :</p>
        <input type="text" id="overlaywmslayers" value=""/>
        <button id="addoverlayserverwms"><i class="fa fa-plus-circle" aria-hidden="true" style="color:green;"></i> <?php p($l->t('Add')); ?></button>
    </div>
    <div id="overlaywmsserverlist">
        <h3><?php p($l->t('Your WMS overlay tile servers')); ?></h3>
        <ul class="disclist">
<?php
if (count($_['useroverlayserverswms']) > 0){
    foreach($_['useroverlayserverswms'] as $ts){
        echo '<li title="'.$ts['url'].'"';
        foreach (Array('servername', 'type', 'url', 'layers', 'version', 'format', 'opacity', 'transparent', 'minzoom', 'maxzoom', 'attribution') as $field) {
            if (array_key_exists($field, $ts)) {
                echo ' '.$field.'="';
                p($ts[$field]);
                echo '"';
            }
        }
        echo '>';
        p($ts['servername']);
        echo '&nbsp <button><i class="fa fa-trash" aria-hidden="true" style="color:red;"></i> ';
        p($l->t('Delete'));
        echo '</button></li>';
    }
}
?>
        </ul>
    </div>
</div>

</div>
    <hr/>
    <br/>
    <div id="cleandiv">
        <h3 class="sectiontitle"><?php p($l->t('Clean files or database')); ?></h3>
        <button id="cleanall"><i class="fa fa-trash" aria-hidden="true" style="color:red;"></i> <?php p($l->t('Delete all \'.marker\' and \'.geojson\' files')); ?></button>
        <button id="clean"><i class="fa fa-trash" aria-hidden="true" style="color:red;"></i> <?php p($l->t('Delete \'.markers\' and \'.geojson\' files corresponding to existing gpx files')); ?></button>
        <button id="cleandb"><i class="fa fa-trash" aria-hidden="true" style="color:red;" title="<?php p($l->t('Metadata will be generated again on folder load')); ?>"></i> <?php p($l->t('Delete metadata for all tracks in the database (distance, duration, average speed...)')); ?></button>
        <div id="clean_results"></div>
    </div>
    <div id="deleting"><p>
        <i class="fa fa-spinner fa-pulse fa-3x fa-fw"></i>
        <?php p($l->t('deleting')); ?></p>
    </div>
    <div id="linkdialog" style="display:none;" title="Public link">
        <label id="linklabel" for="linkinput"></label>
        <br/>
        <input id="linkinput" type="text"></input>
        <div id="linkhint">
        <?php p($l->t('Append "&track=all" to the link to display all tracks when public folder page loads.')); ?>
        </div>
    </div>
    <input id="tracknamecolor" type="text" style="display:none;"></input>
    <input id="trackfoldercolor" type="text" style="display:none;"></input>
    <input id="colorinput" type="color" style="display:none;"></input>

</div>
<div class="sidebar-pane" id="help">
    <h1 class="sectiontitle"><?php p($l->t('About GpxPod')); ?></h1>
    <hr/><br/>
    <h3 class="sectiontitle"><?php p($l->t('Shortcuts')); ?> :</h3>
    <ul class="disclist">
        <li><b>&lt;</b> : <?php p($l->t('toggle sidebar')); ?></li>
        <li><b>!</b> : <?php p($l->t('toggle minimap')); ?></li>
    </ul>
    <br/><hr/><br/>
    <h3 class="sectiontitle"><?php p($l->t('Features')); ?> :</h3>
    <ul class="disclist">
        <li>View :
        <ul class="circlist">
        <li>Click on marker cluster to zoom in.</li>
        <li>Click on track line or track marker to show popup with track stats
        and a link to draw track elevation profile.</li>
        <li>In main sidebar tab, the table lists all track that fits into
        current map bounds. This table is kept up to date when you zoom or move.</li>
        <li>Sidebar table columns are sortable.</li>
        <li>In sidebar table and track popup, click on track links to download
        the GPX file.</li>
        <li>"Transparency" option : enable sidebar transparency when hover on
        table rows to display track overviews.</li>
        <li>"Display markers" option : hide all map markers. Sidebar table still
        lists available tracks in current map bounds.</li>
        <li>Auto popup : toggle popup opening when drawing a track</li>
        <li>Auto zoom : toggle zoom when changing folder or drawing a track</li>
        <li>Dynamic table : Always show all tracks if disabled. Otherwise
        , update the table when zooming or moving the map view.</li>
        <li>Track coloration : color each track segment depending on elevation or speed.</li>
        <li>Browser timezone detection.</li>
        <li>Manual timezone setting.</li>
        <li>Several criterias to list tracks in sidebar table</li>
        <li>Filter visible tracks by length, date, cumulative elevation gain.</li>
        <li>Add personal <a href="https://wiki.openstreetmap.org/wiki/Tile_servers" target="_blank">custom tile servers</a>. <a href="https://wiki.openstreetmap.org/wiki/WMS#OSM_WMS_Servers" target="_blank">WMS</a> servers are supported.</li>
        <li>Display geotagged JPG pictures</li>
        </ul>
        </li>

        <li>Share :
        <ul class="circlist">
        <li>Share track : In sidebar table, click on <i class="fa fa-share-alt" aria-hidden="true"></i> near the track name to get a public link which
        works only if the track (or one of its parent directories) is shared in
        "Files" app with public without password.</li>
        <li>Share folder : Near the selected folder, click on <i class="fa fa-share-alt" aria-hidden="true"></i> to get a public link to currently selected folder.
        This link will work only if the folder is shared in "Files" app with public without password.</li>
        </ul>
        </li>

        <li>Other :
        <ul class="circlist">
        <li>Ability to clean old files produced by old GpxPod versions.</li>
        <li>Pre-process tracks with SRTM.py (if installed and found
        on server's system) to correct elevations.
        This can be done on a single track (with a link in track popup) or on a whole folder (with scan type).</li>
        <li>Convert KML, IGC and TCX files to gpx (GpsBabel is needed on server's system for IGC and TCX).</li>
        </ul>
        </li>

        <li>Many leaflet plugins are active :
            <ul class="circlist">
                <li>Markercluster</li>
                <li>Elevation (modified to display time when hover on graph)</li>
                <li>Sidebar-v2</li>
                <li>Control Geocoder (search in nominatim DB)</li>
                <li>Minimap (bottom-left corner of map)</li>
                <li>MousePosition</li>
            </ul>
        </li>
    </ul>

    <br/><hr/><br/>
    <h3 class="sectiontitle"><?php p($l->t('Documentation')); ?></h3>
    <a class="toplink" target="_blank" href="https://gitlab.com/eneiluj/gpxpod-oc/wikis/home">
    <i class="fab fa-gitlab" aria-hidden="true"></i>
    Project wiki
    </a>
    <br/>

    <br/><hr/><br/>
    <h3 class="sectiontitle"><?php p($l->t('Source management')); ?></h3>
    <ul class="disclist">
        <li><a class="toplink" target="_blank" href="https://gitlab.com/eneiluj/gpxpod-oc">
        <i class="fab fa-gitlab" aria-hidden="true"></i>
        Gitlab project main page</a></li>
        <li><a class="toplink" target="_blank" href="https://gitlab.com/eneiluj/gpxpod-oc/issues">
        <i class="fab fa-gitlab" aria-hidden="true"></i>
        Gitlab project issue tracker</a></li>
        <li><a class="toplink" target="_blank" href="https://crowdin.com/project/gpxpod">
        <i class="fa fa-globe-africa" aria-hidden="true"></i>
        Help us to translate this app on Crowdin !</a></li>
    </ul>

    <br/><hr/><br/>
    <h3 class="sectiontitle"><?php p($l->t('Authors')); ?> :</h3>
    <ul class="disclist">
        <li>Julien Veyssier</li>
        <li>Fritz Kleinschroth (german translation)</li>
        <li>@slipeer (russian translation)</li>
    </ul>

</div>
</div>
</div>
<!-- ============= MAP DIV =============== -->
<div id="map" class="sidebar-map"></div>

