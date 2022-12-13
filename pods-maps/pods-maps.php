<?php
/*
Plugin Name: Pods Maps
Plugin URI: http://pods.io/
Description: Adds map functionality to Pods.
Version: 1.0
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

define( 'PODS_MAPS_VERSION', '1.0' );
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


function pods_display_map( $value, $options = array() ) {
	$multiple = true;

	$options = array_merge( Pods_Component_Maps::$options, $options );

	$view = '';
	$name = pods_v( 'name', $options, '' );
	$type = pods_v( 'type', $options, '' );

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
