<?php
/**
 * Plugin Name: Gift Hub Engine
 * Description: Programmatically generates evergreen international gift idea pages from local datasets.
 * Version: 1.0.0
 * Author: Gift Hub Team
 * Text Domain: gift-hub-engine
 */

if (! defined('ABSPATH')) {
    exit;
}

define('GHE_VERSION', '1.0.0');
define('GHE_PLUGIN_FILE', __FILE__);
define('GHE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GHE_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once GHE_PLUGIN_DIR . 'includes/class-ghe-post-types.php';
require_once GHE_PLUGIN_DIR . 'includes/class-ghe-importer.php';
require_once GHE_PLUGIN_DIR . 'includes/class-ghe-template.php';
require_once GHE_PLUGIN_DIR . 'includes/class-ghe-cli.php';

function ghe_bootstrap(): void
{
    GHE_Post_Types::register_hooks();
    GHE_Template::register_hooks();

    if (defined('WP_CLI') && WP_CLI) {
        GHE_CLI::register_hooks();
    }
}
add_action('plugins_loaded', 'ghe_bootstrap');

function ghe_activate(): void
{
    GHE_Post_Types::register_content_types();
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'ghe_activate');

function ghe_deactivate(): void
{
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'ghe_deactivate');
