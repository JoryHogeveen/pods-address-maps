<?php
/**
 * @var array  $value {
 *     @type  array       $address {
 *         @type  string  $line_1
 *         @type  string  $line_2
 *         @type  string  $postal_code
 *         @type  string  $city
 *         @type  string  $region
 *         @type  string  $country
 *     }
 *     @type  array       $geo {
 *         @type  int  $lat
 *         @type  int  $lng
 *     }
 *     @type  string      $info_window  The info window content (with magic tags)
 *     @type  int|string  $marker_icon  (optional) Overwrite default marker from $options?
 *     @type  Pods        $pod          (optional) The pod object for this value.
 *     @type  string      $name         (optional) The pod name.
 *     @type  int         $id           (optional) The ID of this value object.
 * }
 * @var array  $options {
 *     @type  int         $maps_zoom         The default zoom depth.
 *     @type  string      $maps_type         The tile layer type
 *     @type  int|string  $maps_marker       The marker (can be an attachment ID or a URL)
 *     @type  bool        $maps_scrollwheel  Enable/Disable the scrollwheel?
 * }
 * @var string $name
 * @var string $type
 * @var bool   $multiple  Value contains an array of multiple values?
 */

wp_enqueue_style( 'leaflet-css' );
wp_enqueue_script( 'leaflet' );
wp_enqueue_script( 'pods-maps' );
wp_enqueue_style( 'pods-maps' );

$name = isset( $name ) ? $name : '';
$type = isset( $type ) ? $type : '';
$options = isset( $options ) ? $options : array();
$multiple = isset( $multiple ) ? $multiple : false;

$attributes = array();
$attributes = PodsForm::merge_attributes( $attributes, $name, $type, $options );

$default_center = Pods_Component_Maps::get_default_center( $options );

$map_options = array();

if ( ! empty( $options['maps_center'] ) ) {
	$map_options['center'] = (array) $options['maps_center'];
} else {
	$map_options['center'] = $default_center;
}

if ( isset( $options['maps_center_auto'] ) ) {
	$map_options['center_auto'] = (bool) $options['maps_center_auto'];
} else {
	$map_options['center_auto'] = empty( $options['maps_center'] );
}

if ( ! empty( $options['maps_zoom'] ) ) {
	$map_options['zoom'] = (int) $options['maps_zoom'];
} else {
	$map_options['zoom'] = (int) pods_v( 'maps_zoom', Pods_Component_Maps::$options, 12 );
}

if ( isset( $options['maps_zoom_auto'] ) ) {
	$map_options['zoom_auto'] = (bool) $options['maps_zoom_auto'];
} else {
	$map_options['zoom_auto'] = true;
}

if ( ! empty( $options['maps_type'] ) ) {
	$map_options['type'] = $options['maps_type'];
} else {
	$map_options['type'] = pods_v( 'maps_type', Pods_Component_Maps::$options, 'osm' );
}

if ( ! empty( $options['maps_marker'] ) ) {
	$map_options['marker'] = $options['maps_marker'];
} else {
	$map_options['marker'] = pods_v( 'maps_marker', Pods_Component_Maps::$options );
}

$map_options['scrollwheel'] = (bool) pods_v( 'maps_scrollwheel', $options, pods_v( 'maps_scrollwheel', Pods_Component_Maps::$options, true ) );

if ( ! empty( $map_options['marker'] ) && is_numeric( $map_options['marker'] ) ) {
	$map_options['marker'] = wp_get_attachment_image_url( $map_options['marker'], 'full' );
}

if ( ! $multiple ) {
	$value = array( $value );
	$multiple = false;
}

foreach ( $value as $key => $val ) {

	$val = wp_parse_args( $val, array(
		'address'      => array(),
		'geo'          => array(),
		'address_html' => '',
		'info_window'  => '',
		'marker_icon'  => null,
	) );

	if ( 'custom' === pods_v( 'maps_info_window_content', $options, true ) ) {
		$address_html = '';
		if ( ! empty( $val['address_html'] ) ) {
			$address_html = $val['address_html'];
		} elseif ( ! empty( $val['info_window'] ) ) {
			$address_html = $val['info_window'];
		}
	} elseif ( ! isset( $address_html ) || $multiple ) {
		if ( ! empty( $val['info_window'] ) ) {
			$format = $val['info_window'];
		} elseif (
			pods_components()->is_component_active( 'templates' )
			&& 'template' === pods_v( 'maps_info_window_content', $options )
			&& isset( $val['pod'] )
			&& $val['pod'] instanceof Pods
		) {
			$template = get_post( pods_v( 'maps_info_window_template', $options ) );
			if ( $template instanceof WP_Post ) {
				$format = $val['pod']->template( $template->post_title );
			}
		} else {
			$format = PodsForm::field_method( 'address', 'default_display_format' );
			if ( 'custom' === pods_v( 'address_display_type', $options ) ) {
				$format = pods_v( 'address_display_type_custom', $options );
			}
		}
		$address_html = PodsForm::field_method( 'address', 'format_to_html', $format, $val, $options );
	}

	unset( $value[ $key ]['info_window'] );
	$value[ $key ]['address_html'] = $address_html;

	if ( is_numeric( $val['marker_icon'] ) ) {
		$value[ $key ]['marker_icon'] = wp_get_attachment_image_url( $val['marker_icon'], 'full' );
	}

	if ( is_array( $val['geo'] ) ) {
		$value[ $key ]['geo'] = array_map( 'floatval', $val['geo'] );
	}

	unset( $value[ $key ]['pod'] );
}

