import { LngLat } from 'maplibre-gl'

export const METERSTOMILES = 0.0006213711
export const METERSTOFOOT = 3.28084
export const METERSTONAUTICALMILES = 0.000539957

export function basename(str) {
	let base = String(str).substring(str.lastIndexOf('/') + 1)
	if (base.lastIndexOf('.') !== -1) {
		base = base.substring(0, base.lastIndexOf('.'))
	}
	return base
}

export function hexToRgb(hex) {
	const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex)
	return result
		? {
			r: parseInt(result[1], 16),
			g: parseInt(result[2], 16),
			b: parseInt(result[3], 16),
		}
		: null
}

export function brify(str, linesize) {
	let res = ''
	const words = str.split(' ')
	let cpt = 0
	let toAdd = ''
	for (let i = 0; i < words.length; i++) {
		if ((cpt + words[i].length) < linesize) {
			toAdd += words[i] + ' '
			cpt += words[i].length + 1
		} else {
			res += toAdd + '<br/>'
			toAdd = words[i] + ' '
			cpt = words[i].length + 1
		}
	}
	res += toAdd
	return res
}

export function metersToDistanceNoAdaptNoUnit(m, unit) {
	const n = parseFloat(m)
	if (unit === 'metric') {
		return (n / 1000).toFixed(2)
	} else if (unit === 'english') {
		return (n * METERSTOMILES).toFixed(2)
	} else if (unit === 'nautical') {
		return (n * METERSTONAUTICALMILES).toFixed(2)
	}
}

export function metersToDistance(m, unit = 'metric') {
	const n = parseFloat(m)
	if (unit === 'metric') {
		if (n > 1000) {
			return (n / 1000).toFixed(2) + ' km'
		} else {
			return n.toFixed(2) + ' m'
		}
	} else if (unit === 'english') {
		const mi = n * METERSTOMILES
		if (mi < 1) {
			return (n * METERSTOFOOT).toFixed(2) + ' ft'
		} else {
			return mi.toFixed(2) + ' mi'
		}
	} else if (unit === 'nautical') {
		const nmi = n * METERSTONAUTICALMILES
		return nmi.toFixed(2) + ' nmi'
	}
}

export function metersToElevation(m, unit = 'metric') {
	const n = parseFloat(m)
	if (unit === 'metric' || unit === 'nautical') {
		return n.toFixed(2) + ' m'
	} else {
		return (n * METERSTOFOOT).toFixed(2) + ' ft'
	}
}

export function metersToElevationNoUnit(m, unit) {
	const n = parseFloat(m)
	if (unit === 'metric' || unit === 'nautical') {
		return n.toFixed(2)
	} else {
		return (n * METERSTOFOOT).toFixed(2)
	}
}

export function kmphToSpeed(kmph, unit = 'metric') {
	const nkmph = parseFloat(kmph)
	if (unit === 'metric') {
		return nkmph.toFixed(2) + ' km/h'
	} else if (unit === 'english') {
		return (nkmph * 1000 * METERSTOMILES).toFixed(2) + ' mi/h'
	} else if (unit === 'nautical') {
		return (nkmph * 1000 * METERSTONAUTICALMILES).toFixed(2) + ' kt'
	}
}

export function kmphToSpeedNoUnit(kmph, unit) {
	const nkmph = parseFloat(kmph)
	if (unit === 'metric') {
		return nkmph.toFixed(2)
	} else if (unit === 'english') {
		return (nkmph * 1000 * METERSTOMILES).toFixed(2)
	} else if (unit === 'nautical') {
		return (nkmph * 1000 * METERSTONAUTICALMILES).toFixed(2)
	}
}

export function minPerKmToPace(minPerKm, unit = 'metric') {
	const nMinPerKm = parseFloat(minPerKm)
	if (unit === 'metric') {
		return nMinPerKm.toFixed(2) + ' min/km'
	} else if (unit === 'english') {
		return (nMinPerKm / 1000 / METERSTOMILES).toFixed(2) + ' min/mi'
	} else if (unit === 'nautical') {
		return (nMinPerKm / 1000 / METERSTONAUTICALMILES).toFixed(2) + ' min/nmi'
	}
}

// eslint-disable-next-line
Number.prototype.pad = function(size) {
	let s = String(this)
	while (s.length < (size || 2)) { s = '0' + s }
	return s
}

export function formatDuration(seconds) {
	return parseInt(seconds / 3600).pad(2) + ':' + parseInt((seconds % 3600) / 60).pad(2) + ':' + (seconds % 60).pad(2)
}

export function escapeHtml(text) {
	const map = {
		'&': '&amp;',
		'<': '&lt;',
		'>': '&gt;',
		'"': '&quot;',
		"'": '&#039;',
	}
	return text.replace(/[&<>"']/g, function(m) { return map[m] })
}

let mytimer = 0
export function delay(callback, ms) {
	return function() {
		const context = this
		const args = arguments
		clearTimeout(mytimer)
		mytimer = setTimeout(function() {
			callback.apply(context, args)
		}, ms || 0)
	}
}

const timers = {}
export function keyDelay(key, callback, ms) {
	return function() {
		const context = this
		const args = arguments
		clearTimeout(timers[key])
		timers[key] = setTimeout(function() {
			callback.apply(context, args)
		}, ms || 0)
	}
}

export function strcmp(a, b) {
	const la = a.toLowerCase()
	const lb = b.toLowerCase()
	return la > lb
		? 1
		: la < lb
			? -1
			: 0
}

export function randomString(length = 8) {
	const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-.,_'
	let str = ''
	for (let i = 0; i < length; i++) {
		str += chars.charAt(Math.floor(Math.random() * chars.length))
	}
	return str
}

export function getPaces(coords) {
	const timestamps = coords.map(c => c[3])
	const lngLats = coords.map((c) => new LngLat(c[0], c[1]))
	const paces = []

	let i, distanceToPrev
	let j = 0
	let distWindow = 0

	let distanceFromStart = 0
	paces.push(0)

	// if there is a missing time : pace is 0
	for (i = 0; i < coords.length; i++) {
		if (!timestamps[i]) {
			return coords.map(c => 0)
		}
	}

	for (i = 1; i < coords.length; i++) {
		// in km
		distanceToPrev = lngLats[i - 1].distanceTo(lngLats[i]) / 1000
		distanceFromStart += distanceToPrev
		distWindow += distanceToPrev

		if (distanceFromStart < 1) {
			paces.push(0)
		} else {
			// get the pace (time to do the last km/mile) for this point
			while (j < i && distWindow > 1) {
				j++
				distWindow = distWindow - (lngLats[j - 1].distanceTo(lngLats[j]) / 1000)
				/*
				if (unit === 'metric') {
					distWindow = distWindow - (gpxpod.map.distance([latlngs[j - 1][0], latlngs[j - 1][1]], [latlngs[j][0], latlngs[j][1]]) / 1000)
				} else if (unit === 'nautical') {
					distWindow = distWindow - (METERSTONAUTICALMILES * gpxpod.map.distance([latlngs[j - 1][0], latlngs[j - 1][1]], [latlngs[j][0], latlngs[j][1]]))
				} else if (unit === 'english') {
					distWindow = distWindow - (METERSTOMILES * gpxpod.map.distance([latlngs[j - 1][0], latlngs[j - 1][1]], [latlngs[j][0], latlngs[j][1]]))
				}
				*/
			}
			// the j to consider is j-1 (when dist between j and i is more than 1)
			// in minutes
			paces.push((timestamps[i] - timestamps[j - 1]) / 60)
		}
	}
	return paces
}
