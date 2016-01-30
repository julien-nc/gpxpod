<?php

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
if ($_['python_error_output'] !== null){
    echo "<b>Python process failure : ".$_['python_return_var']."</b><br/>";
    echo "<br/>".implode("<br/>", $_['python_error_output']);
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

if (count($_['gpxs'])>0){
    echo"<hr />";
    echo "<p>File pair to compare : <select id='gpxselect'>";
    $len = count($_['gpxs']);
    for ($i=0; $i<$len; $i++){
        for ($j=$i+1; $j<$len; $j++){
            echo "<option>".str_replace(' ','_',$_['gpxs'][$i]).
                 " and ".str_replace(' ','_',$_['gpxs'][$j])."</option>\n";
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

if (count($_['geojson'])>0){
    foreach($_['geojson'] as $geoname => $geocontent){
        echo '<p id="';
        p(str_replace(' ','_',str_replace('.gpx','',str_replace('.GPX','',$geoname))));
        echo '" style="display:none">';
        p($geocontent);
        echo '</p>'."\n";
    }
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
