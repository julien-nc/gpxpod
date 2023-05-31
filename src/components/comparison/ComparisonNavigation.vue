<template>
	<NcAppNavigation ref="nav">
		<template #list>
			list
		</template>
		<template #default>
			<NcSelect
				:value="selectedPair"
				class="pair-select"
				:options="formattedPairs"
				input-id="pair-select"
				:clearable="false"
				@input="$emit('pair-selected', $event)" />
		</template>
	</NcAppNavigation>
</template>

<script>
import NcAppNavigation from '@nextcloud/vue/dist/Components/NcAppNavigation.js'
import NcSelect from '@nextcloud/vue/dist/Components/NcSelect.js'

import { emit } from '@nextcloud/event-bus'
import { basename } from '@nextcloud/paths'

export default {
	name: 'ComparisonNavigation',

	components: {
		NcAppNavigation,
		NcSelect,
	},

	props: {
		pairs: {
			type: Array,
			required: true,
		},
		stats: {
			type: Object,
			required: true,
		},
		selectedPair: {
			type: Object,
			required: true,
		},
	},

	data() {
		return {
		}
	},

	computed: {
		formattedPairs() {
			const result = []
			return this.pairs.map(p => {
				return {
					id: p.track1 + '|' + p.track2,
					value: [p.track1, p.track2],
					label: basename(p.track1) + ' -> ' + basename(p.track2),
				}
			})
		},
	},

	watch: {
	},

	mounted() {
	},

	methods: {
	},
}
</script>

<style scoped lang="scss">
// nothing yet
</style>
