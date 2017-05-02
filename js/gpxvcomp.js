(function ($, OC) {
    'use strict';

    var gpxvcomp = {
        map: {},
        actualLayer: {},
        actualLayers: [],
        actualLayerNumber: -1,
        layers: [{}, {}],
        minimapControl: null,
        searchControl: null,
        mytzname: ''
    };

    var METERSTOMILES = 0.0006213711;
    var METERSTOFOOT = 3.28084;

    function metersToDistance(m) {
        var unit = gpxvcomp.measureunit;
        var n = parseFloat(m);
        if (unit === 'metric') {
            if (n > 1000) {
                return (n / 1000).toFixed(2) + ' km';
            }
            else{
                return n.toFixed(2) + ' m';
            }
        }
        else {
            var mi = n * METERSTOMILES;
            if (mi < 1) {
                return (n * METERSTOFOOT).toFixed(2) + ' ft';
            }
            else {
                return mi.toFixed(2) + ' mi';
            }
        }
    }

    function metersToDistanceNoAdaptNoUnit(m) {
        var unit = gpxvcomp.measureunit;
        var n = parseFloat(m);
        if (unit === 'metric') {
            return (n).toFixed(2);
        }
        else {
            return (n * 1000 * METERSTOMILES).toFixed(2);
        }
    }

    function metersToElevation(m) {
        var unit = gpxvcomp.measureunit;
        var n = parseFloat(m);
        if (unit === 'metric') {
            return n.toFixed(2) + ' m';
        }
        else {
            return (n * METERSTOFOOT).toFixed(2) + ' ft';
        }
    }

    function metersToElevationNoUnit(m) {
        var unit = gpxvcomp.measureunit;
        var n = parseFloat(m);
        if (unit === 'metric') {
            return n.toFixed(2);
        }
        else {
            return (n * METERSTOFOOT).toFixed(2);
        }
    }

    function kmphToSpeedNoUnit(kmph) {
        var unit = gpxvcomp.measureunit;
        var nkmph = parseFloat(kmph);
        if (unit === 'metric') {
            return nkmph.toFixed(2);
        }
        else {
            return (nkmph * 1000 * METERSTOMILES).toFixed(2);
        }
    }

    function load_map() {
        var default_layer = 'OpenStreetMap';
        gpxvcomp.map = new L.Map('map', {zoomControl: true});
        L.control.scale({metric: true, imperial: true, position:'topleft'})
            .addTo(gpxvcomp.map);
        L.control.mousePosition().addTo(gpxvcomp.map);
        L.control.sidebar('sidebar').addTo(gpxvcomp.map);
        gpxvcomp.searchControl = L.Control.geocoder({position:'topleft'});
        gpxvcomp.searchControl.addTo(gpxvcomp.map);
        gpxvcomp.locateControl = L.control.locate({follow:true});
        gpxvcomp.locateControl.addTo(gpxvcomp.map);

        // get url from key and layer type
        function geopUrl(key, layer, format) {
            return 'http://wxs.ign.fr/'+ key + '/wmts?LAYER=' + layer +
                '&EXCEPTIONS=text/xml&FORMAT='+(format?format:'image/jpeg') +
                '&SERVICE=WMTS&VERSION=1.0.0&REQUEST=GetTile&STYLE=normal' +
                '&TILEMATRIXSET=PM&TILEMATRIX={z}&TILECOL={x}&TILEROW={y}' ;
        }
        // change it if you deploy GPXPOD
        var API_KEY = 'ljthe66m795pr2v2g8p7faxt';
        var ign = new L.tileLayer ( geopUrl(API_KEY,'GEOGRAPHICALGRIDSYSTEMS.MAPS'),
                { attribution:'&copy; <a href="http://www.ign.fr/">IGN-France</a>',
                    maxZoom:18
                });

        var osmUrl = 'http://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
        var osmAttribution = 'Map data &copy; 2013 <a href="http://openstreetmap.'+
                             'org">OpenStreetMap</a> contributors';
        var osm = new L.TileLayer(osmUrl, {maxZoom: 18, attribution: osmAttribution});

        var osmfrUrl = 'https://{s}.tile.openstreetmap.fr/osmfr/{z}/{x}/{y}.png';
        var osmfr = new L.TileLayer(osmfrUrl,
                    {maxZoom: 20, attribution: osmAttribution});
        var osmfr2 = new L.TileLayer(osmfrUrl,
                     {minZoom: 0, maxZoom: 13, attribution: osmAttribution});

        var openmapsurferUrl = 'http://openmapsurfer.uni-hd.de/tiles/roads/'+
                               'x={x}&y={y}&z={z}';
        var openmapsurferAttribution = 'Imagery from <a href="http://giscie'+
        'nce.uni-hd.de/">GIScience Research Group @ University of Heidelberg'+
        '</a> &mdash;   Map data &copy; <a href="http://www.openstreetmap.org'+
        '/copyright">OpenStreetMap</a>';
        var openmapsurfer = new L.TileLayer(openmapsurferUrl,
                {maxZoom: 18, attribution: openmapsurferAttribution});

        var transportUrl = 'http://a.tile2.opencyclemap.org/transport'+
                           '/{z}/{x}/{y}.png';
        var transport = new L.TileLayer(transportUrl,
                        {maxZoom: 18, attribution: osmAttribution});

        var pisteUrl = 'http://tiles.openpistemap.org/nocontours/{z}/{x}/{y}.png';
        var piste = new L.TileLayer(pisteUrl,
                    {maxZoom: 18, attribution: osmAttribution});

        var hikebikeUrl = 'http://a.tiles.wmflabs.org/hikebike/{z}/{x}/{y}.png';
        var hikebike = new L.TileLayer(hikebikeUrl,
                       {maxZoom: 18, attribution: osmAttribution});

        var osmCycleUrl = 'http://{s}.tile.opencyclemap.org/cycle/{z}/{x}/{y}.png';
        var osmCycleAttrib = '&copy; <a href="http://www.opencyclemap.org">'+
        'OpenCycleMap</a>, &copy; <a href="http://www.openstreetmap.org/copyright">'+
        'OpenStreetMap</a>';
        var osmCycle = new L.TileLayer(osmCycleUrl,
                       {maxZoom: 18, attribution: osmCycleAttrib});

        var darkUrl = 'http://a.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}.png';
        var darkAttrib = '&copy; Map tiles by CartoDB, under CC BY 3.0. Data by'+
                         ' OpenStreetMap, under ODbL.';
        var dark = new L.TileLayer(darkUrl, {maxZoom: 18, attribution: darkAttrib});

        var esriTopoUrl = 'http://server.arcgisonline.com/ArcGIS/rest/services/'+
                          'World_Topo_Map/MapServer/tile/{z}/{y}/{x}';
        var esriTopoAttrib = 'Tiles &copy; Esri &mdash; Esri, DeLorme, NAVTEQ, '+
        'TomTom, Intermap, iPC, USGS, FAO, NPS, NRCAN, GeoBase, Kadaster NL,'+
        ' Ordnance Survey, Esri Japan, METI, Esri China (Hong Kong),'+
        ' and the GIS User Community';
        var esriTopo = new L.TileLayer(esriTopoUrl,
                       {maxZoom: 18, attribution: esriTopoAttrib});

        var esriAerialUrl = 'http://server.arcgisonline.com/ArcGIS/rest/services/'+
                            'World_Imagery/MapServer/tile/{z}/{y}/{x}';
        var esriAerialAttrib = 'Tiles &copy; Esri &mdash; Source: Esri, i-cubed,'+
        ' USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the'+
        ' GIS User Community';
        var esriAerial = new L.TileLayer(esriAerialUrl,
                         {maxZoom: 18, attribution: esriAerialAttrib});

        var routeUrl = 'http://{s}.tile.openstreetmap.fr/route500/{z}/{x}/{y}.png';
        var routeAttrib = '&copy, Tiles © <a href="http://www.openstreetmap.fr">'+
                          'OpenStreetMap France</a>';
        var route = new L.TileLayer(routeUrl,
                    {minZoom: 1, maxZoom: 20, attribution: routeAttrib});

        var tonerUrl = 'http://{s}.tile.stamen.com/toner/{z}/{x}/{y}.jpg';
        var stamenAttribution = '<a href="http://leafletjs.com" title="A JS library'+
        ' for interactive maps">Leaflet</a> | © Map tiles by <a href="http://stamen'+
        '.com">Stamen Design</a>, under <a href="http://creativecommons.org/license'+
        's/by/3.0">CC BY 3.0</a>, Data by <a href="http://openstreetmap.org">OpenS '+
        ' treetMap</a>, under <a href="http://creativecommons.org/licenses/by-sa/3.0'+
        '">CC BY SA</a>.';
        var toner = new L.TileLayer(tonerUrl,
                    {maxZoom: 18, attribution: stamenAttribution});

        var watercolorUrl = 'http://{s}.tile.stamen.com/watercolor/{z}/{x}/{y}.jpg';
        var watercolor = new L.TileLayer(watercolorUrl,
                         {maxZoom: 18, attribution: stamenAttribution});

        gpxvcomp.map.setView(new L.LatLng(47, 3), 6);

        var baseLayers = {
              'OpenStreetMap': osm,
              'OpenCycleMap': osmCycle,
              'IGN France': ign,
              'OpenMapSurfer Roads': openmapsurfer,
              'Hike & bike': hikebike,
              'OSM Transport': transport,
              'ESRI Aerial': esriAerial,
              'ESRI Topo with relief': esriTopo,
              'Dark' : dark,
              'Toner' : toner,
              'Watercolor' : watercolor,
              'OpenStreetMap France': osmfr
        };
        // add custom layers
        $('#tileservers li').each(function(){
            var sname = $(this).attr('name');
            var surl = $(this).attr('title');
            baseLayers[sname] = new L.TileLayer(surl,
                    {maxZoom: 18, attribution: 'custom tile server'});
        });
        var baseOverlays = {
            'OsmFr Route500': route,
            'OpenPisteMap Relief': L.tileLayer(
                    'http://tiles2.openpistemap.org/landshaded/{z}/{x}/{y}.png',
                    {
                        attribution: '&copy, Tiles © <a href="http://www.openstreet'+
                            'map.fr">OpenStreetMap France</a>',
                        minZoom: 1, maxZoom: 15
                    }),
            'OpenPisteMap pistes' : piste
        };

        new L.control.layers(baseLayers, baseOverlays).addTo(gpxvcomp.map);

        gpxvcomp.minimapControl = new L.Control.MiniMap(
                osmfr2,
                {
                    toggleDisplay: true,
                    position:'bottomleft'
                })
        .addTo(gpxvcomp.map);
        gpxvcomp.minimapControl._toggleDisplayButtonClicked();

        gpxvcomp.map.addLayer(baseLayers[default_layer]);

        gpxvcomp.map.on('contextmenu',function(){return;});
    }

    function styleFunction(feature) {
        return {
            color: getColor(feature.properties),
            opacity: 0.9
        };
    }

    function eachFeatureFunction(feature, layer, name) {
        var y, other, t1s, t2s;

        var criteria = $('select#criteria option:selected').text();

        var linecolor = getColor(feature.properties);
        var tooltiptxt;
        if (linecolor === 'blue'){
            tooltiptxt = name;
        }
        else if (linecolor === 'green'){
            tooltiptxt = name + '<br/>(' + t('gpxpod', 'better in') +
                         ' ' + criteria +')';
        }
        else if (linecolor === 'red'){
            tooltiptxt = name + '<br/>(' +
                         t('gpxpod','worse in') +
                         ' ' + criteria + ')';
        }
        layer.bindTooltip(tooltiptxt, {sticky:true});

        var txt ='';
        txt = txt + '<h3 style="text-align:center;">'+t('gpxpod','Track')+' : '+
        name+'</h3><hr/>';
        if(feature.properties.time !== null) {
            txt = txt + '<div style="width:100%;text-align:center;">'+
                '<b><u>'+t('gpxpod','Divergence details')+'</u></b></div>';

            var shorter = (('shorterThan' in feature.properties) &&
                    (feature.properties.shorterThan.length > 0)
                    );
            var distColor = shorter ? 'green' : 'red';

            txt = txt + '<ul><li style="color:'+distColor+';"><b>'+
                t('gpxpod','Divergence distance')+'</b>&nbsp;: '+
                metersToDistance(feature.properties.distance) +
                '</li>';
            if (shorter){
                txt = txt +'<li style="color:green">'+t('gpxpod','is shorter than')+' '+
                    '&nbsp;: <div style="color:red">';
                for(y = 0; y < feature.properties.shorterThan.length; y++){
                    other = feature.properties.shorterThan[y];
                    txt = txt + other +' (' +
                            metersToDistance(feature.properties.distanceOthers[other]) + ')';
                }
                txt = txt + '</div> &nbsp;</li>';
            }
            else{
                txt = txt +'<li style="color:red">'+t('gpxpod','is longer than')+' '+
                    '&nbsp;: <div style="color:green">';
                for (y = 0; y < feature.properties.longerThan.length; y++){
                    other = feature.properties.longerThan[y];
                    txt = txt + other + ' (' +
                            metersToDistance(feature.properties.distanceOthers[other]) + ')';
                }
                txt = txt + '</div> &nbsp;</li>';
            }

            var quicker = (('quickerThan' in feature.properties) &&
                    (feature.properties.quickerThan.length > 0)
                    );
            var timeColor = quicker ? 'green' : 'red';

            txt = txt +'<li style="color:'+timeColor+';"><b>'+
                t('gpxpod','Divergence time')+'</b>&nbsp;: '+
                feature.properties.time+' &nbsp;</li>';
            if (quicker){
                txt = txt +'<li style="color:green">'+
                    t('gpxpod','is quicker than')+' '+
                    '&nbsp;: <div style="color:red">';
                for(y = 0; y < feature.properties.quickerThan.length; y++){
                    other = feature.properties.quickerThan[y];
                    txt = txt + other + ' (' + feature.properties.timeOthers[other] + ')';
                }
                txt = txt + '</div> &nbsp;</li>';
            }
            else{
                txt = txt +'<li style="color:red">'+t('gpxpod','is slower than')+' '+
                    '&nbsp;: <div style="color:green">';
                for(y = 0; y < feature.properties.slowerThan.length; y++){
                    other=feature.properties.slowerThan[y];
                    txt = txt+other+' ('+feature.properties.timeOthers[other]+')';
                }
                txt = txt + '</div> &nbsp;</li>';
            }

            var lessDeniv = (('lessPositiveDenivThan' in feature.properties) &&
                (feature.properties.lessPositiveDenivThan.length > 0)
                );
            var denivColor = lessDeniv ? 'green' : 'red';

            txt = txt + '<li style="color:'+denivColor+';"><b>'+
            t('gpxpod','Cumulative elevation gain')+' </b>'+
            '&nbsp;: '+
            metersToElevation(feature.properties.positiveDeniv)+
            '</li>';
            if (lessDeniv){
                txt = txt +'<li style="color:green">'+t('gpxpod','is less than')+' '+
                    '&nbsp;: <div style="color:red">';
                for(y = 0; y<feature.properties.lessPositiveDenivThan.length; y++){
                    other = feature.properties.lessPositiveDenivThan[y];
                    txt = txt + other + ' (' +
                            metersToElevation(feature.properties.positiveDenivOthers[other])+')';
                }
                txt = txt + '</div> &nbsp;</li>';
            }
            else{
                txt = txt +'<li style="color:red">'+t('gpxpod','is more than')+' '+
                    '&nbsp;: <div style="color:green">';
                for(y = 0; y < feature.properties.morePositiveDenivThan.length; y++){
                    other = feature.properties.morePositiveDenivThan[y];
                    txt = txt + other + ' (' +
                            metersToElevation(feature.properties.positiveDenivOthers[other])+')';
                }
                txt = txt + '</div> &nbsp;</li>';
            }
            txt = txt + '</ul>';
        }
        else{
            txt = txt + '<li><b>'+t('gpxpod','There is no divergence here')+'</b></li>';
        }
        txt = txt + '<hr/>';
        txt = txt + '<div style="text-align:center">';
        txt = txt + '<b><u>'+t('gpxpod','Segment details')+'</u></b></div>';
        txt = txt + '<ul>';
        txt = txt + '<li>'+t('gpxpod','Segment id')+' : '+feature.properties.id+'</li>';
        txt = txt + '<li>'+t('gpxpod','From')+' : '+feature.geometry.coordinates[0][1]+
            ' ; '+feature.geometry.coordinates[0][0]+'</li>';
        var lastCoordIndex = feature.geometry.coordinates.length-1;
        txt = txt + '<li>'+t('gpxpod','To')+' : '+feature.geometry.coordinates[lastCoordIndex][1]+
            ' ; '+feature.geometry.coordinates[lastCoordIndex][0]+'</li>';
        try{
            var tsplt = feature.properties.timestamps.split(' ; ');
            var t1 = moment(tsplt[0].replace(' ','T'));
            var t2 = moment(tsplt[1].replace(' ','T'));
            t1.tz(gpxvcomp.mytzname);
            t2.tz(gpxvcomp.mytzname);
            t1s = t1.format('YYYY-MM-DD HH:mm:ss (Z)');
            t2s = t2.format('YYYY-MM-DD HH:mm:ss (Z)');
        }
        catch(err){
            t1s = 'no date';
            t2s = 'no date';
        }
        txt = txt + '<li>' + t('gpxpod','Time') + ' :<br/>&emsp;' + t1s +
              ' &#x21e8; <br/>&emsp;' + t2s + '</li>';
        txt = txt + '<li>' + t('gpxpod','Elevation') + ' : ' +
              metersToElevation(feature.properties.elevation[0]) +
              ' &#x21e8; ' + metersToElevation(feature.properties.elevation[1]) + '</li>';
        txt = txt + '</ul>';
        layer.bindPopup(txt, {autoPan: true});
    }

    // if criteria or track pair is changed on the page, dynamically draw
    // considering changes
    function drawResults() {
        var layer;
        for (layer in gpxvcomp.actualLayers){
            gpxvcomp.map.removeLayer(gpxvcomp.actualLayers[layer]);
        }

        var criteria = $('select#criteria option:selected').val();
        var name1 = $( 'select option:selected' ).attr('name1');
        var name2 = $( 'select option:selected' ).attr('name2');
        var cleaname1 = name1.replace('.gpx','').replace('.GPX','').replace(' ','_');
        var cleaname2 = name2.replace('.gpx','').replace('.GPX','').replace(' ','_');
        var data1 = $('#'+cleaname1+cleaname2).html();
        var data2 = $('#'+cleaname2+cleaname1).html();
        var odata1 = $.parseJSON(data1);
        var odata2 = $.parseJSON(data2);

        var results = [odata1, odata2];
        var names = [name1, name2];
        var n;
        for(n = 0; n < 2; n++) {
            delete gpxvcomp.layers[n];
            gpxvcomp.layers[n] = new L.geoJson(results[n], {
                weight: 5,
                style: styleFunction,
                onEachFeature: function(feature, layer){
                    eachFeatureFunction(feature, layer, names[n]);
                }
            });
            gpxvcomp.layers[n].addTo(gpxvcomp.map);
        }

        gpxvcomp.actualLayers = [gpxvcomp.layers[0], gpxvcomp.layers[1]];
        var coord_min = results[0].features[0].geometry.coordinates[0];
        var coord_max = results[0].features[results[0].features.length-1].
                        geometry.coordinates[0];

        var bounds1 = gpxvcomp.layers[0].getBounds();
        var bounds2 = bounds1.extend(gpxvcomp.layers[1].getBounds());
        if (bounds2.isValid()) {
            gpxvcomp.map.fitBounds(bounds2,
                    {animate:true, paddingTopLeft: [parseInt($('#sidebar').css('width')),0]}
            );
        }
        //var txt = '<p>'+t('gpxpod','Comparison between')+' :\n';
        //txt = txt + '<ul class="trackpairlist"><li>'+name1+'</li><li>'+name2+'</li></ul></p>';
        var txt = '';
        if (! gpxvcomp.layers[0].getBounds().
              intersects(gpxvcomp.layers[1].getBounds())){

            txt = txt + '<p style="color:red">Those tracks are not comparable.</p>';
        }
        txt = txt + '<p>'+t('gpxpod', 'Click on a track line to get details on the section')+'.</p><br/>';
        $('#status').html(txt);

        colorSelectedTrackColumns();
    }

    // get a feature color considering the track pair
    // currently under comparison and the used criteria
    function getColor(props){
        var color = 'blue';
        var name1 = $( 'select#gpxselect option:selected' ).attr('name1');
        var name2 = $( 'select#gpxselect option:selected' ).attr('name2');
        var criteria = $('select#criteria option:selected').val();
        if (criteria === 'distance'){
            if ( ('shorterThan' in props) &&
                    (props.shorterThan.indexOf(name1) !== -1 ||
                     props.shorterThan.indexOf(name2) !== -1)){
                color = 'green';
            }
            if ( ('longerThan' in props) &&
                    (props.longerThan.indexOf(name1) !== -1 ||
                     props.longerThan.indexOf(name2) !== -1)){
                color = 'red';
            }
        }
        else if (criteria === 'time'){
            //console.log(props['quickerThan'] + ' // '+name1+ ' // '+name2);
            if ( ('quickerThan' in props) &&
                    (props.quickerThan.indexOf(name1) !== -1 ||
                     props.quickerThan.indexOf(name2) !== -1)){
                color = 'green';
            }
            if ( ('slowerThan' in props) &&
                    (props.slowerThan.indexOf(name1) !== -1 ||
                     props.slowerThan.indexOf(name2) !== -1)){
                color = 'red';
            }
        }
        else if (criteria === 'cumulative elevation gain'){
            if ( ('lessPositiveDenivThan' in props) &&
                    (props.lessPositiveDenivThan.indexOf(name1) !== -1 ||
                     props.lessPositiveDenivThan.indexOf(name2) !== -1)){
                color = 'green';
            }
            if ( ('morePositiveDenivThan' in props) &&
                    (props.morePositiveDenivThan.indexOf(name1) !== -1 ||
                     props.morePositiveDenivThan.indexOf(name2) !== -1)){
                color = 'red';
            }
        }
        return color;
    }

    function checkKey(e){
        e = e || window.event;
        var kc = e.keyCode;

        if (kc === 161){
            e.preventDefault();
            gpxvcomp.minimapControl._toggleDisplayButtonClicked();
        }
        if (kc === 60){
            e.preventDefault();
            $('#sidebar').toggleClass('collapsed');
        }
    }

    // add a file input in the form
    function addFileInput(){
        if ($('div.fileupdiv').length < 10){
            $('<div style="display:none" class="fileupdiv"><input id="gpxup99" '+
              'name="gpx99" type="file"/>&nbsp;<button class="rmFile" >'+
              '<i class="fa fa-minus-circle" aria-hidden="true"></i></button>'+
              '</div>').insertAfter($('div.fileupdiv:last')).slideDown(300);

            resetFileUploadNumbers();
        }
    }

    // remove a file input from the form
    function rmFileInput(elem){
        elem.slideUp(300,function(){
            elem.remove();
        });
        resetFileUploadNumbers();
    }

    // on each form modification, reset correctly
    // the field numbers to be consecutive
    function resetFileUploadNumbers(){
        var num = 1;
        $('div.fileupdiv').each(function(){
            var inp = $(this).find('input');
            inp.attr('id','gpx'+num);
            inp.attr('name','gpx'+num);
            num++;
        });
    }

    function colorSelectedTrackColumns(){
        // reset colors
        $('#stattable td').each(function(){
            if (! $(this).hasClass('statnamecol')){
                $(this).removeClass('selectedColumn');
                $(this).addClass('normal');
            }
        });
        // then color columns
        var name1 = $( 'select option:selected' ).attr('name1');
        var name2 = $( 'select option:selected' ).attr('name2');
        $('td[track="'+name1+'"]').addClass('selectedColumn');
        $('td[track="'+name2+'"]').addClass('selectedColumn');
        $('td[track="'+name1+'"]').removeClass('normal');
        $('td[track="'+name2+'"]').removeClass('normal');
    }

    function getMeasureUnit() {
        var unit = 'metric';
        var url = OC.generateUrl('/apps/gpxpod/getOptionsValues');
        var req = {
        };
        var optionsValues = '{}';
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: false
        }).done(function (response) {
            optionsValues = response.values;
        }).fail(function() {
        });
        optionsValues = $.parseJSON(optionsValues);
        if (optionsValues.measureunit !== undefined) {
            unit = optionsValues.measureunit;
        }

        gpxvcomp.measureunit = unit;

        // set unit in global table
        if (unit === 'metric') {
            $('.distanceunit').text('km');
            $('.speedunit').text('km/h');
            $('.elevationunit').text('m');
        }
        else {
            $('.distanceunit').text('mi');
            $('.speedunit').text('mi/h');
            $('.elevationunit').text('ft');
        }

        // convert values in global table
        $('table#stattable tr[stat=length_2d] td:not(.statnamecol)').each(function() {
            var val = $(this).text();
            $(this).text(metersToDistanceNoAdaptNoUnit(val));
        });
        $('table#stattable tr[stat=length_3d] td:not(.statnamecol)').each(function() {
            var val = $(this).text();
            $(this).text(metersToDistanceNoAdaptNoUnit(val));
        });
        $('table#stattable tr[stat=moving_avg_speed] td:not(.statnamecol)').each(function() {
            var val = $(this).text();
            $(this).text(kmphToSpeedNoUnit(val));
        });
        $('table#stattable tr[stat=avg_speed] td:not(.statnamecol)').each(function() {
            var val = $(this).text();
            $(this).text(kmphToSpeedNoUnit(val));
        });
        $('table#stattable tr[stat=max_speed] td:not(.statnamecol)').each(function() {
            var val = $(this).text();
            $(this).text(kmphToSpeedNoUnit(val));
        });
        $('table#stattable tr[stat=total_uphill] td:not(.statnamecol)').each(function() {
            var val = $(this).text();
            $(this).text(metersToElevationNoUnit(val));
        });
        $('table#stattable tr[stat=total_downhill] td:not(.statnamecol)').each(function() {
            var val = $(this).text();
            $(this).text(metersToElevationNoUnit(val));
        });
    }

    $(document).ready(function(){
        getMeasureUnit();
        var mytz = jstz.determine_timezone();
        gpxvcomp.mytzname = mytz.timezone.olson_tz;
        load_map();
        $('#stattable').tablesorter();
        $('select#gpxselect').change(function(){
            drawResults();
        });
        $('select#criteria').change(function(){
            drawResults();
        });
        $('select#gpxselect').change();

        $('button.addFile').click(function(e){
            e.preventDefault();
            addFileInput();
        });
        $('body').on('click','.rmFile', function(e) {
            e.preventDefault();
            rmFileInput($(this).parent());
        });

        document.onkeydown = checkKey;
    });

})(jQuery, OC);
