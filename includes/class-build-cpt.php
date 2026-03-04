<?php
/**
 * Build custom post type.
 *
 * @package WPConfigurator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPConfigurator_Build_CPT {
	const POST_TYPE = 'printer_build';

	/**
	 * Hook registrations.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_action( 'init', array( __CLASS__, 'register_meta' ) );
	}

	/**
	 * Register printer build post type.
	 *
	 * @return void
	 */
	public static function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'label'               => __( 'Printer Builds', 'wpconfigurator' ),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_rest'        => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'supports'            => array( 'title', 'author' ),
			)
		);
	}

	/**
	 * Register sanitized meta fields.
	 *
	 * @return void
	 */
	public static function register_meta() {
		register_post_meta(
			self::POST_TYPE,
			'build_name',
			array(
				'single'            => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => array( __CLASS__, 'can_edit_post_meta' ),
				'show_in_rest'      => false,
			)
		);

		register_post_meta(
			self::POST_TYPE,
			'selections',
			array(
				'single'            => true,
				'type'              => 'object',
				'sanitize_callback' => array( __CLASS__, 'sanitize_selections' ),
				'auth_callback'     => array( __CLASS__, 'can_edit_post_meta' ),
				'show_in_rest'      => false,
			)
		);

		register_post_meta(
			self::POST_TYPE,
			'selected_parts',
			array(
				'single'            => true,
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_selected_parts' ),
				'auth_callback'     => array( __CLASS__, 'can_edit_post_meta' ),
				'show_in_rest'      => false,
			)
		);

		register_post_meta(
			self::POST_TYPE,
			'schema_version',
			array(
				'single'            => true,
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'auth_callback'     => array( __CLASS__, 'can_edit_post_meta' ),
				'show_in_rest'      => false,
			)
		);

		register_post_meta(
			self::POST_TYPE,
			'updated_at',
			array(
				'single'            => true,
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_timestamp' ),
				'auth_callback'     => array( __CLASS__, 'can_edit_post_meta' ),
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * Meta auth callback.
	 *
	 * @param bool   $allowed Allowed.
	 * @param string $meta_key Meta key.
	 * @param int    $post_id Post ID.
	 * @return bool
	 */
	public static function can_edit_post_meta( $allowed, $meta_key, $post_id ) {
		return current_user_can( 'edit_post', $post_id );
	}

	/**
	 * Sanitize selections object.
	 *
	 * @param mixed $value Raw value.
	 * @return array
	 */
	public static function sanitize_selections( $value ) {
		if ( is_string( $value ) ) {
			$decoded = json_decode( $value, true );
			if ( JSON_ERROR_NONE === json_last_error() ) {
				$value = $decoded;
			}
		}

		if ( ! is_array( $value ) ) {
			return array();
		}

		$clean = array();
		foreach ( $value as $decision_id => $option_id ) {
			if ( WPConfigurator_Schema_Store::is_valid_option_id( (string) $decision_id ) && WPConfigurator_Schema_Store::is_valid_option_id( (string) $option_id ) ) {
				$clean[ $decision_id ] = $option_id;
			}
		}

		return $clean;
	}

	/**
	 * Sanitize selected parts list.
	 *
	 * @param mixed $value Parts.
	 * @return array
	 */
	public static function sanitize_selected_parts( $value ) {
		if ( is_string( $value ) ) {
			$decoded = json_decode( $value, true );
			if ( JSON_ERROR_NONE === json_last_error() ) {
				$value = $decoded;
			}
		}

		if ( ! is_array( $value ) ) {
			return array();
		}

		$clean = array();
		foreach ( $value as $part ) {
			$part = sanitize_text_field( (string) $part );
			if ( '' !== $part ) {
				$clean[] = $part;
			}
		}

		return array_values( array_unique( $clean ) );
	}

	/**
	 * Sanitize datetime.
	 *
	 * @param mixed $value Date.
	 * @return string
	 */
	public static function sanitize_timestamp( $value ) {
		$value = sanitize_text_field( (string) $value );
		$time  = strtotime( $value );

		if ( false === $time ) {
			return gmdate( 'c' );
		}

		return gmdate( 'c', $time );
	}
}
