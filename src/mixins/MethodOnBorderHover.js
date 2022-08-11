import { LngLat, Popup } from 'maplibre-gl'
import moment from '@nextcloud/moment'

export default {
	methods: {
		findPoint(lngLat) {
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
			// this.$emit('track-point-hover', minDistPoint)
		},
		showPointPopup(lngLat, persist = false) {
			const minDistPoint = this.findPoint(lngLat)
			if (minDistPoint !== null) {
				if (this.nonPersistentPopup) {
					this.nonPersistentPopup.remove()
				}
				const containerClass = persist ? 'class="with-button"' : ''
				const html = '<div ' + containerClass + ' style="border-color: ' + this.track.color + ';">'
					+ moment.unix(minDistPoint[3]).format('YYYY-MM-DD HH:mm:ss (Z)')
					+ '<br>'
					+ t('gpxpod', 'Altitude') + ': ' + minDistPoint[2]
					+ '</div>'
				const popup = new Popup({
					closeButton: persist,
					closeOnClick: !persist,
					closeOnMove: !persist,
				})
					.setLngLat([minDistPoint[0], minDistPoint[1]])
					.setHTML(html)
					.addTo(this.map)
				if (!persist) {
					this.nonPersistentPopup = popup
				}
			}
		},
		onBorderMouseEnter(e) {
			this.bringToTop()
			this.map.getCanvas().style.cursor = 'pointer'
			this.showPointPopup(e.lngLat, false)
		},
		onBorderMouseLeave(e) {
			this.map.getCanvas().style.cursor = ''
			if (this.nonPersistentPopup) {
				this.nonPersistentPopup.remove()
			}
		},
		onBorderClick(e) {
			this.showPointPopup(e.lngLat, true)
		},
		listenToBorderHover() {
			this.map.on('click', this.borderLayerId, this.onBorderClick)
			this.map.on('mouseenter', this.borderLayerId, this.onBorderMouseEnter)
			this.map.on('mouseleave', this.borderLayerId, this.onBorderMouseLeave)
		},
		releaseBorderHover() {
			this.map.off('mouseenter', this.borderLayerId, this.onBorderMouseEnter)
			this.map.off('mouseleave', this.borderLayerId, this.onBorderMouseLeave)
		},
	},
}
