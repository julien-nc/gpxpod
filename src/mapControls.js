/**
 * SPDX-FileCopyrightText: 2015 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { createApp, h, reactive } from 'vue'
import { LngLat } from 'maplibre-gl'
import { MaplibreMeasureControl } from '@watergis/maplibre-gl-terradraw'
import '@watergis/maplibre-gl-terradraw/dist/maplibre-gl-terradraw.css'
import TileServerControl from './components/TileServerControl.vue'
import { measureState } from './mapUtils.js'

export class ImageControl {
	constructor(options) {
		this.options = options
	}

	onAdd(map) {
		this.map = map
		this.container = document.createElement('div')
		this.container.className = 'maplibregl-ctrl maptiler-logo-control'
		const a = document.createElement('a')
		a.setAttribute('href', this.options.linkTarget)
		a.setAttribute('target', '_blank')
		const img = document.createElement('img')
		img.setAttribute('src', this.options.imgUrl)
		img.setAttribute('alt', this.options.imgAlt ?? 'plop')
		a.appendChild(img)
		this.container.appendChild(a)
		return this.container
	}

	onRemove() {
		this.container.parentNode.removeChild(this.container)
		this.map = undefined
	}

	moveBefore(elem) {
		elem.parentElement.insertBefore(this.container, elem)
	}
}

export class MousePositionControl {
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

export class TileControl {
	constructor(options) {
		this.options = options
		console.debug('control options', options)
		this._events = {}
	}

	onAdd(map) {
		this.map = map
		this.container = document.createElement('div')
		this.container.className = 'maplibregl-ctrl my-custom-tile-control'

		const tileServerOptions = Object.keys(this.options.styles)
			.reduce((acc, val) => {
				acc[val] = {
					...this.options.styles[val],
					key: val,
				}
				return acc
			}, {})

		const props = reactive({
			options: Object.values(tileServerOptions),
			modelValue: tileServerOptions[this.options.selectedKey] ?? tileServerOptions.osmRaster,
		})

		const app = createApp({
			render: () => h(TileServerControl, props),
		})
		app.mixin({ methods: { t, n } })
		app.mount(this.container)

		this.container.addEventListener('update:model-value', (data) => {
			console.debug('TILE change', data.detail)
			props.modelValue = data.detail
			const styleKey = data.detail.key
			const style = this.options.styles[styleKey]
			if (style.uri) {
				this.map.setStyle(style.uri, { diff: false })
			} else {
				this.map.setStyle(style, { diff: false })
			}
			this.emit('changeStyle', styleKey)
		})

		return this.container
	}

	onRemove() {
		this.container.parentNode.removeChild(this.container)
		this.map = undefined
	}

	on(name, listener) {
		if (!this._events[name]) {
			this._events[name] = []
		}

		this._events[name].push(listener)
	}

	removeListener(name, listenerToRemove) {
		if (!this._events[name]) {
			throw new Error(`Can't remove a listener. Event "${name}" doesn't exits.`)
		}

		const filterListeners = (listener) => listener !== listenerToRemove

		this._events[name] = this._events[name].filter(filterListeners)
	}

	emit(name, data) {
		if (!this._events[name]) {
			throw new Error(`Can't emit an event. Event "${name}" doesn't exits.`)
		}

		const fireCallbacks = (callback) => {
			callback(data)
		}

		this._events[name].forEach(fireCallbacks)
	}
}

export class GlobeControl {
	constructor(options = {}) {
		this.options = options
		this._events = {}
	}

	onAdd(map) {
		this.map = map
		this.container = document.createElement('div')
		this.container.className = 'maplibregl-ctrl maplibregl-ctrl-group'
		this.globeButton = document.createElement('button')
		this.globeButton.className = 'maplibregl-ctrl-globe'
		const span = document.createElement('span')
		span.className = 'maplibregl-ctrl-icon'
		span.setAttribute('aria-hidden', 'true')
		this.globeButton.appendChild(span)
		this.globeButton.addEventListener('click', (e) => {
			this.emit('toggleGlobe')
		})
		this.container.appendChild(this.globeButton)

		return this.container
	}

	onRemove() {
		this.container.parentNode.removeChild(this.container)
		this.map = undefined
	}

	on(name, listener) {
		if (!this._events[name]) {
			this._events[name] = []
		}

		this._events[name].push(listener)
	}

	removeListener(name, listenerToRemove) {
		if (!this._events[name]) {
			throw new Error(`Can't remove a listener. Event "${name}" doesn't exits.`)
		}

		const filterListeners = (listener) => listener !== listenerToRemove

		this._events[name] = this._events[name].filter(filterListeners)
	}

	emit(name, data) {
		if (!this._events[name]) {
			throw new Error(`Can't emit an event. Event "${name}" doesn't exits.`)
		}

		const fireCallbacks = (callback) => {
			callback(data)
		}

		this._events[name].forEach(fireCallbacks)
	}

	updateGlobeIcon(enabled) {
		this.globeButton.classList.remove('maplibregl-ctrl-globe')
		this.globeButton.classList.remove('maplibregl-ctrl-globe-enabled')
		if (enabled) {
			this.globeButton.classList.add('maplibregl-ctrl-globe-enabled')
			this.globeButton.title = this.map._getUIString('GlobeControl.Disable')
		} else {
			this.globeButton.classList.add('maplibregl-ctrl-globe')
			this.globeButton.title = this.map._getUIString('GlobeControl.Enable')
		}
	}
}

export class TerrainControl {
	constructor(options = {}) {
		this.options = options
		this._events = {}
	}

	onAdd(map) {
		this.map = map
		this.container = document.createElement('div')
		this.container.className = 'maplibregl-ctrl maplibregl-ctrl-group'
		this.terrainButton = document.createElement('button')
		this.terrainButton.className = 'maplibregl-ctrl-terrain'
		const span = document.createElement('span')
		span.className = 'maplibregl-ctrl-icon'
		span.setAttribute('aria-hidden', 'true')
		this.terrainButton.appendChild(span)
		this.terrainButton.addEventListener('click', (e) => {
			this.emit('toggleTerrain')
		})
		this.container.appendChild(this.terrainButton)

		return this.container
	}

	onRemove() {
		this.container.parentNode.removeChild(this.container)
		this.map = undefined
	}

	on(name, listener) {
		if (!this._events[name]) {
			this._events[name] = []
		}

		this._events[name].push(listener)
	}

	removeListener(name, listenerToRemove) {
		if (!this._events[name]) {
			throw new Error(`Can't remove a listener. Event "${name}" doesn't exits.`)
		}

		const filterListeners = (listener) => listener !== listenerToRemove

		this._events[name] = this._events[name].filter(filterListeners)
	}

	emit(name, data) {
		if (!this._events[name]) {
			throw new Error(`Can't emit an event. Event "${name}" doesn't exits.`)
		}

		const fireCallbacks = (callback) => {
			callback(data)
		}

		this._events[name].forEach(fireCallbacks)
	}

	updateTerrainIcon(enabled) {
		this.terrainButton.classList.remove('maplibregl-ctrl-terrain')
		this.terrainButton.classList.remove('maplibregl-ctrl-terrain-enabled')
		if (enabled) {
			this.terrainButton.classList.add('maplibregl-ctrl-terrain-enabled')
			this.terrainButton.title = this.map._getUIString('TerrainControl.Disable')
		} else {
			this.terrainButton.classList.add('maplibregl-ctrl-terrain')
			this.terrainButton.title = this.map._getUIString('TerrainControl.Enable')
		}
	}
}

/**
 * Options for the Terra Draw measure control. The polygon label is
 * customized: on circles (whose label feature inherits the radiusKilometers
 * property set by the circle mode), it also displays the radius and
 * diameter; on rectangles (whose label feature inherits the
 * widthMeters/heightMeters properties set by MeasureControl), it displays
 * the width and height; otherwise it just displays the area.
 *
 * @param {string} unit user distance unit: 'metric', 'imperial' or 'nautical'
 * @return {object} MaplibreMeasureControl options
 */
