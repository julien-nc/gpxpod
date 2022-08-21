<template>
	<LineChartJs
		:chart-data="chartData"
		:chart-options="chartOptions" />
</template>

<script>
import LineChartJs from './chart.js/LineChartJs.vue'
import { LngLat } from 'maplibre-gl'
import { formatDuration, kmphToSpeed, metersToElevation, metersToDistance } from '../utils.js'
import moment from '@nextcloud/moment'

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
				return ['time', 'distance'].includes(value)
			},
		},
	},

	data() {
		return {
		}
	},

	computed: {
		chartData() {
			const labels = []
			const elevationData = []
			const speedData = []
			const getLineLabels = this.xAxis === 'time'
				? this.getLineTimeLabels
				: this.xAxis === 'distance'
					? this.getLineDistanceLabels
					: () => []

			this.track.geojson.features.forEach((feature) => {
				if (feature.geometry.type === 'LineString') {
					labels.push(...getLineLabels(feature.geometry.coordinates, labels[labels.length - 1] ?? 0))
					elevationData.push(...this.getLineElevationData(feature.geometry.coordinates))
					speedData.push(...this.getLineSpeedData(feature.geometry.coordinates))
				} else if (feature.geometry.type === 'MultiLineString') {
					feature.geometry.coordinates.forEach((coords) => {
						labels.push(...getLineLabels(coords, labels[labels.length - 1] ?? 0))
						elevationData.push(...this.getLineElevationData(coords))
						speedData.push(...this.getLineSpeedData(coords))
					})
				}
			})

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
				data: elevationData,
				id: 'elevation',
				label: t('gpxpod', 'Elevation'),
				// FIXME hacky way to change alpha channel:
				backgroundColor: '#00ffff4D',
				pointBackgroundColor: '#00ffff',
				borderColor: '#00ffff',
				pointHighlightStroke: '#00ffff',
				// // deselect the dataset from the beginning
				// hidden: condition,
				order: 0,
				yAxisID: 'elevation',
			}

			const speedDataSet = {
				...commonDataSetValues,
				data: speedData,
				id: 'speed',
				label: t('gpxpod', 'Speed'),
				// FIXME hacky way to change alpha channel:
				backgroundColor: '#ffff004D',
				pointBackgroundColor: '#ffff00',
				borderColor: '#ffff00',
				pointHighlightStroke: '#ffff00',
				// // deselect the dataset from the beginning
				// hidden: condition,
				order: 1,
				yAxisID: 'speed',
			}

			return {
				labels,
				datasets: [
					elevationDataSet,
					speedDataSet,
				],
			}
		},
		firstValidXValue() {
			return this.chartData.labels.find(ts => { return !!ts })
		},
		chartOptions() {
			const firstValidXValue = this.firstValidXValue
			const that = this
			return {
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
					x: {
						ticks: {
							// display: false,
							// eslint-disable-next-line
							callback: function(value, index, ticks) {
								if (that.xAxis === 'time' && firstValidXValue && value) {
									return formatDuration(this.getLabelForValue(value) - firstValidXValue)
									// return moment.unix(value).format('HH:mm')
								} else if (that.xAxis === 'distance' && value) {
									return metersToDistance(this.getLabelForValue(value))
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
						intersect: false,
						mode: 'index',
						callbacks: {
							// eslint-disable-next-line
							title: function(context) {
								return context[0]?.label
									? that.xAxis === 'time'
										? moment.unix(context[0].label).format('YYYY-MM-DD HH:mm:ss (Z)')
										: that.xAxis === 'distance'
											? t('gpxpod', 'Traveled distance') + ' ' + metersToDistance(context[0].label)
											: '??'
									: '??'
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
							? t('gpxpod', 'By time')
							: that.xAxis === 'distance'
								? t('gpxpod', 'By traveled distance')
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
				onHover: this.onChartHover,
			}
		},
	},

	methods: {
		getTooltipLabel(context) {
			const formattedValue = context.dataset.id === 'elevation'
				? metersToElevation(context.raw)
				: context.dataset.id === 'speed'
					? kmphToSpeed(context.raw)
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
		getLineTimeLabels(points, previousValue) {
			return points.map(p => {
				return p[3] ?? 0
			})
		},
		getLineDistanceLabels(points, previousValue) {
			console.debug('dddddddddd', previousValue)
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
		onChartHover(event, data) {
			// console.debug('hover', event, data)
		},
	},
}
</script>
