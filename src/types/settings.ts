export interface TileServer {
	id: number
	user_id: string | null
	type: number
	name: string
	url: string
	min_zoom: number | null
	max_zoom: number | null
	attribution: string | null
}

export interface GpxpodSettings {
	nav_tracks_filter_map_bounds: string
	nav_show_hovered_dir_bounds: string
	global_track_colorization: string
	show_marker_cluster: string
	show_picture_cluster: string
	show_mouse_position_control: string
	compact_mode: string
	line_border: string
	direction_arrows: string
	arrows_scale_factor: string | number
	arrows_spacing: string | number
	line_width: string | number
	line_opacity: string | number
	distance_unit: 'metric' | 'imperial' | 'nautical'
	terrainExaggeration: string | number
	fontScale: string | number
	maptiler_api_key: string
	extra_tile_servers: TileServer[]
	app_version: string
}

export interface DistanceUnitOption {
	label: string
	value: string
}

export interface DistanceUnitOptions {
	metric: DistanceUnitOption
	imperial: DistanceUnitOption
	nautical: DistanceUnitOption
}
