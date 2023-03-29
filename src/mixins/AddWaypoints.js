import { Popup } from 'maplibre-gl'
// import moment from '@nextcloud/moment'
import { metersToElevation } from '../utils.js'
// import { emit } from '@nextcloud/event-bus'

export default {
	data() {
		return {
			nonPersistentWaypointPopup: null,
			waypointPopups: [],
		}
	},

	computed: {
		waypointsLayerId() {
			return this.layerId + '-waypoints'
		},
	},

	watch: {
		ready(newVal) {
			if (newVal) {
				this.initWaypoints()
				this.listenToWaypointEvents()
			}
		},
	},

	destroyed() {
		this.releaseWaypointEvents()
		this.removeWaypoints()
		this.clearWaypointPopups()
	},

	methods: {
		bringToTop() {
			if (this.map.getLayer(this.waypointsLayerId)) {
				this.map.moveLayer(this.waypointsLayerId)
			}
		},
		removeWaypoints() {
			if (this.map.getLayer(this.waypointsLayerId)) {
				this.map.removeLayer(this.waypointsLayerId)
			}
		},
		initWaypoints() {
			console.debug('init addwaypoints')
			this.map.addLayer({
				type: 'symbol',
				source: this.layerId,
				id: this.waypointsLayerId,
				layout: {
					'icon-image': 'marker',
					'icon-anchor': 'bottom',
					'icon-size': 0.5,
				},
				filter: ['==', '$type', 'Point'],
			})
		},
		showWaypointPopup(e, persist = false) {
			if (this.nonPersistentWaypointPopup) {
				this.nonPersistentWaypointPopup.remove()
			}
			if (e.features.length === 0) {
				return
			} else {
				const props = e.features[0].properties
				if ((!props?.lat || !props?.lng) || (!props?.name && !props?.elevation)) {
					return
				}
			}
			const containerClass = persist ? 'class="with-button"' : ''
			let dataHtml = ''
			const props = e.features[0].properties
			if (this.track.name) {
				const tmpNode = document.createTextNode(this.track.name)
				dataHtml += '<strong>' + t('gpxpod', 'Track') + '</strong>: ' + tmpNode.textContent + '<br>'
			}
			if (props.name) {
				const tmpNode = document.createTextNode(props.name)
				dataHtml += '<strong>' + t('gpxpod', 'Waypoint') + '</strong>: ' + tmpNode.textContent + '<br>'
			}
			if (props.elevation) {
				dataHtml += '<strong>' + t('gpxpod', 'Altitude') + '</strong>: ' + metersToElevation(props.elevation) + '<br>'
			}
			const html = '<div ' + containerClass + ' style="border-color: ' + this.track.color + ';">'
				+ dataHtml
				+ '</div>'
			const popup = new Popup({
				closeButton: persist,
				closeOnClick: !persist,
				closeOnMove: !persist,
				offset: [0, -30],
			})
				.setLngLat([props.lng, props.lat])
				.setHTML(html)
				.addTo(this.map)
			if (persist) {
				this.waypointPopups.push(popup)
			} else {
				this.nonPersistentWaypointPopup = popup
			}
		},
		clearWaypointPopups() {
			if (this.nonPersistentWaypointPopup) {
				this.nonPersistentWaypointPopup.remove()
			}
			this.waypointPopups.forEach(p => {
				p.remove()
			})
			this.waypointPopups = []
		},
		onMouseEnterWaypoint(e) {
			this.map.getCanvas().style.cursor = 'pointer'
			console.debug('enter waypoint', e)
			this.bringToTop()
			this.showWaypointPopup(e, false)
		},
		onMouseLeaveWaypoint(e) {
			this.map.getCanvas().style.cursor = ''
			if (this.nonPersistentWaypointPopup) {
				this.nonPersistentWaypointPopup.remove()
			}
		},
		onClickWaypoint(e) {
			this.showWaypointPopup(e, true)
		},
		listenToWaypointEvents() {
			this.map.on('click', this.waypointsLayerId, this.onClickWaypoint)
			this.map.on('mouseenter', this.waypointsLayerId, this.onMouseEnterWaypoint)
			this.map.on('mouseleave', this.waypointsLayerId, this.onMouseLeaveWaypoint)
		},
		releaseWaypointEvents() {
			this.map.off('click', this.waypointsLayerId, this.onClickWaypoint)
			this.map.off('mouseenter', this.waypointsLayerId, this.onMouseEnterWaypoint)
			this.map.off('mouseleave', this.waypointsLayerId, this.onMouseLeaveWaypoint)
		},
	},
}
