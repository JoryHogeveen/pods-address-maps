<?php

class Pods_Component_Maps_Leaflet implements Pods_Component_Maps_Provider {

	public static $nominatim_search_url = '';

	public static $nominatim_reverse_url = '';

	public static $response = array();

	public function __construct() {

		$search_url = 'https://nominatim.openstreetmap.org/search';
		$reverse_url = 'https://nominatim.openstreetmap.org/reverse';

		self::$nominatim_search_url = apply_filters( 'pods_maps_leaflet_nominatim_search_url', $search_url );
		self::$nominatim_reverse_url = apply_filters( 'pods_maps_leaflet_nominatim_reverse_url', $reverse_url );
	}

	/**
	 * Load provider assets.
	 * @inheritdoc
	 */
	public function assets() {

		wp_register_style( 'leaflet-css', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', array(), '1.9.4' );
		wp_register_script( 'leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', array(), '1.9.4', true );
	}

	/**
	 * Add options to the maps component.
	 * @inheritdoc
	 */
	public function options( $options = array() ) {

		$options['maps_type'] = array(
			'label'   => __( 'Default Tile Layer', 'pods' ),
			'default' => 'osm',
			'type'    => 'pick',
			'data'    => self::tile_layer_options(),
		);

		$options['maps_zoom'] = array(
			'label'   => __( 'Default Map Zoom Level', 'pods' ),
			'default' => 12,
			'type'    => 'number',
			'options' => array(
				'number_decimals'   => 0,
				'number_max_length' => 2,
				'number_min'        => 1,
				'number_max'        => 19,
				'number_format'     => '9999.99',
			),
		);

		$options['maps_scrollwheel'] = array(
			'label'   => __( 'Enable scrollwheel?', 'pods' ),
			'default' => 1,
			'type'    => 'boolean',
		);

		$options['maps_marker'] = array(
			'label'   => __( 'Default Map Custom Marker', 'pods' ),
			'type'    => 'file',
			'options' => array(
				'file_uploader'          => 'plupload',
				'file_edit_title'        => 0,
				'file_restrict_filesize' => '1MB',
				'file_type'              => 'images',
				'file_add_button'        => __( 'Upload Marker Icon', 'pods' ),
			),
		);

		return $options;
	}

	/**
	 * Add options to the maps fields.
	 * @inheritdoc
	 */
	public function field_options( $options = array(), $type = '' ) {

		$options['maps_type'] = array(
			'label'      => __( 'Tile Layer', 'pods' ),
			'depends-on' => array( 'maps' => true ),
			'default'    => pods_v( 'maps_type', Pods_Component_Maps::$options, 'osm', true ),
			'type'       => 'pick',
			'data'       => self::tile_layer_options(),
		);

		$options['maps_zoom'] = array(
			'label'      => __( 'Map Zoom Level', 'pods' ),
			'depends-on' => array( 'maps' => true ),
			'default'    => pods_v( 'maps_zoom', Pods_Component_Maps::$options, 12, true ),
			'type'       => 'number',
			'options'    => array(
				'number_decimals'   => 0,
				'number_max_length' => 2,
				'number_min'        => 1,
				'number_max'        => 19,
				'number_format'     => '9999.99',
			),
		);

		$options['maps_scrollwheel'] = array(
			'label'      => __( 'Enable scroll wheel?', 'pods' ),
			'default'    => 1,
			'type'       => 'boolean',
			'depends-on' => array( 'maps' => true ),
		);

		$options['maps_info_window'] = array(
			'label'      => __( 'Display a Popup', 'pods' ),
			'default'    => 0,
			'type'       => 'boolean',
			'depends-on' => array( 'maps' => true ),
			'dependency' => true,
		);

		$options['maps_info_window_content'] = array(
			'label'      => __( 'Popup content', 'pods' ),
			'depends-on' => array(
				'maps'             => true,
				'maps_info_window' => true,
			),
			'default'    => 'paragraph',
			'type'       => 'pick',
			'data'       => array(
				'paragraph'    => __( 'Custom', 'pods' ),
				'wysiwyg'      => __( 'Custom (WYSIWYG)', 'pods' ),
				'display_type' => __( 'Display Type', 'pods' ),
			),
		);

		if ( pods_components()->is_component_active( 'templates' ) ) {
			$options['maps_info_window_content']['data']['template'] = __( 'Template', 'pods' );
			$options['maps_info_window_template'] = array(
				'label'      => __( 'Popup template', 'pods' ),
				'depends-on' => array(
					'maps'                     => true,
					'maps_info_window'         => true,
					'maps_info_window_content' => 'template',
				),
				'default'    => 'true',
				'type'       => 'pick',
				'data'       => (array) Pods_Component_Maps::get_template_titles(),
				'pick_format_type'   => 'single',
				'pick_format_single' => 'dropdown',
			);
		}

		$options['maps_marker'] = array(
			'label'      => __( 'Map Custom Marker', 'pods' ),
			'depends-on' => array( 'maps' => true ),
			'default'    => pods_v( 'maps_marker', Pods_Component_Maps::$options ),
			'type'       => 'file',
			'options'    => array(
				'file_uploader'          => 'plupload',
				'file_edit_title'        => 0,
				'file_restrict_filesize' => '1MB',
				'file_type'              => 'images',
				'file_add_button'        => __( 'Upload Marker Icon', 'pods' ),
			),
		);

		return $options;
	}

	/**
	 * The input field view file. Used by pods_view();
	 * @inheritdoc
	 */
	public function field_input_view() {

		return plugin_dir_path( __FILE__ ) . 'ui/fields/map-leaflet.php';
	}

	/**
	 * The display field view file. Used by pods_view();
	 * @inheritdoc
	 */
	public function field_display_view() {

		return plugin_dir_path( __FILE__ ) . 'ui/front/map-leaflet.php';
	}

	/**
	 * Geocode an address with given data.
	 * @inheritdoc
	 */
	public static function geocode_address( $data, $api_key = '' ) {

		$raw_data = self::search( $data );
		$address = self::get_address( $raw_data );
		$latlng = self::get_latlng( $raw_data );

		return array( 'address' => $address, 'geo' => $latlng );
	}

	/**
	 * Geocode an address into Latitude and Longitude values.
	 * @inheritdoc
	 */
	public static function geocode_address_to_latlng( $address, $api_key = '' ) {

		$raw_data = self::search( $address );

		return self::get_latlng( $raw_data );
	}

	/**
	 * Get address data from Latitude and Longitude values.
	 * @inheritdoc
	 */
	public static function geocode_latlng_to_address( $lat_lng, $api_key = '' ) {

		$raw_data = self::reverse( $lat_lng );

		return self::get_address( $raw_data );
	}

	/**
	 * Perform a Nominatim search lookup.
	 *
	 * @param string|array $address
	 *
	 * @return array
	 */
	public static function search( $address ) {

		if ( is_array( $address ) ) {
			foreach ( $address as $key => $val ) {
				if ( is_array( $val ) ) {
					$address[ $key ] = implode( ', ', $val );
				}
			}
			$address = implode( ', ', array_filter( $address ) );
		}

		$query = trim( (string) $address );
		if ( '' === $query ) {
			return array();
		}

		$url = add_query_arg( array(
			'q'               => $query,
			'format'          => 'jsonv2',
			'addressdetails'  => 1,
			'limit'           => 1,
			'email'           => self::contact_email(),
		), self::$nominatim_search_url );

		$data = self::request_json( $url );

		if ( ! empty( $data[0] ) && is_array( $data[0] ) ) {
			return $data[0];
		}

		// Some hosts/proxies respond better with the older format.
		$legacy_url = add_query_arg( array(
			'q'               => $query,
			'format'          => 'json',
			'addressdetails'  => 1,
			'limit'           => 1,
			'email'           => self::contact_email(),
		), self::$nominatim_search_url );

		$data = self::request_json( $legacy_url );

		if ( ! empty( $data[0] ) && is_array( $data[0] ) ) {
			return $data[0];
		}

		return array();
	}

	/**
	 * Perform a Nominatim reverse lookup.
	 *
	 * @param string|array $lat_lng
	 *
	 * @return array
	 */
	public static function reverse( $lat_lng ) {

		$lat = null;
		$lng = null;

		if ( is_array( $lat_lng ) ) {
			$lat = pods_v( 'lat', $lat_lng, pods_v( 0, $lat_lng ) );
			$lng = pods_v( 'lng', $lat_lng, pods_v( 1, $lat_lng ) );
		} elseif ( is_string( $lat_lng ) && false !== strpos( $lat_lng, ',' ) ) {
			$parts = explode( ',', $lat_lng );
			$lat = isset( $parts[0] ) ? trim( $parts[0] ) : null;
			$lng = isset( $parts[1] ) ? trim( $parts[1] ) : null;
		}

		if ( null === $lat || null === $lng ) {
			return array();
		}

		$url = add_query_arg( array(
			'lat'            => (float) $lat,
			'lon'            => (float) $lng,
			'format'         => 'jsonv2',
			'addressdetails' => 1,
			'email'          => self::contact_email(),
		), self::$nominatim_reverse_url );

		$data = self::request_json( $url );

		if ( is_array( $data ) ) {
			return $data;
		}

		return array();
	}

	/**
	 * Build normalized address array from Nominatim result.
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	public static function get_address( $data ) {

		if ( empty( $data['address'] ) || ! is_array( $data['address'] ) ) {
			return array();
		}

		$address = $data['address'];
		$line_1_parts = array();

		if ( ! empty( $address['road'] ) ) {
			$line_1_parts[] = $address['road'];
		}
		if ( ! empty( $address['house_number'] ) ) {
			$line_1_parts[] = $address['house_number'];
		}

		$line_2 = '';
		foreach ( array( 'suburb', 'neighbourhood', 'quarter' ) as $line_2_key ) {
			if ( ! empty( $address[ $line_2_key ] ) ) {
				$line_2 = $address[ $line_2_key ];
				break;
			}
		}

		$city = '';
		foreach ( array( 'city', 'town', 'village', 'hamlet', 'municipality' ) as $city_key ) {
			if ( ! empty( $address[ $city_key ] ) ) {
				$city = $address[ $city_key ];
				break;
			}
		}

		$region = '';
		foreach ( array( 'state', 'county', 'state_district', 'region' ) as $region_key ) {
			if ( ! empty( $address[ $region_key ] ) ) {
				$region = $address[ $region_key ];
				break;
			}
		}

		$normalized = array(
			'line_1'       => implode( ' ', $line_1_parts ),
			'line_2'       => $line_2,
			'postal_code'  => pods_v( 'postcode', $address, '' ),
			'city'         => $city,
			'region'       => $region,
			'country'      => pods_v( 'country', $address, '' ),
			'country_code' => strtoupper( (string) pods_v( 'country_code', $address, '' ) ),
		);

		return apply_filters( 'pods_component_maps_leaflet_get_address', $normalized, $address, $data );
	}

	/**
	 * Return lat/lng data from a Nominatim result.
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	public static function get_latlng( $data ) {

		if ( empty( $data['lat'] ) || empty( $data['lon'] ) ) {
			return array();
		}

		return array(
			'lat' => (float) $data['lat'],
			'lng' => (float) $data['lon'],
		);
	}

	/**
	 * Execute a remote request and decode JSON.
	 *
	 * @param string $url
	 *
	 * @return array
	 */
	public static function request_json( $url ) {

		$args = array(
			'timeout' => 15,
			'headers' => array(
				'Accept'     => 'application/json',
				'Referer'    => home_url( '/' ),
				'User-Agent' => self::build_user_agent(),
			),
		);

		$post = wp_remote_get( $url, $args );
		if ( is_wp_error( $post ) ) {
			self::$response = array( 'error' => $post->get_error_message() );
			return array();
		}

		$code = (int) wp_remote_retrieve_response_code( $post );
		$body = wp_remote_retrieve_body( $post );

		if ( 200 !== $code || empty( $body ) ) {
			self::$response = array(
				'error'  => 'Request failed',
				'code'   => $code,
				'body'   => $body,
				'url'    => $url,
			);
			return array();
		}

		$data = json_decode( $body, true );
		if ( ! is_array( $data ) ) {
			self::$response = array(
				'error' => 'Invalid JSON response',
				'body'  => $body,
				'url'   => $url,
			);
			return array();
		}

		self::$response = $data;

		return $data;
	}

	/**
	 * Build a contactable user-agent for Nominatim usage policy compliance.
	 *
	 * @return string
	 */
	public static function build_user_agent() {

		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		if ( empty( $host ) ) {
			$host = 'wordpress';
		}

		$email = self::contact_email();
		if ( ! empty( $email ) ) {
			return 'PodsMapsLeaflet/1.0 (' . $host . '; ' . $email . ')';
		}

		return 'PodsMapsLeaflet/1.0 (' . $host . ')';
	}

	/**
	 * Contact email used for Nominatim policy compliance.
	 *
	 * @return string
	 */
	public static function contact_email() {

		$email = (string) get_option( 'admin_email', '' );
		if ( is_email( $email ) ) {
			return $email;
		}

		return '';
	}

	/**
	 * Available tile layer options.
	 *
	 * @return array
	 */
	public static function tile_layer_options() {

		return array(
			'osm'         => __( 'OpenStreetMap Standard', 'pods' ),
			'osm-human'   => __( 'OpenStreetMap Humanitarian', 'pods' ),
			'carto-light' => __( 'CartoDB Positron', 'pods' ),
			'carto-dark'  => __( 'CartoDB Dark Matter', 'pods' ),
		);
	}

}
