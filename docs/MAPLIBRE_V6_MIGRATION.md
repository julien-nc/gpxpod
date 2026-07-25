# MapLibre GL JS v5 to v6 Migration Guide

This document describes the changes made to migrate gpxpod from maplibre-gl v5 to v6.

## Summary of Changes

### 1. Import Statement Changes (ESM Only)

**Issue:** MapLibre GL JS v6 ships as ES modules only. The UMD bundle and default export are gone. If you used the default import pattern in v5, it will no longer work in v6.

**Fix:** Change from default import to named imports or namespace import:

```javascript
// Before (v5) - NO LONGER WORKS
import maplibregl from 'maplibre-gl'
const map = new maplibregl.Map({ ... })

// After (v6) - Option 1: Named imports (recommended)
import { Map, Popup, Marker, NavigationControl } from 'maplibre-gl'
const map = new Map({ ... })

// After (v6) - Option 2: Namespace import
import * as maplibregl from 'maplibre-gl'
const map = new maplibregl.Map({ ... })
```

**Note:** This codebase uses the namespace import pattern (`import * as maplibregl`) because the global `maplibregl` object needs to be passed to `MaplibreGeocoder`:

```javascript
import * as maplibregl from 'maplibre-gl'
import MaplibreGeocoder from '@maplibre/maplibre-gl-geocoder'

new MaplibreGeocoder({ forwardGeocode: maplibreForwardGeocode }, {
  maplibregl,
  // ... other options
})
```

### 2. Worker URL Configuration (Critical)

**Issue:** In v6, maplibre-gl ships as ES modules only. For bundler setups (Vite, webpack, etc.), the worker URL must be explicitly configured using `setWorkerUrl()`. Without this, the web worker cannot load, causing:
- Vector tiles not rendering (no base map)
- GeoJSON sources not processing (tracks invisible)
- Cluster processing failures

**Fix:** Added worker URL configuration in `src/components/map/MaplibreMap.vue`:

```javascript
import { Map, setWorkerUrl } from 'maplibre-gl'
import workerUrl from 'maplibre-gl/dist/maplibre-gl-worker.mjs?worker&url'

setWorkerUrl(workerUrl)
```

**Important:** Use `?worker&url` (not just `?url`) because the worker imports its sibling `maplibre-gl-shared.mjs`. The `?worker&url` query ensures Vite's worker pipeline emits a self-contained chunk.

### 3. Worker Output Directory (Vite Configuration)

**Issue:** Vite's default behavior outputs worker files to an `assets/` directory, but Nextcloud apps expect all JavaScript assets in the `js/` directory.

**Fix:** Updated `vite.config.ts` to configure worker output:

```typescript
export default createAppConfig({
  // ... entries
}, {
  config: {
    // ... other config
    worker: {
      rollupOptions: {
        output: {
          entryFileNames: 'js/[name]-[hash].js',
        },
      },
    },
  },
  // ... other options
})
```

### 4. Cluster API Changes (Callback to Promise)

**Issue:** The `getClusterExpansionZoom()` method signature changed from callback-based to Promise-based:

```typescript
// v5 (callback)
getClusterExpansionZoom(clusterId: number, callback: (err: Error, zoom: number) => void): void

// v6 (Promise)
getClusterExpansionZoom(clusterId: number): Promise<number>
```

**Fix:** Updated cluster click handlers in `src/components/map/MarkerCluster.vue` and `src/components/map/PictureCluster.vue`:

```javascript
// Before (v5)
onClusterClick(clusterId, clusterCoords) {
  this.map.getSource(this.stringId).getClusterExpansionZoom(
    clusterId,
    (err, zoom) => {
      if (err) return
      this.map.easeTo({ center: clusterCoords, zoom })
    }
  )
}

// After (v6)
async onClusterClick(clusterId, clusterCoords) {
  try {
    const zoom = await this.map.getSource(this.stringId).getClusterExpansionZoom(clusterId)
    this.map.easeTo({ center: clusterCoords, zoom })
  } catch (err) {
    console.error(err)
  }
}
```

### 5. isSourceLoaded() Error Handling

**Issue:** In v6, `isSourceLoaded()` fires an `ErrorEvent` when the source doesn't exist in the tile manager registry:

```javascript
isSourceLoaded(id) {
  const tileManager = this.style?.tileManagers[id]
  if (tileManager === undefined) {
    this.fire(new ErrorEvent(new Error(`There is no tile manager with ID '${id}'`)))
    return
  }
  return tileManager.loaded()
}
```

