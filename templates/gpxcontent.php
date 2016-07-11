<div id="sidebar" class="sidebar">
<!-- Nav tabs -->
<ul class="sidebar-tabs" role="tablist">
<li class="active"><a href="#ho" role="tab"><i class="fa fa-bars"></i></a></li>
<li><a href="#settings" role="tab"><i class="fa fa-gear"></i></a></li>
<li><a href="#help" role="tab"><i class="fa fa-question"></i></a></li>
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
            <label for="subfolderselect">Folder :</label>
            <select name="subfolder" id="subfolderselect">
            <option style="color:red; font-weight:bold">Choose a folder</option>
<?php

// populate select options
foreach($_['dirs'] as $dir){
    echo '<option>';
    p($dir);
    echo '</option>'."\n";
}

?>
            </select>
            <button id="saveForm" class="uibutton">Display</button>
        </div>
        <div id="folderselection">
            <div id="scantypediv">
                <div id="computecheckdiv" 
    title="'Process new files only' : only process new files since last process.

'Process all files' : process everything
You should do it after installing a new GpxPod version.
Usefull if a file was modified since last process.
<?php
if (count($_['extra_scan_type']) > 0){
    echo '
SRTM correction is done with SRTM.py (gpxelevations)';
}
?>
">
                    <label for="processtypeselect">Scan type :</label>
                    <select name="processtype" id="processtypeselect">
                    <option value="new" selected="selected"
                    >Process new files only</option>
                    <option value="all"
                    >Process all files</option>
<?php
foreach ($_['extra_scan_type'] as $opt => $txt){
    echo '<option value="';
    p($opt);
    echo '">';
    p($txt);
    echo '</option>';
}
?>
                    </select>
                </div>
            </div>
<?php

if (count($_['dirs']) === 0){
    echo '<br/><p id="nofolder">No gpx file found</p>
        <br/><p id="nofoldertext">You should have at least one gpx/kml/tcx file
        in your files.</p>';
}

?>
        </div>
    </div>
    <div style="clear:both"></div>
    </form>
    <hr/>
    <div id="options">
        <div>
        <h3 class="sectiontitle" style="float:left;">Options</h3>
        </div>
        <div style="clear:both"></div>
        <div id="optionbuttonsdiv">
            <button id="removeelevation" class="uibutton">
            Hide elevation profile&nbsp;&nbsp;&nbsp;&nbsp;
            </button>
            <br/>
            <button id="comparebutton"  class="uibutton">
            Compare selected tracks
            </button>
            <br/>
            <div id="colorcriteriadiv" title="Enables tracks coloring by the
chosen criteria">
                <label for="colorcriteria">Color tracks by :</label>
                <select name="colorcriteria" id="colorcriteria">
                    <option>none</option>
                    <option>speed</option>
                    <option>slope</option>
                    <option>elevation</option>
                </select>
            </div>
            <br/>
            <select id="tzselect"></select>
        </div>
        <div id="optioncheckdiv">
            <div>
                <input id="displayclusters" type="checkbox" checked="checked">
                <label for="displayclusters">Display markers</label>
            </div>
            <div title="Open info popup when a track is drawn">
                <input id="openpopupcheck" type="checkbox" checked="checked">
                <label for="openpopupcheck">Auto-popup</label>
            </div>
            <div title="If enabled :
    - Zoom on track when it is drawn
    - Zoom to show all tracks when selecting a folder
If disabled :
    - Do nothing when a track is drawn
    - Reset zoom to world view when selecting a folder">
                <input id="autozoomcheck" type="checkbox" checked="checked">
                <label for="autozoomcheck">Auto-zoom</label>
            </div>
            <div title=
"Enables transparency when hover on table rows
 to display track overviews">
                <input id="transparentcheck" type="checkbox">
                <label for="transparentcheck">Transparency
                </label>
            </div>
            <div title="Table only shows tracks that are inside current map view">
                <input id="updtracklistcheck" type="checkbox" checked="checked">
                <label for="updtracklistcheck">Keep table up to date</label>
            </div>
        </div>
    </div>
    <div style="clear:both"></div>
    <hr/>
    <h3 id="ticv" class="sectiontitle">Tracks from current view</h3>
    <div id="tablecriteria"
    title="what determines if a track in shown in the table :
   - crosses : at least one track point is inside current view
   - starting point marker is inside current view
   - track square bounds intersect current view bounds square

If nothing ever shows up, try to process all files.
Anyway, if you recently change GpxPod version, do a 'process all files' once."
    >
        <label for="tablecriteriasel" id="tablecriterialabel">
            List tracks that :
        </label>
        <select name="tablecriteriasel" id="tablecriteriasel">
            <option value="cross">cross current view</option>
            <option value="start">start in current view</option>
            <option value="bounds">have N,S,E,W bounds crossing current view</option>
        </select>
    </div>
    <div id="loading"><p>loading track&nbsp;</p></div>
    <div id="loadingmarkers"><p>processing files&nbsp;</p></div>
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
<h1 class="sectiontitle">Settings and extra actions</h1>
<hr/>
<br/>
<div id="filtertabtitle">
    <h3 class="sectiontitle">Filters</h3>
    <button id="clearfilter" class="uibutton filterbutton">Clear</button>
    <button id="applyfilter" class="uibutton filterbutton">Apply</button>
