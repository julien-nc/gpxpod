<?php
$abs_path_to_gpxvcomp = getcwd().'/apps/gpxpod/gpxvcomp.py';
$data_folder = getcwd().'/data/'.$_['user'].'/files/gpx/';

$gpxs = Array();

$tempdir = getcwd().'/data/'.$_['user'].'/cache/'.rand();
mkdir($tempdir);

// gpx in GET parameters
if (!empty($_GET)){
    $subfolder = str_replace(array('/', '\\'), '',  $_GET['subfolder']);
    for ($i=1; $i<=10; $i++){
        if (isset($_GET['name'.$i]) and $_GET['name'.$i] != ""){
            $name = str_replace(array('/', '\\'), '',  $_GET['name'.$i]);
            file_put_contents($tempdir.'/'.$name, file_get_contents($data_folder
                              .$subfolder.'/'.$name));
            array_push($gpxs, $name);
        }
    }
}

// we uploaded a gpx
if (!empty($_POST)){
    // we copy each gpx in the tempdir
    for ($i=1; $i<=10; $i++){
        if (isset($_FILES["gpx$i"]) and $_FILES["gpx$i"]['name'] != ""){
            $name = str_replace(" ","_",$_FILES["gpx$i"]['name']);
            copy($_FILES["gpx$i"]['tmp_name'], "$tempdir/$name");
            array_push($gpxs, $name);
        }
    }
}

if (count($gpxs)>0){
    // then we process the files
    $params = "";
    foreach($gpxs as $gpx){
        $shella = escapeshellarg($gpx);
        $params .= " $shella";
    }
    chdir("$tempdir");
    exec(escapeshellcmd($abs_path_to_gpxvcomp.' '.$params),
         $output, $returnvar);
}

?>
 <div id="sidebar" class="sidebar">
<!-- Nav tabs -->
<ul class="sidebar-tabs" role="tablist">
<li class="active"><a href="#ho" role="tab"><i class="fa fa-bars"></i></a></li>
<li><a href="#stats" role="tab"><i class="fa fa-table"></i></a></li>
<li><a href="#help" role="tab"><i class="fa fa-question"></i></a></li>
</ul>
<!-- Tab panes -->
<div class="sidebar-content active">
<div class="sidebar-pane active" id="ho">

            <div id="logo">
            </div>
            <hr/>
            <div id="upload">
<?php
if (count($gpxs)>0 and $returnvar != 0){
    echo "<b>Python process failure : $returnvar</b><br/>";
    echo "<br/>".implode("<br/>",$output);
    echo "<br/>Check your input files";
}
?>
            <h3 class="sectiontitle">Gpx files to compare :</h3>
            <form id="formgpx" enctype="multipart/form-data" method="post"
            action="gpxvcompp">
            <div class="fileupdiv"><input id="gpxup1" name="gpx1" type="file"/>
            </div>
            <div class="fileupdiv"><input id="gpxup2" name="gpx2" type="file"/>
            </div>
            <button class="addFile" >+</button><br/>
            <!-- it appears that gpxup* inputs are not in $_POST ...
            so we need a fake input -->
            <input type="hidden" name="nothing" value="plop"/>
            <button id="saveForm" class="uibutton">Compare</button>
            </form>
            </div>
            <hr />
            <div id="links"></div>
            <div id="status"></div>
<?php
if (count($gpxs)>0){
    echo"<hr />";
    echo "<p>File pair to compare : <select id='gpxselect'>";
    $len = count($gpxs);
    for ($i=0; $i<$len; $i++){
        for ($j=$i+1; $j<$len; $j++){
            echo "<option>".str_replace(' ','_',$gpxs[$i]).
                 " and ".str_replace(' ','_',$gpxs[$j])."</option>\n";
        }
    }
    echo "</select></p>";
    echo "<p>Criteria to compare :";
    echo "<select id='criteria'>";
    echo "<option>time</option>";
    echo "<option>distance</option>";
    echo "<option>positive height difference</option>";
    echo "</select></p>";
}

if (count($gpxs)>0){
    foreach($gpxs as $gpx){
        echo '<p id="';
        p(str_replace(' ','_',str_replace('.gpx','',$gpx)));
        echo '" style="display:none">';
        p(file_get_contents($gpx.'.geojson'));
        echo '</p>'."\n";
        unlink($gpx.'.geojson');
        unlink($gpx);
    }
}

if (!rmdir($tempdir)){
    echo "Problem deleting temporary dir on server";
}
?>



</div>
<div class="sidebar-pane" id="stats">
    <h1 class="sectiontitle">Stats on loaded tracks</h1>Coming soon
</div>
<div class="sidebar-pane" id="help"><h1 class="sectiontitle">Help</h1>
<h3  class="sectiontitle">Shortcuts (tested on Firefox and Chromium)</h3>
    <ul>
        <li>&lt; : toggle sidebar</li>
        <li>! : toggle minimap</li>
        <li>œ or ² : toggle search</li>
    </ul>
    <br/> 
    <br/> 
    <h3 class="sectiontitle">Features</h3>
    <ul>
        <li>Select track files to compare (two or more) and press compare to
        process a comparison between each divergent part of submitted
        track.</li>
        <li>Click on tracks lines to display details on sections
        (divergent or not).</li>
        <li>Click on sidebar current tab icon to toggle sidebar.</li>
        <li>Many leaflet plugins are active :
            <ul>
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

