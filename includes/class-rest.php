<?php
/**
 * REST endpoints for schema and build operations.
 *
 * @package WPConfigurator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPConfigurator_REST {
	const NAMESPACE = 'printer-config/v1';

	/**
	 * Bootstrap routes.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Register all REST routes.
	 *
	 * @return void
	 */
	public static function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			'/schema',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_schema' ),
					'permission_callback' => '__return_true',
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/schema',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'update_schema' ),
					'permission_callback' => array( __CLASS__, 'can_manage_schema' ),
					'args'                => array(
						'schema' => array(
							'required'          => true,
							'type'              => 'object',
							'sanitize_callback' => array( __CLASS__, 'sanitize_schema_arg' ),
							'validate_callback' => array( __CLASS__, 'validate_schema_arg' ),
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/schema/import',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'import_schema' ),
					'permission_callback' => array( __CLASS__, 'can_manage_schema' ),
					'args'                => array(
						'payload' => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'wp_kses_post',
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/schema/export',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'export_schema' ),
					'permission_callback' => array( __CLASS__, 'can_manage_schema' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/builds',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'list_builds' ),
					'permission_callback' => array( __CLASS__, 'can_access_builds' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'create_build' ),
					'permission_callback' => array( __CLASS__, 'can_mutate_builds' ),
					'args'                => self::build_args_schema(),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/builds/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_build' ),
					'permission_callback' => array( __CLASS__, 'can_access_single_build' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( __CLASS__, 'update_build' ),
					'permission_callback' => array( __CLASS__, 'can_mutate_single_build' ),
					'args'                => self::build_args_schema(),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( __CLASS__, 'delete_build' ),
					'permission_callback' => array( __CLASS__, 'can_mutate_single_build' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/builds/(?P<id>\d+)/duplicate',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'duplicate_build' ),
					'permission_callback' => array( __CLASS__, 'can_mutate_single_build' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/builds/(?P<id>\d+)/set-active',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'set_active_build' ),
					'permission_callback' => array( __CLASS__, 'can_mutate_single_build' ),
				),
			)
		);
	}

	public static function get_schema() {
		return rest_ensure_response( WPConfigurator_Schema_Store::get() );
	}

	public static function update_schema( WP_REST_Request $request ) {
		$nonce_error = self::verify_nonce_or_error( $request );
		if ( is_wp_error( $nonce_error ) ) {
			return $nonce_error;
		}
		$result = WPConfigurator_Schema_Store::save( $request->get_param( 'schema' ) );
		return self::ensure_result( $result );
	}

	public static function import_schema( WP_REST_Request $request ) {
		$nonce_error = self::verify_nonce_or_error( $request );
		if ( is_wp_error( $nonce_error ) ) {
			return $nonce_error;
		}
		$result = WPConfigurator_Schema_Store::import( $request->get_param( 'payload' ) );
		return self::ensure_result( $result );
	}

	public static function export_schema() {
		$result = WPConfigurator_Schema_Store::export();
		return self::ensure_result( is_wp_error( $result ) ? $result : array( 'payload' => $result ) );
	}

	public static function list_builds() {
		$query = new WP_Query(
			array(
				'post_type'      => WPConfigurator_Build_CPT::POST_TYPE,
				'post_status'    => array( 'publish', 'private', 'draft' ),
				'author'         => get_current_user_id(),
				'posts_per_page' => 100,
			)
		);

		$data = array_map( array( __CLASS__, 'format_build' ), $query->posts );
		return rest_ensure_response( $data );
	}

	public static function create_build( WP_REST_Request $request ) {
		$nonce_error = self::verify_nonce_or_error( $request );
		if ( is_wp_error( $nonce_error ) ) {
			return $nonce_error;
		}
		$postarr = array(
			'post_type'   => WPConfigurator_Build_CPT::POST_TYPE,
			'post_status' => 'publish',
			'post_author' => get_current_user_id(),
			'post_title'  => $request->get_param( 'build_name' ) ? sanitize_text_field( $request->get_param( 'build_name' ) ) : __( 'Untitled Build', 'wpconfigurator' ),
		);

		$post_id = wp_insert_post( $postarr, true );
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		self::persist_build_meta( $post_id, $request );
		return rest_ensure_response( self::format_build( get_post( $post_id ) ) );
	}

	public static function get_build( WP_REST_Request $request ) {
		$post = get_post( (int) $request['id'] );
		return rest_ensure_response( self::format_build( $post ) );
	}

	public static function update_build( WP_REST_Request $request ) {
		$nonce_error = self::verify_nonce_or_error( $request );
		if ( is_wp_error( $nonce_error ) ) {
			return $nonce_error;
		}
		$post_id = (int) $request['id'];

		if ( null !== $request->get_param( 'build_name' ) ) {
			wp_update_post(
				array(
					'ID'         => $post_id,
					'post_title' => sanitize_text_field( $request->get_param( 'build_name' ) ),
				)
			);
		}

		self::persist_build_meta( $post_id, $request );
		return rest_ensure_response( self::format_build( get_post( $post_id ) ) );
	}

	public static function delete_build( WP_REST_Request $request ) {
		$nonce_error = self::verify_nonce_or_error( $request );
		if ( is_wp_error( $nonce_error ) ) {
			return $nonce_error;
		}
		$deleted = wp_delete_post( (int) $request['id'], true );
		if ( ! $deleted ) {
			return new WP_Error( 'build_delete_failed', __( 'Build could not be deleted.', 'wpconfigurator' ), array( 'status' => 500 ) );
		}
		return rest_ensure_response( array( 'deleted' => true ) );
	}

	public static function duplicate_build( WP_REST_Request $request ) {
		$nonce_error = self::verify_nonce_or_error( $request );
		if ( is_wp_error( $nonce_error ) ) {
			return $nonce_error;
		}
		$source = get_post( (int) $request['id'] );
		$post_id = wp_insert_post(
			array(
				'post_type'   => WPConfigurator_Build_CPT::POST_TYPE,
				'post_status' => 'publish',
				'post_author' => get_current_user_id(),
				'post_title'  => sprintf( __( '%s (Copy)', 'wpconfigurator' ), $source->post_title ),
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		foreach ( array( 'build_name', 'selections', 'selected_parts', 'schema_version' ) as $meta_key ) {
			update_post_meta( $post_id, $meta_key, get_post_meta( $source->ID, $meta_key, true ) );
		}
		update_post_meta( $post_id, 'updated_at', gmdate( 'c' ) );

		return rest_ensure_response( self::format_build( get_post( $post_id ) ) );
	}

	public static function set_active_build( WP_REST_Request $request ) {
		$nonce_error = self::verify_nonce_or_error( $request );
		if ( is_wp_error( $nonce_error ) ) {
			return $nonce_error;
		}
		update_user_meta( get_current_user_id(), 'wpconfigurator_active_build_id', (int) $request['id'] );
		return rest_ensure_response( array( 'active_build_id' => (int) $request['id'] ) );
	}

	protected static function persist_build_meta( $post_id, WP_REST_Request $request ) {
		if ( null !== $request->get_param( 'build_name' ) ) {
			update_post_meta( $post_id, 'build_name', sanitize_text_field( $request->get_param( 'build_name' ) ) );
		}
		if ( null !== $request->get_param( 'selections' ) ) {
			update_post_meta( $post_id, 'selections', WPConfigurator_Build_CPT::sanitize_selections( $request->get_param( 'selections' ) ) );
		}
		if ( null !== $request->get_param( 'selected_parts' ) ) {
			update_post_meta( $post_id, 'selected_parts', WPConfigurator_Build_CPT::sanitize_selected_parts( $request->get_param( 'selected_parts' ) ) );
		}
		if ( null !== $request->get_param( 'schema_version' ) ) {
			update_post_meta( $post_id, 'schema_version', absint( $request->get_param( 'schema_version' ) ) );
		}
		update_post_meta( $post_id, 'updated_at', gmdate( 'c' ) );
	}

	protected static function format_build( $post ) {
		if ( ! ( $post instanceof WP_Post ) ) {
			return array();
		}

		return array(
			'id'             => (int) $post->ID,
			'build_name'     => get_post_meta( $post->ID, 'build_name', true ),
			'selections'     => get_post_meta( $post->ID, 'selections', true ),
			'selected_parts' => get_post_meta( $post->ID, 'selected_parts', true ),
			'schema_version' => (int) get_post_meta( $post->ID, 'schema_version', true ),
			'updated_at'     => get_post_meta( $post->ID, 'updated_at', true ),
			'is_active'      => (int) get_user_meta( get_current_user_id(), 'wpconfigurator_active_build_id', true ) === (int) $post->ID,
		);
	}

	public static function can_manage_schema() {
		return current_user_can( 'manage_options' );
	}

	public static function can_access_builds() {
		return is_user_logged_in();
	}

	public static function can_mutate_builds( WP_REST_Request $request ) {
		return is_user_logged_in() && self::verify_nonce( $request );
	}

	public static function can_access_single_build( WP_REST_Request $request ) {
		$post = get_post( (int) $request['id'] );
		return self::is_owned_build( $post );
	}

	public static function can_mutate_single_build( WP_REST_Request $request ) {
		$post = get_post( (int) $request['id'] );
		return self::is_owned_build( $post ) && self::verify_nonce( $request );
	}

	protected static function is_owned_build( $post ) {
		return is_user_logged_in() && $post instanceof WP_Post && WPConfigurator_Build_CPT::POST_TYPE === $post->post_type && (int) $post->post_author === get_current_user_id();
	}

	protected static function verify_nonce( WP_REST_Request $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( empty( $nonce ) ) {
			$nonce = $request->get_header( 'x_wp_nonce' );
		}
		return ! empty( $nonce ) && wp_verify_nonce( $nonce, 'wp_rest' );
	}

	protected static function verify_nonce_or_error( WP_REST_Request $request ) {
		if ( ! self::verify_nonce( $request ) ) {
			return new WP_Error( 'invalid_nonce', __( 'Invalid or missing REST nonce.', 'wpconfigurator' ), array( 'status' => 403 ) );
		}

		return true;
	}

	public static function sanitize_schema_arg( $value ) {
		return is_array( $value ) ? $value : array();
	}

	public static function validate_schema_arg( $value ) {
		return WPConfigurator_Schema_Store::validate_schema( $value );
	}

	protected static function ensure_result( $result ) {
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( $result );
	}

	protected static function build_args_schema() {
		return array(
			'build_name' => array(
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'selections' => array(
				'type'              => 'object',
				'required'          => false,
				'sanitize_callback' => array( WPConfigurator_Build_CPT::class, 'sanitize_selections' ),
				'validate_callback' => array( __CLASS__, 'validate_selections_arg' ),
			),
			'selected_parts' => array(
				'type'              => 'array',
				'required'          => false,
				'sanitize_callback' => array( WPConfigurator_Build_CPT::class, 'sanitize_selected_parts' ),
			),
			'schema_version' => array(
				'type'              => 'integer',
				'required'          => false,
				'sanitize_callback' => 'absint',
			),
		);
	}

	public static function validate_selections_arg( $value ) {
		if ( ! is_array( $value ) ) {
			return false;
		}

		foreach ( $value as $decision_id => $option_id ) {
			if ( ! WPConfigurator_Schema_Store::is_valid_option_id( (string) $decision_id ) || ! WPConfigurator_Schema_Store::is_valid_option_id( (string) $option_id ) ) {
				return false;
			}
		}

		return true;
	}
}
