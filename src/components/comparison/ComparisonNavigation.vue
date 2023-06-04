<template>
	<NcAppNavigation ref="nav"
		class="comparisonNavigation compact">
		<template #default>
			<h2>
				<ScaleBalanceIcon :size="20" class="icon" />
				<span>{{ t('gpxpod', 'Track comparison') }}</span>
			</h2>
			<div class="field">
				<label for="pair-select">
					{{ t('gpxpod', 'Pair') }}
				</label>
				<NcSelect
					:value="selectedPair"
					class="pair-select"
					:options="pairs"
					input-id="pair-select"
					:clearable="false"
					@input="$emit('pair-selected', $event)" />
			</div>
			<div class="field">
				<label for="criteria">
					{{ t('gpxpod', 'Color by') }}
				</label>
				<select id="criteria"
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
			</div>
		</template>
		<!--template #list>
			list
		</template-->
		<template #footer>
			<div id="app-settings">
				<div id="app-settings-header">
					<NcAppNavigationItem
						:name="t('gpxpod', 'Global comparison table')"
						@click="showSidebar">
						<template #icon>
							<TableLargeIcon :size="20" />
						</template>
					</NcAppNavigationItem>
				</div>
			</div>
		</template>
	</NcAppNavigation>
</template>

<script>
import ScaleBalanceIcon from 'vue-material-design-icons/ScaleBalance.vue'
import TableLargeIcon from 'vue-material-design-icons/TableLarge.vue'

import NcAppNavigation from '@nextcloud/vue/dist/Components/NcAppNavigation.js'
import NcAppNavigationItem from '@nextcloud/vue/dist/Components/NcAppNavigationItem.js'
import NcSelect from '@nextcloud/vue/dist/Components/NcSelect.js'

export default {
	name: 'ComparisonNavigation',

	components: {
		NcAppNavigation,
		NcAppNavigationItem,
		NcSelect,
		ScaleBalanceIcon,
		TableLargeIcon,
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
		showSidebar() {
			this.$emit('show-sidebar-clicked')
		},
	},
}
</script>

<style scoped lang="scss">
.comparisonNavigation {
	.field {
		margin: 12px 4px 0 4px;
		display: flex;
		flex-direction: column;
	}

	h2 {
		display: flex;
		justify-content: center;
		margin-top: 4px;
		.icon {
			margin-right: 8px;
		}
	}

	:deep(.app-navigation-toggle) {
		top: 0px !important;
		right: 0px !important;

		color: var(--color-main-text) !important;
		background-color: var(--color-main-background) !important;

		&:focus,
		&:hover {
			background-color: var(--color-background-hover) !important;
		}
	}

	&.compact :deep(.app-navigation-toggle) {
		margin-right: -54px !important;
		top: 6px !important;
	}
}
</style>
