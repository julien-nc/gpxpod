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

export function getColorGradientColors(startHue = 0, endHue = 120, percentStep = 0.1) {
	const hueDiff = endHue - startHue
	const result = []
	for (let i = 0; i <= 1; i += percentStep) {
		result.push('hsl(' + (startHue + i * hueDiff).toString(10) + ', 100%, 50%)',)
	}
	return result
	/*
	return [
		'hsl(' + maxHue + ', 100%, 50%)',
		'hsl(' + (minHue + 0.9 * hueDiff).toString(10) + ', 100%, 50%)',
		'hsl(' + (minHue + 0.8 * hueDiff).toString(10) + ', 100%, 50%)',
		'hsl(' + (minHue + 0.7 * hueDiff).toString(10) + ', 100%, 50%)',
		'hsl(' + (minHue + 0.6 * hueDiff).toString(10) + ', 100%, 50%)',
		'hsl(' + (minHue + 0.5 * hueDiff).toString(10) + ', 100%, 50%)',
		'hsl(' + (minHue + 0.4 * hueDiff).toString(10) + ', 100%, 50%)',
		'hsl(' + (minHue + 0.3 * hueDiff).toString(10) + ', 100%, 50%)',
		'hsl(' + (minHue + 0.2 * hueDiff).toString(10) + ', 100%, 50%)',
		'hsl(' + (minHue + 0.1 * hueDiff).toString(10) + ', 100%, 50%)',
		'hsl(' + minHue + ', 100%, 50%)',
	]
	*/
}

export function getColorHueInInterval(startHue = 0, endHue = 120, weight) {
	const hueDiff = endHue - startHue
	return startHue + (weight * hueDiff)
}
