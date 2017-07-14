<?php
/**
 * Admin: Settings
 *
 * @package Apocalypse Meow
 * @author  Blobfolio, LLC <hello@blobfolio.com>
 */

/**
 * Do not execute this file directly.
 */
if (!defined('ABSPATH')) {
	exit;
}

use \blobfolio\wp\meow\about;
use \blobfolio\wp\meow\ajax;
use \blobfolio\wp\meow\core;
use \blobfolio\wp\meow\login;
use \blobfolio\wp\meow\options;
use \blobfolio\wp\meow\vendor\common;

$current_user = wp_get_current_user();

$data = array(
	'forms'=>array(
		'settings'=>array(
			'action'=>'meow_ajax_settings',
			'n'=>ajax::get_nonce(),
			'errors'=>array(),
			'saved'=>false,
			'loading'=>false
		)
	),
	'readonly'=>options::get_readonly(),
	'section'=>'settings',
	'modal'=>false,
	// @codingStandardsIgnoreStart
	'modals'=>array(
		'brute-force'=>array(
			sprintf(
				esc_html__('%s robots visit WordPress dozens of times each day, attempting to guess their way into wp-admin. WordPress makes no attempt to mitigate this, allowing a single robot to try combination after combination until they succeed.', 'apocalypse-meow'),
				'<a href="https://en.wikipedia.org/wiki/Brute-force_attack" target="_blank" rel="noopener">' . esc_html__('Brute-force', 'apocalypse-meow') . '</a>'
			),
			esc_html__('Apocalypse Meow keeps track of login attempts and will temporarily ban any person or robot who has failed too much, too fast. This is critical set-and-forget protection.', 'apocalypse-meow')
		),
		'login-fail_limit'=>array(
			esc_html__('This is the maximum number of login failures allowed for a given IP before the login process is disabled for that individual.', 'apocalypse-meow')
		),
		'login-subnet_fail_limit'=>array(
			sprintf(
				esc_html__('Sometimes attacks come from multiple IPs on the same network. This limit applies to the number of failures attributed to a network subnet (%s for IPv4 and %s for IPv6). It is recommended you set this value 4-5x higher than the individual fail limit.', 'apocalypse-meow'),
				'<code>/24</code>',
				'<code>/64</code>'
			)
		),
		'login-fail_window'=>array(
			esc_html__('An individual IP or entire network subnet will be banned from logging in whenever their total number of failures within this window exceeds the fail limits.', 'apocalypse-meow'),
			esc_html__('The ban lasts as long as this is true, and will reset when the earliest of the counted failures grows old enough to fall outside the window. Remaining failures, if any, that are still within the window will continue to be matched against the fail limits.', 'apocalypse-meow'),
			esc_html__('For reference, the default value of 720 translates to 12 hours.')
		),
		'login-reset_on_success'=>array(
			esc_html__('When someone successfully logs in, their prior failures are no longer counted against them, even if those failures are still within the window.', 'apocalypse-meow'),
		),
		'login-key'=>array(
			sprintf(
				esc_html__("Most servers report the remote visitor's IP address using the %s key, but if yours is living behind a proxy, the IP information might live somewhere else. If you aren't sure what to do, look for your IP address in the list.", 'apocalypse-meow'),
				'<code>REMOTE_ADDR</code>'
			),
			esc_html__('Note: visitor IP information forwarded from a proxy is not trustworthy because it is populated via request headers, which can be forged. Depending on the setup of your particular environment, this may make it impossible to effectively mitigate brute-force attacks.', 'apocalypse-meow')
		),
		'login-exempt'=>array(
			esc_html__('It is very important you avoid getting yourself or your coworkers banned (the latter happens frequently in office environments where multiple employees fail around the same time). You should whitelist any IP addresses, ranges, or subnets from which you will be connecting.', 'apocalypse-meow'),
		),
		'login-nonce'=>array(
			sprintf(
				esc_html__('This option adds a hidden field to the standard %s form to help ensure that login attempts are actually originating there (rather than coming out of the blue, as is typical of robotic assaults).', 'apocalypse-meow'),
				'<code>wp-login.php</code>'
			),
			esc_html__('*Do not* enable this option if your site uses custom login forms or if the login page is cached.', 'apocalypse-meow'),
			esc_html__('Note: stale cookies from past visits might ocassionally trigger a Nonce failure on the first login attempt. WordPress should refresh the Nonce value during that first post, so it should work on the second go-around.', 'apocalypse-meow')
		),
		'login-alert_on_new'=>array(
			esc_html__('This will send an email to the account user whenever access is granted to an IP address that has not successfully logged in before.', 'apocalypse-meow'),
			esc_html__('Note: this depends on the data logged by the plugin, so if you have configured a short retention time, it may not be very useful.', 'apocalypse-meow')
		),
		'login-alert_by_subnet'=>array(
			esc_html__('This will cause the email alert function to use subnets rather than individual IPs when determining "newness". This setting is recommended for IPv6 users in particular as their IPs will change frequently.', 'apocalypse-meow'),
		),
		'passwords'=>array(
			esc_html__('Strong, unique passwords are critical for security. For historical reasons, WordPress still allows users to choose unsafe passwords for themselves. These options set some basic boundaries.', 'apocalypse-meow'),
			esc_html__('Note: because WordPress passwords are encrypted, it is not possible to apply these settings retroactively. However when users log in, if their passwords are unsafe, they will be directed to change it.')
		),
		'password-alpha'=>array(
			esc_html__('Whether or not a password must have letters in it. The third option, "UPPER & lower", requires a password contain a mixture of both upper- and lowercase letters.', 'apocalypse-meow'),
		),
		'password-numeric'=>array(
			esc_html__('Whether or not a password must have numbers in it.', 'apocalypse-meow'),
		),
		'password-symbol'=>array(
			esc_html__('Whether or not a password must have non-alphanumeric characters in it, like a cartoon curse word: $!#*()%.', 'apocalypse-meow'),
		),
		'password-length'=>array(
			esc_html__("This sets a minimum length requirement for passwords. The plugin's own minimum minimum (how low you are allowed to set it) is subject to change as technology advances. If your entry falls below the times, it will be adjusted automatically.", 'apocalypse-meow'),
		),
		'password-common'=>array(
			esc_html__('Apocalypse Meow automatically prevents users from choosing any of the top 100K most common passwords. This protection is mandatory and cannot be disabled. ;)', 'apocalypse-meow'),
		),
		'core'=>array(
			esc_html__('Out-of-the-Box, certain WordPress features and frontend oversights on the part of theme and plugin developers, can inadvertently place sites at greater risk of being successfully exploited by a hacker.', 'apocalypse-meow'),
			esc_html__('Please make sure you read about each option before flipping any switches. While each of these items mitigates a threat, some threats are more threatening than others, and if your workflow depends on something you disable here, that might make life sad.', 'apocalypse-meow')
		),
		'core-wp_rest'=>array(
			sprintf(
				esc_html__('%s is a new feature within WordPress that provides %s API access to view or update site content. Depending on who you ask, this is either a dream come true or a complete shitshow.', 'apocalypse-meow'),
				'<a href="http://v2.wp-api.org/" target="_blank" rel="noopener">WP-REST</a>',
				'<a href="https://en.wikipedia.org/wiki/Representational_state_transfer" target="_blank" rel="noopener">RESTful</a>'
			),
			esc_html__("But hackers universally love it. Not only does it vastly streamline reconnaissance, it also extends the software's attack surface, providing all new avenues for exploitation.", 'apocalypse-meow'),
			esc_html__("WordPress no longer allows WP-REST to be fully disabled, so Apocalypse Meow gives you the next best thing: the ability to restrict *access* to it. If you aren't using this feature at all, please set the access to \"Nobody\" to limit your exposure.", 'apocalypse-meow')
		),
		'template-adjacent_posts'=>array(
			esc_html__("WordPress adds information about next and previous posts in the HTML <head>. This isn't usually a big deal, but can help robots find pages you thought were private. This is just robot food, so you can safely remove it.", 'apocalypse-meow')
		),
		'core-file_edit'=>array(
			esc_html__('WordPress comes with the ability to edit theme and plugin files directly through the browser. If an attacker gains access to WordPress, they too can make such changes.', 'apocalypse-meow'),
			esc_html__('Please just use FTP to push code changes to the site. Haha.', 'apocalypse-meow'),
			sprintf(
				esc_html('Note: This will have no effect if the %s constant is defined elsewhere.', 'apocalypse-meow'),
				'<code>DISALLOW_FILE_EDIT</code>'
			)
		),
		'template-generator_tag'=>array(
			esc_html__('By default, WordPress embeds a version tag in the HTML <head>. While this information is largely innocuous (and discoverable elsewhere), it can help nogoodniks better target attacks against your site. Since this is really only something a robot would see, it is safe to remove.', 'apocalypse-meow')
		),
		'template-readme'=>array(
			esc_html__('WordPress releases include a publicly accessible file detailing the version information. This is one of the first things a hacker will look for as it will help them better target their attacks.', 'apocalypse-meow'),
			(
				@file_exists(trailingslashit(ABSPATH) . 'readme.html') ? sprintf(
						esc_html__('Click %s to view yours.', 'apocalypse-meow'),
						'<a href="' . esc_url(site_url('readme.html')) . '" target="_blank" rel="noopener">' . esc_html__('here', 'apocalypse-meow') . '</a>'
					) : esc_html__('Your site does not have one right now. Woo!', 'apocalypse-meow')
			)
		),
		'template-noopener'=>array(
			sprintf(
				esc_html__("Any links on your site that open in a new window (e.g. %s) could potentially trigger a redirect in *your* site's window. This opens the door to some sneaky phishing attacks. See %s and %s for more information.", 'apocalypse-meow'),
				'<code>target="blank"</code>',
				'<a href="https://dev.to/ben/the-targetblank-vulnerability-by-example" target="_blank" rel="noopener">' . esc_html__('here', 'apocalypse-meow') . '</a>',
				'<a href="https://mathiasbynens.github.io/rel-noopener/" target="_blank" rel="noopener">' . esc_html__('here', 'apocalypse-meow') . '</a>'
			),
			sprintf(
				esc_html__("This option adds %s to vulnerable links on your site, which is meant to disable this capability. It is a lightweight and non-destructive approach, but doesn't protect all browsers.", 'apocalypse-meow'),
				'<code>rel="noopener"</code>'
			),
			sprintf(
				esc_html__('For a more comprehensive solution, take a look at %s.', 'apocalypse-meow'),
				'<a href="https://github.com/danielstjules/blankshield" target="_blank" rel="noopener">blankshield</a>'
			)
		),
		'enumeration'=>array(
			sprintf(
				esc_html__("Ever wonder how a robot guessed your username? There's a neat trick that exploits a weakness in WordPress' permalink rewriting: visit %s and you should be redirected to a pretty URL ending in your username (unless Apocalypse Meow stops it). Robots simply try %s, %s, etc.", 'apocalypse-meow'),
				'<a href="' . site_url('?author=' . $current_user->ID) . '" target="_blank" rel="noopener">' . site_url('?author=' . $current_user->ID) . '</a>',
				'<code>?author=1</code>',
				'<code>?author=2</code>'
			),
		),
		'core-enumeration'=>array(
			sprintf(
				esc_html__("This setting blacklists the %s query variable so it cannot be used by robots… or anyone. Do not enable this setting if any of your themes or plugins lazy-link to an author's ID instead of their actual archive URL.", 'apocalypse-meow'),
				'<code>author</code>'
			),
			sprintf(
				esc_html__('Note: this setting will also disable the WP-REST %s endpoint in WordPress versions 4.7+. To restrict API requests for user information in earlier versions, alter the WP-REST access setting at the top of this section.', 'apocalypse-meow'),
				'<code>users</code>'
			)
		),
		'core-enumeration_die'=>array(
			sprintf(
				esc_html__('By default, this plugin simply redirects any %s requests to the home page. But if you enable this option, it will instead trigger a 400 error and exit. This approach uses fewer resources and can more easily integrate with general log-monitoring policies.', 'apocalypse-meow'),
				'<code>?author=X</code>'
			),
			esc_html__('Note: WP-REST requests will always result in an API error.', 'apocalypse-meow')
		),
		'core-enumeration_fail'=>array(
			esc_html__('When enabled, user enumeration attempts will be counted as login failures. You probably want to enable this as user enumeration usually precedes a login attack.', 'apocalypse-meow'),
			sprintf(
				esc_html__('For tracking purposes, the "username" for these log entries will always read "%s".', 'apocalypse-meow'),
				core::ENUMERATION_USERNAME
			),
		),
		'core-xmlrpc'=>array(
			sprintf(
				esc_html__("WordPress comes with an %s API to let users manage their blog content from mobile apps and other web sites. This is good stuff, but is also a common (and powerful) entry point for hackers. If you aren't using it, disable it.", 'apocalypse-meow'),
				'<a href="https://codex.wordpress.org/XML-RPC_Support" target="_blank" rel="noopener">XML-RPC</a>'
			),
			sprintf(
				esc_html__('Some plugins, like %s, will not work correctly with XML-RPC disabled. If something breaks, just re-enable it.', 'apocalypse-meow'),
				'<a href="https://wordpress.org/plugins/jetpack/" target="_blank" rel="noopener">Jetpack</a>'
			)
		),
		'prune'=>array(
			esc_html__('Brute-force login prevention relies on record-keeping. Over time, with lots of activity, that data might start to pose storage or performance problems. Apocalypse Meow can be configured to automatically remove old data.', 'apocalypse-meow'),
		),
		'prune-active'=>array(
			esc_html__('Enable this option to ease your server of the burden of keeping indefinite login activity records.', 'apocalypse-meow')
		),
		'prune-limit'=>array(
			esc_html__("Data older than this will be automatically pruned. It's a balance. Don't be too stingy or features like New Login Alerts won't be as effective. For most sites, it is a good idea to maintain at least 3 months worth of data.", 'apocalypse-meow')
		)
	)
	// @codingStandardsIgnoreEnd
);



