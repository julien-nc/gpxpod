import { LngLat, Popup } from 'maplibre-gl'
import moment from '@nextcloud/moment'
import { metersToElevation, kmphToSpeed } from '../utils.js'
import { emit } from '@nextcloud/event-bus'

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
			let minDistPointIndex = null
			let tmpDist
			let tmpIndex = 0
			this.track.geojson.features.forEach((feature) => {
				if (feature.geometry.type === 'LineString') {
					for (let i = 0; i < feature.geometry.coordinates.length; i++) {
						const c = feature.geometry.coordinates[i]
						tmpDist = lngLat.distanceTo(new LngLat(c[0], c[1]))
						if (tmpDist < minDist) {
							minDist = tmpDist
							minDistPoint = [
								...c,
								feature.geometry.coordinates[i - 1] ?? null,
							]
							minDistPointIndex = tmpIndex
						}
						tmpIndex++
					}
				} else if (feature.geometry.type === 'MultiLineString') {
					feature.geometry.coordinates.forEach((coords) => {
						for (let i = 0; i < coords.length; i++) {
							const c = coords[i]
							tmpDist = lngLat.distanceTo(new LngLat(c[0], c[1]))
							if (tmpDist < minDist) {
								minDist = tmpDist
								minDistPoint = [
									...c,
									coords[i - 1] ?? null,
								]
								minDistPointIndex = tmpIndex
							}
							tmpIndex++
						}
					})
				}
			})
			console.debug('found', minDistPoint)
			return { minDistPoint, minDistPointIndex }
		},
		showPointPopup(lngLat, persist = false) {
			if (!this.track.geojson) {
				return
			}
			const { minDistPoint, minDistPointIndex } = this.findPoint(lngLat)
			if (minDistPoint !== null) {
				const previousPoint = minDistPoint[minDistPoint.length - 1]

				if (this.nonPersistentPopup) {
					this.nonPersistentPopup.remove()
				}

				const containerClass = persist ? 'class="with-button"' : ''
				const dataHtml = (minDistPoint[3] === null && minDistPoint[2] === null)
					? t('gpxpod', 'No data')
					: (minDistPoint[3] !== null ? ('<strong>' + t('gpxpod', 'Date') + '</strong>: ' + moment.unix(minDistPoint[3]).format('YYYY-MM-DD HH:mm:ss (Z)') + '<br>') : '')
						+ (minDistPoint[2] !== null ? ('<strong>' + t('gpxpod', 'Altitude') + '</strong>: ' + metersToElevation(minDistPoint[2]) + '<br>') : '')
						+ (minDistPoint[3] !== null && previousPoint !== null && previousPoint[3] !== null
							? ('<strong>' + t('gpxpod', 'Speed') + '</strong>: ' + kmphToSpeed(this.getPointSpeed(minDistPoint)))
							: '')
						+ this.getExtensionsPopupText(minDistPoint)
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
					emit('track-point-hover', { trackId: this.track.id, pointIndex: minDistPointIndex })
					this.nonPersistentPopup = popup
				}
			}
		},
		getExtensionsPopupText(point) {
			if (point[4]?.unsupported) {
				const unsupported = point[4].unsupported
				return '<br>'
					+ Object.keys(unsupported).map(extKey => {
						if (extKey === 'speed') {
							return '<strong>' + t('gpxpod', 'GPS speed') + '</strong>: ' + kmphToSpeed(parseFloat(unsupported[extKey]))
						} else if (extKey === 'heart_rate') {
							return '<strong>' + t('gpxpod', 'Heart rate') + '</strong>: ' + unsupported[extKey] + ' bpm'
						}
						return '<strong>' + extKey + '</strong>: ' + unsupported[extKey]
					}).join('<br>')
			}
			return ''
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
		getPointSpeed(p) {
			const previousPoint = p[p.length - 1]
			const ll1 = new LngLat(previousPoint[0], previousPoint[1])
			const ts1 = previousPoint[3]
			const ll2 = new LngLat(p[0], p[1])
			const ts2 = p[3]

			const distance = ll1.distanceTo(ll2)
			const time = ts2 - ts1
			return distance / time * 3.6
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
			this.map.on('click', this.invisibleBorderLayerId, this.onClickPointInfo)
			this.map.on('mouseenter', this.invisibleBorderLayerId, this.onMouseEnterPointInfo)
			this.map.on('mouseleave', this.invisibleBorderLayerId, this.onMouseLeavePointInfo)
		},
		releasePointInfoEvents() {
			this.map.off('click', this.invisibleBorderLayerId, this.onClickPointInfo)
			this.map.off('mouseenter', this.invisibleBorderLayerId, this.onMouseEnterPointInfo)
			this.map.off('mouseleave', this.invisibleBorderLayerId, this.onMouseLeavePointInfo)
		},
	},
}
