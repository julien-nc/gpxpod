(function ($, OC) {
    'use strict';

    //////////////// VAR DEFINITION /////////////////////

    var colors = [
        'red', 'cyan', 'purple', 'Lime', 'yellow',
        'orange', 'blue', 'brown', 'Chartreuse',
        'Crimson', 'DeepPink', 'Gold'
    ];
    var colorCode = {
        'red': '#ff0000',
        'cyan': '#00ffff',
        'purple': '#800080',
        'Lime': '#00ff00',
        'yellow': '#ffff00',
        'orange': '#ffa500',
        'blue': '#0000ff',
        'brown': '#a52a2a',
        'Chartreuse': '#7fff00',
        'Crimson': '#dc143c',
        'DeepPink': '#ff1493',
        'Gold': '#ffd700'
    };
    var lastColorUsed = -1;
    var gpxpod = {
        map: {},
        baseLayers: null,
        overlayLayers: null,
        restoredTileLayer: null,
        markers: [],
        markersPopupTxt: {},
        markerLayer: null,
        // layers currently displayed, indexed by track name
        gpxlayers: {},
        gpxCache: {},
        subfolder: '',
        // layer of current elevation chart
        elevationLayer: null,
        // track concerned by elevation
        elevationTrack: null,
        minimapControl: null,
        searchControl: null,
        sort: {},
        currentHoverLayer : null,
        currentHoverLayerOutlines: L.layerGroup(),
        currentHoverAjax: null,
        // dict indexed by track names containing running ajax (for tracks)
        // this dict is used in updateTrackListFromBounds to show spinner or checkbox in first td
        currentAjax: {},
        // to store the ajax progress percentage
        currentAjaxPercentage: {},
        currentMarkerAjax: null,
        currentCorrectingAjax: null,
        // as tracks are retrieved by ajax, there's a lapse between mousein event
        // on table rows and track overview display, if mouseout was triggered
        // during this lapse, track was displayed anyway. i solve it by keeping
        // this prop up to date and drawing ajax result just if its value is true
        insideTr: false,
        picturePopups: [],
        pictureSmallMarkers: [],
        pictureBigMarkers: []
    };

    var darkIcon  = L.Icon.Default.extend({options: {iconUrl: 'marker-desat.png'}});

    var hoverStyle = {
        weight: 12,
        opacity: 0.7,
        color: 'black'
    };
    var defaultStyle = {
        weight: 5,
        opacity: 1
    };

    /*
     * markers are stored as list of values in this format :
     *
     * m[0] : lat,
     * m[1] : lon,
     * m[2] : name,
     * m[3] : total_distance,
     * m[4] : total_duration,
     * m[5] : date_begin,
     * m[6] : date_end,
     * m[7] : pos_elevation,
     * m[8] : neg_elevation,
     * m[9] : min_elevation,
     * m[10] : max_elevation,
     * m[11] : max_speed,
     * m[12] : avg_speed
     * m[13] : moving_time
     * m[14] : stopped_time
     * m[15] : moving_avg_speed
     * m[16] : north
     * m[17] : south
     * m[18] : east
     * m[19] : west
     * m[20] : shortPointList
     * m[21] : tracknameList
     *
     */

    var LAT = 0;
    var LON = 1;
    var NAME = 2;
    var TOTAL_DISTANCE = 3;
    var TOTAL_DURATION = 4;
    var DATE_BEGIN = 5;
    var DATE_END = 6;
    var POSITIVE_ELEVATION_GAIN = 7;
    var NEGATIVE_ELEVATION_GAIN = 8;
    var MIN_ELEVATION = 9;
    var MAX_ELEVATION = 10;
    var MAX_SPEED = 11;
    var AVERAGE_SPEED = 12;
    var MOVING_TIME = 13;
    var STOPPED_TIME = 14;
    var MOVING_AVERAGE_SPEED = 15;
    var NORTH = 16;
    var SOUTH = 17;
    var EAST = 18;
    var WEST = 19;
    var SHORTPOINTLIST = 20;
    var TRACKNAMELIST = 21;
    var LINKURL = 22;
    var LINKTEXT = 23;
    var MOVING_PACE = 24;

    var symbolSelectClasses = {
        'Dot, White': 'dot-select',
        'Pin, Blue': 'pin-blue-select',
        'Pin, Green': 'pin-green-select',
        'Pin, Red': 'pin-red-select',
        'Flag, Green': 'flag-green-select',
        'Flag, Red': 'flag-red-select',
        'Flag, Blue': 'flag-blue-select',
        'Block, Blue': 'block-blue-select',
        'Block, Green': 'block-green-select',
        'Block, Red': 'block-red-select',
        'Blue Diamond': 'diamond-blue-select',
        'Green Diamond': 'diamond-green-select',
        'Red Diamond': 'diamond-red-select',
        'Residence': 'residence-select',
        'Drinking Water': 'drinking-water-select',
        'Trail Head': 'hike-select',
        'Bike Trail': 'bike-trail-select',
        'Campground': 'campground-select',
        'Bar': 'bar-select',
        'Skull and Crossbones': 'skullcross-select',
        'Geocache': 'geocache-select',
        'Geocache Found': 'geocache-open-select',
        'Medical Facility': 'medical-select',
        'Contact, Alien': 'contact-alien-select',
        'Contact, Big Ears': 'contact-bigears-select',
        'Contact, Female3': 'contact-female3-select',
        'Contact, Cat': 'contact-cat-select',
        'Contact, Dog': 'contact-dog-select',
    };

    var symbolIcons = {
        'Dot, White': L.divIcon({
                iconSize: L.point(7,7),
        }),
        'Pin, Blue': L.divIcon({
            className: 'pin-blue',
            iconAnchor: [5, 30]
        }),
        'Pin, Green': L.divIcon({
            className: 'pin-green',
            iconAnchor: [5, 30]
        }),
        'Pin, Red': L.divIcon({
            className: 'pin-red',
            iconAnchor: [5, 30]
        }),
        'Flag, Green': L.divIcon({
            className: 'flag-green',
            iconAnchor: [1, 25]
        }),
        'Flag, Red': L.divIcon({
            className: 'flag-red',
            iconAnchor: [1, 25]
        }),
        'Flag, Blue': L.divIcon({
            className: 'flag-blue',
            iconAnchor: [1, 25]
        }),
        'Block, Blue': L.divIcon({
            className: 'block-blue',
            iconAnchor: [8, 8]
        }),
        'Block, Green': L.divIcon({
            className: 'block-green',
            iconAnchor: [8, 8]
        }),
        'Block, Red': L.divIcon({
            className: 'block-red',
            iconAnchor: [8, 8]
        }),
        'Blue Diamond': L.divIcon({
            className: 'diamond-blue',
            iconAnchor: [9, 9]
        }),
        'Green Diamond': L.divIcon({
            className: 'diamond-green',
            iconAnchor: [9, 9]
        }),
        'Red Diamond': L.divIcon({
            className: 'diamond-red',
            iconAnchor: [9, 9]
        }),
        'Residence': L.divIcon({
            className: 'residence',
            iconAnchor: [12, 12]
        }),
        'Drinking Water': L.divIcon({
            className: 'drinking-water',
            iconAnchor: [12, 12]
        }),
        'Trail Head': L.divIcon({
            className: 'hike',
            iconAnchor: [12, 12]
        }),
        'Bike Trail': L.divIcon({
            className: 'bike-trail',
            iconAnchor: [12, 12]
        }),
        'Campground': L.divIcon({
            className: 'campground',
            iconAnchor: [12, 12]
        }),
        'Bar': L.divIcon({
            className: 'bar',
            iconAnchor: [10, 12]
        }),
        'Skull and Crossbones': L.divIcon({
            className: 'skullcross',
            iconAnchor: [12, 12]
        }),
        'Geocache': L.divIcon({
            className: 'geocache',
            iconAnchor: [11, 10]
        }),
        'Geocache Found': L.divIcon({
            className: 'geocache-open',
            iconAnchor: [11, 10]
        }),
        'Medical Facility': L.divIcon({
            className: 'medical',
            iconAnchor: [13, 11]
        }),
        'Contact, Alien': L.divIcon({
            className: 'contact-alien',
            iconAnchor: [12, 12]
        }),
        'Contact, Big Ears': L.divIcon({
            className: 'contact-bigears',
            iconAnchor: [12, 12]
        }),
        'Contact, Female3': L.divIcon({
            className: 'contact-female3',
            iconAnchor: [12, 12]
        }),
        'Contact, Cat': L.divIcon({
            className: 'contact-cat',
            iconAnchor: [12, 12]
        }),
        'Contact, Dog': L.divIcon({
            className: 'contact-dog',
            iconAnchor: [12, 12]
        }),
    };

    var METERSTOMILES = 0.0006213711;
    var METERSTOFOOT = 3.28084;
    var METERSTONAUTICALMILES = 0.000539957;

    //////////////// UTILS /////////////////////

    function basename(str) {
        var base = new String(str).substring(str.lastIndexOf('/') + 1);
        if (base.lastIndexOf(".") !== -1) {
            base = base.substring(0, base.lastIndexOf("."));
        }
        return base;
    }

    function hexToRgb(hex) {
        var result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
        return result ? {
            r: parseInt(result[1], 16),
            g: parseInt(result[2], 16),
            b: parseInt(result[3], 16)
        } : null;
    }

    function brify(str, linesize) {
        var res = '';
        var words = str.split(' ');
        var cpt = 0;
        var toAdd = '';
        for (var i=0; i<words.length; i++) {
            if ((cpt + words[i].length) < linesize) {
                toAdd += words[i] + ' ';
                cpt += words[i].length + 1;
            }
            else{
                res += toAdd + '<br/>';
                toAdd = words[i] + ' ';
                cpt = words[i].length + 1;
            }
        }
        res += toAdd;
        return res;
    }

    function metersToDistanceNoAdaptNoUnit(m) {
        var unit = $('#measureunitselect').val();
        var n = parseFloat(m);
        if (unit === 'metric') {
            return (n / 1000).toFixed(2);
        }
        else if (unit === 'english') {
            return (n * METERSTOMILES).toFixed(2);
        }
        else if (unit === 'nautical') {
            return (n * METERSTONAUTICALMILES).toFixed(2);
        }
    }

    function metersToDistance(m) {
        var unit = $('#measureunitselect').val();
        var n = parseFloat(m);
        if (unit === 'metric') {
            if (n > 1000) {
                return (n / 1000).toFixed(2) + ' km';
            }
            else{
                return n.toFixed(2) + ' m';
            }
        }
        else if (unit === 'english') {
            var mi = n * METERSTOMILES;
            if (mi < 1) {
                return (n * METERSTOFOOT).toFixed(2) + ' ft';
            }
            else {
                return mi.toFixed(2) + ' mi';
            }
        }
        else if (unit === 'nautical') {
            var nmi = n * METERSTONAUTICALMILES;
            return nmi.toFixed(2) + ' nmi';
        }
    }

    function metersToElevation(m) {
        var unit = $('#measureunitselect').val();
        var n = parseFloat(m);
        if (unit === 'metric' || unit === 'nautical') {
            return n.toFixed(2) + ' m';
        }
        else {
            return (n * METERSTOFOOT).toFixed(2) + ' ft';
        }
    }

    function metersToElevationNoUnit(m) {
        var unit = $('#measureunitselect').val();
        var n = parseFloat(m);
        if (unit === 'metric' || unit === 'nautical') {
            return n.toFixed(2);
        }
        else {
            return (n * METERSTOFOOT).toFixed(2);
        }
    }

    function kmphToSpeed(kmph) {
        var unit = $('#measureunitselect').val();
        var nkmph = parseFloat(kmph);
        if (unit === 'metric') {
            return nkmph.toFixed(2) + ' km/h';
        }
        else if (unit === 'english') {
            return (nkmph * 1000 * METERSTOMILES).toFixed(2) + ' mi/h';
        }
        else if (unit === 'nautical') {
            return (nkmph * 1000 * METERSTONAUTICALMILES).toFixed(2) + ' kt';
        }
    }

    function minPerKmToPace(minPerKm) {
        var unit = $('#measureunitselect').val();
        var nMinPerKm = parseFloat(minPerKm);
        if (unit === 'metric') {
            return nMinPerKm.toFixed(2) + ' min/km';
        }
        else if (unit === 'english') {
            return (nMinPerKm / 1000 / METERSTOMILES).toFixed(2) + ' min/mi';
        }
        else if (unit === 'nautical') {
            return (nMinPerKm / 1000 / METERSTONAUTICALMILES).toFixed(2) + ' min/nmi';
        }
    }

    //////////////// MAP /////////////////////

    function load_map() {
        // change meta to send referrer
        // usefull for IGN tiles authentication !
        $('meta[name=referrer]').attr('content', 'origin');

        var layer = getUrlParameter('layer');
        var default_layer = 'OpenStreetMap';
        if (gpxpod.restoredTileLayer !== null) {
            default_layer = gpxpod.restoredTileLayer;
        }
        else if (typeof layer !== 'undefined') {
            default_layer = layer;
        }

        var overlay = getUrlParameter('overlay');
        var overlays = [];
        if (overlay) {
            overlays = overlay.split(';;');
        }

        var osmfr2 = new L.TileLayer('https://{s}.tile.openstreetmap.fr/osmfr/{z}/{x}/{y}.png', {
            minZoom: 0,
            maxZoom: 13,
            attribution: 'Map data &copy; 2013 <a href="http://openstreetmap.org">OpenStreetMap</a> contributors'
        });

        var baseLayers = {};

        // add base layers
        $('#basetileservers li[type=tile]').each(function() {
            var sname = $(this).attr('name');
            var surl = $(this).attr('url');
            var minz = parseInt($(this).attr('minzoom'));
            var maxz = parseInt($(this).attr('maxzoom'));
            var sattrib = $(this).attr('attribution');
            var stransparent = ($(this).attr('transparent') === 'true');
            var sopacity = $(this).attr('opacity');
            if (typeof sopacity !== typeof undefined && sopacity !== false && sopacity !== '') {
                sopacity = parseFloat(sopacity);
            }
            else {
                sopacity = 1;
            }
            baseLayers[sname] = new L.TileLayer(surl, {minZoom: minz, maxZoom: maxz, attribution: sattrib, opacity: sopacity, transparent: stransparent});
        });
        $('#basetileservers li[type=tilewms]').each(function() {
            var sname = $(this).attr('name');
            var surl = $(this).attr('url');
            var slayers = $(this).attr('layers') || '';
            var sversion = $(this).attr('version') || '1.1.1';
            var stransparent = ($(this).attr('transparent') === 'true');
            var sformat = $(this).attr('format') || 'image/png';
            var sopacity = $(this).attr('opacity');
            if (typeof sopacity !== typeof undefined && sopacity !== false && sopacity !== '') {
                sopacity = parseFloat(sopacity);
            }
            else {
                sopacity = 1;
            }
            var sattrib = $(this).attr('attribution') || '';
            baseLayers[sname] = new L.tileLayer.wms(surl, {layers: slayers, version: sversion, transparent: stransparent, opacity: sopacity, format: sformat, attribution: sattrib});
        });
        // add custom layers
        $('#tileserverlist li').each(function() {
            var sname = $(this).attr('servername');
            var surl = $(this).attr('url');
            var sminzoom = $(this).attr('minzoom') || '1';
            var smaxzoom = $(this).attr('maxzoom') || '20';
            var sattrib = $(this).attr('attribution') || '';
            baseLayers[sname] = new L.TileLayer(surl,
                    {minZoom: sminzoom, maxZoom: smaxzoom, attribution: sattrib});
        });
        $('#tilewmsserverlist li').each(function() {
            var sname = $(this).attr('servername');
            var surl = $(this).attr('url');
            var sminzoom = $(this).attr('minzoom') || '1';
            var smaxzoom = $(this).attr('maxzoom') || '20';
            var slayers = $(this).attr('layers') || '';
            var sversion = $(this).attr('version') || '1.1.1';
            var sformat = $(this).attr('format') || 'image/png';
            var sattrib = $(this).attr('attribution') || '';
            baseLayers[sname] = new L.tileLayer.wms(surl,
                    {format: sformat, version: sversion, layers: slayers, minZoom: sminzoom, maxZoom: smaxzoom, attribution: sattrib});
        });
        gpxpod.baseLayers = baseLayers;

        var baseOverlays = {};

        // add base overlays
        $('#basetileservers li[type=overlay]').each(function() {
            var sname = $(this).attr('name');
            var surl = $(this).attr('url');
            var minz = parseInt($(this).attr('minzoom'));
            var maxz = parseInt($(this).attr('maxzoom'));
            var sattrib = $(this).attr('attribution');
            var stransparent = ($(this).attr('transparent') === 'true');
            var sopacity = $(this).attr('opacity');
            if (typeof sopacity !== typeof undefined && sopacity !== false && sopacity !== '') {
                sopacity = parseFloat(sopacity);
            }
            else {
                sopacity = 0.4;
            }
            baseOverlays[sname] = new L.TileLayer(surl, {minZoom: minz, maxZoom: maxz, attribution: sattrib, opacity: sopacity, transparent: stransparent});
        });
        $('#basetileservers li[type=overlaywms]').each(function() {
            var sname = $(this).attr('name');
            var surl = $(this).attr('url');
            var slayers = $(this).attr('layers') || '';
            var sversion = $(this).attr('version') || '1.1.1';
            var stransparent = ($(this).attr('transparent') === 'true');
            var sopacity = $(this).attr('opacity');
            if (typeof sopacity !== typeof undefined && sopacity !== false && sopacity !== '') {
                sopacity = parseFloat(sopacity);
            }
            else {
                sopacity = 0.4;
            }
            var sformat = $(this).attr('format') || 'image/png';
            var sattrib = $(this).attr('attribution') || '';
            baseOverlays[sname] = new L.tileLayer.wms(surl, {layers: slayers, version: sversion, transparent: stransparent, opacity: sopacity, format: sformat, attribution: sattrib});
        });
        // add custom overlays
        $('#overlayserverlist li').each(function() {
            var sname = $(this).attr('servername');
            var surl = $(this).attr('url');
            var sminzoom = $(this).attr('minzoom') || '1';
            var smaxzoom = $(this).attr('maxzoom') || '20';
            var stransparent = ($(this).attr('transparent') === 'true');
            var sopacity = $(this).attr('opacity');
            if (typeof sopacity !== typeof undefined && sopacity !== false && sopacity !== '') {
                sopacity = parseFloat(sopacity);
            }
            else {
                sopacity = 0.4;
            }
            var sattrib = $(this).attr('attribution') || '';
            baseOverlays[sname] = new L.TileLayer(surl,
                    {minZoom: sminzoom, maxZoom: smaxzoom, transparent: stransparent, opcacity: sopacity, attribution: sattrib});
        });
        $('#overlaywmsserverlist li').each(function() {
            var sname = $(this).attr('servername');
            var surl = $(this).attr('url');
            var sminzoom = $(this).attr('minzoom') || '1';
            var smaxzoom = $(this).attr('maxzoom') || '20';
            var slayers = $(this).attr('layers') || '';
            var sversion = $(this).attr('version') || '1.1.1';
            var sformat = $(this).attr('format') || 'image/png';
            var stransparent = ($(this).attr('transparent') === 'true');
            var sopacity = $(this).attr('opacity');
            if (typeof sopacity !== typeof undefined && sopacity !== false && sopacity !== '') {
                sopacity = parseFloat(sopacity);
            }
            else {
                sopacity = 0.4;
            }
            var sattrib = $(this).attr('attribution') || '';
            baseOverlays[sname] = new L.tileLayer.wms(surl, {layers: slayers, version: sversion, transparent: stransparent, opacity: sopacity, format: sformat, attribution: sattrib, minZoom: sminzoom, maxZoom: smaxzoom});
        });
        gpxpod.overlayLayers = baseOverlays;

        gpxpod.map = new L.Map('map', {
            zoomControl: true,
        });

        var notificationText = '<div id="loadingnotification">' +
            '<span id="stackgroup" class="fa-stack fa-2x">' +
            '<i id="spinload" class="fa fa-spinner fa-pulse fa-stack-1x"></i>' +
            '<i id="folderload" class="far fa-folder-open fa-stack-1x"></i>' +
            '<i id="deleteload" class="far fa-trash-alt fa-stack-1x"></i>' +
            '<i id="trackload" class="fas fa-chart-line fa-stack-1x"></i>' +
            '<i id="correctload" class="far fa-chart-area fa-stack-1x"></i>' +
            '</span>' +
            '<b id="loadingpc"></b></div>';
        gpxpod.notificationDialog = L.control.dialog({
            anchor: [0, -65],
            position: 'topright',
            size: [55, 55]
        })
        .setContent(notificationText)

        // picture spiderfication
        gpxpod.oms = new OverlappingMarkerSpiderfier(gpxpod.map, {keepSpiderfied: true});
        gpxpod.oms.addListener('click', function(m) {
            gpxpod.picturePopups[m.number].openOn(gpxpod.map);
            $('.group1').colorbox({rel: 'group1', height: '90%', photo: true});
            $('.group1').click();
            gpxpod.map.closePopup(gpxpod.picturePopups[m.number]);
        });
        gpxpod.oms.addListener('spiderfy', function(markers, m2) {
            var i, p;
            for (i = 0; i < markers.length; i++) {
                markers[i].setIcon(new darkIcon());
            }
            for (i = 0; i < gpxpod.pictureBigMarkers.length; i++) {
                // close all tooltips to avoid having one still opened
                gpxpod.pictureBigMarkers[i].closeTooltip();
                gpxpod.pictureBigMarkers[i].closePopup();
            }
            if ($('#picturestyleselect').val() === 'bmp'){
                for (i = 0; i < markers.length; i++) {
                    p = L.popup({
                        closeOnClick: true,
                        autoClose: false
                    }).setLatLng(markers[i].getLatLng()).setContent(gpxpod.picturePopups[markers[i].number].getContent());
                    p.openOn(gpxpod.map);
                    $('.group1').colorbox({rel: 'group1', height: '90%', photo: true});
                }
            }
        });
        gpxpod.oms.addListener('unspiderfy', function(markers, m2) {
            var i;
            for (i = 0; i < markers.length; i++) {
                markers[i].setIcon(
                    new L.divIcon({
                        className: 'leaflet-marker-red',
                        iconAnchor: [12, 41]
                    })
                );
            }
            for (i = 0; i < gpxpod.pictureBigMarkers.length; i++) {
                gpxpod.pictureBigMarkers[i].closeTooltip();
            }
        });

        L.control.scale({metric: true, imperial: true, position: 'topleft'})
        .addTo(gpxpod.map);

        L.control.mousePosition().addTo(gpxpod.map);
        gpxpod.searchControl = L.Control.geocoder({position: 'topleft'});
        gpxpod.searchControl.addTo(gpxpod.map);
        gpxpod.locateControl = L.control.locate({follow: true});
        gpxpod.locateControl.addTo(gpxpod.map);
        gpxpod.map.addControl(new L.Control.LinearMeasurement({
            unitSystem: 'metric',
            color: '#FF0080',
            type: 'line'
        }));
        L.control.sidebar('sidebar').addTo(gpxpod.map);
        if (pageIsPublicFileOrFolder()) {
            var showSidebar = getUrlParameter('sidebar');
            if (showSidebar === '0') {
                $('#sidebar').toggleClass('collapsed');
            }
        }

        gpxpod.map.setView(new L.LatLng(27, 5), 3);

        if (! baseLayers.hasOwnProperty(default_layer)) {
            default_layer = 'OpenStreetMap';
        }
        gpxpod.map.addLayer(baseLayers[default_layer]);

        gpxpod.activeLayers = L.control.activeLayers(baseLayers, baseOverlays);
        gpxpod.activeLayers.addTo(gpxpod.map);

        for (var ii in overlays) {
            gpxpod.map.addLayer(baseOverlays[overlays[ii]]);
        }

        gpxpod.minimapControl = new L.Control.MiniMap(
                osmfr2,
                { toggleDisplay: true, position: 'bottomleft' }
        ).addTo(gpxpod.map);
        gpxpod.minimapControl._toggleDisplayButtonClicked();

        //gpxpod.map.on('contextmenu',rightClick);
        //gpxpod.map.on('popupclose',function() {});
        //gpxpod.map.on('viewreset',updateTrackListFromBounds);
        //gpxpod.map.on('dragend',updateTrackListFromBounds);
        gpxpod.map.on('moveend', updateTrackListFromBounds);
        gpxpod.map.on('zoomend', updateTrackListFromBounds);
        gpxpod.map.on('baselayerchange', updateTrackListFromBounds);
        if (! pageIsPublicFileOrFolder()) {
            gpxpod.map.on('baselayerchange', saveOptionTileLayer);
        }
    }

    //function rightClick(e) {
    //    //new L.popup()
    //    //    .setLatLng(e.latlng)
    //    //    .setContent(preparepopup(e.latlng.lat,e.latlng.lng))
    //    //    .openOn(gpxpod.map);
    //}

    function removeElevation() {
        // clean other elevation
        if (gpxpod.elevationLayer !== null) {
            gpxpod.map.removeControl(gpxpod.elevationLayer);
            delete gpxpod.elevationLayer;
            gpxpod.elevationLayer = null;
            delete gpxpod.elevationTrack;
            gpxpod.elevationTrack = null;
        }
    }

    function zoomOnAllDrawnTracks() {
        var b;
        // get bounds of first layer
        for (var l in gpxpod.gpxlayers) {
            b = L.latLngBounds(
                gpxpod.gpxlayers[l].layer.getBounds().getSouthWest(),
                gpxpod.gpxlayers[l].layer.getBounds().getNorthEast()
            );
            break;
        }
        // then extend to other bounds
        for (var k in gpxpod.gpxlayers) {
            b.extend(gpxpod.gpxlayers[k].layer.getBounds());
        }
        // zoom
        if (b.isValid()) {
            var xoffset = parseInt($('#sidebar').css('width'));
            if (pageIsPublicFileOrFolder()) {
                var showSidebar = getUrlParameter('sidebar');
                if (showSidebar === '0') {
                    xoffset = 0;
                }
            }
            gpxpod.map.fitBounds(b, {
                animate: true,
                paddingTopLeft: [xoffset, 0]
            });
        }
    }

    function zoomOnAllMarkers() {
        if (gpxpod.markers.length > 0 || gpxpod.pictureBigMarkers.length > 0) {
            var i, ll, m, north, south, east, west;
            if (gpxpod.markers.length > 0) {
                north = gpxpod.markers[0][LAT];
                south = gpxpod.markers[0][LAT];
                east = gpxpod.markers[0][LON];
                west = gpxpod.markers[0][LON];
            }
            for (i = 1; i < gpxpod.markers.length; i++) {
                m = gpxpod.markers[i];
                if (m[LAT] > north) {
                    north = m[LAT];
                }
                if (m[LAT] < south) {
                    south = m[LAT];
                }
                if (m[LON] < west) {
                    west = m[LON];
                }
                if (m[LON] > east) {
                    east = m[LON];
                }
            }
            if (gpxpod.pictureBigMarkers.length > 0) {
                // init n,s,e,w if it hasn't been done
                if (gpxpod.markers.length === 0) {
                    m = gpxpod.pictureBigMarkers[0];
                    ll = m.getLatLng();
                    north = ll.lat;
                    south = ll.lat;
                    west = ll.lng;
                    east = ll.lng;
                }
                for (i = 0; i < gpxpod.pictureBigMarkers.length; i++) {
                    m = gpxpod.pictureBigMarkers[i];
                    ll = m.getLatLng();
                    if (ll.lat > north) {
                        north = ll.lat;
                    }
                    if (ll.lat < south) {
                        south = ll.lat;
                    }
                    if (ll.lng < west) {
                        west = ll.lng;
                    }
                    if (ll.lng > east) {
                        east = ll.lng;
                    }
                }
            }
            var b = L.latLngBounds([south, west],[north, east]);
            if (b.isValid()) {
                var xoffset = parseInt($('#sidebar').css('width'));
                if (pageIsPublicFileOrFolder()) {
                    var showSidebar = getUrlParameter('sidebar');
                    if (showSidebar === '0') {
                        xoffset = 0;
                    }
                }
                gpxpod.map.fitBounds([[south, west],[north, east]],
                        {animate: true, paddingTopLeft: [xoffset, 0]}
                );
            }
        }
    }

    /*
     * returns true if at least one point of the track is
     * inside the map bounds
     */
    function trackCrossesMapBounds(shortPointList, mapb) {
        if (typeof shortPointList !== 'undefined') {
            for (var i = 0; i < shortPointList.length; i++) {
                var p = shortPointList[i];
                if (mapb.contains(new L.LatLng(p[0], p[1]))) {
                    return true;
                }
            }
        }
        return false;
    }

    //////////////// MARKERS /////////////////////

    /*
     * display markers if the checkbox is checked
     */
    function redrawMarkers()
    {
        // remove markers if they are present
        removeMarkers();
        addMarkers();
        return;

    }

    function removeMarkers() {
        if (gpxpod.markerLayer !== null) {
            gpxpod.map.removeLayer(gpxpod.markerLayer);
            delete gpxpod.markerLayer;
            gpxpod.markerLayer = null;
        }
    }

    // add markers respecting the filtering rules
    function addMarkers() {
        var markerclu = L.markerClusterGroup({ chunkedLoading: true });
        var a, title, marker;
        for (var i = 0; i < gpxpod.markers.length; i++) {
            a = gpxpod.markers[i];
            if (filter(a)) {
                title = a[NAME];
                marker = L.marker(L.latLng(a[LAT], a[LON]));
                marker.tid = title;
                marker.bindPopup(
                    gpxpod.markersPopupTxt[title].popup,
                    {
                        autoPan: true,
                        autoClose: true,
                        closeOnClick: true
                    }
                );
                marker.bindTooltip(title);
                marker.on('mouseover', function(e) {
                    if (gpxpod.currentCorrectingAjax === null) {
                        gpxpod.insideTr = true;
                        displayOnHover(e.target.tid);
                    }
                });
                marker.on('mouseout', function() {
                    if (gpxpod.currentHoverAjax !== null) {
                        gpxpod.currentHoverAjax.abort();
                        hideAnimation();
                    }
                    gpxpod.insideTr = false;
                    deleteOnHover();
                });
                gpxpod.markersPopupTxt[title].marker = marker;
                markerclu.addLayer(marker);
            }
        }

        if ($('#displayclusters').is(':checked')) {
            gpxpod.map.addLayer(markerclu);
        }
        //gpxpod.map.setView(new L.LatLng(47, 3), 2);

        gpxpod.markerLayer = markerclu;

        //markers.on('clusterclick', function (a) {
        //   var bounds = a.layer.getConvexHull();
        //   updateTrackListFromBounds(bounds);
        //});
    }

    function genPopupTxt() {
        var dl_url;
        gpxpod.markersPopupTxt = {};
        var chosentz = $('#tzselect').val();
        var url = OC.generateUrl('/apps/files/ajax/download.php');
        var subfo = gpxpod.subfolder;
        if (subfo === '/') {
            subfo = '';
        }
        // if this is a public link, the url is the public share
        if (pageIsPublicFileOrFolder()) {
            url = OC.generateUrl('/s/' + gpxpod.token);
        }
        for (var i = 0; i < gpxpod.markers.length; i++) {
            var a = gpxpod.markers[i];
            var title = a[NAME];

            if (pageIsPublicFolder()) {
                var subpath = getUrlParameter('path');
                if (subpath === undefined) {
                    subpath = '/';
                }
                dl_url = '"' + url.split('?')[0] + '/download?path=' + encodeURIComponent(subpath) +
                    '&files=' + encodeURIComponent(title) + '" target="_blank"';
            }
            else if (pageIsPublicFile()) {
                dl_url = '"' + url + '" target="_blank"';
            }
            else{
                dl_url = '"' + url + '?dir=' + encodeURIComponent(gpxpod.subfolder) + '&files=' + encodeURIComponent(title) + '"';
            }

            var popupTxt = '<h3 class="popupTitle">' +
                t('gpxpod','File') + ' : <a href=' +
                dl_url + ' title="' + t('gpxpod','download') + '" class="getGpx" >' +
                '<i class="fa fa-cloud-download-alt" aria-hidden="true"></i> ' + title + '</a> ';
            if (! pageIsPublicFileOrFolder()) {
                popupTxt = popupTxt + '<a class="publink" type="track" name="' + title + '" ' +
                           'href="" target="_blank" title="' +
                           escapeHTML(t('gpxpod', 'This public link will work only if \'{title}\' or one of its parent folder is shared in \'files\' app by public link without password', {title: title})) +
                           '">' +
                           '<i class="fa fa-share-alt" aria-hidden="true"></i>' +
                           '</a>';
            }
            popupTxt = popupTxt + '</h3>';
            popupTxt = popupTxt + '<button class="drawButton" tid="' + title + '">' +
                '<i class="fa fa-pencil-alt" aria-hidden="true"></i> ' + t('gpxpod', 'Draw track') + '</button>';
            // link url and text
            if (a.length >= LINKTEXT && a[LINKURL]) {
                var lt = a[LINKTEXT];
                if (!lt) {
                    lt = t('gpxpod', 'metadata link');
                }
                popupTxt = popupTxt + '<a class="metadatalink" title="' +
                    t('gpxpod', 'metadata link') + '" href="' + a[LINKURL] +
                    '" target="_blank">' + lt + '</a>';
            }
            if (a.length >= TRACKNAMELIST + 1) {
                popupTxt = popupTxt + '<ul title="' + t('gpxpod', 'tracks/routes name list') +
                    '" class="trackNamesList">';
                for (var z=0; z < a[TRACKNAMELIST].length; z++) {
                    var trname = a[TRACKNAMELIST][z];
                    if (trname === '') {
                        trname = 'unnamed';
                    }
                    popupTxt = popupTxt + '<li>' + escapeHTML(trname) + '</li>';
                }
                popupTxt = popupTxt + '</ul>';
            }

            popupTxt = popupTxt +'<table class="popuptable">';
            popupTxt = popupTxt +'<tr>';
            popupTxt = popupTxt +'<td><i class="fa fa-arrows-alt-h" aria-hidden="true"></i> <b>' +
                t('gpxpod','Distance') + '</b></td>';
            if (a[TOTAL_DISTANCE] !== null) {
                popupTxt = popupTxt + '<td>' + metersToDistance(a[TOTAL_DISTANCE]) + '</td>';
            }
            else{
                popupTxt = popupTxt + '<td> NA</td>';
            }
            popupTxt = popupTxt + '</tr><tr>';

            popupTxt = popupTxt + '<td><i class="fa fa-clock" aria-hidden="true"></i> ' +
                t('gpxpod','Duration') + ' </td><td> ' + a[TOTAL_DURATION] + '</td>';
            popupTxt = popupTxt + '</tr><tr>';
            popupTxt = popupTxt + '<td><i class="fa fa-clock" aria-hidden="true"></i> <b>' +
                t('gpxpod','Moving time') + '</b> </td><td> ' + a[MOVING_TIME] + '</td>';
            popupTxt = popupTxt + '</tr><tr>';
            popupTxt = popupTxt + '<td><i class="fa fa-clock" aria-hidden="true"></i> ' +
                t('gpxpod','Pause time') + ' </td><td> ' + a[STOPPED_TIME] + '</td>';
            popupTxt = popupTxt + '</tr><tr>';

            var dbs = "no date";
            var dbes = "no date";
            try{
                if (a[DATE_BEGIN] !== '' && a[DATE_BEGIN] !== 'None') {
                    var db = moment(a[DATE_BEGIN].replace(' ', 'T'));
                    db.tz(chosentz);
                    dbs = db.format('YYYY-MM-DD HH:mm:ss (Z)');
                }
                if (a[DATE_END] !== '' && a[DATE_END] !== 'None') {
                    var dbe = moment(a[DATE_END].replace(' ', 'T'));
                    dbe.tz(chosentz);
                    dbes = dbe.format('YYYY-MM-DD HH:mm:ss (Z)');
                }
            }
            catch(err) {
            }
            popupTxt = popupTxt +'<td><i class="fa fa-calendar-alt" aria-hidden="true"></i> ' +
                t('gpxpod', 'Begin') + ' </td><td> ' + dbs + '</td>';
            popupTxt = popupTxt +'</tr><tr>';
            popupTxt = popupTxt +'<td><i class="fa fa-calendar-alt" aria-hidden="true"></i> ' +
                t('gpxpod','End') + ' </td><td> ' + dbes + '</td>';
            popupTxt = popupTxt +'</tr><tr>';
            popupTxt = popupTxt +'<td><i class="fa fa-chart-line" aria-hidden="true"></i> <b>' +
                t('gpxpod', 'Cumulative elevation gain') + '</b> </td><td> ' +
                metersToElevation(a[POSITIVE_ELEVATION_GAIN]) + '</td>';
            popupTxt = popupTxt +'</tr><tr>';
            popupTxt = popupTxt +'<td><i class="fa fa-chart-line" aria-hidden="true"></i> ' +
                t('gpxpod','Cumulative elevation loss') + ' </td><td> ' +
                metersToElevation(a[NEGATIVE_ELEVATION_GAIN]) + '</td>';
            popupTxt = popupTxt +'</tr><tr>';
            popupTxt = popupTxt +'<td><i class="fa fa-chart-area" aria-hidden="true"></i> ' +
                t('gpxpod','Minimum elevation') + ' </td><td> ' +
                metersToElevation(a[MIN_ELEVATION]) + '</td>';
            popupTxt = popupTxt +'</tr><tr>';
            popupTxt = popupTxt +'<td><i class="fa fa-chart-area" aria-hidden="true"></i> ' +
                t('gpxpod','Maximum elevation') + ' </td><td> ' +
                metersToElevation(a[MAX_ELEVATION]) + '</td>';
            popupTxt = popupTxt +'</tr><tr>';
            popupTxt = popupTxt +'<td><i class="fa fa-tachometer-alt" aria-hidden="true"></i> <b>' +
                t('gpxpod','Maximum speed') + '</b> </td><td> ';
            if (a[MAX_SPEED] !== null) {
                popupTxt = popupTxt + kmphToSpeed(a[MAX_SPEED]);
            }
            else{
                popupTxt = popupTxt +'NA';
            }
            popupTxt = popupTxt +'</td>';
            popupTxt = popupTxt +'</tr><tr>';

            popupTxt = popupTxt +'<td><i class="fa fa-tachometer-alt" aria-hidden="true"></i> ' +
                t('gpxpod','Average speed') + ' </td><td> ';
            if (a[AVERAGE_SPEED] !== null) {
                popupTxt = popupTxt + kmphToSpeed(a[AVERAGE_SPEED]);
            }
            else{
                popupTxt = popupTxt +'NA';
            }
            popupTxt = popupTxt +'</td>';
            popupTxt = popupTxt +'</tr><tr>';

            popupTxt = popupTxt +'<td><i class="fa fa-tachometer-alt" aria-hidden="true"></i> <b>' +
                t('gpxpod','Moving average speed') + '</b> </td><td> ';
            if (a[MOVING_AVERAGE_SPEED] !== null) {
                popupTxt = popupTxt + kmphToSpeed(a[MOVING_AVERAGE_SPEED]);
            }
            else{
                popupTxt = popupTxt +'NA';
            }
            popupTxt = popupTxt +'</td></tr>';

            popupTxt = popupTxt +'<tr><td><i class="fa fa-tachometer-alt" aria-hidden="true"></i> <b>' +
                t('gpxpod','Moving average pace') + '</b> </td><td> ';
            if (a[MOVING_PACE] !== null) {
                popupTxt = popupTxt + minPerKmToPace(a[MOVING_PACE]);
            }
            else{
                popupTxt = popupTxt +'NA';
            }
            popupTxt = popupTxt +'</td></tr>';
            popupTxt = popupTxt + '</table>';

            gpxpod.markersPopupTxt[title] = {};
            gpxpod.markersPopupTxt[title].popup = popupTxt;
        }
    }

    function getAjaxMarkersSuccess(markerstxt) {
        // load markers
        loadMarkers(markerstxt);
        // remove all draws
        for(var tid in gpxpod.gpxlayers) {
            removeTrackDraw(tid);
        }
        if ($('#autozoomcheck').is(':checked')) {
            zoomOnAllMarkers();
        }
        else{
            gpxpod.map.setView(new L.LatLng(27, 5), 3);
        }
    }

    // read in #markers
    function loadMarkers(m) {
        var markerstxt;
        if (m === '') {
            markerstxt = $('#markers').text();
        }
        else{
            markerstxt = m;
        }
        if (markerstxt !== null && markerstxt !== '' && markerstxt !== false) {
            gpxpod.markers = $.parseJSON(markerstxt).markers;
            gpxpod.subfolder = $('#subfolderselect').val();
            gpxpod.gpxcompRootUrl = $('#gpxcomprooturl').text();
            genPopupTxt();
        }
        else{
            delete gpxpod.markers;
            gpxpod.markers = [];
        }
        redrawMarkers();
        updateTrackListFromBounds();
    }

    function stopGetMarkers() {
        if (gpxpod.currentMarkerAjax !== null) {
            // abort ajax
            gpxpod.currentMarkerAjax.abort();
            gpxpod.currentMarkerAjax = null;
        }
    }

    // if GET params dir and file are set, we select the track
    function selectTrackFromUrlParam() {
        if (getUrlParameter('dir') && getUrlParameter('file')) {
            var dirGet = getUrlParameter('dir');
            var fileGet = getUrlParameter('file');
            if ($('select#subfolderselect').val() === dirGet) {
                if ($('input.drawtrack[id="' + fileGet + '"]').length === 1) {
                    $('input.drawtrack[id="' + fileGet + '"]').prop('checked', true);
                    $('input.drawtrack[id="' + fileGet + '"]').change();
                    OC.Notification.showTemporary(t('gpxpod', 'Track "{tn}" is loading', {tn: fileGet}));
                }
            }
        }
    }

    //////////////// FILTER /////////////////////

    // return true if the marker respects all filters
    function filter(m) {
        var unit = $('#measureunitselect').val();

        var mdate = new Date(m[DATE_END].split(' ')[0]);
        var mdist = m[TOTAL_DISTANCE];
        var mceg = m[POSITIVE_ELEVATION_GAIN];
        if (unit === 'english') {
            mdist = mdist * METERSTOMILES;
            mceg = mceg * METERSTOFOOT;
        }
        else if (unit === 'nautical') {
            mdist = mdist * METERSTONAUTICALMILES;
        }
        var datemin = $('#datemin').val();
        var datemax = $('#datemax').val();
        var distmin = $('#distmin').val();
        var distmax = $('#distmax').val();
        var cegmin = $('#cegmin').val();
        var cegmax = $('#cegmax').val();

        if (datemin !== '') {
            var ddatemin = new Date(datemin);
            if (mdate < ddatemin) {
                return false;
            }
        }
        if (datemax !== '') {
            var ddatemax = new Date(datemax);
            if (ddatemax < mdate) {
                return false;
            }
        }
        if (distmin !== '') {
            if (mdist < distmin) {
                return false;
            }
        }
        if (distmax !== '') {
            if (distmax < mdist) {
                return false;
            }
        }
        if (cegmin !== '') {
            if (mceg < cegmin) {
                return false;
            }
        }
        if (cegmax !== '') {
            if (cegmax < mceg) {
                return false;
            }
        }

        return true;
    }

    function clearFiltersValues() {
        $('#datemin').val('');
        $('#datemax').val('');
        $('#distmin').val('');
        $('#distmax').val('');
        $('#cegmin').val('');
        $('#cegmax').val('');
    }

    //////////////// SIDEBAR TABLE /////////////////////

    function deleteOneTrack(name) {
        var trackNameList = [];
        trackNameList.push(name);

        var req = {
            tracknames: trackNameList,
            folder: gpxpod.subfolder
        };
        var url = OC.generateUrl('/apps/gpxpod/deleteTracks');
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            if (! response.done) {
                OC.dialogs.alert(
                    t('gpxpod', 'Failed to delete track') + name + '. ' +
                    t('gpxpod', 'Reload this page')
                    ,
                    t('gpxpod', 'Error')
                );
            }
            else {
                $('#subfolderselect').change();
            }
            if (response.message) {
                OC.Notification.showTemporary(response.message);
            }
            else {
                var msg, msg2;
                if (response.deleted) {
                    msg = t('gpxpod', 'Successfully deleted') + ' : ' + response.deleted + '. ';
                    OC.Notification.showTemporary(msg);
                    msg2 = t('gpxpod', 'You can restore deleted files in "Files" app');
                    OC.Notification.showTemporary(msg2);
                }
                if (response.notdeleted) {
                    msg = t('gpxpod', 'Impossible to delete') + ' : ' + response.notdeleted + '.';
                    OC.Notification.showTemporary(msg);
                }
            }
        }).fail(function() {
            OC.dialogs.alert(
                t('gpxpod', 'Failed to delete selected tracks') + '. ' +
                t('gpxpod', 'Reload this page')
                ,
                t('gpxpod', 'Error')
            );
        }).always(function() {
        });
    }

    function deleteSelectedTracks() {
        var trackNameList = [];
        $('input.drawtrack:checked').each(function () {
            trackNameList.push($(this).attr('id'));
        });

        showDeletingAnimation();
        var req = {
            tracknames: trackNameList,
            folder: gpxpod.subfolder
        };
        var url = OC.generateUrl('/apps/gpxpod/deleteTracks');
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            if (! response.done) {
                OC.dialogs.alert(
                    t('gpxpod', 'Failed to delete selected tracks') + '. ' +
                    t('gpxpod', 'Reload this page')
                    ,
                    t('gpxpod', 'Error')
                );
            }
            else {
                $('#subfolderselect').change();
            }
            if (response.message) {
                OC.Notification.showTemporary(response.message);
            }
            else {
                var msg, msg2;
                if (response.deleted) {
                    msg = t('gpxpod', 'Successfully deleted') + ' : ' + response.deleted + '. ';
                    OC.Notification.showTemporary(msg);
                    msg2 = t('gpxpod', 'You can restore deleted files in "Files" app');
                    OC.Notification.showTemporary(msg2);
                }
                if (response.notdeleted) {
                    msg = t('gpxpod', 'Impossible to delete') + ' : ' + response.notdeleted + '.';
                    OC.Notification.showTemporary(msg);
                }
            }
        }).fail(function() {
            OC.dialogs.alert(
                t('gpxpod', 'Failed to delete selected tracks') + '. ' +
                t('gpxpod', 'Reload this page')
                ,
                t('gpxpod', 'Error')
            );
        }).always(function() {
            hideAnimation();
        });
    }

    function updateTrackListFromBounds(e) {
        var m;
        var pc, dl_url;
        var table, datestr, sortkey;
        var table_rows = '';
        var hassrtm = ($('#hassrtm').text() === 'yes');
        var mapBounds = gpxpod.map.getBounds();
        var chosentz = $('#tzselect').val();
        var activeLayerName = gpxpod.activeLayers.getActiveBaseLayer().name;
        var url = OC.generateUrl('/apps/files/ajax/download.php');
        // state of "update table" option checkbox
        var updOption = $('#updtracklistcheck').is(':checked');
        var tablecriteria = $('#tablecriteriasel').val();
        var subfo = gpxpod.subfolder;
        if (subfo === '/') {
            subfo = '';
        }
        var elevationunit, distanceunit;
        var unit = $('#measureunitselect').val();

        // if this is a public link, the url is the public share
        if (pageIsPublicFolder()) {
            url = OC.generateUrl('/s/' + gpxpod.token);
            var subpath = getUrlParameter('path');
            if (subpath === undefined) {
                subpath = '/';
            }
            url = url.split('?')[0] + '/download?path=' + encodeURIComponent(subpath) + '&files=';
        }
        else if (pageIsPublicFile()) {
            url = OC.generateUrl('/s/' + gpxpod.token);
        }

        for (var i = 0; i < gpxpod.markers.length; i++) {
            m = gpxpod.markers[i];
            if (filter(m)) {
                //if ((!updOption) || mapBounds.contains(new L.LatLng(m[LAT], m[LON]))) {
                if ((!updOption) ||
                        (tablecriteria == 'bounds' && mapBounds.intersects(
                            new L.LatLngBounds(
                                new L.LatLng(m[SOUTH], m[WEST]),
                                new L.LatLng(m[NORTH], m[EAST])
                                )
                            )
                        ) ||
                        (tablecriteria == 'start' &&
                         mapBounds.contains(new L.LatLng(m[LAT], m[LON]))) ||
                        (tablecriteria == 'cross' &&
                         trackCrossesMapBounds(m[SHORTPOINTLIST], mapBounds))
                   ) {
                    if (gpxpod.gpxlayers.hasOwnProperty(m[NAME])) {
                        table_rows = table_rows + '<tr><td class="colortd" title="' +
                        t('gpxpod','Click the color to change it') + '" style="background:' +
                        gpxpod.gpxlayers[m[NAME]].color + '"><input title="' +
                        t('gpxpod','Deselect to hide track drawing') + '" type="checkbox"';
                        table_rows = table_rows + ' checked="checked" ';
                    }
                    else{
                        table_rows = table_rows + '<tr><td><input title="' +
                            t('gpxpod','Select to draw the track') + '" type="checkbox"';
                    }
                    if (gpxpod.currentAjax.hasOwnProperty(m[NAME])) {
                        table_rows = table_rows + ' style="display:none;"';
                    }
                    table_rows = table_rows + ' class="drawtrack" id="' +
                                 m[NAME] + '">' +
                                 '<p ';
                    if (! gpxpod.currentAjax.hasOwnProperty(m[NAME])) {
                        table_rows = table_rows + ' style="display:none;"';
                        pc = '';
                    }
                    else{
                        pc = gpxpod.currentAjaxPercentage[m[NAME]];
                    }
                    table_rows = table_rows + '><i class="fa fa-spinner fa-pulse fa-3x fa-fw"></i>' +
                        '<tt class="progress" track="' + m[NAME] + '">' +
                        pc + '</tt>%</p>' +
                        '</td>\n';
                    table_rows = table_rows +
                                 '<td class="trackname"><div class="trackcol">';

                    dl_url = '';
                    if (pageIsPublicFolder()) {
                        dl_url = '"' + url + encodeURIComponent(m[NAME]) + '" target="_blank"';
                    }
                    else if (pageIsPublicFile()) {
                        dl_url = '"' + url + '" target="_blank"';
                    }
                    else{
                        dl_url = '"' + url + '?dir=' + encodeURIComponent(gpxpod.subfolder) +
                                 '&files=' + encodeURIComponent(m[NAME]) + '"';
                    }
                    table_rows = table_rows + '<a href=' + dl_url +
                                 ' title="' + t('gpxpod', 'download') + '" class="tracklink">' +
                                 '<i class="fa fa-cloud-download-alt" aria-hidden="true"></i>' +
                                 m[NAME] + '</a>\n';

                    table_rows = table_rows + '<div>';

                    if (! pageIsPublicFileOrFolder()) {
                        table_rows = table_rows +'<button class="dropdownbutton" title="' +
                            t('gpxpod', 'More') + '">' +
                            '<i class="fa fa-bars" aria-hidden="true"></i></button>';
                    }
                    table_rows = table_rows +'<button class="zoomtrackbutton" name="' + m[NAME] + '"' +
                        ' title="' + t('gpxpod', 'Center map on this track') + '">' +
                        '<i class="fa fa-search" aria-hidden="true"></i></button>';
                    if (! pageIsPublicFileOrFolder()) {
                        table_rows = table_rows +' <button class="publink" ' +
                                     'type="track" name="' + m[NAME] + '"' +
                                     'title="' +
                                     t('gpxpod', 'This public link will work only if \'{title}\' or one of its parent folder is shared in \'files\' app by public link without password',
                                                 {title: m[NAME]}
                                     ) +
                                     '" target="_blank" href="">' +
                                     '<i class="fa fa-share-alt" aria-hidden="true"></i></button>';

                        table_rows = table_rows + '<div class="dropdown-content">';
                        table_rows = table_rows + '<a href="#" track="' +
                                     m[NAME] + '" class="deletetrack">' +
                                     '<i class="fa fa-trash" aria-hidden="true"></i> ' +
                                     t('gpxpod', 'Delete this track file') +
                                     '</a>';
                        if (hassrtm) {
                            table_rows = table_rows + '<a href="#" track="' +
                                         m[NAME] + '" class="csrtms">' +
                                         '<i class="fa fa-chart-line" aria-hidden="true"></i> ' +
                                         t('gpxpod','Correct elevations with smoothing for this track') +
                                         '</a>';
                            table_rows = table_rows + '<a href="#" track="' +
                                         m[NAME] + '" class="csrtm">' +
                                         '<i class="fa fa-chart-line" aria-hidden="true"></i> ' +
                                         t('gpxpod', 'Correct elevations for this track') +
                                         '</a>';
                        }
                        if (gpxpod.gpxmotion_compliant) {
                            var motionviewurl = gpxpod.gpxmotionview_url + 'autoplay=1&path=' +
                                        encodeURIComponent(subfo + '/' + m[NAME]);
                            table_rows = table_rows + '<a href="' + motionviewurl + '" ' +
                                         'target="_blank" class="motionviewlink">' +
                                         '<i class="fa fa-play-circle" aria-hidden="true"></i> ' +
                                         t('gpxpod','View this file in GpxMotion') +
                                         '</a>';
                            //// why not ?
                            //var motionediturl = gpxpod.gpxmotionedit_url + 'path=' +
                            //            encodeURIComponent(subfo + '/' + escapeHTML(m[NAME]));
                            //table_rows = table_rows + '<a href="' + motionediturl + '" ' +
                            //             'target="_blank" class="motioneditlink" title="' +
                            //             t('gpxpod','Edit this file in GpxMotion') + '">' +
                            //             '<i class="fa fa-play-circle-o" aria-hidden="true"></i>' +
                            //             '</a>';
                        }
                        if (gpxpod.gpxedit_compliant) {
                            var edurl = gpxpod.gpxedit_url + 'file=' +
                                        encodeURIComponent(subfo + '/' + m[NAME]);
                            table_rows = table_rows + '<a href="' + edurl + '" ' +
                                         'target="_blank" class="editlink">' +
                                         '<i class="fa fa-pencil-alt" aria-hidden="true"></i> ' +
                                         t('gpxpod','Edit this file in GpxEdit') +
                                         '</a>';
                        }
                        table_rows = table_rows + '</div>';
                    }

                    table_rows = table_rows + '</div>';

                    table_rows = table_rows +'</div></td>\n';
                    datestr = t('gpxpod','no date');
                    sortkey = 0;
                    try{
                        if (m[DATE_END] !== '' && m[DATE_END] !== 'None') {
                            var mom = moment(m[DATE_END].replace(' ', 'T'));
                            mom.tz(chosentz);
                            datestr = mom.format('YYYY-MM-DD');
                            sortkey = mom.unix();
                        }
                    }
                    catch(err) {
                    }
                    table_rows = table_rows + '<td sorttable_customkey="' + sortkey + '">' +
                                 escapeHTML(datestr) + '</td>\n';
                    table_rows = table_rows +
                    '<td>' + metersToDistanceNoAdaptNoUnit(m[TOTAL_DISTANCE]) + '</td>\n';

                    table_rows = table_rows +
                    '<td><div class="durationcol">' +
                    escapeHTML(m[TOTAL_DURATION]) + '</div></td>\n';

                    table_rows = table_rows +
                    '<td>' + metersToElevationNoUnit(m[POSITIVE_ELEVATION_GAIN]) + '</td>\n';
                    table_rows = table_rows + '</tr>\n';
                }
            }
        }

        if (table_rows === '') {
            table = '';
            $('#gpxlist').html(table);
            //$('#ticv').hide();
            $('#ticv').text(t('gpxpod', 'No track visible'));
        }
        else{
            //$('#ticv').show();
            if ($('#updtracklistcheck').is(':checked')) {
                $('#ticv').text(t('gpxpod', 'Tracks from current view'));
            }
            else{
                $('#ticv').text(t('gpxpod', 'All tracks'));
            }
            if (unit === 'metric') {
                elevationunit = 'm';
                distanceunit = 'km';
            }
            else if (unit === 'english') {
                elevationunit = 'ft';
                distanceunit = 'mi';
            }
            else if (unit === 'nautical') {
                elevationunit = 'm';
                distanceunit = 'nmi';
            }
            table = '<table id="gpxtable" class="sortable">\n<thead>';
            table = table + '<tr>';
            table = table + '<th col="1" title="' + t('gpxpod', 'Draw') + '">' +
                    '<i class="bigfa fa fa-pen-square" aria-hidden="true"></i></th>\n';
            table = table + '<th col="2">' + t('gpxpod', 'Track') +
                '<br/><i class="bigfa fa fa-road" aria-hidden="true"></i></th>\n';
            table = table + '<th col="3">' + t('gpxpod', 'Date') +
                    '<br/><i class="bigfa far fa-calendar-alt" aria-hidden="true"></i></th>\n';
            table = table + '<th col="4">' + t('gpxpod', 'Dist<br/>ance<br/>') +
                    '<i>(' + distanceunit + ')</i>'+
                    '<br/><i class="bigfa fa fa-arrows-alt-h" aria-hidden="true"></i></th>\n';
            table = table + '<th col="5">' + t('gpxpod', 'Duration') +
                    '<br/><i class="bigfa fa fa-clock" aria-hidden="true"></i></th>\n';
            table = table + '<th col="6">' + t('gpxpod', 'Cumulative<br/>elevation<br/>gain') +
                    ' <i>(' + elevationunit + ')</i>'+
                    '<br/><i class="bigfa fa fa-chart-line" aria-hidden="true"></i></th>\n';
            table = table + '</tr></thead><tbody>\n';
            table = table + table_rows;
            table = table + '</tbody></table>';
            var desc = gpxpod.sort.desc;
            var col = gpxpod.sort.col;
            $('#gpxlist').html(table);
            sorttable.makeSortable(document.getElementById('gpxtable'));
            // restore filtered columns
            $('#gpxtable thead th[col='+col+']').click();
            if (desc) {
                $('#gpxtable thead th[col='+col+']').click();
            }
        }
    }

    //////////////// DRAW TRACK /////////////////////

    // update progress percentage in track table
    function showProgress(tid) {
        $('.progress[track="' + tid + '"]').text(gpxpod.currentAjaxPercentage[tid]);
    }

    function layerBringToFront(l) {
        l.bringToFront();
    }

    function checkAddTrackDraw(tid, checkbox=null, color=null) {
        var url;
        var colorcriteria = $('#colorcriteria').val();
        var showchart = $('#showchartcheck').is(':checked');
        var cacheKey = gpxpod.subfolder + '.' + tid;
        if (gpxpod.gpxCache.hasOwnProperty(cacheKey)) {
            // add a multicolored track only if a criteria is selected and
            // no forced color was chosen
            if (colorcriteria !== 'none' && color === null) {
                addColoredTrackDraw(gpxpod.gpxCache[cacheKey], tid, showchart);
            }
            else{
                addTrackDraw(gpxpod.gpxCache[cacheKey], tid, showchart, color);
            }
        }
        else{
            var req = {
                title : tid,
            };
            // are we in the public folder page ?
            if (pageIsPublicFolder()) {
                req.username = gpxpod.username;
                req.folder = $('#publicdir').text();
                url = OC.generateUrl('/apps/gpxpod/getpublicgpx');
            }
            else{
                req.folder = gpxpod.subfolder;
                url = OC.generateUrl('/apps/gpxpod/getgpx');
            }
            gpxpod.currentAjaxPercentage[tid] = 0;
            if (checkbox !== null) {
                checkbox.parent().find('p').show();
                checkbox.hide();
            }
            showProgress(tid);
            gpxpod.currentAjax[tid] = $.ajax({
                    type: "POST",
                    async: true,
                    url: url,
                    data: req,
                    xhr: function() {
                        var xhr = new window.XMLHttpRequest();
                        xhr.addEventListener('progress', function(evt) {
                            if (evt.lengthComputable) {
                                var percentComplete = evt.loaded / evt.total * 100;
                                gpxpod.currentAjaxPercentage[tid] = parseInt(percentComplete);
                                showProgress(tid);
                            }
                        }, false);

                        return xhr;
                    }
            }).done(function (response) {
                gpxpod.gpxCache[cacheKey] = response.content;
                // add a multicolored track only if a criteria is selected and
                // no forced color was chosen
                if (colorcriteria !== 'none' && color === null) {
                    addColoredTrackDraw(response.content, tid, showchart);
                }
                else{
                    addTrackDraw(response.content, tid, showchart, color);
                }
            });
        }
    }

    function addColoredTrackDraw(gpx, tid, withElevation) {
        deleteOnHover();

        var latlngs, latlng, times, minVal, maxVal;
        var lat, lon, extval, ele, time, linkText, linkUrl, linkHTML;
        var date, dateTime, dist, speed;
        var name, cmt, desc, sym;
        var color = 'red';
        var lineBorder = $('#linebordercheck').is(':checked');
        var rteaswpt = $('#rteaswpt').is(':checked');
        var arrow = $('#arrowcheck').is(':checked');
        var colorCriteria = $('#colorcriteria').val();
        var colorCriteriaExt = $('#colorcriteriaext').val();
        var chartTitle = t('gpxpod', colorCriteriaExt) + '/' + t('gpxpod', 'distance');
        if (colorCriteria === 'elevation') {
            chartTitle = t('gpxpod', 'altitude/distance');
        }
        else if (colorCriteria === 'speed') {
            chartTitle = t('gpxpod', 'speed/distance');
        }
        else if (colorCriteria === 'pace') {
            chartTitle = t('gpxpod', 'pace(time for last km or mi)/distance');
        }
        var unit = $('#measureunitselect').val();
        var yUnit, xUnit;
        var decimalsY = 0;
        if (unit === 'metric') {
            xUnit = 'km';
            if (colorCriteria === 'speed') {
                yUnit = 'km/h';
            }
            else if (colorCriteria === 'pace') {
                yUnit = 'min/km';
                decimalsY = 2;
            }
            else if (colorCriteria === 'elevation') {
                yUnit = 'm';
            }
        }
        else if (unit === 'english') {
            xUnit = 'mi';
            if (colorCriteria === 'speed') {
                yUnit = 'mi/h';
            }
            else if (colorCriteria === 'pace') {
                yUnit = 'min/mi';
                decimalsY = 2;
            }
            else if (colorCriteria === 'elevation') {
                yUnit = 'ft';
            }
        }
        else if (unit === 'nautical') {
            xUnit = 'nmi';
            if (colorCriteria === 'speed') {
                yUnit = 'kt';
            }
            else if (colorCriteria === 'pace') {
                yUnit = 'min/nmi';
                decimalsY = 2;
            }
            else if (colorCriteria === 'elevation') {
                yUnit = 'm';
            }
        }
        if (colorCriteria === 'extension') {
            yUnit = '';
            decimalsY = 2;
        }

        var gpxp = $.parseXML(gpx.replace(/version="1.1"/, 'version="1.0"'));
        var gpxx = $(gpxp).find('gpx');

        if (gpxpod.gpxlayers.hasOwnProperty(tid)) {
            removeTrackDraw(tid);
        }

        // count the number of lines and point
        var nbPoints = gpxx.find('>wpt').length;
        var nbLines = gpxx.find('>trk').length + gpxx.find('>rte').length;

        if (withElevation) {
            removeElevation();
            if (nbLines>0) {
                var el = L.control.elevation({
                    position: 'bottomright',
                    height: 100,
                    width: 720,
                    margins: {
                        top: 10,
                        right: 120,
                        bottom: 33,
                        left: 60
                    },
                    yUnit: yUnit,
                    xUnit: xUnit,
                    hoverNumber: {
                        decimalsX: 3,
                        decimalsY: decimalsY,
                        formatter: undefined
                    },
                    title: chartTitle + ' : ' + tid,
                    timezone: $('#tzselect').val(),
                    theme: 'steelblue-theme'
                });
                el.addTo(gpxpod.map);
                gpxpod.elevationLayer = el;
                gpxpod.elevationTrack = tid;
            }
        }

        if (! gpxpod.gpxlayers.hasOwnProperty(tid)) {
            var whatToDraw = $('#trackwaypointdisplayselect').val();
            var weight = parseInt($('#lineweight').val());
            var waypointStyle = getWaypointStyle();
            var tooltipStyle = getTooltipStyle();
            var symbolOverwrite = getSymbolOverwrite();

            var gpxlayer = {color: 'linear-gradient(to right, lightgreen, yellow, red);'};
            gpxlayer.layer = L.featureGroup();
            gpxlayer.layerOutlines = null;

            var fileDesc = gpxx.find('>metadata>desc').text();

            if (whatToDraw !== 't') {
                gpxx.find('wpt').each(function() {
                    lat = $(this).attr('lat');
                    lon = $(this).attr('lon');
                    name = $(this).find('name').text();
                    cmt = $(this).find('cmt').text();
                    desc = $(this).find('desc').text();
                    sym = $(this).find('sym').text();
                    ele = $(this).find('ele').text();
                    time = $(this).find('time').text();
                    linkText = $(this).find('link text').text();
                    linkUrl = $(this).find('link').attr('href');

                    var mm = L.marker(
                        [lat, lon],
                        {
                            icon: symbolIcons[waypointStyle]
                        }
                    );
                    if (tooltipStyle === 'p') {
                        mm.bindTooltip(brify(name, 20), {permanent: true, className: 'tooltip' + color});
                    }
                    else{
                        mm.bindTooltip(brify(name, 20), {className: 'tooltip' + color});
                    }

                    var popupText = '<h3 style="text-align:center;">' + escapeHTML(name) + '</h3><hr/>' +
                                    t('gpxpod', 'Track')+ ' : ' + escapeHTML(tid) + '<br/>';
                    if (linkText && linkUrl) {
                        popupText = popupText +
                                    t('gpxpod', 'Link') + ' : <a href="' + escapeHTML(linkUrl) + '" title="' + escapeHTML(linkUrl) + '" target="_blank">'+ escapeHTML(linkText) + '</a><br/>';
                    }
                    if (ele !== '') {
                        popupText = popupText + t('gpxpod', 'Elevation')+ ' : ' +
                                    ele + 'm<br/>';
                    }
                    popupText = popupText + t('gpxpod', 'Latitude') + ' : '+ lat + '<br/>' +
                                t('gpxpod', 'Longitude') + ' : '+ lon + '<br/>';
                    if (cmt !== '') {
                        popupText = popupText +
                                    t('gpxpod', 'Comment') + ' : '+ cmt + '<br/>';
                    }
                    if (desc !== '') {
                        popupText = popupText +
                                    t('gpxpod', 'Description') + ' : ' + desc + '<br/>';
                    }
                    if (sym !== '') {
                        popupText = popupText +
                                    t('gpxpod', 'Symbol name') + ' : '+ sym;
                    }
                    if (symbolOverwrite && sym) {
                        if (symbolIcons.hasOwnProperty(sym)) {
                            mm.setIcon(symbolIcons[sym]);
                        }
                        else{
                            mm.setIcon(L.divIcon({
                                className: 'unknown',
                                iconAnchor: [12, 12]
                            }));
                        }
                    }
                    mm.bindPopup(popupText);
                    gpxlayer.layer.addLayer(mm);
                });
            }

            if (whatToDraw !== 'w') {
                gpxx.find('trk').each(function() {
                    name = $(this).find('>name').text();
                    cmt = $(this).find('>cmt').text();
                    desc = $(this).find('>desc').text();
                    linkText = $(this).find('link text').text();
                    linkUrl = $(this).find('link').attr('href');
                    $(this).find('trkseg').each(function() {
                        if (colorCriteria === 'extension') {
                            latlngs = [];
                            times = [];
                            minVal = null;
                            maxVal = null;
                            $(this).find('trkpt').each(function() {
                                lat = $(this).attr('lat');
                                lon = $(this).attr('lon');
                                extval = $(this).find('extensions '+colorCriteriaExt).text();
                                time = $(this).find('time').text();
                                times.push(time);
                                if (extval !== '') {
                                    extval = parseFloat(extval);
                                    if (extval !== Infinity) {
                                        if (minVal === null || extval < minVal) {
                                            minVal = extval;
                                        }
                                        if (maxVal === null || extval > maxVal) {
                                            maxVal = extval;
                                        }
                                    }
                                    else {
                                        extval = 0;
                                    }
                                    latlngs.push([lat, lon, extval]);
                                }
                                else{
                                    latlngs.push([lat, lon, 0]);
                                }
                            });
                        }
                        else if (colorCriteria === 'elevation') {
                            latlngs = [];
                            times = [];
                            minVal = null;
                            maxVal = null;
                            $(this).find('trkpt').each(function() {
                                lat = $(this).attr('lat');
                                lon = $(this).attr('lon');
                                ele = $(this).find('ele').text();
                                time = $(this).find('time').text();
                                times.push(time);
                                if (ele !== '') {
                                    ele = parseFloat(ele);
                                    if (unit === 'english') {
                                        ele = parseFloat(ele) * METERSTOFOOT;
                                    }
                                    if (ele !== Infinity) {
                                        if (minVal === null || ele < minVal) {
                                            minVal = ele;
                                        }
                                        if (maxVal === null || ele > maxVal) {
                                            maxVal = ele;
                                        }
                                    }
                                    else {
                                        ele = 0;
                                    }
                                    latlngs.push([lat, lon, ele]);
                                }
                                else{
                                    latlngs.push([lat, lon, 0]);
                                }
                            });
                        }
                        else if (colorCriteria === 'pace') {
                            latlngs = [];
                            times = [];
                            minVal = null;
                            maxVal = null;
                            var minMax = [];
                            $(this).find('trkpt').each(function() {
                                lat = $(this).attr('lat');
                                lon = $(this).attr('lon');
                                time = $(this).find('time').text();
                                times.push(time);
                                latlngs.push([lat, lon]);
                            });
                            getPace(latlngs, times, minMax);
                            minVal = minMax[0];
                            maxVal = minMax[1];
                        }
                        else if (colorCriteria === 'speed') {
                            latlngs = [];
                            times = [];
                            var prevLatLng = null;
                            var prevDateTime = null;
                            minVal = null;
                            maxVal = null;
                            latlng;
                            date;
                            dateTime;
                            $(this).find('trkpt').each(function() {
                                lat = $(this).attr('lat');
                                lon = $(this).attr('lon');
                                latlng = L.latLng(lat, lon);
                                ele = $(this).find('ele').text();
                                time = $(this).find('time').text();
                                times.push(time);
                                if (time !== '') {
                                    date = new Date(time);
                                    dateTime = date.getTime();
                                }

                                if (time !== '' && prevDateTime !== null) {
                                    dist = latlng.distanceTo(prevLatLng);
                                    if (unit === 'english') {
                                        dist = dist * METERSTOMILES;
                                    }
                                    else if (unit === 'metric') {
                                        dist = dist / 1000;
                                    }
                                    else if (unit === 'nautical') {
                                        dist = dist * METERSTONAUTICALMILES;
                                    }
                                    speed = dist / ((dateTime - prevDateTime) / 1000) * 3600;
                                    if (speed !== Infinity) {
                                        if (minVal === null || speed < minVal) {
                                            minVal = speed;
                                        }
                                        if (maxVal === null || speed > maxVal) {
                                            maxVal = speed;
                                        }
                                    }
                                    else {
                                        speed = 0;
                                    }
                                    latlngs.push([lat, lon, speed]);
                                }
                                else{
                                    latlngs.push([lat, lon, 0]);
                                }

                                // keep some previous values
                                prevLatLng = latlng;
                                if (time !== '') {
                                    prevDateTime = dateTime;
                                }
                                else{
                                    prevDateTime = null;
                                }
                            });
                        }

                        var outlineWidth = 0.3 * weight;
                        if (!lineBorder) {
                            outlineWidth = 0;
                        }
                        var l = L.hotline(latlngs, {
                            weight: weight,
                            outlineWidth: outlineWidth,
                            min: minVal,
                            max: maxVal
                        });
                        var popupText = gpxpod.markersPopupTxt[tid].popup;
                        if (cmt !== '') {
                            popupText = popupText + '<p class="combutton" combutforfeat="' +
                                        escapeHTML(tid) + escapeHTML(name) +
                                        '" style="margin:0; cursor:pointer;">' + t('gpxpod', 'Comment') +
                                        ' <i class="fa fa-expand"></i></p>' +
                                        '<p class="comtext" style="display:none; margin:0; cursor:pointer;" comforfeat="' +
                                        escapeHTML(tid) + escapeHTML(name) + '">' +
                                        escapeHTML(cmt) + '</p>';
                        }
                        if (desc !== '') {
                            popupText = popupText + '<p class="descbutton" descbutforfeat="' +
                                        escapeHTML(tid) + escapeHTML(name) +
                                        '" style="margin:0; cursor:pointer;">Description <i class="fa fa-expand"></i></p>' +
                                        '<p class="desctext" style="display:none; margin:0; cursor:pointer;" descforfeat="' +
                                        escapeHTML(tid) + escapeHTML(name) + '">' +
                                        escapeHTML(desc) + '</p>';
                        }
                        linkHTML = '';
                        if (linkText && linkUrl) {
                            linkHTML = '<a href="' + escapeHTML(linkUrl) + '" title="' + escapeHTML(linkUrl) + '" target="_blank">' + escapeHTML(linkText) + '</a>';
                        }
                        popupText = popupText.replace('<li>' + escapeHTML(name) + '</li>',
                                    '<li><b>' + escapeHTML(name) + ' (' + linkHTML + ')</b></li>');
                        l.bindPopup(
                                popupText,
                                {
                                    autoPan: true,
                                    autoClose: true,
                                    closeOnClick: true
                                }
                        );
                        var tooltipText = tid;
                        if (tid !== name) {
                            tooltipText = tooltipText + '<br/>' + escapeHTML(name);
                        }
                        if (tooltipStyle === 'p') {
                            l.bindTooltip(tooltipText, {permanent: true});
                        }
                        else{
                            l.bindTooltip(tooltipText, {sticky: true});
                        }
                        if (withElevation) {
                            var data = l.toGeoJSON();
                            if (times.length === data.geometry.coordinates.length) {
                                for (var i=0; i<data.geometry.coordinates.length; i++) {
                                    data.geometry.coordinates[i].push(times[i]);
                                }
                            }
                            if (data.geometry.coordinates.length !== 0) {
                                el.addData(data, l);
                            }
                        }
                        l.on('mouseover', function() {
                            hoverStyle.weight = parseInt(2 * weight);
                            defaultStyle.weight = weight;
                            l.setStyle(hoverStyle);
                            defaultStyle.color = color;
                            gpxpod.gpxlayers[tid].layer.bringToFront();
                            l.bringToFront();
                        });
                        l.on('mouseout', function() {
                            l.setStyle(defaultStyle);
                        });

                        gpxlayer.layer.addLayer(l);

                        if (arrow) {
                            var arrows = L.polylineDecorator(l);
                            arrows.setPatterns([{
                                offset: 30,
                                repeat: 40,
                                symbol: L.Symbol.arrowHead({
                                    pixelSize: 15 + weight,
                                    polygon: false,
                                    pathOptions: {
                                        stroke: true,
                                        color: 'blue',
                                        opacity: 1,
                                        weight: parseInt(weight * 0.6)
                                    }
                                })
                            }]);
                            gpxlayer.layer.addLayer(arrows);
                        }
                    });
                });
                gpxx.find('rte').each(function() {
                    name = $(this).find('>name').text();
                    cmt = $(this).find('>cmt').text();
                    desc = $(this).find('>desc').text();
                    linkText = $(this).find('link text').text();
                    linkUrl = $(this).find('link').attr('href');
                    var wpts = null;
                    var m, pname;
                    if (rteaswpt) {
                        wpts = L.featureGroup();
                    }
                    if (colorCriteria === 'extension') {
                        latlngs = [];
                        times = [];
                        minVal = null;
                        maxVal = null;
                        $(this).find('rtept').each(function() {
                            lat = $(this).attr('lat');
                            lon = $(this).attr('lon');
                            extval = $(this).find('extensions '+colorCriteriaExt).text();
                            time = $(this).find('time').text();
                            times.push(time);
                            if (extval !== '') {
                                extval = parseFloat(extval);
                                if (extval !== Infinity) {
                                    if (minVal === null || extval < minVal) {
                                        minVal = extval;
                                    }
                                    if (maxVal === null || extval > maxVal) {
                                        maxVal = extval;
                                    }
                                }
                                else {
                                    extval = 0;
                                }
                                latlngs.push([lat, lon, extval]);
                            }
                            else{
                                latlngs.push([lat, lon, 0]);
                            }
                            if (rteaswpt) {
                                m = L.marker([lat, lon], {
                                    icon: symbolIcons[waypointStyle]
                                });
                                pname = $(this).find('name').text();
                                if (pname) {
                                    m.bindTooltip(pname, {permanent: false});
                                }
                                wpts.addLayer(m);
                            }
                        });
                    }
                    else if (colorCriteria === 'elevation') {
                        latlngs = [];
                        times = [];
                        minVal = null;
                        maxVal = null;
                        $(this).find('rtept').each(function() {
                            lat = $(this).attr('lat');
                            lon = $(this).attr('lon');
                            ele = $(this).find('ele').text();
                            time = $(this).find('time').text();
                            times.push(time);
                            if (ele !== '') {
                                ele = parseFloat(ele);
                                if (unit === 'english') {
                                    ele = parseFloat(ele) * METERSTOFOOT;
                                }
                                if (ele !== Infinity) {
                                    if (minVal === null || ele < minVal) {
                                        minVal = ele;
                                    }
                                    if (maxVal === null || ele > maxVal) {
                                        maxVal = ele;
                                    }
                                }
                                else {
                                    ele = 0;
                                }
                                latlngs.push([lat, lon, ele]);
                            }
                            else{
                                latlngs.push([lat, lon, 0]);
                            }
                            if (rteaswpt) {
                                m = L.marker([lat, lon], {
                                    icon: symbolIcons[waypointStyle]
                                });
                                pname = $(this).find('name').text();
                                if (pname) {
                                    m.bindTooltip(pname, {permanent: false});
                                }
                                wpts.addLayer(m);
                            }
                        });
                    }
                    else if (colorCriteria === 'pace') {
                        latlngs = [];
                        times = [];
                        minVal = null;
                        maxVal = null;
                        var minMax = [];
                        $(this).find('rtept').each(function() {
                            lat = $(this).attr('lat');
                            lon = $(this).attr('lon');
                            time = $(this).find('time').text();
                            times.push(time);
                            latlngs.push([lat, lon]);
                            if (rteaswpt) {
                                m = L.marker([lat, lon], {
                                    icon: symbolIcons[waypointStyle]
                                });
                                pname = $(this).find('name').text();
                                if (pname) {
                                    m.bindTooltip(pname, {permanent: false});
                                }
                                wpts.addLayer(m);
                            }
                        });
                        getPace(latlngs, times, minMax);
                        minVal = minMax[0];
                        maxVal = minMax[1];
                    }
                    else if (colorCriteria === 'speed') {
                        latlngs = [];
                        times = [];
                        var prevLatLng = null;
                        var prevDateTime = null;
                        minVal = null;
                        maxVal = null;
                        latlng;
                        date;
                        dateTime;
                        $(this).find('rtept').each(function() {
                            lat = $(this).attr('lat');
                            lon = $(this).attr('lon');
                            latlng = L.latLng(lat, lon);
                            ele = $(this).find('ele').text();
                            time = $(this).find('time').text();
                            times.push(time);
                            if (time !== '') {
                                date = new Date(time);
                                dateTime = date.getTime();
                            }

                            if (time !== '' && prevDateTime !== null) {
                                dist = latlng.distanceTo(prevLatLng);
                                if (unit === 'english') {
                                    dist = dist * METERSTOMILES;
                                }
                                else if (unit === 'metric') {
                                    dist = dist / 1000;
                                }
                                else if (unit === 'nautical') {
                                    dist = dist * METERSTONAUTICALMILES;
                                }
                                var speed = dist / ((dateTime - prevDateTime) / 1000) * 3600;
                                if (speed !== Infinity) {
                                    if (minVal === null || speed < minVal) {
                                        minVal = speed;
                                    }
                                    if (maxVal === null || speed > maxVal) {
                                        maxVal = speed;
                                    }
                                }
                                else {
                                    speed = 0;
                                }
                                latlngs.push([lat, lon, speed]);
                            }
                            else{
                                latlngs.push([lat, lon, 0]);
                            }

                            // keep some previous values
                            prevLatLng = latlng;
                            if (time !== '') {
                                prevDateTime = dateTime;
                            }
                            else{
                                prevDateTime = null;
                            }
                            if (rteaswpt) {
                                m = L.marker([lat, lon], {
                                    icon: symbolIcons[waypointStyle]
                                });
                                pname = $(this).find('name').text();
                                if (pname) {
                                    m.bindTooltip(pname, {permanent: false});
                                }
                                wpts.addLayer(m);
                            }
                        });
                    }

                    var outlineWidth = 0.3 * weight;
                    if (!lineBorder) {
                        outlineWidth = 0;
                    }
                    var l = L.hotline(latlngs, {
                        weight: weight,
                        outlineWidth: outlineWidth,
                        min: minVal,
                        max: maxVal
                    });
                    var popupText = gpxpod.markersPopupTxt[tid].popup;
                    if (cmt !== '') {
                        popupText = popupText + '<p class="combutton" combutforfeat="' +
                                    escapeHTML(tid) + escapeHTML(name) +
                                    '" style="margin:0; cursor:pointer;">' + t('gpxpod', 'Comment') +
                                    ' <i class="fa fa-expand"></i></p>' +
                                    '<p class="comtext" style="display:none; margin:0; cursor:pointer;" comforfeat="' +
                                    escapeHTML(tid) + escapeHTML(name) + '">' +
                                    escapeHTML(cmt) + '</p>';
                    }
                    if (desc !== '') {
                        popupText = popupText + '<p class="descbutton" descbutforfeat="' +
                                    escapeHTML(tid) + escapeHTML(name) +
                                    '" style="margin:0; cursor:pointer;">Description <i class="fa fa-expand"></i></p>' +
                                    '<p class="desctext" style="display:none; margin:0; cursor:pointer;" descforfeat="' +
                                    escapeHTML(tid) + escapeHTML(name) + '">' +
                                    escapeHTML(desc) + '</p>';
                    }
                    linkHTML = '';
                    if (linkText && linkUrl) {
                        linkHTML = '<a href="' + escapeHTML(linkUrl) + '" title="' + escapeHTML(linkUrl) + '" target="_blank">' + escapeHTML(linkText) + '</a>';
                    }
                    popupText = popupText.replace('<li>' + escapeHTML(name) + '</li>',
                                '<li><b>' + escapeHTML(name) + ' (' + linkHTML + ')</b></li>');
                    l.bindPopup(
                            popupText,
                            {
                                autoPan: true,
                                autoClose: true,
                                closeOnClick: true
                            }
                    );
                    var tooltipText = tid;
                    if (tid !== name) {
                        tooltipText = tooltipText + '<br/>' + escapeHTML(name);
                    }
                    if (tooltipStyle === 'p') {
                        l.bindTooltip(tooltipText, {permanent: true});
                    }
                    else{
                        l.bindTooltip(tooltipText, {sticky: true});
                    }
                    if (withElevation) {
                        var data = l.toGeoJSON();
                        if (times.length === data.geometry.coordinates.length) {
                            for (var i=0; i < data.geometry.coordinates.length; i++) {
                                data.geometry.coordinates[i].push(times[i]);
                            }
                        }
                        if (data.geometry.coordinates.length !== 0) {
                            el.addData(data, l);
                        }
                    }
                    l.on('mouseover', function() {
                        hoverStyle.weight = parseInt(2 * weight);
                        defaultStyle.weight = weight;
                        l.setStyle(hoverStyle);
                        defaultStyle.color = color;
                        gpxpod.gpxlayers[tid].layer.bringToFront();
                    });
                    l.on('mouseout', function() {
                        l.setStyle(defaultStyle);
                    });

                    gpxlayer.layer.addLayer(l);
                    if (rteaswpt) {
                        gpxlayer.layer.addLayer(wpts);
                    }

                    if (arrow) {
                        var arrows = L.polylineDecorator(l);
                        arrows.setPatterns([{
                            offset: 30,
                            repeat: 40,
                            symbol: L.Symbol.arrowHead({
                                pixelSize: 15 + weight,
                                polygon: false,
                                pathOptions: {
                                    stroke: true,
                                    color: 'blue',
                                    opacity: 1,
                                    weight: parseInt(weight * 0.6)
                                }
                            })
                        }]);
                        gpxlayer.layer.addLayer(arrows);
                    }
                });
            }

            gpxlayer.layer.addTo(gpxpod.map);
            gpxpod.gpxlayers[tid] = gpxlayer;

            if ($('#autozoomcheck').is(':checked')) {
                zoomOnAllDrawnTracks();
            }


            delete gpxpod.currentAjax[tid];
            delete gpxpod.currentAjaxPercentage[tid];
            updateTrackListFromBounds();
            if ($('#openpopupcheck').is(':checked') && nbLines > 0) {
                // open popup on the marker position,
                // works better than opening marker popup
                // because the clusters avoid popup opening when marker is
                // not visible because it's grouped
                var pop = L.popup({
                    autoPan: true,
                    autoClose: true,
                    closeOnClick: true
                });
                pop.setContent(gpxpod.markersPopupTxt[tid].popup);
                pop.setLatLng(gpxpod.markersPopupTxt[tid].marker.getLatLng());
                pop.openOn(gpxpod.map);
            }
        }
    }

    function getPace(latlngs, times, minMax) {
        var min = null;
        var max = null;
        var unit = $('#measureunitselect').val();
        var i, distanceToPrev, timei, timej, delta;

        var j = 0;
        var distWindow = 0;

        var distanceFromStart = 0;
        latlngs[0].push(0);

        // if there is a missing time : pace is 0
        for (i = 0; i < latlngs.length; i++) {
            if (!times[i]) {
                for (j = 1; j < latlngs.length; j++) {
                    latlngs[j].push(0);
                }
                return;
            }
        }

        for (i = 1; i < latlngs.length; i++) {
            distanceToPrev = gpxpod.map.distance([latlngs[i-1][0], latlngs[i-1][1]], [latlngs[i][0], latlngs[i][1]]);
            if (unit === 'metric') {
                distanceToPrev = distanceToPrev / 1000;
            }
            else if (unit === 'nautical') {
                distanceToPrev = METERSTONAUTICALMILES * distanceToPrev;
            }
            else if (unit === 'english') {
                distanceToPrev = METERSTOMILES * distanceToPrev;
            }
            distanceFromStart = distanceFromStart + distanceToPrev;
            distWindow = distWindow + distanceToPrev;

            if (distanceFromStart < 1) {
                latlngs[i].push(0);
            }
            else {
                // get the pace (time to do the last km/mile) for this point
                while (j < i && distWindow > 1) {
                    j++;
                    if (unit === 'metric') {
                        distWindow = distWindow - (gpxpod.map.distance([latlngs[j-1][0], latlngs[j-1][1]], [latlngs[j][0], latlngs[j][1]]) / 1000);
                    }
                    else if (unit === 'nautical') {
                        distWindow = distWindow - (METERSTONAUTICALMILES * gpxpod.map.distance([latlngs[j-1][0], latlngs[j-1][1]], [latlngs[j][0], latlngs[j][1]]));
                    }
                    else if (unit === 'english') {
                        distWindow = distWindow - (METERSTOMILES * gpxpod.map.distance([latlngs[j-1][0], latlngs[j-1][1]], [latlngs[j][0], latlngs[j][1]]));
                    }
                }
                // the j to consider is j-1 (when dist between j and i is more than 1)
                timej = moment(times[j-1]);
                timei = moment(times[i]);
                delta = timei.diff(timej) / 1000 / 60;
                if (delta !== Infinity) {
                    if (min === null || delta < min) {
                        min = delta;
                    }
                    if (max === null || delta > max) {
                        max = delta;
                    }
                }
                else {
                    delta = 0;
                }
                latlngs[i].push(delta);
            }
        }
        minMax.push(min);
        minMax.push(max);
    }

    function addTrackDraw(gpx, tid, withElevation, forcedColor=null) {
        deleteOnHover();

        var lat, lon, name, cmt, desc, sym, ele, time, linkText, linkUrl, linkHTML;
        var latlngs, times, wpts;
        var unit = $('#measureunitselect').val();
        var yUnit, xUnit;
        if (unit === 'metric') {
            xUnit = 'km';
            yUnit = 'm';
        }
        else if (unit === 'english') {
            xUnit = 'mi';
            yUnit = 'ft';
        }
        else if (unit === 'nautical') {
            xUnit = 'nmi';
            yUnit = 'm';
        }

        var lineBorder = $('#linebordercheck').is(':checked');
        var rteaswpt = $('#rteaswpt').is(':checked');
        var arrow = $('#arrowcheck').is(':checked');
        // choose color
        var color;
        var chartTitle = t('gpxpod', 'altitude/distance');
        var coloredTooltipClass;
        var rgbc;
        $('style[track="' + tid + '"]').each(function() {
            $(this).remove();
        });
        if (forcedColor !== null) {
            color = forcedColor;
            rgbc = hexToRgb(color);
            $('<style track="' + escapeHTML(tid) + '">.tooltip' + color.replace('#','') + ' { ' +
              'background: rgba(' + rgbc.r + ', ' + rgbc.g + ', ' + rgbc.b + ', 0.4);' +
              'color: black; font-weight: bold;' +
              ' }</style>').appendTo('body');
            coloredTooltipClass = 'tooltip' + color.replace('#','');
        }
        else{
            color = colors[++lastColorUsed % colors.length];
            rgbc = hexToRgb(colorCode[color]);
            $('<style track="' + escapeHTML(tid) + '">.tooltip' + color + ' { ' +
              'background: rgba(' + rgbc.r + ', ' + rgbc.g + ', ' + rgbc.b + ', 0.4);' +
              'color: black; font-weight: bold;' +
              ' }</style>').appendTo('body');
            coloredTooltipClass = 'tooltip' + color;
        }

        var gpxp = $.parseXML(gpx.replace(/version="1.1"/, 'version="1.0"'));
        var gpxx = $(gpxp).find('gpx');

        // count the number of lines and point
        var nbPoints = gpxx.find('>wpt').length;
        var nbLines = gpxx.find('>trk').length + gpxx.find('>rte').length;

        if (withElevation) {
            removeElevation();
            if (nbLines>0) {
                var el = L.control.elevation({
                    position: 'bottomright',
                    height: 100,
                    width: 700,
                    margins: {
                        top: 10,
                        right: 120,
                        bottom: 33,
                        left: 50
                    },
                    yUnit: yUnit,
                    xUnit: xUnit,
                    title: chartTitle + ' : ' + tid,
                    timezone: $('#tzselect').val(),
                    theme: 'steelblue-theme'
                });
                el.addTo(gpxpod.map);
                gpxpod.elevationLayer = el;
                gpxpod.elevationTrack = tid;
            }
        }

        if ( (! gpxpod.gpxlayers.hasOwnProperty(tid))) {
            var whatToDraw = $('#trackwaypointdisplayselect').val();
            var weight = parseInt($('#lineweight').val());
            var waypointStyle = getWaypointStyle();
            var tooltipStyle = getTooltipStyle();
            var symbolOverwrite = getSymbolOverwrite();

            var gpxlayer = {color: color};
            gpxlayer.layerOutlines = L.layerGroup();
            gpxlayer.layer = L.featureGroup();

            var fileDesc = gpxx.find('>metadata>desc').text();

            if (whatToDraw !== 't') {
                gpxx.find('wpt').each(function() {
                    lat = $(this).attr('lat');
                    lon = $(this).attr('lon');
                    name = $(this).find('name').text();
                    cmt = $(this).find('cmt').text();
                    desc = $(this).find('desc').text();
                    sym = $(this).find('sym').text();
                    ele = $(this).find('ele').text();
                    time = $(this).find('time').text();
                    linkText = $(this).find('link text').text();
                    linkUrl = $(this).find('link').attr('href');

                    var mm = L.marker(
                        [lat, lon],
                        {
                            icon: symbolIcons[waypointStyle]
                        }
                    );
                    if (tooltipStyle === 'p') {
                        mm.bindTooltip(brify(name, 20), {permanent: true, className: coloredTooltipClass});
                    }
                    else{
                        mm.bindTooltip(brify(name, 20), {className: coloredTooltipClass});
                    }

                    var popupText = '<h3 style="text-align:center;">' + escapeHTML(name) + '</h3><hr/>' +
                                    t('gpxpod', 'Track')+ ' : ' + escapeHTML(tid) + '<br/>';
                    if (linkText && linkUrl) {
                        popupText = popupText +
                                    t('gpxpod', 'Link') + ' : <a href="' + escapeHTML(linkUrl) + '" title="' + escapeHTML(linkUrl) + '" target="_blank">'+ escapeHTML(linkText) + '</a><br/>';
                    }
                    if (ele !== '') {
                        popupText = popupText + t('gpxpod', 'Elevation')+ ' : ' +
                                    ele + 'm<br/>';
                    }
                    popupText = popupText + t('gpxpod', 'Latitude') + ' : '+ lat + '<br/>' +
                                t('gpxpod', 'Longitude') + ' : '+ lon + '<br/>';
                    if (cmt !== '') {
                        popupText = popupText +
                                    t('gpxpod', 'Comment') + ' : '+ cmt + '<br/>';
                    }
                    if (desc !== '') {
                        popupText = popupText +
                                    t('gpxpod', 'Description') + ' : '+ desc + '<br/>';
                    }
                    if (sym !== '') {
                        popupText = popupText +
                                    t('gpxpod', 'Symbol name') + ' : '+ sym;
                    }
                    if (symbolOverwrite && sym) {
                        if (symbolIcons.hasOwnProperty(sym)) {
                            mm.setIcon(symbolIcons[sym]);
                        }
                        else{
                            mm.setIcon(L.divIcon({
                                className: 'unknown',
                                iconAnchor: [12, 12]
                            }));
                        }
                    }
                    mm.bindPopup(popupText);
                    gpxlayer.layer.addLayer(mm);
                });
            }

            if (whatToDraw !== 'w') {
                gpxx.find('trk').each(function() {
                    name = $(this).find('>name').text();
                    cmt = $(this).find('>cmt').text();
                    desc = $(this).find('>desc').text();
                    linkText = $(this).find('link text').text();
                    linkUrl = $(this).find('link').attr('href');
                    $(this).find('trkseg').each(function() {
                        latlngs = [];
                        times = [];
                        $(this).find('trkpt').each(function() {
                            lat = $(this).attr('lat');
                            lon = $(this).attr('lon');
                            ele = $(this).find('ele').text();
                            if (unit === 'english') {
                                ele = parseFloat(ele) * METERSTOFOOT;
                            }
                            time = $(this).find('time').text();
                            times.push(time);
                            if (ele !== '') {
                                latlngs.push([lat, lon, ele]);
                            }
                            else{
                                latlngs.push([lat, lon]);
                            }
                        });
                        var l = L.polyline(latlngs, {
                            weight: weight,
                            opacity : 1,
                            color: color,
                        });
                        var popupText = gpxpod.markersPopupTxt[tid].popup;
                        if (cmt !== '') {
                            popupText = popupText + '<p class="combutton" combutforfeat="' +
                                        escapeHTML(tid) + escapeHTML(name) +
                                        '" style="margin:0; cursor:pointer;">' + t('gpxpod', 'Comment') +
                                        ' <i class="fa fa-expand"></i></p>' +
                                        '<p class="comtext" style="display:none; margin:0; cursor:pointer;" comforfeat="' +
                                        escapeHTML(tid) + escapeHTML(name) + '">' +
                                        escapeHTML(cmt) + '</p>';
                        }
                        if (desc !== '') {
                            popupText = popupText + '<p class="descbutton" descbutforfeat="' +
                                        escapeHTML(tid) + escapeHTML(name) +
                                        '" style="margin:0; cursor:pointer;">Description <i class="fa fa-expand"></i></p>' +
                                        '<p class="desctext" style="display:none; margin:0; cursor:pointer;" descforfeat="' +
                                        escapeHTML(tid) + escapeHTML(name) + '">' +
                                        escapeHTML(desc) + '</p>';
                        }
                        linkHTML = '';
                        if (linkText && linkUrl) {
                            linkHTML = '<a href="' + escapeHTML(linkUrl) + '" title="' + escapeHTML(linkUrl) + '" target="_blank">' + escapeHTML(linkText) + '</a>';
                        }
                        popupText = popupText.replace('<li>' + escapeHTML(name) + '</li>',
                                    '<li><b>' + escapeHTML(name) + ' (' + linkHTML + ')</b></li>');
                        l.bindPopup(
                                popupText,
                                {
                                    autoPan: true,
                                    autoClose: true,
                                    closeOnClick: true
                                }
                        );
                        var tooltipText = tid;
                        if (tid !== name) {
                            tooltipText = tooltipText + '<br/>' + escapeHTML(name);
                        }
                        if (tooltipStyle === 'p') {
                            l.bindTooltip(tooltipText, {permanent: true, className: coloredTooltipClass});
                        }
                        else{
                            l.bindTooltip(tooltipText, {sticky: true, className: coloredTooltipClass});
                        }
                        if (withElevation) {
                            var data = l.toGeoJSON();
                            if (times.length === data.geometry.coordinates.length) {
                                for (var i=0; i < data.geometry.coordinates.length; i++) {
                                    data.geometry.coordinates[i].push(times[i]);
                                }
                            }
                            if (data.geometry.coordinates.length !== 0) {
                                el.addData(data, l);
                            }
                        }
                        // border layout
                        var bl;
                        if (lineBorder) {
                            bl = L.polyline(latlngs,
                                {opacity:1, weight: parseInt(weight * 1.6), color: 'black'});
                            gpxlayer.layerOutlines.addLayer(bl);
                            bl.on('mouseover', function() {
                                hoverStyle.weight = parseInt(2 * weight);
                                defaultStyle.weight = weight;
                                l.setStyle(hoverStyle);
                                defaultStyle.color = color;
                                gpxpod.gpxlayers[tid].layerOutlines.eachLayer(layerBringToFront);
                                //layer.bringToFront();
                                gpxpod.gpxlayers[tid].layer.bringToFront();
                            });
                            bl.on('mouseout', function() {
                                l.setStyle(defaultStyle);
                            });
                            if (tooltipStyle !== 'p') {
                                bl.bindTooltip(tooltipText, {sticky: true, className: coloredTooltipClass});
                            }
                        }
                        l.on('mouseover', function() {
                            hoverStyle.weight = parseInt(2 * weight);
                            defaultStyle.weight = weight;
                            l.setStyle(hoverStyle);
                            defaultStyle.color = color;
                            if (lineBorder) {
                                gpxpod.gpxlayers[tid].layerOutlines.eachLayer(layerBringToFront);
                            }
                            //layer.bringToFront();
                            gpxpod.gpxlayers[tid].layer.bringToFront();
                        });
                        l.on('mouseout', function() {
                            l.setStyle(defaultStyle);
                        });

                        gpxlayer.layer.addLayer(l);

                        if (arrow) {
                            var arrows = L.polylineDecorator(l);
                            arrows.setPatterns([{
                                offset: 30,
                                repeat: 40,
                                symbol: L.Symbol.arrowHead({
                                    pixelSize: 15 + weight,
                                    polygon: false,
                                    pathOptions: {
                                        stroke: true,
                                        color: color,
                                        opacity: 1,
                                        weight: parseInt(weight * 0.6)
                                    }
                                })
                            }]);
                            gpxlayer.layer.addLayer(arrows);
                        }
                    });
                });

                // ROUTES
                gpxx.find('rte').each(function() {
                    name = $(this).find('>name').text();
                    cmt = $(this).find('>cmt').text();
                    desc = $(this).find('>desc').text();
                    linkText = $(this).find('link text').text();
                    linkUrl = $(this).find('link').attr('href');
                    latlngs = [];
                    times = [];
                    wpts = null;
                    var m, pname;
                    if (rteaswpt) {
                        wpts = L.featureGroup();
                    }
                    $(this).find('rtept').each(function() {
                        lat = $(this).attr('lat');
                        lon = $(this).attr('lon');
                        ele = $(this).find('ele').text();
                        if (unit === 'english') {
                            ele = parseFloat(ele) * METERSTOFOOT;
                        }
                        time = $(this).find('time').text();
                        times.push(time);
                        if (ele !== '') {
                            latlngs.push([lat, lon, ele]);
                        }
                        else{
                            latlngs.push([lat, lon]);
                        }
                        if (rteaswpt) {
                            m = L.marker([lat, lon], {
                                icon: symbolIcons[waypointStyle]
                            });
                            pname = $(this).find('name').text();
                            if (pname) {
                                m.bindTooltip(pname, {permanent: false, className: coloredTooltipClass});
                            }
                            wpts.addLayer(m);
                        }
                    });
                    var l = L.polyline(latlngs, {
                        weight: weight,
                        opacity : 1,
                        color: color,
                    });
                    var popupText = gpxpod.markersPopupTxt[tid].popup;
                    if (cmt !== '') {
                        popupText = popupText + '<p class="combutton" combutforfeat="' +
                                    escapeHTML(tid) + escapeHTML(name) +
                                    '" style="margin:0; cursor:pointer;">' + t('gpxpod', 'Comment') +
                                    ' <i class="fa fa-expand"></i></p>' +
                                    '<p class="comtext" style="display:none; margin:0; cursor:pointer;" comforfeat="' +
                                    escapeHTML(tid) + escapeHTML(name) + '">' +
                                    escapeHTML(cmt) + '</p>';
                    }
                    if (desc !== '') {
                        popupText = popupText + '<p class="descbutton" descbutforfeat="' +
                                    escapeHTML(tid) + escapeHTML(name) +
                                    '" style="margin:0; cursor:pointer;">Description <i class="fa fa-expand"></i></p>' +
                                    '<p class="desctext" style="display:none; margin:0; cursor:pointer;" descforfeat="' +
                                    escapeHTML(tid) + escapeHTML(name) + '">' +
                                    escapeHTML(desc) + '</p>';
                    }
                    linkHTML = '';
                    if (linkText && linkUrl) {
                        linkHTML = '<a href="' + escapeHTML(linkUrl) + '" title="' + escapeHTML(linkUrl) + '" target="_blank">' + escapeHTML(linkText) + '</a>';
                    }
                    popupText = popupText.replace('<li>' + escapeHTML(name) + '</li>',
                                                  '<li><b>' + escapeHTML(name) + '</b></li>');
                    l.bindPopup(
                            popupText,
                            {
                                autoPan: true,
                                autoClose: true,
                                closeOnClick: true
                            }
                    );
                    var tooltipText = tid;
                    if (tid !== name) {
                        tooltipText = tooltipText + '<br/>' + escapeHTML(name);
                    }
                    if (tooltipStyle === 'p') {
                        l.bindTooltip(tooltipText, {permanent: true, className: coloredTooltipClass});
                    }
                    else{
                        l.bindTooltip(tooltipText, {sticky: true, className: coloredTooltipClass});
                    }
                    if (withElevation) {
                        var data = l.toGeoJSON();
                        if (times.length === data.geometry.coordinates.length) {
                            for (var i = 0; i < data.geometry.coordinates.length; i++) {
                                data.geometry.coordinates[i].push(times[i]);
                            }
                        }
                        if (data.geometry.coordinates.length !== 0) {
                            el.addData(data, l);
                        }
                    }
                    // border layout
                    var bl;
                    if (lineBorder) {
                        bl = L.polyline(latlngs,
                            {opacity: 1, weight: parseInt(weight * 1.6), color: 'black'});
                        gpxlayer.layerOutlines.addLayer(bl);
                        bl.on('mouseover', function() {
                            hoverStyle.weight = parseInt(2 * weight);
                            defaultStyle.weight = weight;
                            l.setStyle(hoverStyle);
                            defaultStyle.color = color;
                            gpxpod.gpxlayers[tid].layerOutlines.eachLayer(layerBringToFront);
                            //layer.bringToFront();
                            gpxpod.gpxlayers[tid].layer.bringToFront();
                        });
                        bl.on('mouseout', function() {
                            l.setStyle(defaultStyle);
                        });
                        if (tooltipStyle !== 'p') {
                            bl.bindTooltip(tooltipText, {sticky: true, className: coloredTooltipClass});
                        }
                    }
                    l.on('mouseover', function() {
                        hoverStyle.weight = parseInt(2 * weight);
                        defaultStyle.weight = weight;
                        l.setStyle(hoverStyle);
                        defaultStyle.color = color;
                        if (lineBorder) {
                            gpxpod.gpxlayers[tid].layerOutlines.eachLayer(layerBringToFront);
                        }
                        //layer.bringToFront();
                        gpxpod.gpxlayers[tid].layer.bringToFront();
                    });
                    l.on('mouseout', function() {
                        l.setStyle(defaultStyle);
                    });

                    gpxlayer.layer.addLayer(l);
                    if (rteaswpt) {
                        gpxlayer.layer.addLayer(wpts);
                    }

                    if (arrow) {
                        var arrows = L.polylineDecorator(l);
                        arrows.setPatterns([{
                            offset: 30,
                            repeat: 40,
                            symbol: L.Symbol.arrowHead({
                                pixelSize: 15 + weight,
                                polygon: false,
                                pathOptions: {
                                    stroke: true,
                                    color: color,
                                    opacity: 1,
                                    weight: parseInt(weight * 0.6)
                                }
                            })
                        }]);
                        gpxlayer.layer.addLayer(arrows);
                    }
                });
            }

            gpxlayer.layerOutlines.addTo(gpxpod.map);
            gpxlayer.layer.addTo(gpxpod.map);
            gpxpod.gpxlayers[tid] = gpxlayer;

            if ($('#autozoomcheck').is(':checked')) {
                zoomOnAllDrawnTracks();
            }

            delete gpxpod.currentAjax[tid];
            delete gpxpod.currentAjaxPercentage[tid];
            updateTrackListFromBounds();
            if ($('#openpopupcheck').is(':checked') && nbLines > 0) {
                // open popup on the marker position,
                // works better than opening marker popup
                // because the clusters avoid popup opening when marker is
                // not visible because it's grouped
                var pop = L.popup({
                    autoPan: true,
                    autoClose: true,
                    closeOnClick: true
                });
                pop.setContent(gpxpod.markersPopupTxt[tid].popup);
                pop.setLatLng(gpxpod.markersPopupTxt[tid].marker.getLatLng());
                pop.openOn(gpxpod.map);
            }
        }
    }

    function removeTrackDraw(tid) {
        if (   gpxpod.gpxlayers.hasOwnProperty(tid)
            && gpxpod.gpxlayers[tid].hasOwnProperty('layer')
            && gpxpod.map.hasLayer(gpxpod.gpxlayers[tid].layer)
        ) {
            gpxpod.map.removeLayer(gpxpod.gpxlayers[tid].layer);
            if (gpxpod.gpxlayers[tid].layerOutlines !== null) {
                gpxpod.map.removeLayer(gpxpod.gpxlayers[tid].layerOutlines);
            }
            delete gpxpod.gpxlayers[tid].layer;
            delete gpxpod.gpxlayers[tid].layerOutlines;
            delete gpxpod.gpxlayers[tid].color;
            delete gpxpod.gpxlayers[tid];
            updateTrackListFromBounds();
            if (gpxpod.elevationTrack === tid) {
                removeElevation();
            }
        }
    }

    //////////////// COLOR PICKER /////////////////////

    function showColorPicker(trackname) {
            $('#tracknamecolor').val(trackname);
            var currentColor = gpxpod.gpxlayers[trackname].color;
            if (colorCode.hasOwnProperty(currentColor)) {
                currentColor = colorCode[currentColor];
            }
            $('#colorinput').val(currentColor);
            $('#colorinput').click();
    }

    function okColor() {
        var color = $('#colorinput').val();
        var trackname = $('#tracknamecolor').val();
        removeTrackDraw(trackname);
        var checkbox = $('input[id="' + trackname + '"]');
        if (pageIsPublicFile()) {
            displayPublicTrack(color);
        }
        else{
            checkAddTrackDraw(trackname, checkbox, color);
        }
    }

    //////////////// VARIOUS /////////////////////

    function clearCache() {
        var keysToRemove = [];
        for (var k in gpxpod.gpxCache) {
            keysToRemove.push(k);
        }

        for(var i = 0; i < keysToRemove.length; i++) {
            delete gpxpod.gpxCache[keysToRemove[i]];
        }
        gpxpod.gpxCache = {};
    }

    // if gpxedit_version > one.two.three and we're connected and not on public page
    function isGpxeditCompliant(one, two, three) {
        var ver = $('p#gpxedit_version').html();
        if (ver !== '') {
            var vspl = ver.split('.');
            return (   parseInt(vspl[0]) > one
                    || parseInt(vspl[1]) > two
                    || parseInt(vspl[2]) > three
            );
        }
        else{
            return false;
        }
    }

    // if gpxmotion_version > one.two.three and we're connected and not on public page
    function isGpxmotionCompliant(one, two, three) {
        var ver = $('p#gpxmotion_version').html();
        if (ver !== '') {
            var vspl = ver.split('.');
            return (   parseInt(vspl[0]) > one
                    || parseInt(vspl[1]) > two
                    || parseInt(vspl[2]) > three
            );
        }
        else{
            return false;
        }
    }

    function getWaypointStyle() {
        return $('#waypointstyleselect').val();
    }

    function getTooltipStyle() {
        return $('#tooltipstyleselect').val();
    }

    function getSymbolOverwrite() {
        return $('#symboloverwrite').is(':checked');
    }

    function correctElevation(link) {
        if (gpxpod.currentHoverAjax !== null) {
            gpxpod.currentHoverAjax.abort();
            hideAnimation();
        }
        var track = link.attr('track');
        var folder = gpxpod.subfolder;
        var smooth = (link.attr('class') === 'csrtms');
        showCorrectingAnimation();
        var req = {
            trackname: track,
            folder: folder,
            smooth: smooth
        };
        var url = OC.generateUrl('/apps/gpxpod/processTrackElevations');
        gpxpod.currentCorrectingAjax = $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            if (response.done) {
                // erase track cache to be sure it will be reloaded
                delete gpxpod.gpxCache[folder + '.' + track];
                // processed successfully, we reload folder
                $('#subfolderselect').change();
            }
            else{
                OC.Notification.showTemporary(response.message);
            }
        }).always(function() {
            hideAnimation();
            gpxpod.currentCorrectingAjax = null;
        });
    }

    /*
     * send ajax request to clean .marker,
     * .geojson and .geojson.colored files
     */
    function askForClean(forwhat) {
        // ask to clean by ajax
        var req = {
            forall: forwhat
        };
        var url = OC.generateUrl('/apps/gpxpod/cleanMarkersAndGeojsons');
        showDeletingAnimation();
        $('#clean_results').html('');
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            $('#clean_results').html(
                'Those files were deleted :\n<br/>' +
                response.deleted + '\n<br/>' +
                'Problems :\n<br/>' + response.problems
            );
        }).always(function() {
            hideAnimation();
        });
    }

    function cleanDb() {
        var req = {};
        var url = OC.generateUrl('/apps/gpxpod/cleanDb');
        showDeletingAnimation();
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            if (response.done === 1) {
                OC.Notification.showTemporary(t('gpxpod', 'Database has been cleaned'));
            }
            else {
                OC.Notification.showTemporary(t('gpxpod', 'Impossible to clean database'));
            }
        }).always(function() {
            hideAnimation();
        });
    }

    /*
     * If timezone changes, we regenerate popups
     * by reloading current folder
     */
    function tzChanged() {
        stopGetMarkers();
        chooseDirSubmit();

        // if it's a public link, we display it again to update dates
        if (pageIsPublicFolder()) {
            displayPublicDir();
        }
        else if (pageIsPublicFile()) {
            displayPublicTrack();
        }
    }

    function measureUnitChanged() {
        var unit = $('#measureunitselect').val();
        if (unit === 'metric') {
            $('.distanceunit').text('m');
            $('.elevationunit').text('m');
        }
        else if (unit === 'english') {
            $('.distanceunit').text('mi');
            $('.elevationunit').text('ft');
        }
        else if (unit === 'nautical') {
            $('.distanceunit').text('nmi');
            $('.elevationunit').text('m');
        }
    }

    function compareSelectedTracks() {
        // build url list
        var params = [];
        var i = 1;
        var param = 'subfolder=' + gpxpod.subfolder;
        params.push(param);
        $('#gpxtable tbody input[type=checkbox]:checked').each(function() {
            var aa = $(this).parent().parent().find('td.trackname a.tracklink');
            var trackname = aa.text();
            params.push('name' + i + '=' + trackname);
            i++;
        });

        // go to new gpxcomp tab
        var win = window.open(
            gpxpod.gpxcompRootUrl + '?' + params.join('&'),
            '_blank'
        );
        if(win) {
            //Browser has allowed it to be opened
            win.focus();
        }else{
            //Broswer has blocked it
            OC.dialogs.alert('Allow popups for this page in order'+
                             ' to open comparison tab/window.');
        }
    }

    /*
     * get key events
     */
    function checkKey(e) {
        e = e || window.event;
        var kc = e.keyCode;
        //console.log(kc);

        if (kc === 161 || kc === 223) {
            e.preventDefault();
            gpxpod.minimapControl._toggleDisplayButtonClicked();
        }
        if (kc === 60 || kc === 220) {
            e.preventDefault();
            $('#sidebar').toggleClass('collapsed');
        }
    }

    function getUrlParameter(sParam)
    {
        var sPageURL = window.location.search.substring(1);
        var sURLVariables = sPageURL.split('&');
        for (var i = 0; i < sURLVariables.length; i++)
        {
            var sParameterName = sURLVariables[i].split('=');
            if (sParameterName[0] === sParam)
            {
                return decodeURIComponent(sParameterName[1]);
            }
        }
    }

    /*
     * the directory selection has been changed
     */
    function chooseDirSubmit(processAll=false) {
        // in all cases, we clean the view (marker clusters, table)
        $('#gpxlist').html('');
        removeMarkers();
        removePictures();

        gpxpod.subfolder = $('#subfolderselect').val();
        var sel = $('#subfolderselect').prop('selectedIndex');
        if(sel === 0) {
            $('label[for=subfolderselect]').html(
                t('gpxpod', 'Folder') +
                ' :'
            );
            $('#folderbuttons').hide();
            return false;
        }
        else {
            $('#folderbuttons').show();
        }
        // we put the public link to folder
        $('.publink[type=folder]').attr('name', gpxpod.subfolder);
        $('.publink[type=folder]').attr('title',
            t('gpxpod', 'Public link to \'{folder}\' which will work only if this folder is shared in \'files\' app by public link without password', {folder: gpxpod.subfolder}));

        gpxpod.map.closePopup();
        clearCache();
        // get markers by ajax
        var req = {
            subfolder: gpxpod.subfolder,
            processAll: processAll
        };
        var url = OC.generateUrl('/apps/gpxpod/getmarkers');
        showLoadingMarkersAnimation();
        gpxpod.currentMarkerAjax = $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            if (response.error !== '') {
                OC.dialogs.alert(response.error,
                                 'Server error');
            }
            else {
                getAjaxPicturesSuccess(response.pictures);
                getAjaxMarkersSuccess(response.markers);
                selectTrackFromUrlParam();
            }
        }).always(function() {
            hideAnimation();
            gpxpod.currentMarkerAjax = null;
        });
    }

    //////////////// HOVER /////////////////////

    function displayOnHover(tid) {
        var url;
        if (gpxpod.currentHoverAjax !== null) {
            gpxpod.currentHoverAjax.abort();
            hideAnimation();
        }

        if ($('#simplehovercheck').is(':checked')) {
            var m;
            var mid = 'null';
            var i = 0;
            while (i < gpxpod.markers.length && mid !== tid) {
                mid = gpxpod.markers[i][NAME];
                m = gpxpod.markers[i];
                i++;
            }
            addSimplifiedHoverTrackDraw(m[SHORTPOINTLIST], tid);
        }
        else{
            // use the geojson cache if this track has already been loaded
            var cacheKey = gpxpod.subfolder + '.' + tid;
            if (gpxpod.gpxCache.hasOwnProperty(cacheKey)) {
                addHoverTrackDraw(gpxpod.gpxCache[cacheKey], tid);
            }
            // otherwise load it in ajax
            else{
                var req = {
                    title: tid,
                };
                // if this is a public folder link page
                if (pageIsPublicFolder()) {
                    req.username = gpxpod.username;
                    req.folder = $('#publicdir').text();
                    url = OC.generateUrl('/apps/gpxpod/getpublicgpx');
                }
                else{
                    req.folder = gpxpod.subfolder;
                    url = OC.generateUrl('/apps/gpxpod/getgpx');
                }
                showLoadingAnimation();
                gpxpod.currentHoverAjax = $.ajax({
                        type: "POST",
                        async: true,
                        url: url,
                        data: req,
                        xhr: function() {
                            var xhr = new window.XMLHttpRequest();
                            xhr.addEventListener('progress', function(evt) {
                                if (evt.lengthComputable) {
                                    var percentComplete = evt.loaded / evt.total * 100;
                                    $('#loadingpc').text(parseInt(percentComplete) + '%');
                                }
                            }, false);

                            return xhr;
                        }
                }).done(function (response) {
                    gpxpod.gpxCache[cacheKey] = response.content;
                    addHoverTrackDraw(response.content, tid);
                    hideAnimation();
                });
            }
        }
    }

    function addSimplifiedHoverTrackDraw(pointList, tid) {
        deleteOnHover();

        if (gpxpod.insideTr) {
            var lineBorder = $('#linebordercheck').is(':checked');
            var arrow = $('#arrowcheck').is(':checked');
            var weight = parseInt($('#lineweight').val());

            gpxpod.currentHoverLayer = new L.layerGroup();

            if (lineBorder) {
                gpxpod.currentHoverLayerOutlines.addLayer(L.polyline(
                    pointList,
                    {opacity: 1, weight: parseInt(weight * 1.6), color: 'black'}
                ));
            }
            var l = L.polyline(pointList, {
                weight: weight,
                style: {color: 'blue', opacity: 1},
            });
            if (arrow) {
                var arrows = L.polylineDecorator(l);
                arrows.setPatterns([{
                    offset: 30,
                    repeat: 40,
                    symbol: L.Symbol.arrowHead({
                        pixelSize: 15 + weight,
                        polygon: false,
                        pathOptions: {
                            stroke: true,
                            color: 'blue',
                            opacity: 1,
                            weight: parseInt(weight * 0.6)
                        }
                    })
                }]);
                gpxpod.currentHoverLayer.addLayer(arrows);
            }
            gpxpod.currentHoverLayer.addLayer(l);

            if (lineBorder) {
                gpxpod.currentHoverLayerOutlines.addTo(gpxpod.map);
            }
            gpxpod.currentHoverLayer.addTo(gpxpod.map);
        }
    }

    function addHoverTrackDraw(gpx, tid) {
        deleteOnHover();

        if (gpxpod.insideTr) {
            var gpxp = $.parseXML(gpx.replace(/version="1.1"/, 'version="1.0"'));
            var gpxx = $(gpxp).find('gpx');

            var lineBorder = $('#linebordercheck').is(':checked');
            var rteaswpt = $('#rteaswpt').is(':checked');
            var arrow = $('#arrowcheck').is(':checked');
            var whatToDraw = $('#trackwaypointdisplayselect').val();
            var weight = parseInt($('#lineweight').val());
            var waypointStyle = getWaypointStyle();
            var tooltipStyle = getTooltipStyle();
            var symbolOverwrite = getSymbolOverwrite();


            gpxpod.currentHoverLayer = new L.layerGroup();

            if (whatToDraw !== 't') {
                gpxx.find('>wpt').each(function() {
                    var lat = $(this).attr('lat');
                    var lon = $(this).attr('lon');
                    var name = $(this).find('name').text();
                    var cmt = $(this).find('cmt').text();
                    var desc = $(this).find('desc').text();
                    var sym = $(this).find('sym').text();
                    var ele = $(this).find('ele').text();
                    var time = $(this).find('time').text();

                    var mm = L.marker([lat, lon], {
                        icon: symbolIcons[waypointStyle]
                    });
                    if (tooltipStyle === 'p') {
                        mm.bindTooltip(brify(name, 20), {permanent: true, className: 'tooltipblue'});
                    }
                    else{
                        mm.bindTooltip(brify(name, 20), {className: 'tooltipblue'});
                    }
                    if (symbolOverwrite && sym) {
                        if (symbolIcons.hasOwnProperty(sym)) {
                            mm.setIcon(symbolIcons[sym]);
                        }
                        else{
                            mm.setIcon(L.divIcon({
                                className: 'unknown',
                                iconAnchor: [12, 12]
                            }));
                        }
                    }
                    gpxpod.currentHoverLayer.addLayer(mm);
                });
            }

            if (whatToDraw !== 'w') {
                gpxx.find('>trk').each(function() {
                    var name = $(this).find('>name').text();
                    var cmt = $(this).find('>cmt').text();
                    var desc = $(this).find('>desc').text();
                    $(this).find('trkseg').each(function() {
                        var latlngs = [];
                        $(this).find('trkpt').each(function() {
                            var lat = $(this).attr('lat');
                            var lon = $(this).attr('lon');
                            latlngs.push([lat, lon]);
                        });
                        var l = L.polyline(latlngs, {
                            weight: weight,
                            style: {color: 'blue', opacity: 1},
                        });
                        if (lineBorder) {
                            gpxpod.currentHoverLayerOutlines.addLayer(L.polyline(
                                latlngs,
                                {opacity: 1, weight: parseInt(weight * 1.6), color: 'black'}
                            ));
                        }
                        var tooltipText = tid;
                        if (tid !== name) {
                            tooltipText = tooltipText + '<br/>' + escapeHTML(name);
                        }
                        if (tooltipStyle === 'p') {
                            l.bindTooltip(tooltipText, {permanent: true, className: 'tooltipblue'});
                        }
                        if (arrow) {
                            var arrows = L.polylineDecorator(l);
                            arrows.setPatterns([{
                                offset: 30,
                                repeat: 40,
                                symbol: L.Symbol.arrowHead({
                                    pixelSize: 15 + weight,
                                    polygon: false,
                                    pathOptions: {
                                        stroke: true,
                                        color: 'blue',
                                        opacity: 1,
                                        weight: parseInt(weight * 0.6)
                                    }
                                })
                            }]);
                            gpxpod.currentHoverLayer.addLayer(arrows);
                        }
                        gpxpod.currentHoverLayer.addLayer(l);
                    });
                });

                gpxx.find('>rte').each(function() {
                    var latlngs = [];
                    var name = $(this).find('>name').text();
                    var cmt = $(this).find('>cmt').text();
                    var desc = $(this).find('>desc').text();
                    var wpts = null;
                    var m, pname;
                    if (rteaswpt) {
                        wpts = L.featureGroup();
                    }
                    $(this).find('rtept').each(function() {
                        var lat = $(this).attr('lat');
                        var lon = $(this).attr('lon');
                        latlngs.push([lat, lon]);
                        if (rteaswpt) {
                            m = L.marker([lat, lon], {
                                icon: symbolIcons[waypointStyle]
                            });
                            wpts.addLayer(m);
                        }
                    });
                    var l = L.polyline(latlngs, {
                        weight: weight,
                        style: {color: 'blue', opacity: 1},
                    });

                    if (lineBorder) {
                        gpxpod.currentHoverLayerOutlines.addLayer(L.polyline(
                            latlngs,
                            {opacity: 1, weight: parseInt(weight * 1.6), color: 'black'}
                        ));
                    }
                    var tooltipText = tid;
                    if (tid !== name) {
                        tooltipText = tooltipText + '<br/>' + escapeHTML(name);
                    }
                    if (tooltipStyle === 'p') {
                        l.bindTooltip(tooltipText, {permanent: true, className: 'tooltipblue'});
                    }
                    if (arrow) {
                        var arrows = L.polylineDecorator(l);
                        arrows.setPatterns([{
                            offset: 30,
                            repeat: 40,
                            symbol: L.Symbol.arrowHead({
                                pixelSize: 15 + weight,
                                polygon: false,
                                pathOptions: {
                                    stroke: true,
                                    color: 'blue',
                                    opacity: 1,
                                    weight: parseInt(weight * 0.6)
                                }
                            })
                        }]);
                        gpxpod.currentHoverLayer.addLayer(arrows);
                    }
                    gpxpod.currentHoverLayer.addLayer(l);
                    if (rteaswpt) {
                        gpxpod.currentHoverLayer.addLayer(wpts);
                    }
                });
            }

            gpxpod.currentHoverLayerOutlines.addTo(gpxpod.map);
            gpxpod.currentHoverLayer.addTo(gpxpod.map);
        }
    }

    function deleteOnHover() {
        gpxpod.map.removeLayer(gpxpod.currentHoverLayerOutlines);
        gpxpod.currentHoverLayerOutlines.clearLayers();
        if (gpxpod.currentHoverLayer !== null) {
            gpxpod.map.removeLayer(gpxpod.currentHoverLayer);
        }
    }

    //////////////// ANIMATIONS /////////////////////

    function showLoadingMarkersAnimation() {
        gpxpod.notificationDialog.addTo(gpxpod.map);
        $('#loadingpc').text('');

        $('#deleteload').hide();
        $('#trackload').hide();
        $('#correctload').hide();
    }

    function showCorrectingAnimation() {
        gpxpod.notificationDialog.addTo(gpxpod.map);
        $('#loadingpc').text('');

        $('#folderload').hide();
        $('#trackload').hide();
        $('#deleteload').hide();
    }

    function showLoadingAnimation() {
        gpxpod.notificationDialog.addTo(gpxpod.map);
        $('#loadingpc').text('');

        $('#folderload').hide();
        $('#correctload').hide();
        $('#deleteload').hide();
    }

    function showDeletingAnimation() {
        gpxpod.notificationDialog.addTo(gpxpod.map);
        $('#loadingpc').text('');

        $('#folderload').hide();
        $('#correctload').hide();
        $('#trackload').hide();
    }

    function hideAnimation() {
        gpxpod.notificationDialog.remove();
    }

    //////////////// PICTURES /////////////////////

    function removePictures() {
        var i;
        for (i = 0; i < gpxpod.picturePopups.length; i++) {
            gpxpod.map.closePopup(gpxpod.picturePopups[i]);
            delete gpxpod.picturePopups[i];
        }
        gpxpod.picturePopups = [];

        for (i = 0; i < gpxpod.pictureSmallMarkers.length; i++) {
            gpxpod.pictureSmallMarkers[i].remove();
            delete gpxpod.pictureSmallMarkers[i];
        }
        gpxpod.pictureSmallMarkers = [];

        for (i = 0; i < gpxpod.pictureBigMarkers.length; i++) {
            gpxpod.pictureBigMarkers[i].remove();
            delete gpxpod.pictureBigMarkers[i];
        }
        gpxpod.pictureBigMarkers = [];
    }

    function getAjaxPicturesSuccess(pictures) {
        var subpath, dlParams, dlUrl, smallPreviewParams;
        var bigPreviewParams, fullPreviewParams, previewUrl;
        var piclist = $.parseJSON(pictures);
        if (Object.keys(piclist).length > 0) {
            $('#showpicsdiv').show();
        }
        else{
            $('#showpicsdiv').hide();
        }

        var picstyle = $('#picturestyleselect').val();
        var smallPreviewX = 60;
        var smallPreviewY = 60;
        var bigPreviewX = 200;
        var bigPreviewY = 200;
        var fullPreviewX = 1200;
        var fullPreviewY = 900;

        // pictures work in normal page and public dir page
        // but the preview and DL urls are different
        if (pageIsPublicFolder()) {
            var tokenspl = gpxpod.token.split('?');
            var token = tokenspl[0];
            if (tokenspl.length === 1) {
                subpath = '/';
            }
            else{
                subpath = decodeURIComponent(tokenspl[1].replace('path=', ''));
            }
            smallPreviewParams = {
                file: '',
                x: smallPreviewX,
                y: smallPreviewY,
            };
            bigPreviewParams = {
                file: '',
                x: bigPreviewX,
                y: bigPreviewY,
                a: 1,
            };
            fullPreviewParams = {
                file: '',
                x: fullPreviewX,
                y: fullPreviewY,
                a: 1,
            };
            previewUrl = OC.generateUrl('/apps/files_sharing/publicpreview/' + token + '?');

            dlParams = {
                path: subpath,
                files: ''
            };
            dlUrl = OC.generateUrl('/s/' + token + '/download?');
        }
        else{
            dlParams = {
                dir: gpxpod.subfolder,
                files: ''
            };
            dlUrl = OC.generateUrl('/apps/files/ajax/download.php?');
            smallPreviewParams = {
                x: smallPreviewX,
                y: smallPreviewY,
                forceIcon: 0,
                file: ''
            };
            bigPreviewParams = {
                x: bigPreviewX,
                y: bigPreviewY,
                a: 1,
                forceIcon: 0,
                file: ''
            };
            fullPreviewParams = {
                x: fullPreviewX,
                y: fullPreviewY,
                a: 1,
                forceIcon: 0,
                file: ''
            };
            previewUrl = OC.generateUrl('/core/preview.png?');
            subpath = gpxpod.subfolder;
        }

        var expandoriginalpicture = $('#expandoriginalpicture').is(':checked');
        for (var p in piclist) {
            dlParams.files = p;
            var durl = dlUrl + $.param(dlParams);
            smallPreviewParams.file = subpath + '/' + p;
            bigPreviewParams.file = subpath + '/' + p;
            fullPreviewParams.file = subpath + '/' + p;
            var smallpurl = previewUrl + $.param(smallPreviewParams);
            var bigpurl = previewUrl + $.param(bigPreviewParams);
            if (expandoriginalpicture) {
                var fullpurl = durl;
            }
            else {
                var fullpurl = previewUrl + $.param(fullPreviewParams);
            }

            // POPUP
            var previewDiv = '<div class="popupImage">' +
                             '<img style="width:'+smallPreviewX+'px;" src="' + smallpurl + '"/></div>' +
                             '<i class="fa fa-expand" aria-hidden="true"></i> ' +
                             t('gpxpod', 'enlarge') + '<br/>';
            var popupContent = '<div class="picPopup"><a class="group1" href="' + fullpurl + '" title="' + p + '">' +
                               previewDiv + '</a><a href="' + durl + '" target="_blank">' +
                               '<i class="fa fa-cloud-download-alt" aria-hidden="true"></i> ' +
                               t('gpxpod', 'download') + '</a></div>';

            var popup = L.popup({
                autoClose: false,
                //offset: L.point(0, -30),
                autoPan: false,
                closeOnClick: false
            });
            popup.setContent(popupContent);
            popup.setLatLng(L.latLng(piclist[p][0], piclist[p][1]));
            gpxpod.picturePopups.push(popup);

            // MARKERS
            var tooltipContent = p + '<br/><img src="' + bigpurl + '"/>';
            var bm = L.marker(L.latLng(piclist[p][0], piclist[p][1]),
                {
                    icon: L.divIcon({
                        className: 'leaflet-marker-red',
                        iconAnchor: [12, 41]
                    })
                }
            );
            var sm = L.marker(L.latLng(piclist[p][0], piclist[p][1]),
                {
                    icon: L.divIcon({
                        iconSize: L.point(6, 6),
                        className: 'smallRedMarker'
                    })
                }
            );

            sm.on('click', function(e) {
                gpxpod.picturePopups[e.target.number].openOn(gpxpod.map);
                $('.group1').colorbox({rel: 'group1', height: '90%', photo: true});
                $('.group1').click();
                gpxpod.map.closePopup(gpxpod.picturePopups[e.target.number]);
            });

            gpxpod.pictureSmallMarkers.push(sm);
            gpxpod.pictureBigMarkers.push(bm);
            sm.bindTooltip(tooltipContent);
            bm.bindTooltip(tooltipContent);
        }

        if ($('#showpicscheck').is(':checked')) {
            showPictures();
        }
    }

    function hidePictures() {
        var i;
        for (i = 0; i < gpxpod.picturePopups.length; i++) {
            gpxpod.map.closePopup(gpxpod.picturePopups[i]);
        }
        for (i = 0; i < gpxpod.pictureSmallMarkers.length; i++) {
            gpxpod.pictureSmallMarkers[i].remove();
        }
        for (i = 0; i < gpxpod.pictureBigMarkers.length; i++) {
            gpxpod.pictureBigMarkers[i].remove();
        }
        // if it was spiderfied, we need to remove the spiderfication
        gpxpod.oms.unspiderfy();
        gpxpod.map.closePopup();
    }

    function showPictures() {
        var i;
        var picstyle = $('#picturestyleselect').val();

        if (picstyle === 'p') {
            for (i = 0; i < gpxpod.picturePopups.length; i++) {
                gpxpod.picturePopups[i].options.closeOnClick = false;
                gpxpod.picturePopups[i].options.autoClose = false;
                gpxpod.picturePopups[i].update();
                gpxpod.picturePopups[i].openOn(gpxpod.map);
            }
            $('.group1').colorbox({rel: 'group1', height: '90%', photo: true});
        }
        else if (picstyle === 'sm') {
            for (i = 0; i < gpxpod.pictureSmallMarkers.length; i++) {
                // with small markers, the popups are not permanent
                gpxpod.picturePopups[i].options.closeOnClick = true;
                gpxpod.picturePopups[i].options.autoClose = true;
                gpxpod.picturePopups[i].update();

                gpxpod.pictureSmallMarkers[i].addTo(gpxpod.map);
                gpxpod.pictureSmallMarkers[i].number = i;
            }
        }
        else{
            for (i = 0; i < gpxpod.pictureBigMarkers.length; i++) {
                // with big markers, the popups are not permanent
                gpxpod.picturePopups[i].options.closeOnClick = true;
                gpxpod.picturePopups[i].options.autoClose = true;
                gpxpod.picturePopups[i].update();

                gpxpod.pictureBigMarkers[i].addTo(gpxpod.map);
                gpxpod.pictureBigMarkers[i].number = i;
                gpxpod.oms.addMarker(gpxpod.pictureBigMarkers[i]);
            }
        }
    }

    function picStyleChange() {
        hidePictures();
        if ($('#showpicscheck').is(':checked')) {
            showPictures();
        }
    }

    function picShowChange() {
        if ($('#showpicscheck').is(':checked')) {
            showPictures();
        }
        else{
            hidePictures();
        }
    }

    //////////////// PUBLIC DIR/FILE /////////////////////

    function pageIsPublicFile() {
        var publicgpx = $('p#publicgpx').text();
        var publicdir = $('p#publicdir').text();
        return (publicgpx !== '' && publicdir === '');
    }
    function pageIsPublicFolder() {
        var publicgpx = $('p#publicgpx').text();
        var publicdir = $('p#publicdir').text();
        return (publicgpx === '' && publicdir !== '');
    }
    function pageIsPublicFileOrFolder() {
        var publicgpx = $('p#publicgpx').text();
        var publicdir = $('p#publicdir').text();
        return (publicgpx !== '' || publicdir !== '');
    }

    function getCurrentOptionValues() {
        var optionValues = {};
        optionValues.autopopup = 'y';
        if (! $('#openpopupcheck').is(':checked')) {
            optionValues.autopopup = 'n';
        }
        optionValues.autozoom = 'y';
        if (! $('#autozoomcheck').is(':checked')) {
            optionValues.autozoom = 'n';
        }
        optionValues.showchart = 'y';
        if (! $('#showchartcheck').is(':checked')) {
            optionValues.showchart = 'n';
        }
        optionValues.tableutd = 'y';
        if (! $('#updtracklistcheck').is(':checked')) {
            optionValues.tableutd = 'n';
        }
        var activeLayerName = gpxpod.activeLayers.getActiveBaseLayer().name;
        optionValues.layer = encodeURI(activeLayerName);

        optionValues.overlay = '';
        var activeOverlayLayers = gpxpod.activeLayers.getActiveOverlayLayers();
        var i;
        for (i in activeOverlayLayers) {
            optionValues.overlay += encodeURIComponent(activeOverlayLayers[i].name) + ';;';
        }
        optionValues.overlay = optionValues.overlay.replace(/;;$/, '');

        optionValues.displaymarkers = 'y';
        if (! $('#displayclusters').is(':checked')) {
            optionValues.displaymarkers = 'n';
        }
        optionValues.showpics = 'y';
        if (! $('#showpicscheck').is(':checked')) {
            optionValues.showpics = 'n';
        }
        optionValues.transp = 'y';
        if (! $('#transparentcheck').is(':checked')) {
            optionValues.transp = 'n';
        }
        optionValues.lineborders = 'y';
        if (! $('#linebordercheck').is(':checked')) {
            optionValues.lineborders = 'n';
        }
        optionValues.simplehover = 'y';
        if (! $('#simplehovercheck').is(':checked')) {
            optionValues.simplehover = 'n';
        }
        optionValues.rteaswpt = 'y';
        if (! $('#rteaswpt').is(':checked')) {
            optionValues.rteaswpt = 'n';
        }
        optionValues.arrow = 'y';
        if (! $('#arrowcheck').is(':checked')) {
            optionValues.arrow = 'n';
        }
        optionValues.sidebar = '0';
        if ($('#enablesidebar').is(':checked')) {
            optionValues.sidebar = '1';
        }
        optionValues.lineweight = $('#lineweight').val();
        optionValues.color = $('#colorcriteria').val();
        optionValues.colorext = $('#colorcriteriaext').val();
        optionValues.picstyle = $('#picturestyleselect').val();
        optionValues.tooltipstyle = $('#tooltipstyleselect').val();
        optionValues.draw = encodeURIComponent($('#trackwaypointdisplayselect').val());
        optionValues.waystyle = encodeURIComponent($('#waypointstyleselect').val());
        optionValues.unit = $('#measureunitselect').val();

        return optionValues;
    }

    function displayPublicDir() {
        $('p#nofolder').hide();
        $('p#nofoldertext').hide();

        $('#subfolderselect').hide();
        $('label[for=subfolderselect]').hide();
        $('#folderbuttons').hide();
        $('p#nofolder').hide();
        var publicdir = $('p#publicdir').html();

        var url = OC.generateUrl('/s/' + gpxpod.token);
        if ($('#pubtitle').length === 0) {
            $('div#logofolder').append(
                    '<p id="pubtitle" style="text-align:center; font-size:14px;">' +
                    '<br/>' + t('gpxpod', 'Public folder share') + ' :<br/>' +
                    '<a href="' + url + '" class="toplink" title="' +
                    t('gpxpod', 'download') + '"' +
                    ' target="_blank">' + basename(publicdir) + '</a>' +
                    '</p>'
            );
        }

        var publicmarker = $('p#publicmarker').text();
        var markers = $.parseJSON(publicmarker);
        gpxpod.markers = markers.markers;

        genPopupTxt();
        addMarkers();
        updateTrackListFromBounds();

        var pictures = $('p#pictures').html();
        getAjaxPicturesSuccess(pictures);

        if ($('#autozoomcheck').is(':checked')) {
            zoomOnAllMarkers();
        }
        else{
            gpxpod.map.setView(new L.LatLng(27, 5), 3);
        }
    }

    /*
     * manage display of public track
     * hide folder selection
     * get marker content, generate popup
     * create a markercluster
     * and finally draw the track
     */
    function displayPublicTrack(color=null) {
        $('p#nofolder').hide();
        $('p#nofoldertext').hide();

        $('#subfolderselect').hide();
        $('#folderbuttons').hide();
        $('label[for=subfolderselect]').hide();
        removeMarkers();
        gpxpod.map.closePopup();

        var publicgpx = $('p#publicgpx').html();
        publicgpx = $('<div/>').html(publicgpx).text();
        var publicmarker = $('p#publicmarker').html();
        var a = $.parseJSON(publicmarker);
        gpxpod.markers = [a];
        genPopupTxt();

        var markerclu = L.markerClusterGroup({chunkedLoading: true});
        var title = a[NAME];
        var url = OC.generateUrl('/s/' + gpxpod.token);
        if ($('#pubtitle').length === 0) {
            $('div#logofolder').append(
                    '<p id="pubtitle" style="text-align:center; font-size:14px;">' +
                    '<br/>' + t('gpxpod', 'Public file share') + ' :<br/>' +
                    '<a href="' + url + '" class="toplink" title="' +
                    t('gpxpod', 'download') + '"' +
                    ' target="_blank">' + title + '</a>' +
                    '</p>'
            );
        }
        var marker = L.marker(L.latLng(a[LAT], a[LON]), {title: title});
        marker.bindPopup(
                gpxpod.markersPopupTxt[title].popup,
                {
                    autoPan: true,
                    autoClose: true,
                    closeOnClick: true
                }
                );
        gpxpod.markersPopupTxt[title].marker = marker;
        markerclu.addLayer(marker);
        if ($('#displayclusters').is(':checked')) {
            gpxpod.map.addLayer(markerclu);
        }
        gpxpod.markerLayer = markerclu;
        var showchart = $('#showchartcheck').is(':checked');
        if ($('#colorcriteria').val() !== 'none' && color === null) {
            addColoredTrackDraw(publicgpx, title, showchart);
        }
        else{
            removeTrackDraw(title);
            addTrackDraw(publicgpx, title, showchart, color);
        }
    }

    //////////////// USER TILE SERVERS /////////////////////

    function addTileServer(type) {
        var sname = $('#'+type+'servername').val();
        var surl = $('#'+type+'serverurl').val();
        var sminzoom = $('#'+type+'minzoom').val();
        var smaxzoom = $('#'+type+'maxzoom').val();
        var stransparent = $('#'+type+'transparent').is(':checked');
        var sopacity = $('#'+type+'opacity').val() || '';
        var sformat = $('#'+type+'format').val() || '';
        var sversion = $('#'+type+'version').val() || '';
        var slayers = $('#'+type+'layers').val() || '';
        if (sname === '' || surl === '') {
            OC.dialogs.alert(t('gpxpod', 'Server name or server url should not be empty'),
                             t('gpxpod', 'Impossible to add tile server'));
            return;
        }
        if ($('#'+type+'serverlist ul li[servername="' + sname + '"]').length > 0) {
            OC.dialogs.alert(t('gpxpod', 'A server with this name already exists'),
                             t('gpxpod', 'Impossible to add tile server'));
            return;
        }
        $('#'+type+'servername').val('');
        $('#'+type+'serverurl').val('');

        var req = {
            servername: sname,
            serverurl: surl,
            type: type,
            layers: slayers,
            version: sversion,
            tformat: sformat,
            opacity: sopacity,
            transparent: stransparent,
            minzoom: sminzoom,
            maxzoom: smaxzoom,
            attribution: ''
        };
        var url = OC.generateUrl('/apps/gpxpod/addTileServer');
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            if (response.done) {
                $('#'+type+'serverlist ul').prepend(
                    '<li style="display:none;" servername="' + escapeHTML(sname) +
                    '" title="' + escapeHTML(surl) + '">' +
                    escapeHTML(sname) + ' <button>' +
                    '<i class="fa fa-trash" aria-hidden="true" style="color:red;"></i> ' +
                    t('gpxpod', 'Delete') +
                    '</button></li>'
                );
                $('#'+type+'serverlist ul li[servername="' + sname + '"]').fadeIn('slow');

                if (type === 'tile') {
                    // add tile server in leaflet control
                    var newlayer = new L.TileLayer(surl,
                        {minZoom: sminzoom, maxZoom: smaxzoom, attribution: ''});
                    gpxpod.activeLayers.addBaseLayer(newlayer, sname);
                    gpxpod.baseLayers[sname] = newlayer;
                }
                else if (type === 'tilewms'){
                    // add tile server in leaflet control
                    var newlayer = new L.tileLayer.wms(surl,
                        {format: sformat, version: sversion, layers: slayers, minZoom: sminzoom, maxZoom: smaxzoom, attribution: ''});
                    gpxpod.activeLayers.addBaseLayer(newlayer, sname);
                    gpxpod.overlayLayers[sname] = newlayer;
                }
                if (type === 'overlay') {
                    // add tile server in leaflet control
                    var newlayer = new L.TileLayer(surl,
                        {minZoom: sminzoom, maxZoom: smaxzoom, transparent: stransparent, opcacity: sopacity, attribution: ''});
                    gpxpod.activeLayers.addOverlay(newlayer, sname);
                    gpxpod.baseLayers[sname] = newlayer;
                }
                else if (type === 'overlaywms'){
                    // add tile server in leaflet control
                    var newlayer = new L.tileLayer.wms(surl,
                        {layers: slayers, version: sversion, transparent: stransparent, opacity: sopacity, format: sformat, attribution: '', minZoom: sminzoom, maxZoom: smaxzoom});
                    gpxpod.activeLayers.addOverlay(newlayer, sname);
                    gpxpod.overlayLayers[sname] = newlayer;
                }
                OC.Notification.showTemporary(t('gpxpod', 'Tile server "{ts}" has been added', {ts: sname}));
            }
            else{
                OC.Notification.showTemporary(t('gpxpod', 'Failed to add tile server "{ts}"', {ts: sname}));
            }
        }).always(function() {
        }).fail(function() {
            OC.Notification.showTemporary(t('gpxpod', 'Failed to add tile server "{ts}"', {ts: sname}));
        });
    }

    function deleteTileServer(li, type) {
        var sname = li.attr('servername');
        var req = {
            servername: sname,
            type: type
        };
        var url = OC.generateUrl('/apps/gpxpod/deleteTileServer');
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            if (response.done) {
                li.fadeOut('slow', function() {
                    li.remove();
                });
                if (type === 'tile') {
                    var activeLayerName = gpxpod.activeLayers.getActiveBaseLayer().name;
                    // if we delete the active layer, first select another
                    if (activeLayerName === sname) {
                        $('input.leaflet-control-layers-selector').first().click();
                    }
                    gpxpod.activeLayers.removeLayer(gpxpod.baseLayers[sname]);
                    delete gpxpod.baseLayers[sname];
                }
                else {
                    gpxpod.activeLayers.removeLayer(gpxpod.overlayLayers[sname]);
                    delete gpxpod.overlayLayers[sname];
                }
                OC.Notification.showTemporary(t('gpxpod', 'Tile server "{ts}" has been deleted', {ts: sname}));
            }
            else{
                OC.Notification.showTemporary(t('gpxpod', 'Failed to delete tile server "{ts}"', {ts: sname}));
            }
        }).always(function() {
        }).fail(function() {
            OC.Notification.showTemporary(t('gpxpod', 'Failed to delete tile server "{ts}"', {ts: sname}));
        });
    }

    //////////////// SAVE/RESTORE OPTIONS /////////////////////

    function restoreOptions() {
        var url = OC.generateUrl('/apps/gpxpod/getOptionsValues');
        var req = {
        };
        var optionsValues = {};
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            optionsValues = response.values;
            if (optionsValues) {
                var elem, tag, type, k;
                for (k in optionsValues) {
                    elem = $('#'+k);
                    tag = elem.prop('tagName');
                    if (k === 'waypointstyleselect') {
                        if (symbolIcons.hasOwnProperty(optionsValues[k])) {
                            elem.val(optionsValues[k]);
                            updateWaypointStyle(optionsValues[k]);
                        }
                    }
                    else if (k === 'measureunitselect') {
                        elem.val(optionsValues[k]);
                        measureUnitChanged();
                    }
                    else if (k === 'tilelayer') {
                        gpxpod.restoredTileLayer = optionsValues[k];
                    }
                    else if (tag === 'SELECT') {
                        elem.val(optionsValues[k]);
                    }
                    else if (tag === 'INPUT') {
                        type = elem.attr('type');
                        if (type === 'checkbox') {
                            elem.prop('checked', optionsValues[k] !== 'false');
                        }
                        else if (type === 'text' || type === 'number') {
                            elem.val(optionsValues[k]);
                        }
                    }
                }
            }
            // quite important ;-)
            main();
        }).fail(function() {
            OC.dialogs.alert(
                t('gpxpod', 'Failed to restore options values') + '. ' +
                t('gpxpod', 'Reload this page')
                ,
                t('gpxpod', 'Error')
            );
        });
    }

    function saveOptionTileLayer() {
        saveOptions('tilelayer');
    }

    function saveOptions(key) {
        var i, value;
        var valList = ['trackwaypointdisplayselect', 'waypointstyleselect', 'tooltipstyleselect', 'colorcriteria', 'colorcriteriaext', 'tablecriteriasel', 'picturestyleselect', 'measureunitselect', 'igctrackselect', 'lineweight'];
        var checkList = ['displayclusters', 'openpopupcheck', 'autozoomcheck', 'showchartcheck', 'transparentcheck', 'updtracklistcheck', 'showpicscheck', 'symboloverwrite', 'linebordercheck', 'simplehovercheck', 'rteaswpt', 'showshared', 'showmounted', 'arrowcheck', 'expandoriginalpicture', 'enablesidebar'];
        if (key === 'tilelayer') {
            value = gpxpod.activeLayers.getActiveBaseLayer().name;
        }
        else {
            var elem = $('#'+key);
            var tag = elem.prop('tagName');
            var type = elem.attr('type');
            if (tag === 'SELECT' || (tag === 'INPUT' && (type === 'text' || type === 'number'))) {
                value = elem.val();
            }
            else if (tag === 'INPUT' && type === 'checkbox') {
                value = elem.is(':checked');
            }
        }

        var req = {
            key: key,
            value: value
        };
        var url = OC.generateUrl('/apps/gpxpod/saveOptionValue');
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            //alert(response);
        }).fail(function() {
            OC.dialogs.alert(
                t('gpxpod', 'Failed to save options values'),
                t('gpxpod', 'Error')
            );
        });
    }

    //////////////// SYMBOLS /////////////////////

    function fillWaypointStyles() {
        for (var st in symbolIcons) {
            $('select#waypointstyleselect').append('<option value="' + st + '">' + st + '</option>');
        }
        $('select#waypointstyleselect').val('Pin, Blue');
        updateWaypointStyle('Pin, Blue');
    }

    function addExtraSymbols() {
        var url = OC.generateUrl('/apps/gpxedit/getExtraSymbol?');
        $('ul#extrasymbols li').each(function() {
            var name = $(this).attr('name');
            var smallname = $(this).html();
            var fullurl = url + 'name=' + encodeURI(name);
            var d = L.icon({
                iconUrl: fullurl,
                iconSize: L.point(24, 24),
                iconAnchor: [12, 12]
            });
            symbolIcons[smallname] = d;
        });
    }

    function updateWaypointStyle(val) {
        var sel = $('#waypointstyleselect');
        sel.removeClass(sel.attr('class'));
        sel.attr('style', '');
        if (symbolSelectClasses.hasOwnProperty(val)) {
            sel.addClass(symbolSelectClasses[val]);
        }
        else if (val !== '') {
            var url = OC.generateUrl('/apps/gpxedit/getExtraSymbol?');
            var fullurl = url + 'name=' + encodeURI(val + '.png');
            sel.attr('style',
                    'background: url(\'' + fullurl + '\') no-repeat ' +
                    'right 8px center var(--color-main-background);' +
                    'background-size: contain;');
        }
    }

    function moveSelectedTracksTo(destination) {
        var trackNameList = [];
        $('input.drawtrack:checked').each(function () {
            var tid = $(this).attr('id');
            trackNameList.push(tid);
        });

        var req = {
            tracknames: trackNameList,
            folder: gpxpod.subfolder,
            destination: destination
        };
        var url = OC.generateUrl('/apps/gpxpod/moveTracks');
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            if (! response.done) {
                var addMsg = '';
                if (response.message === 'dnw') {
                    addMsg = t('gpxpod', 'Destination directory is not writeable');
                }
                if (response.message === 'dne') {
                    addMsg = t('gpxpod', 'Destination directory does not exist');
                }
                if (response.message === 'fne') {
                    addMsg = t('gpxpod', 'Origin directory does not exist');
                }
                OC.dialogs.alert(
                    t('gpxpod', 'Failed to move selected tracks') + '. ' + addMsg,
                    t('gpxpod', 'Error')
                );
            }
            else {
                moveSuccess(response);
            }
        }).fail(function() {
            OC.dialogs.alert(
                t('gpxpod', 'Failed to move selected tracks') + '. ' +
                t('gpxpod', 'Reload this page')
                ,
                t('gpxpod', 'Error')
            );
        }).always(function() {
        });
    }

    function moveSuccess(response) {
        OC.Notification.showTemporary(t('gpxpod', 'Following files were moved successfully') + ' : ' + response.moved);
        if (response.notmoved !== '') {
            OC.Notification.showTemporary(t('gpxpod', 'Following files were NOT moved') + ' : ' + response.notmoved);
        }
        OC.Notification.showTemporary(t('gpxpod', 'Page will be reloaded in 5 sec'));
        setTimeout(function(){var url = OC.generateUrl('apps/gpxpod/'); window.location.href = url;}, 6000);
    }

    function hideAllDropDowns() {
        var dropdowns = document.getElementsByClassName('dropdown-content');
        var i;
        for (i = 0; i < dropdowns.length; i++) {
            var openDropdown = dropdowns[i];
            if (openDropdown.classList.contains('show')) {
                openDropdown.classList.remove('show');
            }
        }
    }

    //////////////// MAIN /////////////////////

    $(document).ready(function() {
        // get the exra symbols from gpxedit
        if (isGpxeditCompliant(0, 0, 2)) {
            addExtraSymbols();
        }
        fillWaypointStyles();
        if ( !pageIsPublicFileOrFolder() ) {
            restoreOptions();
        }
        else {
            main();
        }
    });

    function main() {

        if (pageIsPublicFolder() || pageIsPublicFile()) {
            var autopopup = getUrlParameter('autopopup');
            if (typeof autopopup !== 'undefined' && autopopup === 'n') {
                $('#openpopupcheck').prop('checked', false);
            }
            else{
                $('#openpopupcheck').prop('checked', true);
            }
            var autozoom = getUrlParameter('autozoom');
            if (typeof autozoom !== 'undefined' && autozoom === 'n') {
                $('#autozoomcheck').prop('checked', false);
            }
            else{
                $('#autozoomcheck').prop('checked', true);
            }
            var showchart = getUrlParameter('showchart');
            if (typeof showchart !== 'undefined' && showchart === 'n') {
                $('#showchartcheck').prop('checked', false);
            }
            else{
                $('#autozoomcheck').prop('checked', true);
            }
            var tableutd = getUrlParameter('tableutd');
            if (typeof tableutd !== 'undefined' && tableutd === 'n') {
                $('#updtracklistcheck').prop('checked', false);
            }
            else{
                $('#updtracklistcheck').prop('checked', true);
            }
            var displaymarkers = getUrlParameter('displaymarkers');
            if (typeof displaymarkers !== 'undefined' && displaymarkers === 'n') {
                $('#displayclusters').prop('checked', false);
            }
            else{
                $('#displayclusters').prop('checked', true);
            }
            var showpics = getUrlParameter('showpics');
            if (typeof showpics !== 'undefined' && showpics === 'n') {
                $('#showpicscheck').prop('checked', false);
            }
            else{
                $('#showpicscheck').prop('checked', true);
            }
            var transp = getUrlParameter('transp');
            if (typeof transp !== 'undefined' && transp === 'n') {
                $('#transparentcheck').prop('checked', false);
            }
            else{
                $('#transparentcheck').prop('checked', true);
            }
            var arrow = getUrlParameter('arrow');
            if (typeof arrow !== 'undefined' && arrow === 'n') {
                $('#arrowcheck').prop('checked', false);
            }
            else{
                $('#arrowcheck').prop('checked', true);
            }
            var simplehover = getUrlParameter('simplehover');
            if (typeof simplehover !== 'undefined' && simplehover === 'n') {
                $('#simplehovercheck').prop('checked', false);
            }
            else{
                $('#simplehovercheck').prop('checked', true);
            }
            var rteaswpt = getUrlParameter('rteaswpt');
            if (typeof rteaswpt !== 'undefined' && rteaswpt === 'n') {
                $('#rteaswpt').prop('checked', false);
            }
            else{
                $('#rteaswpt').prop('checked', true);
            }
            var lineborders = getUrlParameter('lineborders');
            if (typeof lineborders !== 'undefined' && lineborders === 'n') {
                $('#linebordercheck').prop('checked', false);
            }
            else{
                $('#linebordercheck').prop('checked', true);
            }
            var lineweight = getUrlParameter('lineweight');
            if (typeof lineweight !== 'undefined') {
                $('#lineweight').val(lineweight);
            }
            var color = getUrlParameter('color');
            if (typeof color !== 'undefined') {
                $('#colorcriteria').val(color);
            }
            var colorext = getUrlParameter('colorext');
            if (typeof colorext !== 'undefined') {
                $('#colorcriteriaext').val(colorext);
            }
            var picstyle = getUrlParameter('picstyle');
            if (typeof picstyle !== 'undefined') {
                $('#picturestyleselect').val(picstyle);
            }
            var waystyle = getUrlParameter('waystyle');
            if (typeof waystyle !== 'undefined') {
                $('#waypointstyleselect').val(waystyle);
                updateWaypointStyle(waystyle);
            }
            var unit = getUrlParameter('unit');
            if (typeof unit !== 'undefined') {
                $('#measureunitselect').val(unit);
            }
            var tooltipstyle = getUrlParameter('tooltipstyle');
            if (typeof tooltipstyle !== 'undefined') {
                $('#tooltipstyleselect').val(tooltipstyle);
            }
            var trackwaydisplay = getUrlParameter('draw');
            if (typeof trackwaydisplay !== 'undefined') {
                $('#trackwaypointdisplayselect').val(trackwaydisplay);
            }
        }

        gpxpod.username = $('p#username').html();
        gpxpod.token = $('p#token').text();
        gpxpod.gpxedit_version = $('p#gpxedit_version').html();
        gpxpod.gpxedit_compliant = isGpxeditCompliant(0, 0, 1);
        gpxpod.gpxedit_url = OC.generateUrl('/apps/gpxedit/?');
        gpxpod.gpxmotion_compliant = isGpxmotionCompliant(0, 0, 2);
        gpxpod.gpxmotionedit_url = OC.generateUrl('/apps/gpxmotion/?');
        gpxpod.gpxmotionview_url = OC.generateUrl('/apps/gpxmotion/view?');
        load_map();
        loadMarkers('');
        if (pageIsPublicFolder()) {
            gpxpod.subfolder = $('#publicdir').text();
        }

        // directory can be passed by get parameter in normal page
        if (!pageIsPublicFileOrFolder()) {
            var dirGet = getUrlParameter('dir');
            if ($('select#subfolderselect option[value="' + dirGet + '"]').length > 0) {
                $('select#subfolderselect').val(dirGet);
            }
        }

        // check a track in the sidebar table
        $('body').on('change','.drawtrack', function(e) {
            // in publink, no check
            if (pageIsPublicFile()) {
                e.preventDefault();
                $(this).prop('checked', true);
                return;
            }
            var tid = $(this).attr('id');
            if ($(this).is(':checked')) {
                if (gpxpod.currentHoverAjax !== null) {
                    gpxpod.currentHoverAjax.abort();
                    hideAnimation();
                }
                checkAddTrackDraw(tid, $(this));
            }
            else{
                removeTrackDraw(tid);
            }
        });

        // hover on a sidebar table line
        $('body').on('mouseenter', '#gpxtable tbody tr', function() {
            gpxpod.insideTr = true;
            if (gpxpod.currentCorrectingAjax === null
                && !$(this).find('.drawtrack').is(':checked')
            ) {
                var tid = $(this).find('.drawtrack').attr('id');
                displayOnHover(tid);
                if ($('#transparentcheck').is(':checked')) {
                    $('#sidebar').addClass('transparent');
                }
            }
        });
        $('body').on('mouseleave', '#gpxtable tbody tr', function() {
            if (gpxpod.currentHoverAjax !== null) {
                gpxpod.currentHoverAjax.abort();
                hideAnimation();
            }
            gpxpod.insideTr = false;
            $('#sidebar').removeClass('transparent');
            deleteOnHover();
        });

        // keeping table sort order
        $('body').on('sort', '#gpxtable thead th', function(e) {
            gpxpod.sort.col = $(this).attr('col');
            gpxpod.sort.desc = $(this).hasClass('sorttable_sorted_reverse');
        });

        //////////////// OPTION EVENTS /////////////////////

        $('body').on('change', '#transparentcheck', function() {
            if (!pageIsPublicFileOrFolder()) {
                saveOptions($(this).attr('id'));
            }
        });
        $('body').on('change', '#autozoomcheck', function() {
            if (!pageIsPublicFileOrFolder()) {
                saveOptions($(this).attr('id'));
            }
        });
        $('body').on('change', '#simplehovercheck', function() {
            if (!pageIsPublicFileOrFolder()) {
                saveOptions($(this).attr('id'));
            }
        });
        $('body').on('change', '#rteaswpt', function() {
            if (!pageIsPublicFileOrFolder()) {
                saveOptions($(this).attr('id'));
            }
        });
        $('body').on('change', '#showshared, #showmounted', function() {
            if (!pageIsPublicFileOrFolder()) {
                saveOptions($(this).attr('id'));
            }
        });
        $('body').on('change', '#expandoriginalpicture', function() {
            if (!pageIsPublicFileOrFolder()) {
                saveOptions($(this).attr('id'));
                // to make this effective
                $('#subfolderselect').change();
            }
            if (pageIsPublicFolder()) {
                removePictures();
                displayPublicDir();
            }
        });
        $('body').on('change', '#showchartcheck', function() {
            if (!pageIsPublicFileOrFolder()) {
                saveOptions($(this).attr('id'));
            }
        });
        $('body').on('change', '#openpopupcheck', function() {
            if (!pageIsPublicFileOrFolder()) {
                saveOptions($(this).attr('id'));
            }
        });
        $('body').on('change', '#displayclusters', function() {
            if (!pageIsPublicFileOrFolder()) {
                saveOptions($(this).attr('id'));
            }
            redrawMarkers();
        });
        $('body').on('change', '#measureunitselect', function() {
            if (!pageIsPublicFileOrFolder()) {
                saveOptions($(this).attr('id'));
            }
            measureUnitChanged();
            tzChanged();
        });
        $('body').on('change', '#igctrackselect', function() {
            if (!pageIsPublicFileOrFolder()) {
                saveOptions($(this).attr('id'));
            }
        });
        $('body').on('change', '#picturestyleselect', function() {
            if (!pageIsPublicFileOrFolder()) {
                saveOptions($(this).attr('id'));
            }
            picStyleChange();
        });
        $('body').on('change', '#lineweight', function() {
            if (!pageIsPublicFileOrFolder()) {
                saveOptions($(this).attr('id'));
            }
            if (pageIsPublicFile()) {
                displayPublicTrack();
            }
        });
        $('body').on('change', '#arrowcheck', function() {
            if (!pageIsPublicFileOrFolder()) {
                saveOptions($(this).attr('id'));
            }
            if (pageIsPublicFile()) {
                displayPublicTrack();
            }
        });
        $('body').on('change', '#linebordercheck', function() {
            if (!pageIsPublicFileOrFolder()) {
                saveOptions($(this).attr('id'));
            }
            if (pageIsPublicFile()) {
                displayPublicTrack();
            }
        });
        $('body').on('change', '#enablesidebar', function() {
            if (!pageIsPublicFileOrFolder()) {
                saveOptions($(this).attr('id'));
            }
        });
        $('body').on('change', '#showpicscheck', function() {
            if (!pageIsPublicFileOrFolder()) {
                saveOptions($(this).attr('id'));
            }
            picShowChange();
        });
        // change track color trigger public track (publink) redraw
        $('#colorcriteria').change(function(e) {
            if (!pageIsPublicFileOrFolder()) {
                saveOptions($(this).attr('id'));
            }
            if (pageIsPublicFile()) {
                displayPublicTrack();
            }
        });
        $('#colorcriteriaext').change(function(e) {
            if (!pageIsPublicFileOrFolder()) {
                saveOptions($(this).attr('id'));
            }
        });
        $('#waypointstyleselect').change(function(e) {
            if (!pageIsPublicFileOrFolder()) {
                saveOptions($(this).attr('id'));
            }
            if (pageIsPublicFile()) {
                displayPublicTrack();
            }
            updateWaypointStyle($(this).val());
        });
        $('#tooltipstyleselect').change(function(e) {
            if (!pageIsPublicFileOrFolder()) {
                saveOptions($(this).attr('id'));
            }
            if (pageIsPublicFile()) {
                displayPublicTrack();
            }
        });
        $('body').on('change', '#symboloverwrite', function() {
            if (!pageIsPublicFileOrFolder()) {
                saveOptions($(this).attr('id'));
            }
            if (pageIsPublicFile()) {
                displayPublicTrack();
            }
        });
        $('#trackwaypointdisplayselect').change(function(e) {
            if (!pageIsPublicFileOrFolder()) {
                saveOptions($(this).attr('id'));
            }
            if (pageIsPublicFile()) {
                displayPublicTrack();
            }
        });

        $('body').on('click', '#comparebutton', function(e) {
            compareSelectedTracks();
        });
        $('body').on('click', '#removeelevation', function(e) {
            removeElevation();
        });
        $('body').on('click', '#updtracklistcheck', function(e) {
            if (!pageIsPublicFileOrFolder()) {
                saveOptions($(this).attr('id'));
            }
            if ($('#updtracklistcheck').is(':checked')) {
                $('#ticv').text(t('gpxpod', 'Tracks from current view'));
                $('#tablecriteria').show();
            }
            else{
                $('#ticv').text('All tracks');
                $('#tablecriteria').hide();
            }
            updateTrackListFromBounds();
        });

        // in case #updtracklistcheck is restored unchecked
        if (!pageIsPublicFileOrFolder()) {
            if ($('#updtracklistcheck').is(':checked')) {
                $('#ticv').text(t('gpxpod', 'Tracks from current view'));
                $('#tablecriteria').show();
            }
            else{
                $('#ticv').text('All tracks');
                $('#tablecriteria').hide();
            }
        }

        $('#tablecriteriasel').change(function(e) {
            if (!pageIsPublicFileOrFolder()) {
                saveOptions($(this).attr('id'));
            }
            updateTrackListFromBounds();
        });

        // get key events
        document.onkeydown = checkKey;

        // fields in filters sidebar tab
        $('#clearfilter').click(function(e) {
            e.preventDefault();
            clearFiltersValues();
            redrawMarkers();
            updateTrackListFromBounds();
        });
        $('#applyfilter').click(function(e) {
            e.preventDefault();
            redrawMarkers();
            updateTrackListFromBounds();
        });
        $('select#subfolderselect').change(function(e, processAll=false) {
            stopGetMarkers();
            chooseDirSubmit(processAll);

            // dynamic url change
            if (!pageIsPublicFileOrFolder()) {
                var sel = $('#subfolderselect').prop('selectedIndex');
                if(sel === 0) {
                    document.title = 'GpxPod';
                    window.history.pushState({'html': '', 'pageTitle': ''},'', '?');
                }
                else {
                    document.title = 'GpxPod - ' + gpxpod.subfolder;
                    window.history.pushState({'html': '', 'pageTitle': ''},'', '?dir='+encodeURIComponent(gpxpod.subfolder));
                }
            }

        });

        // TIMEZONE
        var mytz = jstz.determine_timezone();
        var mytzname = mytz.timezone.olson_tz;
        var tzoptions = '';
        for (var tzk in jstz.olson.timezones) {
            var tz = jstz.olson.timezones[tzk];
            tzoptions = tzoptions + '<option value="' + tz.olson_tz +
                        '">' + tz.olson_tz + ' (GMT' +
                        tz.utc_offset + ')</option>\n';
        }
        $('#tzselect').html(tzoptions);
        $('#tzselect').val(mytzname);
        $('#tzselect').change(function(e) {
            tzChanged();
        });
        tzChanged();

        // options to clean useless files from previous GpxPod versions
        $('#clean').click(function(e) {
            e.preventDefault();
            askForClean('nono');
        });
        $('#cleanall').click(function(e) {
            e.preventDefault();
            askForClean('all');
        });
        $('#cleandb').click(function(e) {
            e.preventDefault();
            cleanDb();
        });

        // Custom tile server management
        $('body').on('click', '#tileserverlist button', function(e) {
            deleteTileServer($(this).parent(), 'tile');
        });
        $('#addtileserver').click(function() {
            addTileServer('tile');
        });
        $('body').on('click', '#overlayserverlist button', function(e) {
            deleteTileServer($(this).parent(), 'overlay');
        });
        $('#addoverlayserver').click(function() {
            addTileServer('overlay');
        });

        $('body').on('click', '#tilewmsserverlist button', function(e) {
            deleteTileServer($(this).parent(), 'tilewms');
        });
        $('#addtileserverwms').click(function() {
            addTileServer('tilewms');
        });
        $('body').on('click', '#overlaywmsserverlist button', function(e) {
            deleteTileServer($(this).parent(), 'overlaywms');
        });
        $('#addoverlayserverwms').click(function() {
            addTileServer('overlaywms');
        });

        // elevation correction of one track
        $('body').on('click', '.csrtm', function(e) {
            correctElevation($(this));
        });
        $('body').on('click', '.csrtms', function(e) {
            correctElevation($(this));
        });

        // in public link and public folder link :
        // hide compare button and custom tiles server management
        if (pageIsPublicFileOrFolder()) {
            $('button#comparebutton').hide();
            $('div#tileserverlist').hide();
            $('div#tileserveradd').hide();
        }

        // PUBLINK management
        $('body').on('click', '.publink', function(e) {
            var subfo = gpxpod.subfolder;
            if (subfo === '/') {
                subfo = '';
            }
            e.preventDefault();
            var optionValues = getCurrentOptionValues();
            var optionName;
            var url = '';

            var name = $(this).attr('name');
            var type = $(this).attr('type');
            var ttype = t('gpxpod', $(this).attr('type'));
            var title = t('gpxpod', 'Public link to') + ' ' + ttype + ' : ' + name;
            var ajaxurl, req, isShareable, token, path, txt, urlparams;
            if (type === 'track') {
                ajaxurl = OC.generateUrl('/apps/gpxpod/isFileShareable');
                req = {
                    trackpath: subfo + '/' + name
                };
                var filename;
                $.ajax({
                    type: 'POST',
                    url: ajaxurl,
                    data: req,
                    async: true
                }).done(function (response) {
                    isShareable = response.response;
                    token = response.token;
                    path = response.path;
                    filename = response.filename;

                    if (isShareable) {
                        txt = '<i class="fa fa-check-circle" style="color:green;" aria-hidden="true"></i> ';
                        url = OC.generateUrl('/apps/gpxpod/publicFile?');

                        urlparams = 'token=' + encodeURIComponent(token);
                        if (path) {
                            urlparams = urlparams + '&path=' + encodeURIComponent(path);
                            urlparams = urlparams + '&filename=' + encodeURIComponent(filename);
                        }
                        url = url + urlparams;

                        url = window.location.origin + url;
                    }
                    else{
                        txt = '<i class="fa fa-times-circle" style="color:red;" aria-hidden="true"></i> ';
                        txt = txt + t('gpxpod', 'This public link will work only if \'{title}\' or one of its parent folder is shared in \'files\' app by public link without password', {title: name});
                    }

                    if (url !== '') {
                        for (optionName in optionValues) {
                            url = url + '&' + optionName + '=' + optionValues[optionName];
                        }
                        $('#linkinput').val(url);
                    }
                    else {
                        $('#linkinput').val('');
                    }
                    $('#linkhint').hide();

                    // fill the fields, show the dialog
                    $('#linklabel').html(txt);
                    $('#linkdialog').dialog({
                        title: title,
                        width: 400,
                        open: function(event, ui) {
                            $('.ui-dialog-titlebar-close', ui.dialog | ui).html('<i class="far fa-times-circle"></i>');
                        }
                    });
                    $('#linkinput').select();
                });
            }
            else{
                var folder = $(this).attr('name');

                ajaxurl = OC.generateUrl('/apps/gpxpod/isFolderShareable');
                req = {
                    folderpath: gpxpod.subfolder
                };
                $.ajax({
                    type: 'POST',
                    url: ajaxurl,
                    data: req,
                    async: true
                }).done(function (response) {
                    isShareable = response.response;
                    token = response.token;
                    path = response.path;

                    if (isShareable) {
                        txt = '<i class="fa fa-check-circle" style="color:green;" aria-hidden="true"></i> ';
                        url = OC.generateUrl('/apps/gpxpod/publicFolder?');
                        urlparams = 'token=' + encodeURIComponent(token);
                        if (path) {
                            urlparams = urlparams + '&path=' + encodeURIComponent(path);
                        }
                        url = url + urlparams;
                        url = window.location.origin + url;
                    }
                    else{
                        txt = '<i class="fa fa-times-circle" style="color:red;" aria-hidden="true"></i> ';
                        txt = txt + t('gpxpod', 'Public link to \'{folder}\' which will work only if this folder is shared in \'files\' app by public link without password', {folder: name});
                    }

                    if (url !== '') {
                        for (optionName in optionValues) {
                            url = url + '&' + optionName + '=' + optionValues[optionName];
                        }
                        $('#linkinput').val(url);
                    }
                    else {
                        $('#linkinput').val('');
                    }
                    $('#linkhint').show();

                    // fill the fields, show the dialog
                    $('#linklabel').html(txt);
                    $('#linkdialog').dialog({
                        title: title,
                        width: 400,
                        open: function(event, ui) {
                            $('.ui-dialog-titlebar-close', ui.dialog | ui).html('<i class="far fa-times-circle"></i>');
                        }
                    });
                    $('#linkinput').select();
                });
            }
        });

        // show/hide options
        $('body').on('click','h3#optiontitle', function(e) {
            if ($('#optionscontent').is(':visible')) {
                $('#optionscontent').slideUp();
                $('#optiontoggle').html('<i class="fa fa-angle-double-down"></i>');
                $('#optiontoggle').animate({'left': 0}, 'slow');
            }
            else{
                $('#optionscontent').slideDown();
                $('#optiontoggle').html('<i class="fa fa-angle-double-up"></i>');
                var offset = parseInt($('#optiontitle').css('width')) -
                    parseInt($('#optiontoggle').css('width')) -
                    parseInt($('#optiontitletext').css('width')) - 5;
                $('#optiontoggle').animate({'left': offset}, 'slow');
            }
        });

        // on public pages
        if (pageIsPublicFolder() || pageIsPublicFile()) {
            tzChanged();
            measureUnitChanged();

            // select all tracks if it was asked
            var track = getUrlParameter('track');
            if (track === 'all') {
                $('#openpopupcheck').prop('checked', false);
                $('#showchartcheck').prop('checked', false);
                $('#displayclusters').prop('checked', false);
                $('#displayclusters').change();
                $('input.drawtrack').each(function () { $(this).prop('checked', true) });
                $('input.drawtrack').each(function () { $(this).change() });
                removeElevation();
                zoomOnAllMarkers();
            }
        }

        // comments and descs in popups
        $('body').on('click', '.comtext', function(e) {
            $(this).slideUp();
        });
        $('body').on('click', '.combutton', function(e) {
            var fid = $(this).attr('combutforfeat');
            var p = $('p[comforfeat="' + fid + '"]');
            if (p.is(':visible')) {
                p.slideUp();
            }
            else{
                p.slideDown();
            }
        });
        $('body').on('click', '.desctext', function(e) {
            $(this).slideUp();
        });
        $('body').on('click', '.descbutton', function(e) {
            var fid = $(this).attr('descbutforfeat');
            var p = $('p[descforfeat="' + fid + '"]');
            if (p.is(':visible')) {
                p.slideUp();
            }
            else{
                p.slideDown();
            }
        });

        // user color change
        $('body').on('change', '#colorinput', function(e) {
            okColor();
        });
        $('body').on('click', '.colortd', function(e) {
            if ($(this).find('input').is(':checked')) {
                var id = $(this).find('input').attr('id');
                showColorPicker(id);
            }
        });

        // buttons to select or deselect all tracks
        $('#selectall').click(function(e) {
            $('#openpopupcheck').prop('checked', false);
            $('input.drawtrack:not(checked)').each(function () {
                var tid = $(this).attr('id');
                checkAddTrackDraw(tid, $(this));
            });
        });

        $('#deselectallv').click(function(e) {
            $('input.drawtrack:checked').each(function () {
                var tid = $(this).attr('id');
                removeTrackDraw(tid);
            });
            gpxpod.map.closePopup();
        });

        $('#deselectall').click(function(e) {
            for(var tid in gpxpod.gpxlayers) {
                removeTrackDraw(tid);
            }
            gpxpod.map.closePopup();
        });

        $('#moveselectedto').click(function(e) {
            if ($('input.drawtrack:checked').length < 1) {
                OC.Notification.showTemporary(t('gpxpod', 'Select at least one track'));
            }
            else {
                OC.dialogs.filepicker(
                    t('gpxpod', 'Destination folder'),
                    function(targetPath) {
                        if (targetPath === gpxpod.subfolder) {
                            OC.Notification.showTemporary(t('gpxpod', 'Origin and destination directories must be different'));
                        }
                        else {
                            moveSelectedTracksTo(targetPath);
                        }
                    },
                    false, "httpd/unix-directory", true
                );
            }
        });

        if (pageIsPublicFile()) {
            $('#deselectall').hide();
            $('#selectall').hide();
            $('#deselectallv').hide();
        }

        $('#deleteselected').click(function(e) {
            deleteSelectedTracks();
        });

        $('body').on('click', '.deletetrack', function(e) {
            var name = $(this).attr('track');
            OC.dialogs.confirm(
                t('gpxpod',
                    'Are you sure you want to delete the track {name} ?',
                    {name: name}
                ),
                t('gpxpod','Confirm track deletion'),
                function (result) {
                    if (result) {
                        deleteOneTrack(name);
                    }
                },
                true
            );
        });

        if (!pageIsPublicFileOrFolder()) {
            $('#reloadprocessfolder').click(function() {
                $('select#subfolderselect').trigger('change', true);
            });
            $('#reloadfolder').click(function() {
                $('select#subfolderselect').change();
            });
        }

        if (pageIsPublicFileOrFolder()) {
            $('#deleteselected').hide();
            $('#cleandiv').hide();
            $('#customtilediv').hide();
            $('#moveselectedto').hide();
        }

        $('body').on('click','h3.customtiletitle', function(e) {
            var forAttr = $(this).attr('for');
            if ($('#'+forAttr).is(':visible')) {
                $('#'+forAttr).slideUp();
                $(this).find('i').removeClass('fa-angle-double-up').addClass('fa-angle-double-down');
            }
            else{
                $('#'+forAttr).slideDown();
                $(this).find('i').removeClass('fa-angle-double-down').addClass('fa-angle-double-up');
            }
        });

        // DROPDOWN management
        window.onclick = function(event) {
            if (!event.target.matches('.dropdownbutton') && !event.target.matches('.dropdownbutton i')) {
                hideAllDropDowns();
            }
        }

        $('body').on('click','.dropdownbutton', function(e) {
            var dcontent;
            if (e.target.nodeName === 'BUTTON') {
                dcontent = $(e.target).parent().find('.dropdown-content');
            }
            else {
                dcontent = $(e.target).parent().parent().find('.dropdown-content');
            }
            var isVisible = dcontent.hasClass('show');
            hideAllDropDowns();
            if (!isVisible) {
                dcontent.toggleClass('show');
            }
        });

        $('body').on('click','.zoomtrackbutton', function(e) {
            var tid = $(this).attr('name');
            if (gpxpod.gpxlayers.hasOwnProperty(tid)) {
                var b = gpxpod.gpxlayers[tid].layer.getBounds();
                var xoffset = parseInt($('#sidebar').css('width'));
                if (pageIsPublicFileOrFolder()) {
                    var showSidebar = getUrlParameter('sidebar');
                    if (showSidebar === '0') {
                        xoffset = 0;
                    }
                }
                gpxpod.map.fitBounds(b, {
                    animate: true,
                    paddingTopLeft: [xoffset, 0]
                });
            }
        });

        $('body').on('click','.drawButton', function(e) {
            var tid = $(this).attr('tid');
            var checkbox = $('input[id="' + tid + '"]');
            checkAddTrackDraw(tid, checkbox);
        });

        var buttonColor = 'blue';
        if (OCA.Theming) {
            buttonColor = OCA.Theming.color;
        }

        $('<style role="buttons">.fa, .fas, .far { ' +
            'color: ' + buttonColor + '; }</style>').appendTo('body');

    }

})(jQuery, OC);
