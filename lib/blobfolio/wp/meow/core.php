<?php
/**
 * Apocalypse Meow Core/Template Functions
 *
 * Security actions relating to the core/template settings groups are
 * located here.
 *
 * @package apocalypse-meow
 * @author  Blobfolio, LLC <hello@blobfolio.com>
 */

namespace blobfolio\wp\meow;

use \blobfolio\wp\meow\vendor\common;
use \WP_Error;

class core {

	// The username saved when tracking user enumeration attempts.
	const ENUMERATION_USERNAME = 'enumeration-attempt';


	// -----------------------------------------------------------------
	// Init/Setup
	// -----------------------------------------------------------------

	protected static $_init = false;

	/**
	 * Register Actions
	 *
	 * Almost everything relevant to this category of actions can be
	 * determined once WordPress fires the 'init' hook.
	 *
	 * @return bool True/false.
	 */
	public static function init() {
		// Only need to do this once.
		if (static::$_init) {
			return true;
		}
		static::$_init = true;

		$settings = options::get();
		$class = get_called_class();

		// Disable file editor.
		if ($settings['core']['file_edit'] && !defined('DISALLOW_FILE_EDIT')) {
			define('DISALLOW_FILE_EDIT', true);
		}

		// Disable XML-RPC.
		if ($settings['core']['xmlrpc']) {
			// Disable XML-RPC methods requiring authentication.
			add_filter('xmlrpc_enabled', '__return_false');

			// Clean up a few other stupid things.
			remove_action('wp_head', 'rsd_link');
			add_filter('wp_headers', array($class, 'core_xmlrpc_pingback'));
			add_filter('pings_open', '__return_false', PHP_INT_MAX);
		}

		// Disable adjacent posts.
		if ($settings['template']['adjacent_posts']) {
			add_filter('previous_post_rel_link', '__return_false');
			add_filter('next_post_rel_link', '__return_false');
		}

		// Disable generator.
		if ($settings['template']['generator_tag']) {
			add_filter('the_generator', '__return_false');
		}

		// Remove the readme.html file.
		$readme = trailingslashit(ABSPATH) . 'readme.html';
		if ($settings['template']['readme'] && @file_exists($readme)) {
			@unlink($readme);
		}

		// Enqueue the rel=noopener script. This is hooked into both the
		// front- and backend sites.
		if ($settings['template']['noopener']) {
			add_action('wp_enqueue_scripts', array($class, 'template_noopener'));
			add_action('admin_init', array($class, 'template_noopener'));
		}

		// User Enumeration.
		if ($settings['core']['enumeration']) {
			// Regular request.
			if (isset($_GET['author']) && get_option('permalink_structure')) {
				static::core_enumeration();
			}

			// WP-REST requests.
			add_filter('rest_authentication_errors', array($class, 'core_enumeration_api'), 100);
		}

		// WP-REST access.
		if ('all' !== $settings['core']['wp_rest']) {
			static::core_wp_rest();
		}

		return true;
	}

	// ----------------------------------------------------------------- end init



	// -----------------------------------------------------------------
	// XML-RPC
	// -----------------------------------------------------------------

	/**
	 * Remove XML-RPC Pingback Header
	 *
	 * @param array $headers Headers.
	 * @return array Headers.
	 */
	public static function core_xmlrpc_pingback($headers) {
		if (isset($headers['X-Pingback'])) {
			unset($headers['X-Pingback']);
		}

		return $headers;
	}

	// ----------------------------------------------------------------- end xml-rpc



	// -----------------------------------------------------------------
	// Rel=Noopener
	// -----------------------------------------------------------------

	/**
	 * Enqueue Noopener Script
	 *
	 * This script will run after everything's loaded and add
	 * rel=noopener to links with target=_blank.
	 *
	 * @see {https://github.com/danielstjules/blankshield}
	 *
	 * @return void Nothing.
	 */
	public static function template_noopener() {
		wp_register_script(
			'meow_js_noopener',
			MEOW_PLUGIN_URL . 'js/noopener.min.js',
			array(),
			admin::ASSET_VERSION,
			true
		);
		wp_enqueue_script('meow_js_noopener');
	}

	// ----------------------------------------------------------------- end noopener



	// -----------------------------------------------------------------
	// User Enumeration
	// -----------------------------------------------------------------