$options = options::get();
foreach ($options as $k=>$v) {
	// Everything but license.
	if ('license' === $k) {
		continue;
	}

	// We need to convert any boolean values to integers to keep Vue.js
	// happy.
	foreach ($v as $k2=>$v2) {
		if (is_bool($v2)) {
			$v[$k2] = $v2 ? 1 : 0;
		}
	}

	$data['forms']['settings'][$k] = $v;
}

// The fail window gets translated to minutes to make the numbers
// easier to deal with.
$data['forms']['settings']['login']['fail_window'] = ceil($data['forms']['settings']['login']['fail_window'] / 60);

// The whitelist needs to be collapsed.
$data['forms']['settings']['login']['exempt'] = trim(implode("\n", $data['forms']['settings']['login']['exempt']));

?><div class="wrap" id="vue-settings" data-env="<?php echo esc_attr(json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)); ?>" v-cloak>
	<h1>Apocalypse Meow: <?php echo esc_html__('Settings', 'apocalypse-meow'); ?></h1>



	<div class="updated" v-if="forms.settings.saved"><p><?php echo esc_html__('Your settings have been saved!', 'apocalypse-meow'); ?></p></div>
	<div class="error" v-for="error in forms.settings.errors"><p>{{error}}</p></div>



	<p>&nbsp;</p>
	<h3 class="nav-tab-wrapper">
		<a style="cursor: pointer;" class="nav-tab" v-bind:class="{'nav-tab-active' : section === 'settings'}" v-on:click.prevent="toggleSection('settings')"><?php echo esc_html__('Settings', 'apocalypse-meow'); ?></a>

		<a style="cursor: pointer;" class="nav-tab" v-bind:class="{'nav-tab-active' : section === 'community'}" v-on:click.prevent="toggleSection('community')"><?php echo esc_html__('Community Pool', 'apocalypse-meow'); ?></a>

		<?php if (options::is_pro()) { ?>
			<a style="cursor: pointer;" class="nav-tab" v-bind:class="{'nav-tab-active' : section === 'wp-config'}" v-on:click.prevent="toggleSection('wp-config')"><?php echo esc_html__('WP-Config', 'apocalypse-meow'); ?></a>
		<?php } ?>
	</h3>




	<!-- ==============================================
	MAIN SETTINGS
	=============================================== -->
	<form v-if="section === 'settings'" method="post" action="<?php echo admin_url('admin-ajax.php'); ?>" v-form name="settings" v-on:submit.prevent="settingsSubmit">
		<div id="poststuff">
			<div id="post-body" class="metabox-holder meow-columns one-two fixed fluid">

				<!-- Settings -->
				<div class="postbox-container two">

					<!-- ==============================================
					BRUTE FORCE
					=============================================== -->
					<div class="meow-fluid-tile">
						<div class="postbox">
							<h3 class="hndle">
								<?php echo esc_html__('Brute-Force Protection', 'apocalypse-meow'); ?>
								<span class="dashicons dashicons-editor-help meow-info-toggle" v-bind:class="{'is-active' : modal === 'brute-force'}" v-on:click.prevent="toggleModal('brute-force')"></span>
							</h3>
							<div class="inside">

								<div class="meow-fieldset inline">
									<label for="login-fail_limit"><?php echo esc_html__('Fail Limit', 'apocalypse-meow'); ?></label>

									<span class="dashicons dashicons-editor-help meow-info-toggle" v-bind:class="{'is-active' : modal === 'login-fail_limit'}" v-on:click.prevent="toggleModal('login-fail_limit')"></span>

									<input type="number" id="login-fail_limit" v-model.number="forms.settings.login.fail_limit" min="3" max="50" step="1" v-bind:readonly="readonly.indexOf('login-fail_limit') !== -1" required />
								</div>

								<div class="meow-fieldset inline">
									<label for="login-subnet_fail_limit"><?php echo esc_html__('Subnet Fail Limit', 'apocalypse-meow'); ?></label>

									<span class="dashicons dashicons-editor-help meow-info-toggle" v-bind:class="{'is-active' : modal === 'login-subnet_fail_limit'}" v-on:click.prevent="toggleModal('login-subnet_fail_limit')"></span>

									<input type="number" id="login-subnet_fail_limit" v-model.number="forms.settings.login.subnet_fail_limit" min="10" max="100" step="1" v-bind:readonly="readonly.indexOf('login-subnet_fail_limit') !== -1" required />
								</div>

								<div class="meow-fieldset inline">
									<label for="login-fail_window"><?php echo esc_html__('Fail Window', 'apocalypse-meow'); ?></label>

									<span class="dashicons dashicons-editor-help meow-info-toggle" v-bind:class="{'is-active' : modal === 'login-fail_window'}" v-on:click.prevent="toggleModal('login-fail_window')"></span>

									<input type="number" id="login-fail_window" v-model.number="forms.settings.login.fail_window" min="10" max="1440" step="1" v-bind:readonly="readonly.indexOf('login-fail_window') !== -1" required />

									<span class="meow-fg-grey">&nbsp;<?php echo esc_html__('minutes', 'apocalypse-meow'); ?></span>
								</div>

								<div class="meow-fieldset inline">
									<label for="login-reset_on_success">
										<input type="checkbox" id="login-reset_on_success" v-model.number="forms.settings.login.reset_on_success" v-bind:true-value="1" v-bind:false-value="0" v-bind:disabled="readonly.indexOf('login-reset_on_success') !== -1" />
										<?php echo esc_html__('Reset on Success', 'apocalypse-meow'); ?>
									</label>

									<span class="dashicons dashicons-editor-help meow-info-toggle" v-bind:class="{'is-active' : modal === 'login-reset_on_success'}" v-on:click.prevent="toggleModal('login-reset_on_success')"></span>
								</div>

								<?php
								$keys = login::get_server_keys();
								if (count($keys) > 1) {
									?>
									<div class="meow-fieldset inline">
										<label for="login-key"><?php echo esc_html__('Remote IP/Proxy', 'apocalypse-meow'); ?></label>

										<span class="dashicons dashicons-editor-help meow-info-toggle" v-bind:class="{'is-active' : modal === 'login-key'}" v-on:click.prevent="toggleModal('login-key')"></span>

										<select id="login-key" v-model.trim="forms.settings.login.key" v-bind:disabled="readonly.indexOf('login-key') !== -1">
											<?php
											foreach ($keys as $k=>$v) {
												echo '<option value="' . esc_attr($k) . '">' . esc_attr("$k - $v") . '</option>';
											}
											?>
										</select>
									</div>
									<?php
								}
								?>

								<div class="meow-fieldset outline">
									<p>
										<label for="login-exempt"><?php echo esc_html__('Whitelist', 'apocalypse-meow'); ?></label>

										<span class="dashicons dashicons-editor-help meow-info-toggle" v-bind:class="{'is-active' : modal === 'login-exempt'}" v-on:click.prevent="toggleModal('login-exempt')"></span>
									</p>

									<p><textarea id="login-exempt" v-model.trim="forms.settings.login.exempt"></textarea></p>

									<p class="description">
										<?php echo esc_html__('Enter an IP or range, one per line. Accepted formats:', 'apocalypse-meow'); ?><br>
										<code>127.0.0.1</code>,<br>
										<code>127.0.0.0/24</code>,<br>
										<code>127.0.0.1-127.0.0.10</code>
									</p>

									<?php
									$ip = login::get_visitor_ip();
									$subnet = login::get_visitor_subnet();
									?>
									<p class="description">
										<?php if ($ip) { ?>
											<?php echo esc_html__('Your IP address is', 'apocalypse-meow'); ?>
											<strong><code><?php echo $ip; ?></code></strong><br>
											<?php echo esc_html__('Your network subnet is', 'apocalypse-meow'); ?>
											<strong><code><?php echo $subnet; ?></code></strong><br>
										<?php
										}
										else {
											echo esc_html__('Your IP address cannot be determined right now. That either means you are on the same network as the server, or the proxy key is not correct.', 'apocalypse-meow');
										}
										?>
									</p>
								</div>

								<div class="meow-fieldset inline">
									<label for="login-nonce">
										<input type="checkbox" id="login-nonce" v-model.number="forms.settings.login.nonce" v-bind:true-value="1" v-bind:false-value="0" v-bind:disabled="readonly.indexOf('login-nonce') !== -1" />
										<?php echo esc_html__('Add Login Nonce', 'apocalypse-meow'); ?>
									</label>

									<span class="dashicons dashicons-editor-help meow-info-toggle" v-bind:class="{'is-active' : modal === 'login-nonce'}" v-on:click.prevent="toggleModal('login-nonce')"></span>
								</div>

								<div class="meow-fieldset inline">
									<label for="login-alert_on_new">
										<input type="checkbox" id="login-alert_on_new" v-model.number="forms.settings.login.alert_on_new" v-bind:true-value="1" v-bind:false-value="0" v-bind:disabled="readonly.indexOf('login-alert_on_new') !== -1" />
										<?php echo esc_html__('Email Alert: New Login IP', 'apocalypse-meow'); ?>
									</label>

									<span class="dashicons dashicons-editor-help meow-info-toggle" v-bind:class="{'is-active' : modal === 'login-alert_on_new'}" v-on:click.prevent="toggleModal('login-alert_on_new')"></span>
								</div>

								<div class="meow-fieldset inline" v-if="forms.settings.login.alert_on_new">
									<label for="login-alert_by_subnet">
										<input type="checkbox" id="login-alert_by_subnet" v-model.number="forms.settings.login.alert_by_subnet" v-bind:true-value="1" v-bind:false-value="0" v-bind:disabled="readonly.indexOf('login-alert_by_subnet') !== -1" />
										<?php echo esc_html__('Email Alert: New Subnet Only', 'apocalypse-meow'); ?>
									</label>

									<span class="dashicons dashicons-editor-help meow-info-toggle" v-bind:class="{'is-active' : modal === 'login-alert_by_subnet'}" v-on:click.prevent="toggleModal('login-alert_by_subnet')"></span>
								</div>

							</div>
						</div><!--brute force-->
					</div>



					<!-- ==============================================
					DATA RETENTION
					=============================================== -->
					<div class="meow-fluid-tile">
						<div class="postbox">
							<h3 class="hndle">
								<?php echo esc_html__('Data Retention', 'apocalypse-meow'); ?>
								<span class="dashicons dashicons-editor-help meow-info-toggle" v-bind:class="{'is-active' : modal === 'prune'}" v-on:click.prevent="toggleModal('prune')"></span>
							</h3>
							<div class="inside">
								<div class="meow-fieldset inline">
									<label for="prune-active">
										<input type="checkbox" id="prune-active" v-model.number="forms.settings.prune.active" v-bind:true-value="1" v-bind:false-value="0" v-bind:disabled="readonly.indexOf('prune-active') !== -1" />
										<?php echo esc_html__('Prune Old Data', 'apocalypse-meow'); ?>
									</label>

									<span class="dashicons dashicons-editor-help meow-info-toggle" v-bind:class="{'is-active' : modal === 'prune-active'}" v-on:click.prevent="toggleModal('prune-active')"></span>
								</div>

								<div class="meow-fieldset inline" v-if="forms.settings.prune.active">
									<label for="prune-limit"><?php echo esc_html__('Data Expiration', 'apocalypse-meow'); ?></label>

									<span class="dashicons dashicons-editor-help meow-info-toggle" v-bind:class="{'is-active' : modal === 'prune-limit'}" v-on:click.prevent="toggleModal('prune-limit')"></span>

									<input type="number" id="prune-limit" v-model.number="forms.settings.prune.limit" min="30" max="365" step="1" v-bind:readonly="readonly.indexOf('prune-limit') !== -1" required />

									<span class="meow-fg-grey">&nbsp;<?php echo esc_html__('days', 'apocalypse-meow'); ?></span>
								</div>
							</div>
						</div>
					</div>



					<!-- ==============================================
					PASSWORD REQUIREMENTS
					=============================================== -->
					<div class="meow-fluid-tile">
						<div class="postbox">
							<h3 class="hndle">
								<?php echo esc_html__('Password Requirements', 'apocalypse-meow'); ?>
								<span class="dashicons dashicons-editor-help meow-info-toggle" v-bind:class="{'is-active' : modal === 'passwords'}" v-on:click.prevent="toggleModal('passwords')"></span>
							</h3>
							<div class="inside">

								<div class="meow-fieldset inline">
									<label for="password-alpha"><?php echo esc_html__('Letters', 'apocalypse-meow'); ?></label>

									<span class="dashicons dashicons-editor-help meow-info-toggle" v-bind:class="{'is-active' : modal === 'password-alpha'}" v-on:click.prevent="toggleModal('password-alpha')"></span>

									<select id="password-alpha" v-model.trim="forms.settings.password.alpha" v-bind:disabled="readonly.indexOf('password-alpha') !== -1">
										<option value="optional"><?php echo esc_attr__('Optional', 'apocalypse-meow'); ?></option>
										<option value="required"><?php echo esc_attr__('Required', 'apocalypse-meow'); ?></option>
										<option value="required-both"><?php echo esc_attr__('UPPER & lower', 'apocalypse-meow'); ?></option>
									</select>
								</div>

								<div class="meow-fieldset inline">
									<label for="password-numeric"><?php echo esc_html__('Numbers', 'apocalypse-meow'); ?></label>

									<span class="dashicons dashicons-editor-help meow-info-toggle" v-bind:class="{'is-active' : modal === 'password-numeric'}" v-on:click.prevent="toggleModal('password-numeric')"></span>

									<select id="password-numeric" v-model.trim="forms.settings.password.numeric" v-bind:disabled="readonly.indexOf('password-numeric') !== -1">
										<option value="optional"><?php echo esc_attr__('Optional', 'apocalypse-meow'); ?></option>
										<option value="required"><?php echo esc_attr__('Required', 'apocalypse-meow'); ?></option>
									</select>
								</div>

								<div class="meow-fieldset inline">
									<label for="password-symbol"><?php echo esc_html__('Symbols', 'apocalypse-meow'); ?></label>

									<span class="dashicons dashicons-editor-help meow-info-toggle" v-bind:class="{'is-active' : modal === 'password-symbol'}" v-on:click.prevent="toggleModal('password-symbol')"></span>

									<select id="password-symbol" v-model.trim="forms.settings.password.symbol" v-bind:disabled="readonly.indexOf('password-symbol') !== -1">
										<option value="optional"><?php echo esc_attr__('Optional', 'apocalypse-meow'); ?></option>
										<option value="required"><?php echo esc_attr__('Required', 'apocalypse-meow'); ?></option>
									</select>
								</div>

								<div class="meow-fieldset inline">
									<label for="password-length"><?php echo esc_html__('Minimum Length', 'apocalypse-meow'); ?></label>

									<span class="dashicons dashicons-editor-help meow-info-toggle" v-bind:class="{'is-active' : modal === 'password-length'}" v-on:click.prevent="toggleModal('password-length')"></span>

									<input type="number" id="password-length" v-model.number="forms.settings.password.length" min="<?php echo options::MIN_PASSWORD_LENGTH; ?>" max="500" step="1" v-bind:readonly="readonly.indexOf('password-length') !== -1" />
								</div>

								<div class="meow-fieldset inline">
									<label for="password-common">
										<input type="checkbox" id="password-common" checked disabled />
										<?php echo esc_html__('Block Common Passwords', 'apocalypse-meow'); ?>
									</label>

									<span class="dashicons dashicons-editor-help meow-info-toggle" v-bind:class="{'is-active' : modal === 'password-common'}" v-on:click.prevent="toggleModal('password-common')"></span>
								</div>

							</div>
						</div>
					</div>



					<!-- ==============================================
					USER ENUMERATION
					=============================================== -->
					<div class="meow-fluid-tile">
						<div class="postbox">
							<h3 class="hndle">
								<?php echo esc_html__('User Enumeration', 'apocalypse-meow'); ?>
								<span class="dashicons dashicons-editor-help meow-info-toggle" v-bind:class="{'is-active' : modal === 'enumeration'}" v-on:click.prevent="toggleModal('enumeration')"></span>
							</h3>
							<div class="inside">

								<div class="meow-fieldset inline">
									<label for="core-enumeration">
										<input type="checkbox" id="core-enumeration" v-model.number="forms.settings.core.enumeration" v-bind:true-value="1" v-bind:false-value="0" v-bind:disabled="readonly.indexOf('core-enumeration') !== -1" />
										<?php echo esc_html__('Prevent User Enumeration', 'apocalypse-meow'); ?>
									</label>

									<span class="dashicons dashicons-editor-help meow-info-toggle" v-bind:class="{'is-active' : modal === 'core-enumeration'}" v-on:click.prevent="toggleModal('core-enumeration')"></span>
								</div>

								<div class="meow-fieldset inline" v-if="forms.settings.core.enumeration">
									<label for="core-enumeration_die">
										<input type="checkbox" id="core-enumeration_die" v-model.number="forms.settings.core.enumeration_die" v-bind:true-value="1" v-bind:false-value="0" v-bind:disabled="readonly.indexOf('core-enumeration_die') !== -1" />
										<?php echo esc_html__('Error Instead of Redirect', 'apocalypse-meow'); ?>
									</label>

									<span class="dashicons dashicons-editor-help meow-info-toggle" v-bind:class="{'is-active' : modal === 'core-enumeration_die'}" v-on:click.prevent="toggleModal('core-enumeration_die')"></span>
								</div>

								<div class="meow-fieldset inline" v-if="forms.settings.core.enumeration">
									<label for="core-enumeration_fail">
										<input type="checkbox" id="core-enumeration_fail" v-model.number="forms.settings.core.enumeration_fail" v-bind:true-value="1" v-bind:false-value="0" v-bind:disabled="readonly.indexOf('core-enumeration_fail') !== -1" />
										<?php echo esc_html__('Track Enumeration Failures', 'apocalypse-meow'); ?>
									</label>

									<span class="dashicons dashicons-editor-help meow-info-toggle" v-bind:class="{'is-active' : modal === 'core-enumeration_fail'}" v-on:click.prevent="toggleModal('core-enumeration_fail')"></span>
								</div>

							</div>
						</div>
					</div>



					<!-- ==============================================
					CORE/TEMPLATE
					=============================================== -->
					<div class="meow-fluid-tile">
						<div class="postbox">
							<h3 class="hndle">
								<?php echo esc_html__('Core & Template Overrides', 'apocalypse-meow'); ?>
								<span class="dashicons dashicons-editor-help meow-info-toggle" v-bind:class="{'is-active' : modal === 'core'}" v-on:click.prevent="toggleModal('core')"></span>
							</h3>
							<div class="inside">

								<div class="meow-fieldset inline">
									<label for="core-wp_rest"><?php echo esc_html__('WP-REST Access', 'apocalypse-meow'); ?></label>

									<span class="dashicons dashicons-editor-help meow-info-toggle" v-bind:class="{'is-active' : modal === 'core-wp_rest'}" v-on:click.prevent="toggleModal('core-wp_rest')"></span>

									<select id="core-wp_rest" v-model.trim="forms.settings.core.wp_rest" v-bind:disabled="readonly.indexOf('core-wp_rest') !== -1">
										<option value="all"><?php echo esc_attr__('Default', 'apocalypse-meow'); ?></option>
										<option value="users"><?php echo esc_attr__('Only WP Users', 'apocalypse-meow'); ?></option>
										<option value="none"><?php echo esc_attr__('Nobody', 'apocalypse-meow'); ?></option>
									</select>
								</div>

								<div class="meow-fieldset inline">
									<label for="template-adjacent_posts">
										<input type="checkbox" id="template-adjacent_posts" v-model.number="forms.settings.template.adjacent_posts" v-bind:true-value="1" v-bind:false-value="0" v-bind:disabled="readonly.indexOf('template-adjacent_posts') !== -1" />
										<?php echo esc_html__('Remove Adjacent Post Tags', 'apocalypse-meow'); ?>
									</label>

									<span class="dashicons dashicons-editor-help meow-info-toggle" v-bind:class="{'is-active' : modal === 'template-adjacent_posts'}" v-on:click.prevent="toggleModal('template-adjacent_posts')"></span>
								</div>

								<div class="meow-fieldset inline">
									<label for="core-file_edit">
										<input type="checkbox" id="core-file_edit" v-model.number="forms.settings.core.file_edit" v-bind:true-value="1" v-bind:false-value="0" v-bind:disabled="readonly.indexOf('core-file_edit') !== -1" />
										<?php echo esc_html__('Disable File Editor', 'apocalypse-meow'); ?>
									</label>

									<span class="dashicons dashicons-editor-help meow-info-toggle" v-bind:class="{'is-active' : modal === 'core-file_edit'}" v-on:click.prevent="toggleModal('core-file_edit')"></span>
								</div>

								<div class="meow-fieldset inline">
									<label for="template-generator_tag">
										<input type="checkbox" id="template-generator_tag" v-model.number="forms.settings.template.generator_tag" v-bind:true-value="1" v-bind:false-value="0" v-bind:disabled="readonly.indexOf('template-generator_tag') !== -1" />
										<?php echo esc_html__('Remove "Generator" Tag', 'apocalypse-meow'); ?>
									</label>

									<span class="dashicons dashicons-editor-help meow-info-toggle" v-bind:class="{'is-active' : modal === 'template-generator_tag'}" v-on:click.prevent="toggleModal('template-generator_tag')"></span>
								</div>

								<div class="meow-fieldset inline">
									<label for="template-readme">
										<input type="checkbox" id="template-readme" v-model.number="forms.settings.template.readme" v-bind:true-value="1" v-bind:false-value="0" v-bind:disabled="readonly.indexOf('template-readme') !== -1" />
										<?php echo esc_html__('Delete "readme.html"', 'apocalypse-meow'); ?>
									</label>

									<span class="dashicons dashicons-editor-help meow-info-toggle" v-bind:class="{'is-active' : modal === 'template-readme'}" v-on:click.prevent="toggleModal('template-readme')"></span>
								</div>

								<div class="meow-fieldset inline">
									<label for="template-noopener">
										<input type="checkbox" id="template-noopener" v-model.number="forms.settings.template.noopener" v-bind:true-value="1" v-bind:false-value="0" v-bind:disabled="readonly.indexOf('template-noopener') !== -1" />
										<?php echo esc_html__('rel="noopener"', 'apocalypse-meow'); ?>
									</label>

									<span class="dashicons dashicons-editor-help meow-info-toggle" v-bind:class="{'is-active' : modal === 'template-noopener'}" v-on:click.prevent="toggleModal('template-noopener')"></span>
								</div>

								<div class="meow-fieldset inline">
									<label for="core-xmlrpc">
										<input type="checkbox" id="core-xmlrpc" v-model.number="forms.settings.core.xmlrpc" v-bind:true-value="1" v-bind:false-value="0" v-bind:disabled="readonly.indexOf('core-xmlrpc') !== -1" />
										<?php echo esc_html__('Disable XML-RPC', 'apocalypse-meow'); ?>
									</label>

									<span class="dashicons dashicons-editor-help meow-info-toggle" v-bind:class="{'is-active' : modal === 'core-xmlrpc'}" v-on:click.prevent="toggleModal('core-xmlrpc')"></span>
								</div>
							</div>
						</div>
					</div>

				</div><!--.postbox-container-->



				<!-- Sidebar -->
				<div class="postbox-container one">

					<!-- ==============================================
					SAVE
					=============================================== -->
					<button class="button button-large button-primary" type="submit" v-bind:disabled="forms.settings.loading" style="height: 50px; width: 100%; margin-bottom: 20px; font-size: 16px; display: block;"><?php echo esc_html__('Save Settings', 'apocalypse-meow'); ?></button>




					<!-- ==============================================
					READONLY NOTICE
					=============================================== -->
					<div class="postbox" v-if="readonly.length">
						<div class="inside">
							<p class="description"><?php
							echo sprintf(
								esc_html__("Note: some settings have been hard-coded into this site's %s and cannot be edited here. Such fields will have a somewhat ghostly appearance.", 'apocalypse-meow'),
								'<code>' . esc_html__('wp-config.php', 'apocalypse-meow') . '</code>'
							);
							?></p>
						</div>
					</div>



					<?php
					$plugins = \blobfolio\wp\meow\admin::sister_plugins();
					if (count($plugins)) {
						?>
						<!-- ==============================================
						SISTER PLUGINS
						=============================================== -->
						<div class="postbox">
							<div class="inside">
								<a href="https://blobfolio.com/" target="_blank" class="sister-plugins--blobfolio"><?php echo file_get_contents(MEOW_PLUGIN_DIR . 'img/blobfolio.svg'); ?></a>

								<div class="sister-plugins--intro">
									<?php
									echo sprintf(
										esc_html__('Impressed with %s?', 'apocalypse-meow') . '<br>' .
										esc_html__('You might also enjoy these other fine and practical plugins from %s.', 'apocalypse-meow'),
										'<strong>Apocalypse Meow</strong>',
										'<a href="https://blobfolio.com/" target="_blank">Blobfolio, LLC</a>'
									);
									?>
								</div>

								<nav class="sister-plugins">
									<?php foreach ($plugins as $p) { ?>
										<div class="sister-plugin">
											<a href="<?php echo esc_attr($p['url']); ?>" target="_blank" class="sister-plugin--name"><?php echo esc_html($p['name']); ?></a>

											<div class="sister-plugin--text"><?php echo esc_html($p['description']); ?></div>
										</div>
									<?php } ?>
								</nav>
							</div>
						</div>
					<?php } ?>

				</div><!--.postbox-container-->

			</div><!--#post-body-->
		</div><!--#poststuff-->
	</form>




	<!-- ==============================================
	COMMUNITY
	=============================================== -->
	<form v-if="section === 'community'" method="post" action="<?php echo admin_url('admin-ajax.php'); ?>" name="communityForm" v-on:submit.prevent="communitySubmit">
		<div id="poststuff">
			<div id="post-body" class="metabox-holder meow-columns one-two">

				<!-- Main -->
				<div class="postbox-container two">
					<!-- ==============================================
					ABOUT
					=============================================== -->
					<div class="postbox">
						<h3 class="hndle"><?php echo esc_html__('About the Pool', 'apocalypse-meow'); ?></h3>
						<div class="inside">
							<?php
								// @codingStandardsIgnoreStart
								$out = array(
									esc_html__('The Community Pool is an *optional* extension to the brute-force login protection that combines attack data from your site with other sites running in pool mode to produce a global blocklist.', 'apocalypse-meow'),

									esc_html__('In other words, an attack against one becomes an attack against all!', 'apocalypse-meow'),

									esc_html__("When enabled, your site will periodically share its attack data with the centralized Meow API. The Meow API will crunch and combine this data and return a community blocklist, which your site will then integrate with its own bans.", 'apocalypse-meow'),

									esc_html__('The blocklist data is conservatively filtered using a tiered and weighted ranking system based on activity shared within the past 24 hours. For an IP address to be eligible for community banning, it must be independently reported from multiple sources and have a significant number of total failures.', 'apocalypse-meow'),

									esc_html__("Your site's whitelist is always respected. Failures from whitelisted IPs will never be sent to the pool, and if the pool declares a ban for an IP you have whitelisted, your site will not ban it. Be sure to add your own IP address to your site's whitelist. :)", 'apocalypse-meow'),

									esc_html__("Anybody can join the Community Pool. There's just one requirement:", 'apocalypse-meow') . ' <strong>' . esc_html__('To Receive, Your Must Give.', 'apocalypse-meow') . '</strong> ' . esc_html__('It is, after all, a community. Haha.', 'apocalypse-meow')
								);
								// @codingStandardsIgnoreEnd

								echo '<p>' . implode('</p><p>', $out) . '</p>';
							?>
						</div>
					</div>

					<!-- ==============================================
					SAVE
					=============================================== -->
					<div class="postbox">
						<h3 class="hndle"><?php echo esc_html__('Community Status', 'apocalypse-meow'); ?></h3>
						<div class="inside">
							<div class="meow-pool-form">
								<div class="meow-pool-form--form">
									<p>
										<strong><?php echo esc_html__('Status', 'apocalypse-meow'); ?>:</strong>
										<span v-if="forms.settings.login.community"><?php echo esc_html__('Enabled', 'apocalypse-meow'); ?></span>
										<span v-else><?php echo esc_html__('Disabled', 'apocalypse-meow'); ?></span>
									</p>

									<p v-if="readonly.indexOf('login-community') === -1">
										<button class="button button-large button-primary" type="submit" v-bind:disabled="forms.settings.loading">
											<span v-if="forms.settings.login.community"><?php echo esc_html__('Leave Community', 'apocalypse-meow'); ?></span>
											<span v-else><?php echo esc_html__('Join Community', 'apocalypse-meow'); ?></span>
										</button>
									</p>
									<p v-else>
										<?php echo sprintf(
											esc_html__('The Community Pool setting has been hard-coded into your site configuration (probably in %s). To change the status, that code will have to be altered.', 'apocalypse-meow'),
											'<code>wp-config.php</code>'
										); ?>
									</p>
								</div>

								<img src="<?php echo MEOW_PLUGIN_URL; ?>img/kitten.gif" alt="Kitten" class="meow-pool-form--cat left" v-if="forms.settings.login.community" />

								<img src="<?php echo MEOW_PLUGIN_URL; ?>img/kitten.gif" alt="Kitten" class="meow-pool-form--cat" />
							</div>
						</div>
					</div>

				</div>

				<!-- Sidebar -->
				<div class="postbox-container one">

					<!-- ==============================================
					PRIVACY NOTICE
					=============================================== -->
					<div class="postbox">
						<h3 class="hndle"><?php echo esc_html__('Privacy Notice', 'apocalypse-meow'); ?></h3>
						<div class="inside">
							<?php
								echo '<p>' . esc_html__('Information about your site is *never* shared with other Community Pool participants. The Meow API acts as a go-between.', 'apocalypse-meow') . '</p>';

								echo '<p>' . esc_html__('But that said, this is not usually data that would be leaving your site, so if you are not comfortable with the idea, please leave this feature disabled!', 'apocalypse-meow') . '</p>';
							?>
						</div>
					</div>


					<div class="postbox">
						<h3 class="hndle"><?php echo esc_html__('Login Failures', 'apocalypse-meow'); ?></h3>
						<div class="inside">
							<?php
								$out = array(
									esc_html__('A UTC timestamp', 'apocalypse-meow'),
									esc_html__('An IP address', 'apocalypse-meow'),
									sprintf(
										esc_html__('Whether or not the username was %s or %s', 'apocalypse-meow'),
										'<code>admin</code>',
										'<code>administrator</code>'
									)
								);

								echo '<p>' . esc_html__('The following details from failed login attempts are shared:', 'apocalypse-meow') . '</p><ul style="list-style-type: disc; list-style-position: outside; padding-left: 3em;"><li>' . implode('</li><li>', $out) . '</li></ul>';
							?>
						</div>
					</div>


					<div class="postbox">
						<h3 class="hndle"><?php echo esc_html__('Environment/Setup', 'apocalypse-meow'); ?></h3>
						<div class="inside">
							<?php
								// @codingStandardsIgnoreStart
								$out = array(
									esc_html__('Aside from attack data, the API also collects some basic information about your site setup. This is done primarily to help the API keep its data sources straight, but might also help inform what sorts of future features would be most helpful to develop.', 'apocalypse-meow'),
									esc_html__('This information is *only* used internally — and not very sensitive to begin with — but you should still be aware it is being leaked. :)', 'apocalypse-meow')
								);
								// @codingStandardsIgnoreEnd

								echo '<p>' . implode('</p><p>', $out) . '</p>';

								// Output the table.
								$out = array(
									__('Domain', 'apocalypse-meow')=>common\sanitize::hostname(site_url()),
									__('OS', 'apocalypse-meow')=>PHP_OS,
									__('PHP', 'apocalypse-meow')=>PHP_VERSION,
									__('WordPress', 'apocalypse-meow')=>common\format::decode_entities(get_bloginfo('version')),
									__('This Plugin', 'apocalypse-meow')=>about::get_local('Version'),
									__('Premium', 'apocalypse-meow')=>options::is_pro() ? __('Yes', 'apocalypse-meow') : __('No', 'apocalypse-meow'),
									__('Locale', 'apocalypse-meow')=>get_locale(),
									__('Timezone', 'apocalypse-meow')=>about::get_timezone(),
								);
								echo '<table class="meow-meta"><tbody>';
								foreach ($out as $k=>$v) {
									echo '<tr><th scope="row">' . esc_html($k) . '</th><td>' . esc_html($v) . '</td></tr>';
								}
								echo '</tbody></table>';
							?>
						</div>
					</div>

				</div>

			</div><!--#post-body-->
		</div><!--#poststuff-->
	</form>



	<?php if (options::is_pro()) { ?>
		<!-- ==============================================
		WP-CONFIG WIZARD
		=============================================== -->
		<div id="poststuff" v-if="section === 'wp-config'">
			<div id="post-body" class="metabox-holder meow-columns one-two fixed">

				<!-- Config -->
				<div class="postbox-container two">
					<div class="postbox">
						<h3 class="hndle"><?php echo esc_html__('Configuration Constants', 'apocalypse-meow'); ?></h3>
						<div class="inside">
							<pre class="language-php line-numbers"><code><?php echo file_get_contents(MEOW_PLUGIN_DIR . 'skel/wp-config.html');
							?></code></pre>

							<p><code><?php echo trailingslashit(ABSPATH); ?>wp-config.php</code></p>
						</div>
					</div>
				</div>

				<!-- Sidebar -->
				<div class="postbox-container one">
					<div class="postbox">
						<h3 class="hndle"><?php echo esc_html__('Explanation', 'apocalypse-meow'); ?></h3>
						<div class="inside">
							<?php
								// @codingStandardsIgnoreStart
								$out = array(
									sprintf(
										esc_html__('Almost all of the plugin settings can alternatively be defined as PHP constants in %s. This allows system administrators to configure behaviors without logging into WordPress, and prevents those configurations from being changed by other users with access to this page.', 'apocalypse-meow'),
										'<code>wp-config.php</code>'
									),
									sprintf(
										esc_html__('This code sample contains the corresponding PHP code for every setting as currently configured. It can be copied as-is to %s, or certain pieces can be removed or tweaked as needed. Any options that site administrators should be allowed to change through this page should first be removed.', 'apocalypse-meow'),
										'<code>wp-config.php</code>'
									),
									sprintf(
										esc_html__('Note: while PHP constants can be shoved pretty much anywhere, these must be loaded into memory before the %s hook is fired or Apocalypse Meow might not see them. %s is the safest bet.', 'apocalypse-meow'),
										'<code>' . (MEOW_MUST_USE ? 'muplugins_loaded' : 'plugins_loaded') . '</code>',
										'<code>wp-config.php</code>'
									)
								);
								// @codingStandardsIgnoreEnd

								echo '<p>' . implode('</p><p>', $out) . '</p>';
							?>
						</div>
					</div>
				</div>
			</div>
		</div>
	<?php } ?>



	<!-- ==============================================
	HELP MODAL
	=============================================== -->
	<transition name="fade">
		<div v-if="modal" class="meow-modal">
			<span class="dashicons dashicons-dismiss meow-modal--close" v-on:click.prevent="toggleModal('')"></span>
			<img src="<?php echo MEOW_PLUGIN_URL; ?>img/kitten.gif" class="meow-modal--cat" alt="Kitten" />
			<div class="meow-modal--inner">
				<p v-for="p in modals[modal]" v-html="p"></p>
			</div>
		</div>
	</transition>

</div><!--.wrap-->
