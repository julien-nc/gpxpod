<template>
	<LineChartJs
		:chart-data="chartData"
		:chart-options="chartOptions" />
</template>

<script>
import LineChartJs from './chart.js/LineChartJs.vue'
import { LngLat } from 'maplibre-gl'
import { formatDuration, kmphToSpeed, metersToElevation } from '../utils.js'
import moment from '@nextcloud/moment'

export default {
	name: 'TrackChartByTime',

	components: {
		LineChartJs,
	},

	props: {
		track: {
			type: Object,
			required: true,
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

			this.track.geojson.features.forEach((feature) => {
				if (feature.geometry.type === 'LineString') {
					labels.push(...this.getLineLabels(feature.geometry.coordinates))
					elevationData.push(...this.getLineElevationData(feature.geometry.coordinates))
					speedData.push(...this.getLineSpeedData(feature.geometry.coordinates))
				} else if (feature.geometry.type === 'MultiLineString') {
					feature.geometry.coordinates.forEach((coords) => {
						labels.push(...this.getLineLabels(coords))
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
		firstTimestamp() {
			return this.chartData.labels.find(ts => { return !!ts })
		},
		chartOptions() {
			const firstTimestamp = this.firstTimestamp
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
								if (firstTimestamp && value) {
									return formatDuration(this.getLabelForValue(value) - firstTimestamp)
									// return moment.unix(value).format('HH:mm')
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
									? moment.unix(context[0].label).format('YYYY-MM-DD HH:mm:ss (Z)')
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
						text: t('gpxpod', 'By time'),
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
		getLineLabels(points) {
			return points.map(p => { return p[3] ?? 0 })
		},
		onChartHover(event, data) {
			// console.debug('hover', event, data)
		},
	},
}
</script>
