<div id="sidebar" class="sidebar">
<!-- Nav tabs -->
<ul class="sidebar-tabs" role="tablist">
<li class="active" title="<?php p($l->t('Folder and tracks selection')); ?>"><a href="#ho" role="tab"><i class="fa fa-bars"></i></a></li>
<li title="<?php p($l->t('Settings and extra actions')); ?>"><a href="#settings" role="tab"><i class="fa fa-gear"></i></a></li>
<li title="<?php p($l->t('About GpxPod')); ?>"><a href="#help" role="tab"><i class="fa fa-question"></i></a></li>
</ul>
<!-- Tab panes -->
<div class="sidebar-content active">
<div class="sidebar-pane active" id="ho">
    <form name="choosedir" method="get" action="?">
    <div id="logofolder">
        <div id="logo">
            <!--p align="center"><img src="gpxpod.png"/></p-->
            <div>
            <p>v
<?php
p($_['gpxpod_version']);
?>
            </p>
            </div>
        </div>
        <div id="folderdiv">
        <label for="subfolderselect"><?php p($l->t('Folder')); ?> :</label>
            <select name="subfolder" id="subfolderselect">
            <option style="color:red; font-weight:bold"><?php p($l->t('Choose a folder')); ?></option>
<?php

// populate select options
if (count($_['dirs']) > 0){
    foreach($_['dirs'] as $dir){
        echo '<option>';
        p($dir);
        echo '</option>'."\n";
    }
}

?>
            </select>
        </div>
            <div id="scantypediv">
                <div id="computecheckdiv"
                title="<?php
p($l->t('\'Process new files only\' : only process new files since last process'));
echo "\n\n";
p($l->t('\'Process all files\' : process everything'));
echo "\n";
p($l->t('You should do it after installing a new GpxPod version'));
echo "\n";
p($l->t('Usefull if a file was modified since last process'));
echo "\n"; ?>
<?php
if (count($_['extra_scan_type']) > 0){
    echo "\n";
    p($l->t('Elevation correction is done with SRTM.py (gpxelevations)'));
}
?>
">
                    <label for="processtypeselect"><?php p($l->t('Scan type')); ?> :</label>
                    <select name="processtype" id="processtypeselect">
                    <option value="new" selected="selected"
                    ><?php p($l->t('Process new files only')); ?></option>
                    <option value="all"
                    ><?php p($l->t('Process all files')); ?></option>
<?php
if (count($_['extra_scan_type']) > 0){
    foreach ($_['extra_scan_type'] as $opt => $txt){
        echo '<option value="';
        p($opt);
        echo '">';
        p($txt);
        echo '</option>';
    }
}
?>
                    </select>
                </div>
            </div>
<?php

if (count($_['dirs']) === 0){
    echo '<p id="nofolder">';
    p($l->t('No gpx file found'));
    echo '</p><p id="nofoldertext">';
    p($l->t('You should have at least one gpx/kml/tcx file in your files'));
    echo '.</p>';
}

