<template>
	<LineChartJs v-if="shouldDrawChart"
		:chart-data="chartData"
		:chart-options="chartOptions"
		@mouseenter.native="onChartMouseEnter"
		@mouseout.native="onChartMouseOut" />
	<NcEmptyContent v-else
		:title="t('gpxpod', 'No data to display')">
		<template #icon>
			<ChartLineIcon />
		</template>
	</NcEmptyContent>
</template>

<script>
import ChartLineIcon from 'vue-material-design-icons/ChartLine.vue'

import { LngLat } from 'maplibre-gl'

import LineChartJs from './chart.js/LineChartJs.vue'
import {
	formatDuration, kmphToSpeed, metersToElevation,
	metersToDistance, delay, minPerKmToPace, formatExtensionKey, formatExtensionValue,
} from '../utils.js'
import { getPaces } from '../mapUtils.js'

import moment from '@nextcloud/moment'
import { emit, subscribe, unsubscribe } from '@nextcloud/event-bus'

import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'

import { Tooltip } from 'chart.js'
Tooltip.positioners.top = function(elements, eventPosition) {
	// 'this' is a reference to the tooltip
	// const tooltip = this
	return {
		x: eventPosition.x,
		y: 0,
		// possible to include xAlign and yAlign to override tooltip options
	}
}

const SPEED_COLOR = '#ffa500'
const ELEVATION_COLOR = '#00ffff'
const PACE_COLOR = '#ff00ff'
const EXTENSION_COLOR = '#88ff88'

