<?php
/**
 * Apocalypse Meow options.
 *
 * An options wrapper.
 *
 * @package apocalypse-meow
 * @author  Blobfolio, LLC <hello@blobfolio.com>
 */

namespace blobfolio\wp\meow;

use \blobfolio\wp\meow\vendor\common;

class options {

	const OPTION_NAME = 'meow_options';
	const OPTIONS = array(
		// Core settings.
		'core'=>array(
			'enumeration'=>false,		// Try to stop user enumeration.
			'enumeration_die'=>false,	// Die on attempt.
			'enumeration_fail'=>true,	// Count enumeration attempts as login failures.
			'file_edit'=>false,			// Disable file editor.
			'wp_rest'=>'all',			// WP-REST API access.
			'xmlrpc'=>false,			// Disable xmlrpc.
		),

		// Pro license.
		'license'=>'',

		// Login settings.
		'login'=>array(
			'alert_by_subnet'=>true,	// Use subnet to determine newness.
			'alert_on_new'=>true,		// Alert on new login.
			'community'=>false,			// Community pooling.
			'exempt'=>array(),			// Exempt IPs.
			'fail_limit'=>5,			// Max fails.
			'fail_window'=>43200,		// Fail window.
			'key'=>'REMOTE_ADDR',		// Where in $_SERVER to find IP.
			'nonce'=>false,				// Add a nonce to login form.
			'reset_on_success'=>true,	// Reset fail count on success.
			'subnet_fail_limit'=>20,	// Limit to ban whole subnet.
		),

		// Password strength requirements.
		'password'=>array(
			'alpha'=>'required',		// Require letters.
			'length'=>10,				// Min length.
			'numeric'=>'required',		// Require numbers.
			'symbol'=>'optional',		// Require symbols.
		),

		// Prune db after X days.
		'prune'=>array(
			'active'=>true,
			'limit'=>90,				// Clear after X days.
		),

		// Template settings.
		'template'=>array(
			'adjacent_posts'=>true,		// Remove previous/next post tags.
			'generator_tag'=>true,		// Remove generator tag.
			'noopener'=>false,			// Add rel="noopener" to vulnerable links.
			'readme'=>false,			// Remove readme file.
		)
	);

	// The minimum minimum.
	const MIN_PASSWORD_LENGTH = 10;
	const MIN_PASSWORD_CHARS = 4;

	// Once upon a time, settings were saved to separate options.
	const OLD_OPTIONS = array(
		'meow_alerts'=>'login-alert_on_new',
		'meow_apocalypse_content'=>'',
		'meow_apocalypse_title'=>'',
		'meow_clean_database'=>'prune-active',
		'meow_data_expiration'=>'prune-limit',
		'meow_disable_editor'=>'core-file_edit',
		'meow_disable_xmlrpc'=>'core-xmlrpc',
		'meow_fail_limit'=>'login-fail_limit',
		'meow_fail_reset_on_success'=>'login-reset_on_success',
		'meow_fail_window'=>'login-fail_window',
		'meow_ip_exempt'=>'login-exempt',
		'meow_ip_key'=>'login-key',
		'meow_login_nonce'=>'login-nonce',
		'meow_password_alpha'=>'password-alpha',
		'meow_password_length'=>'password-length',
		'meow_password_numeric'=>'password-numeric',
		'meow_password_symbol'=>'password-symbol',
		'meow_protect_login'=>'',
		'meow_remove_adjacent_posts_tag'=>'template-adjacent_posts',
		'meow_remove_generator_tag'=>'template-generator_tag',
		'meow_store_ua'=>'',
	);

	// Constants didn't always follow a consistent structure.
	const OLD_CONSTANTS = array(
		'core'=>array(
			'wp_rest'=>'MEOW_API_ACCESS',
			'xmlrpc'=>'MEOW_DISABLE_XMLRPC',
		),
		'prune'=>array(
			'active'=>'MEOW_CLEAN_DATABASE',
			'limit'=>'MEOW_DATA_EXPIRATION',
		),
		'login'=>array(
			'alert_on_new'=>'MEOW_ALERTS',
			'fail_limit'=>'MEOW_FAIL_LIMIT',
			'fail_window'=>'MEOW_FAIL_WINDOW',
			'reset_on_success'=>'MEOW_FAIL_RESET_ON_SUCCESS',
		),
		'template'=>array(
			'adjacent_posts'=>'MEOW_REMOVE_ADJACENT_POSTS_TAG',
			'generator_tag'=>'MEOW_REMOVE_GENERATOR_TAG',
		)
	);

	// Misc enum settings.
	const API_ACCESS = array('all','users','none');
	const PASSWORD_ALPHA = array('optional','required','required-both');
	const PASSWORD_NUMERIC = array('optional','required');

	protected static $options;
	protected static $readonly;
	protected static $pro;