?>
    </div>
    <div style="clear:both"></div>
    </form>
    <hr/>
    <div id="options">
        <div>
        <h3 id="optiontitle" class="sectiontitle"><?php p($l->t('Options')); ?> 
        <b id="optiontoggle"><i class="fa fa-compress"></i></b></h3>
        </div>
        <div style="clear:both"></div>
        <div id="optionscontent">
        <div id="optionbuttonsdiv">
            <button id="removeelevation" class="uibutton">
            <?php p($l->t('Hide elevation profile')); ?>
            </button>
            <br/>
            <button id="comparebutton"  class="uibutton">
            <?php p($l->t('Compare selected tracks')); ?>
            </button>
            <br/>
            <div id="colorcriteriadiv"
            title="<?php p($l->t('Enables tracks coloring by the chosen criteria')); ?>">
            <label for="colorcriteria"><?php p($l->t('Color tracks by')); ?> :</label>
                <select name="colorcriteria" id="colorcriteria">
                <option><?php p($l->t('none')); ?></option>
                <option><?php p($l->t('speed')); ?></option>
                <option><?php p($l->t('slope')); ?></option>
                <option><?php p($l->t('elevation')); ?></option>
                </select>
            </div>
            <br/>
            <select id="tzselect"></select>
        </div>
        <div id="optioncheckdiv">
            <div>
                <input id="displayclusters" type="checkbox" checked="checked">
                <label for="displayclusters"><?php p($l->t('Display markers'));?></label>
            </div>
            <div title="<?php p($l->t('Open info popup when a track is drawn')); ?>">
                <input id="openpopupcheck" type="checkbox" checked="checked">
                <label for="openpopupcheck"><?php p($l->t('Auto-popup')); ?></label>
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
                <label for="autozoomcheck"><?php p($l->t('Auto-zoom')); ?></label>
            </div>
            <div title=
            "<?php p($l->t('Enables transparency when hover on table rows to display track overviews')); ?>">
                <input id="transparentcheck" type="checkbox">
                <label for="transparentcheck"><?php p($l->t('Transparency')); ?>
                </label>
            </div>
            <div title="<?php p($l->t('Table only shows tracks that are inside current map view')); ?>">
                <input id="updtracklistcheck" type="checkbox" checked="checked">
                <label for="updtracklistcheck"><?php p($l->t('Dynamic table')); ?></label>
            </div>
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
echo "\n\n";
p($l->t('If nothing ever shows up in the table, try to \'process all files\'.'));
echo "\n";
p($l->t('Anyway, if you recently change GpxPod version, do a \'process all files\' once.'));
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
    <div id="loading"><p>
        <i class="fa fa-spinner fa-pulse fa-3x fa-fw"></i>
        <?php p($l->t('loading track')); ?>&nbsp;</p>
    </div>
    <div id="loadingmarkers"><p>
        <i class="fa fa-spinner fa-pulse fa-3x fa-fw"></i>
        <?php p($l->t('processing files')); ?>&nbsp;</p>
    </div>
    <div id="gpxlist"></div>
<?php

echo '<p id="gpxcomprooturl" style="display:none">';
p($_['gpxcomp_root_url']);
echo '</p>'."\n";
echo '<p id="publicgeo" style="display:none">';
p($_['publicgeo']);
echo '</p>'."\n";
echo '<p id="publicgeocol" style="display:none">';
p($_['publicgeocol']);
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
echo '<p id="token" style="display:none">';
p($_['token']);
echo '</p>'."\n";

?>
</div>
<div class="sidebar-pane" id="settings">
<h1 class="sectiontitle"><?php p($l->t('Settings and extra actions')); ?></h1>
<hr/>
<br/>
<div id="filtertabtitle">
    <h3 class="sectiontitle"><?php p($l->t('Filters')); ?></h3>
    <button id="clearfilter" class="uibutton filterbutton"><?php p($l->t('Clear')); ?></button>
    <button id="applyfilter" class="uibutton filterbutton"><?php p($l->t('Apply')); ?></button>
</div>
<br/>
<br/>
<ul id="filterlist" class="disclist">
    <li>
        <b><?php p($l->t('Date')); ?></b><br/>
        <?php p($l->t('min')); ?> : <input type="text" id="datemin"><br/>
        <?php p($l->t('max')); ?> : <input type="text" id="datemax">
    </li>
    <li>
        <b><?php p($l->t('Distance (m)'));?></b><br/>
        <?php p($l->t('min')); ?> : <input id="distmin"><br/>
        <?php p($l->t('max')); ?> : <input id="distmax">
    </li>
    <li>
        <b><?php p($l->t('Cumulative elevation gain (m)')); ?></b><br/>
        <?php p($l->t('min')); ?> : <input id="cegmin"><br/>
        <?php p($l->t('max')); ?> : <input id="cegmax">
    </li>
</ul>
<br/>
<hr/>
<br/>
    <h3 class="sectiontitle"><?php p($l->t('Custom tile servers')); ?></h3>
    <br/>
    <div id="tileserveradd">
        <?php p($l->t('Server name (for example \'my custom server\')')); ?> :
        <input type="text" id="tileservername"><br/>
        <?php p($l->t('Server url (\'http://tile.server.org/cycle/{z}/{x}/{y}.png\')')); ?> :
        <input type="text" id="tileserverurl"><br/>
        <button id="addtileserver" class="uibutton"><?php p($l->t('Add')); ?></button>
    </div>
    <br/>
    <div id="tileserverlist">
        <h2><?php p($l->t('Your servers')); ?></h2>
        <ul class="disclist">
