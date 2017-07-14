<?php
/**
 * Apocalypse Meow - Bootstrap
 *
 * Set up the environment.
 *
 * @package apocalypse-meow
 * @author  Blobfolio, LLC <hello@blobfolio.com>
 */

/**
 * Do not execute this file directly.
 */
if (!defined('ABSPATH')) {
	exit;
}



// Bootstrap.
// phpab -e "./node_modules/*" -o ./lib/autoload.php .
require_once(MEOW_PLUGIN_DIR . 'lib/autoload.php');

// So many actions!
add_action('admin_enqueue_scripts', array(MEOW_BASE_CLASS . 'admin', 'enqueue_scripts'));
add_action('admin_notices', array(MEOW_BASE_CLASS . 'admin', 'warnings'));
add_action('init', array(MEOW_BASE_CLASS . 'core', 'init'));
add_action('init', array(MEOW_BASE_CLASS . 'login', 'init'));
add_action('plugins_loaded', array(MEOW_BASE_CLASS . 'admin', 'localize'));
add_action('plugins_loaded', array(MEOW_BASE_CLASS . 'admin', 'server_name'));
add_action('plugins_loaded', array(MEOW_BASE_CLASS . 'db', 'check'));
add_action('plugins_loaded', array(MEOW_BASE_CLASS . 'hooks', 'init'));

// WP-CLI functions.
if (defined('WP_CLI') && WP_CLI) {
	require_once(MEOW_PLUGIN_DIR . 'lib/blobfolio/wp/meow/cli.php');
}

// A few things run once at first install.
register_activation_hook(MEOW_INDEX, array(MEOW_BASE_CLASS . 'admin', 'requirements'));
register_activation_hook(MEOW_INDEX, array(MEOW_BASE_CLASS . 'db', 'check'));

// And a couple things we can go ahead and run right away.
\blobfolio\wp\meow\admin::register_menus();
\blobfolio\wp\meow\ajax::init();

// --------------------------------------------------------------------- end setup

