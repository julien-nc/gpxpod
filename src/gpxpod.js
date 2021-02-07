import $ from 'jquery'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import 'leaflet-dialog/Leaflet.Dialog'
import 'leaflet-dialog/Leaflet.Dialog.css'
import 'd3'
import 'sorttable/sorttable'
import marker from 'leaflet/dist/images/marker-icon.png'
import marker2x from 'leaflet/dist/images/marker-icon-2x.png'
import markerShadow from 'leaflet/dist/images/marker-shadow.png'
import 'mapbox-gl/dist/mapbox-gl'
import 'mapbox-gl/dist/mapbox-gl.css'
import 'mapbox-gl-leaflet/leaflet-mapbox-gl'
// import '@fortawesome/fontawesome-free/css/all.min.css'
import 'leaflet.locatecontrol/dist/L.Control.Locate.min'
import 'leaflet.locatecontrol/dist/L.Control.Locate.min.css'
import 'leaflet-mouse-position/src/L.Control.MousePosition'
import 'leaflet-mouse-position/src/L.Control.MousePosition.css'
import 'leaflet-easybutton/src/easy-button'
import 'leaflet-easybutton/src/easy-button.css'
import 'leaflet-polylinedecorator/dist/leaflet.polylineDecorator'
import 'leaflet-sidebar-v2/js/leaflet-sidebar.min'
import 'leaflet-sidebar-v2/css/leaflet-sidebar.min.css'
import 'leaflet-linear-measurement/src/Leaflet.LinearMeasurement'
import 'leaflet-linear-measurement/sass/Leaflet.LinearMeasurement.scss'
import 'leaflet-hotline/dist/leaflet.hotline.min'
import 'leaflet.markercluster/dist/leaflet.markercluster'
import 'leaflet.markercluster/dist/MarkerCluster.css'
import 'leaflet.markercluster/dist/MarkerCluster.Default.css'
// import 'jstz/dist/jstz.min'
import 'npm-overlapping-marker-spiderfier/lib/oms.min'
import './L.Control.Elevation'

import myjstz from './detect_timezone'
import moment from 'moment-timezone'
import { basename, dirname } from '@nextcloud/paths'
import axios from '@nextcloud/axios'

import { generateUrl } from '@nextcloud/router'
import { loadState } from '@nextcloud/initial-state'

import {
	METERSTOFOOT,
	METERSTOMILES,
	METERSTONAUTICALMILES,
	formatDuration,
	minPerKmToPace,
	kmphToSpeed,
	metersToDistance,
	metersToDistanceNoAdaptNoUnit,
	metersToElevation,
	metersToElevationNoUnit,
	brify,
	hexToRgb,
	escapeHtml,
} from './utils'

