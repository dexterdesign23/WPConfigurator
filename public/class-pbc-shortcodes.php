<?php

if (! defined('ABSPATH')) {
    exit;
}

if (! class_exists('PBC_Shortcodes')) {
    class PBC_Shortcodes
    {
        /**
         * Register plugin shortcodes.
         */
        public static function register(): void
        {
            add_shortcode('printer_builder_configurator', array(__CLASS__, 'render_configurator'));
        }

        /**
         * Render frontend app mount point.
         */
        public static function render_configurator(): string
        {
            return '<div id="pbc-public-app"></div>';
        }
    }
}
