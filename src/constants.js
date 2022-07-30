import { translate as t } from '@nextcloud/l10n'

export const COLOR_CRITERIAS = {
	none: {
		value: 0,
		label: t('gpxpod', 'None'),
	},
	elevation: {
		value: 1,
		label: t('gpxpod', 'Elevation'),
	},
	speed: {
		value: 2,
		label: t('gpxpod', 'Speed'),
	},
	pace: {
		value: 3,
		label: t('gpxpod', 'Pace'),
	},
}