	/**
	 * Prevent User Enumeration
	 *
	 * This stops user enumeration attempts for regular HTTP requests.
	 *
	 * @return bool|void True/nothing.
	 */
	public static function core_enumeration() {
		// Enumeration not applicable in these cases.
		if (
			(defined('DOING_CRON') && DOING_CRON) ||
			(defined('DOING_AJAX') && DOING_AJAX) ||
			(defined('WP_CLI') && WP_CLI) ||
			is_admin()
		) {
			return true;
		}

		// Track this as a failure?
		if (options::get('core-enumeration_fail')) {
			login::login_log_fail(static::ENUMERATION_USERNAME);
		}

		// Trigger an error page.
		if (options::get('core-enumeration_die')) {
			wp_die(
				__('Author archives are not accessible by user ID.', 'apocalypse-meow'),
				__('Invalid Request', 'apocalypse-meow'),
				400
			);
		}

		// Otherwise send them to the home page.
		wp_redirect(site_url());
		exit;
	}

	/**
	 * Prevent User Enumeration: WP-REST
	 *
	 * Same as above, but for WP-REST requests.
	 *
	 * @param mixed $access Access.
	 * @return mixed Error or access.
	 */
	public static function core_enumeration_api($access) {
		global $wp;

		$route = isset($wp->query_vars['rest_route']) ? $wp->query_vars['rest_route'] : '';
		if (
			!is_user_logged_in() &&
			$route &&
			preg_match('/\/users\//i', trailingslashit($route))
		) {
			// Track this as a failure?
			if (options::get('core-enumeration_fail')) {
				login::login_log_fail(static::ENUMERATION_USERNAME);
			}

			$access = new WP_Error(
				'rest_access_forbidden_enumeration',
				__('WP-REST user access is disabled.', 'apocalypse-meow'),
				array('status'=>403)
			);
		}

		return $access;
	}

	// ----------------------------------------------------------------- end enumeration



	// -----------------------------------------------------------------
	// WP-REST
	// -----------------------------------------------------------------

	/**
	 * WP-REST init
	 *
	 * There are a bunch of different things that need to happen
	 * depending on the version of WordPress and the setting value.
	 *
	 * @return bool True/false.
	 */
	public static function core_wp_rest() {
		$rest = options::get('core-wp_rest');

		// Default access, no change.
		if ('all' === $rest) {
			return false;
		}

		// Disable it?
		if (('none' === $rest) || !is_user_logged_in()) {
			// Remove meta.
			remove_action('wp_head', 'rest_output_link_wp_head');

			// For versions prior to 4.7, there were hooks!
			if (version_compare(get_bloginfo('version'), '4.7') < 0) {
				// Disable functionality.
				add_filter('json_enabled', '__return_false');
				add_filter('json_jsonp_enabled', '__return_false');
				add_filter('rest_enabled', '__return_false');
				add_filter('rest_jsonp_enabled', '__return_false');

				// Clean up headers.
				remove_action('xmlrpc_rsd_apis', 'rest_output_rsd');
				remove_action('wp_head', 'rest_output_link_wp_head', 10);
				remove_action('template_redirect', 'rest_output_link_header', 11);
			}
			// Otherwise we just need to trigger an authentication error.
			else {
				add_filter('rest_authentication_errors', array(get_called_class(), 'core_wp_rest_none'));
			}

			return true;
		}

		// Users-only.
		add_filter('rest_authentication_errors', array(get_called_class(), 'core_wp_rest_users'));
	}

	/**
	 * WP-REST Access: Nobody
	 *
	 * @param mixed $access Access.
	 * @return WP_Error Error.
	 */
	public static function core_wp_rest_none($access) {
		$access = new WP_Error(
			'rest_access_forbidden_none',
			__('The WP-REST API has been disabled.', 'apocalypse-meow'),
			array('status'=>403)
		);
		return $access;
	}

	/**
	 * WP-REST Access: Users
	 *
	 * @param mixed $access Access.
	 * @return mixed Error or not.
	 */
	public static function core_wp_rest_users($access) {
		if (!is_user_logged_in()) {
			$access = new WP_Error(
				'rest_access_forbidden_users',
				__('The WP-REST API is only available to authenticated users.', 'apocalypse-meow'),
				array('status'=>403)
			);
		}

		return $access;
	}

	// ----------------------------------------------------------------- end wp-rest
}
