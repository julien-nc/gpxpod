import $ from 'jquery'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import marker from 'leaflet/dist/images/marker-icon.png'
import marker2x from 'leaflet/dist/images/marker-icon-2x.png'
import markerShadow from 'leaflet/dist/images/marker-shadow.png'
// import '@fortawesome/fontawesome-free/css/all.min.css'
import 'leaflet.locatecontrol/dist/L.Control.Locate.min.js'
import 'leaflet.locatecontrol/dist/L.Control.Locate.min.css'
import 'leaflet-mouse-position/src/L.Control.MousePosition.js'
import 'leaflet-mouse-position/src/L.Control.MousePosition.css'
import 'leaflet-polylinedecorator/dist/leaflet.polylineDecorator.js'
import 'leaflet-sidebar-v2/js/leaflet-sidebar.min.js'
import 'leaflet-sidebar-v2/css/leaflet-sidebar.min.css'
import 'leaflet-dialog/Leaflet.Dialog.js'
import 'leaflet-dialog/Leaflet.Dialog.css'
import myjstz from './detect_timezone.js'
import moment from 'moment-timezone'

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { loadState } from '@nextcloud/initial-state'
import {
	kmphToSpeedNoUnit,
	metersToDistance,
	metersToDistanceNoAdaptNoUnit,
	metersToElevation,
	metersToElevationNoUnit,
} from './utils.js'

