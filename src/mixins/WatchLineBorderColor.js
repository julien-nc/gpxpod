export default {
	watch: {
		borderColor(newVal) {
			if (this.map.getLayer(this.stringId + 'b')) {
				this.map.setPaintProperty(this.stringId + 'b', 'line-color', newVal)
			}
		},
	},
}
