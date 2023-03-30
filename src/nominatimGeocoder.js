import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

function formatNominatimToCarmentGeojson(results) {
	// https://docs.mapbox.com/api/search/geocoding/#geocoding-response-object
	return results.map(r => {
		const bb = r.boundingbox
		return {
			id: r.osm_id,
			place_name: r.display_name,
			bbox: [bb[2], bb[0], bb[3], bb[1]],
			// center: [r.lon, r.lat],
		}
	})
}

export async function nominatimGeocoder(query) {
	try {
		const req = {
			params: {
				query,
			},
		}
		const url = generateUrl('/apps/gpxpod/nominatim/search')
		const result = await axios.get(url, req)
		const data = result.data
		console.debug('gpxpod nominatim search result', data)
		return formatNominatimToCarmentGeojson(data)
	} catch (error) {
		console.error('Nominatim search error', error)
	}
}