// eslint-disable-next-line
(($, OC) => {
	'use strict'
	delete L.Icon.Default.prototype._getIconUrl
	L.Icon.Default.mergeOptions({
		iconRetinaUrl: marker2x,
		iconUrl: marker,
		shadowUrl: markerShadow,
	})

	/// ///////////// VAR DEFINITION /////////////////////

	const colors = [
		'red', 'cyan', 'purple', 'Lime', 'yellow',
		'orange', 'blue', 'brown', 'Chartreuse',
		'Crimson', 'DeepPink', 'Gold',
	]
	const colorCode = {
		red: '#ff0000',
		cyan: '#00ffff',
		purple: '#800080',
		Lime: '#00ff00',
		yellow: '#ffff00',
		orange: '#ffa500',
		blue: '#0000ff',
		brown: '#a52a2a',
		Chartreuse: '#7fff00',
		Crimson: '#dc143c',
		DeepPink: '#ff1493',
		Gold: '#ffd700',
	}
	let lastColorUsed = -1
	const gpxpod = {
		map: {},
		baseLayers: null,
		overlayLayers: null,
		restoredTileLayer: null,
		markers: {},
		markersPopupTxt: {},
		markerLayer: null,
		// layers currently displayed, indexed by track name
		gpxlayers: {},
		gpxCache: {},
		subfolder: '',
		// layer of current elevation chart
		elevationLayer: null,
		// track concerned by elevation
		elevationTrackId: null,
		minimapControl: null,
		sort: { col: 3, desc: true },
		currentHoverLayer: null,
		currentHoverLayerOutlines: L.layerGroup(),
		currentHoverSource: null,
		// dict indexed by track names containing running ajax (for tracks)
		// this dict is used in updateTrackListFromBounds to show spinner or checkbox in first td
		currentAjaxSources: {},
		// to store the ajax progress percentage
		currentAjaxPercentage: {},
		currentMarkerAjax: null,
		currentlyCorrecting: false,
		// as tracks are retrieved by ajax, there's a lapse between mousein event
		// on table rows and track overview display, if mouseout was triggered
		// during this lapse, track was displayed anyway. i solve it by keeping
		// this prop up to date and drawing ajax result just if its value is true
		insideTr: false,
		points: {},
		isPhotosInstalled: loadState('gpxpod', 'photos'),
	}

	// const darkIcon = L.Icon.Default.extend({ options: { iconUrl: 'marker-desat.png' } })

	const hoverStyle = {
		weight: 12,
		opacity: 0.7,
		color: 'black',
	}
	const defaultStyle = {
		weight: 5,
		opacity: 1,
	}

	const PHOTO_MARKER_VIEW_SIZE = 40

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

	const LAT = 0
	const LON = 1
	const FOLDER = 2
	const NAME = 3
	const TOTAL_DISTANCE = 4
	const TOTAL_DURATION = 5
	const DATE_BEGIN = 6
	const DATE_END = 7
	const POSITIVE_ELEVATION_GAIN = 8
	const NEGATIVE_ELEVATION_GAIN = 9
	const MIN_ELEVATION = 10
	const MAX_ELEVATION = 11
	const MAX_SPEED = 12
	const AVERAGE_SPEED = 13
	const MOVING_TIME = 14
	const STOPPED_TIME = 15
	const MOVING_AVERAGE_SPEED = 16
	const NORTH = 17
	const SOUTH = 18
	const EAST = 19
	const WEST = 20
	const SHORTPOINTLIST = 21
	const TRACKNAMELIST = 22
	const LINKURL = 23
	const LINKTEXT = 24
	const MOVING_PACE = 25

	const symbolSelectClasses = {
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
		Residence: 'residence-select',
		'Drinking Water': 'drinking-water-select',
		'Trail Head': 'hike-select',
		'Bike Trail': 'bike-trail-select',
		Campground: 'campground-select',
		Bar: 'bar-select',
		'Skull and Crossbones': 'skullcross-select',
		Geocache: 'geocache-select',
		'Geocache Found': 'geocache-open-select',
		'Medical Facility': 'medical-select',
		'Contact, Alien': 'contact-alien-select',
		'Contact, Big Ears': 'contact-bigears-select',
		'Contact, Female3': 'contact-female3-select',
		'Contact, Cat': 'contact-cat-select',
		'Contact, Dog': 'contact-dog-select',
	}

	const symbolIcons = {
		'Dot, White': L.divIcon({
			iconSize: L.point(7, 7),
		}),
		'Pin, Blue': L.divIcon({
			className: 'pin-blue',
			iconAnchor: [5, 30],
		}),
		'Pin, Green': L.divIcon({
			className: 'pin-green',
			iconAnchor: [5, 30],
		}),
		'Pin, Red': L.divIcon({
			className: 'pin-red',
			iconAnchor: [5, 30],
		}),
		'Flag, Green': L.divIcon({
			className: 'flag-green',
			iconAnchor: [1, 25],
		}),
		'Flag, Red': L.divIcon({
			className: 'flag-red',
			iconAnchor: [1, 25],
		}),
		'Flag, Blue': L.divIcon({
			className: 'flag-blue',
			iconAnchor: [1, 25],
		}),
		'Block, Blue': L.divIcon({
			className: 'block-blue',
			iconAnchor: [8, 8],
		}),
		'Block, Green': L.divIcon({
			className: 'block-green',
			iconAnchor: [8, 8],
		}),
		'Block, Red': L.divIcon({
			className: 'block-red',
			iconAnchor: [8, 8],
		}),
		'Blue Diamond': L.divIcon({
			className: 'diamond-blue',
			iconAnchor: [9, 9],
		}),
		'Green Diamond': L.divIcon({
			className: 'diamond-green',
			iconAnchor: [9, 9],
		}),
		'Red Diamond': L.divIcon({
			className: 'diamond-red',
			iconAnchor: [9, 9],
		}),
		Residence: L.divIcon({
			className: 'residence',
			iconAnchor: [12, 12],
		}),
		'Drinking Water': L.divIcon({
			className: 'drinking-water',
			iconAnchor: [12, 12],
		}),
		'Trail Head': L.divIcon({
			className: 'hike',
			iconAnchor: [12, 12],
		}),
		'Bike Trail': L.divIcon({
			className: 'bike-trail',
			iconAnchor: [12, 12],
		}),
		Campground: L.divIcon({
			className: 'campground',
			iconAnchor: [12, 12],
		}),
		Bar: L.divIcon({
			className: 'bar',
			iconAnchor: [10, 12],
		}),
		'Skull and Crossbones': L.divIcon({
			className: 'skullcross',
			iconAnchor: [12, 12],
		}),
		Geocache: L.divIcon({
			className: 'geocache',
			iconAnchor: [11, 10],
		}),
		'Geocache Found': L.divIcon({
			className: 'geocache-open',
			iconAnchor: [11, 10],
		}),
		'Medical Facility': L.divIcon({
			className: 'medical',
			iconAnchor: [13, 11],
		}),
		'Contact, Alien': L.divIcon({
			className: 'contact-alien',
			iconAnchor: [12, 12],
		}),
		'Contact, Big Ears': L.divIcon({
			className: 'contact-bigears',
			iconAnchor: [12, 12],
		}),
		'Contact, Female3': L.divIcon({
			className: 'contact-female3',
			iconAnchor: [12, 12],
		}),
		'Contact, Cat': L.divIcon({
			className: 'contact-cat',
			iconAnchor: [12, 12],
		}),
		'Contact, Dog': L.divIcon({
			className: 'contact-dog',
			iconAnchor: [12, 12],
		}),
	}

	function getPhotoMarkerOnClickFunction() {
		return function(evt) {
			const marker = evt.layer
			let galleryUrl
			if (marker.data.token) {
				let subpath = marker.data.pubsubpath
				subpath = subpath.replace(/^\//, '')
				if (subpath !== '') {
					subpath += '/'
				}
				if (!gpxpod.isPhotosInstalled) {
					galleryUrl = generateUrl('/apps/gallery/s/' + marker.data.token + '#'
								+ encodeURIComponent(subpath + basename(marker.data.path)))
				} else {
					galleryUrl = generateUrl('/s/' + marker.data.token)
					if (subpath !== '/' && subpath !== '') {
						galleryUrl = galleryUrl + '?' + $.param({ path: subpath })
					}
				}
				const win = window.open(galleryUrl, '_blank')
				if (win) {
					win.focus()
				}
			} else {
				// use Viewer app if available and recent enough to provide standalone viewer
				if (!pageIsPublicFileOrFolder() && OCA.Viewer && OCA.Viewer.open) {
					OCA.Viewer.open(marker.data.path)
				} else {
					if (gpxpod.isPhotosInstalled) {
						const dir = dirname(marker.data.path)
						galleryUrl = generateUrl('/apps/photos/albums/' + dir.replace(/^\//, ''))
					} else {
						galleryUrl = generateUrl('/apps/gallery/#' + encodeURIComponent(marker.data.path.replace(/^\//, '')))
					}
					const win = window.open(galleryUrl, '_blank')
					if (win) {
						win.focus()
					}
				}
			}
		}
	}

	function getClusterIconCreateFunction() {
		return function(cluster) {
			const marker = cluster.getAllChildMarkers()[0].data
			let iconUrl
			if (marker.hasPreview) {
				iconUrl = generatePreviewUrl(marker)
			} else {
				iconUrl = getImageIconUrl()
			}
			const label = cluster.getChildCount()
			return new L.DivIcon(L.extend({
				className: 'leaflet-marker-photo cluster-marker',
				html: '<div class="thumbnail" style="background-image: url(' + iconUrl + ');">'
					  + '</div>​<span class="label">' + label + '</span>',
			}, this.icon))
		}
	}

	function generatePreviewUrl(markerData) {
		if (markerData.token) {
			// pub folder
			const filename = basename(markerData.path)
			const previewParams = {
				file: markerData.pubsubpath + '/' + filename,
				x: 341,
				y: 256,
				a: 1,
			}
			const previewUrl = generateUrl('/apps/files_sharing/publicpreview/' + markerData.token + '?')
			const smallpurl = previewUrl + $.param(previewParams)
			return smallpurl
		} else {
			// normal page
			return generateUrl('core') + '/preview?fileId=' + markerData.fileId + '&x=341&y=256&a=1'
		}
	}

	function getImageIconUrl() {
		return generateUrl('/apps/theming/img/core/filetypes') + '/image.svg?v=2'
	}

	function createPhotoView(markerData) {
		let iconUrl
		if (markerData.hasPreview) {
			iconUrl = generatePreviewUrl(markerData)
		} else {
			iconUrl = getImageIconUrl()
		}
		return L.divIcon(L.extend({
			html: '<div class="thumbnail" style="background-image: url(' + iconUrl + ');"></div>​',
			className: 'leaflet-marker-photo photo-marker',
		}, markerData, {
			iconSize: [PHOTO_MARKER_VIEW_SIZE, PHOTO_MARKER_VIEW_SIZE],
			iconAnchor: [PHOTO_MARKER_VIEW_SIZE / 2, PHOTO_MARKER_VIEW_SIZE],
		}))
	}

	function lineOver(e) {
		if (gpxpod.overMarker) {
			gpxpod.overMarker.remove()
		}
		// console.log(e.target)
		// console.log(e.layer.tid)
		const tid = e.target.tid
		const li = e.target.li
		const overLatLng = gpxpod.map.layerPointToLatLng(e.layerPoint)
		let minDist = 40000000
		let markerLatLng = null
		let markerTime = null
		let markerExts = {}
		let tmpDist
		let j, jmin
		jmin = -1
		const segmentLatlngs = gpxpod.points[tid][li].coords
		const segmentTimes = gpxpod.points[tid][li].times
		const segmentExts = gpxpod.points[tid][li].exts
		for (j = 0; j < segmentLatlngs.length; j++) {
			tmpDist = gpxpod.map.distance(overLatLng, L.latLng(segmentLatlngs[j]))
			if (tmpDist < minDist) {
				jmin = j
				minDist = tmpDist
			}
		}
		if (jmin < segmentLatlngs.length) {
			markerLatLng = segmentLatlngs[jmin]
		}
		if (jmin < segmentTimes.length) {
			markerTime = segmentTimes[jmin]
		}
		if (jmin < segmentExts.length) {
			markerExts = segmentExts[jmin]
		}
		// draw it
		const radius = 8
		const shape = 'r'
		const pointIcon = L.divIcon({
			iconAnchor: [radius, radius],
			className: shape + 'marker color' + tid,
			html: '',
		})
		gpxpod.overMarker = L.marker(
			markerLatLng, {
				icon: pointIcon,
			}
		)
		// tooltip
		let tooltipContent = ''
		if (markerTime) {
			const chosentz = $('#tzselect').val()
			const mom = moment(markerTime)
			mom.tz(chosentz)
			const fdate = mom.format('YYYY-MM-DD HH:mm:ss (Z)')
			tooltipContent += fdate
		}
		if (markerLatLng.length > 2) {
			const colorcriteria = $('#colorcriteria').val()
			if (colorcriteria === 'none' || colorcriteria === 'elevation') {
				tooltipContent += '<br/>' + t('gpxpod', 'Elevation') + ' : ' + parseFloat(markerLatLng[2]).toFixed(2) + ' m'
			} else if (colorcriteria === 'speed') {
				tooltipContent += '<br/>' + t('gpxpod', 'Calculated speed') + ' : ' + parseFloat(markerLatLng[2]).toFixed(2) + ' km/h'
			} else if (colorcriteria === 'pace') {
				tooltipContent += '<br/>' + t('gpxpod', 'Pace') + ' : ' + parseFloat(markerLatLng[2]).toFixed(2) + ' min/km'
			} else if (colorcriteria === 'extension') {
				tooltipContent += '<br/>' + $('#colorcriteriaext').val() + ' : ' + parseFloat(markerLatLng[2]).toFixed(2)
			}
		}
		for (const e in markerExts) {
			// convert speed
			if (e === 'speed') {
				const speed = parseFloat(markerExts[e]) / 1000 * 3600
				tooltipContent += '<br/>' + t('gpxpod', 'GPS speed') + ' : ' + speed.toFixed(2) + ' km/h'
			} else {
				tooltipContent += '<br/>' + e + ' : ' + markerExts[e]
			}
		}
		gpxpod.overMarker.bindTooltip(tooltipContent, { className: 'mytooltip tooltip' + tid })
		gpxpod.map.addLayer(gpxpod.overMarker)
		gpxpod.overMarker.dragging.disable()
	}

	function lineOut(e) {

	}

	/// ///////////// MAP /////////////////////

	function loadMap() {
		const layer = getUrlParameter('layer')
		let defaultLayer = 'OpenStreetMap'
		if (gpxpod.restoredTileLayer !== null) {
			defaultLayer = gpxpod.restoredTileLayer
		} else if (typeof layer !== 'undefined') {
			defaultLayer = layer
		}

		const overlays = []

		const baseLayers = {}

		// add base layers
		$('#basetileservers li[type=tile], #basetileservers li[type=mapbox]').each(function() {
			const sname = $(this).attr('name')
			const surl = $(this).attr('url')
			const minz = parseInt($(this).attr('minzoom'))
			const maxz = parseInt($(this).attr('maxzoom'))
			const sattrib = $(this).attr('attribution')
			const stransparent = ($(this).attr('transparent') === 'true')
			let sopacity = $(this).attr('opacity')
			if (typeof sopacity !== typeof undefined && sopacity !== false && sopacity !== '') {
				sopacity = parseFloat(sopacity)
			} else {
				sopacity = 1
			}

			const type = $(this).attr('type')
			if (type === 'tile') {
				baseLayers[sname] = new L.TileLayer(surl, {
					minZoom: minz,
					maxZoom: maxz,
					attribution: sattrib,
					opacity: sopacity,
					transparent: stransparent,
				})
			} else if (type === 'mapbox') {
				const token = $(this).attr('token')
				baseLayers[sname] = L.mapboxGL({
					accessToken: token || 'token',
					style: surl,
					minZoom: minz || 1,
					maxZoom: maxz || 22,
					attribution: sattrib,
				})
			}
		})
		$('#basetileservers li[type=tilewms]').each(function() {
			const sname = $(this).attr('name')
			const surl = $(this).attr('url')
			const slayers = $(this).attr('layers') || ''
			const sversion = $(this).attr('version') || '1.1.1'
			const stransparent = ($(this).attr('transparent') === 'true')
			const sformat = $(this).attr('format') || 'image/png'
			let sopacity = $(this).attr('opacity')
			if (typeof sopacity !== typeof undefined && sopacity !== false && sopacity !== '') {
				sopacity = parseFloat(sopacity)
			} else {
				sopacity = 1
			}
			const sattrib = $(this).attr('attribution') || ''
			// eslint-disable-next-line
			baseLayers[sname] = new L.tileLayer.wms(surl, { layers: slayers, version: sversion, transparent: stransparent, opacity: sopacity, format: sformat, attribution: sattrib })
		})
		// add custom layers
		$('#tileserverlist li').each(function() {
			const sname = $(this).attr('servername')
			const surl = $(this).attr('url')
			const sminzoom = $(this).attr('minzoom') || '1'
			const smaxzoom = $(this).attr('maxzoom') || '20'
			const sattrib = $(this).attr('attribution') || ''
			baseLayers[sname] = new L.TileLayer(surl,
				{ minZoom: sminzoom, maxZoom: smaxzoom, attribution: sattrib })
		})
		$('#mapboxtileserverlist li').each(function() {
			const sname = $(this).attr('servername')
			const surl = $(this).attr('url')
			const token = $(this).attr('token')
			const sattrib = $(this).attr('attribution') || ''
			baseLayers[sname] = L.mapboxGL({
				accessToken: token || 'token',
				style: surl,
				minZoom: 1,
				maxZoom: 22,
				attribution: sattrib,
			})
		})
		$('#tilewmsserverlist li').each(function() {
			const sname = $(this).attr('servername')
			const surl = $(this).attr('url')
			const sminzoom = $(this).attr('minzoom') || '1'
			const smaxzoom = $(this).attr('maxzoom') || '20'
			const slayers = $(this).attr('layers') || ''
			const sversion = $(this).attr('version') || '1.1.1'
			const sformat = $(this).attr('format') || 'image/png'
			const sattrib = $(this).attr('attribution') || ''
			// eslint-disable-next-line
			baseLayers[sname] = new L.tileLayer.wms(surl,
				{ format: sformat, version: sversion, layers: slayers, minZoom: sminzoom, maxZoom: smaxzoom, attribution: sattrib })
		})
		gpxpod.baseLayers = baseLayers

		const baseOverlays = {}

		// add base overlays
		$('#basetileservers li[type=overlay]').each(function() {
			const sname = $(this).attr('name')
			const surl = $(this).attr('url')
			const minz = parseInt($(this).attr('minzoom'))
			const maxz = parseInt($(this).attr('maxzoom'))
			const sattrib = $(this).attr('attribution')
			const stransparent = ($(this).attr('transparent') === 'true')
			let sopacity = $(this).attr('opacity')
			if (typeof sopacity !== typeof undefined && sopacity !== false && sopacity !== '') {
				sopacity = parseFloat(sopacity)
			} else {
				sopacity = 0.4
			}
			baseOverlays[sname] = new L.TileLayer(surl, { minZoom: minz, maxZoom: maxz, attribution: sattrib, opacity: sopacity, transparent: stransparent })
		})
		$('#basetileservers li[type=overlaywms]').each(function() {
			const sname = $(this).attr('name')
			const surl = $(this).attr('url')
			const slayers = $(this).attr('layers') || ''
			const sversion = $(this).attr('version') || '1.1.1'
			const stransparent = ($(this).attr('transparent') === 'true')
			let sopacity = $(this).attr('opacity')
			if (typeof sopacity !== typeof undefined && sopacity !== false && sopacity !== '') {
				sopacity = parseFloat(sopacity)
			} else {
				sopacity = 0.4
			}
			const sformat = $(this).attr('format') || 'image/png'
			const sattrib = $(this).attr('attribution') || ''
			// eslint-disable-next-line
			baseOverlays[sname] = new L.tileLayer.wms(surl, { layers: slayers, version: sversion, transparent: stransparent, opacity: sopacity, format: sformat, attribution: sattrib })
		})
		// add custom overlays
		$('#overlayserverlist li').each(function() {
			const sname = $(this).attr('servername')
			const surl = $(this).attr('url')
			const sminzoom = $(this).attr('minzoom') || '1'
			const smaxzoom = $(this).attr('maxzoom') || '20'
			const stransparent = ($(this).attr('transparent') === 'true')
			let sopacity = $(this).attr('opacity')
			if (typeof sopacity !== typeof undefined && sopacity !== false && sopacity !== '') {
				sopacity = parseFloat(sopacity)
			} else {
				sopacity = 0.4
			}
			const sattrib = $(this).attr('attribution') || ''
			baseOverlays[sname] = new L.TileLayer(surl,
				{ minZoom: sminzoom, maxZoom: smaxzoom, transparent: stransparent, opcacity: sopacity, attribution: sattrib })
		})
		$('#overlaywmsserverlist li').each(function() {
			const sname = $(this).attr('servername')
			const surl = $(this).attr('url')
			const sminzoom = $(this).attr('minzoom') || '1'
			const smaxzoom = $(this).attr('maxzoom') || '20'
			const slayers = $(this).attr('layers') || ''
			const sversion = $(this).attr('version') || '1.1.1'
			const sformat = $(this).attr('format') || 'image/png'
			const stransparent = ($(this).attr('transparent') === 'true')
			let sopacity = $(this).attr('opacity')
			if (typeof sopacity !== typeof undefined && sopacity !== false && sopacity !== '') {
				sopacity = parseFloat(sopacity)
			} else {
				sopacity = 0.4
			}
			const sattrib = $(this).attr('attribution') || ''
			// eslint-disable-next-line
			baseOverlays[sname] = new L.tileLayer.wms(surl, { layers: slayers, version: sversion, transparent: stransparent, opacity: sopacity, format: sformat, attribution: sattrib, minZoom: sminzoom, maxZoom: smaxzoom })
		})
		gpxpod.overlayLayers = baseOverlays

		gpxpod.map = new L.Map('map', {
			zoomControl: true,
			// this is set because leaflet-mapbox-gl tileLayer does not set it...
			// so now, tileLayer maxZoom is ignored and we can zoom too much
			maxZoom: 22,
			minZoom: 2,
		})

		const notificationText = '<div id="loadingnotification">'
			+ '<span id="stackgroup" class="fa-stack fa-2x">'
			+ '<i id="spinload" class="fa fa-spinner fa-pulse fa-stack-1x"></i>'
			+ '<i id="folderload" class="far fa-folder-open fa-stack-1x"></i>'
			+ '<i id="deleteload" class="far fa-trash-alt fa-stack-1x"></i>'
			+ '<i id="trackload" class="fas fa-chart-line fa-stack-1x"></i>'
			+ '<i id="correctload" class="far fa-chart-area fa-stack-1x"></i>'
			+ '</span>'
			+ '<b id="loadingpc"></b></div>'
		gpxpod.notificationDialog = L.control.dialog({
			anchor: [0, -65],
			position: 'topright',
			size: [55, 55],
		}).setContent(notificationText)

		// picture spiderfication
		gpxpod.oms = L.markerClusterGroup({
			iconCreateFunction: getClusterIconCreateFunction(),
			spiderfyOnMaxZoom: false,
			showCoverageOnHover: false,
			zoomToBoundsOnClick: false,
			maxClusterRadius: PHOTO_MARKER_VIEW_SIZE + 10,
			icon: {
				iconSize: [PHOTO_MARKER_VIEW_SIZE, PHOTO_MARKER_VIEW_SIZE],
			},
		})
		gpxpod.oms.on('click', getPhotoMarkerOnClickFunction())
		gpxpod.oms.on('clusterclick', function(a) {
			if (a.layer.getChildCount() > 20 && gpxpod.map.getZoom() !== gpxpod.map.getMaxZoom()) {
				a.layer.zoomToBounds()
			} else {
				a.layer.spiderfy()
			}
		})

		L.control.scale({ metric: true, imperial: true, position: 'topleft' })
			.addTo(gpxpod.map)

		L.control.mousePosition().addTo(gpxpod.map)
		gpxpod.locateControl = L.control.locate({ follow: true })
		gpxpod.locateControl.addTo(gpxpod.map)
		$('.leaflet-control-locate span').removeClass('fa-map-marker').addClass('fa-map-marker-alt')
		gpxpod.map.addControl(new L.Control.LinearMeasurement({
			unitSystem: 'metric',
			color: '#FF0080',
			type: 'line',
		}))
		$('a.icon-ruler').addClass('fa fa-ruler')
		L.control.sidebar('sidebar').addTo(gpxpod.map)
		if (pageIsPublicFileOrFolder()) {
			const showSidebar = getUrlParameter('sidebar')
			if (showSidebar === '0') {
				$('#sidebar').toggleClass('collapsed')
			}
		}

		gpxpod.map.setView(new L.LatLng(27, 5), 3)

		if (!(defaultLayer in baseLayers)) {
			defaultLayer = 'OpenStreetMap'
		}
		gpxpod.map.addLayer(baseLayers[defaultLayer])
		gpxpod.currentLayerName = defaultLayer

		gpxpod.controlLayers = L.control.layers(baseLayers, baseOverlays)
		gpxpod.controlLayers.addTo(gpxpod.map)

		for (const ii in overlays) {
			gpxpod.map.addLayer(baseOverlays[overlays[ii]])
		}

		// close elevation chart button
		gpxpod.closeElevationButton = L.easyButton({
			position: 'bottomleft',
			states: [{
				stateName: 'no-importa',
				icon: 'fa-times',
				title: t('gpxpod', 'Close elevation chart'),
				onClick(btn, map) {
					removeElevation()
				},
			}],
		})

		// would fix overlays tiles displayed behind mapbox
		// BUT it also draws lines behind tiles
		// gpxpod.map.getPanes().tilePane.style.zIndex = 499
		// console.log(gpxpod.map.getPanes())

		// gpxpod.map.on('contextmenu',rightClick)
		// gpxpod.map.on('popupclose',function() {})
		// gpxpod.map.on('viewreset',updateTrackListFromBounds)
		// gpxpod.map.on('dragend',updateTrackListFromBounds)
		gpxpod.map.on('moveend', updateTrackListFromBounds)
		gpxpod.map.on('zoomend', updateTrackListFromBounds)
		gpxpod.map.on('baselayerchange', function(e) {
			gpxpod.currentLayerName = e.name
			updateTrackListFromBounds()
			if (!pageIsPublicFileOrFolder()) {
				saveOptionTileLayer()
			}
		})
		gpxpod.map.on('click', function(e) {
			if (gpxpod.overMarker) {
				gpxpod.overMarker.remove()
			}
		})
	}

	// function rightClick(e) {
	//    //new L.popup()
	//    //    .setLatLng(e.latlng)
	//    //    .setContent(preparepopup(e.latlng.lat,e.latlng.lng))
	//    //    .openOn(gpxpod.map)
	// }

	function removeElevation() {
		// clean other elevation
		if (gpxpod.elevationLayer !== null) {
			gpxpod.map.removeControl(gpxpod.elevationLayer)
			delete gpxpod.elevationLayer
			gpxpod.elevationLayer = null
			delete gpxpod.elevationTrackId
			gpxpod.elevationTrackId = null
			gpxpod.closeElevationButton.remove()
			$('#hover-timestamp').remove()
		}
	}

	function zoomOnAllDrawnTracks() {
		let b
		// get bounds of first layer
		const layerKeys = Object.keys(gpxpod.gpxlayers)
		if (layerKeys.length > 0) {
			b = L.latLngBounds(
				gpxpod.gpxlayers[layerKeys[0]].layer.getBounds().getSouthWest(),
				gpxpod.gpxlayers[layerKeys[0]].layer.getBounds().getNorthEast()
			)
			// then extend to other bounds
			for (const k in gpxpod.gpxlayers) {
				b.extend(gpxpod.gpxlayers[k].layer.getBounds())
			}
			// zoom
			if (b.isValid()) {
				let xoffset = parseInt($('#sidebar').css('width'))
				if (pageIsPublicFileOrFolder()) {
					const showSidebar = getUrlParameter('sidebar')
					if (showSidebar === '0') {
						xoffset = 0
					}
				}
				gpxpod.map.fitBounds(b, {
					animate: true,
					paddingTopLeft: [xoffset, 100],
					paddingBottomRight: [100, 100],
				})
			}
		}
	}

	function zoomOnAllMarkers() {
		const trackIds = Object.keys(gpxpod.markers)
		const picLayers = gpxpod.oms.getLayers()
		const showPics = $('#showpicscheck').is(':checked')

		if (trackIds.length > 0 || (picLayers.length > 0 && showPics)) {
			let i, ll, m, north, south, east, west
			if (trackIds.length > 0) {
				north = gpxpod.markers[trackIds[0]][LAT]
				south = gpxpod.markers[trackIds[0]][LAT]
				east = gpxpod.markers[trackIds[0]][LON]
				west = gpxpod.markers[trackIds[0]][LON]
			}
			for (i = 1; i < trackIds.length; i++) {
				m = gpxpod.markers[trackIds[i]]
				if (m[LAT] > north) {
					north = m[LAT]
				}
				if (m[LAT] < south) {
					south = m[LAT]
				}
				if (m[LON] < west) {
					west = m[LON]
				}
				if (m[LON] > east) {
					east = m[LON]
				}
			}
			if (picLayers.length > 0 && showPics) {
				// init n,s,e,w if it hasn't been done
				if (trackIds.length === 0) {
					m = picLayers[0]
					ll = m.getLatLng()
					north = ll.lat
					south = ll.lat
					west = ll.lng
					east = ll.lng
				}
				for (i = 0; i < picLayers.length; i++) {
					m = picLayers[i]
					ll = m.getLatLng()
					if (ll.lat > north) {
						north = ll.lat
					}
					if (ll.lat < south) {
						south = ll.lat
					}
					if (ll.lng < west) {
						west = ll.lng
					}
					if (ll.lng > east) {
						east = ll.lng
					}
				}
			}
			const b = L.latLngBounds([south, west], [north, east])
			if (b.isValid()) {
				let xoffset = parseInt($('#sidebar').css('width'))
				if (pageIsPublicFileOrFolder()) {
					const showSidebar = getUrlParameter('sidebar')
					if (showSidebar === '0') {
						xoffset = 0
					}
				}
				gpxpod.map.fitBounds([[south, west], [north, east]], {
					animate: true,
					paddingTopLeft: [xoffset, 100],
					paddingBottomRight: [100, 100],
				}
				)
			}
		}
	}

	/*
	 * returns true if at least one point of the track is
	 * inside the map bounds
	 */
	function trackCrossesMapBounds(shortPointList, mapb) {
		if (typeof shortPointList !== 'undefined') {
			for (let i = 0; i < shortPointList.length; i++) {
				const p = shortPointList[i]
				if (mapb.contains(new L.LatLng(p[0], p[1]))) {
					return true
				}
			}
		}
		return false
	}

	/// ///////////// MARKERS /////////////////////

	/*
	 * display markers if the checkbox is checked
	 */
	function redrawMarkers() {
		// remove markers if they are present
		removeMarkers()
		addMarkers()

	}

	function removeMarkers() {
		if (gpxpod.markerLayer !== null) {
			gpxpod.map.removeLayer(gpxpod.markerLayer)
			delete gpxpod.markerLayer
			gpxpod.markerLayer = null
		}
	}

	// add markers respecting the filtering rules
	function addMarkers() {
		const markerclu = L.markerClusterGroup({ chunkedLoading: true })
		let a, marker
		for (const tid in gpxpod.markers) {
			a = gpxpod.markers[tid]
			if (filter(a)) {
				marker = L.marker(L.latLng(a[LAT], a[LON]))
				marker.tid = tid
				marker.bindPopup(
					gpxpod.markersPopupTxt[tid].popup,
					{
						autoPan: true,
						autoClose: true,
						closeOnClick: true,
					}
				)
				marker.bindTooltip(decodeURIComponent(a[NAME]), { className: 'mytooltip' })
				marker.on('mouseover', function(e) {
					if (!gpxpod.currentlyCorrecting) {
						gpxpod.insideTr = true
						displayOnHover(e.target.tid)
					}
				})
				marker.on('mouseout', function() {
					if (gpxpod.currentHoverSource !== null) {
						gpxpod.currentHoverSource.cancel()
						gpxpod.currentHoverSource = null
						hideAnimation()
					}
					gpxpod.insideTr = false
					deleteOnHover()
				})
				gpxpod.markersPopupTxt[tid].marker = marker
				markerclu.addLayer(marker)
			}
		}

		if ($('#displayclusters').is(':checked')) {
			gpxpod.map.addLayer(markerclu)
		}
		// gpxpod.map.setView(new L.LatLng(47, 3), 2)

		gpxpod.markerLayer = markerclu

		// markers.on('clusterclick', function (a) {
		//   var bounds = a.layer.getConvexHull()
		//   updateTrackListFromBounds(bounds)
		// })
	}

	function genPopupTxt() {
		let dlUrl
		const unit = $('#measureunitselect').val()
		gpxpod.markersPopupTxt = {}
		const chosentz = $('#tzselect').val()
		let url = generateUrl('/apps/files/ajax/download.php')
		// if this is a public link, the url is the public share
		if (pageIsPublicFileOrFolder()) {
			url = generateUrl('/s/' + gpxpod.token)
		}
		for (const id in gpxpod.markers) {
			const a = gpxpod.markers[id]
			const name = decodeURIComponent(a[NAME])
			const folder = decodeURIComponent(a[FOLDER])
			const path = folder.replace(/^\/$/, '') + '/' + name

			if (pageIsPublicFolder()) {
				let subpath = getUrlParameter('path')
				if (subpath === undefined) {
					subpath = '/'
				}
				dlUrl = '"' + url.split('?')[0] + '/download?path=' + encodeURIComponent(subpath)
					+ '&files=' + encodeURIComponent(name) + '" target="_blank"'
			} else if (pageIsPublicFile()) {
				dlUrl = '"' + url + '" target="_blank"'
			} else {
				dlUrl = '"' + url + '?dir=' + encodeURIComponent(folder) + '&files=' + encodeURIComponent(name) + '"'
			}

			let popupTxt = '<h3 class="popupTitle">'
				+ t('gpxpod', 'File') + ' : <a href='
				+ dlUrl + ' title="' + t('gpxpod', 'download') + '" class="getGpx" >'
				+ '<i class="fa fa-cloud-download-alt" aria-hidden="true"></i> ' + escapeHtml(name) + '</a> '
			if (!pageIsPublicFileOrFolder()) {
				popupTxt = popupTxt + '<a class="publink" type="track" tid="' + id + '" '
						   + 'href="" target="_blank" title="'
						   + escapeHtml(t('gpxpod', 'This public link will work only if \'{title}\' or one of its parent folder is shared in \'files\' app by public link without password', { title: path }))
						   + '">'
						   + '<i class="fa fa-share-alt" aria-hidden="true"></i>'
						   + '</a>'
			}
			popupTxt = popupTxt + '</h3>'
			popupTxt = popupTxt + '<button class="drawButton" tid="' + id + '">'
				+ '<i class="fa fa-pencil-alt" aria-hidden="true"></i> ' + t('gpxpod', 'Draw track') + '</button>'
			// link url and text
			if (a.length >= LINKTEXT && a[LINKURL]) {
				let lt = a[LINKTEXT]
				if (!lt) {
					lt = t('gpxpod', 'metadata link')
				}
				popupTxt = popupTxt + '<a class="metadatalink" title="'
					+ t('gpxpod', 'metadata link') + '" href="' + a[LINKURL]
					+ '" target="_blank">' + lt + '</a>'
			}
			if (a.length >= TRACKNAMELIST + 1) {
				popupTxt = popupTxt + '<ul title="' + t('gpxpod', 'tracks/routes name list')
					+ '" class="trackNamesList">'
				for (let z = 0; z < a[TRACKNAMELIST].length; z++) {
					let trname = a[TRACKNAMELIST][z]
					if (trname === '') {
						trname = 'unnamed'
					}
					popupTxt = popupTxt + '<li>' + escapeHtml(trname) + '</li>'
				}
				popupTxt = popupTxt + '</ul>'
			}

			popupTxt = popupTxt + '<table class="popuptable">'
			popupTxt = popupTxt + '<tr>'
			popupTxt = popupTxt + '<td><i class="fa fa-arrows-alt-h" aria-hidden="true"></i> <b>'
				+ t('gpxpod', 'Distance') + '</b></td>'
			if (a[TOTAL_DISTANCE] !== null) {
				popupTxt = popupTxt + '<td>' + metersToDistance(a[TOTAL_DISTANCE], unit) + '</td>'
			} else {
				popupTxt = popupTxt + '<td> NA</td>'
			}
			popupTxt = popupTxt + '</tr><tr>'

			popupTxt = popupTxt + '<td><i class="fa fa-clock" aria-hidden="true"></i> '
				+ t('gpxpod', 'Duration') + ' </td><td> ' + formatDuration(a[TOTAL_DURATION]) + '</td>'
			popupTxt = popupTxt + '</tr><tr>'
			popupTxt = popupTxt + '<td><i class="fa fa-clock" aria-hidden="true"></i> <b>'
				+ t('gpxpod', 'Moving time') + '</b> </td><td> ' + formatDuration(a[MOVING_TIME]) + '</td>'
			popupTxt = popupTxt + '</tr><tr>'
			popupTxt = popupTxt + '<td><i class="fa fa-clock" aria-hidden="true"></i> '
				+ t('gpxpod', 'Pause time') + ' </td><td> ' + formatDuration(a[STOPPED_TIME]) + '</td>'
			popupTxt = popupTxt + '</tr><tr>'

			let dbs = 'no date'
			let dbes = 'no date'
			try {
				if (a[DATE_BEGIN] !== '' && a[DATE_BEGIN] !== 'None') {
					const db = moment(a[DATE_BEGIN].replace(' ', 'T') + 'Z')
					db.tz(chosentz)
					dbs = db.format('YYYY-MM-DD HH:mm:ss (Z)')
				}
				if (a[DATE_END] !== '' && a[DATE_END] !== 'None') {
					const dbe = moment(a[DATE_END].replace(' ', 'T') + 'Z')
					dbe.tz(chosentz)
					dbes = dbe.format('YYYY-MM-DD HH:mm:ss (Z)')
				}
			} catch (err) {
			}
			popupTxt = popupTxt + '<td><i class="fa fa-calendar-alt" aria-hidden="true"></i> '
				+ t('gpxpod', 'Begin') + ' </td><td> ' + dbs + '</td>'
			popupTxt = popupTxt + '</tr><tr>'
			popupTxt = popupTxt + '<td><i class="fa fa-calendar-alt" aria-hidden="true"></i> '
				+ t('gpxpod', 'End') + ' </td><td> ' + dbes + '</td>'
			popupTxt = popupTxt + '</tr><tr>'
			popupTxt = popupTxt + '<td><i class="fa fa-chart-line" aria-hidden="true"></i> <b>'
				+ t('gpxpod', 'Cumulative elevation gain') + '</b> </td><td> '
				+ metersToElevation(a[POSITIVE_ELEVATION_GAIN], unit) + '</td>'
			popupTxt = popupTxt + '</tr><tr>'
			popupTxt = popupTxt + '<td><i class="fa fa-chart-line" aria-hidden="true"></i> '
				+ t('gpxpod', 'Cumulative elevation loss') + ' </td><td> '
				+ metersToElevation(a[NEGATIVE_ELEVATION_GAIN], unit) + '</td>'
			popupTxt = popupTxt + '</tr><tr>'
			popupTxt = popupTxt + '<td><i class="fa fa-chart-area" aria-hidden="true"></i> '
				+ t('gpxpod', 'Minimum elevation') + ' </td><td> '
				+ metersToElevation(a[MIN_ELEVATION], unit) + '</td>'
			popupTxt = popupTxt + '</tr><tr>'
			popupTxt = popupTxt + '<td><i class="fa fa-chart-area" aria-hidden="true"></i> '
				+ t('gpxpod', 'Maximum elevation') + ' </td><td> '
				+ metersToElevation(a[MAX_ELEVATION], unit) + '</td>'
			popupTxt = popupTxt + '</tr><tr>'
			popupTxt = popupTxt + '<td><i class="fa fa-tachometer-alt" aria-hidden="true"></i> <b>'
				+ t('gpxpod', 'Maximum speed') + '</b> </td><td> '
			if (a[MAX_SPEED] !== null) {
				popupTxt = popupTxt + kmphToSpeed(a[MAX_SPEED], unit)
			} else {
				popupTxt = popupTxt + 'NA'
			}
			popupTxt = popupTxt + '</td>'
			popupTxt = popupTxt + '</tr><tr>'

			popupTxt = popupTxt + '<td><i class="fa fa-tachometer-alt" aria-hidden="true"></i> '
				+ t('gpxpod', 'Average speed') + ' </td><td> '
			if (a[AVERAGE_SPEED] !== null) {
				popupTxt = popupTxt + kmphToSpeed(a[AVERAGE_SPEED], unit)
			} else {
				popupTxt = popupTxt + 'NA'
			}
			popupTxt = popupTxt + '</td>'
			popupTxt = popupTxt + '</tr><tr>'

			popupTxt = popupTxt + '<td><i class="fa fa-tachometer-alt" aria-hidden="true"></i> <b>'
				+ t('gpxpod', 'Moving average speed') + '</b> </td><td> '
			if (a[MOVING_AVERAGE_SPEED] !== null) {
				popupTxt = popupTxt + kmphToSpeed(a[MOVING_AVERAGE_SPEED], unit)
			} else {
				popupTxt = popupTxt + 'NA'
			}
			popupTxt = popupTxt + '</td></tr>'

			popupTxt = popupTxt + '<tr><td><i class="fa fa-tachometer-alt" aria-hidden="true"></i> <b>'
				+ t('gpxpod', 'Moving average pace') + '</b> </td><td> '
			if (a[MOVING_PACE] !== null) {
				popupTxt = popupTxt + minPerKmToPace(a[MOVING_PACE], unit)
			} else {
				popupTxt = popupTxt + 'NA'
			}
			popupTxt = popupTxt + '</td></tr>'
			popupTxt = popupTxt + '</table>'

			gpxpod.markersPopupTxt[id] = {}
			gpxpod.markersPopupTxt[id].popup = popupTxt
		}
	}

	function setFileNumber(nbTracks, nbPics = 0) {
		const tracksTxt = n('gpxpod', '{n} track', '{n} tracks', nbTracks, { n: nbTracks })
		const picsTxt = n('gpxpod', '{np} picture', '{np} pictures', nbPics, { np: nbPics })
		const txt = tracksTxt + ', ' + picsTxt
		$('#filenumberlabel').text(txt)
	}

	function getAjaxMarkersSuccess(markerstxt) {
		// load markers
		loadMarkers(markerstxt)
		// remove all draws
		for (const tid in gpxpod.gpxlayers) {
			removeTrackDraw(tid)
		}
		if ($('#autozoomcheck').is(':checked')) {
			zoomOnAllMarkers()
		} else {
			gpxpod.map.setView(new L.LatLng(27, 5), 3)
		}
	}

	// read in #markers
	function loadMarkers(m) {
		let markerstxt
		if (m === '') {
			markerstxt = $('#markers').text()
		} else {
			markerstxt = m
		}
		if (markerstxt !== null && markerstxt !== '' && markerstxt !== false) {
			gpxpod.markers = $.parseJSON(markerstxt).markers
			gpxpod.subfolder = decodeURIComponent($('#subfolderselect').val())
			gpxpod.gpxcompRootUrl = $('#gpxcomprooturl').text()
			genPopupTxt()
		} else {
			delete gpxpod.markers
			gpxpod.markers = {}
		}
		redrawMarkers()
		updateTrackListFromBounds()
	}

	function stopGetMarkers() {
		if (gpxpod.currentMarkerAjax !== null) {
			// abort ajax
			gpxpod.currentMarkerAjax.abort()
			gpxpod.currentMarkerAjax = null
		}
	}

	// if GET params dir and file are set, we select the track
	function selectTrackFromUrlParam() {
		if (getUrlParameter('dir') && getUrlParameter('file')) {
			const dirGet = getUrlParameter('dir')
			const fileGet = getUrlParameter('file')
			const selectedDir = decodeURIComponent($('select#subfolderselect').val())
			if (selectedDir === dirGet) {
				const line = $('#gpxtable tr[name="' + encodeURIComponent(fileGet) + '"][folder="' + encodeURIComponent(dirGet) + '"]')
				if (line.length === 1) {
					const input = line.find('.drawtrack')
					input.prop('checked', true)
					input.change()
					OC.Notification.showTemporary(t('gpxpod', 'Track "{tn}" is loading', { tn: fileGet }))
				}
			}
		}
	}

	/// ///////////// FILTER /////////////////////

	// return true if the marker respects all filters
	function filter(m) {
		const unit = $('#measureunitselect').val()

		const mdate = new Date(m[DATE_END].split(' ')[0])
		let mdist = m[TOTAL_DISTANCE]
		let mceg = m[POSITIVE_ELEVATION_GAIN]
		if (unit === 'english') {
			mdist = mdist * METERSTOMILES
			mceg = mceg * METERSTOFOOT
		} else if (unit === 'nautical') {
			mdist = mdist * METERSTONAUTICALMILES
		}
		const datemin = $('#datemin').val()
		const datemax = $('#datemax').val()
		const distmin = $('#distmin').val()
		const distmax = $('#distmax').val()
		const cegmin = $('#cegmin').val()
		const cegmax = $('#cegmax').val()

		if (datemin !== '') {
			const ddatemin = new Date(datemin)
			if (mdate < ddatemin) {
				return false
			}
		}
		if (datemax !== '') {
			const ddatemax = new Date(datemax)
			if (ddatemax < mdate) {
				return false
			}
		}
		if (distmin !== '') {
			if (mdist < distmin) {
				return false
			}
		}
		if (distmax !== '') {
			if (distmax < mdist) {
				return false
			}
		}
		if (cegmin !== '') {
			if (mceg < cegmin) {
				return false
			}
		}
		if (cegmax !== '') {
			if (cegmax < mceg) {
				return false
			}
		}

		return true
	}

	function clearFiltersValues() {
		$('#datemin').val('')
		$('#datemax').val('')
		$('#distmin').val('')
		$('#distmax').val('')
		$('#cegmin').val('')
		$('#cegmax').val('')
	}

	/// ///////////// SIDEBAR TABLE /////////////////////

	function deleteOneTrack(tid) {
		const path = decodeURIComponent(gpxpod.markers[tid][FOLDER]).replace(/^\/$/, '')
				   + '/' + decodeURIComponent(gpxpod.markers[tid][NAME])
		const trackPathList = []
		trackPathList.push(path)

		const req = {
			paths: trackPathList,
		}
		const url = generateUrl('/apps/gpxpod/deleteTracks')
		axios.post(url, req).then((response) => {
			if (!response.data.done) {
				OC.dialogs.alert(
					t('gpxpod', 'Failed to delete track') + decodeURIComponent(gpxpod.markers[tid][NAME]) + '. '
					+ t('gpxpod', 'Reload this page')
					,
					t('gpxpod', 'Error')
				)
			} else {
				$('#subfolderselect').change()
			}
			if (response.data.message) {
				OC.Notification.showTemporary(response.data.message)
			} else {
				let msg, msg2
				if (response.data.deleted) {
					msg = t('gpxpod', 'Successfully deleted') + ' : ' + response.data.deleted + '. '
					OC.Notification.showTemporary(msg)
					msg2 = t('gpxpod', 'You can restore deleted files in "Files" app')
					OC.Notification.showTemporary(msg2)
				}
				if (response.data.notdeleted) {
					msg = t('gpxpod', 'Impossible to delete') + ' : ' + response.data.notdeleted + '.'
					OC.Notification.showTemporary(msg)
				}
			}
		}).catch((error) => {
			console.error(error)
			OC.Notification.showTemporary(t('gpxpod', 'Failed to delete selected tracks'))
		})
	}

	function deleteSelectedTracks() {
		const trackPathList = []
		let tid, path
		$('input.drawtrack:checked').each(function() {
			tid = $(this).attr('tid')
			path = decodeURIComponent(gpxpod.markers[tid][FOLDER]).replace(/^\/$/, '')
					+ '/' + decodeURIComponent(gpxpod.markers[tid][NAME])
			trackPathList.push(path)
		})

		showDeletingAnimation()
		const req = {
			paths: trackPathList,
		}
		const url = generateUrl('/apps/gpxpod/deleteTracks')
		axios.post(url, req).then((response) => {
			if (!response.data.done) {
				OC.dialogs.alert(
					t('gpxpod', 'Failed to delete selected tracks') + '. '
					+ t('gpxpod', 'Reload this page')
					,
					t('gpxpod', 'Error')
				)
			} else {
				$('#subfolderselect').change()
			}
			if (response.data.message) {
				OC.Notification.showTemporary(response.data.message)
			} else {
				let msg, msg2
				if (response.data.deleted) {
					msg = t('gpxpod', 'Successfully deleted') + ' : ' + response.data.deleted + '. '
					OC.Notification.showTemporary(msg)
					msg2 = t('gpxpod', 'You can restore deleted files in "Files" app')
					OC.Notification.showTemporary(msg2)
				}
				if (response.data.notdeleted) {
					msg = t('gpxpod', 'Impossible to delete') + ' : ' + response.data.notdeleted + '.'
					OC.Notification.showTemporary(msg)
				}
			}
		}).catch((error) => {
			console.error(error)
			OC.dialogs.alert(
				t('gpxpod', 'Failed to delete selected tracks') + '. '
				+ t('gpxpod', 'Reload this page')
				,
				t('gpxpod', 'Error')
			)
		}).then(() => {
			hideAnimation()
		})
	}

	function updateTrackListFromBounds(e) {
		let m
		let pc, dlUrl
		let table, datestr, sortkey
		let tableRows = ''
		const hassrtm = ($('#hassrtm').text() === 'yes')
		const mapBounds = gpxpod.map.getBounds()
		const chosentz = $('#tzselect').val()
		let url = generateUrl('/apps/files/ajax/download.php')
		// state of "update table" option checkbox
		const updOption = $('#updtracklistcheck').is(':checked')
		const tablecriteria = $('#tablecriteriasel').val()
		let elevationunit, distanceunit
		const unit = $('#measureunitselect').val()

		let totalDistance = 0
		let totalDuration = 0
		let totalCumulEle = 0
		let trackDuration, trackDurationSec, trackDistance, trackCumulEle

		// if this is a public link, the url is the public share
		if (pageIsPublicFolder()) {
			url = generateUrl('/s/' + gpxpod.token)
			let subpath = getUrlParameter('path')
			if (subpath === undefined) {
				subpath = '/'
			}
			url = url.split('?')[0] + '/download?path=' + encodeURIComponent(subpath) + '&files='
		} else if (pageIsPublicFile()) {
			url = generateUrl('/s/' + gpxpod.token)
		}

		let name, folder, encName, encFolder
		for (const id in gpxpod.markers) {
			m = gpxpod.markers[id]
			if (filter(m)) {
				// if ((!updOption) || mapBounds.contains(new L.LatLng(m[LAT], m[LON]))) {
				if ((!updOption)
						|| (tablecriteria === 'bounds' && mapBounds.intersects(
							new L.LatLngBounds(
								new L.LatLng(m[SOUTH], m[WEST]),
								new L.LatLng(m[NORTH], m[EAST])
							)
						)
						)
						|| (tablecriteria === 'start'
						 && mapBounds.contains(new L.LatLng(m[LAT], m[LON])))
						|| (tablecriteria === 'cross'
						 && trackCrossesMapBounds(m[SHORTPOINTLIST], mapBounds))
				   ) {
					// totals
					trackDistance = parseFloat(metersToDistanceNoAdaptNoUnit(m[TOTAL_DISTANCE], unit))
					totalDistance += trackDistance
					trackDurationSec = m[TOTAL_DURATION]
					trackDuration = formatDuration(trackDurationSec)
					totalDuration += trackDurationSec
					trackCumulEle = parseFloat(metersToElevationNoUnit(m[POSITIVE_ELEVATION_GAIN], unit))
					totalCumulEle += trackCumulEle

					encName = m[NAME]
					encFolder = m[FOLDER]
					name = decodeURIComponent(m[NAME])
					folder = decodeURIComponent(m[FOLDER])
					const path = folder.replace(/^\/$/, '') + '/' + name

					if (id in gpxpod.gpxlayers) {
						tableRows = tableRows + '<tr name="' + encName + '" folder="' + encFolder + '" '
						+ 'title="' + escapeHtml(path) + '"><td class="colortd" title="'
						+ t('gpxpod', 'Click the color to change it') + '" style="background:'
						+ gpxpod.gpxlayers[id].color + '"><input title="'
						+ t('gpxpod', 'Deselect to hide track drawing') + '" type="checkbox"'
						tableRows = tableRows + ' checked="checked" '
					} else {
						tableRows = tableRows + '<tr name="' + encName + '" folder="' + encFolder + '" '
							+ 'title="' + escapeHtml(path) + '"><td><input title="'
							+ t('gpxpod', 'Select to draw the track') + '" type="checkbox"'
					}
					if (id in gpxpod.currentAjaxSources) {
						tableRows = tableRows + ' style="display:none;"'
					}
					tableRows = tableRows + ' class="drawtrack" tid="'
								 + id + '">'
								 + '<p '
					if (!(id in gpxpod.currentAjaxSources)) {
						tableRows = tableRows + ' style="display:none;"'
						pc = ''
					} else {
						pc = gpxpod.currentAjaxPercentage[id]
					}
					tableRows = tableRows + '><i class="fa fa-spinner fa-pulse fa-3x fa-fw"></i>'
						+ '<tt class="progress" tid="' + id + '">'
						+ pc + '</tt>%</p>'
						+ '</td>\n'
					tableRows = tableRows
								 + '<td class="trackname"><div class="trackcol">'

					dlUrl = ''
					if (pageIsPublicFolder()) {
						dlUrl = '"' + url + encName + '" target="_blank"'
					} else if (pageIsPublicFile()) {
						dlUrl = '"' + url + '" target="_blank"'
					} else {
						dlUrl = '"' + url + '?dir=' + encFolder
								 + '&files=' + encName + '"'
					}
					tableRows = tableRows + '<a href=' + dlUrl
								 + ' title="' + t('gpxpod', 'download') + '" class="tracklink">'
								 + '<i class="fa fa-cloud-download-alt" aria-hidden="true"></i>'
								 + escapeHtml(name) + '</a>\n'

					tableRows = tableRows + '<div>'

					if (!pageIsPublicFileOrFolder()) {
						tableRows = tableRows + '<button class="dropdownbutton" title="'
							+ t('gpxpod', 'More') + '">'
							+ '<i class="fa fa-ellipsis-h" aria-hidden="true"></i></button>'
					}
					if (id in gpxpod.gpxlayers) {
						tableRows = tableRows + '<button class="zoomtrackbutton" tid="' + id + '"'
							+ ' title="' + t('gpxpod', 'Center map on this track') + '">'
							+ '<i class="fa fa-search" aria-hidden="true"></i></button>'
					}
					if (!pageIsPublicFileOrFolder()) {
						tableRows = tableRows + ' <button class="publink" '
									 + 'type="track" tid="' + id + '"'
									 + 'title="'
									 + t('gpxpod', 'This public link will work only if \'{title}\' or one of its parent folder is shared in \'files\' app by public link without password',
												 { title: path }
									 )
									 + '" target="_blank" href="">'
									 + '<i class="fa fa-share-alt" aria-hidden="true"></i></button>'

						tableRows = tableRows + '<div class="dropdown-content">'
						tableRows = tableRows + '<a href="#" tid="'
									 + id + '" class="deletetrack">'
									 + '<i class="fa fa-trash" aria-hidden="true"></i> '
									 + t('gpxpod', 'Delete this track file')
									 + '</a>'
						if (hassrtm) {
							tableRows = tableRows + '<a href="#" tid="'
										+ id + '" class="csrtms">'
										 + '<i class="fa fa-chart-line" aria-hidden="true"></i> '
										 + t('gpxpod', 'Correct elevations with smoothing for this track')
										 + '</a>'
							tableRows = tableRows + '<a href="#" tid="'
										 + id + '" class="csrtm">'
										 + '<i class="fa fa-chart-line" aria-hidden="true"></i> '
										 + t('gpxpod', 'Correct elevations for this track')
										 + '</a>'
						}
						if (gpxpod.gpxmotion_compliant) {
							const motionviewurl = gpxpod.gpxmotionview_url + 'autoplay=1&path='
										+ encodeURIComponent(path)
							tableRows = tableRows + '<a href="' + motionviewurl + '" '
										 + 'target="_blank" class="motionviewlink">'
										 + '<i class="fa fa-play-circle" aria-hidden="true"></i> '
										 + t('gpxpod', 'View this file in GpxMotion')
										 + '</a>'
						}
						if (gpxpod.gpxedit_compliant) {
							const edurl = gpxpod.gpxedit_url + 'file='
										+ encodeURIComponent(path)
							tableRows = tableRows + '<a href="' + edurl + '" '
										 + 'target="_blank" class="editlink">'
										 + '<i class="fa fa-pencil-alt" aria-hidden="true"></i> '
										 + t('gpxpod', 'Edit this file in GpxEdit')
										 + '</a>'
						}
						tableRows = tableRows + '</div>'
					}

					tableRows = tableRows + '</div>'

					tableRows = tableRows + '</div></td>\n'
					datestr = t('gpxpod', 'no date')
					sortkey = 0
					try {
						if (m[DATE_END] !== '' && m[DATE_END] !== 'None') {
							const mom = moment(m[DATE_END].replace(' ', 'T') + 'Z')
							mom.tz(chosentz)
							datestr = mom.format('YYYY-MM-DD')
							sortkey = mom.unix()
						}
					} catch (err) {
					}
					tableRows = tableRows + '<td sorttable_customkey="' + sortkey + '">'
								 + escapeHtml(datestr) + '</td>\n'
					tableRows = tableRows
					+ '<td>' + trackDistance + '</td>\n'

					tableRows = tableRows
					+ '<td><div class="durationcol">'
					+ escapeHtml(trackDuration) + '</div></td>\n'

					tableRows = tableRows
					+ '<td>' + trackCumulEle + '</td>\n'
					tableRows = tableRows + '</tr>\n'
				}
			}
		}

		if (tableRows === '') {
			table = ''
			$('#gpxlist').html(table)
			// $('#ticv').hide()
			$('#ticv').text(t('gpxpod', 'No track visible'))
		} else {
			// $('#ticv').show()
			if ($('#updtracklistcheck').is(':checked')) {
				$('#ticv').text(t('gpxpod', 'Tracks from current view'))
			} else {
				$('#ticv').text(t('gpxpod', 'All tracks'))
			}
			if (unit === 'metric') {
				elevationunit = 'm'
				distanceunit = 'km'
			} else if (unit === 'english') {
				elevationunit = 'ft'
				distanceunit = 'mi'
			} else if (unit === 'nautical') {
				elevationunit = 'm'
				distanceunit = 'nmi'
			}
			table = '<table id="gpxtable" class="sortable sidebar-table">\n<thead>'
			table = table + '<tr>'
			table = table + '<th col="1" title="' + t('gpxpod', 'Draw') + '">'
					+ '<i class="bigfa fa fa-pen-square" aria-hidden="true"></i></th>\n'
			table = table + '<th col="2">' + t('gpxpod', 'Track')
				+ '<br/><i class="bigfa fa fa-road" aria-hidden="true"></i></th>\n'
			table = table + '<th col="3">' + t('gpxpod', 'Date')
					+ '<br/><i class="bigfa far fa-calendar-alt" aria-hidden="true"></i></th>\n'
			table = table + '<th col="4">' + t('gpxpod', 'Dist<br/>ance<br/>')
					+ '<i>(' + distanceunit + ')</i>'
					+ '<br/><i class="bigfa fa fa-arrows-alt-h" aria-hidden="true"></i></th>\n'
			table = table + '<th col="5">' + t('gpxpod', 'Duration')
					+ '<br/><i class="bigfa fa fa-clock" aria-hidden="true"></i></th>\n'
			table = table + '<th col="6">' + t('gpxpod', 'Cumulative<br/>elevation<br/>gain')
					+ ' <i>(' + elevationunit + ')</i>'
					+ '<br/><i class="bigfa fa fa-chart-line" aria-hidden="true"></i></th>\n'
			table = table + '</tr></thead><tbody>\n'
			table = table + tableRows
			table = table + '</tbody></table>'
			table += '<h3>' + t('gpxpod', 'Total') + '</h3>'
			table += '<table id="totals" class="sidebar-table"><thead>'
				+ '<th>' + t('gpxpod', 'Distance') + '</th>'
				+ '<th>' + t('gpxpod', 'Duration') + '</th>'
				+ '<th>' + t('gpxpod', 'Cumulative<br/>elevation<br/>gain') + '</th>'
				+ '</thead><tbody><tr>'
				+ '<td>' + totalDistance + '</td>'
				+ '<td>' + formatDuration(totalDuration) + '</td>'
				+ '<td>' + totalCumulEle + '</td>'
				+ '</tr></tbody></table>'
			const desc = gpxpod.sort.desc
			const col = gpxpod.sort.col
			$('#gpxlist').html(table)
			// eslint-disable-next-line
			sorttable.makeSortable(document.getElementById('gpxtable'))
			// restore filtered columns
			$('#gpxtable thead th[col=' + col + ']').click()
			if (desc) {
				$('#gpxtable thead th[col=' + col + ']').click()
			}
		}
	}

	/// ///////////// DRAW TRACK /////////////////////

	// update progress percentage in track table
	function showProgress(tid) {
		$('.progress[tid="' + tid + '"]').text(gpxpod.currentAjaxPercentage[tid])
	}

	function layerBringToFront(l) {
		l.bringToFront()
	}

	function checkAddTrackDraw(tid, checkbox = null, color = null, showchart = null, collectPoints = true, cache = true) {
		let url
		const colorcriteria = $('#colorcriteria').val()
		if (showchart === null) {
			showchart = $('#showchartcheck').is(':checked')
		}
		if (tid in gpxpod.gpxCache) {
			// add a multicolored track only if a criteria is selected and
			// no forced color was chosen
			if (colorcriteria !== 'none' && color === null) {
				addColoredTrackDraw(gpxpod.gpxCache[tid], tid, showchart, collectPoints)
			} else {
				addTrackDraw(gpxpod.gpxCache[tid], tid, showchart, color, collectPoints)
			}
		} else {
			const req = {
				path: decodeURIComponent(gpxpod.markers[tid][FOLDER]).replace(/^\/$/, '')
						+ '/' + decodeURIComponent(gpxpod.markers[tid][NAME]),
			}
			// are we in the public folder page ?
			if (pageIsPublicFolder()) {
				req.username = gpxpod.username
				url = generateUrl('/apps/gpxpod/getpublicgpx')
			} else {
				url = generateUrl('/apps/gpxpod/getgpx')
			}
			gpxpod.currentAjaxPercentage[tid] = 0
			if (checkbox !== null) {
				checkbox.parent().find('p').show()
				checkbox.hide()
			}
			showProgress(tid)
			if (tid in gpxpod.currentAjaxSources) {
				gpxpod.currentAjaxSources[tid].cancel()
			}
			gpxpod.currentAjaxSources[tid] = axios.CancelToken.source()
			axios.post(url, req, {
				cancelToken: gpxpod.currentAjaxSources[tid].token,
				onDownloadProgress: (e) => {
					if (e.lengthComputable) {
						const percentComplete = e.loaded / e.total * 100
						gpxpod.currentAjaxPercentage[tid] = parseInt(percentComplete)
						showProgress(tid)
					}
				}
			}).then((response) => {
				if (cache) {
					gpxpod.gpxCache[tid] = response.data.content
				}
				// add a multicolored track only if a criteria is selected and
				// no forced color was chosen
				if (colorcriteria !== 'none' && color === null) {
					addColoredTrackDraw(response.data.content, tid, showchart, collectPoints)
				} else {
					addTrackDraw(response.data.content, tid, showchart, color, collectPoints)
				}
			})
		}
	}

	function addColoredTrackDraw(gpx, tid, withElevation, collectPoints = true) {
		deleteOnHover()

		let points, latlngs, times, minVal, maxVal, minMax, exts, ext
		let prevLatLng, prevDateTime, outlineWidth, l, tooltipText
		let data, arrows, wpts, speed
		let lat, lon, extval, ele, time, linkText, linkUrl, linkHTML
		let dist
		let name, cmt, desc, sym
		const color = 'red'
		const lineBorder = $('#linebordercheck').is(':checked')
		const rteaswpt = $('#rteaswpt').is(':checked')
		const arrow = $('#arrowcheck').is(':checked')
		const colorCriteria = $('#colorcriteria').val()
		const colorCriteriaExt = $('#colorcriteriaext').val()
		let chartTitle = t('gpxpod', colorCriteriaExt) + '/' + t('gpxpod', 'distance')
		if (colorCriteria === 'elevation') {
			chartTitle = t('gpxpod', 'altitude/distance')
		} else if (colorCriteria === 'speed') {
			chartTitle = t('gpxpod', 'speed/distance')
		} else if (colorCriteria === 'pace') {
			chartTitle = t('gpxpod', 'pace(time for last km or mi)/distance')
		}
		const unit = $('#measureunitselect').val()
		let yUnit, xUnit
		let decimalsY = 0
		if (unit === 'metric') {
			xUnit = 'km'
			if (colorCriteria === 'speed') {
				yUnit = 'km/h'
			} else if (colorCriteria === 'pace') {
				yUnit = 'min/km'
				decimalsY = 2
			} else if (colorCriteria === 'elevation') {
				yUnit = 'm'
			}
		} else if (unit === 'english') {
			xUnit = 'mi'
			if (colorCriteria === 'speed') {
				yUnit = 'mi/h'
			} else if (colorCriteria === 'pace') {
				yUnit = 'min/mi'
				decimalsY = 2
			} else if (colorCriteria === 'elevation') {
				yUnit = 'ft'
			}
		} else if (unit === 'nautical') {
			xUnit = 'nmi'
			if (colorCriteria === 'speed') {
				yUnit = 'kt'
			} else if (colorCriteria === 'pace') {
				yUnit = 'min/nmi'
				decimalsY = 2
			} else if (colorCriteria === 'elevation') {
				yUnit = 'm'
			}
		}
		if (colorCriteria === 'extension') {
			yUnit = ''
			decimalsY = 2
		}

		const path = decodeURIComponent(gpxpod.markers[tid][FOLDER]).replace(/^\/$/, '')
				   + '/' + decodeURIComponent(gpxpod.markers[tid][NAME])

		const gpxp = $.parseXML(gpx.replace(/version="1.1"/, 'version="1.0"'))
		const gpxx = $(gpxp).find('gpx')

		if (tid in gpxpod.gpxlayers) {
			removeTrackDraw(tid)
		}

		// count the number of lines and point
		// const nbPoints = gpxx.find('>wpt').length
		const nbLines = gpxx.find('>trk').length + gpxx.find('>rte').length

		let el
		if (withElevation) {
			removeElevation()
			if (nbLines > 0) {
				el = L.control.elevation({
					position: 'bottomleft',
					height: 100,
					width: 720,
					margins: {
						top: 10,
						right: 50,
						bottom: 33,
						left: 60,
					},
					yUnit,
					xUnit,
					hoverNumber: {
						decimalsX: 3,
						decimalsY,
						formatter: undefined,
					},
					title: chartTitle + ' : ' + path,
					detached: false,
					followMarker: false,
					lazyLoadJS: false,
					timezone: $('#tzselect').val(),
					theme: 'steelblue-theme',
					showTime: 'hover-timestamp',
				})
				el.addTo(gpxpod.map)
				gpxpod.elevationLayer = el
				gpxpod.elevationTrackId = tid
				gpxpod.closeElevationButton.addTo(gpxpod.map)
				$('<div id="hover-timestamp"></div>').insertAfter(gpxpod.closeElevationButton._container)
			}
		}
		// css
		setTrackCss(tid, '#FFFFFF')
		const coloredTooltipClass = 'mytooltip tooltip' + tid

		if (!(tid in gpxpod.gpxlayers)) {
			const whatToDraw = $('#trackwaypointdisplayselect').val()
			const weight = parseInt($('#lineweight').val())
			const waypointStyle = getWaypointStyle()
			const tooltipStyle = getTooltipStyle()
			const symbolOverwrite = getSymbolOverwrite()

			gpxpod.points[tid] = {}

			const gpxlayer = { color: 'linear-gradient(to right, lightgreen, yellow, red);' }
			gpxlayer.layer = L.featureGroup()
			gpxlayer.layerOutlines = null

			// const fileDesc = gpxx.find('>metadata>desc').text()
			let mm, popupText

			if (whatToDraw === 'trw' || whatToDraw === 'w') {
				gpxx.find('wpt').each(function() {
					lat = $(this).attr('lat')
					lon = $(this).attr('lon')
					name = $(this).find('name').text()
					cmt = $(this).find('cmt').text()
					desc = $(this).find('desc').text()
					sym = $(this).find('sym').text()
					ele = $(this).find('ele').text()
					time = $(this).find('time').text()
					linkText = $(this).find('link text').text()
					linkUrl = $(this).find('link').attr('href')

					mm = L.marker(
						[lat, lon],
						{
							icon: symbolIcons[waypointStyle],
						}
					)
					if (tooltipStyle === 'p') {
						mm.bindTooltip(brify(name, 20), { permanent: true, className: coloredTooltipClass })
					} else {
						mm.bindTooltip(brify(name, 20), { className: coloredTooltipClass })
					}

					popupText = '<h3 style="text-align:center;">' + escapeHtml(name) + '</h3><hr/>'
									+ t('gpxpod', 'Track') + ' : ' + escapeHtml(path) + '<br/>'
					if (linkText && linkUrl) {
						popupText = popupText
									+ t('gpxpod', 'Link') + ' : <a href="' + escapeHtml(linkUrl) + '" title="' + escapeHtml(linkUrl) + '" target="_blank">' + escapeHtml(linkText) + '</a><br/>'
					}
					if (ele !== '') {
						popupText = popupText + t('gpxpod', 'Elevation') + ' : '
									+ ele + 'm<br/>'
					}
					popupText = popupText + t('gpxpod', 'Latitude') + ' : ' + lat + '<br/>'
								+ t('gpxpod', 'Longitude') + ' : ' + lon + '<br/>'
					if (cmt !== '') {
						popupText = popupText
									+ t('gpxpod', 'Comment') + ' : ' + cmt + '<br/>'
					}
					if (desc !== '') {
						popupText = popupText
									+ t('gpxpod', 'Description') + ' : ' + desc + '<br/>'
					}
					if (sym !== '') {
						popupText = popupText
									+ t('gpxpod', 'Symbol name') + ' : ' + sym
					}
					if (symbolOverwrite && sym) {
						if (sym in symbolIcons) {
							mm.setIcon(symbolIcons[sym])
						} else {
							mm.setIcon(L.divIcon({
								className: 'unknown',
								iconAnchor: [12, 12],
							}))
						}
					}
					mm.bindPopup(popupText)
					gpxlayer.layer.addLayer(mm)
				})
			}

			let li = 0
			if (whatToDraw === 'trw' || whatToDraw === 't') {
				gpxx.find('trk').each(function() {
					name = $(this).find('>name').text()
					cmt = $(this).find('>cmt').text()
					desc = $(this).find('>desc').text()
					linkText = $(this).find('link text').text()
					linkUrl = $(this).find('link').attr('href')
					$(this).find('trkseg').each(function() {
						points = $(this).find('trkpt')
						// get points extensions
						exts = []
						points.each(function() {
							ext = {}
							$(this).find('extensions').children().each(function() {
								ext[$(this).prop('tagName').toLowerCase()] = $(this).text()
							})
							exts.push(ext)
						})
						if (colorCriteria === 'extension') {
							latlngs = []
							times = []
							minVal = null
							maxVal = null
							points.each(function() {
								lat = $(this).attr('lat')
								lon = $(this).attr('lon')
								if (!lat || !lon) {
									return
								}
								extval = $(this).find('extensions ' + colorCriteriaExt).text()
								time = $(this).find('time').text()
								times.push(time)
								if (extval !== '') {
									extval = parseFloat(extval)
									if (extval !== Infinity) {
										if (minVal === null || extval < minVal) {
											minVal = extval
										}
										if (maxVal === null || extval > maxVal) {
											maxVal = extval
										}
									} else {
										extval = 0
									}
									latlngs.push([lat, lon, extval])
								} else {
									latlngs.push([lat, lon, 0])
								}
							})
						} else if (colorCriteria === 'elevation') {
							latlngs = []
							times = []
							minVal = null
							maxVal = null
							points.each(function() {
								lat = $(this).attr('lat')
								lon = $(this).attr('lon')
								if (!lat || !lon) {
									return
								}
								ele = $(this).find('ele').text()
								time = $(this).find('time').text()
								times.push(time)
								if (ele !== '') {
									ele = parseFloat(ele)
									if (unit === 'english') {
										ele = parseFloat(ele) * METERSTOFOOT
									}
									if (ele !== Infinity) {
										if (minVal === null || ele < minVal) {
											minVal = ele
										}
										if (maxVal === null || ele > maxVal) {
											maxVal = ele
										}
									} else {
										ele = 0
									}
									latlngs.push([lat, lon, ele])
								} else {
									latlngs.push([lat, lon, 0])
								}
							})
						} else if (colorCriteria === 'pace') {
							latlngs = []
							times = []
							minVal = null
							maxVal = null
							minMax = []
							points.each(function() {
								lat = $(this).attr('lat')
								lon = $(this).attr('lon')
								if (!lat || !lon) {
									return
								}
								time = $(this).find('time').text()
								times.push(time)
								latlngs.push([lat, lon])
							})
							getPace(latlngs, times, minMax)
							minVal = minMax[0]
							maxVal = minMax[1]
						} else if (colorCriteria === 'speed') {
							latlngs = []
							times = []
							prevLatLng = null
							prevDateTime = null
							minVal = null
							maxVal = null
							let latlng
							let date
							let dateTime
							points.each(function() {
								lat = $(this).attr('lat')
								lon = $(this).attr('lon')
								if (!lat || !lon) {
									return
								}
								latlng = L.latLng(lat, lon)
								ele = $(this).find('ele').text()

								time = $(this).find('time').text()
								times.push(time)
								if (time !== '') {
									date = new Date(time)
									dateTime = date.getTime()
								}

								if (time !== '' && prevDateTime !== null) {
									dist = latlng.distanceTo(prevLatLng)
									if (unit === 'english') {
										dist = dist * METERSTOMILES
									} else if (unit === 'metric') {
										dist = dist / 1000
									} else if (unit === 'nautical') {
										dist = dist * METERSTONAUTICALMILES
									}
									speed = dist / ((dateTime - prevDateTime) / 1000) * 3600
									if (speed !== Infinity) {
										if (minVal === null || speed < minVal) {
											minVal = speed
										}
										if (maxVal === null || speed > maxVal) {
											maxVal = speed
										}
									} else {
										speed = 0
									}
									latlngs.push([lat, lon, speed])
								} else {
									latlngs.push([lat, lon, 0])
								}

								// keep some previous values
								prevLatLng = latlng
								if (time !== '') {
									prevDateTime = dateTime
								} else {
									prevDateTime = null
								}
							})
						}

						outlineWidth = 0.3 * weight
						if (!lineBorder) {
							outlineWidth = 0
						}
						l = L.hotline(latlngs, {
							weight,
							outlineWidth,
							min: minVal,
							max: maxVal,
						})
						popupText = gpxpod.markersPopupTxt[tid].popup
						if (cmt !== '') {
							popupText = popupText + '<p class="combutton" combutforfeat="'
										+ escapeHtml(tid) + escapeHtml(name)
										+ '" style="margin:0; cursor:pointer;">' + t('gpxpod', 'Comment')
										+ ' <i class="fa fa-expand"></i></p>'
										+ '<p class="comtext" style="display:none; margin:0; cursor:pointer;" comforfeat="'
										+ escapeHtml(tid) + escapeHtml(name) + '">'
										+ escapeHtml(cmt) + '</p>'
						}
						if (desc !== '') {
							popupText = popupText + '<p class="descbutton" descbutforfeat="'
										+ escapeHtml(tid) + escapeHtml(name)
										+ '" style="margin:0; cursor:pointer;">Description <i class="fa fa-expand"></i></p>'
										+ '<p class="desctext" style="display:none; margin:0; cursor:pointer;" descforfeat="'
										+ escapeHtml(tid) + escapeHtml(name) + '">'
										+ escapeHtml(desc) + '</p>'
						}
						linkHTML = ''
						if (linkText && linkUrl) {
							linkHTML = '<a href="' + escapeHtml(linkUrl) + '" title="' + escapeHtml(linkUrl) + '" target="_blank">' + escapeHtml(linkText) + '</a>'
						}
						popupText = popupText.replace('<li>' + escapeHtml(name) + '</li>',
							'<li><b>' + escapeHtml(name) + ' (' + linkHTML + ')</b></li>')
						l.bindPopup(
							popupText,
							{
								autoPan: true,
								autoClose: true,
								closeOnClick: true,
							}
						)
						tooltipText = decodeURIComponent(gpxpod.markers[tid][NAME])
						if (decodeURIComponent(gpxpod.markers[tid][NAME]) !== name) {
							tooltipText = tooltipText + '<br/>' + escapeHtml(name)
						}
						if (tooltipStyle === 'p') {
							l.bindTooltip(tooltipText, { permanent: true, className: coloredTooltipClass })
						} else {
							l.bindTooltip(tooltipText, { sticky: true, className: coloredTooltipClass })
						}
						if (withElevation) {
							data = l.toGeoJSON()
							if (times.length === data.geometry.coordinates.length) {
								for (let i = 0; i < data.geometry.coordinates.length; i++) {
									data.geometry.coordinates[i].push(times[i])
								}
							}
							if (data.geometry.coordinates.length !== 0) {
								el.addData(data, l)
							}
						}
						l.on('mouseover', function() {
							hoverStyle.weight = parseInt(2 * weight)
							defaultStyle.weight = weight
							l.setStyle(hoverStyle)
							defaultStyle.color = color
							gpxpod.gpxlayers[tid].layer.bringToFront()
							l.bringToFront()
						})
						l.on('mouseout', function() {
							l.setStyle(defaultStyle)
						})
						if (collectPoints) {
							l.on('mouseover', lineOver)
							l.on('mouseout', lineOut)
						}

						gpxlayer.layer.addLayer(l)

						if (arrow) {
							arrows = L.polylineDecorator(l)
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
										weight: parseInt(weight * 0.6),
									},
								}),
							}])
							gpxlayer.layer.addLayer(arrows)
						}
						l.tid = tid
						l.li = li
						if (collectPoints) {
							gpxpod.points[tid][li] = {
								coords: latlngs,
								times,
								exts,
							}
						}
						li++
					})
				})
			}
			if (whatToDraw === 'trw' || whatToDraw === 'r') {
				gpxx.find('rte').each(function() {
					name = $(this).find('>name').text()
					cmt = $(this).find('>cmt').text()
					desc = $(this).find('>desc').text()
					linkText = $(this).find('link text').text()
					linkUrl = $(this).find('link').attr('href')
					wpts = null
					let m, pname
					if (rteaswpt) {
						wpts = L.featureGroup()
					}
					points = $(this).find('rtept')
					// get points extensions
					exts = []
					points.each(function() {
						ext = {}
						$(this).find('extensions').children().each(function() {
							ext[$(this).prop('tagName').toLowerCase()] = $(this).text()
						})
						exts.push(ext)
					})
					if (colorCriteria === 'extension') {
						latlngs = []
						times = []
						minVal = null
						maxVal = null
						points.each(function() {
							lat = $(this).attr('lat')
							lon = $(this).attr('lon')
							if (!lat || !lon) {
								return
							}
							extval = $(this).find('extensions ' + colorCriteriaExt).text()
							time = $(this).find('time').text()
							times.push(time)
							if (extval !== '') {
								extval = parseFloat(extval)
								if (extval !== Infinity) {
									if (minVal === null || extval < minVal) {
										minVal = extval
									}
									if (maxVal === null || extval > maxVal) {
										maxVal = extval
									}
								} else {
									extval = 0
								}
								latlngs.push([lat, lon, extval])
							} else {
								latlngs.push([lat, lon, 0])
							}
							if (rteaswpt) {
								m = L.marker([lat, lon], {
									icon: symbolIcons[waypointStyle],
								})
								pname = $(this).find('name').text()
								if (pname) {
									m.bindTooltip(pname, { permanent: false })
								}
								wpts.addLayer(m)
							}
						})
					} else if (colorCriteria === 'elevation') {
						latlngs = []
						times = []
						minVal = null
						maxVal = null
						points.each(function() {
							lat = $(this).attr('lat')
							lon = $(this).attr('lon')
							if (!lat || !lon) {
								return
							}
							ele = $(this).find('ele').text()
							time = $(this).find('time').text()
							times.push(time)
							if (ele !== '') {
								ele = parseFloat(ele)
								if (unit === 'english') {
									ele = parseFloat(ele) * METERSTOFOOT
								}
								if (ele !== Infinity) {
									if (minVal === null || ele < minVal) {
										minVal = ele
									}
									if (maxVal === null || ele > maxVal) {
										maxVal = ele
									}
								} else {
									ele = 0
								}
								latlngs.push([lat, lon, ele])
							} else {
								latlngs.push([lat, lon, 0])
							}
							if (rteaswpt) {
								m = L.marker([lat, lon], {
									icon: symbolIcons[waypointStyle],
								})
								pname = $(this).find('name').text()
								if (pname) {
									m.bindTooltip(pname, { permanent: false })
								}
								wpts.addLayer(m)
							}
						})
					} else if (colorCriteria === 'pace') {
						latlngs = []
						times = []
						minVal = null
						maxVal = null
						minMax = []
						points.each(function() {
							lat = $(this).attr('lat')
							lon = $(this).attr('lon')
							if (!lat || !lon) {
								return
							}
							time = $(this).find('time').text()
							times.push(time)
							latlngs.push([lat, lon])
							if (rteaswpt) {
								m = L.marker([lat, lon], {
									icon: symbolIcons[waypointStyle],
								})
								pname = $(this).find('name').text()
								if (pname) {
									m.bindTooltip(pname, { permanent: false })
								}
								wpts.addLayer(m)
							}
						})
						getPace(latlngs, times, minMax)
						minVal = minMax[0]
						maxVal = minMax[1]
					} else if (colorCriteria === 'speed') {
						latlngs = []
						times = []
						prevLatLng = null
						prevDateTime = null
						minVal = null
						maxVal = null
						let latlng
						let date
						let dateTime
						points.each(function() {
							lat = $(this).attr('lat')
							lon = $(this).attr('lon')
							if (!lat || !lon) {
								return
							}
							latlng = L.latLng(lat, lon)
							ele = $(this).find('ele').text()
							time = $(this).find('time').text()
							times.push(time)
							if (time !== '') {
								date = new Date(time)
								dateTime = date.getTime()
							}

							if (time !== '' && prevDateTime !== null) {
								dist = latlng.distanceTo(prevLatLng)
								if (unit === 'english') {
									dist = dist * METERSTOMILES
								} else if (unit === 'metric') {
									dist = dist / 1000
								} else if (unit === 'nautical') {
									dist = dist * METERSTONAUTICALMILES
								}
								speed = dist / ((dateTime - prevDateTime) / 1000) * 3600
								if (speed !== Infinity) {
									if (minVal === null || speed < minVal) {
										minVal = speed
									}
									if (maxVal === null || speed > maxVal) {
										maxVal = speed
									}
								} else {
									speed = 0
								}
								latlngs.push([lat, lon, speed])
							} else {
								latlngs.push([lat, lon, 0])
							}

							// keep some previous values
							prevLatLng = latlng
							if (time !== '') {
								prevDateTime = dateTime
							} else {
								prevDateTime = null
							}
							if (rteaswpt) {
								m = L.marker([lat, lon], {
									icon: symbolIcons[waypointStyle],
								})
								pname = $(this).find('name').text()
								if (pname) {
									m.bindTooltip(pname, { permanent: false })
								}
								wpts.addLayer(m)
							}
						})
					}

					outlineWidth = 0.3 * weight
					if (!lineBorder) {
						outlineWidth = 0
					}
					l = L.hotline(latlngs, {
						weight,
						outlineWidth,
						min: minVal,
						max: maxVal,
					})
					popupText = gpxpod.markersPopupTxt[tid].popup
					if (cmt !== '') {
						popupText = popupText + '<p class="combutton" combutforfeat="'
									+ escapeHtml(tid) + escapeHtml(name)
									+ '" style="margin:0; cursor:pointer;">' + t('gpxpod', 'Comment')
									+ ' <i class="fa fa-expand"></i></p>'
									+ '<p class="comtext" style="display:none; margin:0; cursor:pointer;" comforfeat="'
									+ escapeHtml(tid) + escapeHtml(name) + '">'
									+ escapeHtml(cmt) + '</p>'
					}
					if (desc !== '') {
						popupText = popupText + '<p class="descbutton" descbutforfeat="'
									+ escapeHtml(tid) + escapeHtml(name)
									+ '" style="margin:0; cursor:pointer;">Description <i class="fa fa-expand"></i></p>'
									+ '<p class="desctext" style="display:none; margin:0; cursor:pointer;" descforfeat="'
									+ escapeHtml(tid) + escapeHtml(name) + '">'
									+ escapeHtml(desc) + '</p>'
					}
					linkHTML = ''
					if (linkText && linkUrl) {
						linkHTML = '<a href="' + escapeHtml(linkUrl) + '" title="' + escapeHtml(linkUrl) + '" target="_blank">' + escapeHtml(linkText) + '</a>'
					}
					popupText = popupText.replace('<li>' + escapeHtml(name) + '</li>',
						'<li><b>' + escapeHtml(name) + ' (' + linkHTML + ')</b></li>')
					l.bindPopup(
						popupText,
						{
							autoPan: true,
							autoClose: true,
							closeOnClick: true,
						}
					)
					tooltipText = decodeURIComponent(gpxpod.markers[tid][NAME])
					if (decodeURIComponent(gpxpod.markers[tid][NAME]) !== name) {
						tooltipText = tooltipText + '<br/>' + escapeHtml(name)
					}
					if (tooltipStyle === 'p') {
						l.bindTooltip(tooltipText, { permanent: true, className: coloredTooltipClass })
					} else {
						l.bindTooltip(tooltipText, { sticky: true, className: coloredTooltipClass })
					}
					if (withElevation) {
						data = l.toGeoJSON()
						if (times.length === data.geometry.coordinates.length) {
							for (let i = 0; i < data.geometry.coordinates.length; i++) {
								data.geometry.coordinates[i].push(times[i])
							}
						}
						if (data.geometry.coordinates.length !== 0) {
							el.addData(data, l)
						}
					}
					l.on('mouseover', function() {
						hoverStyle.weight = parseInt(2 * weight)
						defaultStyle.weight = weight
						l.setStyle(hoverStyle)
						defaultStyle.color = color
						gpxpod.gpxlayers[tid].layer.bringToFront()
					})
					l.on('mouseout', function() {
						l.setStyle(defaultStyle)
					})
					if (collectPoints) {
						l.on('mouseover', lineOver)
						l.on('mouseout', lineOut)
					}
					l.tid = tid
					l.li = li
					if (collectPoints) {
						gpxpod.points[tid][li] = {
							coords: latlngs,
							times,
							exts,
						}
					}

					gpxlayer.layer.addLayer(l)
					if (rteaswpt) {
						gpxlayer.layer.addLayer(wpts)
					}

					if (arrow) {
						arrows = L.polylineDecorator(l)
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
									weight: parseInt(weight * 0.6),
								},
							}),
						}])
						gpxlayer.layer.addLayer(arrows)
					}
					li++
				})
			}

			gpxlayer.layer.addTo(gpxpod.map)
			gpxpod.gpxlayers[tid] = gpxlayer

			if ($('#autozoomcheck').is(':checked')) {
				zoomOnAllDrawnTracks()
			}

			delete gpxpod.currentAjaxSources[tid]
			delete gpxpod.currentAjaxPercentage[tid]
			updateTrackListFromBounds()
			if ($('#openpopupcheck').is(':checked') && nbLines > 0) {
				// open popup on the marker position,
				// works better than opening marker popup
				// because the clusters avoid popup opening when marker is
				// not visible because it's grouped
				const pop = L.popup({
					autoPan: true,
					autoClose: true,
					closeOnClick: true,
				})
				pop.setContent(gpxpod.markersPopupTxt[tid].popup)
				pop.setLatLng(gpxpod.markersPopupTxt[tid].marker.getLatLng())
				pop.openOn(gpxpod.map)
			}
		}
	}

	function getPace(latlngs, times, minMax) {
		let min = null
		let max = null
		const unit = $('#measureunitselect').val()
		let i, distanceToPrev, timei, timej, delta

		let j = 0
		let distWindow = 0

		let distanceFromStart = 0
		latlngs[0].push(0)

		// if there is a missing time : pace is 0
		for (i = 0; i < latlngs.length; i++) {
			if (!times[i]) {
				for (j = 1; j < latlngs.length; j++) {
					latlngs[j].push(0)
				}
				return
			}
		}

		for (i = 1; i < latlngs.length; i++) {
			distanceToPrev = gpxpod.map.distance([latlngs[i - 1][0], latlngs[i - 1][1]], [latlngs[i][0], latlngs[i][1]])
			if (unit === 'metric') {
				distanceToPrev = distanceToPrev / 1000
			} else if (unit === 'nautical') {
				distanceToPrev = METERSTONAUTICALMILES * distanceToPrev
			} else if (unit === 'english') {
				distanceToPrev = METERSTOMILES * distanceToPrev
			}
			distanceFromStart = distanceFromStart + distanceToPrev
			distWindow = distWindow + distanceToPrev

			if (distanceFromStart < 1) {
				latlngs[i].push(0)
			} else {
				// get the pace (time to do the last km/mile) for this point
				while (j < i && distWindow > 1) {
					j++
					if (unit === 'metric') {
						distWindow = distWindow - (gpxpod.map.distance([latlngs[j - 1][0], latlngs[j - 1][1]], [latlngs[j][0], latlngs[j][1]]) / 1000)
					} else if (unit === 'nautical') {
						distWindow = distWindow - (METERSTONAUTICALMILES * gpxpod.map.distance([latlngs[j - 1][0], latlngs[j - 1][1]], [latlngs[j][0], latlngs[j][1]]))
					} else if (unit === 'english') {
						distWindow = distWindow - (METERSTOMILES * gpxpod.map.distance([latlngs[j - 1][0], latlngs[j - 1][1]], [latlngs[j][0], latlngs[j][1]]))
					}
				}
				// the j to consider is j-1 (when dist between j and i is more than 1)
				timej = moment(times[j - 1])
				timei = moment(times[i])
				delta = timei.diff(timej) / 1000 / 60
				if (delta !== Infinity) {
					if (min === null || delta < min) {
						min = delta
					}
					if (max === null || delta > max) {
						max = delta
					}
				} else {
					delta = 0
				}
				latlngs[i].push(delta)
			}
		}
		minMax.push(min)
		minMax.push(max)
	}

	function addTrackDraw(gpx, tid, withElevation, forcedColor = null, collectPoints = true) {
		deleteOnHover()

		let lat, lon, name, cmt, desc, sym, ele, time, linkText, linkUrl, linkHTML
		let latlngs, times, wpts, exts, ext, tooltipText
		let l, data, bl, arrows, m, pname
		const unit = $('#measureunitselect').val()
		let yUnit, xUnit
		if (unit === 'metric') {
			xUnit = 'km'
			yUnit = 'm'
		} else if (unit === 'english') {
			xUnit = 'mi'
			yUnit = 'ft'
		} else if (unit === 'nautical') {
			xUnit = 'nmi'
			yUnit = 'm'
		}

		const lineBorder = $('#linebordercheck').is(':checked')
		const rteaswpt = $('#rteaswpt').is(':checked')
		const arrow = $('#arrowcheck').is(':checked')
		// choose color
		let color
		const chartTitle = t('gpxpod', 'altitude/distance')
		if (forcedColor !== null) {
			color = forcedColor
		} else {
			color = colorCode[colors[++lastColorUsed % colors.length]]
		}
		setTrackCss(tid, color)
		const coloredTooltipClass = 'mytooltip tooltip' + tid

		const path = decodeURIComponent(gpxpod.markers[tid][FOLDER]).replace(/^\/$/, '')
				   + '/' + decodeURIComponent(gpxpod.markers[tid][NAME])

		const gpxp = $.parseXML(gpx.replace(/version="1.1"/, 'version="1.0"'))
		const gpxx = $(gpxp).find('gpx')

		// count the number of lines and point
		// const nbPoints = gpxx.find('>wpt').length
		const nbLines = gpxx.find('>trk').length + gpxx.find('>rte').length

		let el
		if (withElevation) {
			removeElevation()
			if (nbLines > 0) {
				el = L.control.elevation({
					position: 'bottomleft',
					height: 100,
					width: 700,
					margins: {
						top: 10,
						right: 50,
						bottom: 33,
						left: 50,
					},
					yUnit,
					xUnit,
					title: chartTitle + ' : ' + path,
					detached: false,
					followMarker: false,
					lazyLoadJS: false,
					timezone: $('#tzselect').val(),
					theme: 'steelblue-theme',
					showTime: 'hover-timestamp',
				})
				el.addTo(gpxpod.map)
				gpxpod.elevationLayer = el
				gpxpod.elevationTrackId = tid
				gpxpod.closeElevationButton.addTo(gpxpod.map)
				$('<div id="hover-timestamp"></div>').insertAfter(gpxpod.closeElevationButton._container)
			}
		}

		if ((!(tid in gpxpod.gpxlayers))) {
			const whatToDraw = $('#trackwaypointdisplayselect').val()
			const weight = parseInt($('#lineweight').val())
			const waypointStyle = getWaypointStyle()
			const tooltipStyle = getTooltipStyle()
			const symbolOverwrite = getSymbolOverwrite()

			gpxpod.points[tid] = {}

			const gpxlayer = { color }
			gpxlayer.layerOutlines = L.layerGroup()
			gpxlayer.layer = L.featureGroup()

			// const fileDesc = gpxx.find('>metadata>desc').text()
			let mm, popupText

			if (whatToDraw === 'trw' || whatToDraw === 'w') {
				gpxx.find('wpt').each(function() {
					lat = $(this).attr('lat')
					lon = $(this).attr('lon')
					name = $(this).find('name').text()
					cmt = $(this).find('cmt').text()
					desc = $(this).find('desc').text()
					sym = $(this).find('sym').text()
					ele = $(this).find('ele').text()
					time = $(this).find('time').text()
					linkText = $(this).find('link text').text()
					linkUrl = $(this).find('link').attr('href')

					mm = L.marker(
						[lat, lon],
						{
							icon: symbolIcons[waypointStyle],
						}
					)
					if (tooltipStyle === 'p') {
						mm.bindTooltip(brify(name, 20), { permanent: true, className: coloredTooltipClass })
					} else {
						mm.bindTooltip(brify(name, 20), { className: coloredTooltipClass })
					}

					popupText = '<h3 style="text-align:center;">' + escapeHtml(name) + '</h3><hr/>'
									+ t('gpxpod', 'Track') + ' : ' + escapeHtml(path) + '<br/>'
					if (linkText && linkUrl) {
						popupText = popupText
									+ t('gpxpod', 'Link') + ' : <a href="' + escapeHtml(linkUrl) + '" title="' + escapeHtml(linkUrl) + '" target="_blank">' + escapeHtml(linkText) + '</a><br/>'
					}
					if (ele !== '') {
						popupText = popupText + t('gpxpod', 'Elevation') + ' : '
									+ ele + 'm<br/>'
					}
					popupText = popupText + t('gpxpod', 'Latitude') + ' : ' + lat + '<br/>'
								+ t('gpxpod', 'Longitude') + ' : ' + lon + '<br/>'
					if (cmt !== '') {
						popupText = popupText
									+ t('gpxpod', 'Comment') + ' : ' + cmt + '<br/>'
					}
					if (desc !== '') {
						popupText = popupText
									+ t('gpxpod', 'Description') + ' : ' + desc + '<br/>'
					}
					if (sym !== '') {
						popupText = popupText
									+ t('gpxpod', 'Symbol name') + ' : ' + sym
					}
					if (symbolOverwrite && sym) {
						if (sym in symbolIcons) {
							mm.setIcon(symbolIcons[sym])
						} else {
							mm.setIcon(L.divIcon({
								className: 'unknown',
								iconAnchor: [12, 12],
							}))
						}
					}
					mm.bindPopup(popupText)
					gpxlayer.layer.addLayer(mm)
				})
			}

			let li = 0
			if (whatToDraw === 'trw' || whatToDraw === 't') {
				gpxx.find('trk').each(function() {
					name = $(this).find('>name').text()
					cmt = $(this).find('>cmt').text()
					desc = $(this).find('>desc').text()
					linkText = $(this).find('link text').text()
					linkUrl = $(this).find('link').attr('href')
					$(this).find('trkseg').each(function() {
						latlngs = []
						times = []
						exts = []
						$(this).find('trkpt').each(function() {
							lat = $(this).attr('lat')
							lon = $(this).attr('lon')
							if (!lat || !lon) {
								return
							}
							ele = $(this).find('ele').text()
							if (unit === 'english') {
								ele = parseFloat(ele) * METERSTOFOOT
							}
							time = $(this).find('time').text()
							times.push(time)
							if (ele !== '') {
								latlngs.push([lat, lon, ele])
							} else {
								latlngs.push([lat, lon])
							}
							ext = {}
							$(this).find('extensions').children().each(function() {
								ext[$(this).prop('tagName').toLowerCase()] = $(this).text()
							})
							exts.push(ext)
						})
						l = L.polyline(latlngs, {
							className: 'poly' + tid,
							weight,
						})
						popupText = gpxpod.markersPopupTxt[tid].popup
						if (cmt !== '') {
							popupText = popupText + '<p class="combutton" combutforfeat="'
										+ escapeHtml(tid) + escapeHtml(name)
										+ '" style="margin:0; cursor:pointer;">' + t('gpxpod', 'Comment')
										+ ' <i class="fa fa-expand"></i></p>'
										+ '<p class="comtext" style="display:none; margin:0; cursor:pointer;" comforfeat="'
										+ escapeHtml(tid) + escapeHtml(name) + '">'
										+ escapeHtml(cmt) + '</p>'
						}
						if (desc !== '') {
							popupText = popupText + '<p class="descbutton" descbutforfeat="'
										+ escapeHtml(tid) + escapeHtml(name)
										+ '" style="margin:0; cursor:pointer;">Description <i class="fa fa-expand"></i></p>'
										+ '<p class="desctext" style="display:none; margin:0; cursor:pointer;" descforfeat="'
										+ escapeHtml(tid) + escapeHtml(name) + '">'
										+ escapeHtml(desc) + '</p>'
						}
						linkHTML = ''
						if (linkText && linkUrl) {
							linkHTML = '<a href="' + escapeHtml(linkUrl) + '" title="' + escapeHtml(linkUrl) + '" target="_blank">' + escapeHtml(linkText) + '</a>'
						}
						popupText = popupText.replace('<li>' + escapeHtml(name) + '</li>',
							'<li><b>' + escapeHtml(name) + ' (' + linkHTML + ')</b></li>')
						l.bindPopup(
							popupText,
							{
								autoPan: true,
								autoClose: true,
								closeOnClick: true,
							}
						)
						tooltipText = decodeURIComponent(gpxpod.markers[tid][NAME])
						if (decodeURIComponent(gpxpod.markers[tid][NAME]) !== name) {
							tooltipText = tooltipText + '<br/>' + escapeHtml(name)
						}
						if (tooltipStyle === 'p') {
							l.bindTooltip(tooltipText, { permanent: true, className: coloredTooltipClass })
						} else {
							l.bindTooltip(tooltipText, { sticky: true, className: coloredTooltipClass })
						}
						if (withElevation) {
							data = l.toGeoJSON()
							if (times.length === data.geometry.coordinates.length) {
								for (let i = 0; i < data.geometry.coordinates.length; i++) {
									data.geometry.coordinates[i].push(times[i])
								}
							}
							if (data.geometry.coordinates.length !== 0) {
								el.addData(data, l)
							}
						}
						// border layout
						if (lineBorder) {
							bl = L.polyline(latlngs,
								{ opacity: 1, weight: parseInt(weight * 1.6), color: 'black' })
							gpxlayer.layerOutlines.addLayer(bl)
							if (collectPoints) {
								bl.on('mouseover', lineOver)
								bl.on('mouseout', lineOut)
							}
							bl.on('mouseover', function() {
								hoverStyle.weight = parseInt(2 * weight)
								defaultStyle.weight = weight
								l.setStyle(hoverStyle)
								defaultStyle.color = color
								gpxpod.gpxlayers[tid].layerOutlines.eachLayer(layerBringToFront)
								// layer.bringToFront()
								gpxpod.gpxlayers[tid].layer.bringToFront()
							})
							bl.on('mouseout', function() {
								l.setStyle(defaultStyle)
							})
							if (tooltipStyle !== 'p') {
								bl.bindTooltip(tooltipText, { sticky: true, className: coloredTooltipClass })
							}
							bl.tid = tid
							bl.li = li
						}
						if (collectPoints) {
							l.on('mouseover', lineOver)
							l.on('mouseout', lineOut)
						}
						l.on('mouseover', function() {
							hoverStyle.weight = parseInt(2 * weight)
							defaultStyle.weight = weight
							l.setStyle(hoverStyle)
							defaultStyle.color = color
							if (lineBorder) {
								gpxpod.gpxlayers[tid].layerOutlines.eachLayer(layerBringToFront)
							}
							// layer.bringToFront()
							gpxpod.gpxlayers[tid].layer.bringToFront()
						})
						l.on('mouseout', function() {
							l.setStyle(defaultStyle)
						})
						l.tid = tid
						l.li = li
						if (collectPoints) {
							gpxpod.points[tid][li] = {
								coords: latlngs,
								times,
								exts,
							}
						}

						gpxlayer.layer.addLayer(l)

						if (arrow) {
							arrows = L.polylineDecorator(l)
							arrows.setPatterns([{
								offset: 30,
								repeat: 40,
								symbol: L.Symbol.arrowHead({
									pixelSize: 15 + weight,
									polygon: false,
									pathOptions: {
										stroke: true,
										color,
										opacity: 1,
										weight: parseInt(weight * 0.6),
									},
								}),
							}])
							gpxlayer.layer.addLayer(arrows)
						}
						li++
					})
				})
			}
			if (whatToDraw === 'trw' || whatToDraw === 'r') {
				// ROUTES
				gpxx.find('rte').each(function() {
					name = $(this).find('>name').text()
					cmt = $(this).find('>cmt').text()
					desc = $(this).find('>desc').text()
					linkText = $(this).find('link text').text()
					linkUrl = $(this).find('link').attr('href')
					latlngs = []
					times = []
					exts = []
					wpts = null
					if (rteaswpt) {
						wpts = L.featureGroup()
					}
					$(this).find('rtept').each(function() {
						lat = $(this).attr('lat')
						lon = $(this).attr('lon')
						if (!lat || !lon) {
							return
						}
						ele = $(this).find('ele').text()
						if (unit === 'english') {
							ele = parseFloat(ele) * METERSTOFOOT
						}
						time = $(this).find('time').text()
						times.push(time)
						if (ele !== '') {
							latlngs.push([lat, lon, ele])
						} else {
							latlngs.push([lat, lon])
						}
						if (rteaswpt) {
							m = L.marker([lat, lon], {
								icon: symbolIcons[waypointStyle],
							})
							pname = $(this).find('name').text()
							if (pname) {
								m.bindTooltip(pname, { permanent: false, className: coloredTooltipClass })
							}
							wpts.addLayer(m)
						}
						ext = {}
						$(this).find('extensions').children().each(function() {
							ext[$(this).prop('tagName').toLowerCase()] = $(this).text()
						})
						exts.push(ext)
					})
					l = L.polyline(latlngs, {
						className: 'poly' + tid,
						weight,
					})
					popupText = gpxpod.markersPopupTxt[tid].popup
					if (cmt !== '') {
						popupText = popupText + '<p class="combutton" combutforfeat="'
									+ escapeHtml(tid) + escapeHtml(name)
									+ '" style="margin:0; cursor:pointer;">' + t('gpxpod', 'Comment')
									+ ' <i class="fa fa-expand"></i></p>'
									+ '<p class="comtext" style="display:none; margin:0; cursor:pointer;" comforfeat="'
									+ escapeHtml(tid) + escapeHtml(name) + '">'
									+ escapeHtml(cmt) + '</p>'
					}
					if (desc !== '') {
						popupText = popupText + '<p class="descbutton" descbutforfeat="'
									+ escapeHtml(tid) + escapeHtml(name)
									+ '" style="margin:0; cursor:pointer;">Description <i class="fa fa-expand"></i></p>'
									+ '<p class="desctext" style="display:none; margin:0; cursor:pointer;" descforfeat="'
									+ escapeHtml(tid) + escapeHtml(name) + '">'
									+ escapeHtml(desc) + '</p>'
					}
					linkHTML = ''
					if (linkText && linkUrl) {
						linkHTML = '<a href="' + escapeHtml(linkUrl) + '" title="' + escapeHtml(linkUrl) + '" target="_blank">' + escapeHtml(linkText) + '</a>'
					}
					popupText = popupText.replace('<li>' + escapeHtml(name) + '</li>',
												  '<li><b>' + escapeHtml(name) + '</b></li>')
					l.bindPopup(
						popupText,
						{
							autoPan: true,
							autoClose: true,
							closeOnClick: true,
						}
					)
					tooltipText = decodeURIComponent(gpxpod.markers[tid][NAME])
					if (decodeURIComponent(gpxpod.markers[tid][NAME]) !== name) {
						tooltipText = tooltipText + '<br/>' + escapeHtml(name)
					}
					if (tooltipStyle === 'p') {
						l.bindTooltip(tooltipText, { permanent: true, className: coloredTooltipClass })
					} else {
						l.bindTooltip(tooltipText, { sticky: true, className: coloredTooltipClass })
					}
					if (withElevation) {
						data = l.toGeoJSON()
						if (times.length === data.geometry.coordinates.length) {
							for (let i = 0; i < data.geometry.coordinates.length; i++) {
								data.geometry.coordinates[i].push(times[i])
							}
						}
						if (data.geometry.coordinates.length !== 0) {
							el.addData(data, l)
						}
					}
					// border layout
					if (lineBorder) {
						const bl = L.polyline(latlngs,
							{ opacity: 1, weight: parseInt(weight * 1.6), color: 'black' })
						gpxlayer.layerOutlines.addLayer(bl)
						if (collectPoints) {
							bl.on('mouseover', lineOver)
							bl.on('mouseout', lineOut)
						}
						bl.on('mouseover', function() {
							hoverStyle.weight = parseInt(2 * weight)
							defaultStyle.weight = weight
							l.setStyle(hoverStyle)
							defaultStyle.color = color
							gpxpod.gpxlayers[tid].layerOutlines.eachLayer(layerBringToFront)
							// layer.bringToFront()
							gpxpod.gpxlayers[tid].layer.bringToFront()
						})
						bl.on('mouseout', function() {
							l.setStyle(defaultStyle)
						})
						if (tooltipStyle !== 'p') {
							bl.bindTooltip(tooltipText, { sticky: true, className: coloredTooltipClass })
						}
						bl.tid = tid
						bl.li = li
					}
					l.on('mouseover', function() {
						hoverStyle.weight = parseInt(2 * weight)
						defaultStyle.weight = weight
						l.setStyle(hoverStyle)
						defaultStyle.color = color
						if (lineBorder) {
							gpxpod.gpxlayers[tid].layerOutlines.eachLayer(layerBringToFront)
						}
						// layer.bringToFront()
						gpxpod.gpxlayers[tid].layer.bringToFront()
					})
					l.on('mouseout', function() {
						l.setStyle(defaultStyle)
					})
					if (collectPoints) {
						l.on('mouseover', lineOver)
						l.on('mouseout', lineOut)
					}
					l.tid = tid
					l.li = li
					if (collectPoints) {
						gpxpod.points[tid][li] = {
							coords: latlngs,
							times,
							exts,
						}
					}

					gpxlayer.layer.addLayer(l)
					if (rteaswpt) {
						gpxlayer.layer.addLayer(wpts)
					}

					if (arrow) {
						arrows = L.polylineDecorator(l)
						arrows.setPatterns([{
							offset: 30,
							repeat: 40,
							symbol: L.Symbol.arrowHead({
								pixelSize: 15 + weight,
								polygon: false,
								pathOptions: {
									stroke: true,
									color,
									opacity: 1,
									weight: parseInt(weight * 0.6),
								},
							}),
						}])
						gpxlayer.layer.addLayer(arrows)
					}
					li++
				})
			}

			gpxlayer.layerOutlines.addTo(gpxpod.map)
			gpxlayer.layer.addTo(gpxpod.map)
			gpxpod.gpxlayers[tid] = gpxlayer
			gpxpod.gpxlayers[tid].color = color

			if ($('#autozoomcheck').is(':checked')) {
				zoomOnAllDrawnTracks()
			}

			delete gpxpod.currentAjaxSources[tid]
			delete gpxpod.currentAjaxPercentage[tid]
			updateTrackListFromBounds()
			if ($('#openpopupcheck').is(':checked') && nbLines > 0) {
				// open popup on the marker position,
				// works better than opening marker popup
				// because the clusters avoid popup opening when marker is
				// not visible because it's grouped
				const pop = L.popup({
					autoPan: true,
					autoClose: true,
					closeOnClick: true,
				})
				pop.setContent(gpxpod.markersPopupTxt[tid].popup)
				pop.setLatLng(gpxpod.markersPopupTxt[tid].marker.getLatLng())
				pop.openOn(gpxpod.map)
			}
		}
	}

	function removeTrackDraw(tid) {
		if (tid in gpxpod.gpxlayers
			&& 'layer' in gpxpod.gpxlayers[tid]
			&& gpxpod.map.hasLayer(gpxpod.gpxlayers[tid].layer)
		) {
			gpxpod.map.removeLayer(gpxpod.gpxlayers[tid].layer)
			if (gpxpod.gpxlayers[tid].layerOutlines !== null) {
				gpxpod.map.removeLayer(gpxpod.gpxlayers[tid].layerOutlines)
			}
			delete gpxpod.gpxlayers[tid].layer
			delete gpxpod.gpxlayers[tid].layerOutlines
			delete gpxpod.gpxlayers[tid].color
			delete gpxpod.gpxlayers[tid]
			delete gpxpod.points[tid]
			if (gpxpod.overMarker) {
				gpxpod.overMarker.remove()
			}
			updateTrackListFromBounds()
			if (gpxpod.elevationTrackId === tid) {
				removeElevation()
			}
		}
	}

	/// ///////////// COLOR PICKER /////////////////////

	function showColorPicker(tid) {
		gpxpod.currentColorTrackId = tid
		let currentColor = gpxpod.gpxlayers[tid].color
		if (currentColor in colorCode) {
			currentColor = colorCode[currentColor]
		}
		$('#colorinput').val(currentColor)
		$('#colorinput').click()
	}

	function okColor() {
		const color = $('#colorinput').val()
		const tid = gpxpod.currentColorTrackId
		const checkbox = $('input[tid="' + tid + '"]')
		setTrackCss(tid, color)
		gpxpod.gpxlayers[tid].color = color
		checkbox.parent().css('background', color)
	}

	function setTrackCss(tid, colorcode, shape = 'r') {
		const rgbc = hexToRgb(colorcode)
		const opacity = 1
		let textcolor = 'black'
		if (rgbc.r + rgbc.g + rgbc.b < 3 * 80) {
			textcolor = 'white'
		}
		let background = 'background: rgba(' + rgbc.r + ', ' + rgbc.g + ', ' + rgbc.b + ', 0);'
		let border = 'border-color: rgba(' + rgbc.r + ', ' + rgbc.g + ', ' + rgbc.b + ', ' + opacity + ');'
		if (shape !== 't') {
			background = 'background: rgba(' + rgbc.r + ', ' + rgbc.g + ', ' + rgbc.b + ', ' + opacity + ');'
			border = 'border: 1px solid grey;'
		}
		$('style[track="' + tid + '"]').remove()
		$('<style track="' + tid + '">'
			+ '.color' + tid + ' { '
			+ background
			+ border
			+ 'color: ' + textcolor + '; font-weight: bold;'
			+ ' }'
			+ '.poly' + tid + ' {'
			+ 'stroke: ' + colorcode + ';'
			+ 'opacity: ' + opacity + ';'
			+ '}'
			+ '.tooltip' + tid + ' {'
			+ 'border: 3px solid ' + colorcode + ';'
			+ '}</style>').appendTo('body')
	}

	/// ///////////// VARIOUS /////////////////////

	function clearCache() {
		const keysToRemove = []
		for (const k in gpxpod.gpxCache) {
			keysToRemove.push(k)
		}

		for (let i = 0; i < keysToRemove.length; i++) {
			delete gpxpod.gpxCache[keysToRemove[i]]
		}
		gpxpod.gpxCache = {}
	}

	// if gpxedit_version > one.two.three and we're connected and not on public page
	function isGpxeditCompliant(one, two, three) {
		const ver = $('p#gpxedit_version').html()
		if (ver !== '') {
			const vspl = ver.split('.')
			return (parseInt(vspl[0]) > one
					|| parseInt(vspl[1]) > two
					|| parseInt(vspl[2]) > three
			)
		} else {
			return false
		}
	}

	// if gpxmotion_version > one.two.three and we're connected and not on public page
	function isGpxmotionCompliant(one, two, three) {
		const ver = $('p#gpxmotion_version').html()
		if (ver !== '') {
			const vspl = ver.split('.')
			return (parseInt(vspl[0]) > one
					|| parseInt(vspl[1]) > two
					|| parseInt(vspl[2]) > three
			)
		} else {
			return false
		}
	}

	function getWaypointStyle() {
		return $('#waypointstyleselect').val()
	}

	function getTooltipStyle() {
		return $('#tooltipstyleselect').val()
	}

	function getSymbolOverwrite() {
		return $('#symboloverwrite').is(':checked')
	}

	function correctElevation(link) {
		if (gpxpod.currentHoverSource !== null) {
			gpxpod.currentHoverSource.cancel()
			gpxpod.currentHoverSource = null
			hideAnimation()
		}
		const tid = link.attr('tid')
		const smooth = (link.attr('class') === 'csrtms')
		showCorrectingAnimation()
		const req = {
			path: decodeURIComponent(gpxpod.markers[tid][FOLDER]).replace(/^\/$/, '')
				   + '/' + decodeURIComponent(gpxpod.markers[tid][NAME]),
			smooth,
		}
		const url = generateUrl('/apps/gpxpod/processTrackElevations')

		gpxpod.currentlyCorrecting = true
		axios.post(url, req).then((response) => {
			if (response.data.done) {
				// erase track cache to be sure it will be reloaded
				delete gpxpod.gpxCache[tid]
				// processed successfully, we reload folder
				$('#subfolderselect').change()
			} else {
				OC.Notification.showTemporary(response.data.message)
			}
		}).catch((error) => {
			console.error(error)
		}).then(() => {
			hideAnimation()
			gpxpod.currentlyCorrecting = false
		})
	}

	/*
	 * send ajax request to clean .marker,
	 * .geojson and .geojson.colored files
	 */
	function askForClean(forwhat) {
		// ask to clean by ajax
		const req = {
			forall: forwhat,
		}
		const url = generateUrl('/apps/gpxpod/cleanMarkersAndGeojsons')
		showDeletingAnimation()
		$('#clean_results').html('')
		axios.post(url, req).then((response) => {
			$('#clean_results').html(
				'Those files were deleted :\n<br/>'
				+ response.data.deleted + '\n<br/>'
				+ 'Problems :\n<br/>' + response.data.problems
			)
		}).then(() => {
			hideAnimation()
		})
	}

	function cleanDb() {
		const req = {}
		const url = generateUrl('/apps/gpxpod/cleanDb')
		showDeletingAnimation()
		axios.post(url, req).then((response) => {
			if (response.data.done === 1) {
				OC.Notification.showTemporary(t('gpxpod', 'Database has been cleaned'))
			} else {
				OC.Notification.showTemporary(t('gpxpod', 'Impossible to clean database'))
			}
		}).then(() => {
			hideAnimation()
		})
	}

	/*
	 * If timezone changes, we regenerate popups
	 * by reloading current folder
	 */
	function tzChanged() {
		stopGetMarkers()
		chooseDirSubmit()

		// if it's a public link, we display it again to update dates
		if (pageIsPublicFolder()) {
			displayPublicDir()
		} else if (pageIsPublicFile()) {
			displayPublicTrack()
		}
	}

	function measureUnitChanged() {
		const unit = $('#measureunitselect').val()
		if (unit === 'metric') {
			$('.distanceunit').text('m')
			$('.elevationunit').text('m')
		} else if (unit === 'english') {
			$('.distanceunit').text('mi')
			$('.elevationunit').text('ft')
		} else if (unit === 'nautical') {
			$('.distanceunit').text('nmi')
			$('.elevationunit').text('m')
		}
	}

	function compareSelectedTracks() {
		// build url list
		const params = []
		let i = 1
		let name, folder, path
		$('#gpxtable tbody input[type=checkbox]:checked').each(function() {
			const aa = $(this).parent().parent().find('td.trackname a.tracklink')
			name = aa.text()
			folder = decodeURIComponent($(this).parent().parent().attr('folder'))
			path = folder.replace(/^\/$/, '') + '/' + name
			params.push('path' + i + '=' + path)
			i++
		})

		// go to new gpxcomp tab
		const win = window.open(
			gpxpod.gpxcompRootUrl + '?' + params.join('&'),
			'_blank'
		)
		if (win) {
			// Browser has allowed it to be opened
			win.focus()
		} else {
			// Broswer has blocked it
			OC.dialogs.alert('Allow popups for this page in order'
							 + ' to open comparison tab/window.')
		}
	}

	/*
	 * get key events
	 */
	function checkKey(e) {
		e = e || window.event
		const kc = e.keyCode
		// console.log(kc)

		if (kc === 161 || kc === 223) {
			e.preventDefault()
			gpxpod.minimapControl._toggleDisplayButtonClicked()
		}
		if (kc === 60 || kc === 220) {
			e.preventDefault()
			$('#sidebar').toggleClass('collapsed')
		}
	}

	function getUrlParameter(sParam) {
		const sPageURL = window.location.search.substring(1)
		const sURLVariables = sPageURL.split('&')
		for (let i = 0; i < sURLVariables.length; i++) {
			const sParameterName = sURLVariables[i].split('=')
			if (sParameterName[0] === sParam) {
				return decodeURIComponent(sParameterName[1])
			}
		}
	}

	/*
	 * the directory selection has been changed
	 */
	function chooseDirSubmit(processAll = false) {
		// in all cases, we clean the view (marker clusters, table)
		$('#gpxlist').html('')
		removeMarkers()
		removePictures()

		setFileNumber(0, 0)

		const recursive = $('#recursivetrack').is(':checked') ? '1' : '0'

		gpxpod.subfolder = decodeURIComponent($('#subfolderselect').val())
		const sel = $('#subfolderselect').prop('selectedIndex')
		if (sel === 0) {
			$('label[for=subfolderselect]').html(
				t('gpxpod', 'Folder')
				+ ' :'
			)
			$('#folderbuttons').hide()
			return false
		} else {
			$('#folderbuttons').show()
		}
		// we put the public link to folder
		$('.publink[type=folder]').attr('path', gpxpod.subfolder)
		$('.publink[type=folder]').attr('title',
			t('gpxpod', 'Public link to \'{folder}\' which will work only if this folder is shared in \'files\' app by public link without password', { folder: gpxpod.subfolder }))

		gpxpod.map.closePopup()
		clearCache()
		// get markers by ajax
		const req = {
			subfolder: gpxpod.subfolder,
			processAll,
			recursive,
		}
		const url = generateUrl('/apps/gpxpod/getmarkers')
		showLoadingMarkersAnimation()
		axios.post(url, req).then((response) => {
			if (response.data.error !== '') {
				OC.dialogs.alert(response.data.error,
								 'Server error')
			} else {
				getAjaxPicturesSuccess(response.data.pictures)
				getAjaxMarkersSuccess(response.data.markers)
				setFileNumber(Object.keys(gpxpod.markers).length, gpxpod.oms.getLayers().length)
				selectTrackFromUrlParam()

				if ($('#drawallcheck').is(':checked')) {
					$('input.drawtrack').each(function() { $(this).prop('checked', true) })
					$('input.drawtrack').each(function() { $(this).change() })
				}
			}
		}).catch((error) => {
			console.error(error)
		}).then(() => {
			hideAnimation()
			gpxpod.currentMarkerAjax = null
		})
	}

	/// ///////////// HOVER /////////////////////

	function displayOnHover(tid) {
		let url
		if (gpxpod.currentHoverSource !== null) {
			gpxpod.currentHoverSource.cancel()
			gpxpod.currentHoverSource = null
			hideAnimation()
		}

		if ($('#simplehovercheck').is(':checked')) {
			const m = gpxpod.markers[tid]
			addSimplifiedHoverTrackDraw(m[SHORTPOINTLIST], tid)
		} else {
			// use the geojson cache if this track has already been loaded
			if (tid in gpxpod.gpxCache) {
				addHoverTrackDraw(gpxpod.gpxCache[tid], tid)
			} else {
				// otherwise load it in ajax
				const req = {
					path: decodeURIComponent(gpxpod.markers[tid][FOLDER]).replace(/^\/$/, '')
						  + '/' + decodeURIComponent(gpxpod.markers[tid][NAME]),
				}
				// if this is a public folder link page
				if (pageIsPublicFolder()) {
					req.username = gpxpod.username
					url = generateUrl('/apps/gpxpod/getpublicgpx')
				} else {
					url = generateUrl('/apps/gpxpod/getgpx')
				}
				showLoadingAnimation()
				gpxpod.currentHoverSource = axios.CancelToken.source()
				axios.post(url, req, {
					cancelToken: gpxpod.currentHoverSource.token,
					onDownloadProgress: (e) => {
						if (e.lengthComputable) {
							const percentComplete = e.loaded / e.total * 100
							$('#loadingpc').text(parseInt(percentComplete) + '%')
						}
					},
				}).then((response) => {
					gpxpod.gpxCache[tid] = response.data.content
					addHoverTrackDraw(response.data.content, tid)
					hideAnimation()
				}).catch((error) => {
					if (axios.isCancel(error)) {
						console.debug('refresh was canceled')
					} else {
						console.error(error)
					}
				}).then(() => {
					gpxpod.currentHoverSource = null
				})
			}
		}
	}

	function addSimplifiedHoverTrackDraw(pointList, tid) {
		deleteOnHover()

		if (gpxpod.insideTr) {
			const lineBorder = $('#linebordercheck').is(':checked')
			const arrow = $('#arrowcheck').is(':checked')
			const weight = parseInt($('#lineweight').val())

			// eslint-disable-next-line
			gpxpod.currentHoverLayer = new L.layerGroup()

			if (lineBorder) {
				gpxpod.currentHoverLayerOutlines.addLayer(L.polyline(
					pointList,
					{ opacity: 1, weight: parseInt(weight * 1.6), color: 'black' }
				))
			}
			const l = L.polyline(pointList, {
				weight,
				style: { color: 'blue', opacity: 1 },
			})
			if (arrow) {
				const arrows = L.polylineDecorator(l)
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
							weight: parseInt(weight * 0.6),
						},
					}),
				}])
				gpxpod.currentHoverLayer.addLayer(arrows)
			}
			gpxpod.currentHoverLayer.addLayer(l)

			if (lineBorder) {
				gpxpod.currentHoverLayerOutlines.addTo(gpxpod.map)
			}
			gpxpod.currentHoverLayer.addTo(gpxpod.map)
		}
	}

	function addHoverTrackDraw(gpx, tid) {
		deleteOnHover()

		if (gpxpod.insideTr) {
			const gpxp = $.parseXML(gpx.replace(/version="1.1"/, 'version="1.0"'))
			const gpxx = $(gpxp).find('gpx')

			const lineBorder = $('#linebordercheck').is(':checked')
			const rteaswpt = $('#rteaswpt').is(':checked')
			const arrow = $('#arrowcheck').is(':checked')
			const whatToDraw = $('#trackwaypointdisplayselect').val()
			const weight = parseInt($('#lineweight').val())
			const waypointStyle = getWaypointStyle()
			const tooltipStyle = getTooltipStyle()
			const symbolOverwrite = getSymbolOverwrite()
			let tooltipText

			let lat, lon, name, sym
			let mm, latlngs, l, arrows, wpts, m

			// eslint-disable-next-line
			gpxpod.currentHoverLayer = new L.layerGroup()

			if (whatToDraw === 'trw' || whatToDraw === 'w') {
				gpxx.find('>wpt').each(function() {
					lat = $(this).attr('lat')
					lon = $(this).attr('lon')
					name = $(this).find('name').text()
					// cmt = $(this).find('cmt').text()
					// desc = $(this).find('desc').text()
					sym = $(this).find('sym').text()
					// ele = $(this).find('ele').text()
					// time = $(this).find('time').text()

					mm = L.marker([lat, lon], {
						icon: symbolIcons[waypointStyle],
					})
					if (tooltipStyle === 'p') {
						mm.bindTooltip(brify(name, 20), { permanent: true, className: 'tooltipblue' })
					} else {
						mm.bindTooltip(brify(name, 20), { className: 'tooltipblue' })
					}
					if (symbolOverwrite && sym) {
						if (sym in symbolIcons) {
							mm.setIcon(symbolIcons[sym])
						} else {
							mm.setIcon(L.divIcon({
								className: 'unknown',
								iconAnchor: [12, 12],
							}))
						}
					}
					gpxpod.currentHoverLayer.addLayer(mm)
				})
			}

			if (whatToDraw === 'trw' || whatToDraw === 't') {
				gpxx.find('>trk').each(function() {
					name = $(this).find('>name').text()
					// cmt = $(this).find('>cmt').text()
					// desc = $(this).find('>desc').text()
					$(this).find('trkseg').each(function() {
						latlngs = []
						$(this).find('trkpt').each(function() {
							lat = $(this).attr('lat')
							lon = $(this).attr('lon')
							if (!lat || !lon) {
								return
							}
							latlngs.push([lat, lon])
						})
						l = L.polyline(latlngs, {
							weight,
							style: { color: 'blue', opacity: 1 },
						})
						if (lineBorder) {
							gpxpod.currentHoverLayerOutlines.addLayer(L.polyline(
								latlngs,
								{ opacity: 1, weight: parseInt(weight * 1.6), color: 'black' }
							))
						}
						tooltipText = decodeURIComponent(gpxpod.markers[tid][NAME])
						if (decodeURIComponent(gpxpod.markers[tid][NAME]) !== name) {
							tooltipText = tooltipText + '<br/>' + escapeHtml(name)
						}
						if (tooltipStyle === 'p') {
							l.bindTooltip(tooltipText, { permanent: true, className: 'tooltipblue' })
						}
						if (arrow) {
							arrows = L.polylineDecorator(l)
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
										weight: parseInt(weight * 0.6),
									},
								}),
							}])
							gpxpod.currentHoverLayer.addLayer(arrows)
						}
						gpxpod.currentHoverLayer.addLayer(l)
					})
				})
			}
			if (whatToDraw === 'trw' || whatToDraw === 'r') {
				gpxx.find('>rte').each(function() {
					latlngs = []
					name = $(this).find('>name').text()
					// cmt = $(this).find('>cmt').text()
					// desc = $(this).find('>desc').text()
					wpts = null
					if (rteaswpt) {
						wpts = L.featureGroup()
					}
					$(this).find('rtept').each(function() {
						lat = $(this).attr('lat')
						lon = $(this).attr('lon')
						if (!lat || !lon) {
							return
						}
						latlngs.push([lat, lon])
						if (rteaswpt) {
							m = L.marker([lat, lon], {
								icon: symbolIcons[waypointStyle],
							})
							wpts.addLayer(m)
						}
					})
					l = L.polyline(latlngs, {
						weight,
						style: { color: 'blue', opacity: 1 },
					})

					if (lineBorder) {
						gpxpod.currentHoverLayerOutlines.addLayer(L.polyline(
							latlngs,
							{ opacity: 1, weight: parseInt(weight * 1.6), color: 'black' }
						))
					}
					tooltipText = decodeURIComponent(gpxpod.markers[tid][NAME])
					if (decodeURIComponent(gpxpod.markers[tid][NAME]) !== name) {
						tooltipText = tooltipText + '<br/>' + escapeHtml(name)
					}
					if (tooltipStyle === 'p') {
						l.bindTooltip(tooltipText, { permanent: true, className: 'tooltipblue' })
					}
					if (arrow) {
						arrows = L.polylineDecorator(l)
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
									weight: parseInt(weight * 0.6),
								},
							}),
						}])
						gpxpod.currentHoverLayer.addLayer(arrows)
					}
					gpxpod.currentHoverLayer.addLayer(l)
					if (rteaswpt) {
						gpxpod.currentHoverLayer.addLayer(wpts)
					}
				})
			}

			gpxpod.currentHoverLayerOutlines.addTo(gpxpod.map)
			gpxpod.currentHoverLayer.addTo(gpxpod.map)
		}
	}

	function deleteOnHover() {
		gpxpod.map.removeLayer(gpxpod.currentHoverLayerOutlines)
		gpxpod.currentHoverLayerOutlines.clearLayers()
		if (gpxpod.currentHoverLayer !== null) {
			gpxpod.map.removeLayer(gpxpod.currentHoverLayer)
		}
	}

	/// ///////////// ANIMATIONS /////////////////////

	function showLoadingMarkersAnimation() {
		gpxpod.notificationDialog.addTo(gpxpod.map)
		$('#loadingpc').text('')

		$('#deleteload').hide()
		$('#trackload').hide()
		$('#correctload').hide()
	}

	function showCorrectingAnimation() {
		gpxpod.notificationDialog.addTo(gpxpod.map)
		$('#loadingpc').text('')

		$('#folderload').hide()
		$('#trackload').hide()
		$('#deleteload').hide()
	}

	function showLoadingAnimation() {
		gpxpod.notificationDialog.addTo(gpxpod.map)
		$('#loadingpc').text('')

		$('#folderload').hide()
		$('#correctload').hide()
		$('#deleteload').hide()
	}

	function showDeletingAnimation() {
		gpxpod.notificationDialog.addTo(gpxpod.map)
		$('#loadingpc').text('')

		$('#folderload').hide()
		$('#correctload').hide()
		$('#trackload').hide()
	}

	function hideAnimation() {
		gpxpod.notificationDialog.remove()
	}

	/// ///////////// PICTURES /////////////////////

	function removePictures() {
		gpxpod.oms.clearLayers()
	}

	function getAjaxPicturesSuccess(pictures) {
		let subpath
		let lat, lon
		let token = null
		let tokenspl
		const piclist = $.parseJSON(pictures)
		if (Object.keys(piclist).length > 0) {
			$('#showpicsdiv').show()
		} else {
			$('#showpicsdiv').hide()
		}

		// pictures work in normal page and public dir page
		// but the preview and DL urls are different
		if (pageIsPublicFolder()) {
			tokenspl = gpxpod.token.split('?')
			token = tokenspl[0]
			if (tokenspl.length === 1) {
				subpath = '/'
			} else {
				subpath = decodeURIComponent(tokenspl[1].replace('path=', ''))
			}
		}

		let pdec, fileId, dateTaken, dateStr
		const markers = []
		for (const p in piclist) {
			lat = piclist[p][0]
			lon = piclist[p][1]
			fileId = piclist[p][2]
			dateTaken = parseInt(piclist[p][3])

			pdec = decodeURIComponent(p)

			// MARKERS
			const markerData = {
				lat,
				lng: lon,
				token,
				fileId,
				path: pdec,
				hasPreview: true,
				date: dateTaken,
				pubsubpath: subpath,
			}
			const marker = L.marker(markerData, {
				icon: createPhotoView(markerData),
			})
			marker.data = markerData
			const previewUrl = generatePreviewUrl(marker.data)
			if (dateTaken !== 0) {
				// dateStr = OC.Util.formatDate(marker.data.date * 1000)
				dateStr = moment(marker.data.date * 1000).format('LL')
			} else {
				dateStr = t('gpxpod', 'no date')
			}
			const img = '<img class="photo-tooltip" src=' + previewUrl + '/>'
				+ '<p class="tooltip-photo-date">' + dateStr + '</p>'
				+ '<p class="tooltip-photo-name">' + escapeHtml(basename(markerData.path)) + '</p>'
			marker.bindTooltip(img, {
				permanent: false,
				className: 'leaflet-marker-photo-tooltip',
				direction: 'right',
				offset: L.point(0, -30),
			})
			markers.push(marker)

			gpxpod.oms.addLayers(markers)

		}

		if ($('#showpicscheck').is(':checked')) {
			showPictures()
		}
	}

	function hidePictures() {
		gpxpod.map.removeLayer(gpxpod.oms)
	}

	function showPictures() {
		gpxpod.map.addLayer(gpxpod.oms)
	}

	function picShowChange() {
		if ($('#showpicscheck').is(':checked')) {
			showPictures()
		} else {
			hidePictures()
		}
	}

	/// ///////////// PUBLIC DIR/FILE /////////////////////

	function pageIsPublicFile() {
		const publicgpx = $('p#publicgpx').text()
		const publicdir = $('p#publicdir').text()
		return (publicgpx !== '' && publicdir === '')
	}
	function pageIsPublicFolder() {
		const publicgpx = $('p#publicgpx').text()
		const publicdir = $('p#publicdir').text()
		return (publicgpx === '' && publicdir !== '')
	}
	function pageIsPublicFileOrFolder() {
		const publicgpx = $('p#publicgpx').text()
		const publicdir = $('p#publicdir').text()
		return (publicgpx !== '' || publicdir !== '')
	}

	function getCurrentOptionValues() {
		const optionValues = {}
		optionValues.autopopup = 'y'
		if (!$('#openpopupcheck').is(':checked')) {
			optionValues.autopopup = 'n'
		}
		optionValues.autozoom = 'y'
		if (!$('#autozoomcheck').is(':checked')) {
			optionValues.autozoom = 'n'
		}
		optionValues.showchart = 'y'
		if (!$('#showchartcheck').is(':checked')) {
			optionValues.showchart = 'n'
		}
		optionValues.tableutd = 'y'
		if (!$('#updtracklistcheck').is(':checked')) {
			optionValues.tableutd = 'n'
		}
		const activeLayerName = gpxpod.currentLayerName
		optionValues.layer = encodeURI(activeLayerName)

		optionValues.displaymarkers = 'y'
		if (!$('#displayclusters').is(':checked')) {
			optionValues.displaymarkers = 'n'
		}
		optionValues.showpics = 'y'
		if (!$('#showpicscheck').is(':checked')) {
			optionValues.showpics = 'n'
		}
		optionValues.transp = 'y'
		if (!$('#transparentcheck').is(':checked')) {
			optionValues.transp = 'n'
		}
		optionValues.lineborders = 'y'
		if (!$('#linebordercheck').is(':checked')) {
			optionValues.lineborders = 'n'
		}
		optionValues.simplehover = 'y'
		if (!$('#simplehovercheck').is(':checked')) {
			optionValues.simplehover = 'n'
		}
		optionValues.rteaswpt = 'y'
		if (!$('#rteaswpt').is(':checked')) {
			optionValues.rteaswpt = 'n'
		}
		optionValues.arrow = 'y'
		if (!$('#arrowcheck').is(':checked')) {
			optionValues.arrow = 'n'
		}
		optionValues.sidebar = '0'
		if ($('#enablesidebar').is(':checked')) {
			optionValues.sidebar = '1'
		}
		optionValues.lineweight = $('#lineweight').val()
		optionValues.color = $('#colorcriteria').val()
		optionValues.colorext = $('#colorcriteriaext').val()
		optionValues.tooltipstyle = $('#tooltipstyleselect').val()
		optionValues.draw = encodeURIComponent($('#trackwaypointdisplayselect').val())
		optionValues.waystyle = encodeURIComponent($('#waypointstyleselect').val())
		optionValues.unit = $('#measureunitselect').val()

		return optionValues
	}

	function displayPublicDir() {
		$('#nofolder').hide()
		$('#nofoldertext').hide()

		$('#subfolderselect').hide()
		$('label[for=subfolderselect]').hide()
		$('#folderbuttons').hide()
		const publicdir = $('p#publicdir').html()

		const url = generateUrl('/s/' + gpxpod.token)
		if ($('#pubtitle').length === 0) {
			$('div#logofolder').append(
				'<p id="pubtitle" style="text-align:center; font-size:14px;">'
					+ '<br/>' + t('gpxpod', 'Public folder share') + ' :<br/>'
					+ '<a href="' + url + '" class="toplink" title="'
					+ t('gpxpod', 'download') + '"'
					+ ' target="_blank">' + basename(publicdir) + '</a>'
					+ '</p>'
			)
		}

		const publicmarker = $('p#publicmarker').text()
		const markers = $.parseJSON(publicmarker)
		gpxpod.markers = markers.markers

		genPopupTxt()
		addMarkers()
		updateTrackListFromBounds()

		const pictures = $('p#pictures').html()
		getAjaxPicturesSuccess(pictures)

		setFileNumber(Object.keys(gpxpod.markers).length, gpxpod.oms.getLayers().length)

		if ($('#autozoomcheck').is(':checked')) {
			zoomOnAllMarkers()
		} else {
			gpxpod.map.setView(new L.LatLng(27, 5), 3)
		}
	}

	/*
	 * manage display of public track
	 * hide folder selection
	 * get marker content, generate popup
	 * create a markercluster
	 * and finally draw the track
	 */
	function displayPublicTrack(color = null) {
		$('#nofolder').hide()
		$('#nofoldertext').hide()

		$('#subfolderselect').hide()
		$('#folderbuttons').hide()
		$('label[for=subfolderselect]').hide()
		removeMarkers()
		gpxpod.map.closePopup()

		let publicgpx = $('p#publicgpx').html()
		publicgpx = $('<div/>').html(publicgpx).text()
		const publicmarker = $('p#publicmarker').html()
		const a = $.parseJSON(publicmarker)
		gpxpod.markers = { 1: a }
		genPopupTxt()

		const markerclu = L.markerClusterGroup({ chunkedLoading: true })
		// const encTitle = a[NAME]
		// const encFolder = a[FOLDER]
		const title = decodeURIComponent(a[NAME])
		// const folder = decodeURIComponent(a[FOLDER])
		const tid = 1
		const url = generateUrl('/s/' + gpxpod.token)
		if ($('#pubtitle').length === 0) {
			$('div#logofolder').append(
				'<p id="pubtitle" style="text-align:center; font-size:14px;">'
					+ '<br/>' + t('gpxpod', 'Public file share') + ' :<br/>'
					+ '<a href="' + url + '" class="toplink" title="'
					+ t('gpxpod', 'download') + '"'
					+ ' target="_blank">' + escapeHtml(title) + '</a>'
					+ '</p>'
			)
		}
		const marker = L.marker(L.latLng(a[LAT], a[LON]), { title })
		marker.bindPopup(
			gpxpod.markersPopupTxt[tid].popup,
			{
				autoPan: true,
				autoClose: true,
				closeOnClick: true,
			}
		)
		gpxpod.markersPopupTxt[tid].marker = marker
		markerclu.addLayer(marker)
		if ($('#displayclusters').is(':checked')) {
			gpxpod.map.addLayer(markerclu)
		}
		gpxpod.markerLayer = markerclu
		const showchart = $('#showchartcheck').is(':checked')

		setFileNumber(Object.keys(gpxpod.markers).length, gpxpod.oms.getLayers().length)

		if ($('#colorcriteria').val() !== 'none' && color === null) {
			addColoredTrackDraw(publicgpx, tid, showchart)
		} else {
			removeTrackDraw(tid)
			addTrackDraw(publicgpx, tid, showchart, color)
		}
	}

	/// ///////////// USER TILE SERVERS /////////////////////

	function addTileServer(type) {
		const sname = $('#' + type + 'servername').val()
		const surl = $('#' + type + 'serverurl').val()
		const stoken = $('#' + type + 'token').val()
		const sminzoom = $('#' + type + 'minzoom').val() || ''
		const smaxzoom = $('#' + type + 'maxzoom').val() || ''
		const stransparent = $('#' + type + 'transparent').is(':checked')
		const sopacity = $('#' + type + 'opacity').val() || ''
		const sformat = $('#' + type + 'format').val() || ''
		const sversion = $('#' + type + 'version').val() || ''
		const slayers = $('#' + type + 'layers').val() || ''
		if (sname === '' || surl === '') {
			OC.dialogs.alert(t('gpxpod', 'Server name or server url should not be empty'),
							 t('gpxpod', 'Impossible to add tile server'))
			return
		}
		if ($('#' + type + 'serverlist ul li[servername="' + sname + '"]').length > 0) {
			OC.dialogs.alert(t('gpxpod', 'A server with this name already exists'),
							 t('gpxpod', 'Impossible to add tile server'))
			return
		}
		$('#' + type + 'servername').val('')
		$('#' + type + 'serverurl').val('')
		$('#' + type + 'token').val('')

		const req = {
			servername: sname,
			serverurl: surl,
			type,
			token: stoken,
			layers: slayers,
			version: sversion,
			tformat: sformat,
			opacity: sopacity,
			transparent: stransparent,
			minzoom: sminzoom,
			maxzoom: smaxzoom,
			attribution: '',
		}
		const url = generateUrl('/apps/gpxpod/addTileServer')
		axios.post(url, req).then((response) => {
			if (response.data.done) {
				$('#' + type + 'serverlist ul').prepend(
					'<li style="display:none;" servername="' + escapeHtml(sname)
					+ '" title="' + escapeHtml(surl) + '">'
					+ escapeHtml(sname) + ' <button>'
					+ '<i class="fa fa-trash" aria-hidden="true" style="color:red;"></i> '
					+ t('gpxpod', 'Delete')
					+ '</button></li>'
				)
				$('#' + type + 'serverlist ul li[servername="' + sname + '"]').fadeIn('slow')

				if (type === 'tile') {
					// add tile server in leaflet control
					// eslint-disable-next-line
					var newlayer = new L.TileLayer(surl,
						{ minZoom: sminzoom, maxZoom: smaxzoom, attribution: '' })
					gpxpod.controlLayers.addBaseLayer(newlayer, sname)
					gpxpod.baseLayers[sname] = newlayer
				} else if (type === 'mapboxtile') {
					const newlayer = L.mapboxGL({
						accessToken: stoken || 'token',
						style: surl,
						minZoom: 1,
						maxZoom: 22,
						attribution: '',
					})
					gpxpod.controlLayers.addBaseLayer(newlayer, sname)
					gpxpod.baseLayers[sname] = newlayer
				} else if (type === 'tilewms') {
					// add tile server in leaflet control
					// eslint-disable-next-line
					const newlayer = new L.tileLayer.wms(surl,
						{ format: sformat, version: sversion, layers: slayers, minZoom: sminzoom, maxZoom: smaxzoom, attribution: '' })
					gpxpod.controlLayers.addBaseLayer(newlayer, sname)
					gpxpod.overlayLayers[sname] = newlayer
				}
				if (type === 'overlay') {
					// add tile server in leaflet control
					// eslint-disable-next-line
					const newlayer = new L.TileLayer(surl,
						{ minZoom: sminzoom, maxZoom: smaxzoom, transparent: stransparent, opcacity: sopacity, attribution: '' })
					gpxpod.controlLayers.addOverlay(newlayer, sname)
					gpxpod.baseLayers[sname] = newlayer
				} else if (type === 'overlaywms') {
					// add tile server in leaflet control
					// eslint-disable-next-line
					const newlayer = new L.tileLayer.wms(surl,
						{ layers: slayers, version: sversion, transparent: stransparent, opacity: sopacity, format: sformat, attribution: '', minZoom: sminzoom, maxZoom: smaxzoom })
					gpxpod.controlLayers.addOverlay(newlayer, sname)
					gpxpod.overlayLayers[sname] = newlayer
				}
				OC.Notification.showTemporary(t('gpxpod', 'Tile server "{ts}" has been added', { ts: sname }))
			} else {
				OC.Notification.showTemporary(t('gpxpod', 'Failed to add tile server "{ts}"', { ts: sname }))
			}
		}).catch((error) => {
			console.error(error)
			OC.Notification.showTemporary(t('gpxpod', 'Failed to add tile server "{ts}"', { ts: sname }))
		})
	}

	function deleteTileServer(li, type) {
		const sname = li.attr('servername')
		const req = {
			servername: sname,
			type,
		}
		const url = generateUrl('/apps/gpxpod/deleteTileServer')
		axios.post(url, req).then((response) => {
			if (response.data.done) {
				li.fadeOut('slow', function() {
					li.remove()
				})
				if (type === 'tile') {
					const activeLayerName = gpxpod.currentLayerName
					// if we delete the active layer, first select another
					if (activeLayerName === sname) {
						$('input.leaflet-control-layers-selector').first().click()
					}
					gpxpod.controlLayers.removeLayer(gpxpod.baseLayers[sname])
					delete gpxpod.baseLayers[sname]
				} else {
					gpxpod.controlLayers.removeLayer(gpxpod.overlayLayers[sname])
					delete gpxpod.overlayLayers[sname]
				}
				OC.Notification.showTemporary(t('gpxpod', 'Tile server "{ts}" has been deleted', { ts: sname }))
			} else {
				OC.Notification.showTemporary(t('gpxpod', 'Failed to delete tile server "{ts}"', { ts: sname }))
			}
		}).catch((error) => {
			console.error(error)
			OC.Notification.showTemporary(t('gpxpod', 'Failed to delete tile server "{ts}"', { ts: sname }))
		})
	}

	/// ///////////// SAVE/RESTORE OPTIONS /////////////////////

	function restoreOptions() {
		const url = generateUrl('/apps/gpxpod/getOptionsValues')
		const req = {
		}
		let optionsValues = {}
		axios.post(url, req).then((response) => {
			optionsValues = response.data.values
			if (optionsValues) {
				let elem, tag, type, k
				for (k in optionsValues) {
					elem = $('#' + k)
					tag = elem.prop('tagName')
					if (k === 'waypointstyleselect') {
						if (k in symbolIcons) {
							elem.val(optionsValues[k])
							updateWaypointStyle(optionsValues[k])
						}
					} else if (k === 'trackwaypointdisplayselect') {
						const trackwaydisplay = optionsValues[k]
						if (trackwaydisplay === 'trw' || trackwaydisplay === 't' || trackwaydisplay === 'r' || trackwaydisplay === 'w') {
							elem.val(trackwaydisplay)
						}
					} else if (k === 'measureunitselect') {
						elem.val(optionsValues[k])
						measureUnitChanged()
					} else if (k === 'tilelayer') {
						gpxpod.restoredTileLayer = optionsValues[k]
					} else if (tag === 'SELECT') {
						elem.val(optionsValues[k])
					} else if (tag === 'INPUT') {
						type = elem.attr('type')
						if (type === 'checkbox') {
							elem.prop('checked', optionsValues[k] !== 'false')
						} else if (type === 'text' || type === 'number') {
							elem.val(optionsValues[k])
						}
					}
				}
			}
			postRestore()
			// quite important ;-)
			main()
		}).catch((error) => {
			console.error(error)
			OC.dialogs.alert(
				t('gpxpod', 'Failed to restore options values') + '. '
				+ t('gpxpod', 'Reload this page')
				,
				t('gpxpod', 'Error')
			)
		})
	}

	function postRestore() {
		if ($('#sendreferrer').is(':checked')) {
			// change meta to send referrer
			// usefull for IGN tiles authentication !
			if ($('meta[name=referrer]').length) {
				// Change tag if already present
				$('meta[name=referrer]').attr('content', 'origin')
			} else {
				// Insert new meta tag if no referrer tag was already found
				const meta = document.createElement('meta')
				meta.name = 'referrer'
				meta.content = 'origin'
				document.getElementsByTagName('head')[0].appendChild(meta)
			}
		}
	}

	function saveOptionTileLayer() {
		saveOptions('tilelayer')
	}

	function saveOptions(key) {
		let value
		/* const valList = [
			'trackwaypointdisplayselect', 'waypointstyleselect', 'tooltipstyleselect',
			'colorcriteria', 'colorcriteriaext', 'tablecriteriasel',
			'measureunitselect', 'igctrackselect', 'lineweight',
		]
		const checkList = [
			'displayclusters', 'openpopupcheck', 'autozoomcheck', 'showchartcheck',
			'transparentcheck', 'updtracklistcheck', 'showpicscheck', 'symboloverwrite',
			'linebordercheck', 'simplehovercheck', 'rteaswpt', 'showshared',
			'showmounted', 'arrowcheck', 'enablesidebar', 'drawallcheck',
		] */
		if (key === 'tilelayer') {
			value = gpxpod.currentLayerName
		} else {
			const elem = $('#' + key)
			const tag = elem.prop('tagName')
			const type = elem.attr('type')
			if (tag === 'SELECT' || (tag === 'INPUT' && (type === 'text' || type === 'number'))) {
				value = elem.val()
			} else if (tag === 'INPUT' && type === 'checkbox') {
				value = elem.is(':checked')
			}
		}

		const req = {
			key,
			value,
		}
		const url = generateUrl('/apps/gpxpod/saveOptionValue')
		axios.post(url, req).then((response) => {
			// alert(response)
		}).catch((error) => {
			console.error(error)
			OC.dialogs.alert(
				t('gpxpod', 'Failed to save options values'),
				t('gpxpod', 'Error')
			)
		})
	}

	/// ///////////// SYMBOLS /////////////////////

	function fillWaypointStyles() {
		for (const st in symbolIcons) {
			$('select#waypointstyleselect').append('<option value="' + st + '">' + st + '</option>')
		}
		$('select#waypointstyleselect').val('Pin, Blue')
		updateWaypointStyle('Pin, Blue')
	}

	function addExtraSymbols() {
		const url = generateUrl('/apps/gpxedit/getExtraSymbol?')
		$('ul#extrasymbols li').each(function() {
			const name = $(this).attr('name')
			const smallname = $(this).html()
			const fullurl = url + 'name=' + encodeURI(name)
			const d = L.icon({
				iconUrl: fullurl,
				iconSize: L.point(24, 24),
				iconAnchor: [12, 12],
			})
			symbolIcons[smallname] = d
		})
	}

	function updateWaypointStyle(val) {
		const sel = $('#waypointstyleselect')
		sel.removeClass(sel.attr('class'))
		sel.attr('style', '')
		if (val in symbolSelectClasses) {
			sel.addClass(symbolSelectClasses[val])
		} else if (val !== '') {
			const url = generateUrl('/apps/gpxedit/getExtraSymbol?')
			const fullurl = url + 'name=' + encodeURI(val + '.png')
			sel.attr('style',
				'background: url(\'' + fullurl + '\') no-repeat '
					+ 'right 8px center var(--color-main-background);'
					+ 'background-size: contain;')
		}
	}

	function moveSelectedTracksTo(destination) {
		const trackPathList = []
		let tid, path
		$('input.drawtrack:checked').each(function() {
			tid = $(this).attr('tid')
			path = decodeURIComponent(gpxpod.markers[tid][FOLDER]).replace(/^\/$/, '')
					+ '/' + decodeURIComponent(gpxpod.markers[tid][NAME])
			trackPathList.push(path)

		})

		const req = {
			trackpaths: trackPathList,
			destination,
		}
		const url = generateUrl('/apps/gpxpod/moveTracks')
		axios.post(url, req).then((response) => {
			if (!response.data.done) {
				let addMsg = ''
				if (response.data.message === 'dnw') {
					addMsg = t('gpxpod', 'Destination directory is not writeable')
				}
				if (response.data.message === 'dne') {
					addMsg = t('gpxpod', 'Destination directory does not exist')
				}
				OC.dialogs.alert(
					t('gpxpod', 'Failed to move selected tracks') + '. ' + addMsg,
					t('gpxpod', 'Error')
				)
			} else {
				moveSuccess(response.data)
			}
		}).catch((error) => {
			console.error(error)
			OC.dialogs.alert(
				t('gpxpod', 'Failed to move selected tracks') + '. '
				+ t('gpxpod', 'Reload this page')
				,
				t('gpxpod', 'Error')
			)
		})
	}

	function moveSuccess(response) {
		OC.Notification.showTemporary(t('gpxpod', 'Following files were moved successfully') + ' : ' + response.moved)
		if (response.notmoved !== '') {
			OC.Notification.showTemporary(t('gpxpod', 'Following files were NOT moved') + ' : ' + response.notmoved)
		}
		// OC.Notification.showTemporary(t('gpxpod', 'Page will be reloaded in 5 sec'))
		// setTimeout(function(){var url = generateUrl('apps/gpxpod/'); window.location.href = url;}, 6000)
		chooseDirSubmit()
	}

	function hideAllDropDowns() {
		const dropdowns = document.getElementsByClassName('dropdown-content')
		let i
		for (i = 0; i < dropdowns.length; i++) {
			const openDropdown = dropdowns[i]
			if (openDropdown.classList.contains('show')) {
				openDropdown.classList.remove('show')
			}
		}
	}

	function addDirectory(path) {
		showLoadingAnimation()
		if (path === '') {
			path = '/'
		}
		const req = {
			path,
		}
		const url = generateUrl('/apps/gpxpod/adddirectory')
		axios.post(url, req).then((response) => {
			const encPath = encodeURIComponent(path)
			OC.Notification.showTemporary(
				t('gpxpod', 'Directory {p} has been added', { p: path })
			)
			$('<option value="' + encPath + '">' + escapeHtml(path) + '</option>').appendTo('#subfolderselect')
			$('select#subfolderselect').val(encPath)
			$('select#subfolderselect').change()
			// remove warning
			if ($('select#subfolderselect option').length === 2) {
				$('#nofolder').hide()
				$('#nofoldertext').hide()
			}
		}).catch((error) => {
			console.error(error)
			OC.Notification.showTemporary(
				t('gpxpod', 'Failed to add directory') + '. ' + error.responseText
			)
		}).always(function() {
			hideAnimation()
		})
	}

	function addDirectoryRecursive(path) {
		showLoadingAnimation()
		const req = {
			path,
		}
		const url = generateUrl('/apps/gpxpod/adddirectoryrecursive')
		axios.post(url, req).then((response) => {
			// const encPath = encodeURIComponent(path)

			for (let i = 0; i < response.data.length; i++) {
				const dir = response.data[i]
				const encDir = encodeURIComponent(dir)
				OC.Notification.showTemporary(
					t('gpxpod', 'Directory {p} has been added', { p: dir })
				)
				$('<option value="' + encDir + '">' + escapeHtml(dir) + '</option>').appendTo('#subfolderselect')
			}
			// remove warning
			if ($('select#subfolderselect option').length > 1) {
				$('#nofolder').hide()
				$('#nofoldertext').hide()
			}
			if (response.data.length === 0) {
				OC.Notification.showTemporary(
					t('gpxpod', 'There is no compatible file in {p} or any of its sub directories', { p: path })
				)
			} else {
				const dir = response.data[0]
				const encDir = encodeURIComponent(dir)
				$('select#subfolderselect').val(encDir)
				$('select#subfolderselect').change()
			}
		}).catch((error) => {
			console.error(error)
			OC.Notification.showTemporary(
				t('gpxpod', 'Failed to recursively add directory') + '. ' + error.responseText
			)
		}).then(() => {
			hideAnimation()
		})
	}

	function delDirectory() {
		showLoadingAnimation()
		const path = decodeURIComponent($('#subfolderselect').val())
		const req = {
			path,
		}
		const url = generateUrl('/apps/gpxpod/deldirectory')
		axios.post(url, req).then((response) => {
			OC.Notification.showTemporary(
				t('gpxpod', 'Directory {p} has been removed', { p: path })
			)
			$('#subfolderselect option[value="' + encodeURIComponent(path) + '"]').remove()
			chooseDirSubmit()
			// warning
			if ($('select#subfolderselect option').length === 1) {
				$('#nofolder').show()
				$('#nofoldertext').show()
			}
		}).catch((error) => {
			console.error(error)
			OC.Notification.showTemporary(
				t('gpxpod', 'Failed to remove directory') + '. ' + error.responseText
			)
		}).then(() => {
			hideAnimation()
		})
	}

	/// ///////////// MAIN /////////////////////

	$(document).ready(function() {
		// get the exra symbols from gpxedit
		if (isGpxeditCompliant(0, 0, 2)) {
			addExtraSymbols()
		}
		fillWaypointStyles()
		if (!pageIsPublicFileOrFolder()) {
			restoreOptions()
		} else {
			main()
		}
	})

	function main() {
		if (pageIsPublicFolder() || pageIsPublicFile()) {
			const autopopup = getUrlParameter('autopopup')
			if (typeof autopopup !== 'undefined' && autopopup === 'n') {
				$('#openpopupcheck').prop('checked', false)
			} else {
				$('#openpopupcheck').prop('checked', true)
			}
			const autozoom = getUrlParameter('autozoom')
			if (typeof autozoom !== 'undefined' && autozoom === 'n') {
				$('#autozoomcheck').prop('checked', false)
			} else {
				$('#autozoomcheck').prop('checked', true)
			}
			const showchart = getUrlParameter('showchart')
			if (typeof showchart !== 'undefined' && showchart === 'n') {
				$('#showchartcheck').prop('checked', false)
			} else {
				$('#autozoomcheck').prop('checked', true)
			}
			const tableutd = getUrlParameter('tableutd')
			if (typeof tableutd !== 'undefined' && tableutd === 'n') {
				$('#updtracklistcheck').prop('checked', false)
			} else {
				$('#updtracklistcheck').prop('checked', true)
			}
			const displaymarkers = getUrlParameter('displaymarkers')
			if (typeof displaymarkers !== 'undefined' && displaymarkers === 'n') {
				$('#displayclusters').prop('checked', false)
			} else {
				$('#displayclusters').prop('checked', true)
			}
			const showpics = getUrlParameter('showpics')
			if (typeof showpics !== 'undefined' && showpics === 'n') {
				$('#showpicscheck').prop('checked', false)
			} else {
				$('#showpicscheck').prop('checked', true)
			}
			const transp = getUrlParameter('transp')
			if (typeof transp !== 'undefined' && transp === 'n') {
				$('#transparentcheck').prop('checked', false)
			} else {
				$('#transparentcheck').prop('checked', true)
			}
			const arrow = getUrlParameter('arrow')
			if (typeof arrow !== 'undefined' && arrow === 'n') {
				$('#arrowcheck').prop('checked', false)
			} else {
				$('#arrowcheck').prop('checked', true)
			}
			const simplehover = getUrlParameter('simplehover')
			if (typeof simplehover !== 'undefined' && simplehover === 'n') {
				$('#simplehovercheck').prop('checked', false)
			} else {
				$('#simplehovercheck').prop('checked', true)
			}
			const rteaswpt = getUrlParameter('rteaswpt')
			if (typeof rteaswpt !== 'undefined' && rteaswpt === 'n') {
				$('#rteaswpt').prop('checked', false)
			} else {
				$('#rteaswpt').prop('checked', true)
			}
			const lineborders = getUrlParameter('lineborders')
			if (typeof lineborders !== 'undefined' && lineborders === 'n') {
				$('#linebordercheck').prop('checked', false)
			} else {
				$('#linebordercheck').prop('checked', true)
			}
			const lineweight = getUrlParameter('lineweight')
			if (typeof lineweight !== 'undefined') {
				$('#lineweight').val(lineweight)
			}
			const color = getUrlParameter('color')
			if (typeof color !== 'undefined') {
				$('#colorcriteria').val(color)
			}
			const colorext = getUrlParameter('colorext')
			if (typeof colorext !== 'undefined') {
				$('#colorcriteriaext').val(colorext)
			}
			const waystyle = getUrlParameter('waystyle')
			if (typeof waystyle !== 'undefined') {
				$('#waypointstyleselect').val(waystyle)
				updateWaypointStyle(waystyle)
			}
			const unit = getUrlParameter('unit')
			if (typeof unit !== 'undefined') {
				$('#measureunitselect').val(unit)
			}
			const tooltipstyle = getUrlParameter('tooltipstyle')
			if (typeof tooltipstyle !== 'undefined') {
				$('#tooltipstyleselect').val(tooltipstyle)
			}
			const trackwaydisplay = getUrlParameter('draw')
			if (typeof trackwaydisplay !== 'undefined') {
				if (trackwaydisplay === 'trw' || trackwaydisplay === 't' || trackwaydisplay === 'r' || trackwaydisplay === 'w') {
					$('#trackwaypointdisplayselect').val(trackwaydisplay)
				}
			}
		}

		gpxpod.username = $('p#username').html()
		gpxpod.token = $('p#token').text()
		gpxpod.gpxedit_version = $('p#gpxedit_version').html()
		gpxpod.gpxedit_compliant = isGpxeditCompliant(0, 0, 1)
		gpxpod.gpxedit_url = generateUrl('/apps/gpxedit/?')
		gpxpod.gpxmotion_compliant = isGpxmotionCompliant(0, 0, 2)
		gpxpod.gpxmotionedit_url = generateUrl('/apps/gpxmotion/?')
		gpxpod.gpxmotionview_url = generateUrl('/apps/gpxmotion/view?')
		loadMap()
		loadMarkers('')
		if (pageIsPublicFolder()) {
			gpxpod.subfolder = $('#publicdir').text()
		}

		// directory can be passed by get parameter in normal page
		if (!pageIsPublicFileOrFolder()) {
			const dirGet = getUrlParameter('dir')
			if ($('select#subfolderselect option[value="' + encodeURIComponent(dirGet) + '"]').length > 0) {
				$('select#subfolderselect').val(encodeURIComponent(dirGet))
			}
		}

		// check a track in the sidebar table
		$('body').on('change', '.drawtrack', function(e) {
			// in publink, no check
			if (pageIsPublicFile()) {
				e.preventDefault()
				$(this).prop('checked', true)
				return
			}
			const tid = $(this).attr('tid')
			// const folder = $(this).parent().parent().attr('folder')
			if ($(this).is(':checked')) {
				if (gpxpod.currentHoverSource !== null) {
					gpxpod.currentHoverSource.cancel()
					gpxpod.currentHoverSource = null
					hideAnimation()
				}
				checkAddTrackDraw(tid, $(this), null)
			} else {
				removeTrackDraw(tid)
			}
		})

		// hover on a sidebar table line
		$('body').on('mouseenter', '#gpxtable tbody tr', function() {
			gpxpod.insideTr = true
			if (!gpxpod.currentlyCorrecting
				&& !$(this).find('.drawtrack').is(':checked')
			) {
				const tid = $(this).find('.drawtrack').attr('tid')
				displayOnHover(tid)
				if ($('#transparentcheck').is(':checked')) {
					$('#sidebar').addClass('transparent')
				}
			}
		})
		$('body').on('mouseleave', '#gpxtable tbody tr', function() {
			if (gpxpod.currentHoverSource !== null) {
				gpxpod.currentHoverSource.cancel()
				gpxpod.currentHoverSource = null
				hideAnimation()
			}
			gpxpod.insideTr = false
			$('#sidebar').removeClass('transparent')
			deleteOnHover()
		})

		// keeping table sort order
		$('body').on('sort', '#gpxtable thead th', function(e) {
			gpxpod.sort.col = $(this).attr('col')
			gpxpod.sort.desc = $(this).hasClass('sorttable_sorted_reverse')
		})

		/// ///////////// OPTION EVENTS /////////////////////

		$('body').on('change', '#transparentcheck', function() {
			if (!pageIsPublicFileOrFolder()) {
				saveOptions($(this).attr('id'))
			}
		})
		$('body').on('change', '#autozoomcheck', function() {
			if (!pageIsPublicFileOrFolder()) {
				saveOptions($(this).attr('id'))
			}
		})
		$('body').on('change', '#simplehovercheck', function() {
			if (!pageIsPublicFileOrFolder()) {
				saveOptions($(this).attr('id'))
			}
		})
		$('body').on('change', '#rteaswpt', function() {
			if (!pageIsPublicFileOrFolder()) {
				saveOptions($(this).attr('id'))
			}
		})
		$('body').on('change', '#showshared, #showmounted, #showpicsonlyfold', function() {
			if (!pageIsPublicFileOrFolder()) {
				saveOptions($(this).attr('id'))
			}
		})
		$('body').on('change', '#recursivetrack', function() {
			if (!pageIsPublicFileOrFolder()) {
				saveOptions($(this).attr('id'))
				chooseDirSubmit()
			}
		})
		$('body').on('change', '#showchartcheck, #sendreferrer', function() {
			if (!pageIsPublicFileOrFolder()) {
				saveOptions($(this).attr('id'))
			}
		})
		$('body').on('change', '#openpopupcheck', function() {
			if (!pageIsPublicFileOrFolder()) {
				saveOptions($(this).attr('id'))
			}
		})
		$('body').on('change', '#displayclusters', function() {
			if (!pageIsPublicFileOrFolder()) {
				saveOptions($(this).attr('id'))
			}
			redrawMarkers()
		})
		$('body').on('change', '#measureunitselect', function() {
			if (!pageIsPublicFileOrFolder()) {
				saveOptions($(this).attr('id'))
			}
			measureUnitChanged()
			tzChanged()
		})
		$('body').on('change', '#igctrackselect', function() {
			if (!pageIsPublicFileOrFolder()) {
				saveOptions($(this).attr('id'))
			}
		})
		$('body').on('change', '#lineweight', function() {
			if (!pageIsPublicFileOrFolder()) {
				saveOptions($(this).attr('id'))
			}
			if (pageIsPublicFile()) {
				displayPublicTrack()
			}
		})
		$('body').on('change', '#drawallcheck', function() {
			if (!pageIsPublicFileOrFolder()) {
				saveOptions($(this).attr('id'))
			}
		})
		$('body').on('change', '#arrowcheck', function() {
			if (!pageIsPublicFileOrFolder()) {
				saveOptions($(this).attr('id'))
			}
			if (pageIsPublicFile()) {
				displayPublicTrack()
			}
		})
		$('body').on('change', '#linebordercheck', function() {
			if (!pageIsPublicFileOrFolder()) {
				saveOptions($(this).attr('id'))
			}
			if (pageIsPublicFile()) {
				displayPublicTrack()
			}
		})
		$('body').on('change', '#enablesidebar', function() {
			if (!pageIsPublicFileOrFolder()) {
				saveOptions($(this).attr('id'))
			}
		})
		$('body').on('change', '#showpicscheck', function() {
			if (!pageIsPublicFileOrFolder()) {
				saveOptions($(this).attr('id'))
			}
			picShowChange()
		})
		// change track color trigger public track (publink) redraw
		$('#colorcriteria').change(function(e) {
			if (!pageIsPublicFileOrFolder()) {
				saveOptions($(this).attr('id'))
			}
			if (pageIsPublicFile()) {
				displayPublicTrack()
			}
		})
		$('#colorcriteriaext').change(function(e) {
			if (!pageIsPublicFileOrFolder()) {
				saveOptions($(this).attr('id'))
			}
		})
		$('#waypointstyleselect').change(function(e) {
			if (!pageIsPublicFileOrFolder()) {
				saveOptions($(this).attr('id'))
			}
			if (pageIsPublicFile()) {
				displayPublicTrack()
			}
			updateWaypointStyle($(this).val())
		})
		$('#tooltipstyleselect').change(function(e) {
			if (!pageIsPublicFileOrFolder()) {
				saveOptions($(this).attr('id'))
			}
			if (pageIsPublicFile()) {
				displayPublicTrack()
			}
		})
		$('body').on('change', '#symboloverwrite', function() {
			if (!pageIsPublicFileOrFolder()) {
				saveOptions($(this).attr('id'))
			}
			if (pageIsPublicFile()) {
				displayPublicTrack()
			}
		})
		$('#trackwaypointdisplayselect').change(function(e) {
			if (!pageIsPublicFileOrFolder()) {
				saveOptions($(this).attr('id'))
			}
			if (pageIsPublicFile()) {
				displayPublicTrack()
			}
		})

		$('body').on('click', '#comparebutton', function(e) {
			compareSelectedTracks()
		})
		$('body').on('click', '#removeelevation', function(e) {
			removeElevation()
		})
		$('body').on('click', '#updtracklistcheck', function(e) {
			if (!pageIsPublicFileOrFolder()) {
				saveOptions($(this).attr('id'))
			}
			if ($('#updtracklistcheck').is(':checked')) {
				$('#ticv').text(t('gpxpod', 'Tracks from current view'))
				$('#tablecriteria').show()
			} else {
				$('#ticv').text('All tracks')
				$('#tablecriteria').hide()
			}
			updateTrackListFromBounds()
		})

		// in case #updtracklistcheck is restored unchecked
		if (!pageIsPublicFileOrFolder()) {
			if ($('#updtracklistcheck').is(':checked')) {
				$('#ticv').text(t('gpxpod', 'Tracks from current view'))
				$('#tablecriteria').show()
			} else {
				$('#ticv').text('All tracks')
				$('#tablecriteria').hide()
			}
		}

		$('#tablecriteriasel').change(function(e) {
			if (!pageIsPublicFileOrFolder()) {
				saveOptions($(this).attr('id'))
			}
			updateTrackListFromBounds()
		})

		// get key events
		document.onkeydown = checkKey

		// fields in filters sidebar tab
		$('#clearfilter').click(function(e) {
			e.preventDefault()
			clearFiltersValues()
			redrawMarkers()
			updateTrackListFromBounds()
		})
		$('#applyfilter').click(function(e) {
			e.preventDefault()
			redrawMarkers()
			updateTrackListFromBounds()
		})
		$('select#subfolderselect').change(function(e, processAll = false) {
			stopGetMarkers()
			chooseDirSubmit(processAll)

			// dynamic url change
			if (!pageIsPublicFileOrFolder()) {
				const sel = $('#subfolderselect').prop('selectedIndex')
				if (sel === 0) {
					document.title = 'GpxPod'
					window.history.pushState({ html: '', pageTitle: '' }, '', '?')
				} else {
					document.title = 'GpxPod - ' + gpxpod.subfolder
					window.history.pushState({ html: '', pageTitle: '' }, '', '?dir=' + encodeURIComponent(gpxpod.subfolder))
				}
			}

		})

		// TIMEZONE
		const mytz = myjstz.determine_timezone()
		const mytzname = mytz.timezone.olson_tz
		let tzoptions = ''
		for (const tzk in myjstz.olson.timezones) {
			const tz = myjstz.olson.timezones[tzk]
			tzoptions = tzoptions + '<option value="' + tz.olson_tz
						+ '">' + tz.olson_tz + ' (GMT'
						+ tz.utc_offset + ')</option>\n'
		}
		$('#tzselect').html(tzoptions)
		$('#tzselect').val(mytzname)
		$('#tzselect').change(function(e) {
			tzChanged()
		})
		tzChanged()

		// options to clean useless files from previous GpxPod versions
		$('#clean').click(function(e) {
			e.preventDefault()
			askForClean('nono')
		})
		$('#cleanall').click(function(e) {
			e.preventDefault()
			askForClean('all')
		})
		$('#cleandb').click(function(e) {
			e.preventDefault()
			cleanDb()
		})

		// Custom tile server management
		$('body').on('click', '#mapboxtileserverlist button', function(e) {
			deleteTileServer($(this).parent(), 'mapboxtile')
		})
		$('body').on('click', '#tileserverlist button', function(e) {
			deleteTileServer($(this).parent(), 'tile')
		})
		$('#addmapboxtileserver').click(function() {
			addTileServer('mapboxtile')
		})
		$('#addtileserver').click(function() {
			addTileServer('tile')
		})
		$('body').on('click', '#overlayserverlist button', function(e) {
			deleteTileServer($(this).parent(), 'overlay')
		})
		$('#addoverlayserver').click(function() {
			addTileServer('overlay')
		})

		$('body').on('click', '#tilewmsserverlist button', function(e) {
			deleteTileServer($(this).parent(), 'tilewms')
		})
		$('#addtileserverwms').click(function() {
			addTileServer('tilewms')
		})
		$('body').on('click', '#overlaywmsserverlist button', function(e) {
			deleteTileServer($(this).parent(), 'overlaywms')
		})
		$('#addoverlayserverwms').click(function() {
			addTileServer('overlaywms')
		})

		// elevation correction of one track
		$('body').on('click', '.csrtm', function(e) {
			correctElevation($(this))
		})
		$('body').on('click', '.csrtms', function(e) {
			correctElevation($(this))
		})

		// in public link and public folder link :
		// hide compare button and custom tiles server management
		if (pageIsPublicFileOrFolder()) {
			$('button#comparebutton').hide()
			$('div#tileserverlist').hide()
			$('div#tileserveradd').hide()
		}

		// PUBLINK management
		$('body').on('click', '.publink', function(e) {
			e.preventDefault()
			const optionValues = getCurrentOptionValues()
			let optionName
			let url = ''

			let dialogTitle
			let linkPath
			const type = $(this).attr('type')
			if (type === 'track') {
				const tid = $(this).attr('tid')
				linkPath = decodeURIComponent(gpxpod.markers[tid][FOLDER]).replace(/^\/$/, '')
				+ '/' + decodeURIComponent(gpxpod.markers[tid][NAME])
				dialogTitle = t('gpxpod', 'Public link to the track') + ' : ' + linkPath
			} else {
				linkPath = $(this).attr('path')
				dialogTitle = t('gpxpod', 'Public link to the folder') + ' : ' + linkPath
			}
			let ajaxurl, req, isShareable, token, path, txt, urlparams
			if (type === 'track') {
				ajaxurl = generateUrl('/apps/gpxpod/isFileShareable')
				req = {
					trackpath: linkPath,
				}
				let filename
				axios.post(ajaxurl, req).then((response) => {
					isShareable = response.data.response
					token = response.data.token
					path = response.data.path
					filename = response.data.filename

					if (isShareable) {
						txt = '<i class="fa fa-check-circle" style="color:green;" aria-hidden="true"></i> '
						url = generateUrl('/apps/gpxpod/publicFile?')

						urlparams = 'token=' + encodeURIComponent(token)
						if (path) {
							urlparams = urlparams + '&path=' + encodeURIComponent(path)
							urlparams = urlparams + '&filename=' + encodeURIComponent(filename)
						}
						url = url + urlparams

						url = window.location.origin + url
					} else {
						txt = '<i class="fa fa-times-circle" style="color:red;" aria-hidden="true"></i> '
						txt = txt + t('gpxpod', 'This public link will work only if \'{title}\' or one of its parent folder is shared in \'files\' app by public link without password', { title: path })
					}

					if (url !== '') {
						for (optionName in optionValues) {
							url = url + '&' + optionName + '=' + optionValues[optionName]
						}
						$('#linkinput').val(url)
					} else {
						$('#linkinput').val('')
					}
					$('#linkhint').hide()

					// fill the fields, show the dialog
					$('#linklabel').html(txt)
					$('#linkdialog').dialog({
						title: dialogTitle,
						width: 400,
						open(event, ui) {
							$('.ui-dialog-titlebar-close', ui.dialog | ui).html('<i class="far fa-times-circle"></i>')
						},
					})
					$('#linkinput').select()
				})
			} else {
				ajaxurl = generateUrl('/apps/gpxpod/isFolderShareable')
				req = {
					folderpath: linkPath,
				}
				axios.post(ajaxurl, req).then((response) => {
					isShareable = response.data.response
					token = response.data.token
					path = response.data.path

					if (isShareable) {
						txt = '<i class="fa fa-check-circle" style="color:green;" aria-hidden="true"></i> '
						url = generateUrl('/apps/gpxpod/publicFolder?')
						urlparams = 'token=' + encodeURIComponent(token)
						if (path) {
							urlparams = urlparams + '&path=' + encodeURIComponent(path)
						}
						url = url + urlparams
						url = window.location.origin + url
					} else {
						txt = '<i class="fa fa-times-circle" style="color:red;" aria-hidden="true"></i> '
						txt = txt + t('gpxpod', 'Public link to \'{folder}\' which will work only if this folder is shared in \'files\' app by public link without password', { folder: path })
					}

					if (url !== '') {
						for (optionName in optionValues) {
							url = url + '&' + optionName + '=' + optionValues[optionName]
						}
						$('#linkinput').val(url)
					} else {
						$('#linkinput').val('')
					}
					$('#linkhint').show()

					// fill the fields, show the dialog
					$('#linklabel').html(txt)
					$('#linkdialog').dialog({
						title: dialogTitle,
						width: 400,
						open(event, ui) {
							$('.ui-dialog-titlebar-close', ui.dialog | ui).html('<i class="far fa-times-circle"></i>')
						},
					})
					$('#linkinput').select()
				})
			}
		})

		// show/hide options
		$('body').on('click', 'h3#optiontitle', function(e) {
			if ($('#optionscontent').is(':visible')) {
				$('#optionscontent').slideUp()
				$(this).find('i').removeClass('fa-caret-down').addClass('fa-caret-right')
			} else {
				$('#optionscontent').slideDown()
				$(this).find('i').removeClass('fa-caret-right').addClass('fa-caret-down')
			}
		})

		// on public pages
		if (pageIsPublicFolder() || pageIsPublicFile()) {
			tzChanged()
			measureUnitChanged()

			// select all tracks if it was asked
			const track = getUrlParameter('track')
			if (track === 'all') {
				$('#openpopupcheck').prop('checked', false)
				$('#showchartcheck').prop('checked', false)
				$('#displayclusters').prop('checked', false)
				$('#displayclusters').change()
				$('input.drawtrack').each(function() { $(this).prop('checked', true) })
				$('input.drawtrack').each(function() { $(this).change() })
				removeElevation()
				zoomOnAllMarkers()
			}
		}

		// comments and descs in popups
		$('body').on('click', '.comtext', function(e) {
			$(this).slideUp()
		})
		$('body').on('click', '.combutton', function(e) {
			const fid = $(this).attr('combutforfeat')
			const p = $('p[comforfeat="' + fid + '"]')
			if (p.is(':visible')) {
				p.slideUp()
			} else {
				p.slideDown()
			}
		})
		$('body').on('click', '.desctext', function(e) {
			$(this).slideUp()
		})
		$('body').on('click', '.descbutton', function(e) {
			const fid = $(this).attr('descbutforfeat')
			const p = $('p[descforfeat="' + fid + '"]')
			if (p.is(':visible')) {
				p.slideUp()
			} else {
				p.slideDown()
			}
		})

		// user color change
		$('body').on('change', '#colorinput', function(e) {
			okColor()
		})
		$('body').on('click', '.colortd', function(e) {
			const colorcriteria = $('#colorcriteria').val()
			if ($(this).find('input').is(':checked') && colorcriteria === 'none') {
				const id = $(this).find('input').attr('tid')
				showColorPicker(id)
			}
		})

		// buttons to select or deselect all tracks
		$('#selectall').click(function(e) {
			$('#openpopupcheck').prop('checked', false)
			$('input.drawtrack:not(checked)').each(function() {
				const tid = $(this).attr('tid')
				// const folder = $(this).parent().parent().attr('folder')
				checkAddTrackDraw(tid, $(this), null, false, false, false)
			})
		})

		$('#deselectallv').click(function(e) {
			$('input.drawtrack:checked').each(function() {
				const tid = $(this).attr('tid')
				removeTrackDraw(tid)
			})
			gpxpod.map.closePopup()
		})

		$('#deselectall').click(function(e) {
			for (const tid in gpxpod.gpxlayers) {
				removeTrackDraw(tid)
			}
			gpxpod.map.closePopup()
		})

		$('#moveselectedto').click(function(e) {
			if ($('input.drawtrack:checked').length < 1) {
				OC.Notification.showTemporary(t('gpxpod', 'Select at least one track'))
			} else {
				OC.dialogs.filepicker(
					t('gpxpod', 'Destination folder'),
					function(targetPath) {
						if (targetPath === gpxpod.subfolder) {
							OC.Notification.showTemporary(t('gpxpod', 'Origin and destination directories must be different'))
						} else {
							moveSelectedTracksTo(targetPath)
						}
					},
					false, 'httpd/unix-directory', true
				)
			}
		})

		if (pageIsPublicFile()) {
			$('#deselectall').hide()
			$('#selectall').hide()
			$('#deselectallv').hide()
		}

		$('#deleteselected').click(function(e) {
			deleteSelectedTracks()
		})

		$('body').on('click', '.deletetrack', function(e) {
			const tid = $(this).attr('tid')
			OC.dialogs.confirm(
				t('gpxpod',
					'Are you sure you want to delete the track {name} ?',
					{ name: decodeURIComponent(gpxpod.markers[tid][NAME]) }
				),
				t('gpxpod', 'Confirm track deletion'),
				function(result) {
					if (result) {
						deleteOneTrack(tid)
					}
				},
				true
			)
		})

		if (!pageIsPublicFileOrFolder()) {
			$('#reloadprocessfolder').click(function() {
				$('select#subfolderselect').trigger('change', true)
			})
			$('#reloadfolder').click(function() {
				$('select#subfolderselect').change()
			})
			$('#addDirButton').click(function() {
				OC.dialogs.filepicker(
					t('gpxpod', 'Add directory'),
					function(targetPath) {
						addDirectory(targetPath)
					},
					false,
					'httpd/unix-directory',
					true
				)
			})
			$('#addDirsButton').click(function() {
				OC.dialogs.filepicker(
					t('gpxpod', 'Add directory recursively'),
					function(targetPath) {
						addDirectoryRecursive(targetPath)
					},
					false,
					'httpd/unix-directory',
					true
				)
			})
			$('#delDirButton').click(function() {
				delDirectory()
			})
		}

		if (pageIsPublicFileOrFolder()) {
			$('#deleteselected').hide()
			$('#cleandiv').hide()
			$('#customtilediv').hide()
			$('#moveselectedto').hide()
			$('#addRemoveButtons').hide()
		} else {
			if ($('select#subfolderselect option').length === 1) {
				$('#nofolder').show()
				$('#nofoldertext').show()
			}
		}

		$('body').on('click', 'h3.customtiletitle', function(e) {
			const forAttr = $(this).attr('for')
			if ($('#' + forAttr).is(':visible')) {
				$('#' + forAttr).slideUp()
				$(this).find('i').removeClass('fa-angle-double-up').addClass('fa-angle-double-down')
			} else {
				$('#' + forAttr).slideDown()
				$(this).find('i').removeClass('fa-angle-double-down').addClass('fa-angle-double-up')
			}
		})

		// DROPDOWN management
		window.onclick = function(event) {
			if (!event.target.matches('.dropdownbutton') && !event.target.matches('.dropdownbutton i')) {
				hideAllDropDowns()
			}
		}

		$('body').on('click', '.dropdownbutton', function(e) {
			let dcontent
			if (e.target.nodeName === 'BUTTON') {
				dcontent = $(e.target).parent().find('.dropdown-content')
			} else {
				dcontent = $(e.target).parent().parent().find('.dropdown-content')
			}
			const isVisible = dcontent.hasClass('show')
			hideAllDropDowns()
			if (!isVisible) {
				dcontent.toggleClass('show')
			}
		})

		$('body').on('click', '.zoomtrackbutton', function(e) {
			const tid = $(this).attr('tid')
			if (tid in gpxpod.gpxlayers) {
				const b = gpxpod.gpxlayers[tid].layer.getBounds()
				let xoffset = parseInt($('#sidebar').css('width'))
				if (pageIsPublicFileOrFolder()) {
					const showSidebar = getUrlParameter('sidebar')
					if (showSidebar === '0') {
						xoffset = 0
					}
				}
				gpxpod.map.fitBounds(b, {
					animate: true,
					paddingTopLeft: [xoffset, 100],
					paddingBottomRight: [100, 100],
				})
			}
		})

		$('body').on('click', '.drawButton', function(e) {
			const tid = $(this).attr('tid')
			// const folder = $(this).parent().parent().attr('folder')
			const checkbox = $('input[id="' + tid + '"]')
			checkAddTrackDraw(tid, checkbox)
		})

		let buttonColor = 'blue'
		if (OCA.Theming) {
			buttonColor = OCA.Theming.color
		}

		const radius = 8
		const diam = 2 * radius
		$('<style role="divmarker">'
			+ '.rmarker, .smarker { '
			+ 'width: ' + diam + 'px !important;'
			+ 'height: ' + diam + 'px !important;'
			+ 'line-height: ' + (diam - 4) + 'px;'
			+ '}'
			+ '.tmarker { '
			+ 'width: 0px !important;'
			+ 'height: 0px !important;'
			+ 'border-left: ' + radius + 'px solid transparent !important;'
			+ 'border-right: ' + radius + 'px solid transparent !important;'
			+ 'border-bottom-width: ' + diam + 'px;'
			+ 'border-bottom-style: solid;'
			+ 'line-height: ' + (diam) + 'px;'
			+ '}'
			+ '</style>').appendTo('body')

		$('<style role="buttons">.fa, .fas, .far { '
			+ 'color: ' + buttonColor + '; }</style>').appendTo('body')

	}

})($, OC)
