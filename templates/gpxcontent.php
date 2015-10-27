<?php

$data_folder = getcwd().'/data/'.$_['user'].'/files/gpx';
$path_to_gpxpod = getcwd().'/apps/gpxpod/gpxpod.py';
$subfolder = "";

if (!empty($_GET)){
    $subfolder = $_GET['subfolder'];
    $path_to_process = $data_folder.'/'.$subfolder;
    if (file_exists($path_to_process) and is_dir($path_to_process)){
        // then we process the folder if it was asked
        if (!isset($_GET['computecheck']) or $_GET['computecheck'] == "no"){
            exec("$path_to_gpxpod $path_to_process", $output, $returnvar);
            error_log('I process !!!!!!');
        }
    }
    else{
        die("$path_to_process does not exist");
    }
}

?>

 <div id="sidebar" class="sidebar">
<!-- Nav tabs -->
<ul class="sidebar-tabs" role="tablist">
<li class="active"><a href="#home" role="tab"><i class="fa fa-bars"></i></a></li>
<li><a href="#settings" role="tab"><i class="fa fa-gear"></i></a></li>
<li><a href="#help" role="tab"><i class="fa fa-question"></i></a></li>
</ul>
<!-- Tab panes -->
<div class="sidebar-content active">
<div class="sidebar-pane active" id="home">
            <div id="logo">
                <!--p align="center"><img src="gpxpod.png"/></p-->
            </div>
            <hr />
            <div id="folderselection">
<?php
//echo $returnvar.'<br/>'.$path_to_process.'<br/>'.$path_to_gpxpod.'<br/>';
?>
                <form name="choosedir" method="get" action="?">
                    <div id="folderrightdiv">
                    <select name="subfolder" id="subfolderselect">
<?php
$dirs = array_filter(glob("$data_folder/*"), 'is_dir');
foreach($dirs as $dir){
    $selected = "";
    // TODO verif si variable existe
    if (basename($dir) === $subfolder){
        $selected = "selected='selected'";
    }
    echo "<option $selected>".basename($dir)."</option>\n";
}
?>
                    </select>
                    <br/>
                    <button id="saveForm" class="uibutton">Display</button>
                    </div>
                    <div id="folderleftdiv">
                        <b id="titlechoosedirform">Folder selection</b>
                        <br/>
                        <div id="computecheckdiv">
                        <input id='computecheck' name='computecheck' type='checkbox' title="Disables gpx file analysis, will not display freshly created tracks but page will load faster" value="yes"><label for='computecheck' title="Disables gpx file analysis, will not display freshly created tracks but page will load faster" id="computechecklabel">Avoid markers and tracks processing</label>
                        </div>
                    </div>
                </form>

            </div>
            <div style="clear:both"></div>
            <hr/>
            <div id="options">
                <h3 class="sectiontitle">Options</h3>
                <div id="optionbuttonsdiv">
                    <button id='removeelevation' class="uibutton">Hide elevation profile</button>
                    <br/>
                    <button id='comparebutton'  class="uibutton">Compare selected tracks</button>
                </div>
                <div id="optioncheckdiv">
                    <input id='displayclusters' type='checkbox' checked='checked'><label for='displayclusters'>Display markers</label>
                    <br/>
                    <input id='openpopupcheck' type='checkbox' checked='checked' title="Open info popup when a track is drawn"><label title="Open info popup when a track is drawn" for='openpopupcheck'>Auto-popup</label>
                    <br/>
                    <input id='autozoomcheck' type='checkbox' checked='checked' title="Zoom on track when it is drawn"><label title="Zoom on track when it is drawn" for='autozoomcheck'>Auto-zoom</label>
                    <br/>
                    <input id='transparentcheck' type='checkbox' title="Enables transparency when hover on table rows to display track overviews"><label title="Enables transparency when hover on table rows to display track overviews" for='transparentcheck'>Transparency</label>
                    <br/>
                    <label for='colorcriteria' title='Enables tracks coloring by the chosen criteria'>Color by :</label>
                    <select name="colorcriteria" title='Enables tracks coloring by the chosen criteria' id="colorcriteria">
                        <option>none</option>
                        <option>speed</option>
                        <option>slope</option>
                        <option>elevation</option>
                    </select>
                </div>
            </div>
            <div style="clear:both"></div>
            <hr/>
            <h3 class="sectiontitle">Tracks inside current view</h3>
            <div id="gpxlist"></div>
<?php

if ($subfolder != ""){
    echo "<p id='markers' style='display:none'>".file_get_contents("$path_to_process/markers.txt")."</p>\n";
    echo "<p id='subfolder' style='display:none'>$subfolder</p>\n";
    echo "<p id='rooturl' style='display:none'>$root_url</p>\n";
    echo "<p id='gpxcomprooturl' style='display:none'>$gpxcomp_root_url</p>\n";
}
?>
</div>
<div class="sidebar-pane" id="settings">
<br/>
<div id="filtertabtitle">
    <b>Filters</b>
    <button id="clearfilter" class="uibutton">Clear</button>
    <button id="applyfilter" class="uibutton">Apply</button>
</div>
<br/>
<br/>
<br/>
<ul id="filterlist">
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
    <ul>
        <li>&lt; : toggle sidebar</li>
        <li>! : toggle minimap</li>
        <li>œ or ² : toggle search</li>
    </ul>
    <br/>
    <h3 class="sectiontitle">Features :</h3>
    <ul>
        <li>Select folder on top of main sidebar tab and press "Display" load a folder content.</li>
        <li>Click on marker cluster to zoom in.</li>
        <li>Click on track line or track marker to show popup with track stats and a link to draw track elevation profile.</li>
        <li>In main sidebar tab, the table lists all track that fits into current map bounds. This table is kept up to date.</li>
        <li>Sidebar table columns are sortable.</li>
        <li>In sidebar table, [p] link near the track name is a permalink.</li>
        <li>In sidebar table and track popup, click on track links to download the GPX file.</li>
        <li>"Transparency" option : enable sidebar transparency when hover on table rows to display track overviews.</li>
        <li>"Display markers" option : hide all map markers. Sidebar table still lists available tracks in current map bounds.</li>
        <li>Many leaflet plugins are active :
            <ul>
                <li>Markercluster</li>
                <li>Elevation</li>
                <li>Sidebar-v2</li>
                <li>Control Geocoder (search in nominatim DB)</li>
                <li>Minimap (bottom-left corner of map)</li>
                <li>MousePosition</li>
            </ul>
        </li>
    </ul>
</div>
</div>
</div>
<!-- ============================ -->

        <div id="map" class="sidebar-map"></div>

