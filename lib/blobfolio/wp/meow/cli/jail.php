<?php
/**
 * CLI: Jail
 *
 * View and manage the login jail and whitelist.
 *
 * @package apocalypse-meow
 * @author  Blobfolio, LLC <hello@blobfolio.com>
 */

namespace blobfolio\wp\meow\cli;

use \blobfolio\wp\meow\login;
use \blobfolio\wp\meow\options;
use \blobfolio\wp\meow\vendor\common;
use \WP_CLI;
use \WP_CLI\Utils;

// Add the main command.
if (!class_exists('WP_CLI') || !class_exists('WP_CLI_Command')) {
	return;
}

// Add the main command.
WP_CLI::add_command(
	'meow jail',
	MEOW_BASE_CLASS . 'cli\\jail',
	array(
		'before_invoke'=>function() {
			if (is_multisite()) {
				WP_CLI::error(__('This plugin cannot be used on Multi-Site.', 'apocalypse-meow'));
			}

			if (!options::is_pro()) {
				WP_CLI::error(__('A premium license is required.', 'apocalypse-meow'));
			}

			global $wp_filesystem;
			WP_Filesystem();
		},
	)
);

/**
 * Login Jail
 *
 * The primary function of Apocalypse Meow is to detect and mitigate
 * brute-force login attacks being conducted against the site. This is
 * accomplished by tracking failed login attempts and temporarily
 * banning offending network addresses.
 *
 * The jail is where offenders go to wait out their sentence.
 *
 * These commands allow for viewing, exporting, and altering the current
 * jail, as well as managing the global whitelist.
 */
class jail extends \WP_CLI_Command {