function getMeasureControlOptions(unit) {
	// format a distance given in meters with an auto m/km unit
	const radiusMeters = ['*', ['get', 'radiusKilometers'], 1000]
	const formatMeters = (meters) => [
		'case',
		['>=', meters, 1000],
		['concat', ['to-string', ['/', ['round', ['*', ['/', meters, 1000], 100]], 100]], ' km'],
		['concat', ['to-string', ['round', meters]], ' m'],
	]
	const areaText = ['concat', ['to-string', ['get', 'area']], ' ', ['get', 'unit']]

	return {
		// 'render' is the expand/collapse toggle button, then
		// linestring/polygon/rectangle/circle to measure, delete to clear
		modes: ['render', 'linestring', 'polygon', 'rectangle', 'circle', 'delete'],
		open: false,
		// no nautical unit in Terra Draw, fall back to metric
		measureUnitType: unit === 'imperial' ? 'imperial' : 'metric',
		polygonLayerSpec: {
			id: '{prefix}-polygon-label',
			type: 'symbol',
			source: '{prefix}-polygon-source',
			filter: ['==', '$type', 'Point'],
			layout: {
				'text-field': [
					'case',
					['has', 'radiusKilometers'],
					[
						'concat',
						areaText,
						'\nR: ', formatMeters(radiusMeters),
						'\nD: ', formatMeters(['*', radiusMeters, 2]),
					],
					[
						'case',
						['has', 'widthMeters'],
						[
							'concat',
							areaText,
							'\nW: ', formatMeters(['get', 'widthMeters']),
							'\nH: ', formatMeters(['get', 'heightMeters']),
						],
						areaText,
					],
				],
				'symbol-placement': 'point',
				'text-size': ['interpolate', ['linear'], ['zoom'], 5, 10, 10, 12, 13, 14, 14, 16, 18, 18],
				'text-overlap': 'always',
				'text-letter-spacing': 0.05,
			},
			paint: {
				'text-halo-color': '#F7F7F7',
				'text-halo-width': 2,
				'text-color': '#232E3D',
			},
		},
	}
}

