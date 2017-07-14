<?php
/**
 * Apocalypse Meow Login Functions
 *
 * Security actions relating to login/password security are located
 * here, along with some related tools and environment helpers.
 *
 * @package apocalypse-meow
 * @author  Blobfolio, LLC <hello@blobfolio.com>
 */

namespace blobfolio\wp\meow;

use \blobfolio\wp\meow\vendor\common;
use \WP_Error;
use \WP_User;

class login {

	const LOGIN_LOG = array(
		'ip'=>'',
		'subnet'=>'',
		'date_created'=>'0000-00-00 00:00:00',
		'date_expires'=>'0000-00-00 00:00:00',
		'type'=>'fail',
		'username'=>'',
		'count'=>1
	);

	const LOGIN_LOG_TYPES = array(
		'alert',
		'ban',
		'fail',
		'success',
	);

	const COMMUNITY_RECEIVE = array(
		'blocklist'=>array(),
		'limits'=>array(),
		'weighting'=>5
	);

	// Environment-related pieces.
	protected static $server_ips;
	protected static $server_keys;
	protected static $visitor_fail_window;
	protected static $visitor_ip;
	protected static $visitor_subnet;

	// Keep track of password errors.
	protected static $password_errors = array();

	// Keep track of usernames we've looked up.
	protected static $users = array();



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

		// Nonce.
		if ($settings['login']['nonce']) {
			add_action('login_form', array($class, 'login_nonce'));
			add_filter('authenticate', array($class, 'login_nonce_validate'), 50, 1);
		}

		// Banned user?
		add_action('login_init', array($class, 'login_banned'));

		// Login hooks.
		add_action('wp_login', array($class, 'login_log_success'), 10, 2);
		add_action('wp_login_failed', array($class, 'login_log_fail'));
		add_filter('wp_login_errors', array($class, 'login_fails_remaining'), 100, 1);
		if (options::get('login-alert_on_new')) {
			add_action('wp_login', array($class, 'login_log_alert'), 20, 2);
		}

		// Password hooks.
		add_action('admin_notices', array($class, 'password_require_reset_notice'));
		add_action('check_passwords', array($class, 'check_passwords'), 10, 3);
		add_action('init', array($class, 'password_require_reset_redirect'), 50);
		add_action('init', array($class, 'password_require_reset_remove'), 20);

		add_action('password_rules', array($class, 'password_rules'), 10, 2);
		add_action('password_rules_error', array($class, 'password_rules_error'));
		add_action('user_profile_update_errors', array($class, 'password_rules_error'));
		add_action('validate_password_reset', array($class, 'validate_password_reset'));
		add_action('wp_login', array($class, 'password_require_reset'), 30, 2);

		// Data pruning CRON job.
		add_action('meow_cron_prune', array($class, 'prune'));
		$timestamp = wp_next_scheduled('meow_cron_prune');
		if (options::get('prune-active')) {
			if (false === $timestamp) {
				wp_schedule_event(time() + 60, 'daily', 'meow_cron_prune');
			}
		}
		elseif (false !== $timestamp) {
			wp_unschedule_event($timestamp, 'meow_cron_prune');
		}