if ( ! empty( $options['maps_combine_equal_geo'] ) ) {
	$combined_values = array();
	foreach ( $value as $key => $val ) {
		$geo_key = implode( ',', $val['geo'] );
		if ( array_key_exists( $geo_key, $combined_values ) ) {
			$combined_values[ $geo_key ]['address_html'] .= $val['address_html'];
			continue;
		}
		$combined_values[ $geo_key ] = $val;
	}

	$value = array();
	foreach ( $combined_values as $val ) {
		$value[] = $val;
	}
}
?>
<div id="<?php echo esc_attr( $attributes['id'] . '-map-canvas' ); ?>"
	class="pods-maps-map-canvas pods-<?php echo esc_attr( $type ); ?>-maps-map-canvas"
	data-value="<?php echo esc_attr( json_encode( $value ) ); ?>"></div>

<script type="text/javascript">
jQuery( document ).ready( function ( $ ) {
	if ( typeof L === 'undefined' ) {
		return;
	}

	var mapCanvas = document.getElementById( '<?php echo esc_attr( $attributes['id'] . '-map-canvas' ); ?>' ),
		values = $( '#<?php echo esc_attr( $attributes['id'] . '-map-canvas' ); ?>' ).attr( 'data-value' ),
		autoCenter = <?php echo $map_options['center_auto'] ? 'true' : 'false'; ?>,
		autoZoom = <?php echo $map_options['zoom_auto'] ? 'true' : 'false'; ?>,
		defaultCenter = [ <?php echo implode( ', ', array_map( 'floatval', $map_options['center'] ) ); ?> ],
		defaultZoom = <?php echo absint( $map_options['zoom'] ); ?>,
		markerIconUrl = <?php echo ( ! empty( $map_options['marker'] ) ? '\'' . esc_url( $map_options['marker'] ) . '\'' : 'null' ); ?>;

	if ( values ) {
		try {
			values = JSON.parse( values );
		} catch ( err ) {
			return;
		}
	} else {
		return;
	}

	if ( autoCenter && values.length && values[0].hasOwnProperty( 'geo' ) ) {
		defaultCenter = [ values[0].geo.lat, values[0].geo.lng ];
	}

	var map = L.map( mapCanvas, {
		scrollWheelZoom: <?php echo $map_options['scrollwheel'] ? 'true' : 'false'; ?>
	} ).setView( defaultCenter, defaultZoom );

	var tileConfig = podsGetTileConfig( '<?php echo esc_attr( $map_options['type'] ); ?>' );
	L.tileLayer( tileConfig.url, {
		attribution: tileConfig.attribution,
		maxZoom: 19
	} ).addTo( map );

	var bounds = L.latLngBounds();
	var autoOpenPopup = ( 1 === values.length );

	$.each( values, function( i, val ) {
		if ( ! values[i].hasOwnProperty( 'geo' ) ) {
			return;
		}

		var markerOptions = {
			draggable: false
		};

		if ( values[i].hasOwnProperty( 'marker_icon' ) && values[i].marker_icon ) {
			markerOptions.icon = L.icon( {
				iconUrl: values[i].marker_icon,
				iconSize: [ 32, 32 ],
				iconAnchor: [ 16, 32 ],
				popupAnchor: [ 0, -32 ]
			} );
		} else if ( markerIconUrl ) {
			markerOptions.icon = L.icon( {
				iconUrl: markerIconUrl,
				iconSize: [ 32, 32 ],
				iconAnchor: [ 16, 32 ],
				popupAnchor: [ 0, -32 ]
			} );
		}

		values[i].marker = L.marker( [ values[i].geo.lat, values[i].geo.lng ], markerOptions ).addTo( map );
		bounds.extend( [ values[i].geo.lat, values[i].geo.lng ] );

		if ( values[i].address_html ) {
			values[i].marker.bindPopup( values[i].address_html );
			if ( autoOpenPopup ) {
				values[i].marker.openPopup();
			}
			values[i].marker.on( 'click', function () {
				$.each( values, function( index ) {
					if ( values[index].hasOwnProperty( 'marker' ) ) {
						values[index].marker.closePopup();
					}
				} );
				values[i].marker.openPopup();
			} );
		}
	} );

	if ( values.length > 1 && autoZoom && bounds.isValid() ) {
		map.fitBounds( bounds );
		if ( map.getZoom() > defaultZoom ) {
			map.setZoom( defaultZoom );
		}
		if ( ! autoCenter ) {
			map.setView( defaultCenter, map.getZoom() );
		}
	} else {
		map.setView( defaultCenter, defaultZoom );
	}

	function podsGetTileConfig( type ) {
		switch ( type ) {
			case 'osm-human':
				return {
					url: 'https://{s}.tile.openstreetmap.fr/hot/{z}/{x}/{y}.png',
					attribution: '&copy; OpenStreetMap contributors, HOT'
				};
			case 'carto-light':
				return {
					url: 'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png',
					attribution: '&copy; OpenStreetMap contributors &copy; CARTO'
				};
			case 'carto-dark':
				return {
					url: 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png',
					attribution: '&copy; OpenStreetMap contributors &copy; CARTO'
				};
			case 'osm':
			default:
				return {
					url: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
					attribution: '&copy; OpenStreetMap contributors'
				};
		}
	}
} );
</script>
