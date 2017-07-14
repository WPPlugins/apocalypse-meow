<?php
/**
 * Apocalypse Meow - Fallback Bootstrap
 *
 * This is run on environments that do not meet the
 * main plugin requirements. It will either deactivate
 * the plugin (if it has never been active) or provide
 * a semi-functional fallback environment to keep the
 * site from breaking, and suggest downgrading to the
 * legacy version.
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



// ---------------------------------------------------------------------
// Compatibility Checking
// ---------------------------------------------------------------------

// There will be errors. What are they?
$meow_errors = array();

if (version_compare(PHP_VERSION, '5.6.0') < 0) {
	$meow_errors['version'] = __('PHP 5.6.0 or newer is required.', 'apocalypse-meow');
}

if (function_exists('is_multisite') && is_multisite()) {
	$meow_errors['multisite'] = __('This plugin cannot be used on Multi-Site.', 'apocalypse-meow');
}

if (!function_exists('bcmul')) {
	$meow_errors['bcmath'] = __('The bcmath PHP extension is required.', 'apocalypse-meow');
}

// Will downgrading to the legacy version help?
$meow_downgrade = (
	(1 === count($meow_errors)) &&
	isset($meow_errors['version']) &&
	version_compare(PHP_VERSION, '5.4.0') >= 0
);

/**
 * Admin Notice
 *
 * @return bool True/false.
 */
function meow_admin_notice() {
	global $meow_errors;
	global $meow_downgrade;

	if (!is_array($meow_errors) || !count($meow_errors)) {
		return false;
	}
	?>
	<div class="notice notice-error">
		<p><?php
		echo sprintf(
			esc_html__('Your server does not meet the requirements for running %s. You or your system administrator should take a look at the following:', 'apocalypse-meow'),
			'<strong>Apocalypse Meow</strong>'
		);
		?></p>

		<?php
		foreach ($meow_errors as $error) {
			echo '<p>&nbsp;&nbsp;&mdash; ' . esc_html($error) . '</p>';
		}

		// Can we recommend the old version?
		if (isset($meow_errors['disabled'])) {
			unset($meow_errors['disabled']);
		}

		if ($meow_downgrade) {
			echo '<p>' .
			sprintf(
				esc_html__('As a *stopgap*, you can %s the Apocalypse Meow plugin to the legacy *20.x* series. The legacy series *will not* receive updates or development support, so please ultimately plan to remove the plugin or upgrade your server environment.', 'apocalypse-meow'),
				'<a href="' . admin_url('update-core.php') . '">' . esc_html__('downgrade', 'apocalypse-meow') . '</a>'
			) . '</p>';
		}
		?>
	</div>
	<?php
	return true;
}
add_action('admin_notices', 'meow_admin_notice');

/**
 * Self-Deactivate
 *
 * If the environment can't support the plugin and the
 * environment never supported the plugin, simply
 * remove it.
 *
 * @return bool True/false.
 */
function meow_deactivate() {
	// Can't deactivate an MU, and it is friendlier to
	// not deactivate someone's existing install, even
	// if the functionality is disabled.
	if (
		MEOW_MUST_USE ||
		('never' !== get_option('meow_db_version', 'never'))
	) {
		return false;
	}

	require_once(trailingslashit(ABSPATH) . 'wp-admin/includes/plugin.php');
	deactivate_plugins(MEOW_INDEX);

	global $meow_errors;
	global $meow_downgrade;
	$meow_downgrade = false;
	$meow_errors['disabled'] = __('The plugin has been automatically disabled.');

	if (isset($_GET['activate'])) {
		unset($_GET['activate']);
	}

	return true;
}
add_action('admin_init', 'meow_deactivate');

/**
 * Downgrade Update
 *
 * Pretend the legacy version is newer to make it easier
 * for people to downgrade. :)
 *
 * @param StdClass $option Plugin lookup info.
 * @return StdClass Option.
 */
function meow_fake_version($option) {
	global $meow_downgrade;

	// Argument must make sense.
	if (!is_object($option) || !$meow_downgrade) {
		return $option;
	}

	// Set up the entry.
	$path = 'apocalypse-meow/index.php';
	if (!array_key_exists($path, $option->response)) {
		$option->response[$path] = new stdClass();
	}

	// Steal some information from the installed plugin.
	require_once(trailingslashit(ABSPATH) . 'wp-admin/includes/plugin.php');
	$info = get_plugin_data(MEOW_INDEX);

	$option->response[$path]->id = 0;
	$option->response[$path]->slug = 'apocalypse-meow';
	$option->response[$path]->plugin = $path;
	$option->response[$path]->new_version = '2020-legacy';
	$option->response[$path]->url = $info['PluginURI'];
	$option->response[$path]->package = 'https://downloads.wordpress.org/plugin/apocalypse-meow.20.2.0.zip';
	$option->response[$path]->upgrade_notice = __('This will downgrade to the legacy 20.2.0 release, which is compatible with PHP 5.4. Do not upgrade from the legacy version until your server meets the requirements of the current release.', 'apocalypse-meow');

	// And done.
	return $option;
}
add_filter('transient_update_plugins', 'meow_fake_version');
add_filter('site_transient_update_plugins', 'meow_fake_version');

/**
 * Localize
 *
 * @return void Nothing.
 */
function meow_localize() {
	if (MEOW_MUST_USE) {
		load_muplugin_textdomain('apocalypse-meow', basename(MEOW_PLUGIN_DIR) . '/languages');
	}
	else {
		load_plugin_textdomain('apocalypse-meow', false, basename(MEOW_PLUGIN_DIR) . '/languages');
	}
}
add_action('plugins_loaded', 'meow_localize');

// --------------------------------------------------------------------- end compatibility