<?php
if (count($_['tileservers']) > 0){
    foreach($_['tileservers'] as $name=>$url){
        echo '<li name="';
        p($name);
        echo '" title="';
        p($url);
        echo '">';
        p($name);
        echo '<button>';
        p($l->t('Delete'));
        echo '</button></li>';
    }
}
?>
        </ul>
    </div>

    <br/>
    <hr/>
    <br/>
    <h3 class="sectiontitle"><?php p($l->t('Python output')); ?></h3>
    <p id="python_output" ></p>
    <br/>
    <hr/>
    <br/>
    <h3 class="sectiontitle"><?php p($l->t('Clean files')); ?></h3>
    <button id="cleanall"><?php p($l->t('Delete all markers and geojson files')); ?></button>
    <button id="clean"><?php p($l->t('Delete markers and geojson files for existing gpx')); ?></button>
    <div id="clean_results"></div>
    <div id="deleting"><p>
        <i class="fa fa-spinner fa-pulse fa-3x fa-fw"></i>
        <?php p($l->t('deleting')); ?></p>
    </div>
    <div id="linkdialog" style="display:none;" title="Public link">
        <label id="linklabel" for="linkinput"></label>
        <br/>
        <input id="linkinput" type="text"></input>
    </div>

</div>
<div class="sidebar-pane" id="help">
    <h1 class="sectiontitle"><?php p($l->t('About GpxPod')); ?></h1>
    <hr/><br/>
    <h3 class="sectiontitle"><?php p($l->t('Shortcuts')); ?> :</h3>
    <ul class="disclist">
        <li><b>&lt;</b> : <?php p($l->t('toggle sidebar')); ?></li>
        <li><b>!</b> : <?php p($l->t('toggle minimap')); ?></li>
        <li><b>œ</b> or <b>²</b> : <?php p($l->t('toggle search')); ?></li>
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
        <li>Track coloration : color each track segment depending on elevation or speed or slope.</li>
        <li>Browser timezone detection.</li>
        <li>Manual timezone setting.</li>
        <li>Several criterias to list tracks in sidebar table</li>
        <li>Filter visible tracks by length, date, cumulative elevation gain.</li>
        <li>Add personnal custom tile servers.</li>
        </ul>
        </li>

        <li>Share :
        <ul class="circlist">
        <li>Share track : In sidebar table, [p] link near the track name is a public link which
        works only if the track (or one of its parent directories) is shared in
        "Files" app with public without password.</li>
        <li>Share folder : Near the selected folder, the [p] link is a public link to currently selected folder.
        This link will work only if the folder is shared in "Files" app with public without password.</li>
        </ul>
        </li>

        <li>Other :
        <ul class="circlist">
        <li>Ability to clean old files produced by old GpxPod versions.</li>
        <li>Pre-process tracks with SRTM.py (if installed and found
        on server's system) to correct elevations.
        This can be done on a single track (with a link in track popup) or on a whole folder (with scan type).</li>
        <li>Convert KML and TCX files to gpx if GpsBabel is found on server's system.</li>
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
    <a class="toplink" target="_blank" href="https://gitlab.com/eneiluj/gpxpod-oc/wikis/home">Project wiki</a>
    <br/>

    <br/><hr/><br/>
    <h3 class="sectiontitle"><?php p($l->t('Source management')); ?></h3>
    <ul class="disclist">
        <li><a class="toplink" target="_blank" href="https://gitlab.com/eneiluj/gpxpod-oc">Gitlab project main page</a></li>
        <li><a class="toplink" target="_blank" href="https://gitlab.com/eneiluj/gpxpod-oc/issues">Gitlab project issue tracker</a></li>
    </ul>

    <br/><hr/><br/>
    <h3 class="sectiontitle"><?php p($l->t('Authors')); ?> :</h3>
    <ul class="disclist">
        <li>Julien Veyssier</li>
        <li>Fritz Kleinschroth (german translation)</li>
    </ul>

</div>
</div>
</div>
<!-- ============= MAP DIV =============== -->
<div id="map" class="sidebar-map"></div>

