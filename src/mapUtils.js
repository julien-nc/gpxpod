import { LngLat } from 'maplibre-gl'

export const mapImages = {
	Bar: 'mapIcons/symbols/bar.png',
	'Bike Trail': 'mapIcons/symbols/bike-trail.png',
	'Block, Blue': 'mapIcons/symbols/block-blue.png',
	'Block, Green': 'mapIcons/symbols/block-green.png',
	'Block, Red': 'mapIcons/symbols/block-red.png',
	Campground: 'mapIcons/symbols/campground.png',
	'Contact, Alien': 'mapIcons/symbols/contact-alien.png',
	'Contact, Big Ears': 'mapIcons/symbols/contact-bigears.png',
	'Contact, Cat': 'mapIcons/symbols/contact-cat.png',
	'Contact, Dog': 'mapIcons/symbols/contact-dog.png',
	'Contact, Female3': 'mapIcons/symbols/contact-female3.png',
	'Blue Diamond': 'mapIcons/symbols/diamond-blue.png',
	'Green Diamond': 'mapIcons/symbols/diamond-green.png',
	'Red Diamond': 'mapIcons/symbols/diamond-red.png',
	'Dot, White': 'mapIcons/symbols/dot.png',
	'Drinking Water': 'mapIcons/symbols/drinking-water.png',
	'Flag, Blue': 'mapIcons/symbols/flag-blue.png',
	'Flag, Green': 'mapIcons/symbols/flag-green.png',
	'Flag, Red': 'mapIcons/symbols/flag-red.png',
	'Geocache Found': 'mapIcons/symbols/geocache-open.png',
	Geocache: 'mapIcons/symbols/geocache.png',
	'Trail Head': 'mapIcons/symbols/hike.png',
	'Medical Facility': 'mapIcons/symbols/medical.png',
	Residence: 'mapIcons/symbols/residence.png',
	'Skull and Crossbones': 'mapIcons/symbols/skullcross.png',
	arrow: 'mapIcons/symbols/arrow-small.png',
}
export const mapVectorImages = {
	marker: 'mapIcons/marker.svg',
	'Pin, Blue': 'mapIcons/pin-blue.svg',
	'Pin, Red': 'mapIcons/pin-red.svg',
	'Pin, Green': 'mapIcons/pin-green.svg',
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