	/**
	 * List of Banned Networks
	 *
	 * Network addresses responsible for too many failed login attempts
	 * within a certain window of time are temporarily jailed to prevent
	 * further attempts.
	 *
	 * Use this function to display or export a list of each IP address
	 * and subnet currently banned.
	 *
	 * ## OPTIONS
	 *
	 * [--relative]
	 * : Show expiration relative to now.
	 *
	 * [--export=<path>]
	 * : Dump the results to a CSV at <path>.
	 *
	 * [--overwrite]
	 * : Overwrite <path> if it exists.
	 *
	 * @param array $args N/A.
	 * @param array $assoc_args Flags.
	 * @return bool True.
	 *
	 * @subcommand list
	 */
	public function _list($args=null, $assoc_args=array()) {
		global $wpdb;
		global $wp_filesystem;

		$args = null;
		$export = Utils\get_flag_value($assoc_args, 'export');
		$overwrite = !!Utils\get_flag_value($assoc_args, 'overwrite');
		$relative = !!Utils\get_flag_value($assoc_args, 'relative');

		// The search.
		$cutoff = current_time('mysql');
		$dbResult = $wpdb->get_results("
			SELECT
				`ip`,
				`subnet`,
				`date_created`,
				`date_expires`,
				`count`,
				`community`
			FROM `{$wpdb->prefix}meow2_log`
			WHERE
				`date_expires` > '$cutoff' AND
				`pardoned`=0
			ORDER BY `date_expires` ASC
		", ARRAY_A);
		if (!is_array($dbResult) || !count($dbResult)) {
			WP_CLI::success(
				__('The jail is currently empty!', 'apocalypse-meow')
			);
			return true;
		}

		// Crunch the data.
		$data = array();
		foreach ($dbResult as $Row) {
			if ('0' === $Row['ip']) {
				$Row['ip'] = '';
			}
			if ('0' === $Row['subnet']) {
				$Row['subnet'] = '';
			}

			$Row['count'] = (int) $Row['count'];
			$Row['community'] = (int) $Row['community'];

			$data[] = array(
				'Created'=>$Row['date_created'],
				'Expires'=>$relative ? human_time_diff(strtotime($Row['date_expires']), current_time('timestamp')) : $Row['date_expires'],
				'IP'=>$Row['ip'],
				'Subnet'=>$Row['subnet'],
				'Persistence'=>$Row['count'] > 1 ? $Row['count'] : '',
				'Community'=>$Row['community'] ? __('Yes', 'apocalypse-meow') : ''
			);
		}

		// Format CLI output.
		$headers = array(
			__('Created', 'apocalypse-meow'),
			__('Expires', 'apocalypse-meow'),
			__('IP', 'apocalypse-meow'),
			__('Subnet', 'apocalypse-meow'),
			__('Persistence', 'apocalypse-meow'),
			__('Community', 'apocalypse-meow')
		);

		WP_CLI\Utils\format_items('table', $data, $headers);

		WP_CLI::success(
			common\format::inflect(
				count($data),
				__('There is currently %d banned network.', 'apocalypse-meow'),
				__('There are currently %d banned networks.', 'apocalypse-meow')
			)
		);

		// Try to kick it into a file!
		if ($export) {
			common\ref\file::path($export, false);
			if (!$export || !preg_match('/\.csv$/i', $export)) {
				WP_CLI::error(
					__('The export file path is not valid.', 'apocalypse-meow')
				);
			}

			if (!$overwrite && $wp_filesystem->exists($export)) {
				WP_CLI::error(
					"$export " . __('already exists. Use --overwrite to replace it.', 'apocalypse-meow')
				);
			}

			$csv = common\format::to_csv($data, $headers);
			$wp_filesystem->put_contents($export, $csv, FS_CHMOD_FILE);
			if ($wp_filesystem->exists($export)) {
				WP_CLI::success(
					__('The data has been saved to', 'apocalypse-meow') . " $export."
				);
			}
			else {
				WP_CLI::error(
					__('The data could not be written to', 'apocalypse-meow') . " $export."
				);
			}
		}

		return true;
	}

	/**
	 * Ban a Network
	 *
	 * Use this function to manually block an IP address or subnet from
	 * the login form for a specified period of time.
	 *
	 * ## OPTIONS
	 *
	 * <IP|Subnet>...
	 * : One or more network addresses to ban.
	 *
	 * [--expires=<datetime>]
	 * : An expiration date. If omitted, the expiration will be set
	 * according to the fail window.
	 *
	 * @param array $args N/A.
	 * @param array $assoc_args Flags.
	 * @return bool True.
	 */
	public function add($args=null, $assoc_args=array()) {
		global $wpdb;

		// First pass at the arguments.
		$bans = array();
		foreach ($args as $v) {
			$v = preg_replace('/\s/u', '', $v);
			if (!$v) {
				continue;
			}
			// A likely subnet.
			elseif (false !== strpos($v, '/')) {
				login::sanitize_subnet($v);
				if ($v) {
					$bans[] = $v;
				}
			}
			else {
				common\ref\sanitize::ip($v);
				if ($v) {
					$bans[] = $v;
				}
			}
		}
		$bans = array_unique($bans);

		// Second pass, take a closer look.
		foreach ($bans as $k=>$v) {
			if (apply_filters('meow_is_banned', false, $v)) {
				WP_CLI::warning(
					"$v " . __('is already banned.', 'apocalypse-meow')
				);
				unset($bans[$k]);
				continue;
			}

			$ip = $v;
			if (false !== strpos($v, '/')) {
				list($ip,$b) = explode('/', $v);
			}

			if (apply_filters('meow_is_whitelisted', false, $ip)) {
				WP_CLI::warning(
					"$v " . __('cannot be banned because it is either whitelisted or belongs to the server.', 'apocalypse-meow')
				);
				unset($bans[$k]);
				continue;
			}
		}

		if (!count($bans)) {
			WP_CLI::error(
				__('At least one valid network address is required.', 'apocalypse-meow')
			);
		}

		// Work out the expiration.
		$expires = common\sanitize::datetime(Utils\get_flag_value($assoc_args, 'expires'));
		if ($expires <= current_time('mysql') || ('0000-00-00 00:00:00' === $expires)) {
			$fail_window = options::get('login-fail_window');
			$expires = date('Y-m-d H:i:s', strtotime("+$fail_window seconds", current_time('timestamp')));
		}

		// Add them!
		sort($bans);
		foreach ($bans as $v) {
			$data = array(
				'date_created'=>current_time('mysql'),
				'date_expires'=>$expires,
				'ip'=>'',
				'subnet'=>'',
				'type'=>'ban'
			);
			if (false !== strpos($v, '/')) {
				$data['subnet'] = $v;
				$data['ip'] = 0;
			}
			else {
				$data['subnet'] = 0;
				$data['ip'] = $v;
			}

			$wpdb->insert(
				"{$wpdb->prefix}meow2_log",
				$data,
				'%s'
			);
		}

		WP_CLI::success(
			common\format::inflect(
				count($bans),
				__('%d network has been banned.', 'apocalypse-meow'),
				__('%d networks have been banned.', 'apocalypse-meow')
			)
		);
		return true;
	}

	/**
	 * Unblock a Network Address
	 *
	 * Use this function to manually remove (or "pardon") an IP address
	 * or subnet that is currently banned from logging into the site.
	 *
	 * ## OPTIONS
	 *
	 * <IP|Subnet>...
	 * : One or more network addresses to pardon.
	 *
	 * @param array $args N/A.
	 * @return bool True.
	 */
	public function remove($args=null) {
		global $wpdb;

		// First pass at the arguments.
		$bans = array();
		foreach ($args as $v) {
			$v = preg_replace('/\s/u', '', $v);
			if (!$v) {
				continue;
			}
			// A likely subnet.
			elseif (false !== strpos($v, '/')) {
				login::sanitize_subnet($v);
			}
			else {
				common\ref\sanitize::ip($v);
			}

			if ($v) {
				$bans[] = $v;
			}
		}
		$bans = array_unique($bans);

		// Second pass, take a closer look.
		foreach ($bans as $k=>$v) {
			if (!apply_filters('meow_is_banned', false, $v)) {
				WP_CLI::warning(
					"$v " . __('is not currently banned.', 'apocalypse-meow')
				);
				unset($bans[$k]);
				continue;
			}
		}

		if (!count($bans)) {
			WP_CLI::error(
				__('At least one valid network address is required.', 'apocalypse-meow')
			);
		}

		// Pardon them!
		sort($bans);
		foreach ($bans as $v) {
			$data = array(
				'date_expires'=>current_time('mysql'),
				'pardoned'=>1,
			);

			$where = array('type'=>'ban');
			if (false !== strpos($v, '/')) {
				$where['subnet'] = $v;
			}
			else {
				$where['ip'] = $v;
			}

			$wpdb->update(
				"{$wpdb->prefix}meow2_log",
				$data,
				$where,
				array('%s','%d'),
				'%s'
			);
		}

		WP_CLI::success(
			common\format::inflect(
				count($bans),
				__('%d ban has been pardoned.', 'apocalypse-meow'),
				__('%d bans have been pardoned.', 'apocalypse-meow')
			)
		);
		return true;
	}

	/**
	 * Whitelist a Network Address
	 *
	 * A global whitelist allows individual IP addresses or ranges to
	 * be exempted from the automatic brute-force login detection and
	 * ban policy.
	 *
	 * Because bans work at the level of an individual IP address,
	 * it is important to whitelist shared networks, like offices,
	 * otherwise a few simultaneous failures from a couple coworkers
	 * could prevent everyone from getting in.
	 *
	 * Use this function to add one or more networks to this list.
	 *
	 * ## OPTIONS
	 *
	 * <IP|Subnet>...
	 * : One or more network addresses.
	 *
	 * @param array $args N/A.
	 * @return bool True.
	 */
	public function whitelist($args=null) {
		$old = options::get('login');
		$new = $old;
		$new['exempt'] = array_merge($old['exempt'], $args);
		options::save('login', $new);
		$new = options::get('login');

		$changed = count($new['exempt']) - count($old['exempt']);

		if ($changed <= 0) {
			WP_CLI::warning(
				__('No changes were made to the whitelist.', 'apocalypse-meow')
			);
		}
		else {
			WP_CLI::success(
				common\format::inflect(
					$changed,
					__('%d network was added to the whitelist.', 'apocalypse-meow'),
					__('%d networks were added to the whitelist.', 'apocalypse-meow')
				)
			);
		}

		return true;
	}

	/**
	 * Remove a Network Address From the Whitelist
	 *
	 * Use this function to remove one or more networks from the global
	 * whitelist. Once removed, they will once more be subject to the
	 * brute-force policity and possible banning.
	 *
	 * ## OPTIONS
	 *
	 * <IP|Subnet>...
	 * : One or more network addresses.
	 *
	 * @param array $args N/A.
	 * @return bool True.
	 */
	public function blacklist($args=null) {

		// Before we get started, we need to sanitize the arguments to
		// ensure we are comparing apples to apples.
		// The whitelist is a little tedious.
		foreach ($args as $k=>$v) {
			$args[$k] = preg_replace('/[^\da-f\.\:\/\-]/i', '', $v);

			// A regular IP.
			if (filter_var($args[$k], FILTER_VALIDATE_IP)) {
				common\ref\sanitize::ip($args[$k], false);
				if (!$args[$k]) {
					unset($args[$k]);
				}
				continue;
			}

			// An arbitrary range?
			if (1 === substr_count($args[$k], '-')) {
				$tmp = explode('-', $args[$k]);
				common\ref\sanitize::ip($tmp[0], false);
				common\ref\sanitize::ip($tmp[1], false);

				// One of the IPs is bad.
				if (!$tmp[0] || !$tmp[1]) {
					unset($args[$k]);
					continue;
				}

				// The same? No range.
				if ($tmp[0] === $tmp[1]) {
					$args[$k] = $tmp[0];
				}
				else {
					// Fix order?
					if (common\format::ip_to_number($tmp[0]) > common\format::ip_to_number($tmp[1])) {
						common\data::switcheroo($tmp[0], $tmp[1]);
					}
					$args[$k] = implode('-', $tmp);
				}

				continue;
			}

			// A CIDR?
			if (false !== ($range = common\format::cidr_to_range($args[$k]))) {
				list($ip, $bits) = explode('/', $args[$k]);
				$ip = common\sanitize::ip($range['min'], false);

				// Continue if the IP is valid, at least.
				if ($ip) {
					common\ref\cast::to_int($bits, true);
					$max = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? 32 : 128;
					common\ref\sanitize::to_range($bits, 0, $max);
					$args[$k] = "{$ip}/{$bits}";
					continue;
				}

				unset($args[$k]);
				continue;
			}

			unset($args[$k]);
		}

		if (!count($args)) {
			WP_CLI::error(
				__('At least one valid network address is required.', 'apocalypse-meow')
			);
		}

		$args = array_unique($args);
		sort($args);

		$old = options::get('login');
		$new = $old;
		$new['exempt'] = array_diff($old['exempt'], $args);
		options::save('login', $new);
		$new = options::get('login');

		$changed = count($old['exempt']) - count($new['exempt']);

		if ($changed <= 0) {
			WP_CLI::warning(
				__('No changes were made to the whitelist.', 'apocalypse-meow')
			);
		}
		else {
			WP_CLI::success(
				common\format::inflect(
					$changed,
					__('%d network was removed from the whitelist.', 'apocalypse-meow'),
					__('%d networks were removed from the whitelist.', 'apocalypse-meow')
				)
			);
		}

		return true;
	}

}
