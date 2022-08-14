import { generateUrl } from '@nextcloud/router'

export function getRasterTileServers(apiKey) {
	return {
		osmRaster: {
			title: 'OpenStreetMap raster',
			version: 8,
			// required to display text, apparently vector styles get this but not raster ones
			glyphs: 'https://api.maptiler.com/fonts/{fontstack}/{range}.pbf?key=' + apiKey,
			sources: {
				'osm-source': {
					type: 'raster',
					tiles: [
						generateUrl('/apps/gpxpod/tiles/osm/') + '{x}/{y}/{z}',
					],
					tileSize: 256,
					attribution: 'Map data &copy; 2013 <a href="https://openstreetmap.org">OpenStreetMap</a> contributors',
				},
			},
			layers: [
				{
					id: 'osm-layer',
					type: 'raster',
					source: 'osm-source',
					minzoom: 0,
					maxzoom: 19,
				},
			],
			maxzoom: 19,
		},
		esriTopo: {
			title: 'ESRI topo with relief',
			version: 8,
			glyphs: 'https://api.maptiler.com/fonts/{fontstack}/{range}.pbf?key=' + apiKey,
			sources: {
				'esri-topo-source': {
					type: 'raster',
					tiles: [
						generateUrl('/apps/gpxpod/tiles/esri-topo/') + '{x}/{y}/{z}',
					],
					tileSize: 256,
					attribution: 'Tiles &copy; Esri &mdash; Esri, DeLorme, NAVTEQ, '
						+ 'TomTom, Intermap, iPC, USGS, FAO, NPS, NRCAN, GeoBase, Kadaster NL, Ord'
						+ 'nance Survey, Esri Japan, METI, Esri China (Hong Kong), and the GIS User'
						+ ' Community',
				},
			},
			layers: [
				{
					id: 'esri-topo-layer',
					type: 'raster',
					source: 'esri-topo-source',
					minzoom: 0,
					maxzoom: 19,
				},
			],
			maxzoom: 19,
		},
		waterColor: {
			title: 'WaterColor',
			version: 8,
			glyphs: 'https://api.maptiler.com/fonts/{fontstack}/{range}.pbf?key=' + apiKey,
			sources: {
				'watercolor-source': {
					type: 'raster',
					tiles: [
						generateUrl('/apps/gpxpod/tiles/watercolor/') + '{x}/{y}/{z}',
					],
					tileSize: 256,
					attribution: '<a href="https://leafletjs.com" title="A JS library'
						+ ' for interactive maps">Leaflet</a> | © Map tiles by <a href="https://stamen'
						+ '.com">Stamen Design</a>, under <a href="https://creativecommons.org/license'
						+ 's/by/3.0">CC BY 3.0</a>, Data by <a href="https://openstreetmap.org">OpenSt'
						+ 'reetMap</a>, under <a href="https://creativecommons.org/licenses/by-sa/3.0"'
						+ '>CC BY SA</a>.',
				},
			},
			layers: [
				{
					id: 'watercolor-layer',
					type: 'raster',
					source: 'watercolor-source',
					minzoom: 0,
					maxzoom: 18,
				},
			],
			maxzoom: 18,
		},
	}
}

export function getVectorStyles(apiKey) {
	return {
		streets: {
			title: 'Streets',
			uri: 'https://api.maptiler.com/maps/streets/style.json?key=' + apiKey,
		},
		satellite: {
			title: 'Satellite',
			uri: 'https://api.maptiler.com/maps/hybrid/style.json?key=' + apiKey,
		},
		outdoor: {
			title: 'Outdoor',
			uri: 'https://api.maptiler.com/maps/outdoor/style.json?key=' + apiKey,
		},
		osm: {
			title: 'OpenStreetMap',
			uri: 'https://api.maptiler.com/maps/openstreetmap/style.json?key=' + apiKey,
		},
		dark: {
			title: 'Dark',
			uri: 'https://api.maptiler.com/maps/streets-dark/style.json?key=' + apiKey,
		},
	}
}

export class MyTileControl {

	constructor(options) {
		this.options = options
		console.debug('control options', options)
		this._events = {}
	}

	onAdd(map) {
		this.map = map
		this.container = document.createElement('div')
		this.container.className = 'maplibregl-ctrl my-custom-tile-control'
		const select = document.createElement('select')
		Object.keys(this.options.styles).forEach((k) => {
			const style = this.options.styles[k]
			const option = document.createElement('option')
			option.textContent = style.title
			option.setAttribute('value', k)
			select.appendChild(option)
		})
		select.value = this.options.selectedKey
		select.addEventListener('change', (e) => {
			const styleKey = e.target.value
			const style = this.options.styles[styleKey]
			if (style.uri) {
				this.map.setStyle(style.uri)
			} else {
				this.map.setStyle(style)
			}
			this.emit('changeStyle', styleKey)
		})
		this.container.appendChild(select)
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
