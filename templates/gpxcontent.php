<?php
$data_folder = $_['userAbsoluteDataPath'];
$path_to_gpxpod = getcwd().'/apps/gpxpod/gpxpod.py';
$subfolder = '';
$gpxcomp_root_url = "gpxvcomp";

if (!empty($_GET)){
    //$subfolder = str_replace(array('/', '\\'), '',  $_GET['subfolder']);
    $subfolder = str_replace(array('../', '..\\'), '',  $_GET['subfolder']);
    $path_to_process = $data_folder.$subfolder;
    if (file_exists($path_to_process) and is_dir($path_to_process)){
        // then we process the folder if it was asked
        if (!isset($_GET['computecheck']) or $_GET['computecheck'] === 'no'){
            exec(escapeshellcmd(
                    $path_to_gpxpod.' '.escapeshellarg($path_to_process)
                ),
                $output, $returnvar);
        }
    }
    else{
        //die($path_to_process.' does not exist');
    }
}

?>

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
$dirs = Array();
// use RecursiveDirectoryIterator if it exists in this environment
if (class_exists('RecursiveDirectoryIterator')){
    $it = new RecursiveDirectoryIterator($data_folder);
    $display = Array ( 'gpx' );
    foreach(new RecursiveIteratorIterator($it) as $file){
        if (in_array(strtolower(array_pop(explode('.', $file))), $display)){
            $dir = str_replace($data_folder,'',dirname($file));
            if ($dir === ''){
                $dir = '/';
            }
            if (!in_array($dir, $dirs)){
                array_push($dirs, $dir);
            }
        }
    }
}
// if no RecursiveDirectoryIterator was found, use recursive glob method
else{
    function globRecursive($path, $find) {
        $dh = opendir($path);
        while (($file = readdir($dh)) !== false) {
            if (substr($file, 0, 1) == '.') continue;
            $rfile = "{$path}/{$file}";
            if (is_dir($rfile)) {
                foreach (globRecursive($rfile, $find) as $ret) {
                    yield $ret;
                }
            } else {
                if (fnmatch($find, $file)) yield $rfile;
            }
        }
        closedir($dh);
    }
    $files = globRecursive($data_folder, '*.gpx');
    foreach($files as $file){
        $dir = str_replace($data_folder,'',dirname($file));
        if ($dir === ''){
            $dir = '/';
        }
        if (!in_array($dir, $dirs)){
            array_push($dirs, $dir);
        }
    }
}

// populate select options
foreach($dirs as $dir){
    $selected = '';
    // TODO verif si variable existe
    if ($dir === $subfolder){
        $selected = 'selected="selected"';
    }
    echo '<option '.$selected.'>';
    p($dir);
    echo '</option>'."\n";
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
            <input id='computecheck' name='computecheck' 
            type='checkbox' title="Disables gpx file analysis, will
            not display freshly created tracks but page will load 
            faster" value="yes"><label for='computecheck' 
            title="Disables gpx file analysis, will not display
            freshly created tracks but page will load faster" 
            id="computechecklabel">Avoid markers and tracks 
            processing</label>
            </div>
        </div>
<?php

if (count($dirs) === 0){
    echo '<br/><p id="nofolder">No gpx file found</p>
        <br/><p id="nofoldertext">You should have at least one gpx file
        in your files.</p>';
}
?>
    </form>

    </div>
    <div style="clear:both"></div>
    <hr/>
    <div id="options">
        <h3 class="sectiontitle">Options</h3>
        <div id="optionbuttonsdiv">
            <button id='removeelevation' class="uibutton">
            Hide elevation profile
            </button>
            <br/>
            <button id='comparebutton'  class="uibutton">
            Compare selected tracks
            </button>
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
    <div style="clear:both"></div>
    <hr/>
    <h3 id="ticv" class="sectiontitle">Tracks inside current view</h3>
    <div id="loading"><p>loading&nbsp;</p></div>
    <div id="gpxlist"></div>
<?php

if ($subfolder !== ''){
    echo '<p id="markers" style="display:none">';
    p(file_get_contents($path_to_process.'/markers.txt'));
    echo '</p>'."\n";

    echo '<p id="subfolder" style="display:none">';
    p($subfolder);
    echo '</p>'."\n";

    echo '<p id="rooturl" style="display:none">';
    p($root_url);
    echo '</p>'."\n";

    echo '<p id="gpxcomprooturl" style="display:none">';
    p($gpxcomp_root_url);
    echo '</p>'."\n";
}
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
</div>
</div>
</div>
<!-- ============================ -->
<div id="map" class="sidebar-map"></div>

