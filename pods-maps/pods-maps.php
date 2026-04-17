<?php
/*
Plugin Name: Pods Maps
Plugin URI: http://pods.io/
Description: Adds map functionality to Pods.
Version: 2.0-dev1
Author: Jory Hogeveen
Author URI: https://www.keraweb.nl
Text Domain: pods-maps
Domain Path: /languages/

Copyright 2013-2017  Jory Hogeveen  (email : info@keraweb.nl)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

define( 'PODS_MAPS_VERSION', '2.0-dev1' );
define( 'PODS_MAPS_URL', plugin_dir_url( __FILE__ ) );
define( 'PODS_MAPS_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Initialize plugin
 */
function pods_maps_init() {
	//register_activation_hook( __FILE__, 'pods_maps_reset' );
	//register_deactivation_hook( __FILE__, 'pods_maps_reset' );

	if ( ! function_exists( 'pods' ) || ! defined( 'PODS_DIR' ) ) {
		return; // Pods not activated.
	}

	if ( file_exists( PODS_DIR . 'components/Maps/Maps.php' ) ) {
		return; // Maps is now part of Pods core.
	}

	// Load plugin textdomain
	load_plugin_textdomain( 'pods-address-field', false, PODS_MAPS_DIR . 'languages/' );
	// Register component
	add_filter( 'pods_components_register', 'pods_maps_register_component' );

	add_shortcode( 'pods_maps', 'pods_shortcode_maps' );

}
add_action( 'plugins_loaded', 'pods_maps_init', 20 );

/**
 * Register component
 */
function pods_maps_register_component( $components ) {
	$components[] = array(
		'File' => PODS_MAPS_DIR . 'components/Maps/Maps.php',
	);
	return $components;
}

function pods_shortcode_maps( $atts = [] ) {
	return pods_display_map( [], $atts );
}


function pods_display_map( $value, $options = array() ) {
	$options = array_merge( Pods_Component_Maps::$options, $options );

	$view     = '';
	$name     = pods_v( 'name', $options, '' );
	$type     = pods_v( 'type', $options, '' );
	$multiple = pods_v( 'multiple', $options, ( isset( $value['geo'] ) ? false : true ) );

	if ( ! $value ) {
		$field_name = pods_v( 'field', $options, '' );

		if ( $field_name ) {

			$merge_field_options = function ( $field_options ) use ( $options ) {
				foreach ( $field_options as $name => $value ) {
					if ( strpos( $name, 'maps_' ) === 0 && ! isset( $options[ $name ] ) ) {
						$options[ $name ] = $value;
					}
				}

				return $options;
			};

			if ( $name ) {

				$pod = pods( $name );
				$field = $pod->fields( $field_name );

				if ( $field ) {
					$options = $merge_field_options( $field->get_args() );
				}

				$id = pods_v( 'id', $options, null );
				if ( $id ) {
					$multiple = false;
					$value = pods_field_raw( $name, $id, $field_name, true );
				} else {
					$pod = pods( $name );
					$allowed_keys = array( 'select', 'order', 'orderby', 'limit', 'offset', 'where', 'having', 'groupby', 'page', 'search' );
					$find_options = array_intersect_key( $options, array_flip( $allowed_keys ) );
					$pod->find( $find_options );
					$value = [];
					while ( $pod->fetch() ) {
						$location = $pod->field( $field_name, true, true );
						if ( $location ) {
							$location['pod'] = pods( $name, $pod->id() );
							$value[] = $location;
						}
					}
				}

			} elseif ( is_singular() ) {
				$multiple = false;
				$value = pods_field_raw( get_post_type(), get_the_ID(), $field_name, true );
				if ( $value ) {
					$value['pod'] = pods( get_post_type(), get_the_ID() );
				}
			} elseif ( is_archive() ) {
				$value = [];
				$posts = get_posts();

				foreach ( $posts as $post ) {
					$_pod = pods( $post->post_type, $post );
					$location = pods_field_raw( $_pod, $post, $field_name, true );
					if ( $location ) {
						$location['pod'] = $_pod;
						$value[] = $location;
					}
				}
			}
		}
	}

	if ( is_callable( array( Pods_Component_Maps::$provider, 'field_display_view' ) ) ) {
		$view = Pods_Component_Maps::$provider->field_display_view();
	}

	if ( $view && file_exists( $view ) ) {
		// Add hidden lat/lng fields for non latlng view types
		$maps_value = pods_view( $view, compact( array_keys( get_defined_vars() ) ), false, 'cache', true );

		return $maps_value;
	}
	return '';
}
