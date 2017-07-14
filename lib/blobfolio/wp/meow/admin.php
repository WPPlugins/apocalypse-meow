<?php
/**
 * Apocalypse Meow options.
 *
 * Admin settings, menus, etc.
 *
 * @package apocalypse-meow
 * @author  Blobfolio, LLC <hello@blobfolio.com>
 */

namespace blobfolio\wp\meow;

use \blobfolio\wp\meow\vendor\common;

class admin {

	const ASSET_VERSION = '20170415';
	const EXTENSIONS = array(
		'bcmath',
		'date',
		'filter',
		'json',
		'pcre',
	);

	protected static $errors = array();

	// ---------------------------------------------------------------------
	// General
	// ---------------------------------------------------------------------

	/**
	 * Requirement Checks
	 *
	 * @return bool True/false.
	 * @throws \Exception Missing requirements.
	 */
	public static function requirements() {
		if (version_compare(PHP_VERSION, '5.6.0') < 0) {
			throw new \Exception(__('PHP 5.6.0 or newer is required.', 'apocalypse-meow'));
		}

		if (function_exists('is_multisite') && is_multisite()) {
			throw new \Exception(__('This plugin cannot be used on Multi-Site.', 'apocalypse-meow'));
		}

		foreach (static::EXTENSIONS as $e) {
			if (!extension_loaded($e)) {
				throw new \Exception(
					sprintf(
						__('This plugin requires the PHP extension %s.', 'apocalypse-meow'),
						$e
					)
				);
			}
		}

		if (!function_exists('hash_algos') || !in_array('sha512', hash_algos(), true)) {
			throw new \Exception(__('PHP must support basic hashing algorithms like SHA512.', 'apocalypse-meow'));
		}

		return true;
	}

	/**
	 * Warnings
	 *
	 * @return bool True/false.
	 */
	public static function warnings() {
		global $pagenow;

		// Only show warnings to administrators, and only on relevant pages.
		if (
			!current_user_can('manage_options') ||
			('plugins.php' !== $pagenow && false === static::current_screen())
		) {
			return true;
		}

		// Requirements.
		try {
			static::requirements();
		} catch (\Throwable $e) {
			static::$errors[] = $e->getMessage();
		} catch (\Exception $e) {
			static::$errors[] = $e->getMessage();
		}

		if (options::get('license') && !options::is_pro()) {
			static::$errors[] = __('The Apocalypse Meow license is not valid for this domain or plugin; premium features have been disabled.', 'apocalypse-meow');
		}

		elseif (options::is_pro() && !extension_loaded('openssl')) {
			static::$errors[] = __('The recommended PHP extension OpenSSL is missing; this will slow down some operations.', 'apocalypse-meow');
		}

		if (!function_exists('idn_to_ascii')) {
			static::$errors[] = __('The recommended PHP extension Intl is missing; you will not be able to handle internationalized or unicode domains.', 'apocalypse-meow');
		}

		// All good!
		if (!count(static::$errors)) {
			return true;
		}

		?>
		<div class="notice notice-error">
			<p><?php
			echo sprintf(
				esc_html__('Your server does not meet the requirements for running %s. You or your system administrator should take a look at the following:'),
				'<strong>Apocalypse Meow</strong>'
			);
			?><br>
			&nbsp;&nbsp;&bullet;&nbsp;&nbsp;<?php echo implode('<br>&nbsp;&nbsp;&bullet;&nbsp;&nbsp;', static::$errors); ?></p>
		</div>
		<?php

		return false;
	}

	/**
	 * Fix Server Name
	 *
	 * WordPress generates its wp_mail() "from" address from
	 * $_SERVER['SERVER_NAME'], which doesn't always exist. This
	 * will generate something to use as a fallback for CLI
	 * instances, etc.
	 *
	 * @return void Nothing.
	 */
	public static function server_name() {
		if (!array_key_exists('SERVER_NAME', $_SERVER)) {
			if (false === $_SERVER['SERVER_NAME'] = common\sanitize::hostname(site_url(), false)) {
				$_SERVER['SERVER_NAME'] = 'localhost';
			}
		}
	}

