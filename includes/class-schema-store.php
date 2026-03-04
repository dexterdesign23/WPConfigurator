<?php
/**
 * Schema persistence helper.
 *
 * @package WPConfigurator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPConfigurator_Schema_Store {
	const OPTION_NAME     = 'wpconfigurator_schema';
	const CURRENT_VERSION = 1;
	const OPTION_ID_REGEX = '/^[0-9A-F]{3}-[0-9A-F]{3}-[0-9A-F]{3}$/';

	/**
	 * Get current schema payload.
	 *
	 * @return array
	 */
	public static function get() {
		$stored = get_option( self::OPTION_NAME, array() );

		if ( ! is_array( $stored ) ) {
			return self::empty_payload();
		}

		$defaults = self::empty_payload();
		$payload  = wp_parse_args( $stored, $defaults );

		if ( ! self::validate_schema( $payload['schema'] ) ) {
			return $defaults;
		}

		return $payload;
	}

	/**
	 * Persist schema and metadata.
	 *
	 * @param array $schema Schema definition.
	 * @return array|WP_Error
	 */
	public static function save( $schema ) {
		if ( ! self::validate_schema( $schema ) ) {
			return new WP_Error( 'invalid_schema', __( 'Invalid schema payload.', 'wpconfigurator' ), array( 'status' => 400 ) );
		}

		$json = wp_json_encode( $schema );
		if ( false === $json ) {
			return new WP_Error( 'schema_encode_failed', __( 'Could not encode schema.', 'wpconfigurator' ), array( 'status' => 500 ) );
		}

		$payload = array(
			'version'      => self::CURRENT_VERSION,
			'schema'       => $schema,
			'hash'         => hash( 'sha256', $json ),
			'updated_at'   => gmdate( 'c' ),
			'option_count' => self::count_options( $schema ),
		);

		update_option( self::OPTION_NAME, $payload, false );

		return $payload;
	}

	/**
	 * Export payload.
	 *
	 * @return string|WP_Error
	 */
	public static function export() {
		$payload = self::get();
		$json    = wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

		if ( false === $json ) {
			return new WP_Error( 'schema_export_failed', __( 'Could not export schema.', 'wpconfigurator' ), array( 'status' => 500 ) );
		}

		return $json;
	}

	/**
	 * Import payload or bare schema JSON.
	 *
	 * @param string $raw_json Raw JSON.
	 * @return array|WP_Error
	 */
	public static function import( $raw_json ) {
		$data = json_decode( (string) $raw_json, true );

		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $data ) ) {
			return new WP_Error( 'invalid_json', __( 'Invalid JSON payload.', 'wpconfigurator' ), array( 'status' => 400 ) );
		}

		$schema = isset( $data['schema'] ) ? $data['schema'] : $data;

		return self::save( $schema );
	}

	/**
	 * Validate schema shape.
	 *
	 * @param array $schema Schema.
	 * @return bool
	 */
	public static function validate_schema( $schema ) {
		if ( ! is_array( $schema ) || empty( $schema ) ) {
			return false;
		}

		if ( empty( $schema['decisions'] ) || ! is_array( $schema['decisions'] ) ) {
			return false;
		}

		foreach ( $schema['decisions'] as $decision ) {
			if ( ! is_array( $decision ) || empty( $decision['id'] ) || empty( $decision['options'] ) || ! is_array( $decision['options'] ) ) {
				return false;
			}

			if ( ! self::is_valid_option_id( $decision['id'] ) ) {
				return false;
			}

			foreach ( $decision['options'] as $option ) {
				if ( ! is_array( $option ) || empty( $option['id'] ) ) {
					return false;
				}

				if ( ! self::is_valid_option_id( $option['id'] ) ) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Validate option/decision IDs.
	 *
	 * @param string $id Identifier.
	 * @return bool
	 */
	public static function is_valid_option_id( $id ) {
		return is_string( $id ) && 1 === preg_match( self::OPTION_ID_REGEX, $id );
	}

	/**
	 * Empty payload defaults.
	 *
	 * @return array
	 */
	protected static function empty_payload() {
		return array(
			'version'      => self::CURRENT_VERSION,
			'schema'       => array( 'decisions' => array() ),
			'hash'         => '',
			'updated_at'   => '',
			'option_count' => 0,
		);
	}

	/**
	 * Count all options in schema.
	 *
	 * @param array $schema Schema.
	 * @return int
	 */
	protected static function count_options( $schema ) {
		$total = 0;

		if ( empty( $schema['decisions'] ) || ! is_array( $schema['decisions'] ) ) {
			return $total;
		}

		foreach ( $schema['decisions'] as $decision ) {
			if ( ! empty( $decision['options'] ) && is_array( $decision['options'] ) ) {
				$total += count( $decision['options'] );
			}
		}

		return $total;
	}
}
