import { generateUrl } from '@nextcloud/router'

export function getRasterTileServers(apiKey) {
	return {
		osm: {
			title: 'blob',
			version: 8,
			// required to display text, apparently vector styles get this but not raster ones
			glyphs: 'https://api.maptiler.com/fonts/{fontstack}/{range}.pbf?key=' + apiKey,
			sources: {
				'raster-tiles': {
					type: 'raster',
					tiles: [
						generateUrl('/apps/gpxpod/osm/') + '{x}/{y}/{z}',
					],
					tileSize: 256,
					attribution:
						'Map tiles by <a target="_top" rel="noopener" href="http://stamen.com">Stamen Design</a>, under <a target="_top" rel="noopener" href="http://creativecommons.org/licenses/by/3.0">CC BY 3.0</a>. Data by <a target="_top" rel="noopener" href="http://openstreetmap.org">OpenStreetMap</a>, under <a target="_top" rel="noopener" href="http://creativecommons.org/licenses/by-sa/3.0">CC BY SA</a>',
				},
			},
			layers: [
				{
					id: 'simple-tiles',
					type: 'raster',
					source: 'raster-tiles',
					minzoom: 0,
					maxzoom: 19,
				},
			],
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
