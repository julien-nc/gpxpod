import { LngLat } from 'maplibre-gl'

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
