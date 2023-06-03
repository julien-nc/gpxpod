<template>
	<NcAppNavigation ref="nav">
		<template #default>
			<NcSelect
				:value="selectedPair"
				class="pair-select"
				:options="pairs"
				input-id="pair-select"
				:clearable="false"
				@input="$emit('pair-selected', $event)" />
			<select
				:value="selectedCriteria"
				@change="onCriteriaChange">
				<option value="time">
					{{ t('gpxpod', 'Time') }}
				</option>
				<option value="distance">
					{{ t('gpxpod', 'Distance') }}
				</option>
				<option value="elevation">
					{{ t('gpxpod', 'Cumulative elevation gain') }}
				</option>
			</select>
		</template>
		<!--template #list>
			list
		</template-->
	</NcAppNavigation>
</template>

<script>
import NcAppNavigation from '@nextcloud/vue/dist/Components/NcAppNavigation.js'
import NcSelect from '@nextcloud/vue/dist/Components/NcSelect.js'

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
		selectedCriteria: {
			type: String,
			required: true,
		},
	},

	data() {
		return {
		}
	},

	computed: {
	},

	watch: {
	},

	mounted() {
	},

	methods: {
		onCriteriaChange(e) {
			this.$emit('criteria-selected', e.target.value)
		},
	},
}
</script>

<style scoped lang="scss">
// nothing yet
</style>
