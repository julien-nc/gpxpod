export default {
	methods: {
		onBorderMouseEnter(e) {
			this.bringToTop()
			this.map.getCanvas().style.cursor = 'pointer'
			console.debug('bbbbbbbbbbbbbb', e)
			// this.$emit('line-hover', e.lngLat)
		},
		onBorderMouseLeave(e) {
			this.map.getCanvas().style.cursor = ''
		},
		listenToBorderHover() {
			this.map.on('mouseenter', this.borderLayerId, this.onBorderMouseEnter)
			this.map.on('mouseleave', this.borderLayerId, this.onBorderMouseLeave)
		},
		releaseBorderHover() {
			this.map.off('mouseenter', this.borderLayerId, this.onBorderMouseEnter)
			this.map.off('mouseleave', this.borderLayerId, this.onBorderMouseLeave)
		},
	},
}
