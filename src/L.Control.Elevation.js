import moment from 'moment-timezone'

const METERSTOMILES = 0.0006213711
const METERSTONAUTICALMILES = 0.000539957

function pad(num, size) {
	let s = num + ''
	while (s.length < size) s = '0' + s
	return s
}

L.Control.Elevation = L.Control.extend({
	options: {
		position: 'topright',
		theme: 'lime-theme',
		width: 600,
		height: 175,
		margins: {
			top: 10,
			right: 20,
			bottom: 30,
			left: 60,
		},
		useHeightIndicator: true,
		interpolation: 'linear',
		hoverNumber: {
			decimalsX: 3,
			decimalsY: 0,
			formatter: undefined,
		},
		xTicks: undefined,
		yTicks: undefined,
		yUnit: 'm',
		xUnit: 'km',
		title: '',
		timezone: '',
		collapsed: false,
		yAxisMin: undefined,
		yAxisMax: undefined,
		forceAxisBounds: false,
		controlButton: {
			iconCssClass: 'elevation-toggle-icon',
			title: 'Elevation',
		},
	},

	onRemove(map) {
		this._container = null
	},

	onAdd(map) {
		this._map = map

		const opts = this.options
		const margin = opts.margins
		opts.xTicks = opts.xTicks || Math.round(this._width() / 75)
		opts.yTicks = opts.yTicks || Math.round(this._height() / 30)
		opts.hoverNumber.formatter = opts.hoverNumber.formatter || this._formatter

		// append theme name on body
		d3.select('body').classed(opts.theme, true)

		const x = this._x = d3.scale.linear()
			.range([0, this._width()])

		const y = this._y = d3.scale.linear()
			.range([this._height(), 0])

		const area = this._area = d3.svg.area()
			.interpolate(opts.interpolation)
			.x(function(d) {
				const xDiagCoord = x(d.dist)
				d.xDiagCoord = xDiagCoord
				return xDiagCoord
			})
			.y0(this._height())
			.y1(function(d) {
				return y(d.altitude)
			})

		const container = this._container = L.DomUtil.create('div', 'elevation')

		this._initToggle()

		const cont = d3.select(container)
		cont.attr('width', opts.width)
		const svg = cont.append('svg')
		svg.attr('width', opts.width)
			.attr('class', 'background')
			.attr('height', opts.height)
			.append('g')
			.attr('transform', 'translate(' + margin.left + ',' + margin.top + ')')

		let line = d3.svg.line()
		line = line
			.x(function(d) {
				return d3.mouse(svg.select('g'))[0]
			})
			.y(function(d) {
				return this._height()
			})

		const g = d3.select(this._container).select('svg').select('g')

		this._areapath = g.append('path')
			.attr('class', 'area')

		const label = g.append('text')
			.attr('x', 0)
			.attr('y', opts.height - 14)
			.style('text-anchor', 'begin')
			.style('font-weight', 'bold')
			.text(opts.title)

		const background = this._background = g.append('rect')
			.attr('width', this._width())
			.attr('height', this._height())
			.style('fill', 'none')
			.style('stroke', 'none')
			.style('pointer-events', 'all')

		if (L.Browser.chrome) {
			background.on('touchmove.drag', this._dragHandler.bind(this))
				.on('touchstart.drag', this._dragStartHandler.bind(this))
				.on('touchstart.focus', this._mousemoveHandler.bind(this))
			L.DomEvent.on(this._container, 'touchend', this._dragEndHandler, this)

			background.on('mousemove.focus', this._mousemoveHandler.bind(this))
				.on('mouseout.focus', this._mouseoutHandler.bind(this))
				.on('mousedown.drag', this._dragStartHandler.bind(this))
				.on('mousemove.drag', this._dragHandler.bind(this))
			L.DomEvent.on(this._container, 'mouseup', this._dragEndHandler, this)

		} else if (L.Browser.mobile) {

			background.on('touchmove.drag', this._dragHandler.bind(this))
				.on('touchstart.drag', this._dragStartHandler.bind(this))
				.on('touchstart.focus', this._mousemoveHandler.bind(this))
			L.DomEvent.on(this._container, 'touchend', this._dragEndHandler, this)

		} else {

			background.on('mousemove.focus', this._mousemoveHandler.bind(this))
				.on('mouseout.focus', this._mouseoutHandler.bind(this))
				.on('mousedown.drag', this._dragStartHandler.bind(this))
				.on('mousemove.drag', this._dragHandler.bind(this))
			L.DomEvent.on(this._container, 'mouseup', this._dragEndHandler, this)

		}

		this._xaxisgraphicnode = g.append('g')
		this._yaxisgraphicnode = g.append('g')
		this._appendXaxis(this._xaxisgraphicnode)
		this._appendYaxis(this._yaxisgraphicnode)

		const focusG = this._focusG = g.append('g')
		this._mousefocus = focusG.append('svg:line')
			.attr('class', 'mouse-focus-line')
			.attr('x2', '0')
			.attr('y2', '0')
			.attr('x1', '0')
			.attr('y1', '0')
		this._focuslabelX = focusG.append('svg:text')
			.style('pointer-events', 'none')
			.attr('class', 'mouse-focus-label-x')
		this._focuslabelY = focusG.append('svg:text')
			.style('pointer-events', 'none')
			.attr('class', 'mouse-focus-label-y')
		this._focuslabelZ = focusG.append('svg:text')
			.style('pointer-events', 'none')
			.attr('class', 'mouse-focus-label-y')

		if (this._data) {
			this._applyData()
		}

		return container
	},

	_dragHandler() {

		// we don´t want map events to occur here
		d3.event.preventDefault()
		d3.event.stopPropagation()

		this._gotDragged = true

		this._drawDragRectangle()

	},

	/*
     * Draws the currently dragged rectabgle over the chart.
     */
	_drawDragRectangle() {

		if (!this._dragStartCoords) {
			return
		}

		const dragEndCoords = this._dragCurrentCoords = d3.mouse(this._background.node())

		const x1 = Math.min(this._dragStartCoords[0], dragEndCoords[0])
		const x2 = Math.max(this._dragStartCoords[0], dragEndCoords[0])

		if (!this._dragRectangle && !this._dragRectangleG) {
			const g = d3.select(this._container).select('svg').select('g')

			this._dragRectangleG = g.append('g')

			this._dragRectangle = this._dragRectangleG.append('rect')
				.attr('width', x2 - x1)
				.attr('height', this._height())
				.attr('x', x1)
				.attr('class', 'mouse-drag')
				.style('pointer-events', 'none')
		} else {
			this._dragRectangle.attr('width', x2 - x1)
				.attr('x', x1)
		}

	},

	/*
     * Removes the drag rectangle and zoms back to the total extent of the data.
     */
	_resetDrag() {

		if (this._dragRectangleG) {

			this._dragRectangleG.remove()
			this._dragRectangleG = null
			this._dragRectangle = null

			this._hidePositionMarker()

			this._map.fitBounds(this._fullExtent)

		}

	},

	/*
     * Handles end of dragg operations. Zooms the map to the selected items extent.
     */
	_dragEndHandler() {

		if (!this._dragStartCoords || !this._gotDragged) {
			this._dragStartCoords = null
			this._gotDragged = false
			this._resetDrag()
			return
		}

		this._hidePositionMarker()

		const item1 = this._findItemForX(this._dragStartCoords[0])
		const item2 = this._findItemForX(this._dragCurrentCoords[0])

		this._fitSection(item1, item2)

		this._dragStartCoords = null
		this._gotDragged = false

	},

	_dragStartHandler() {

		d3.event.preventDefault()
		d3.event.stopPropagation()

		this._gotDragged = false

		this._dragStartCoords = d3.mouse(this._background.node())

	},

	/*
     * Finds a data entry for a given x-coordinate of the diagram
     */
	_findItemForX(x) {
		const bisect = d3.bisector(function(d) {
			return d.dist
		}).left
		const xinvert = this._x.invert(x)
		return bisect(this._data, xinvert)
	},

	/*
     * Finds an item with the smallest delta in distance to the given latlng coords
     */
	_findItemForLatLng(latlng) {
		let result = null
		let d = Infinity
		this._data.forEach(function(item) {
			const dist = latlng.distanceTo(item.latlng)
			if (dist < d) {
				d = dist
				result = item
			}
		})
		return result
	},

	/** Make the map fit the route section between given indexes. */
	_fitSection(index1, index2) {

		const start = Math.min(index1, index2)
		const end = Math.max(index1, index2)

		const ext = this._calculateFullExtent(this._data.slice(start, end))

		this._map.fitBounds(ext)

	},

	_initToggle() {

		/* inspired by L.Control.Layers */

		const container = this._container

		// Makes this work on IE10 Touch devices by stopping it from firing a mouseout event when the touch is released
		container.setAttribute('aria-haspopup', true)

		if (!L.Browser.mobile) {
			L.DomEvent
				.disableClickPropagation(container)
			// .disableScrollPropagation(container);
		} else {
			L.DomEvent.on(container, 'click', L.DomEvent.stopPropagation)
		}

		if (this.options.collapsed) {
			this._collapse()

			if (!L.Browser.android) {
				L.DomEvent
					.on(container, 'mouseover', this._expand, this)
					.on(container, 'mouseout', this._collapse, this)
			}
			const link = this._button = L.DomUtil.create('a', 'elevation-toggle ' + this.options.controlButton
				.iconCssClass, container)
			link.href = '#'
			link.title = this.options.controlButton.title

			if (L.Browser.mobile) {
				L.DomEvent
					.on(link, 'click', L.DomEvent.stop)
					.on(link, 'click', this._expand, this)
			} else {
				L.DomEvent.on(link, 'focus', this._expand, this)
			}

			this._map.on('click', this._collapse, this)
			// TODO keyboard accessibility
		}
	},

	_expand() {
		this._container.className = this._container.className.replace(' elevation-collapsed', '')
	},

	_collapse() {
		L.DomUtil.addClass(this._container, 'elevation-collapsed')
	},

	_width() {
		const opts = this.options
		return opts.width - opts.margins.left - opts.margins.right
	},

	_height() {
		const opts = this.options
		return opts.height - opts.margins.top - opts.margins.bottom
	},

	/*
     * Fromatting funciton using the given decimals and seperator
     */
	_formatter(num, dec, sep) {
		let res
		if (dec === 0) {
			res = Math.round(num) + ''
		} else {
			res = L.Util.formatNum(num, dec) + ''
		}
		const numbers = res.split('.')
		if (numbers[1]) {
			let d = dec - numbers[1].length
			for (; d > 0; d--) {
				numbers[1] += '0'
			}
			res = numbers.join(sep || '.')
		}
		return res
	},

	_appendYaxis(y) {
		y.attr('class', 'y axis')
			.call(d3.svg.axis()
				.scale(this._y)
				.ticks(this.options.yTicks)
				.orient('left'))
			.append('text')
			.attr('x', -10)
			.attr('y', 0)
			.style('text-anchor', 'end')
			.style('font-weight', 'bold')
			.text(this.options.yUnit)
	},

	_appendXaxis(x) {
		x.attr('class', 'x axis')
			.attr('transform', 'translate(0,' + this._height() + ')')
			.call(d3.svg.axis()
				.scale(this._x)
				.ticks(this.options.xTicks)
				.orient('bottom'))
			.append('text')
			.attr('x', this._width() + 20)
			.attr('y', 15)
			.style('text-anchor', 'end')
			.style('font-weight', 'bold')
			.text(this.options.xUnit)
	},

	_updateAxis() {
		this._xaxisgraphicnode.selectAll('g').remove()
		this._xaxisgraphicnode.selectAll('path').remove()
		this._xaxisgraphicnode.selectAll('text').remove()
		this._yaxisgraphicnode.selectAll('g').remove()
		this._yaxisgraphicnode.selectAll('path').remove()
		this._yaxisgraphicnode.selectAll('text').remove()
		this._appendXaxis(this._xaxisgraphicnode)
		this._appendYaxis(this._yaxisgraphicnode)
	},

	_mouseoutHandler() {

		this._hidePositionMarker()
		if (this.options.showTime) {
			$('#' + this.options.showTime).text('')
		}

	},

	/*
     * Hides the position-/heigth indication marker drawn onto the map
     */
	_hidePositionMarker() {

		if (this._marker) {
			this._map.removeLayer(this._marker)
			this._marker = null
		}
		if (this._mouseHeightFocus) {
			this._mouseHeightFocus.style('visibility', 'hidden')
			this._mouseHeightFocusLabel.style('visibility', 'hidden')
		}
		if (this._pointG) {
			this._pointG.style('visibility', 'hidden')
		}
		this._focusG.style('visibility', 'hidden')

	},

	/*
     * Handles the moueseover the chart and displays distance and altitude level
     */
	_mousemoveHandler(d, i, ctx) {
		if (!this._data || this._data.length === 0) {
			return
		}
		const coords = d3.mouse(this._background.node())
		const opts = this.options

		const item = this._data[this._findItemForX(coords[0])]
		const alt = item.altitude
		const dist = item.dist
		const ll = item.latlng
		const numY = opts.hoverNumber.formatter(alt, opts.hoverNumber.decimalsY)
		const numX = opts.hoverNumber.formatter(dist, opts.hoverNumber.decimalsX)

		this._showDiagramIndicator(item, coords[0])

		const layerpoint = this._map.latLngToLayerPoint(ll)

		// if we use a height indicator we create one with SVG
		// otherwise we show a marker
		if (opts.useHeightIndicator) {

			if (!this._mouseHeightFocus) {

				const heightG = d3.select('.leaflet-overlay-pane svg')
					.append('g')
				this._mouseHeightFocus = heightG.append('svg:line')
					.attr('class', 'height-focus line')
					.attr('x2', '0')
					.attr('y2', '0')
					.attr('x1', '0')
					.attr('y1', '0')

				const pointG = this._pointG = heightG.append('g')
				pointG.append('svg:circle')
					.attr('r', 6)
					.attr('cx', 0)
					.attr('cy', 0)
					.attr('class', 'height-focus circle-lower')

				this._mouseHeightFocusLabel = heightG.append('svg:text')
					.attr('class', 'height-focus-label')
					.style('pointer-events', 'none')

			}

			const normalizedAlt = this._height() / this._maxElevation * alt
			const normalizedY = layerpoint.y - normalizedAlt
			this._mouseHeightFocus.attr('x1', layerpoint.x)
				.attr('x2', layerpoint.x)
				.attr('y1', layerpoint.y)
				.attr('y2', normalizedY)
				.style('visibility', 'visible')

			this._pointG.attr('transform', 'translate(' + layerpoint.x + ',' + layerpoint.y + ')')
				.style('visibility', 'visible')

			this._mouseHeightFocusLabel.attr('x', layerpoint.x)
				.attr('y', normalizedY)
				.text(numY + ' ' + this.options.yUnit)
				.style('visibility', 'visible')

		} else {

			if (!this._marker) {

				this._marker = new L.Marker(ll).addTo(this._map)

			} else {

				this._marker.setLatLng(ll)

			}

		}

	},

	/*
     * Parsing of GeoJSON data lines and their elevation in z-coordinate
     */
	_addGeoJSONData(coords) {
		if (coords) {
			const data = this._data || []
			let dist = this._dist || 0
			let ele = this._maxElevation || 0
			for (let i = 0; i < coords.length; i++) {
				const s = new L.LatLng(coords[i][1], coords[i][0])
				const e = new L.LatLng(coords[i ? i - 1 : 0][1], coords[i ? i - 1 : 0][0])
				const time = coords[i][3]
				let newdist = s.distanceTo(e)
				if (this.options.xUnit === 'mi') {
					newdist = newdist * METERSTOMILES * 1000
				} else if (this.options.xUnit === 'nmi') {
					newdist = newdist * METERSTONAUTICALMILES * 1000
				}
				dist = dist + Math.round(newdist / 1000 * 100000) / 100000
				ele = ele < coords[i][2] ? coords[i][2] : ele
				data.push({
					dist,
					altitude: coords[i][2],
					x: coords[i][0],
					y: coords[i][1],
					latlng: s,
					time,
				})
			}
			this._dist = dist
			this._data = data
			this._maxElevation = ele
		}
	},

	/*
     * Parsing function for GPX data as used by https://github.com/mpetazzoni/leaflet-gpx
     */
	_addGPXdata(coords) {
		if (coords) {
			const data = this._data || []
			let dist = this._dist || 0
			let ele = this._maxElevation || 0
			for (let i = 0; i < coords.length; i++) {
				const s = coords[i]
				const e = coords[i ? i - 1 : 0]
				const newdist = s.distanceTo(e)
				dist = dist + Math.round(newdist / 1000 * 100000) / 100000
				ele = ele < s.meta.ele ? s.meta.ele : ele
				data.push({
					dist,
					altitude: s.meta.ele,
					x: s.lng,
					y: s.lat,
					latlng: s,
				})
			}
			this._dist = dist
			this._data = data
			this._maxElevation = ele
		}
	},

	_addData(d) {
		const geom = d && d.geometry && d.geometry
		let i

		if (geom) {
			switch (geom.type) {
			case 'LineString':
				this._addGeoJSONData(geom.coordinates)
				break

			case 'MultiLineString':
				for (i = 0; i < geom.coordinates.length; i++) {
					this._addGeoJSONData(geom.coordinates[i])
				}
				break

			default:
				throw new Error('Invalid GeoJSON object.')
			}
		}

		const feat = d && d.type === 'FeatureCollection'
		if (feat) {
			for (i = 0; i < d.features.length; i++) {
				this._addData(d.features[i])
			}
		}

		if (d && d._latlngs) {
			this._addGPXdata(d._latlngs)
		}
	},

	/*
     * Calculates the full extent of the data array
     */
	_calculateFullExtent(data) {

		if (!data || data.length < 1) {
			throw new Error('no data in parameters')
		}

		const ext = new L.latLngBounds(data[0].latlng, data[0].latlng)

		data.forEach(function(item) {
			ext.extend(item.latlng)
		})

		return ext

	},

	/*
     * Add data to the diagram either from GPX or GeoJSON and
     * update the axis domain and data
     */
	addData(d, layer) {
		this._addData(d)
		if (this._container) {
			this._applyData()
		}
		if (layer === null && d.on) {
			layer = d
		}
		if (layer) {
			layer.on('mousemove', this._handleLayerMouseOver.bind(this))
		}
	},

	/*
     * Handles mouseover events of the data layers on the map.
     */
	_handleLayerMouseOver(evt) {
		if (!this._data || this._data.length === 0) {
			return
		}
		const latlng = evt.latlng
		const item = this._findItemForLatLng(latlng)
		if (item) {
			const x = item.xDiagCoord
			this._showDiagramIndicator(item, x)
		}
	},

	_showDiagramIndicator(item, xCoordinate) {
		const opts = this.options
		this._focusG.style('visibility', 'visible')
		this._mousefocus.attr('x1', xCoordinate)
			.attr('y1', 0)
			.attr('x2', xCoordinate)
			.attr('y2', this._height())
			.classed('hidden', false)

		const alt = item.altitude
		const dist = item.dist
		const ll = item.latlng
		const numY = opts.hoverNumber.formatter(alt, opts.hoverNumber.decimalsY)
		const numX = opts.hoverNumber.formatter(dist, opts.hoverNumber.decimalsX)
		const time = item.time || ''
		if (time) {
			const d = moment(time.replace(' ', 'T'))
			d.tz(opts.timezone)
			const ds = d.format('YYYY-MM-DD HH:mm:ss (Z)')

			if (opts.showTime) {
				$('#' + opts.showTime).text(ds)
			}

			// this._focuslabelZ.attr("y", this._height() - 20)
			//    .attr("x", xCoordinate)
			//    .text(ds);
		}

		this._focuslabelX.attr('x', xCoordinate)
			.text(numY + ' ' + this.options.yUnit)
		this._focuslabelY.attr('y', this._height() - 5)
			.attr('x', xCoordinate)
			.text(numX + ' ' + this.options.xUnit)
	},

	_applyData() {
		const xdomain = d3.extent(this._data, function(d) {
			return d.dist
		})
		const ydomain = d3.extent(this._data, function(d) {
			return d.altitude
		})
		const opts = this.options

		if (opts.yAxisMin !== undefined && (opts.yAxisMin < ydomain[0] || opts.forceAxisBounds)) {
			ydomain[0] = opts.yAxisMin
		}
		if (opts.yAxisMax !== undefined && (opts.yAxisMax > ydomain[1] || opts.forceAxisBounds)) {
			ydomain[1] = opts.yAxisMax
		}

		this._x.domain(xdomain)
		this._y.domain(ydomain)
		this._areapath.datum(this._data)
			.attr('d', this._area)
		this._updateAxis()

		this._fullExtent = this._calculateFullExtent(this._data)
	},

	/*
     * Reset data
     */
	_clearData() {
		this._data = null
		this._dist = null
		this._maxElevation = null
	},

	/*
     * Reset data and display
     */
	clear() {

		this._clearData()

		if (!this._areapath) {
			return
		}

		// workaround for 'Error: Problem parsing d=""' in Webkit when empty data
		// https://groups.google.com/d/msg/d3-js/7rFxpXKXFhI/HzIO_NPeDuMJ
		// this._areapath.datum(this._data).attr("d", this._area);
		this._areapath.attr('d', 'M0 0')

		this._x.domain([0, 1])
		this._y.domain([0, 1])
		this._updateAxis()
	},

})

L.control.elevation = function(options) {
	return new L.Control.Elevation(options)
}
