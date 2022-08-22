<template>
	<LineChartJs
		:chart-data="chartData"
		:chart-options="chartOptions"
		@mouseenter.native="onChartMouseEnter"
		@mouseout.native="onChartMouseOut" />
</template>

<script>
import LineChartJs from './chart.js/LineChartJs.vue'
import { LngLat } from 'maplibre-gl'
import { formatDuration, kmphToSpeed, metersToElevation, metersToDistance, delay, getPaces, minPerKmToPace } from '../utils.js'
import moment from '@nextcloud/moment'
import { emit } from '@nextcloud/event-bus'

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

export default {
	name: 'TrackChart',

	components: {
		LineChartJs,
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
	},

	data() {
		return {
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
		chartData() {
			const commonDataSetValues = {
				// lineTension: 0.2,
				// pointRadius: Array(elevationData.length).fill(0),
				pointRadius: 0,
				pointHoverRadius: 8,
				fill: true,
				borderWidth: 3,
			}

			const elevationDataSet = {
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

			const speedDataSet = {
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

			const paceDataSet = {
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

			return {
				// we don't care about this, we compute the labels in options.plugins.tooltip.callbacks.title
				labels: this.dataLabels.timestamps,
				datasets: [
					elevationDataSet,
					speedDataSet,
					paceDataSet,
				],
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
						position: 'left',
					},
					speed: {
						position: 'right',
					},
					pace: {
						position: 'right',
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
									return metersToDistance(that.dataLabels.traveledDistance[index])
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
								return moment.unix(that.dataLabels.timestamps[index]).format('YYYY-MM-DD HH:mm:ss (Z)')
									+ '\n' + t('gpxpod', 'Elapsed time') + ': ' + formatDuration(that.dataLabels.timestamps[index] - firstValidTimestamp)
									+ '\n' + t('gpxpod', 'Traveled distance') + ': ' + metersToDistance(that.dataLabels.traveledDistance[index])
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

	methods: {
		getTooltipLabel(context) {
			const formattedValue = context.dataset.id === 'elevation'
				? metersToElevation(context.raw)
				: context.dataset.id === 'speed'
					? kmphToSpeed(context.raw)
					: context.dataset.id === 'pace'
						? minPerKmToPace(context.raw)
						: '??'
			return context.dataset.label + ': ' + formattedValue
		},
		getLineElevationData(points) {
			return points.map(p => {
				return p[2] ?? 0
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
					this.speedData[index],
					this.paceData[index],
					this.track.color,
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
			emit('chart-mouseenter')
		},
	},
}
</script>
