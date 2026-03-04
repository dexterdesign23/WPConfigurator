<?php

if (! defined('ABSPATH')) {
    exit;
}

if (! class_exists('PBC_CPT')) {
    class PBC_CPT
    {
        public const POST_TYPE = 'pbc_quote';

        /**
         * Register quote post type.
         */
        public static function register(): void
        {
            register_post_type(
                self::POST_TYPE,
                array(
                    'labels' => array(
                        'name'          => __('Configurator Quotes', 'printer-builder-configurator'),
                        'singular_name' => __('Configurator Quote', 'printer-builder-configurator'),
                    ),
                    'public'             => false,
                    'show_ui'            => true,
                    'show_in_menu'       => true,
                    'supports'           => array('title', 'editor', 'custom-fields'),
                    'capability_type'    => 'post',
                    'map_meta_cap'       => true,
                    'exclude_from_search'=> true,
                    'show_in_rest'       => false,
                )
            );
        }
    }
}