// eslint-disable-next-line
(($, OC) => {
	'use strict'

	delete L.Icon.Default.prototype._getIconUrl
	L.Icon.Default.mergeOptions({
		iconRetinaUrl: marker2x,
		iconUrl: marker,
		shadowUrl: markerShadow,
	})

	const gpxvcomp = {
		map: {},
		actualLayer: {},
		actualLayers: [],
		actualLayerNumber: -1,
		layers: [{}, {}],
		minimapControl: null,
		searchControl: null,
		mytzname: '',
	}

	function loadMap() {
		const defaultLayer = 'OpenStreetMap'
		gpxvcomp.map = new L.Map('map', { zoomControl: true })
		L.control.scale({ metric: true, imperial: true, position: 'topleft' })
			.addTo(gpxvcomp.map)
		L.control.mousePosition().addTo(gpxvcomp.map)
		L.control.sidebar('sidebar').addTo(gpxvcomp.map)
		gpxvcomp.locateControl = L.control.locate({ follow: true })
		gpxvcomp.locateControl.addTo(gpxvcomp.map)

		gpxvcomp.map.setView(new L.LatLng(47, 3), 6)

		const baseLayers = {}

		// add base layers
		$('#basetileservers li[type=tile]').each(function() {
			const sname = $(this).attr('name')
			const surl = $(this).attr('url')
			const minz = parseInt($(this).attr('minzoom'))
			const maxz = parseInt($(this).attr('maxzoom'))
			const sattrib = $(this).attr('attribution')
			baseLayers[sname] = new L.TileLayer(surl, { minZoom: minz, maxZoom: maxz, attribution: sattrib })
		})
		// add custom layers
		$('#tileservers li').each(function() {
			const sname = $(this).attr('name')
			const surl = $(this).attr('title')
			baseLayers[sname] = new L.TileLayer(surl,
				{ maxZoom: 18, attribution: 'custom tile server' })
		})

		const baseOverlays = {}

		// add base overlays
		$('#basetileservers li[type=overlay]').each(function() {
			const sname = $(this).attr('name')
			const surl = $(this).attr('url')
			const minz = parseInt($(this).attr('minzoom'))
			const maxz = parseInt($(this).attr('maxzoom'))
			const sattrib = $(this).attr('attribution')
			baseOverlays[sname] = new L.TileLayer(surl, { minZoom: minz, maxZoom: maxz, attribution: sattrib })
		})
		// add custom overlays
		$('#overlayservers li').each(function() {
			const sname = $(this).attr('name')
			const surl = $(this).attr('title')
			baseOverlays[sname] = new L.TileLayer(surl,
				{ maxZoom: 18, attribution: 'custom tile server' })
		})

		// eslint-disable-next-line
		new L.control.layers(baseLayers, baseOverlays).addTo(gpxvcomp.map)

		gpxvcomp.map.addLayer(baseLayers[defaultLayer])

		// gpxvcomp.map.on('contextmenu',function(){return;})
	}

	function styleFunction(feature) {
		return {
			color: getColor(feature.properties),
			opacity: 0.9,
		}
	}

	function eachFeatureFunction(feature, layer, name) {
		let y, other, t1s, t2s

		const criteria = $('select#criteria option:selected').text()

		const linecolor = getColor(feature.properties)
		let tooltiptxt
		if (linecolor === 'blue') {
			tooltiptxt = name
		} else if (linecolor === 'green') {
			tooltiptxt = name + '<br/>(' + t('gpxpod', 'better in')
				+ ' ' + criteria + ')'
		} else if (linecolor === 'red') {
			tooltiptxt = name + '<br/>('
				+ t('gpxpod', 'worse in')
				+ ' ' + criteria + ')'
		}
		layer.bindTooltip(tooltiptxt, { sticky: true })

		let txt = ''
		txt = txt + '<h3 style="text-align:center;">' + t('gpxpod', 'Track') + ' : '
			+ name + '</h3><hr/>'
		if (feature.properties.time !== null) {
			txt = txt + '<div style="width:100%;text-align:center;">'
				+ '<b><u>' + t('gpxpod', 'Divergence details') + '</u></b></div>'

			const shorter = (('shorterThan' in feature.properties)
				&& (feature.properties.shorterThan.length > 0)
			)
			const distColor = shorter ? 'green' : 'red'

			txt = txt + '<ul><li style="color:' + distColor + ';"><b>'
				+ t('gpxpod', 'Divergence distance') + '</b>&nbsp;: '
				+ metersToDistance(feature.properties.distance, gpxvcomp.measureunit)
				+ '</li>'
			if (shorter) {
				txt = txt + '<li style="color:green">' + t('gpxpod', 'is shorter than') + ' '
					+ '&nbsp;: <div style="color:red">'
				for (y = 0; y < feature.properties.shorterThan.length; y++) {
					other = feature.properties.shorterThan[y]
					txt = txt + other + ' ('
						+ metersToDistance(feature.properties.distanceOthers[other], gpxvcomp.measureunit) + ')'
				}
				txt = txt + '</div> &nbsp;</li>'
			} else {
				txt = txt + '<li style="color:red">' + t('gpxpod', 'is longer than') + ' '
					+ '&nbsp;: <div style="color:green">'
				for (y = 0; y < feature.properties.longerThan.length; y++) {
					other = feature.properties.longerThan[y]
					txt = txt + other + ' ('
						+ metersToDistance(feature.properties.distanceOthers[other], gpxvcomp.measureunit) + ')'
				}
				txt = txt + '</div> &nbsp;</li>'
			}

			const quicker = (('quickerThan' in feature.properties)
				&& (feature.properties.quickerThan.length > 0)
			)
			const timeColor = quicker ? 'green' : 'red'

			txt = txt + '<li style="color:' + timeColor + ';"><b>'
				+ t('gpxpod', 'Divergence time') + '</b>&nbsp;: '
				+ feature.properties.time + ' &nbsp;</li>'
			if (quicker) {
				txt = txt + '<li style="color:green">'
					+ t('gpxpod', 'is quicker than') + ' '
					+ '&nbsp;: <div style="color:red">'
				for (y = 0; y < feature.properties.quickerThan.length; y++) {
					other = feature.properties.quickerThan[y]
					txt = txt + other + ' (' + feature.properties.timeOthers[other] + ')'
				}
				txt = txt + '</div> &nbsp;</li>'
			} else {
				txt = txt + '<li style="color:red">' + t('gpxpod', 'is slower than') + ' '
					+ '&nbsp;: <div style="color:green">'
				for (y = 0; y < feature.properties.slowerThan.length; y++) {
					other = feature.properties.slowerThan[y]
					txt = txt + other + ' (' + feature.properties.timeOthers[other] + ')'
				}
				txt = txt + '</div> &nbsp;</li>'
			}

			const lessDeniv = (('lessPositiveDenivThan' in feature.properties)
				&& (feature.properties.lessPositiveDenivThan.length > 0)
			)
			const denivColor = lessDeniv ? 'green' : 'red'

			txt = txt + '<li style="color:' + denivColor + ';"><b>'
				+ t('gpxpod', 'Cumulative elevation gain') + ' </b>'
				+ '&nbsp;: '
				+ metersToElevation(feature.properties.positiveDeniv, gpxvcomp.measureunit)
				+ '</li>'
			if (lessDeniv) {
				txt = txt + '<li style="color:green">' + t('gpxpod', 'is less than') + ' '
					+ '&nbsp;: <div style="color:red">'
				for (y = 0; y < feature.properties.lessPositiveDenivThan.length; y++) {
					other = feature.properties.lessPositiveDenivThan[y]
					txt = txt + other + ' ('
						+ metersToElevation(feature.properties.positiveDenivOthers[other], gpxvcomp.measureunit) + ')'
				}
				txt = txt + '</div> &nbsp;</li>'
			} else {
				txt = txt + '<li style="color:red">' + t('gpxpod', 'is more than') + ' '
					+ '&nbsp;: <div style="color:green">'
				for (y = 0; y < feature.properties.morePositiveDenivThan.length; y++) {
					other = feature.properties.morePositiveDenivThan[y]
					txt = txt + other + ' ('
						+ metersToElevation(feature.properties.positiveDenivOthers[other], gpxvcomp.measureunit) + ')'
				}
				txt = txt + '</div> &nbsp;</li>'
			}
			txt = txt + '</ul>'
		} else {
			txt = txt + '<li><b>' + t('gpxpod', 'There is no divergence here') + '</b></li>'
		}
		txt = txt + '<hr/>'
		txt = txt + '<div style="text-align:center">'
		txt = txt + '<b><u>' + t('gpxpod', 'Segment details') + '</u></b></div>'
		txt = txt + '<ul>'
		txt = txt + '<li>' + t('gpxpod', 'Segment id') + ' : ' + feature.properties.id + '</li>'
		txt = txt + '<li>' + t('gpxpod', 'From') + ' : ' + feature.geometry.coordinates[0][1]
			+ ' ; ' + feature.geometry.coordinates[0][0] + '</li>'
		const lastCoordIndex = feature.geometry.coordinates.length - 1
		txt = txt + '<li>' + t('gpxpod', 'To') + ' : ' + feature.geometry.coordinates[lastCoordIndex][1]
			+ ' ; ' + feature.geometry.coordinates[lastCoordIndex][0] + '</li>'
		try {
			const tsplt = feature.properties.timestamps.split(' ; ')
			const t1 = moment(tsplt[0].replace(' ', 'T'))
			const t2 = moment(tsplt[1].replace(' ', 'T'))
			t1.tz(gpxvcomp.mytzname)
			t2.tz(gpxvcomp.mytzname)
			t1s = t1.format('YYYY-MM-DD HH:mm:ss (Z)')
			t2s = t2.format('YYYY-MM-DD HH:mm:ss (Z)')
		} catch (err) {
			t1s = 'no date'
			t2s = 'no date'
		}
		txt = txt + '<li>' + t('gpxpod', 'Time') + ' :<br/>&emsp;' + t1s
			+ ' &#x21e8; <br/>&emsp;' + t2s + '</li>'
		txt = txt + '<li>' + t('gpxpod', 'Elevation') + ' : '
			+ metersToElevation(feature.properties.elevation[0], gpxvcomp.measureunit)
			+ ' &#x21e8; ' + metersToElevation(feature.properties.elevation[1], gpxvcomp.measureunit) + '</li>'
		txt = txt + '</ul>'
		layer.bindPopup(txt, { autoPan: true })
	}

	// if criteria or track pair is changed on the page, dynamically draw
	// considering changes
	function drawResults() {
		let layer
		for (layer in gpxvcomp.actualLayers) {
			gpxvcomp.map.removeLayer(gpxvcomp.actualLayers[layer])
		}

		const geojsons = loadState('gpxpod', 'geojson')

		// const criteria = $('select#criteria option:selected').val()
		const name1 = $('select option:selected').attr('name1')
		const name2 = $('select option:selected').attr('name2')
		const odata1 = JSON.parse(geojsons[name1 + name2])
		const odata2 = JSON.parse(geojsons[name2 + name1])

		const results = [odata1, odata2]
		const names = [name1, name2]
		let n
		for (n = 0; n < 2; n++) {
			delete gpxvcomp.layers[n]
			// eslint-disable-next-line
			gpxvcomp.layers[n] = new L.geoJson(results[n], {
				weight: 5,
				style: styleFunction,
				onEachFeature(feature, layer) {
					eachFeatureFunction(feature, layer, names[n])
				},
			})
			gpxvcomp.layers[n].addTo(gpxvcomp.map)
		}

		gpxvcomp.actualLayers = [gpxvcomp.layers[0], gpxvcomp.layers[1]]
		// const coordMin = results[0].features[0].geometry.coordinates[0]
		// const coordMax = results[0].features[results[0].features.length - 1].geometry.coordinates[0]

		const bounds1 = gpxvcomp.layers[0].getBounds()
		const bounds2 = bounds1.extend(gpxvcomp.layers[1].getBounds())
		if (bounds2.isValid()) {
			gpxvcomp.map.fitBounds(bounds2,
				{ animate: true, paddingTopLeft: [parseInt($('#sidebar').css('width')), 0] }
			)
		}
		// var txt = '<p>'+t('gpxpod','Comparison between')+' :\n'
		// txt = txt + '<ul class="trackpairlist"><li>'+name1+'</li><li>'+name2+'</li></ul></p>'
		let txt = ''
		if (!gpxvcomp.layers[0].getBounds()
			.intersects(gpxvcomp.layers[1].getBounds())) {

			txt = txt + '<p style="color:red">Those tracks are not comparable.</p>'
		}
		txt = txt + '<p>' + t('gpxpod', 'Click on a track line to get details on the section') + '.</p><br/>'
		$('#status').html(txt)

		colorSelectedTrackColumns()
	}

	// get a feature color considering the track pair
	// currently under comparison and the used criteria
	function getColor(props) {
		let color = 'blue'
		const name1 = $('select#gpxselect option:selected').attr('name1')
		const name2 = $('select#gpxselect option:selected').attr('name2')
		const criteria = $('select#criteria option:selected').val()
		if (criteria === 'distance') {
			if (('shorterThan' in props)
				&& (props.shorterThan.indexOf(name1) !== -1
					|| props.shorterThan.indexOf(name2) !== -1)) {
				color = 'green'
			}
			if (('longerThan' in props)
				&& (props.longerThan.indexOf(name1) !== -1
					|| props.longerThan.indexOf(name2) !== -1)) {
				color = 'red'
			}
		} else if (criteria === 'time') {
			// console.log(props['quickerThan'] + ' // '+name1+ ' // '+name2)
			if (('quickerThan' in props)
				&& (props.quickerThan.indexOf(name1) !== -1
					|| props.quickerThan.indexOf(name2) !== -1)) {
				color = 'green'
			}
			if (('slowerThan' in props)
				&& (props.slowerThan.indexOf(name1) !== -1
					|| props.slowerThan.indexOf(name2) !== -1)) {
				color = 'red'
			}
		} else if (criteria === 'cumulative elevation gain') {
			if (('lessPositiveDenivThan' in props)
				&& (props.lessPositiveDenivThan.indexOf(name1) !== -1
					|| props.lessPositiveDenivThan.indexOf(name2) !== -1)) {
				color = 'green'
			}
			if (('morePositiveDenivThan' in props)
				&& (props.morePositiveDenivThan.indexOf(name1) !== -1
					|| props.morePositiveDenivThan.indexOf(name2) !== -1)) {
				color = 'red'
			}
		}
		return color
	}

	function checkKey(e) {
		e = e || window.event
		const kc = e.keyCode

		if (kc === 161) {
			e.preventDefault()
			gpxvcomp.minimapControl._toggleDisplayButtonClicked()
		}
		if (kc === 60) {
			e.preventDefault()
			$('#sidebar').toggleClass('collapsed')
		}
	}

	// add a file input in the form
	function addFileInput() {
		if ($('div.fileupdiv').length < 10) {
			$('<div style="display:none" class="fileupdiv"><input id="gpxup99" '
				+ 'name="gpx99" type="file"/>&nbsp;<button class="rmFile" >'
				+ '<i class="fa fa-minus-circle" aria-hidden="true"></i></button>'
				+ '</div>').insertAfter($('div.fileupdiv:last')).slideDown(300)

			resetFileUploadNumbers()
		}
	}

	// remove a file input from the form
	function rmFileInput(elem) {
		elem.slideUp(300, function() {
			elem.remove()
		})
		resetFileUploadNumbers()
	}

	// on each form modification, reset correctly
	// the field numbers to be consecutive
	function resetFileUploadNumbers() {
		let num = 1
		$('div.fileupdiv').each(function() {
			const inp = $(this).find('input')
			inp.attr('id', 'gpx' + num)
			inp.attr('name', 'gpx' + num)
			num++
		})
	}

	function colorSelectedTrackColumns() {
		// reset colors
		$('#stattable td').each(function() {
			if (!$(this).hasClass('statnamecol')) {
				$(this).removeClass('selectedColumn')
				$(this).addClass('normal')
			}
		})
		// then color columns
		const name1 = $('select option:selected').attr('name1')
		const name2 = $('select option:selected').attr('name2')
		$('td[track="' + name1 + '"]').addClass('selectedColumn')
		$('td[track="' + name2 + '"]').addClass('selectedColumn')
		$('td[track="' + name1 + '"]').removeClass('normal')
		$('td[track="' + name2 + '"]').removeClass('normal')
	}

	function getMeasureUnit() {
		let unit = 'metric'
		const url = generateUrl('/apps/gpxpod/getOptionsValues')
		const req = {}
		let optionsValues = '{}'
		axios.post(url, req).then((response) => {
			optionsValues = response.data.values
			if (optionsValues.measureunit !== undefined) {
				unit = optionsValues.measureunit
			}
			gpxvcomp.measureunit = unit

			applyMeasureUnit(unit)
		}).catch((error) => {
			console.error(error)
		})
	}

	function applyMeasureUnit(unit) {

		// set unit in global table
		if (unit === 'metric') {
			$('.distanceunit').text('km')
			$('.speedunit').text('km/h')
			$('.elevationunit').text('m')
		} else if (unit === 'english') {
			$('.distanceunit').text('mi')
			$('.speedunit').text('mi/h')
			$('.elevationunit').text('ft')
		} else if (unit === 'nautical') {
			$('.distanceunit').text('nmi')
			$('.speedunit').text('kt')
			$('.elevationunit').text('m')
		}

		// convert values in global table
		$('table#stattable tr[stat=length_2d] td:not(.statnamecol)').each(function() {
			const val = parseFloat($(this).text()) * 1000
			$(this).text(metersToDistanceNoAdaptNoUnit(val, gpxvcomp.measureunit))
		})
		$('table#stattable tr[stat=length_3d] td:not(.statnamecol)').each(function() {
			const val = parseFloat($(this).text()) * 1000
			$(this).text(metersToDistanceNoAdaptNoUnit(val, gpxvcomp.measureunit))
		})
		$('table#stattable tr[stat=moving_avg_speed] td:not(.statnamecol)').each(function() {
			const val = $(this).text()
			$(this).text(kmphToSpeedNoUnit(val, gpxvcomp.measureunit))
		})
		$('table#stattable tr[stat=avg_speed] td:not(.statnamecol)').each(function() {
			const val = $(this).text()
			$(this).text(kmphToSpeedNoUnit(val, gpxvcomp.measureunit))
		})
		$('table#stattable tr[stat=max_speed] td:not(.statnamecol)').each(function() {
			const val = $(this).text()
			$(this).text(kmphToSpeedNoUnit(val, gpxvcomp.measureunit))
		})
		$('table#stattable tr[stat=total_uphill] td:not(.statnamecol)').each(function() {
			const val = $(this).text()
			$(this).text(metersToElevationNoUnit(val, gpxvcomp.measureunit))
		})
		$('table#stattable tr[stat=total_downhill] td:not(.statnamecol)').each(function() {
			const val = $(this).text()
			$(this).text(metersToElevationNoUnit(val, gpxvcomp.measureunit))
		})

		main()
	}

	function main() {
		const mytz = myjstz.determine_timezone()
		gpxvcomp.mytzname = mytz.timezone.olson_tz
		loadMap()
		// $('#stattable').tablesorter()
		$('select#gpxselect').change(function() {
			drawResults()
		})
		$('select#criteria').change(function() {
			drawResults()
		})
		$('select#gpxselect').change()

		$('button.addFile').click(function(e) {
			e.preventDefault()
			addFileInput()
		})
		$('body').on('click', '.rmFile', function(e) {
			e.preventDefault()
			rmFileInput($(this).parent())
		})

		document.onkeydown = checkKey

		let buttonColor = 'blue'
		if (OCA.Theming) {
			buttonColor = OCA.Theming.color
		}

		$('<style role="buttons">.fa { '
			+ 'color: ' + buttonColor + '; }</style>').appendTo('body')
	}

	document.addEventListener('DOMContentLoaded', (event) => {
		getMeasureUnit()
	})

})($, OC)