</div>
<br/>
<ul id="filterlist" class="disclist">
    <li>
        <b>Date</b><br/>
        min : <input type="text" id="datemin"><br/>
        max : <input type="text" id="datemax">
    </li>
    <li>
        <b>Distance (m)</b><br/>
        min : <input id="distmin"><br/>
        max : <input id="distmax">
    </li>
    <li>
        <b>Cumulative elevation gain (m)</b><br/>
        min : <input id="cegmin"><br/>
        max : <input id="cegmax">
    </li>
</ul>
<br/>
<hr/>
<br/>
    <h3 class="sectiontitle">Custom tile servers</h3>
 (any change will take effect after page reload)
    <br/>
    <br/>
    <div id="tileserveradd">
        Server name (for example "my custom server") :
        <input type="text" id="tileservername"><br/>
        Server url ("http://tile.server.org/cycle/{z}/{x}/{y}.png") :
        <input type="text" id="tileserverurl"><br/>
        <button id="addtileserver" class="uibutton">Add</button>
    </div>
    <br/>
    <div id="tileserverlist">
        <h2>Your servers</h2>
        <ul class="disclist">
<?php
foreach($_['tileservers'] as $name=>$url){
    echo '<li name="';
    p($name);
    echo '" title="';
    p($url);
    echo '">';
    p($name);
    echo '<button>Delete</button></li>';
}
?>
        </ul>
    </div>

    <br/>
    <hr/>
    <br/>
    <h3 class="sectiontitle">Python output</h3>
    <p id="python_output" ></p>
    <br/>
    <hr/>
    <br/>
    <h3 class="sectiontitle">Clean files</h3>
    <button id="cleanall">Clean all markers and geojson files</button>
    <button id="clean">Clean markers and geojson files for existing gpx</button>
    <div id="clean_results"></div>
    <div id="deleting"><p>deleting&nbsp;&nbsp;&nbsp;</p></div>

</div>
<div class="sidebar-pane" id="help">
    <h1 class="sectiontitle">About GpxPod</h1>
    <hr/><br/>
    <h3 class="sectiontitle">Shortcuts :</h3>
    <ul class="disclist">
        <li>&lt; : toggle sidebar</li>
        <li>! : toggle minimap</li>
        <li>œ or ² : toggle search</li>
    </ul>
    <br/><hr/><br/>
    <h3 class="sectiontitle">Features :</h3>
    <ul class="disclist">
        <li>Click on marker cluster to zoom in.</li>
        <li>Click on track line or track marker to show popup with track stats
        and a link to draw track elevation profile.</li>
        <li>In main sidebar tab, the table lists all track that fits into
        current map bounds. This table is kept up to date when you zoom or move.</li>
        <li>Sidebar table columns are sortable.</li>
        <li>In sidebar table, [p] link near the track name is a public link which
        works only if the track (or one of its parent directories) is shared in
        "Files" app with public without password.</li>
        <li> Near the selected folder, the [p] link is a public link to currently selected folder.
        This link will work only if the folder is shared in "Files" app with public without password.</li>
        <li>In sidebar table and track popup, click on track links to download
        the GPX file.</li>
        <li>"Transparency" option : enable sidebar transparency when hover on
        table rows to display track overviews.</li>
        <li>"Display markers" option : hide all map markers. Sidebar table still
        lists available tracks in current map bounds.</li>
        <li>Auto popup : toggle popup opening when drawing a track</li>
        <li>Auto zoom : toggle zoom when changing folder or drawing a track</li>
        <li>Keep table up to date : Always show all tracks if disabled. Otherwise
        , update the table when zooming or moving the map view.</li>
        <li>Track coloration : color each track segment depending on elevation or speed or slope.</li>
        <li>Browser timezone detection.</li>
        <li>Manual timezone setting.</li>
        <li>Several criterias to list tracks in sidebar table</li>
        <li>Filter visible tracks by length, date, cumulative elevation gain.</li>
        <li>Add personnal custom tile servers.</li>
        <li>Ability to clean old files produced by old GpxPod versions.</li>
        <li>Pre-process tracks with SRTM.py (if installed and found
        on server's system) to correct elevations.
        This can be done on a single track or on a whole folder (with scan type).</li>
        <li>Convert KML and TCX files to gpx if GpsBabel is found on server's system.</li>
        <li>Many leaflet plugins are active :
            <ul class="disclist">
                <li>Markercluster</li>
                <li>Elevation</li>
                <li>Sidebar-v2</li>
                <li>Control Geocoder (search in nominatim DB)</li>
                <li>Minimap (bottom-left corner of map)</li>
                <li>MousePosition</li>
            </ul>
        </li>
    </ul>

    <br/><hr/><br/>
    <h3 class="sectiontitle">Source management</h3>
    <ul class="disclist">
        <li><a class="toplink" href="https://gitlab.com/eneiluj/gpxpod-oc">Gitlab project main page</a></li>
        <li><a class="toplink" href="https://gitlab.com/eneiluj/gpxpod-oc/issues">Gitlab project issue tracker</a></li>
    </ul>

    <br/><hr/><br/>
    <h3 class="sectiontitle">Authors :</h3>
    <ul class="disclist">
        <li>Julien Veyssier</li>
    </ul>

</div>
</div>
</div>
<!-- ============= MAP DIV =============== -->
<div id="map" class="sidebar-map"></div>