	/**
	 * Localize
	 *
	 * @return void Nothing.
	 */
	public static function localize() {
		if (MEOW_MUST_USE) {
			load_muplugin_textdomain('apocalypse-meow', basename(MEOW_PLUGIN_DIR) . '/languages');
		}
		else {
			load_plugin_textdomain('apocalypse-meow', false, basename(MEOW_PLUGIN_DIR) . '/languages');
		}
	}

	/**
	 * Current Screen
	 *
	 * The WP Current Screen function isn't ready soon enough
	 * for our needs, so we need to get creative.
	 *
	 * @return bool|string WH screen type or false.
	 */
	public static function current_screen() {
		// Obviously this needs to be an admin page.
		if (!is_admin()) {
			return false;
		}

		// Could be a miscellaneous page.
		if (array_key_exists('page', $_GET)) {
			if (preg_match('/^meow\-/', $_GET['page'])) {
				return $_GET['page'];
			}
		}

		return false;
	}

	/**
	 * Sister Plugins
	 *
	 * Get a list of other plugins by Blobfolio.
	 *
	 * @return array Plugins.
	 */
	public static function sister_plugins() {
		require_once(trailingslashit(ABSPATH) . 'wp-admin/includes/plugin.php');
		require_once(trailingslashit(ABSPATH) . 'wp-admin/includes/plugin-install.php');
		$response = plugins_api(
			'query_plugins',
			array(
				'author'=>'blobfolio',
				'per_page'=>20
			)
		);

		if (!isset($response->plugins) || !is_array($response->plugins)) {
			return array();
		}

		// We want to know whether a plugin is on the system, not
		// necessarily whether it is active.
		$plugin_base = dirname(MEOW_PLUGIN_DIR) . '/';
		$plugins = array();
		foreach ($response->plugins as $p) {
			if ('apocalypse-meow' === $p->slug || file_exists("{$plugin_base}{$p->slug}")) {
				continue;
			}

			$plugins[] = array(
				'name'=>$p->name,
				'slug'=>$p->slug,
				'description'=>$p->short_description,
				'url'=>$p->homepage,
				'version'=>$p->version
			);
		}

		usort(
			$plugins,
			function($a, $b) {
				if ($a['name'] === $b['name']) {
					return 0;
				}

				return $a['name'] > $b['name'] ? 1 : -1;
			}
		);

		return $plugins;
	}

	// --------------------------------------------------------------------- end general



	// ---------------------------------------------------------------------
	// Menus & Pages
	// ---------------------------------------------------------------------