	/**
	 * Load Options
	 *
	 * @param bool $refresh Refresh.
	 * @return bool True/false.
	 */
	public static function load($refresh=false) {
		if (is_null(static::$options) || $refresh) {
			// Nothing saved yet? Or maybe an older version?
			if (false === (static::$options = get_option(static::OPTION_NAME, false))) {
				static::$options = static::OPTIONS;
				foreach (static::OLD_OPTIONS as $k=>$v) {
					if ('notfound' !== ($option = get_option($k, 'notfound'))) {
						list($a, $b) = explode('-', $v);
						static::$options[$a][$b] = $option;
						delete_option($k);
					}
				}
				static::$options = common\data::parse_args(static::$options, static::OPTIONS);
				update_option(static::OPTION_NAME, static::$options);
			}

			// Before.
			$before = md5(json_encode(static::$options));

			// Sanitize them.
			static::sanitize(static::$options, true);

			// After.
			$after = md5(json_encode(static::$options));
			if ($before !== $after) {
				update_option(static::OPTION_NAME, static::$options);
			}
		}

		return true;
	}

	/**
	 * Sanitize Options
	 *
	 * @param array $options Options.
	 * @return bool True/false.
	 */
	protected static function sanitize(&$options) {

		// We moved the WP-REST setting. Just in case anybody is coming
		// from an older version, we want to make sure it gets saved.
		if (isset($options['api']['access'])) {
			$options['core']['wp_rest'] = $options['api']['access'];
		}

		// Make sure it fits the appropriate format.
		static::$options = common\data::parse_args($options, static::OPTIONS);

		// Apply our read-only constants.
		static::apply_readonly($options);

		// WP-REST.
		common\ref\mb::strtolower($options['core']['wp_rest']);
		if (!in_array($options['core']['wp_rest'], static::API_ACCESS, true)) {
			$options['core']['wp_rest'] = static::OPTIONS['core']['wp_rest'];
		}

		// Validate license.
		static::$pro = static::validate_license($options['license']);

		// Logins.
		common\ref\sanitize::to_range($options['login']['fail_limit'], 3, 50);
		common\ref\sanitize::to_range($options['login']['subnet_fail_limit'], 10, 100);
		common\ref\sanitize::to_range($options['login']['subnet_fail_limit'], $options['login']['fail_limit']);
		common\ref\sanitize::to_range($options['login']['fail_window'], 600, 86400);

		// The server key should exist...
		$keys = login::get_server_keys();
		if (!array_key_exists($options['login']['key'], $keys)) {
			if (!count($keys) || array_key_exists(static::OPTIONS['login']['key'], $keys)) {
				$options['login']['key'] = static::OPTIONS['login']['key'];
			}
			// Just pick the first available, I guess.
			else {
				$keys = array_keys($keys);
				$options['login']['key'] = $keys[0];
			}
		}

		static::sanitize_whitelist($options['login']['exempt']);

		// Pruning.
		common\ref\sanitize::to_range($options['prune']['limit'], 30, 365);

		// Passwords.
		common\ref\mb::strtolower($options['password']['alpha']);
		if (!in_array($options['password']['alpha'], static::PASSWORD_ALPHA, true)) {
			$options['password']['alpha'] = static::OPTIONS['password']['alpha'];
		}
		common\ref\mb::strtolower($options['password']['numeric']);
		if (!in_array($options['password']['numeric'], static::PASSWORD_NUMERIC, true)) {
			$options['password']['numeric'] = static::OPTIONS['password']['numeric'];
		}
		common\ref\mb::strtolower($options['password']['symbol']);
		if (!in_array($options['password']['symbol'], static::PASSWORD_NUMERIC, true)) {
			$options['password']['symbol'] = static::OPTIONS['password']['symbol'];
		}
		common\ref\sanitize::to_range($options['password']['length'], static::MIN_PASSWORD_LENGTH, 500);

		return true;
	}

	/**
	 * Sanitize Whitelist
	 *
	 * There is enough going on here that it pays to offload this set
	 * of routines to its own function.
	 *
	 * @param array $whitelist IPs, etc.
	 * @return void Nothing.
	 */
	public static function sanitize_whitelist(&$whitelist) {
		common\ref\cast::to_array($whitelist);

		// Gotta check it line by line.
		foreach ($whitelist as $k=>$v) {
			common\ref\cast::to_string($whitelist[$k], true);
			$whitelist[$k] = preg_replace('/[^\da-f\.\:\/\-]/i', '', $v);

			// A regular IP.
			if (filter_var($whitelist[$k], FILTER_VALIDATE_IP)) {
				common\ref\sanitize::ip($whitelist[$k], false);
				if (!$whitelist[$k]) {
					unset($whitelist[$k]);
				}
				continue;
			}

			// An arbitrary range?
			if (1 === substr_count($whitelist[$k], '-')) {
				$tmp = explode('-', $whitelist[$k]);
				common\ref\sanitize::ip($tmp[0], false);
				common\ref\sanitize::ip($tmp[1], false);

				// One of the IPs is bad.
				if (!$tmp[0] || !$tmp[1]) {
					unset($whitelist[$k]);
					continue;
				}

				// The same? No range.
				if ($tmp[0] === $tmp[1]) {
					$whitelist[$k] = $tmp[0];
				}
				else {
					// Fix order?
					if (common\format::ip_to_number($tmp[0]) > common\format::ip_to_number($tmp[1])) {
						common\data::switcheroo($tmp[0], $tmp[1]);
					}
					$whitelist[$k] = implode('-', $tmp);
				}

				continue;
			}

			// A CIDR?
			if (false !== ($range = common\format::cidr_to_range($whitelist[$k]))) {
				list($ip, $bits) = explode('/', $whitelist[$k]);
				$ip = common\sanitize::ip($range['min'], false);

				// Continue if the IP is valid, at least.
				if ($ip) {
					common\ref\cast::to_int($bits, true);
					$max = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? 32 : 128;
					common\ref\sanitize::to_range($bits, 0, $max);
					$whitelist[$k] = "{$ip}/{$bits}";
					continue;
				}

				unset($whitelist[$k]);
				continue;
			}

			unset($whitelist[$k]);
		}
		$whitelist = array_unique($whitelist);
		sort($whitelist);
	}