		// Community action!
		foreach (array('give','receive') as $field) {
			add_action("meow_cron_community_$field", array($class, "community_$field"));
			$timestamp = wp_next_scheduled("meow_cron_community_$field");
			if (options::get('login-community')) {
				if (false === $timestamp) {
					wp_schedule_event(time() + 60, 'hourly', "meow_cron_community_$field");
				}
			}
			elseif (false !== $timestamp) {
				wp_unschedule_event($timestamp, "meow_cron_community_$field");
			}
		}
	}

	// ----------------------------------------------------------------- end init



	// -----------------------------------------------------------------
	// Login Functions
	// -----------------------------------------------------------------

	/**
	 * Disable Login
	 *
	 * If a user is banned, disable the login form/handler.
	 *
	 * @return void Nothing.
	 */
	public static function login_banned() {
		if (static::is_banned(null, true) || apply_filters('meow_is_banned', false, null)) {
			wp_die(
				__('For security reasons, logins from your network are temporarily prohibited. Please try again later.', 'apocalypse-meow'),
				__('Login Denied', 'apocalypse-meow'),
				403
			);
		}
	}

	/**
	 * Login Nonce
	 *
	 * Add a Nonce to the login form, maybe.
	 *
	 * @return void Nothing.
	 */
	public static function login_nonce() {
		if (options::get('login-nonce')) {
			echo '<input type="hidden" name="meow-login-nonce" value="' . esc_attr(wp_create_nonce('meow-login')) . '" />';
		}
	}

	/**
	 * Validate Login Nonce
	 *
	 * @param WP_User $user User object.
	 * @return mixed User or error.
	 */
	public static function login_nonce_validate($user) {
		if (
			options::get('login-nonce') &&
			('POST' === getenv('REQUEST_METHOD')) &&
			(
				!isset($_POST['meow-login-nonce']) ||
				!wp_verify_nonce($_POST['meow-login-nonce'], 'meow-login')
			)
		) {
			return new WP_Error(
				'meow_login_nonce_error',
				'<strong>' . __('ERROR:', 'apocalypse-meow') . '</strong> ' . __('The form had expired. Please try again.', 'apocalypse-meow')
			);
		}

		return $user;
	}

	/**
	 * Login Log: General
	 *
	 * @param array $args Arguments.
	 *
	 * @arg string $type Type: alert, ban, fail, success.
	 * @arg string $username Username.
	 * @arg int $count Count.
	 *
	 * @return bool True/false.
	 */
	public static function login_log($args=null) {
		global $wpdb;
		$data = common\data::parse_args($args, static::LOGIN_LOG);

		// Bad log type.
		common\ref\mb::strtolower($data['type']);
		if (!in_array($data['type'], static::LOGIN_LOG_TYPES, true)) {
			return false;
		}

		// Bans are one or the other.
		if ('ban' === $data['type']) {
			// IP ban.
			if (
				($data['ip'] && '0' !== $data['ip']) ||
				('0' === $data['subnet']) ||
				(!$data['ip'] && !$data['subnet'])
			) {
				$data['subnet'] = '0';
			}
			else {
				$data['ip'] = '0';
			}
		}
		// Otherwise '0' is unacceptable.
		else {
			if ('0' === $data['ip']) {
				$data['ip'] = '';
			}
			if ('0' === $data['subnet']) {
				$data['subnet'] = '';
			}
		}

		// Determine the IP.
		if ('0' !== $data['ip']) {
			// User-defined.
			if ($data['ip']) {
				common\ref\sanitize::ip($data['ip']);
				if ($data['ip'] && ('ban' !== $data['type'])) {
					$data['subnet'] = common\format::ip_to_subnet($data['ip']);
				}
			}
			// Just the visitor.
			else {
				$data['ip'] = static::get_visitor_ip();
				if ($data['ip'] && ('ban' !== $data['type'])) {
					$data['subnet'] = static::get_visitor_subnet();
				}
			}

			// No IP, no logging.
			if (!$data['ip']) {
				return false;
			}
		}
		// Only the subnet.
		else {
			// User-defined.
			if ($data['subnet']) {
				static::sanitize_subnet($data['subnet']);
			}
			else {
				$data['subnet'] = static::get_visitor_subnet();
			}

			// No subnet, no logging.
			if (!$data['subnet']) {
				return false;
			}
		}

		// Don't log anything if the user is banned.
		if (
			(('0' !== $data['ip']) && apply_filters('meow_is_banned', false, $data['ip'])) ||
			(('0' !== $data['subnet']) && apply_filters('meow_is_banned', false, $data['subnet']))
		) {
			return false;
		}

		// Always now.
		$data['date_created'] = current_time('mysql');

		if ('ban' === $data['type']) {
			$data['username'] = '';
			common\ref\sanitize::to_range($data['count'], 1);
			common\ref\sanitize::datetime($data['date_expires']);
			if ($data['date_expires'] < $data['date_created']) {
				return true;
			}
		}
		else {
			static::sanitize_username($data['username']);
			$data['count'] = 1;
			$data['date_expires'] = '0000-00-00 00:00:00';
		}

		// Okay, let's add it.
		$wpdb->insert(
			"{$wpdb->prefix}meow2_log",
			$data,
			'%s'
		);

		// Trigger the action in case any users want to know more.
		if (options::is_pro() && ('alert' !== $data['type'])) {
			do_action("meow_logged_{$data['type']}", $data);
		}

		// If we aren't failing or the user is whitelisted, we're done.
		if (
			('fail' !== $data['type']) ||
			apply_filters('meow_is_whitelisted', false, $data['ip'])
		) {
			return true;
		}

		$fail_window = options::get('login-fail_window');

		// Has the IP reached its fail limit?
		if (0 === static::visitor_ip_fails_remaining()) {
			// Find the earliest countable failure.
			$window = static::visitor_fail_window();
			$first = $wpdb->get_var("
				SELECT MIN(`date_created`)
				FROM `{$wpdb->prefix}meow2_log`
				WHERE
					`type`='fail' AND
					`ip`='{$data['ip']}' AND
					`date_created` > '$window'
			");
			return static::login_log(
				array(
					'ip'=>$data['ip'],
					'subnet'=>'0',
					'type'=>'ban',
					'date_expires'=>date('Y-m-d H:i:s', strtotime("+$fail_window seconds", strtotime($first)))
				)
			);
		}

		// Has the Subnet reached its fail limit?
		if (0 === static::visitor_subnet_fails_remaining()) {
			// Find the earliest countable failure.
			$window = static::visitor_fail_window();
			$first = $wpdb->get_var("
				SELECT MIN(`date_created`)
				FROM `{$wpdb->prefix}meow2_log`
				WHERE
					`type`='fail' AND
					`subnet`='{$data['subnet']}' AND
					`date_created` > '$window'
			");
			return static::login_log(
				array(
					'ip'=>'0',
					'subnet'=>$data['subnet'],
					'type'=>'ban',
					'date_expires'=>date('Y-m-d H:i:s', strtotime("+$fail_window seconds", strtotime($first)))
				)
			);
		}

		return true;
	}

	/**
	 * Login Log: Fail
	 *
	 * This might recursively result in a ban too.
	 *
	 * @param string $username Username.
	 * @return bool True/false.
	 */
	public static function login_log_fail($username='') {
		return static::login_log(
			array(
				'type'=>'fail',
				'username'=>$username
			)
		);
	}

	/**
	 * Login Log: Success
	 *
	 * @param string $username Username.
	 * @param string $user User object.
	 * @return bool True/false.
	 */
	public static function login_log_success($username='', $user) {
		// Prefer the object's username to whatever the person typed.
		if (is_a($user, 'WP_User')) {
			$username = $user->user_login;
		}

		return static::login_log(
			array(
				'type'=>'success',
				'username'=>$username
			)
		);
	}

	/**
	 * Login Log: Email Alert
	 *
	 * @param string $username Username.
	 * @param string $user User object.
	 * @return bool True/false.
	 */
	public static function login_log_alert($username='', $user) {
		global $wpdb;

		// Can't help bogus IPs.
		if (
			(false === ($ip = static::get_visitor_ip())) ||
			(false === ($subnet = static::get_visitor_subnet()))
		) {
			return true;
		}

		$mode = options::get('login-alert_by_subnet') ? 'subnet' : 'ip';
		$new_login = (int) $wpdb->get_var("
			SELECT COUNT(*)
			FROM `{$wpdb->prefix}meow2_log`
			WHERE
				`type`='success' AND
				`$mode`='{$$mode}'
		");

		// Was this the first?
		if (1 === $new_login) {
			$username = $user->user_login;
			static::sanitize_username($username);

			$site_name = common\format::decode_entities(get_bloginfo('name'));

			// Build the email message.
			$mbody = array(
				__('Hi', 'apocalypse-meow') . " $username,",
				'',
				sprintf(
					__('This is an automated alert to inform you that your account at %s has been accessed from a new network address.', 'apocalypse-meow'),
					$site_name
				),
				'',
				sprintf(
					__('If this was not you, immediately visit %s and reset your password. You should also end all other sessions and make sure no unauthorized changes have been made to your account.', 'apocalypse-meow'),
					admin_url('profile.php')
				),
				'',
				__('Login Time', 'apocalypse-meow') . ': ' . current_time('l, F j, Y @ H:i:s'),
				__('Browser', 'apocalypse-meow') . ': ' . (isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : ''),
				__('IP', 'apocalypse-meow') . ": $ip",
				'',
				__('This email has been sent to', 'apocalypse-meow') . " {$user->user_email}.",
				'',
				__('Regards,', 'apocalypse-meow'),
				__('All at', 'apocalypse-meow') . " $site_name.",
				site_url()
			);
			$mbody = implode("\n", $mbody);

			wp_mail(
				$user->user_email,
				"[$site_name] " . __('Login Alert', 'apocalypse-meow'),
				$mbody
			);

			// And log the alert.
			return static::login_log(
				array(
					'type'=>'alert',
					'username'=>$username
				)
			);
		}

		return true;
	}

	/**
	 * Pardon IP
	 *
	 * Remove a ban.
	 *
	 * @param int $id Record ID.
	 * @return bool True/false.
	 */
	public static function pardon($id) {
		global $wpdb;
		common\ref\cast::to_int($id, true);

		// Obviously bad ID.
		if ($id <= 0) {
			return false;
		}

		// Make sure the ID is valid.
		$cutoff = current_time('mysql');
		if (!intval($wpdb->get_var("
			SELECT COUNT(*)
			FROM `{$wpdb->prefix}meow2_log`
			WHERE
				`id`=$id AND
				`type`='ban' AND
				`date_expires` > '$cutoff'
		"))) {
			return false;
		}

		$wpdb->update(
			"{$wpdb->prefix}meow2_log",
			array(
				'date_expires'=>$cutoff,
				'pardoned'=>1
			),
			array('id'=>$id),
			array('%s','%d'),
			'%d'
		);

		return true;
	}

	/**
	 * Return Failures Remaining
	 *
	 * Append a login error noting the number of failures remaining
	 * before a ban is unavoidable.
	 *
	 * Only bother reporting if the number dips below the limit itself.
	 *
	 * @param mixed $errors Errors.
	 * @return mixed Errors.
	 */
	public static function login_fails_remaining($errors=null) {
		// WP jams non-errors into the error object for some reason.
		// We need to loop through everything to make sure there really
		// was a problem.
		$has_errors = false;
		if (is_wp_error($errors) && count($errors->errors)) {
			foreach ($errors->get_error_codes() as $code) {
				if ('message' !== $errors->get_error_data($code)) {
					$has_errors = true;
					break;
				}
			}
		}

		// See where we stand.
		$fail_limit = options::get('login-fail_limit');
		$remaining = apply_filters('meow_failures_remaining', $fail_limit, null);

		// Kill it if we're banned.
		if (0 === $remaining) {
			static::login_banned();
		}

		// Anything to report?
		if ($remaining < $fail_limit) {
			// Make sure we have an error object.
			if (!is_wp_error($errors)) {
				$errors = new WP_Error();
			}

			if ($remaining > 0) {
				// Add our own non-error error.
				$errors->add(
					'attempts-remaining',
					__('Login attempts remaining', 'apocalypse-meow') . ": $remaining",
					'message'
				);
			}
			else {
				// Add a real error. Any subsequent load will trigger
				// the proper ban screen, but WP isn't going to rerun
				// the hook this time around, so gotta show them
				// something.
				$errors->add(
					'attempts-remaining',
					'<strong>' . __('ERROR:', 'apocalypse-meow') . '</strong> ' . __('For security reasons, logins from your network are temporarily prohibited. Please try again later.', 'apocalypse-meow')
				);
			}
		}

		return $errors;
	}

	// ----------------------------------------------------------------- end login



	// -----------------------------------------------------------------
	// Community Action!
	// -----------------------------------------------------------------

	/**
	 * Community: Give Back
	 *
	 * This is an opt-in function that allows fail data from this site
	 * to be pooled with fail data form other sites to reach a sort of
	 * herd immunity.
	 *
	 * Speaking of sharing, this feature requires sharing some basic
	 * environmental data such as PHP/OS/WP version information, the
	 * site URL, and server IP. This information serves two purposes:
	 * 1) It helps differentiate sources;
	 * 2) It helps inform future plugin development;
	 *
	 * @return bool True/false.
	 */
	public static function community_give() {
		global $wpdb;

		$out = array(
			'source'=>array(
				'domain'=>common\sanitize::hostname(site_url()),
				'locale'=>get_locale(),
				'meow'=>about::get_local('Version'),
				'os'=>PHP_OS,
				'php'=>PHP_VERSION,
				'premium'=>options::is_pro(),
				'timezone'=>about::get_timezone(),
				'wp'=>common\format::decode_entities(get_bloginfo('version')),
			),
			'data'=>array()
		);

		// Where did we leave off?
		$last_id = (int) get_option('meow_community_give', 0);
		$max = (int) $wpdb->get_var("SELECT MAX(`id`) FROM `{$wpdb->prefix}meow2_log` WHERE `type`='fail'");

		// The table might have been truncated, or nothing's happened.
		// Either way, we can bail.
		if ($max <= $last_id) {
			update_option('meow_community_give', $max);
			return true;
		}

		// Keep track of whitelisted IPs locally so we can avoid
		// re-running range checks and whatnot.
		$blacklisted = array();
		$whitelisted = array();
		$tmp = array_merge(options::get('login-exempt'), static::get_server_ips());
		foreach ($tmp as $v) {
			// We can go ahead and whitelist anything that is a single
			// IP address.
			if (filter_var($v, FILTER_VALIDATE_IP)) {
				$whitelisted[$v] = true;
			}
		}

		// Find them failures.
		$conds = array();
		$conds[] = "`id` > $last_id";
		$conds[] = "`type`='fail'";
		$conds[] = 'UNIX_TIMESTAMP(`date_created`) >= ' . strtotime('-1 day');
		if (count($whitelisted)) {
			$conds[] = "NOT(`ip` IN ('" . implode("','", $whitelisted) . "'))";
		}
		$conds = implode(' AND ', $conds);
		$dbResult = $wpdb->get_results("
			SELECT
				`ip`,
				`date_created`,
				`username`
			FROM `{$wpdb->prefix}meow2_log`
			WHERE $conds
			ORDER BY `id` ASC
		", ARRAY_A);
		if (!is_array($dbResult) || !count($dbResult)) {
			return true;
		}

		foreach ($dbResult as $Row) {
			common\ref\sanitize::ip($Row['ip']);

			// We already checked, they're whitelisted.
			if (!$Row['ip'] || isset($whitelisted[$Row['ip']])) {
				continue;
			}
			// We haven't checked, but they are whitelisted.
			elseif (!isset($blacklisted[$Row['ip']]) && login::is_whitelisted($Row['ip'])) {
				$whitelisted[$Row['ip']] = true;
				continue;
			}

			// They're bad, so bad.
			$blacklisted[$Row['ip']] = true;

			if ('UTC' !== $out['source']['timezone']) {
				common\ref\format::to_timezone($Row['date_created'], $out['source']['timezone'], 'UTC');
			}
			else {
				common\ref\sanitize::datetime($Row['date_created']);
			}

			$out['data'][] = array(
				'date_created'=>$Row['date_created'],
				'ip'=>$Row['ip'],
				'weighted'=>(
					in_array($Row['username'], array('admin','administrator'), true) &&
					!static::username_exists($Row['username'])
				),
			);
		}

		// Send it!
		$response = wp_remote_post(
			MEOW_API . 'list/',
			array(
				'timeout'=>10,
				'httpversion'=>'1.1',
				'body'=>$out
			)
		);

		if (200 === wp_remote_retrieve_response_code($response)) {
			update_option('meow_community_give', $max);
		}

		return true;
	}

	/**
	 * Community: Pull in the Blocklist!
	 *
	 * This will retrieve the current community blocklist, if any, and
	 * update the site blocklist accordingly.
	 *
	 * @return bool True/false.
	 */
	public static function community_receive() {
		global $wpdb;

		// Send it!
		$response = wp_remote_get(
			MEOW_API . 'list/',
			array(
				'timeout'=>10,
				'httpversion'=>'1.1',
			)
		);
		if (200 !== wp_remote_retrieve_response_code($response)) {
			return false;
		}

		$pool = wp_remote_retrieve_body($response);
		if (!is_array($pool)) {
			$pool = json_decode($pool, true);
		}
		if (!is_array($pool) || !isset($pool['data'])) {
			return true;
		}

		$pool = common\data::parse_args($pool['data'], static::COMMUNITY_RECEIVE);

		// Save the community weighting information so we can reference
		// it on the settings page.
		$weighting = array(
			'limits'=>$pool['limits'],
			'weighting'=>$pool['weighting']
		);
		update_option('meow_community_weighting', $weighting);

		// Now process the blocklist, if applicable.
		if (count($pool['blocklist'])) {
			// Get a list of currently banned IPs so we don't double-book.
			$banned = array();
			$cutoff = current_time('Y-m-d H:i:s');
			$dbResult = $wpdb->get_results("
				SELECT `ip`
				FROM `{$wpdb->prefix}meow2_log`
				WHERE
					`type`='ban' AND
					`date_expires` > '$cutoff' AND
					`ip` != '0'
				ORDER BY `ip` ASC
			", ARRAY_A);
			if (is_array($dbResult) && count($dbResult)) {
				foreach ($dbResult as $Row) {
					$banned[] = common\sanitize::ip($Row['ip']);
				}
			}

			$inserts = array();
			$timezone = about::get_timezone();
			$now = current_time('mysql');
			foreach ($pool['blocklist'] as $v) {
				if (in_array($v['ip'], $banned, true) || static::is_whitelisted($v['ip'])) {
					continue;
				}

				if ('UTC' !== $timezone) {
					common\ref\format::to_timezone($v['date_expires'], 'UTC', $timezone);
				}

				$line = array(
					$now,
					'ban',
					esc_sql($v['ip']),
					0,
					esc_sql($v['date_expires']),
					1
				);
				$inserts[] = "('" . implode("','", $line) . "')";
			}

			if (count($inserts)) {
				$inserts = array_chunk($inserts, 100);
				foreach ($inserts as $i) {
					$wpdb->query("
						INSERT INTO `{$wpdb->prefix}meow2_log` (`date_created`,`type`,`ip`,`subnet`,`date_expires`,`community`)
						VALUES " . implode(',', $i)
					);
				}
			}
		}

		return true;
	}

	// ----------------------------------------------------------------- end community



	// -----------------------------------------------------------------
	// Password Functions
	// -----------------------------------------------------------------

	/**
	 * Common Passwords
	 *
	 * We can't let people use overly common passwords. Our recipe is
	 * as follows:
	 *
	 * 1. Start with Top 100K.
	 * 2. Remove lines shorter than 10 characters.
	 * 3. Remove lines with fewer than 4 unique characters.
	 * 4. Convert to lowercase.
	 * 5. Sort unique lines.
	 *
	 * @see {https://github.com/danielmiessler/SecLists/tree/master/Passwords}
	 *
	 * @param string $password Password.
	 * @return bool True/false.
	 */
	public static function is_common_password($password) {
	 	common\ref\cast::to_string($password);
	 	common\ref\mb::trim($password);
	 	common\ref\mb::strtolower($password);

	 	// Don't bother checking if it is too short.
	 	if (common\mb::strlen($password) < options::MIN_PASSWORD_LENGTH) {
	 		return false;
	 	}

	 	// Catch errors just in case the server can't handle JSON.
	 	try {
	 		$common = @file_get_contents(MEOW_PLUGIN_DIR . 'bad-passwords.json');
	 		$common = json_decode($common);
	 		if (is_array($common) && count($common)) {
	 			return in_array($password, $common, true);
	 		}
	 	} catch (\Throwable $e) {
	 		return false;
	 	} catch (\Exception $e) {
	 		return false;
	 	}

	 	// Still here? Not common.
	 	return false;
	}

	/**
	 * General Password Checks
	 *
	 * Validate passwords against the site preferences.
	 *
	 * @param string $pass1 Password 1.
	 * @param string $pass2 Password 2.
	 * @return bool True/false.
	 */
	public static function password_rules(&$pass1, &$pass2) {
		// Let WP handle basic errors.
		if (!$pass1 || ($pass1 !== $pass2)) {
			return false;
		}

		// Can't be common.
		if (static::is_common_password($pass1)) {
			static::$password_errors['password_error_common'] = sprintf(
				__('"%s" is actually one of the most common passwords on the Internet. Please try something else!', 'apocalypse-meow'),
				esc_html($pass1)
			);
		}

		// Letters.
		$alpha = options::get('password-alpha');
		if (('required' === $alpha) && !preg_match('/[a-z]/i', $pass1)) {
			static::$password_errors['password_error_alpha'] = __('The password must contain at least one letter.', 'apocalypse-meow');
		}
		elseif (
			('required-both' === $alpha) &&
			(!preg_match('/[a-z]/', $pass1) || !preg_match('/[A-Z]/', $pass1))
		) {
			static::$password_errors['password_error_alpha'] = __('The password must contain at least one uppercase letter and one lowercase letter.', 'apocalypse-meow');
		}

		// Numbers.
		if (('required' === options::get('password-numeric')) && !preg_match('/\d/', $pass1)) {
			static::$password_errors['password_error_numeric'] = __('The password must contain at least one number.', 'apocalypse-meow');
		}

		// Symbols.
		if (('required' === options::get('password-symbol')) && !preg_match('/[^\da-z]/ui', $pass1)) {
			static::$password_errors['password_error_symbol'] = __('The password must contain at least one non-alphanumeric symbol.', 'apocalypse-meow');
		}

		$length = options::get('password-length');
		if (common\mb::strlen($pass1) < $length) {
			static::$password_errors['password_error_length'] = sprintf(
				__('The password must be at least %d characters long.', 'apocalypse-meow'),
				$length
			);
		}
		else {
			$chars = common\mb::str_split($pass1);
			$chars = array_unique($chars);
			if (count($chars) < options::MIN_PASSWORD_CHARS) {
				static::$password_errors['password_error_length'] = sprintf(
					__('The password must consist of at least %d different characters.', 'apocalypse-meow'),
					$options::MIN_PASSWORD_CHARS
				);
			}
		}

		return (0 === count(static::$password_errors));
	}

	/**
	 * General Password Checks (wrapper)
	 *
	 * For whatever reason there's a second action used for password
	 * validation in some places but the argument order is different
	 * so we can't just hook the same function twice.
	 *
	 * @param string $username Username.
	 * @param string $pass1 Password 1.
	 * @param string $pass2 Password 2.
	 * @return bool True/false.
	 */
	public static function check_passwords($username, &$pass1, &$pass2) {
		return static::password_rules($pass1, $pass2);
	}

	/**
	 * Password Rules Error
	 *
	 * For some reason password checking is all action-based, so errors
	 * have to be determined in one function but transmitted in another.
	 *
	 * @param WP_Error $errors Errors.
	 * @return bool True.
	 */
	public static function password_rules_error($errors) {
		if (count(static::$password_errors)) {
			foreach (static::$password_errors as $k=>$v) {
				$errors->add($k, $v, array('form-field'=>'pass1'));
			}
		}

		return true;
	}

	/**
	 * Validate Password Reset
	 *
	 * Again, a separate action, this time with no helpful arguments.
	 *
	 * @return bool True/false.
	 */
	public static function validate_password_reset() {
		// We have to steal the password from $_POST. If that isn't set
		// or contains a hardcoded WP error, it is beyond our scope.
		if (
			!isset($_POST['pass1']) ||
			!isset($_POST['pass2']) ||
			!$_POST['pass1'] ||
			($_POST['pass1'] !== $_POST['pass2'])
		) {
			return false;
		}

		if (!static::password_rules($_POST['pass1'], $_POST['pass2'])) {
			global $errors;
			return static::password_rules_error($errors);
		}

		return true;
	}

	/**
	 * Force Password Reset
	 *
	 * Because WP passwords are encrypted, we cannot know whether the
	 * current choices are inline with the plugin settings. But when
	 * someone logs in, we have their plain text password and can
	 * demand a reset.
	 *
	 * @param string $username Username.
	 * @param WP_User $user User object.
	 * @return bool True/false.
	 */
	public static function password_require_reset($username, $user) {
		global $wpdb;

		if (isset($_POST['pwd']) && $_POST['pwd']) {
			if (!static::password_rules($_POST['pwd'], $_POST['pwd'])) {
				static::$password_errors = array();

				// First, hash their password hash. WP doesn't provide
				// adequate hooks for verifying that a password has
				// been updated, so we'll just have to rely on a later
				// change.
				$value = hash('sha512', $user->user_pass);

				// We have to do this as a cookie because some password
				// reset hooks give us no useful information about who
				// is being reset.
				@setcookie(
					'meow_require_reset',
					$value,
					strtotime('+30 days'),
					COOKIEPATH,
					COOKIE_DOMAIN,
					is_ssl(),
					true
				);
			}
		}

		return true;
	}

	/**
	 * Force Password Reset: Redirect
	 *
	 * Force users in need of a password reset to their profile page.
	 *
	 * @return bool True/false.
	 */
	public static function password_require_reset_redirect() {
		// Forced redirects can go wrong in a lot of ways. We need to
		// make sure we aren't messing something else up by sending
		// them to the profile page.
		if (
			is_user_logged_in() &&
			isset($_COOKIE['meow_require_reset']) &&
			('GET' === getenv('REQUEST_METHOD')) &&
			(!defined('DOING_CRON') || !DOING_CRON) &&
			(!defined('DOING_AJAX') || !DOING_AJAX) &&
			(!defined('WP_CLI') || !WP_CLI) &&
			(false === strpos($_SERVER['REQUEST_URI'], 'profile.php'))
		) {
			wp_redirect(admin_url('profile.php'));
			exit;
		}

		return true;
	}

	/**
	 * Force Password Reset: Admin Notice
	 *
	 * We need to explain to the user why we're hijacking their
	 * session.
	 *
	 * @return bool True/false.
	 */
	public static function password_require_reset_notice() {
		if (
			is_user_logged_in() &&
			isset($_COOKIE['meow_require_reset']) &&
			(false !== strpos($_SERVER['REQUEST_URI'], 'profile.php'))
		) {
			// What is required?
			$pieces = array();

			$alpha = options::get('password-alpha');
			if ('required' === $alpha) {
				$pieces[] = __('letter', 'apocalypse-meow');
			}
			elseif ('required-both' === $alpha) {
				$pieces[] = __('uppercase letter', 'apocalypse-meow');
				$pieces[] = __('lowercase letter', 'apocalypse-meow');
			}

			if ('required' === options::get('password-numeric')) {
				$pieces[] = __('number', 'apocalypse-meow');
			}

			if ('required' === options::get('password-symbol')) {
				$pieces[] = __('symbol', 'apocalypse-meow');
			}

			echo '<div class="notice notice-error">';
				echo '<p><strong>' . esc_html__('Welcome back!', 'apocalypse-meow') . '</strong></p>';
				echo '<p>' . esc_html__('Before going about your business, please choose a new password for yourself.', 'apocalypse-meow') . '</p>';

				echo '<p>' . esc_html__('The site security policy requires that passwords:', 'apocalypse-meow') . '<br>';
				echo '&nbsp;&nbsp;&nbsp;&mdash; ' . sprintf(
					esc_html__('be at least %d characters in length', 'apocalypse-meow'),
					options::get('password-length')
				) . '<br>';
				echo '&nbsp;&nbsp;&nbsp;&mdash; ' . sprintf(
					esc_html__('contain no fewer than %d different characters', 'apocalypse-meow'),
					options::MIN_PASSWORD_CHARS
				) . '<br>';
				echo '&nbsp;&nbsp;&nbsp;&mdash; ' . sprintf(
					esc_html__('contain at least one of each of the following: %s', 'apocalypse-meow'),
					implode('; ', $pieces)
				) . '</p>';
			echo '</div>';
		}

		return true;
	}

	/**
	 * Remove Reset Requirement
	 *
	 * Once the user has caved to our mighty will, we can stop
	 * bothering them. :)
	 *
	 * @return bool True/false.
	 */
	public static function password_require_reset_remove() {
		global $wpdb;
		if (
			isset($_COOKIE['meow_require_reset']) &&
			is_user_logged_in()
		) {
			$current_user = wp_get_current_user();
			$value = hash('sha512', $current_user->user_pass);

			if ($value !== $_COOKIE['meow_require_reset']) {
				@setcookie(
					'meow_require_reset',
					false,
					strtotime('-30 days'),
					COOKIEPATH,
					COOKIE_DOMAIN,
					is_ssl(),
					true
				);
				unset($_COOKIE['meow_require_reset']);
			}
		}

		return true;
	}

	// ----------------------------------------------------------------- end password



	// -----------------------------------------------------------------
	// Pruning/Data Retention
	// -----------------------------------------------------------------

	/**
	 * Remove Old Data
	 *
	 * Data backs up. If pruning is enabled, old records will be
	 * cleaned automatically.
	 *
	 * @return void Nothing.
	 */
	public static function prune() {
		global $wpdb;

		if (options::get('prune-active')) {
			$limit = options::get('prune-limit');
			$limit = date('Y-m-d H:i:s', strtotime("-$limit days", current_time('timestamp')));

			$wpdb->query("
				DELETE FROM `{$wpdb->prefix}meow2_log`
				WHERE `date_created` < '$limit'
			");
		}
	}

	// ----------------------------------------------------------------- end pruning



	// -----------------------------------------------------------------
	// Misc Helpers
	// -----------------------------------------------------------------

	/**
	 * Visitor IP
	 *
	 * Visitor IP addresses might be hiding in a number of places, and
	 * even then might not be something we can/should deal with.
	 *
	 * @return string|bool IP or false.
	 */
	public static function get_visitor_ip() {
		if (is_null(static::$visitor_ip)) {
			static::$visitor_ip = false;

			$key = options::get('login-key');
			if (array_key_exists($key, $_SERVER)) {
				static::$visitor_ip = common\sanitize::ip($_SERVER[$key]);
			}
		}

		return static::$visitor_ip;
	}

	/**
	 * Visitor Subnet
	 *
	 * Works if the IP works.
	 *
	 * @return string|bool Subnet or false.
	 */
	public static function get_visitor_subnet() {
		if (is_null(static::$visitor_subnet)) {
			static::$visitor_subnet = false;
			if (false !== ($ip = static::get_visitor_ip())) {
				static::$visitor_subnet = common\format::ip_to_subnet($ip);
			}
		}

		return static::$visitor_subnet;
	}

	/**
	 * Is an IP whitelisted?
	 *
	 * Local/private-range IPs are also considered whitelisted for
	 * practical reasons.
	 *
	 * @param string $ip IP.
	 * @return bool True/false.
	 */
	public static function is_whitelisted($ip=null) {
		if (is_null($ip)) {
			if (false === ($ip = static::get_visitor_ip())) {
				return true;
			}
		}
		// Invalid/untestable IP? Same as being whitelisted.
		common\ref\sanitize::ip($ip);
		if (!$ip) {
			return true;
		}

		// No whitelist, no match.
		$whitelist = options::get('login-exempt');
		if (!is_array($whitelist) || !count($whitelist)) {
			return false;
		}

		// Gotta run through it.
		foreach ($whitelist as $v) {
			// Simple match.
			if ($ip === $v) {
				return true;
			}
			// A CIDR?
			elseif (false !== strpos($v, '/')) {
				if (common\data::ip_in_range($ip, $v)) {
					return true;
				}
			}
			// An arbitrary range?
			elseif (false !== strpos($v, '-')) {
				list($min, $max) = explode('-', $v);
				if (common\data::ip_in_range($ip, $min, $max)) {
					return true;
				}
			}
		}

		// Must not have matched.
		return false;
	}

	/**
	 * Is an IP banned?
	 *
	 * Check if an IP address is currently banned and optionally
	 * increase the count.
	 *
	 * @param string $ip IP.
	 * @param bool $count Increase count.
	 * @return bool True/false.
	 */
	public static function is_banned($ip=null, $count=false) {
		global $wpdb;

		// Default to the visitor IP.
		if (is_null($ip)) {
			if (false === ($ip = static::get_visitor_ip())) {
				return false;
			}
			$subnet = static::get_visitor_subnet();
		}
		else {
			// De-subnet a subnet.
			if (false !== strpos($ip, '/')) {
				$ip = explode('/', $ip);
				$ip = $ip[0];
			}
			common\ref\sanitize::ip($ip);
			if (!$ip) {
				return false;
			}
			$subnet = common\format::ip_to_subnet($ip);
		}

		// Can't ban someone on the whitelist or the server itself.
		if (apply_filters('meow_is_whitelisted', false, $ip)) {
			return false;
		}

		$cutoff = current_time('mysql');
		$id = (int) $wpdb->get_var("
			SELECT `id`
			FROM `{$wpdb->prefix}meow2_log`
			WHERE
				(`ip`='$ip' OR `subnet`='$subnet') AND
				`type`='ban' AND
				`date_expires` > '$cutoff'
			ORDER BY `date_expires` ASC
			LIMIT 1
		");

		if ($id) {
			// Update the count?
			if ($count) {
				$wpdb->query("
					UPDATE `{$wpdb->prefix}meow2_log`
					SET `count`=`count`+1
					WHERE `id`=$id
				");
			}
			return true;
		}

		return false;
	}

	/**
	 * Specific Fail Window
	 *
	 * The reset-on-success option means that sometimes we need to
	 * count from a different date.
	 *
	 * @return string Timestamp.
	 */
	protected static function visitor_fail_window() {
		global $wpdb;

		if (is_null(static::$visitor_fail_window)) {
			$fail_window = options::get('login-fail_window');
			static::$visitor_fail_window = date('Y-m-d H:i:s', strtotime("-$fail_window seconds", current_time('timestamp')));

			// We have to find the last success dates and see how they stack up.
			if (
				(false !== ($ip = static::get_visitor_ip())) &&
				(false !== ($subnet = static::get_visitor_subnet())) &&
				options::get('login-reset_on_success')
			) {
				$success_window = $wpdb->get_var("
					SELECT MAX(`date_created`)
					FROM `{$wpdb->prefix}meow2_log`
					WHERE
						`type`='success' AND
						(`ip`='$ip' OR `subnet`='$subnet')
				");

				if (!is_null($success_window) && $success_window > static::$visitor_fail_window) {
					static::$visitor_fail_window = $success_window;
				}
			}
		}

		return static::$visitor_fail_window;
	}

	/**
	 * Remaining Failures
	 *
	 * @param string $mode Mode.
	 * @param string $ip IP/subnet.
	 * @return int Failures remaining.
	 */
	public static function fails_remaining($mode='ip', $ip=null) {
		global $wpdb;

		$mode = ('subnet' === $mode) ? 'subnet' : 'ip';

		// IP bits.
		if ('ip' === $mode) {
			$fail_limit = options::get('login-fail_limit');
		}
		// Subnet bits.
		else {
			$fail_limit = options::get('login-subnet_fail_limit');
		}
		$remaining = $fail_limit;

		// User-supplied IP or Subnet.
		if (!is_null($ip)) {
			if ('subnet' === $mode) {
				static::sanitize_subnet($ip);
				$subnet = $ip;
				if ($subnet) {
					$ip = explode('/', $subnet);
					$ip = $ip[0];
				}
			}
			else {
				common\ref\sanitize::ip($ip);
				$subnet = common\format::ip_to_subnet($ip);
			}
		}
		// Default to the visitor's.
		else {
			$ip = static::get_visitor_ip();
			$subnet = static::get_visitor_subnet();
		}

		// Can't prosecute, just leave it at the limit.
		if (
			!$ip ||
			!$subnet ||
			apply_filters('meow_is_whitelisted', false, $ip)
		) {
			return $fail_limit;
		}

		// Already banned?
		if (apply_filters('meow_is_banned', false, $ip)) {
			return 0;
		}

		$fail_window = static::visitor_fail_window();

		$fails = (int) $wpdb->get_var("
			SELECT COUNT(*)
			FROM `{$wpdb->prefix}meow2_log`
			WHERE
				`type`='fail' AND
				`$mode`='{$$mode}' AND
				`date_created` > '$fail_window'
		");

		$remaining -= $fails;
		common\ref\sanitize::to_range($remaining, 0);

		return $remaining;
	}

	/**
	 * Remaining IP Fails?
	 *
	 * Count the number of failures left for a given IP.
	 *
	 * @return int Failures remaining.
	 */
	protected static function visitor_ip_fails_remaining() {
		return static::fails_remaining('ip');
	}

	/**
	 * Remaining Subnet Fails?
	 *
	 * Count the number of failures left for a given Subnet.
	 *
	 * @return int Failures remaining.
	 */
	protected static function visitor_subnet_fails_remaining() {
		return static::fails_remaining('subnet');
	}

	/**
	 * IP-ish Server Keys
	 *
	 * Servers running behind a proxy might not be able to look to
	 * REFERRER_ADDR for visitor IP information. Trust goes way
	 * down, but perhaps it is better than nothing.
	 *
	 * @return string|bool IP or false.
	 */
	public static function get_server_keys() {
		if (!is_array(static::$server_keys)) {
			static::$server_keys = array();
			if (is_array($_SERVER) && count($_SERVER)) {
				foreach ($_SERVER as $k=>$v) {
					if (!is_string($v)) {
						continue;
					}
					// Soft check first.
					if (preg_match('/^[\da-f\.\:]+$/', $v)) {
						// Make sure it is actually an IP.
						$ip = common\sanitize::ip($v);
						if ($ip && !static::is_server_ip($ip)) {
							static::$server_keys[$k] = $ip;
						}
					}
				}

				ksort(static::$server_keys);
			}
		}

		return static::$server_keys;
	}

	/**
	 * Server IPs
	 *
	 * We only need to know server IPs to help prevent accidentally
	 * blaming the server for some action. Local network ranges are
	 * ignored automatically.
	 *
	 * @return array IPs.
	 */
	public static function get_server_ips() {
		if (!is_array(static::$server_ips)) {
			static::$server_ips = array();

			if (is_array($_SERVER) && count($_SERVER)) {
				// First, pull what we have from the $_SERVER variable.
				foreach (array('SERVER_ADDR','LOCAL_ADDR') as $v) {
					if (isset($_SERVER[$v])) {
						$ip = common\sanitize::ip($_SERVER[$v]);
						if ($ip) {
							static::$server_ips[] = $ip;
						}
					}
				}
			}

			// Try a DNS lookup too. This will help discover any
			// other public addresses this might be using.
			$transient_key = 'meow_server_dns';
			if (false === ($dns = get_transient($transient_key))) {
				$host = common\mb::parse_url(site_url(), PHP_URL_HOST);
				if (
					function_exists('dns_get_record') &&
					$host &&
					('localhost' !== $host)
				) {
					$dns = array();
					foreach (array(DNS_A,DNS_AAAA) as $type) {
						if (false === ($records = @dns_get_record($host, $type))) {
							continue;
						}

						foreach ($records as $v) {
							if (isset($v['ip'])) {
								$ip = $v['ip'];
							}
							elseif (isset($v['ipv6'])) {
								$ip = $v['ipv6'];
							}
							else {
								continue;
							}

							common\ref\sanitize::ip($ip);
							if ($ip) {
								$dns[] = $ip;
							}
						}
					}

					set_transient($transient_key, $dns, 86400);
				}
			}

			if (is_array($dns) && count($dns)) {
				foreach ($dns as $d) {
					static::$server_ips[] = $d;
				}
			}

			static::$server_ips = array_unique(static::$server_ips);
			sort(static::$server_ips);
		}

		return static::$server_ips;
	}

	/**
	 * Is Server IP?
	 *
	 * Again, just making sure we aren't going to ban the whole server.
	 *
	 * @param string $ip IP.
	 * @return bool True/false.
	 */
	public static function is_server_ip($ip=null) {
		if (is_null($ip)) {
			$ip = static::get_visitor_ip();
		}
		else {
			common\ref\sanitize::ip($ip);
		}

		$haystack = static::get_server_ips();
		return in_array($ip, $haystack, true);
	}

	/**
	 * Sanitize Subnet
	 *
	 * We want to make sure that anything subnetish matches the block
	 * size the plugin itself uses.
	 *
	 * @param string $subnet Subnet.
	 * @return void Nothing.
	 */
	protected static function sanitize_subnet(&$subnet) {
		common\ref\cast::to_string($subnet, true);

		// Without a slash, it must be an IP.
		if (false !== strpos($subnet, '/')) {
			$subnet = explode('/', $subnet);
			$subnet = $subnet[0];
		}

		common\ref\sanitize::ip($subnet);
		if ($subnet) {
			common\ref\format::ip_to_subnet($subnet);
		}
		else {
			$subnet = '';
		}
	}

	/**
	 * Sanitize Username
	 *
	 * We want to cut down on garbage and also try to ensure that
	 * data gets entered consistently.
	 *
	 * @param string $username Username.
	 * @return void Nothing.
	 */
	protected static function sanitize_username(&$username) {
		common\ref\sanitize::printable($username);
		common\ref\mb::trim($username);
		common\ref\mb::strtolower($username);

		// Maybe an email address?
		if (
			filter_var($username, FILTER_VALIDATE_EMAIL) &&
			(false !== ($user = get_user_by('email', $username)))
		) {
			$username = $user->user_login;
		}
	}

	/**
	 * Username Exists
	 *
	 * This is a wrapper for the WP function, cached so it can run
	 * more efficiently en masse.
	 *
	 * @param string $username Username.
	 * @return bool True/false.
	 */
	public static function username_exists($username) {
		static::sanitize_username($username);
		if (!$username || (core::ENUMERATION_USERNAME === $username)) {
			return false;
		}

		if (!isset(static::$users[$username])) {
			static::$users[$username] = username_exists($username);
		}

		return static::$users[$username];
	}

	// ----------------------------------------------------------------- end helpers
}
