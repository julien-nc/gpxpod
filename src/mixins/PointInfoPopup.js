import { LngLat, Popup } from 'maplibre-gl'
import moment from '@nextcloud/moment'
import { metersToElevation } from '../utils.js'

export default {
	data() {
		return {
			nonPersistentPopup: null,
			popups: [],
		}
	},

	watch: {
		ready(newVal) {
			if (newVal) {
				this.listenToPointInfoEvents()
			}
		},
	},

	destroyed() {
		this.releasePointInfoEvents()
		this.clearPopups()
	},

	methods: {
		findPoint(lngLat) {
			if (!this.track.geojson) {
				return null
			}
			let minDist = 40000000
			let minDistPoint = null
			let tmpDist
			this.track.geojson.features.forEach((feature) => {
				if (feature.geometry.type === 'LineString') {
					feature.geometry.coordinates.forEach(c => {
						tmpDist = lngLat.distanceTo(new LngLat(c[0], c[1]))
						if (tmpDist < minDist) {
							minDist = tmpDist
							minDistPoint = c
						}
					})
				} else if (feature.geometry.type === 'MultiLineString') {
					feature.geometry.coordinates.forEach((coords) => {
						coords.forEach(c => {
							tmpDist = lngLat.distanceTo(new LngLat(c[0], c[1]))
							if (tmpDist < minDist) {
								minDist = tmpDist
								minDistPoint = c
							}
						})
					})
				}
			})
			console.debug('found', minDistPoint)
			return minDistPoint
		},
		showPointPopup(lngLat, persist = false) {
			const minDistPoint = this.findPoint(lngLat)
			if (minDistPoint !== null) {
				if (this.nonPersistentPopup) {
					this.nonPersistentPopup.remove()
				}
				const containerClass = persist ? 'class="with-button"' : ''
				const dataHtml = (minDistPoint[3] === null && minDistPoint[2] === null)
					? t('gpxpod', 'No data')
					: (minDistPoint[3] !== null ? (moment.unix(minDistPoint[3]).format('YYYY-MM-DD HH:mm:ss (Z)') + '<br>') : '')
						+ (minDistPoint[2] !== null ? (t('gpxpod', 'Altitude') + ': ' + metersToElevation(minDistPoint[2])) : '')
				const html = '<div ' + containerClass + ' style="border-color: ' + this.track.color + ';">'
					+ dataHtml
					+ '</div>'
				const popup = new Popup({
					closeButton: persist,
					closeOnClick: !persist,
					closeOnMove: !persist,
				})
					.setLngLat([minDistPoint[0], minDistPoint[1]])
					.setHTML(html)
					.addTo(this.map)
				if (persist) {
					this.popups.push(popup)
				} else {
					this.nonPersistentPopup = popup
				}
			}
		},
		clearPopups() {
			if (this.nonPersistentPopup) {
				this.nonPersistentPopup.remove()
			}
			this.popups.forEach(p => {
				p.remove()
			})
			this.popups = []
		},
		onMouseEnterPointInfo(e) {
			this.map.getCanvas().style.cursor = 'pointer'
			this.showPointPopup(e.lngLat, false)
		},
		onMouseLeavePointInfo(e) {
			this.map.getCanvas().style.cursor = ''
			if (this.nonPersistentPopup) {
				this.nonPersistentPopup.remove()
			}
		},
		onClickPointInfo(e) {
			this.showPointPopup(e.lngLat, true)
		},
		listenToPointInfoEvents() {
			this.map.on('click', this.borderLayerId, this.onClickPointInfo)
			this.map.on('mouseenter', this.borderLayerId, this.onMouseEnterPointInfo)
			this.map.on('mouseleave', this.borderLayerId, this.onMouseLeavePointInfo)
		},
		releasePointInfoEvents() {
			this.map.off('click', this.borderLayerId, this.onClickPointInfo)
			this.map.off('mouseenter', this.borderLayerId, this.onMouseEnterPointInfo)
			this.map.off('mouseleave', this.borderLayerId, this.onMouseLeavePointInfo)
		},
	},
}