This causes console errors during style transitions when old sources are removed but render event handlers are still active.

**Fix:** Added `getSource()` guard before calling `isSourceLoaded()` in cluster components:

```javascript
// Before
onMapRender(e) {
  if (this.map.isSourceLoaded(this.stringId)) {
    this.updateMarkers()
  }
}

// After
onMapRender(e) {
  if (this.map.getSource(this.stringId) && this.map.isSourceLoaded(this.stringId)) {
    this.updateMarkers()
  }
}
```

### 6. setStyle() Timing Issues

**Issue:** In v6, `setStyle()` defaults to diff mode (`_diffStyle`), which is async and may take longer than expected. When switching between raster and vector styles, the diff might fail and fall back to a full rebuild, causing timing issues with the fixed 500ms timeout in `reRenderLayersAndTerrain()`.

**Fix:** Added `{ diff: false }` option to `setStyle()` calls in `src/mapControls.js` to force immediate full style rebuild:

```javascript
// Before
if (style.uri) {
  this.map.setStyle(style.uri)
} else {
  this.map.setStyle(style)
}

// After
if (style.uri) {
  this.map.setStyle(style.uri, { diff: false })
} else {
  this.map.setStyle(style, { diff: false })
}
```

This makes style transitions more predictable and avoids race conditions with the layer re-rendering logic.

### 7. Vue 3 Reactivity Proxy Issue

**Issue:** Vue 3 wraps objects in `data()` with a `reactive()` Proxy. MapLibre GL JS v6's `Color` class uses a lazy getter pattern that calls `Object.defineProperty()` to cache computed values. When the map object is wrapped in a Proxy, this violates Proxy invariants for non-writable, non-configurable properties, causing:

```
Uncaught TypeError: proxy must report the same value for the non-writable, 
non-configurable property '"rgb"'
```

**Fix:** Import `markRaw` from Vue and wrap the map instance to prevent reactivity:

```javascript
import { markRaw } from 'vue'

// In initMap()
this.map = markRaw(new Map(mapOptions))
```

This prevents Vue from wrapping the map and its internal objects in Proxies, allowing maplibre's internal caching mechanisms to work correctly.

### 8. Bug Fix: Missing map Reference

**Issue:** Typo in terrain code where `this.getSource('terrain')` was called instead of `this.map.getSource('terrain')`.

**Fix:** Corrected in `src/components/map/MaplibreMap.vue`:

```javascript
// Before (line ~603)
if (this.map.getTerrain() && this.getSource('terrain')) {

// After
if (this.map.getTerrain() && this.map.getSource('terrain')) {
```

## Files Modified

1. **src/components/map/MaplibreMap.vue**
   - Added `setWorkerUrl()` call
   - Added `markRaw()` wrapper for map instance
   - Fixed `this.getSource()` typo

2. **src/components/map/MarkerCluster.vue**
   - Updated `getClusterExpansionZoom()` to Promise API
   - Added `getSource()` guard before `isSourceLoaded()`

3. **src/components/map/PictureCluster.vue**
   - Updated `getClusterExpansionZoom()` to Promise API
   - Added `getSource()` guard before `isSourceLoaded()`

4. **src/mapControls.js**
   - Added `{ diff: false }` to `setStyle()` calls

5. **vite.config.ts**
   - Added worker output configuration to place worker files in `js/` directory

## Testing Checklist

After migration, verify:

- [ ] Base map renders correctly with both raster and vector tile sources
- [ ] GPX tracks are visible on the map
- [ ] Marker clusters display and expand correctly
- [ ] Picture clusters display and expand correctly
- [ ] Switching between tile servers works without errors
- [ ] Terrain elevation works correctly
- [ ] Globe projection toggle works
- [ ] No console errors about "tile manager" or "proxy"
- [ ] Worker file is output to `js/` directory (not `assets/`)

## References

- [MapLibre GL JS v5 to v6 Migration Guide](https://github.com/maplibre/maplibre-gl-js/blob/v6.0.0/docs/guides/v5-to-v6-migration-guide.md)
- [MapLibre GL JS v6 Documentation](https://github.com/maplibre/maplibre-gl-js/tree/v6.0.0/docs)
- [Vue 3 markRaw() Documentation](https://vuejs.org/api/reactivity-advanced.html#markraw)
