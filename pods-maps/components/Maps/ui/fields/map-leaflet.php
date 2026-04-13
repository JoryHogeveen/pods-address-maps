<?php
wp_enqueue_style( 'leaflet-css' );
wp_enqueue_script( 'leaflet' );
wp_enqueue_script( 'pods-maps' );
wp_enqueue_style( 'pods-maps' );

if ( ! isset( $form_field_type ) ) {
	$form_field_type = PodsForm::$field_type;
}

$map_options = array();
if ( ! empty( $options['maps_zoom'] ) ) {
	$map_options['zoom'] = (int) $options['maps_zoom'];
} else {
	$map_options['zoom'] = (int) Pods_Component_Maps::$options['maps_zoom'];
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

if ( ! empty( $map_options['marker'] ) ) {
	$map_options['marker'] = wp_get_attachment_image_url( $map_options['marker'], 'full' );
}

$default_center = Pods_Component_Maps::get_default_center( $options );

$map_options['scrollwheel'] = (bool) pods_v( 'maps_scrollwheel', $options, pods_v( 'maps_scrollwheel', Pods_Component_Maps::$options, true ) );

$attributes = array();
$attributes = PodsForm::merge_attributes( $attributes, $name, $form_field_type, $options );

$id_prefix = $attributes['id'];
$id_prefix_fallback = PodsForm::merge_attributes( array(), $name, $form_field_type )['id'];

if ( ! empty( $options['maps_info_window'] ) && in_array( $options['maps_info_window_content'], array( 'paragraph', 'wysiwyg' ), true ) ) {
	echo PodsForm::label( $name . '-info-window', __( 'Popup content', 'pods' ) );
	if ( 'address' === $type ) {
		echo PodsForm::comment( $name . '-info-window', __( 'You can use the following tags for address fields', 'pods' ) . ': <br><code>{{line_1}}</code>, <code>{{line_2}}</code>, <code>{{postal_code}}</code>, <code>{{city}}</code>, <code>{{region}}</code>, <code>{{country}}</code>' );
	}
	echo PodsForm::field( $name . '[info_window]', pods_v( 'info_window', $value ), $options['maps_info_window_content'], array(
		'settings' => array(
			'wpautop' => false,
			'editor_height' => 150,
		),
	) );
}

echo PodsForm::label( 'map-leaflet', __( 'OpenStreetMap (Leaflet)', 'pods' ) );
?>
<input type="button" name="<?php echo esc_attr( $id_prefix . '-map-lookup-button' ); ?>"
	id="<?php echo esc_attr( $id_prefix . '-map-lookup-button' ); ?>"
	value="<?php esc_attr_e( 'Lookup Location from Address', 'pods' ); ?>" />
<div id="<?php echo esc_attr( $id_prefix . '-map-canvas' ); ?>"
	class="pods-maps-map-canvas pods-<?php echo esc_attr( $form_field_type ); ?>-maps-map-canvas"></div>

<script type="text/javascript">
jQuery( document ).ready( function ( $ ) {
	$( window ).on( 'load', function() {
		if ( typeof L === 'undefined' ) {
			return;
		}

		var fieldNames = <?php echo wp_json_encode( array(
			'line_1'      => $name . '[address][line_1]',
			'line_2'      => $name . '[address][line_2]',
			'city'        => $name . '[address][city]',
			'postal_code' => $name . '[address][postal_code]',
			'region'      => $name . '[address][region]',
			'country'     => $name . '[address][country]',
			'text'        => $name . '[text]',
			'info_window' => $name . '[info_window]',
			'lat'         => $name . '[geo][lat]',
			'lng'         => $name . '[geo][lng]',
		) ); ?>;

		function findField( oldSuffix, dfvSuffix, inputName ) {
			var field = $();

			if ( inputName ) {
				field = $( '[name="' + inputName + '"]' );
				if ( field.length ) {
					return field.first();
				}
			}

			if ( dfvSuffix ) {
				field = $( '#<?php echo esc_attr( $id_prefix ); ?>-' + dfvSuffix );
				if ( field.length ) {
					return field.first();
				}
			}

			if ( ! oldSuffix && ! dfvSuffix ) {
			field = $( '#<?php echo esc_attr( $id_prefix ); ?>' );
			if ( field.length ) {
				return field.first();
				}
			}

			if ( oldSuffix ) {
				field = $( '#<?php echo esc_attr( $id_prefix ); ?>-' + oldSuffix );
				if ( field.length ) {
					return field.first();
				}

				field = $( '#<?php echo esc_attr( $id_prefix_fallback ); ?>-' + oldSuffix );
				if ( field.length ) {
					return field.first();
				}
			}

			return $();
		}

		function setFieldValue( field, value ) {
			if ( ! field.length ) {
				return;
			}

			field.val( value );
			field.trigger( 'input' );
			field.trigger( 'change' );
		}

		var fieldType = '<?php echo esc_attr( $type ); ?>',
			mapCanvas = document.getElementById( '<?php echo esc_attr( $id_prefix . '-map-canvas' ); ?>' ),
			geocodeButton = $( '#<?php echo esc_attr( $id_prefix . '-map-lookup-button' ); ?>' ),
			fields = {
				line_1: findField( 'address-line-1', '', fieldNames.line_1 ),
				line_2: findField( 'address-line-2', 'line-2', fieldNames.line_2 ),
				city: findField( 'address-city', 'city', fieldNames.city ),
				postal_code: findField( 'address-postal-code', 'postal-code', fieldNames.postal_code ),
				region: findField( 'address-region', 'region', fieldNames.region ),
				country: findField( 'address-country', 'country', fieldNames.country ),
				text: findField( 'text', 'text', fieldNames.text ),
				info_window: findField( 'info-window', 'info-window', fieldNames.info_window ),
				lat: findField( 'geo-lat', 'geo-lat', fieldNames.lat ),
				lng: findField( 'geo-lng', 'geo-lng', fieldNames.lng )
			},
			fieldsFormat = <?php echo wp_json_encode( preg_replace( "/\n/m", '<br>', (string) pods_v( 'address_display_type_custom', $options ) ) ); ?>,
			markerIcon = <?php echo ( ! empty( $map_options['marker'] ) ? '\'' . esc_url( $map_options['marker'] ) . '\'' : 'null' ); ?>,
			popupEnabled = <?php echo esc_attr( ( ! empty( $options['maps_info_window'] ) ) ? 'true' : 'false' ); ?>,
			popupContent = '',
			popupEditor = null,
			address = null,
			latlng = {
				lat: <?php echo (float) $default_center[0]; ?>,
				lng: <?php echo (float) $default_center[1]; ?>
			};

		if ( fields.lat.length && fields.lng.length && fields.lat.val() !== '' && fields.lng.val() !== '' ) {
			latlng = {
				lat: Number( fields.lat.val() ),
				lng: Number( fields.lng.val() )
			};
		}

		var tileConfig = podsGetTileConfig( '<?php echo esc_attr( $map_options['type'] ); ?>' );
		var map = L.map( mapCanvas, {
			scrollWheelZoom: <?php echo $map_options['scrollwheel'] ? 'true' : 'false'; ?>
		} ).setView( [ latlng.lat, latlng.lng ], <?php echo absint( $map_options['zoom'] ); ?> );

		L.tileLayer( tileConfig.url, {
			attribution: tileConfig.attribution,
			maxZoom: 19
		} ).addTo( map );

		var markerOptions = { draggable: true };
		if ( markerIcon ) {
			markerOptions.icon = L.icon( {
				iconUrl: markerIcon,
				iconSize: [ 32, 32 ],
				iconAnchor: [ 16, 32 ],
				popupAnchor: [ 0, -32 ]
			} );
		}

		var marker = L.marker( [ latlng.lat, latlng.lng ], markerOptions ).addTo( map );

		if ( popupEnabled ) {
			podsUpdatePopupContent();
		}

		geocodeButton.on( 'click', function ( event ) {
			event.preventDefault();

			if ( 'lat-lng' === fieldType ) {
				latlng = {
					lat: Number( fields.lat.val() ),
					lng: Number( fields.lng.val() )
				};
				podsSetMapLocation( false );
				return;
			}

			if ( 'address' === fieldType ) {
				address = podsMergeAddressFields();
			} else {
				address = fields.text.val();
			}

			podsMapsAjax( 'geocode_address_to_latlng', address, function( data ) {
				if ( ! data || 'undefined' === typeof data.lat || 'undefined' === typeof data.lng ) {
					alert( 'Geocode was not successful. Please try another address.' );
					return;
				}

				latlng = {
					lat: Number( data.lat ),
					lng: Number( data.lng )
				};
				podsUpdateLatLng();
				podsSetMapLocation( true );
			} );
		} );

		marker.on( 'drag', function ( event ) {
			latlng = {
				lat: event.target.getLatLng().lat,
				lng: event.target.getLatLng().lng
			};
			podsUpdateLatLng();
		} );

		marker.on( 'dragend', function ( event ) {
			latlng = {
				lat: event.target.getLatLng().lat,
				lng: event.target.getLatLng().lng
			};
			podsSetMapLocation( true );
			podsUpdateLatLng();
			podsMapsAjax( 'geocode_latlng_to_address', latlng, function( data ) {
				if ( ! data ) {
					return;
				}
				address = data;
				podsUpdateAddress();
			} );
		} );

		marker.on( 'click', function () {
			if ( popupEnabled ) {
				marker.openPopup();
			}
		} );

		function podsUpdatePopupContent() {
			if ( fields.info_window.length ) {
				popupContent = podsFormatFieldsToHTML( fields.info_window.val() );
				marker.bindPopup( popupContent );

				fields.info_window.on( 'change keyup', function () {
					popupContent = podsFormatFieldsToHTML( fields.info_window.val() );
					marker.bindPopup( popupContent );
				} );

				var wait = setInterval( function () {
					if ( typeof tinyMCE !== 'undefined' && tinyMCE.editors.length ) {
						clearInterval( wait );
						popupEditor = tinyMCE.get( fields.info_window.attr( 'id' ) );
						if ( popupEditor ) {
							popupEditor.on( 'change keyup', function () {
								popupContent = podsFormatFieldsToHTML( popupEditor.getContent( { format: 'raw' } ) );
								marker.bindPopup( popupContent );
							} );
						}
					}
				}, 100 );

				return;
			}

			if ( 'text' === fieldType && fields.text.length ) {
				popupContent = fields.text.val();
				marker.bindPopup( popupContent );
				fields.text.on( 'change keyup', function () {
					popupContent = fields.text.val();
					marker.bindPopup( popupContent );
				} );
				return;
			}

			popupContent = podsFormatFieldsToHTML( fieldsFormat );
			marker.bindPopup( popupContent );
			$.each( fields, function ( key, field ) {
				field.on( 'change keyup', function () {
					popupContent = podsFormatFieldsToHTML( fieldsFormat );
					marker.bindPopup( popupContent );
				} );
			} );
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

		function podsMapsAjax( action, data, callback ) {
			$.post(
				PodsMaps.ajaxurl,
				{
					action: 'pods_maps',
					_pods_maps_nonce: PodsMaps._nonce,
					pods_maps_action: action,
					pods_maps_data: data
				},
				function ( response ) {
					if ( response && response.success && typeof callback === 'function' ) {
						callback( response.data );
						return;
					}

					if ( response && response.data ) {
						alert( response.data );
					} else {
						alert( 'Map lookup failed. Please try again.' );
					}
				}
			).fail( function () {
				alert( 'Map lookup request failed. Please check your network/server logs.' );
			} );
		}

		function podsSetMapLocation( pan ) {
			marker.setLatLng( [ latlng.lat, latlng.lng ] );
			if ( pan ) {
				map.panTo( [ latlng.lat, latlng.lng ] );
			} else {
				map.setView( [ latlng.lat, latlng.lng ], map.getZoom() );
			}
		}

		function podsUpdateLatLng() {
			if ( fields.lat.length ) {
				setFieldValue( fields.lat, latlng.lat );
			}
			if ( fields.lng.length ) {
				setFieldValue( fields.lng, latlng.lng );
			}
		}

		function podsUpdateAddress() {
			if ( typeof address !== 'object' || ! address ) {
				return;
			}

			if ( 'address' === fieldType ) {
				if ( fields.line_1.length ) {
					setFieldValue( fields.line_1, address.line_1 || '' );
				}
				if ( fields.line_2.length ) {
					setFieldValue( fields.line_2, address.line_2 || '' );
				}
				if ( fields.city.length ) {
					setFieldValue( fields.city, address.city || '' );
				}
				if ( fields.postal_code.length ) {
					setFieldValue( fields.postal_code, address.postal_code || '' );
				}
				if ( fields.region.length ) {
					setFieldValue( fields.region, address.region || '' );
				}
				if ( fields.country.length ) {
					if ( fields.country.is( 'select' ) ) {
						setFieldValue( fields.country, address.country_code || address.country || '' );
					} else {
						setFieldValue( fields.country, address.country || '' );
					}
				}
			} else if ( 'text' === fieldType && fields.text.length ) {
				var parts = [];
				if ( address.line_1 ) {
					parts.push( address.line_1 );
				}
				if ( address.line_2 ) {
					parts.push( address.line_2 );
				}
				if ( address.city ) {
					parts.push( address.city );
				}
				if ( address.postal_code ) {
					parts.push( address.postal_code );
				}
				if ( address.region ) {
					parts.push( address.region );
				}
				if ( address.country ) {
					parts.push( address.country );
				}
				setFieldValue( fields.text, parts.join( ', ' ) );
			}
		}

		function podsMergeAddressFields() {
			var tmpAddress = [];
			if ( fields.line_1.length ) {
				tmpAddress.push( fields.line_1.val() );
			}
			if ( fields.line_2.length ) {
				tmpAddress.push( fields.line_2.val() );
			}
			if ( fields.city.length ) {
				tmpAddress.push( fields.city.val() );
			}
			if ( fields.postal_code.length ) {
				tmpAddress.push( fields.postal_code.val() );
			}
			if ( fields.region.length ) {
				tmpAddress.push( fields.region.val() );
			}
			if ( fields.country.length ) {
				tmpAddress.push( fields.country.val() );
			}
			return tmpAddress.join( ', ' );
		}

		function podsFormatFieldsToHTML( html ) {
			$.each( fields, function( key, field ) {
				if ( field.length && field.val().length ) {
					html = html.replace( '{{' + key + '}}', field.val() );
				} else {
					html = html.replace( '{{' + key + '}}', '{{REMOVE}}' );
				}
			} );

			var lines = html.split( '<br>' );
			$.each( lines, function( key, line ) {
				if ( line === '{{REMOVE}}' ) {
					delete lines[ key ];
				} else {
					lines[ key ] = line.replace( '{{REMOVE}}', '' );
				}
			} );

			return lines.filter( function () { return true; } ).join( '<br>' );
		}
	} );
} );
</script>