	/**
	 * Get Option
	 *
	 * @param string $key Key.
	 * @return mixed Value or false.
	 */
	public static function get($key=null) {
		static::load();

		// Return everything?
		if (is_null($key)) {
			return static::$options;
		}

		common\ref\cast::to_string($key, true);

		// A single option.
		if (array_key_exists($key, static::$options)) {
			return static::$options[$key];
		}

		// It could also be a split.
		if (1 === substr_count($key, '-')) {
			list($a,$b) = explode('-', $key);
			if (
				array_key_exists($a, static::$options) &&
				is_array(static::$options[$a]) &&
				array_key_exists($b, static::$options[$a])
			) {
				return static::$options[$a][$b];
			}
		}

		// Must not exist.
		return false;
	}

	/**
	 * Save Option
	 *
	 * @param string $key Key.
	 * @param mixed $value Value.
	 * @param bool $force Force resaving.
	 * @return bool True/false.
	 */
	public static function save($key, $value, $force=false) {
		static::load();
		common\ref\cast::to_string($key, true);

		// Everything else...
		if (!array_key_exists($key, static::$options)) {
			return false;
		}

		// No change?
		if (!$force && static::$options[$key] === $value) {
			return true;
		}

		$original = static::$options[$key];

		static::$options[$key] = $value;
		update_option(static::OPTION_NAME, static::$options);
		static::load(true);

		return true;
	}

	/**
	 * Get Read-Only Options
	 *
	 * @return array Options.
	 */
	public static function get_readonly() {
		static::load();
		return static::$readonly;
	}

	/**
	 * Apply Read-Only Values
	 *
	 * Take any pre-defined constants and throw them into the settings.
	 *
	 * @param array $options Options.
	 * @return bool True/false.
	 */
	protected static function apply_readonly(&$options) {
		static::$readonly = array();

		// Run through everything.
		foreach ($options as $k=>$v) {
			// License doesn't count.
			if ('license' === $k) {
				continue;
			}

			foreach ($v as $k2=>$v2) {
				$key = "$k-$k2";
				$value = static::get_hard_value($k, $k2);
				if (!is_null($value)) {
					$options[$k][$k2] = $value;
					static::$readonly[] = $key;
				}
			}
		}

		sort(static::$readonly);

		return true;
	}

	/**
	 * Retrieve Read-Only Value
	 *
	 * Constants can be used to hard-set any Apocalypse Meow settings.
	 * That's optional and annoying to check, so, here we are.
	 *
	 * @param string $class Classification.
	 * @param string $option Sub-option.
	 * @return mixed Value or null.
	 */
	protected static function get_hard_value($class, $option) {
		common\ref\cast::to_string($class, true);
		common\ref\cast::to_string($option, true);

		// Bad arguments.
		if (!$class || !$option) {
			return null;
		}

		$constant = common\mb::strtoupper("MEOW_{$class}_{$option}");

		// Can't set exemptions. That's it.
		if ('MEOW_LOGIN_EXEMPT' === $constant) {
			return null;
		}

		// Regular constant.
		if (defined($constant)) {
			return constant($constant);
		}

		// Deprecated constant?
		if (
			array_key_exists($class, static::OLD_CONSTANTS) &&
			array_key_exists($option, static::OLD_CONSTANTS[$class]) &&
			defined(static::OLD_CONSTANTS[$class][$option])
		) {
			return constant(static::OLD_CONSTANTS[$class][$option]);
		}

		return null;
	}

	/**
	 * Validate License
	 *
	 * @param string $license License.
	 * @return bool True/false.
	 */
	public static function validate_license(&$license) {
		$tmp = license::get($license);
		$license = $tmp->is_license() ? $tmp->get_raw(true) : '';
		return $tmp->is_pro();
	}

	/**
	 * Is Pro
	 *
	 * @return bool True/false.
	 */
	public static function is_pro() {
		static::load();
		return static::$pro;
	}


}
