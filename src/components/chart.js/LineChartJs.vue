<template>
	<Line :data="data" :options="options" />
</template>

<script>
import { Line } from 'vue-chartjs'
import { Chart as ChartJS, Title, Tooltip, Legend, PointElement, CategoryScale, LinearScale, LineElement, Filler } from 'chart.js'
ChartJS.register(Title, Tooltip, Legend, PointElement, CategoryScale, LinearScale, LineElement, Filler)

export default {
	name: 'LineChartJs',
	components: {
		Line,
	},
	props: {
		data: {
			type: Object,
			required: true,
		},
		options: {
			type: Object,
			required: true,
		},
	},
}
</script>
