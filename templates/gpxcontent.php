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

    'Process all files' : process everything, usefull if a file was
    modified since last process.">
                    <label for="processtypeselect">Scan type :</label>
                    <select name="processtype" id="processtypeselect">
                    <option value="new" selected="selected"
                    >Process new files only</option>
                    <option value="all"
                    >Process all files</option>
                    </select>
                </div>
            </div>
            <?php

            if (count($_['dirs']) === 0){
                echo '<br/><p id="nofolder">No gpx file found</p>
                    <br/><p id="nofoldertext">You should have at least one gpx file
                    in your files.</p>';
            }

            ?>

        </div>
    </div>
    <div style="clear:both"></div>
    </form>
    <hr/>
    <div id="options">
        <h3 class="sectiontitle">Options</h3>
        <div id="optionbuttonsdiv">
            <button id='removeelevation' class="uibutton">
            Hide elevation profile&nbsp;&nbsp;&nbsp;&nbsp;
            </button>
            <br/>
            <button id='comparebutton'  class="uibutton">
            Compare selected tracks
            </button>
            <br/>
            <div id="colorcriteriadiv">
                <label for='colorcriteria' title='Enables tracks coloring by the
                chosen criteria'>Color by :</label>
                <select name="colorcriteria" title='Enables tracks coloring by 
                the chosen criteria' id="colorcriteria">
                    <option>none</option>
                    <option>speed</option>
                    <option>slope</option>
                    <option>elevation</option>
                </select>
            </div>
        </div>
        <div id="optioncheckdiv">
            <input id='displayclusters' type='checkbox' checked='checked'>
            <label for='displayclusters'>Display markers</label>
            <br/>
            <input id='openpopupcheck' type='checkbox' checked='checked'
            title="Open info popup when a track is drawn">
            <label title="Open info popup when a track is drawn"
            for='openpopupcheck'>Auto-popup</label>
            <br/>
            <input id='autozoomcheck' type='checkbox' checked='checked'
            title="Zoom on track when it is drawn">
            <label title="Zoom on track when it is drawn"
            for='autozoomcheck'>Auto-zoom</label>
            <br/>
            <input id='transparentcheck' type='checkbox' title="Enables 
            transparency when hover on table rows to display track overviews">
            <label title="Enables transparency when hover on table rows to
            display track overviews" for='transparentcheck'>Transparency
            </label>
            <br/>
            <input id='updtracklistcheck' type='checkbox' checked='checked'
            title="Table only shows tracks that are inside current map view">
            <label title="Table only shows tracks that are inside current map view"
            for='updtracklistcheck'>Keep table up to date</label>
        </div>
    </div>
    <div style="clear:both"></div>
    <hr/>
    <h3 id="ticv" class="sectiontitle">Tracks from current view</h3>
    <div id="tablecriteria">
        <label for="tablecriteriasel" id="tablecriterialabel">
            List track if :
        </label>
        <select name="tablecriteriasel"
         title='what determines if a track in shown in the table :
   - starting point marker is inside current view
   - track square bounds intersect current view bounds square' id="tablecriteriasel">
            <option value="start">starting point visible</option>
            <option value="bounds">N,S,E,W bounds visible</option>
        </select>
    </div>
    <div id="loading"><p>loading track&nbsp;</p></div>
    <div id="loadingmarkers"><p>processing files&nbsp;</p></div>
    <div id="gpxlist"></div>
<?php

echo '<p id="gpxcomprooturl" style="display:none">';
p($_['gpxcomp_root_url']);
echo '</p>'."\n";

?>
</div>
<div class="sidebar-pane" id="settings">
<br/>
<div id="filtertabtitle">
    <h1 class="sectiontitle">Filters</h1>
    <button id="clearfilter" class="uibutton filterbutton">Clear</button>
    <button id="applyfilter" class="uibutton filterbutton">Apply</button>
</div>
<br/>
<br/>
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
</div>
<div class="sidebar-pane" id="help"><h1 class="sectiontitle">Help</h1>

    <h3 class="sectiontitle">Shortcuts :</h3>
    <ul class="disclist">
        <li>&lt; : toggle sidebar</li>
        <li>! : toggle minimap</li>
        <li>œ or ² : toggle search</li>
    </ul>
    <br/>
    <h3 class="sectiontitle">Features :</h3>
    <ul class="disclist">
        <li>Select folder on top of main sidebar tab and press "Display" load a
        folder content.</li>
        <li>Click on marker cluster to zoom in.</li>
        <li>Click on track line or track marker to show popup with track stats
        and a link to draw track elevation profile.</li>
        <li>In main sidebar tab, the table lists all track that fits into
        current map bounds. This table is kept up to date.</li>
        <li>Sidebar table columns are sortable.</li>
        <li>In sidebar table, [p] link near the track name is a permalink.</li>
        <li>In sidebar table and track popup, click on track links to download
        the GPX file.</li>
        <li>"Transparency" option : enable sidebar transparency when hover on
        table rows to display track overviews.</li>
        <li>"Display markers" option : hide all map markers. Sidebar table still
        lists available tracks in current map bounds.</li>
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
    <br/>
    <h3 class="sectiontitle">Python output :</h3>
<p id="python_output" ></p>

</div>
</div>
</div>
<!-- ============================ -->
<div id="map" class="sidebar-map"></div>