	/**
	 * Register Scripts & Styles
	 *
	 * Register our assets and enqueue some of them maybe.
	 *
	 * @return bool True/false.
	 */
	public static function enqueue_scripts() {
		if (!current_user_can('manage_options')) {
			return false;
		}

		// Find our CSS and JS roots. Easy if this
		// is a regular plugin.
		$js = MEOW_PLUGIN_URL . 'js/';
		$css = MEOW_PLUGIN_URL . 'css/';

		// Dashboard CSS.
		wp_register_style(
			'meow_css_dashboard',
			"{$css}dashboard.css",
			array(),
			static::ASSET_VERSION
		);
		wp_enqueue_style('meow_css_dashboard');

		// The rest is for our pages.
		if (false === ($screen = static::current_screen())) {
			return true;
		}

		// Chartist CSS.
		wp_register_style(
			'meow_css_chartist',
			"{$css}chartist.css",
			array(),
			static::ASSET_VERSION
		);
		if ('meow-stats' === $screen) {
			wp_enqueue_style('meow_css_chartist');
		}

		// Prism CSS.
		wp_register_style(
			'meow_css_prism',
			"{$css}prism.css",
			array(),
			static::ASSET_VERSION
		);
		if (
			options::is_pro() &&
			in_array($screen, array('meow-help', 'meow-settings', 'meow-tools'), true)
		) {
			wp_enqueue_style('meow_css_prism');
		}

		// Main CSS.
		wp_register_style(
			'meow_css',
			"{$css}core.css",
			array(),
			static::ASSET_VERSION
		);
		wp_enqueue_style('meow_css');

		// Chartist JS.
		wp_register_script(
			'meow_js_chartist',
			"{$js}chartist.min.js",
			array('meow_js_vue'),
			static::ASSET_VERSION,
			true
		);

		// Clipboard JS.
		wp_register_script(
			'meow_js_clipboard',
			"{$js}clipboard.min.js",
			array(),
			static::ASSET_VERSION,
			true
		);

		// Prism JS.
		wp_register_script(
			'meow_js_prism',
			"{$js}prism.min.js",
			array('meow_js_clipboard'),
			static::ASSET_VERSION,
			true
		);

		// Vue JS.
		wp_register_script(
			'meow_js_vue',
			"{$js}vue.min.js",
			array('jquery'),
			static::ASSET_VERSION,
			true
		);

		// Pro JS.
		wp_register_script(
			'meow_js_pro',
			"{$js}core-pro.min.js",
			array(
				'meow_js_vue'
			),
			static::ASSET_VERSION,
			true
		);
		if ('meow-pro' === $screen) {
			wp_enqueue_script('meow_js_pro');
		}

		// Activity JS.
		wp_register_script(
			'meow_js_activity',
			"{$js}core-activity.min.js",
			array(
				'meow_js_vue'
			),
			static::ASSET_VERSION,
			true
		);
		if ('meow-activity' === $screen) {
			wp_enqueue_script('meow_js_activity');
		}

		// Help JS.
		wp_register_script(
			'meow_js_help',
			"{$js}core-help.min.js",
			array(
				'meow_js_vue',
				'meow_js_prism'
			),
			static::ASSET_VERSION,
			true
		);
		if ('meow-help' === $screen) {
			wp_enqueue_script('meow_js_help');
		}

		// Settings JS.
		wp_register_script(
			'meow_js_settings',
			"{$js}core-settings.min.js",
			array(
				'meow_js_vue',
				'meow_js_prism'
			),
			static::ASSET_VERSION,
			true
		);
		if ('meow-settings' === $screen) {
			wp_enqueue_script('meow_js_settings');
		}

		// Stats JS.
		wp_register_script(
			'meow_js_stats',
			"{$js}core-stats.min.js",
			array(
				'meow_js_vue',
				'meow_js_chartist'
			),
			static::ASSET_VERSION,
			true
		);
		if ('meow-stats' === $screen) {
			wp_enqueue_script('meow_js_stats');
		}

		// Tools JS.
		wp_register_script(
			'meow_js_tools',
			"{$js}core-tools.min.js",
			array(
				'meow_js_vue',
				'meow_js_prism',
			),
			static::ASSET_VERSION,
			true
		);
		if ('meow-tools' === $screen) {
			wp_enqueue_script('meow_js_tools');
		}

		return true;
	}

	/**
	 * Register Menus
	 *
	 * @return void Nothing.
	 */
	public static function register_menus() {
		$pages = array(
			'settings',
			'activity',
			'stats',
			'tools',
			'help',
			'pro',
			'rename',
		);
		$class = get_called_class();

		foreach ($pages as $page) {
			add_action('admin_menu', array($class, "{$page}_menu"));
		}

		// Register plugins page quick links if we aren't running in
		// Must-Use mode.
		if (!MEOW_MUST_USE) {
			add_filter('plugin_action_links_' . plugin_basename(MEOW_INDEX), array($class, 'plugin_action_links'));
		}
	}

	/**
	 * Settings Menu
	 *
	 * @return bool True/false.
	 */
	public static function settings_menu() {
		// Send settings.
		add_menu_page(
			__('Settings', 'apocalypse-meow'),
			__('Settings', 'apocalypse-meow'),
			'manage_options',
			'meow-settings',
			array(get_called_class(), 'settings_page'),
			'dashicons-meow'
		);

		return true;
	}

	/**
	 * Settings Pages
	 *
	 * @return bool True/false.
	 */
	public static function settings_page() {
		require_once(MEOW_PLUGIN_DIR . 'admin/settings.php');
		return true;
	}

	/**
	 * Activity Menu
	 *
	 * @return bool True/false.
	 */
	public static function activity_menu() {
		add_submenu_page(
			'meow-settings',
			__('Login Activity', 'apocalypse-meow'),
			__('Login Activity', 'apocalypse-meow'),
			'manage_options',
			'meow-activity',
			array(get_called_class(), 'activity_page')
		);

		return true;
	}

	/**
	 * Activity Page
	 *
	 * @return bool True/false.
	 */
	public static function activity_page() {
		require_once(MEOW_PLUGIN_DIR . 'admin/activity.php');
		return true;
	}