export class MeasureControl extends MaplibreMeasureControl {
	constructor({ unit } = {}) {
		super(getMeasureControlOptions(unit))
		// control-level listeners are registered once here and not in onAdd:
		// the plugin's on() does not deduplicate them
		this.on('mode-changed', (e) => {
			// 'render' is the resting mode: no tool is being used
			measureState.active = e.mode !== 'render'
		})
		// collapsing the toolbar does not fire 'mode-changed': it resets the
		// active tool to the resting mode, so popups can be shown again
		this.on('collapsed', () => {
			measureState.active = false
		})
	}

	onAdd(map) {
		const container = super.onAdd(map)
		// the rectangle mode does not expose its dimensions: compute and store
		// them ourselves so the polygon label can display them. A fresh Terra
		// Draw instance is created on every onAdd, so re-registering here is
		// safe
		this.getTerraDrawInstance()?.on('change', (ids, type) => {
			if (type === 'styling' || !Array.isArray(ids)) {
				return
			}
			for (const id of ids) {
				this.updateRectangleDimensions(id)
			}
		})
		return container
	}

	/**
	 * Called when the unit setting changes
	 *
	 * @param {string} unit 'metric', 'imperial' or 'nautical'
	 */
	setUnit(unit) {
		// no nautical unit in Terra Draw, fall back to metric
		this.measureUnitType = unit === 'imperial' ? 'imperial' : 'metric'
	}

	updateRectangleDimensions(featureId) {
		const terradraw = this.getTerraDrawInstance()
		if (!terradraw) {
			return
		}
		const feature = terradraw.getSnapshotFeature(featureId)
		if (!feature
			|| feature.geometry.type !== 'Polygon'
			|| feature.properties.mode !== 'rectangle') {
			return
		}
		const coords = feature.geometry.coordinates[0]
		// width: first edge (constant latitude), height: second edge
		// (constant longitude)
		const width = new LngLat(coords[0][0], coords[0][1]).distanceTo(new LngLat(coords[1][0], coords[1][1]))
		const height = new LngLat(coords[1][0], coords[1][1]).distanceTo(new LngLat(coords[2][0], coords[2][1]))
		// avoid re-triggering the change listener when nothing changed
		if (feature.properties.widthMeters === width && feature.properties.heightMeters === height) {
			return
		}
		terradraw.updateFeatureProperties(featureId, {
			widthMeters: width,
			heightMeters: height,
		})
	}

	/**
	 * The Terra Draw sources/layers are wiped when the map style changes:
	 * recreate the control so it stays usable (measured features are lost).
	 *
	 * @param {Map} map the maplibre map
	 * @param {string} position control position, as given to addControl
	 */
	reset(map, position) {
		measureState.active = false
		try {
			map.removeControl(this)
		} catch (e) {
			// the Terra Draw adapter cleanup throws when the style change
			// already wiped its sources/layers: detach the leftover container
			this.controlContainer?.remove()
		}
		map.addControl(this, position)
	}
}
