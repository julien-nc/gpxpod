import { LngLat } from 'maplibre-gl'

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
			// TODO display popup with point information
			// or just report the found point to let the <Map> display a point (for this point: on hover: display info)
		},
		onBorderMouseEnter(e) {
			this.bringToTop()
			this.map.getCanvas().style.cursor = 'pointer'
			this.findPoint(e.lngLat)
		},
		onBorderMouseLeave(e) {
			this.map.getCanvas().style.cursor = ''
		},
		listenToBorderHover() {
			this.map.on('mouseenter', this.borderLayerId, this.onBorderMouseEnter)
			this.map.on('mouseleave', this.borderLayerId, this.onBorderMouseLeave)
		},
		releaseBorderHover() {
			this.map.off('mouseenter', this.borderLayerId, this.onBorderMouseEnter)
			this.map.off('mouseleave', this.borderLayerId, this.onBorderMouseLeave)
		},
	},
}
