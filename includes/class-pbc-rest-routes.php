<?php

if (! defined('ABSPATH')) {
    exit;
}

if (! class_exists('PBC_REST_Routes')) {
    class PBC_REST_Routes
    {
        public const ROUTE_NAMESPACE = 'pbc/v1';

        /**
         * Register plugin routes.
         */
        public static function register(): void
        {
            register_rest_route(
                self::ROUTE_NAMESPACE,
                '/schema',
                array(
                    array(
                        'methods'             => WP_REST_Server::READABLE,
                        'callback'            => array(__CLASS__, 'get_schema'),
                        'permission_callback' => '__return_true',
                    ),
                    array(
                        'methods'             => WP_REST_Server::EDITABLE,
                        'callback'            => array(__CLASS__, 'update_schema'),
                        'permission_callback' => array(__CLASS__, 'can_manage_options'),
                    ),
                )
            );
        }

        /**
         * Read schema endpoint.
         */
        public static function get_schema(): WP_REST_Response
        {
            return rest_ensure_response(PBC_Schema_Store::get_schema());
        }

        /**
         * Update schema endpoint.
         */
        public static function update_schema(WP_REST_Request $request): WP_REST_Response
        {
            $params = $request->get_json_params();
            $schema = is_array($params) ? $params : array();

            PBC_Schema_Store::save_schema($schema);

            return rest_ensure_response(
                array(
                    'success' => true,
                    'schema'  => PBC_Schema_Store::get_schema(),
                )
            );
        }

        /**
         * Permissions callback.
         */
        public static function can_manage_options(): bool
        {
            return current_user_can('manage_options');
        }
    }
}