	/**
	 * Reference Menu
	 *
	 * @return bool True/false.
	 */
	public static function help_menu() {
		// This is only available to Pro users.
		if (!options::is_pro()) {
			return false;
		}

		add_submenu_page(
			'meow-settings',
			__('Reference', 'apocalypse-meow'),
			__('Reference', 'apocalypse-meow'),
			'manage_options',
			'meow-help',
			array(get_called_class(), 'help_page')
		);

		return true;
	}

	/**
	 * Reference Page
	 *
	 * @return bool True/false.
	 */
	public static function help_page() {
		require_once(MEOW_PLUGIN_DIR . 'admin/help.php');
		return true;
	}

	/**
	 * Stats Menu
	 *
	 * @return bool True/false.
	 */
	public static function stats_menu() {
		// This is only available to Pro users.
		if (!options::is_pro()) {
			return false;
		}

		add_submenu_page(
			'meow-settings',
			__('Login Stats', 'apocalypse-meow'),
			__('Login Stats', 'apocalypse-meow'),
			'manage_options',
			'meow-stats',
			array(get_called_class(), 'stats_page')
		);

		return true;
	}

	/**
	 * Stats Page
	 *
	 * @return bool True/false.
	 */
	public static function stats_page() {
		require_once(MEOW_PLUGIN_DIR . 'admin/stats.php');
		return true;
	}

	/**
	 * Tools
	 *
	 * @return bool True/false.
	 */
	public static function tools_menu() {
		// This is only available to Pro users.
		if (!options::is_pro()) {
			return false;
		}

		add_submenu_page(
			'meow-settings',
			__('Tools', 'apocalypse-meow'),
			__('Tools', 'apocalypse-meow'),
			'manage_options',
			'meow-tools',
			array(get_called_class(), 'tools_page')
		);

		return true;
	}

	/**
	 * Tools Page
	 *
	 * @return bool True/false.
	 */
	public static function tools_page() {
		require_once(MEOW_PLUGIN_DIR . 'admin/tools.php');
		return true;
	}

	/**
	 * Pro License
	 *
	 * @return bool True/false.
	 */
	public static function pro_menu() {
		add_submenu_page(
			'meow-settings',
			__('Pro License', 'apocalypse-meow'),
			__('Pro License', 'apocalypse-meow'),
			'manage_options',
			'meow-pro',
			array(get_called_class(), 'pro_page')
		);

		return true;
	}

	/**
	 * Pro Page
	 *
	 * @return bool True/false.
	 */
	public static function pro_page() {
		require_once(MEOW_PLUGIN_DIR . 'admin/pro.php');
		return true;
	}

	/**
	 * Rename Menu
	 *
	 * We want to change the main menu name but leave the main submenu
	 * link as is. This requires a bit of a hack after the menu has
	 * been populated.
	 *
	 * @return void Nothing.
	 */
	public static function rename_menu() {
		global $menu;
		$tmp = array_reverse($menu, true);
		foreach ($tmp as $k=>$v) {
			if (!is_array($v) || count($v) < 3) {
				continue;
			}
			if (isset($v[2]) && ('meow-settings' === $v[2])) {
				$menu[$k][0] = 'Apocalypse Meow';
				break;
			}
		}
		unset($tmp);
	}

	/**
	 * Plugin Links
	 *
	 * Add some quick links to the entry on the plugins page.
	 *
	 * @param array $links Links.
	 * @return array Links.
	 */
	public static function plugin_action_links($links) {
		// Settings.
		$links[] = '<a href="' . esc_url(admin_url('admin.php?page=meow-settings')) . '">' . esc_html__('Settings', 'apocalypse-meow') . '</a>';

		// Activity.
		$links[] = '<a href="' . esc_url(admin_url('admin.php?page=meow-activity')) . '">' . esc_html__('Activity', 'apocalypse-meow') . '</a>';

		// Tools.
		if (options::is_pro()) {
			$links[] = '<a href="' . esc_url(admin_url('admin.php?page=meow-tools')) . '">' . esc_html__('Tools', 'apocalypse-meow') . '</a>';
		}
		// Pro.
		else {
			$links[] = '<a href="' . esc_url(admin_url('admin.php?page=meow-pro')) . '">' . esc_html__('Enter License', 'apocalypse-meow') . '</a>';
		}

		return $links;
	}

	// --------------------------------------------------------------------- end menus

}
