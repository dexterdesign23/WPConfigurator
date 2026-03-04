<?php

if (! defined('ABSPATH')) {
    exit;
}

if (! class_exists('PBC_Admin')) {
    class PBC_Admin
    {
        /**
         * Register plugin menu page.
         */
        public static function register_menu(): void
        {
            add_menu_page(
                __('Printer Configurator', 'printer-builder-configurator'),
                __('Printer Configurator', 'printer-builder-configurator'),
                'manage_options',
                'pbc-configurator',
                array(__CLASS__, 'render_page'),
                'dashicons-admin-generic',
                58
            );
        }

        /**
         * Render admin app mount point.
         */
        public static function render_page(): void
        {
            echo '<div class="wrap"><h1>' . esc_html__('Printer Configurator', 'printer-builder-configurator') . '</h1>';
            echo '<div id="pbc-admin-app"></div></div>';
        }
    }
}