export default {
	name: 'TrackChart',

	components: {
		LineChartJs,
		ChartLineIcon,
		NcEmptyContent,
	},

	props: {
		track: {
			type: Object,
			required: true,
		},
		xAxis: {
			type: String,
			default: 'time',
			validator(value) {
				return ['time', 'date', 'distance'].includes(value)
			},
		},
		extension: {
			type: String,
			default: null,
		},
		extensionType: {
			type: String,
			default: null,
		},
		chartYScale: {
			type: String,
			default: null,
		},
		settings: {
			type: Object,
			required: true,
		},
	},

	data() {
		return {
			pointIndexToShow: null,
		}
	},

	computed: {
		dataLabels() {
			const dl = {
				timestamps: [],
				traveledDistance: [],
			}
			this.track.geojson.features.forEach((feature) => {
				if (feature.geometry.type === 'LineString') {
					dl.timestamps.push(...this.getLineTimestampLabels(feature.geometry.coordinates))
					dl.traveledDistance.push(...this.getLineDistanceLabels(feature.geometry.coordinates, dl.traveledDistance[dl.traveledDistance.length - 1] ?? 0))
				} else if (feature.geometry.type === 'MultiLineString') {
					feature.geometry.coordinates.forEach((coords) => {
						dl.timestamps.push(...this.getLineTimestampLabels(coords))
						dl.traveledDistance.push(...this.getLineDistanceLabels(coords, dl.traveledDistance[dl.traveledDistance.length - 1] ?? 0))
					})
				}
			})
			return dl
		},
		pointsArray() {
			const points = []
			this.track.geojson.features.forEach((feature) => {
				if (feature.geometry.type === 'LineString') {
					points.push(...feature.geometry.coordinates)
				} else if (feature.geometry.type === 'MultiLineString') {
					feature.geometry.coordinates.forEach((coords) => {
						points.push(...coords)
					})
				}
			})
			return points
		},
		firstValidTimestamp() {
			return this.dataLabels.timestamps.find(ts => { return !!ts })
		},
		elevationData() {
			const elevationData = []
			this.track.geojson.features.forEach((feature) => {
				if (feature.geometry.type === 'LineString') {
					elevationData.push(...this.getLineElevationData(feature.geometry.coordinates))
				} else if (feature.geometry.type === 'MultiLineString') {
					feature.geometry.coordinates.forEach((coords) => {
						elevationData.push(...this.getLineElevationData(coords))
					})
				}
			})
			return elevationData
		},
		speedData() {
			const speedData = []
			this.track.geojson.features.forEach((feature) => {
				if (feature.geometry.type === 'LineString') {
					speedData.push(...this.getLineSpeedData(feature.geometry.coordinates))
				} else if (feature.geometry.type === 'MultiLineString') {
					feature.geometry.coordinates.forEach((coords) => {
						speedData.push(...this.getLineSpeedData(coords))
					})
				}
			})
			return speedData
		},
		paceData() {
			const paceData = []
			this.track.geojson.features.forEach((feature) => {
				if (feature.geometry.type === 'LineString') {
					paceData.push(...getPaces(feature.geometry.coordinates))
				} else if (feature.geometry.type === 'MultiLineString') {
					feature.geometry.coordinates.forEach((coords) => {
						paceData.push(...getPaces(coords))
					})
				}
			})
			return paceData
		},
		extensionData() {
			if (!this.extension || !this.extensionType) {
				return []
			}
			const extensionData = []
			this.track.geojson.features.forEach((feature) => {
				if (feature.geometry.type === 'LineString') {
					extensionData.push(...this.getLineExtensionData(feature.geometry.coordinates))
				} else if (feature.geometry.type === 'MultiLineString') {
					feature.geometry.coordinates.forEach((coords) => {
						extensionData.push(...this.getLineExtensionData(coords))
					})
				}
			})
			return extensionData
		},
		shouldDrawElevation() {
			return this.elevationData.filter(ele => ele !== null).length !== 0
		},
		shouldDrawSpeed() {
			return this.speedData.filter(sp => sp !== 0 && sp !== null).length !== 0
		},
		shouldDrawPace() {
			return this.paceData.filter(pace => pace !== 0).length !== 0
		},
		shouldDrawExtension() {
			return this.extensionData.filter(extValue => !!extValue).length !== 0
		},
		shouldDrawChart() {
			return this.shouldDrawSpeed || this.shouldDrawElevation || this.shouldDrawPace || this.shouldDrawExtension
		},
		chartData() {
			const commonDataSetValues = {
				// lineTension: 0.2,
				pointRadius: 0,
				pointHoverRadius: 8,
				fill: true,
				borderWidth: 3,
			}
			// this is slow
			/*
			if (this.pointIndexToShow !== null) {
				commonDataSetValues.pointRadius = Array(this.elevationData.length).fill(0)
				commonDataSetValues.pointRadius[this.pointIndexToShow] = 8
			}
			*/

			// don't draw elevation data if it only contains null values
			const elevationDataSet = this.shouldDrawElevation
				? {
					...commonDataSetValues,
					data: this.elevationData,
					id: 'elevation',
					label: t('gpxpod', 'Elevation'),
					backgroundColor: ELEVATION_COLOR + '4D',
					pointBackgroundColor: ELEVATION_COLOR,
					borderColor: ELEVATION_COLOR,
					pointHighlightStroke: ELEVATION_COLOR,
					// // deselect the dataset from the beginning
					// hidden: condition,
					order: 0,
					yAxisID: 'elevation',
				}
				: null

			const speedDataSet = this.shouldDrawSpeed
				? {
					...commonDataSetValues,
					data: this.speedData,
					id: 'speed',
					label: t('gpxpod', 'Speed'),
					backgroundColor: SPEED_COLOR + '4D',
					pointBackgroundColor: SPEED_COLOR,
					borderColor: SPEED_COLOR,
					pointHighlightStroke: SPEED_COLOR,
					// // deselect the dataset from the beginning
					// hidden: condition,
					order: 1,
					yAxisID: 'speed',
				}
				: null

			const paceDataSet = this.shouldDrawPace
				? {
					...commonDataSetValues,
					data: this.paceData,
					id: 'pace',
					label: t('gpxpod', 'Pace'),
					backgroundColor: PACE_COLOR + '4D',
					pointBackgroundColor: PACE_COLOR,
					borderColor: PACE_COLOR,
					pointHighlightStroke: PACE_COLOR,
					// // deselect the dataset from the beginning
					// hidden: condition,
					order: 2,
					yAxisID: 'pace',
				}
				: null

			const extensionDataSet = this.shouldDrawExtension
				? {
					...commonDataSetValues,
					data: this.extensionData,
					id: 'extension',
					label: formatExtensionKey(this.extension),
					backgroundColor: EXTENSION_COLOR + '4D',
					pointBackgroundColor: EXTENSION_COLOR,
					borderColor: EXTENSION_COLOR,
					pointHighlightStroke: EXTENSION_COLOR,
					// // deselect the dataset from the beginning
					// hidden: condition,
					order: 3,
					yAxisID: 'extension',
				}
				: null

			return {
				// we don't care about this, we compute the labels in options.plugins.tooltip.callbacks.title
				labels: this.dataLabels.timestamps,
				datasets: [
					elevationDataSet,
					speedDataSet,
					paceDataSet,
					extensionDataSet,
				].filter(e => e !== null),
			}
		},
		chartOptions() {
			const that = this
			const firstValidTimestamp = this.firstValidTimestamp
			return {
				normalized: true,
				animation: false,
				elements: {
					line: {
						// by default, fill lines to the previous dataset
						// fill: '-1',
						// fill: 'origin',
						cubicInterpolationMode: 'monotone',
					},
				},
				scales: {
					elevation: {
						position: 'right',
						display: this.chartYScale === 'elevation',
						ticks: {
							// display: false,
							// eslint-disable-next-line
							callback: function(value, index, ticks) {
								return metersToElevation(value, that.settings.distance_unit)
							},
						},
					},
					speed: {
						position: 'right',
						display: this.chartYScale === 'speed',
						ticks: {
							// display: false,
							// eslint-disable-next-line
							callback: function(value, index, ticks) {
								return kmphToSpeed(value, that.settings.distance_unit)
							},
						},
					},
					pace: {
						position: 'right',
						display: this.chartYScale === 'pace',
						ticks: {
							// display: false,
							// eslint-disable-next-line
							callback: function(value, index, ticks) {
								return minPerKmToPace(value, that.settings.distance_unit)
							},
						},
					},
					extension: {
						position: 'right',
						display: this.chartYScale === 'extension',
						ticks: {
							// display: false,
							// eslint-disable-next-line
							callback: function(value, index, ticks) {
								return formatExtensionValue(that.extension, value, that.settings.distance_unit)
							},
						},
					},
					x: {
						ticks: {
							// display: false,
							// eslint-disable-next-line
							callback: function(value, index, ticks) {
								if (that.xAxis === 'time' && firstValidTimestamp && that.dataLabels.timestamps[index]) {
									return formatDuration(that.dataLabels.timestamps[index] - firstValidTimestamp)
								} else if (that.xAxis === 'date' && that.dataLabels.timestamps[index]) {
									return moment.unix(that.dataLabels.timestamps[index]).format('YYYY-MM-DD HH:mm:ss')
								} else if (that.xAxis === 'distance') {
									return metersToDistance(that.dataLabels.traveledDistance[index], that.settings.distance_unit)
								}
								return ''
							},
						},
					},
				},
				plugins: {
					legend: {
						position: 'top',
					},
					tooltip: {
						position: 'top',
						yAlign: 'bottom',
						intersect: false,
						mode: 'index',
						callbacks: {
							// eslint-disable-next-line
							title: function(context) {
								const index = context[0]?.dataIndex
								const labels = []
								if (that.dataLabels.timestamps[index]) {
									labels.push(moment.unix(that.dataLabels.timestamps[index]).format('YYYY-MM-DD HH:mm:ss (Z)'))
									labels.push(t('gpxpod', 'Elapsed time') + ': ' + formatDuration(that.dataLabels.timestamps[index] - firstValidTimestamp))
								}
								labels.push(t('gpxpod', 'Traveled distance') + ': ' + metersToDistance(that.dataLabels.traveledDistance[index], that.settings.distance_unit))
								return labels.join('\n')
							},
							// eslint-disable-next-line
							label: function(context) {
								return that.getTooltipLabel(context)
							},
						},
					},
					title: {
						display: true,
						text: that.xAxis === 'time'
							? t('gpxpod', 'By elapsed time')
							: that.xAxis === 'distance'
								? t('gpxpod', 'By traveled distance')
								: that.xAxis === 'date'
									? t('gpxpod', 'By date')
									: '??',
						font: {
							weight: 'bold',
							size: 18,
						},
					},
				},
				responsive: true,
				maintainAspectRatio: false,
				showAllTooltips: false,
				hover: {
					intersect: false,
					mode: 'index',
				},
				onHover: this.onChartMouseEvent,
				onClick: this.onChartMouseEvent,
			}
		},
	},

	beforeMount() {
		subscribe('track-point-hover', this.onTrackPointHover)
	},

	beforeDestroy() {
		unsubscribe('track-point-hover', this.onTrackPointHover)
	},

	methods: {
		getTooltipLabel(context) {
			const formattedValue = context.dataset.id === 'elevation'
				? metersToElevation(context.raw, this.settings.distance_unit)
				: context.dataset.id === 'speed'
					? kmphToSpeed(context.raw, this.settings.distance_unit)
					: context.dataset.id === 'pace'
						? minPerKmToPace(context.raw, this.settings.distance_unit)
						: context.dataset.id === 'extension'
							? formatExtensionValue(this.extension, context.raw, this.settings.distance_unit)
							: '??'
			return context.dataset.label + ': ' + formattedValue
		},
		getLineExtensionData(points) {
			return points.map(p => {
				return p[4]?.[this.extensionType]?.[this.extension]
			})
		},
		getLineElevationData(points) {
			return points.map(p => {
				return p[2]
			})
		},
		getLineSpeedData(points) {
			const speeds = [0]
			for (let i = 1; i < points.length; i++) {
				speeds.push(this.getSpeed(points[i - 1], points[i]))
			}
			return speeds
		},
		getSpeed(p1, p2) {
			const ll1 = new LngLat(p1[0], p1[1])
			const ts1 = p1[3]
			const ll2 = new LngLat(p2[0], p2[1])
			const ts2 = p2[3]
			if (ts1 === null || ts2 === null) {
				return null
			}

			const distance = ll1.distanceTo(ll2)
			const time = ts2 - ts1
			return distance / time * 3.6
		},
		getLineTimestampLabels(points) {
			return points.map(p => {
				return p[3] ?? 0
			})
		},
		getLineDistanceLabels(points, previousValue) {
			const distances = [previousValue]
			let previousLngLat = new LngLat(points[0][0], points[0][1])
			for (let i = 1; i < points.length; i++) {
				const lngLat = new LngLat(points[i][0], points[i][1])
				const previousDistance = distances[distances.length - 1]
				distances.push(previousDistance + previousLngLat.distanceTo(lngLat))
				// distances.push(previousLngLat.distanceTo(lngLat))
				previousLngLat = lngLat
			}
			return distances
		},
		onChartMouseEvent(event, data) {
			if (data.length > 0 && data[0].index !== undefined) {
				const index = data[0].index
				const point = [
					...this.pointsArray[index],
					{
						speed: this.speedData[index] ?? null,
						pace: this.paceData[index] ?? null,
						color: this.track.color,
						extension: this.extension && this.extensionData[index]
							? {
								key: this.extension,
								value: this.extensionData[index],
							}
							: undefined,
					},
				]
				if (event.type === 'click') {
					// the click event is fired twice so persistent popups are created twice...
					// this is a dirty workaround
					delay(() => {
						emit('chart-point-hover', { point, persist: true })
					}, 100)()
				} else {
					emit('chart-point-hover', { point, persist: false })
				}
			}
		},
		onChartMouseOut(e) {
			emit('chart-mouseout', { keepPersistent: true })
		},
		onChartMouseEnter(e) {
			this.pointIndexToShow = null
			emit('chart-mouseenter')
		},
		onTrackPointHover({ trackId, pointIndex }) {
			if (trackId === this.track.id) {
				this.pointIndexToShow = pointIndex
			}
		},
	},
}
</script>
