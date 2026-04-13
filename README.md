# pods-address-maps

This repo contains two addon plugins:

- Pods Address field
- Pods Maps component

The code started from the official Pods PR and was refactored into standalone plugins:
[PR at Pods GitHub](https://github.com/pods-framework/pods/pull/3634)

## Installation

Upload both folders from this repo to your WordPress plugins folder (`/wp-content/plugins/`), then activate:

- `pods-address-field`
- `pods-maps`

You can also zip each plugin folder and install them from the WordPress plugin installer.

## Maps Providers

The Maps component supports two providers:

- Google Maps
- OpenStreetMap (Leaflet)

Provider is selected in the Maps component settings:

- `/wp-admin/admin.php?page=pods-component-maps`

## Provider Requirements

### Google Maps

Google requires API keys.

1. Create keys in Google Cloud: <https://console.cloud.google.com/apis/>
2. Set keys in Maps component settings:
   - Maps JavaScript API Key
   - Maps HTTP API Key (optional but recommended for server-side geocoding)

### OpenStreetMap (Leaflet)

No API key is required for default usage.

- Interactive maps are rendered with Leaflet.
- Geocoding uses Nominatim (OpenStreetMap).
- Respect Nominatim usage policy/rate limits for production traffic.

## Default Center Point

The Maps component settings include a shared fallback map center:

- Default Map Center Latitude
- Default Map Center Longitude

This fallback is used by both Google and Leaflet when a map has no saved coordinates.

## Via Subversion (SVN)

Using GitHub Subversion features you can install with:

```bash
cd /path/to/wp-content/plugins # use sudo or see below if permission issues come up
svn export https://github.com/JoryHogeveen/pods-address-maps.git/trunk/pods-maps
svn export https://github.com/JoryHogeveen/pods-address-maps.git/trunk/pods-address-field
```

If you run into permission problems, download the folders to a writable location, fix permissions (for example `sudo chown www-data:www-data -R pods*`), and move the folders into place.
