export default {
	watch: {
		ready(newVal) {
			if (newVal) {
				this.listenBringToTop()
			}
		},
	},

	destroyed() {
		this.releaseBringToTop()
	},

	methods: {
		listenBringToTop() {
			this.map.on('mouseenter', this.borderLayerId, this.bringToTop)
		},
		releaseBringToTop() {
			this.map.off('mouseenter', this.borderLayerId, this.bringToTop)
		},
	},
}
