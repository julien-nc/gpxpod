<!--
  - SPDX-FileCopyrightText: 2015 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<script>
import WatchLineBorderColor from '../../mixins/WatchLineBorderColor.js'
import PointInfoPopup from '../../mixins/PointInfoPopup.js'
import BringTrackToTop from '../../mixins/BringTrackToTop.js'
import AddWaypoints from '../../mixins/AddWaypoints.js'
import LineDirectionArrows from '../../mixins/LineDirectionArrows.js'
import { COLOR_CRITERIAS, getColorHueInInterval } from '../../constants.js'
import { getPaces } from '../../mapUtils.js'
import { LngLat } from 'maplibre-gl'

export default {
	name: 'TrackGradientColorPointsPerSegment',

	components: {
	},

	mixins: [
		WatchLineBorderColor,
		PointInfoPopup,
		BringTrackToTop,
		AddWaypoints,
		LineDirectionArrows,
	],

	props: {
		track: {
			type: Object,
			required: true,
		},
		map: {
			type: Object,
			required: true,
		},
		colorCriteria: {
			type: Number,
			default: COLOR_CRITERIAS.elevation.id,
		},
		colorExtensionCriteria: {
			type: String,
			default: null,
		},
		colorExtensionCriteriaType: {
			type: String,
			default: null,
		},
		lineWidth: {
			type: Number,
			default: 5,
		},
		borderColor: {
			type: String,
			default: 'black',
		},
		border: {
			type: Boolean,
			default: true,
		},
		arrows: {
			type: Boolean,
			default: true,
		},
		opacity: {
			type: Number,
			default: 1,
		},
		settings: {
			type: Object,
			required: true,
		},
	},

	data() {
		return {
			ready: false,
			// segment layers currently drawn on the shared source, to reconcile
			// them with the segments of the track data
			currentSegments: [],
		}
	},

	computed: {
		layerId() {
			return String(this.track.id)
		},
		borderLayerId() {
			return this.layerId + '-border'
		},
		invisibleBorderLayerId() {
			return this.layerId + '-invisible-border'
		},
		color() {
			return this.track.color ?? '#0693e3'
		},
		onTop() {
			return this.track.onTop
		},
		trackGeojsonData() {
			console.debug('[gpxpod] compute track geojson', this.track.geojson)
			// use short point list for hovered track when we don't have the data yet
			if (!this.track.geojson) {
				return {
					type: 'FeatureCollection',
					features: [
						{
							type: 'Feature',
							properties: { s: 0 },
							geometry: {
								coordinates: this.track.short_point_list.map((p) => [p[1], p[0]]),
								type: 'LineString',
							},
						},
					],
				}
			}
			// one feature per segment: this way the border, the invisible border and
			// all the segment gradient layers can read from the single shared source
			const features = []
			let segmentIndex = 0
			this.track.geojson.features.forEach((feature) => {
				if (feature.geometry.type === 'LineString') {
					features.push(this.getSegmentFeature(feature.geometry.coordinates, segmentIndex))
					segmentIndex++
				} else if (feature.geometry.type === 'MultiLineString') {
					feature.geometry.coordinates.forEach((coords) => {
						features.push(this.getSegmentFeature(coords, segmentIndex))
						segmentIndex++
					})
				} else {
					// keep the waypoints and any other feature type untouched
					features.push(feature)
				}
			})
			return {
				type: 'FeatureCollection',
				features,
			}
		},
		// color gradient steps of each segment, the 's' property links the segment
		// feature to the layer rendering it
		segmentSteps() {
			if (!this.track.geojson) {
				return []
			}
			const segmentCoords = []
			this.track.geojson.features.forEach((feature) => {
				if (feature.geometry.type === 'LineString') {
					segmentCoords.push(feature.geometry.coordinates)
				} else if (feature.geometry.type === 'MultiLineString') {
					feature.geometry.coordinates.forEach((coords) => {
						segmentCoords.push(coords)
					})
				}
			})

			// in case we have accumulated values like traveled distance
			// we need to know the value at the end of the previous segment
			let lastSegmentLastPointValue = null
			const segmentValues = segmentCoords.map(coords => {
				const pointValues = this.getPointValues(coords, lastSegmentLastPointValue)
				if (pointValues.length > 0) {
					lastSegmentLastPointValue = pointValues[pointValues.length - 1]
				}
				return pointValues
			})

			// { min, max } for each segment
			const segmentMinsMaxs = segmentValues.map(values => {
				const cleanValues = values.filter(v => v !== undefined && v !== null)
				return cleanValues.length > 0
					? {
							min: cleanValues.reduce((acc, val) => Math.min(acc, val)),
							max: cleanValues.reduce((acc, val) => Math.max(acc, val)),
						}
					: {
							min: null,
							max: null,
						}
			})
			const segmentMins = segmentMinsMaxs.map(mm => mm.min)
			const segmentMaxs = segmentMinsMaxs.map(mm => mm.max)
			const cleanSegmentMins = segmentMins.filter(v => v !== null)
			const cleanSegmentMaxs = segmentMaxs.filter(v => v !== null)

			const trackMin = cleanSegmentMins.length > 0 ? segmentMins.reduce((acc, val) => Math.min(acc, val)) : null
			const trackMax = cleanSegmentMaxs.length > 0 ? segmentMaxs.reduce((acc, val) => Math.max(acc, val)) : null

			const segmentSteps = []
			if (this.settings.global_track_colorization === '1') {
				segmentCoords.forEach((coords, i) => {
					segmentSteps.push(this.getColorSteps(coords, segmentValues[i], trackMin, trackMax))
				})
			} else {
				segmentCoords.forEach((coords, i) => {
					segmentSteps.push(this.getColorSteps(coords, segmentValues[i], segmentMins[i], segmentMaxs[i]))
				})
			}
			return segmentSteps
		},
		getPointValues() {
			return this.colorExtensionCriteria
				? (coords) => {
						return coords.map(c => c[4]?.[this.colorExtensionCriteriaType]?.[this.colorExtensionCriteria] ?? null)
					}
				: this.colorCriteria === COLOR_CRITERIAS.elevation.id
					? (coords) => {
							return coords.map(c => c[2])
						}
					: this.colorCriteria === COLOR_CRITERIAS.pace.id
						? getPaces
						: this.colorCriteria === COLOR_CRITERIAS.speed.id
							? this.getSpeeds
							: this.colorCriteria === COLOR_CRITERIAS.traveled_distance.id
								? this.getTraveledDistances
								: () => null
		},
	},

	watch: {
		onTop(newVal) {
			if (newVal) {
				this.bringToTop()
			}
		},
		trackGeojsonData() {
			console.debug('[gpxpod] trackGeojsonData has changed')
			const source = this.map.getSource(this.layerId)
			if (source) {
				source.setData(this.trackGeojsonData)
				this.updateSegmentLayers()
			}
		},
		colorCriteria() {
			this.updateSegmentLayers()
		},
		colorExtensionCriteria() {
			this.updateSegmentLayers()
		},
		'settings.global_track_colorization': function() {
			this.updateSegmentLayers()
		},
		border(newVal) {
			if (newVal) {
				this.drawBorder()
				// fix border being drawn on top of the line
				this.bringToTop()
			} else {
				this.removeBorder()
			}
		},
		opacity() {
			this.setNormalOpacity()
		},
		lineWidth() {
			this.setNormalLineWidth()
		},
	},

	mounted() {
		console.debug('[gpxpod] track mounted!!!!!', String(this.track.id))
		this.init()
	},

	unmounted() {
		console.debug('[gpxpod] destroy track', String(this.track.id))
		this.remove()
	},

	methods: {
		getSegmentFeature(coords, s) {
			return {
				type: 'Feature',
				properties: { s },
				geometry: {
					coordinates: coords,
					type: 'LineString',
				},
			}
		},
		getColorSteps(coords, pointValues, min, max) {
			console.debug('[gpxpod] simple gradient pointvalues', pointValues, 'min', min, 'max', max)
			const result = []
			const accTraveledDistances = [0]
			let prevLL = new LngLat(coords[0][0], coords[0][1])
			let totalDistance = 0
			for (let i = 1; i < coords.length; i++) {
				const currLL = new LngLat(coords[i][0], coords[i][1])
				const distance = prevLL.distanceTo(currLL)
				totalDistance = totalDistance + distance
				accTraveledDistances.push(totalDistance)
				prevLL = currLL
			}
			let prevPcDistance = null
			pointValues.forEach((val, i) => {
				const pcDistance = accTraveledDistances[i] / totalDistance
				if (pcDistance !== prevPcDistance) {
					prevPcDistance = pcDistance
					result.push(pcDistance)
					if (val !== null && val !== undefined) {
						result.push(this.getColor(min, max, val))
					} else {
						result.push('white')
					}
				}
			})
			return result
		},
		getSpeeds(coords) {
			const speeds = [0]
			let prevLL = new LngLat(coords[0][0], coords[0][1])
			for (let i = 1; i < coords.length; i++) {
				const currLL = new LngLat(coords[i][0], coords[i][1])
				speeds.push(this.getSpeed(prevLL, currLL, coords[i - 1], coords[i]))
				prevLL = currLL
			}
			return speeds
		},
		getTraveledDistances(coords, lastSegmentLastValue) {
			let prevDistance = lastSegmentLastValue ?? 0
			const distances = [prevDistance]
			let prevLL = new LngLat(coords[0][0], coords[0][1])

			for (let i = 1; i < coords.length; i++) {
				const currLL = new LngLat(coords[i][0], coords[i][1])
				const currTraveledDistance = prevDistance + prevLL.distanceTo(currLL)
				distances.push(currTraveledDistance)
				prevLL = currLL
				prevDistance = currTraveledDistance
			}
			return distances
		},
		getSpeed(ll1, ll2, coord1, coord2) {
			const distance = ll1.distanceTo(ll2)
			const time = coord2[3] - coord1[3]
			return distance / time
		},
		getColor(min, max, value) {
			const weight = (value - min) / (max - min)
			const hue = getColorHueInInterval(240, 0, weight || 0)
			return 'hsl(' + hue + ', 100%, 50%)'
		},
		getGradientExpression(steps) {
			return [
				'interpolate',
				['linear'],
				['line-progress'],
				...steps,
			]
		},
		segmentLayerId(i) {
			return this.layerId + '-seg-' + i
		},
		bringToTop() {
			if (this.map.getLayer(this.borderLayerId)) {
				this.map.moveLayer(this.borderLayerId)
			}
			this.currentSegments.forEach((i) => {
				if (this.map.getLayer(this.segmentLayerId(i))) {
					this.map.moveLayer(this.segmentLayerId(i))
				}
			})
			// cannot be done in the mixin as it will happen before so arrows will be behind the line
			this.bringArrowsToTop()
		},
		onMouseEnter() {
			this.currentSegments.forEach((i) => {
				if (this.map.getLayer(this.segmentLayerId(i))) {
					this.map.setPaintProperty(this.segmentLayerId(i), 'line-width', this.lineWidth * 1.7)
				}
			})
			if (this.map.getLayer(this.borderLayerId)) {
				this.map.setPaintProperty(this.borderLayerId, 'line-width', (this.lineWidth * 0.3) * 1.7)
				this.map.setPaintProperty(this.borderLayerId, 'line-gap-width', this.lineWidth * 1.7)
			}
		},
		onMouseLeave() {
			this.setNormalLineWidth()
		},
		setNormalLineWidth() {
			this.currentSegments.forEach((i) => {
				if (this.map.getLayer(this.segmentLayerId(i))) {
					this.map.setPaintProperty(this.segmentLayerId(i), 'line-width', this.lineWidth)
				}
			})
			if (this.map.getLayer(this.borderLayerId)) {
				this.map.setPaintProperty(this.borderLayerId, 'line-width', this.lineWidth * 0.3)
				this.map.setPaintProperty(this.borderLayerId, 'line-gap-width', this.lineWidth)
			}
		},
		setNormalOpacity() {
			this.currentSegments.forEach((i) => {
				if (this.map.getLayer(this.segmentLayerId(i))) {
					this.map.setPaintProperty(this.segmentLayerId(i), 'line-opacity', this.opacity)
				}
			})
			if (this.map.getLayer(this.borderLayerId)) {
				this.map.setPaintProperty(this.borderLayerId, 'line-opacity', this.opacity)
			}
		},
		remove() {
			console.debug('----------- destroy trackGradient')
			this.removeArrows()
			this.removeWaypoints()
			if (this.map.getLayer(this.invisibleBorderLayerId)) {
				this.map.removeLayer(this.invisibleBorderLayerId)
			}
			this.removeBorder()
			this.removeLine()
			if (this.map.getSource(this.layerId)) {
				this.map.removeSource(this.layerId)
			}
		},
		removeLine() {
			this.currentSegments.forEach((i) => {
				if (this.map.getLayer(this.segmentLayerId(i))) {
					this.map.removeLayer(this.segmentLayerId(i))
				}
			})
			this.currentSegments = []
		},
		removeBorder() {
			if (this.map.getLayer(this.borderLayerId)) {
				this.map.removeLayer(this.borderLayerId)
			}
		},
		drawBorder() {
			this.map.addLayer({
				type: 'line',
				source: this.layerId,
				id: this.borderLayerId,
				paint: {
					'line-color': this.borderColor,
					'line-width': this.lineWidth * 0.3,
					'line-opacity': this.opacity,
					'line-gap-width': this.lineWidth,
				},
				layout: {
					'line-cap': 'round',
					'line-join': 'round',
				},
				filter: ['!=', '$type', 'Point'],
			})
		},
		// add and remove the segment layers so they match the segments present in
		// the source data, and refresh the gradient of the existing ones
		updateSegmentLayers() {
			const nbSegments = this.segmentSteps.length
			let removed = false
			this.currentSegments.forEach((i) => {
				if (i >= nbSegments && this.map.getLayer(this.segmentLayerId(i))) {
					this.map.removeLayer(this.segmentLayerId(i))
					removed = true
				}
			})
			// add the missing segments after removing the stale ones, the layer order
			// does not matter, segments cover distinct features
			let added = false
			for (let i = 0; i < nbSegments; i++) {
				const layerId = this.segmentLayerId(i)
				if (this.map.getLayer(layerId)) {
					this.map.setPaintProperty(layerId, 'line-gradient', this.getGradientExpression(this.segmentSteps[i]))
				} else {
					this.addSegmentLayer(i)
					added = true
				}
			}
			this.currentSegments = this.segmentSteps.map((seg, i) => i)
			if (added || removed) {
				// re-added layers go on top of anything drawn after init
				if (this.arrows) {
					this.bringArrowsToTop()
				}
				this.bringWaypointsToTop()
			}
		},
		addSegmentLayer(i) {
			this.map.addLayer({
				type: 'line',
				// all the segment layers read from the single shared source, each one
				// rendering the feature of its own segment
				source: this.layerId,
				id: this.segmentLayerId(i),
				filter: ['all', ['!=', ['geometry-type'], 'Point'], ['==', ['get', 's'], i]],
				paint: {
					'line-width': this.lineWidth,
					'line-opacity': this.opacity,
					'line-gradient': this.getGradientExpression(this.segmentSteps[i]),
				},
				layout: {
					'line-cap': 'round',
					'line-join': 'round',
				},
			})
		},
		init() {
			this.map.addSource(this.layerId, {
				type: 'geojson',
				lineMetrics: true,
				data: this.trackGeojsonData,
			})
			this.map.addLayer({
				type: 'line',
				source: this.layerId,
				id: this.invisibleBorderLayerId,
				paint: {
					'line-opacity': 0,
					'line-width': Math.max(this.lineWidth, 30),
				},
				layout: {
					'line-cap': 'round',
					'line-join': 'round',
				},
			})
			if (this.border) {
				this.drawBorder()
			}
			this.updateSegmentLayers()

			this.ready = true
		},
	},
	render(h) {
		return null
	},
}
</script>
