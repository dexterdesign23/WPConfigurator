<?php
/**
 * Plugin Name: Printer Builder Configurator
 * Plugin URI: https://example.com/printer-builder-configurator
 * Description: Configurable printer builder with schema-driven rules and quote workflow.
 * Version: 0.1.0
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author: WPConfigurator
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: printer-builder-configurator
 */

if (! defined('ABSPATH')) {
    exit;
}

if (! defined('PBC_PLUGIN_FILE')) {
    define('PBC_PLUGIN_FILE', __FILE__);
}

if (! defined('PBC_PLUGIN_BASENAME')) {
    define('PBC_PLUGIN_BASENAME', plugin_basename(__FILE__));
}

if (! defined('PBC_PLUGIN_DIR')) {
    define('PBC_PLUGIN_DIR', plugin_dir_path(__FILE__));
}

if (! defined('PBC_PLUGIN_URL')) {
    define('PBC_PLUGIN_URL', plugin_dir_url(__FILE__));
}

if (! defined('PBC_VERSION')) {
    define('PBC_VERSION', '0.1.0');
}

$includes = array(
    'includes/class-pbc-schema-store.php',
    'includes/class-pbc-rule-engine.php',
    'includes/class-pbc-cpt.php',
    'includes/class-pbc-rest-routes.php',
    'admin/class-pbc-admin.php',
    'public/class-pbc-shortcodes.php',
);

foreach ($includes as $include) {
    $path = PBC_PLUGIN_DIR . $include;

    if (file_exists($path)) {
        require_once $path;
    }
}

/**
 * Activation callback.
 */
function pbc_activate(): void
{
    if (class_exists('PBC_Schema_Store')) {
        PBC_Schema_Store::seed_default_schema();
    }
}
register_activation_hook(PBC_PLUGIN_FILE, 'pbc_activate');

/**
 * Register post types.
 */
function pbc_register_cpt(): void
{
    if (class_exists('PBC_CPT')) {
        PBC_CPT::register();
    }
}
add_action('init', 'pbc_register_cpt');

/**
 * Register public shortcodes.
 */
function pbc_register_shortcodes(): void
{
    if (class_exists('PBC_Shortcodes')) {
        PBC_Shortcodes::register();
    }
}
add_action('init', 'pbc_register_shortcodes');

/**
 * Register admin menu.
 */
function pbc_register_admin_page(): void
{
    if (class_exists('PBC_Admin')) {
        PBC_Admin::register_menu();
    }
}
add_action('admin_menu', 'pbc_register_admin_page');

/**
 * Register REST routes.
 */
function pbc_register_rest_routes(): void
{
    if (class_exists('PBC_REST_Routes')) {
        PBC_REST_Routes::register();
    }
}
add_action('rest_api_init', 'pbc_register_rest_routes');
