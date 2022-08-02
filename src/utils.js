const METERSTOMILES = 0.0006213711
const METERSTOFOOT = 3.28084
const METERSTONAUTICALMILES = 0.000539957

function basename(str) {
	let base = String(str).substring(str.lastIndexOf('/') + 1)
	if (base.lastIndexOf('.') !== -1) {
		base = base.substring(0, base.lastIndexOf('.'))
	}
	return base
}

function hexToRgb(hex) {
	const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex)
	return result
		? {
			r: parseInt(result[1], 16),
			g: parseInt(result[2], 16),
			b: parseInt(result[3], 16),
		}
		: null
}

function brify(str, linesize) {
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

function metersToDistanceNoAdaptNoUnit(m, unit) {
	const n = parseFloat(m)
	if (unit === 'metric') {
		return (n / 1000).toFixed(2)
	} else if (unit === 'english') {
		return (n * METERSTOMILES).toFixed(2)
	} else if (unit === 'nautical') {
		return (n * METERSTONAUTICALMILES).toFixed(2)
	}
}

function metersToDistance(m, unit) {
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

function metersToElevation(m, unit) {
	const n = parseFloat(m)
	if (unit === 'metric' || unit === 'nautical') {
		return n.toFixed(2) + ' m'
	} else {
		return (n * METERSTOFOOT).toFixed(2) + ' ft'
	}
}

function metersToElevationNoUnit(m, unit) {
	const n = parseFloat(m)
	if (unit === 'metric' || unit === 'nautical') {
		return n.toFixed(2)
	} else {
		return (n * METERSTOFOOT).toFixed(2)
	}
}

function kmphToSpeed(kmph, unit) {
	const nkmph = parseFloat(kmph)
	if (unit === 'metric') {
		return nkmph.toFixed(2) + ' km/h'
	} else if (unit === 'english') {
		return (nkmph * 1000 * METERSTOMILES).toFixed(2) + ' mi/h'
	} else if (unit === 'nautical') {
		return (nkmph * 1000 * METERSTONAUTICALMILES).toFixed(2) + ' kt'
	}
}

function kmphToSpeedNoUnit(kmph, unit) {
	const nkmph = parseFloat(kmph)
	if (unit === 'metric') {
		return nkmph.toFixed(2)
	} else if (unit === 'english') {
		return (nkmph * 1000 * METERSTOMILES).toFixed(2)
	} else if (unit === 'nautical') {
		return (nkmph * 1000 * METERSTONAUTICALMILES).toFixed(2)
	}
}

function minPerKmToPace(minPerKm, unit) {
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

function formatDuration(seconds) {
	return parseInt(seconds / 3600).pad(2) + ':' + parseInt((seconds % 3600) / 60).pad(2) + ':' + (seconds % 60).pad(2)
}

function escapeHtml(text) {
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
function delay(callback, ms) {
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
function keyDelay(key, callback, ms) {
	return function() {
		const context = this
		const args = arguments
		clearTimeout(timers[key])
		timers[key] = setTimeout(function() {
			callback.apply(context, args)
		}, ms || 0)
	}
}

class MousePositionControl {

	constructor(options) {
		this.options = options
	}

	onAdd(map) {
		this.map = map
		this.container = document.createElement('div')
		this.container.className = 'maplibregl-ctrl mouse-position-control'
		this.callback = (e) => {
			this.container.textContent = e.lngLat.lat.toFixed(5) + ' : ' + e.lngLat.lng.toFixed(5)
		}
		this.map.on('mousemove', this.callback)
		return this.container
	}

	onRemove() {
		this.container.parentNode.removeChild(this.container)
		this.map.off('mousemove', this.callback)
		this.map = undefined
	}

}

export {
	METERSTOFOOT,
	METERSTOMILES,
	METERSTONAUTICALMILES,
	minPerKmToPace,
	kmphToSpeed,
	kmphToSpeedNoUnit,
	brify,
	basename,
	hexToRgb,
	metersToDistance,
	metersToDistanceNoAdaptNoUnit,
	metersToElevation,
	metersToElevationNoUnit,
	formatDuration,
	escapeHtml,
	delay,
	keyDelay,
	MousePositionControl,
}
