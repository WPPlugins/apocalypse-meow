<?php
/**
 * Admin: Premium License
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

use \blobfolio\wp\meow\ajax;

$data = array(
	'forms'=>array(
		'pro'=>array(
			'action'=>'meow_ajax_pro',
			'n'=>ajax::get_nonce(),
			'license'=>\blobfolio\wp\meow\options::get('license'),
			'errors'=>array(),
			'saved'=>false,
			'loading'=>false
		)
	)
);
$license = \blobfolio\wp\meow\license::get($data['forms']['pro']['license']);
$data['license'] = $license->get_license();

?><div class="wrap" id="vue-pro" data-env="<?php echo esc_attr(json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)); ?>" v-cloak>
	<h1>Apocalypse Meow: <?php echo esc_html__('Premium License', 'apocalypse-meow'); ?></h1>

	<?php
	// Warn about OpenSSL.
	if (!function_exists('openssl_get_publickey')) {
		echo '<div class="notice notice-warning">';
			// @codingStandardsIgnoreStart
			echo '<p>' . esc_html__('Please ask your system administrator to enable the OpenSSL PHP extension. Without this, your site will be unable to decode and validate the license details itself. In the meantime, Apocalypse Meow will try to offload this task to its own server. This should get the job done, but won\'t be as efficient and could impact performance a bit.', 'apocalypse-meow') . '</p>';
			// @codingStandardsIgnoreEnd
		echo '</div>';
	}
	?>

	<div class="updated" v-if="forms.pro.saved"><p><?php echo esc_html__('Your license has been saved!', 'apocalypse-meow'); ?></p></div>
	<div class="error" v-for="error in forms.pro.errors"><p>{{error}}</p></div>

	<div id="poststuff">
		<div id="post-body" class="metabox-holder meow-columns one-two">

			<!-- License -->
			<div class="postbox-container two">
				<div class="postbox">
					<h3 class="hndle"><?php echo esc_html__('License Key', 'apocalypse-meow'); ?></h3>
					<div class="inside">
						<form name="proForm" method="post" action="<?php echo admin_url('admin-ajax.php'); ?>" v-on:submit.prevent="proSubmit">
							<textarea id="meow-license" class="meow-code" name="license" v-model.trim="forms.pro.license" placeholder="Paste your license key here."></textarea>
							<p><button type="submit" v-bind:disabled="forms.pro.loading" class="button button-primary button-large"><?php echo esc_html__('Save', 'apocalypse-meow'); ?></button></p>
						</form>
					</div>
				</div>
			</div><!--.postbox-container-->

			<!-- License -->
			<div class="postbox-container one">

				<div class="postbox" v-if="!license.license_id">
					<h3 class="hndle"><?php echo esc_html__('The Pro Pitch', 'apocalypse-meow'); ?></h3>
					<div class="inside">
						<?php
						echo '<p>' . sprintf(
							esc_html__("Apocalypse Meow's proactive security hardening and attack mitigation features are *free*. Forever. This is not about extortion. Haha. The %s is geared toward developers, system administrators, and general tech enthusiasts.", 'apocalypse-meow'),
							'<a href="' . MEOW_URL . '" target="_blank">Pro version</a>'
						) . '</p>';

						echo '<p>' . esc_html__(" TL;DR it's about Workflow and Data:", 'apocalypse-meow') . '</p>';

						echo '<ul style="list-style: disc; margin-left: 2em;">';
						echo '<li>' . esc_html__('Easy data exports and visualizations;', 'apocalypse-meow') . '</li>';
						echo '<li>' . esc_html__('Complete WP-CLI integration;', 'apocalypse-meow') . '</li>';
						echo '<li>' . esc_html__('Advanced management tools for managing sessions, user passwords, renaming users, and more;', 'apocalypse-meow') . '</li>';
						echo '<li>' . esc_html__('Hookable actions and filters for custom theme/plugin integration;', 'apocalypse-meow') . '</li>';
						echo '</ul>';

						echo '<p>' . sprintf(
							esc_html__('To learn more, visit %s.', 'apocalypse-meow'),
							'<a href="' . MEOW_URL . '" target="_blank">blobfolio.com</a>'
						) . '</p>';
						?>
					</div>
				</div>

				<div class="postbox" v-if="license.license_id">
					<h3 class="hndle"><?php echo esc_html__('Your License', 'apocalypse-meow'); ?></h3>
					<div class="inside">
						<table class="meow-meta">
							<tbody>
								<tr>
									<th scope="row"><?php echo esc_html__('Created', 'apocalypse-meow'); ?></th>
									<td>{{license.date_created}}</td>
								</tr>
								<tr v-if="license.date_created !== license.date_updated">
									<th scope="row"><?php echo esc_html__('Updated', 'apocalypse-meow'); ?></th>
									<td>{{license.date_updated}}</td>
								</tr>
								<tr v-if="license.errors.revoked">
									<th class="meow-fg-orange" scope="row"><?php echo esc_html__('Revoked', 'apocalypse-meow'); ?></th>
									<td>{{license.date_revoked}}</td>
								</tr>
								<tr>
									<th scope="row"><?php echo esc_html__('Name', 'apocalypse-meow'); ?></th>
									<td>{{license.name}}</td>
								</tr>
								<tr v-if="license.company">
									<th scope="row"><?php echo esc_html__('Company', 'apocalypse-meow'); ?></th>
									<td>{{license.company}}</td>
								</tr>
								<tr>
									<th scope="row"><?php echo esc_html__('Email', 'apocalypse-meow'); ?></th>
									<td>{{license.email}}</td>
								</tr>
								<tr>
									<th scope="row"><?php echo esc_html__('Type', 'apocalypse-meow'); ?></th>
									<td>{{license.type}}</td>
								</tr>
								<tr>
									<th v-bind:class="{'meow-fg-orange' : license.errors.item}" scope="row"><?php echo esc_html__('Thing', 'apocalypse-meow'); ?></th>
									<td>{{license.item}}</td>
								</tr>
								<tr v-if="license.type === 'single'">
									<th v-bind:class="{'meow-fg-orange' : license.errors.domain}" scope="row"><?php echo esc_html__('Domain(s)', 'apocalypse-meow'); ?></th>
									<td>
										<div v-for="domain in license.domains">{{domain}}</div>
									</td>
								</tr>
								<tr>
									<th scope="row"><?php echo esc_html__('Help', 'apocalypse-meow'); ?></th>
									<td>
										<span v-if="!license.errors.domain && !license.errors.item && !license.errors.revoked"><?php echo esc_html__('Thanks for going Pro!', 'apocalypse-meow'); ?></span>
										<?php
										echo sprintf(
											__('If you have any questions or need help, visit %s.', 'apocalypse-meow'),
											'<a href="' . MEOW_URL . '" target="_blank">blobfolio.com</a>'
										);
										?>
									</td>
							</tbody>
						</table>
					</div>
				</div>

				<?php
				$plugins = \blobfolio\wp\meow\admin::sister_plugins();
				if (count($plugins)) {
					?>
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
</div><!--.wrap-->
