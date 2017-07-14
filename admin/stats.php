<?php
/**
 * Admin: Statistics
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
use \blobfolio\wp\meow\options;
use \blobfolio\wp\meow\vendor\common\format;


$data = array(
	'forms'=>array(
		'search'=>array(
			'action'=>'meow_ajax_stats',
			'n'=>ajax::get_nonce(),
			'errors'=>array(),
			'loading'=>false
		),
		'download'=>array(
			'action'=>'meow_ajax_activity_csv',
			'n'=>ajax::get_nonce(),
			'errors'=>array(),
			'loading'=>false
		)
	),
	'stats'=>array(),
	'hasStats'=>false,
	'searched'=>false,
	'modal'=>false,
	'modals'=>array(
		'attempts'=>array(
			esc_html__('This indicates the average number of login attempts made *while banned*. This number can be high if your site is routinely attacked by stupid robots.', 'apocalypse-meow'),
		),
		'usernames'=>array(
			esc_html__('This shows the total number of unique usernames submitted during failed login attempts.', 'apocalypse-meow'),
			esc_html__('Note: WordPress allows users to login using either their username or email address. This plugin normalizes all entries to the username to keep things tidy.', 'apocalypse-meow'),
		),
		'invalid'=>array(
			esc_html__('This shows the percentage of failed login attempts using non-existent usernames. While such attempts are fruitless, they do still represent a waste in server resources.', 'apocalypse-meow'),
		),
		'valid'=>array(
			esc_html__('This shows the percentage of failed login attempts using *valid* usernames. Left unchecked, a robot could eventually gain access to the site.', 'apocalypse-meow'),
		),
	),
	'download'=>'',
	'downloadName'=>''
);

?><div class="wrap" id="vue-stats" data-env="<?php echo esc_attr(json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)); ?>" v-cloak>
	<h1>Apocalypse Meow: <?php echo esc_html__('Stats', 'apocalypse-meow'); ?></h1>

	<div class="error" v-for="error in forms.search.errors"><p>{{error}}</p></div>
	<div class="error" v-for="error in forms.download.errors"><p>{{error}}</p></div>
	<div class="updated" v-if="!searched"><p><?php echo esc_html__('The stats are being crunched. Hold tight.', 'apocalypse-meow'); ?></p></div>
	<div class="error" v-if="searched && !hasStats"><p><?php echo esc_html__('No stats were found.', 'apocalypse-meow'); ?></p></div>

	<?php if (options::get('prune-active')) { ?>
		<div class="notice notice-info">
			<p><?php echo sprintf(
				esc_html__('Login data is currently pruned after %s. To change this going forward, visit the %s page.', 'apocalypse-meow'),
				format::inflect(options::get('prune-limit'), esc_html__('%d day', 'apocalypse-meow'), esc_html__('%d days', 'apocalypse-meow')),
				'<a href="' . esc_url(admin_url('admin.php?page=meow-settings')) . '">' . esc_html__('settings', 'apocalypse-meow') . '</a>'
			); ?></p>
		</div>
	<?php } ?>

	<div id="poststuff">
		<div id="post-body" class="metabox-holder meow-columns one-two fixed" v-if="searched">

			<!-- Results -->
			<div class="postbox-container two" v-if="hasStats">
				<!-- ==============================================
				Period
				=============================================== -->
				<div class="postbox" v-if="hasStats && stats.volume.labels.length > 1">
					<h3 class="hndle"><?php echo esc_html__('Login Activity', 'apocalypse-meow'); ?></h3>
					<div class="inside" style="position: relative">
						<chartist
							ratio="ct-major-seventh"
							type="Line"
							:data="stats.volume"
							:options="lineOptions">
						</chartist>

						<ul class="ct-legend" style="position: absolute; top: 0; right: 10px; margin: 0;">
							<li class="ct-series-a"><?php echo esc_html__('Ban', 'apocalypse-meow'); ?></li>
							<li class="ct-series-b"><?php echo esc_html__('Failure', 'apocalypse-meow'); ?></li>
							<li class="ct-series-c"><?php echo esc_html__('Success', 'apocalypse-meow'); ?></li>
						</ulv>
					</div>
				</div>



				<!-- ==============================================
				Breakdown
				=============================================== -->
				<div class="postbox">
					<h3 class="hndle"><?php echo esc_html__('Breakdown', 'apocalypse-meow'); ?></h3>
					<div class="inside">
						<table class="meow-stats">
							<thead>
								<tr>
									<th><?php echo esc_html__('General', 'apocalypse-meow'); ?></th>
									<th class="middle"><?php echo esc_html__('Failures', 'apocalypse-meow'); ?></th>
									<th><?php echo esc_html__('Bans', 'apocalypse-meow'); ?></th>
								</tr>
							</thead>
							<tbody>
								<tr>
									<td>
										<table class="meow-meta">
											<tbody>
												<tr>
													<th scope="row"><?php echo esc_html__('Total', 'apocalypse-meow'); ?></th>
													<td>{{ stats.total }}</td>
												</tr>
												<tr>
													<th scope="row"><?php echo esc_html__('First', 'apocalypse-meow'); ?></th>
													<td>{{ stats.date_min }}</td>
												</tr>
												<tr>
													<th scope="row"><?php echo esc_html__('Last', 'apocalypse-meow'); ?></th>
													<td>{{ stats.date_max }}</td>
												</tr>
												<tr>
													<th scope="row"><?php echo esc_html__('# Days', 'apocalypse-meow'); ?></th>
													<td>{{ stats.days }}</td>
												</tr>
												<tr>
													<th scope="row"><?php echo esc_html__('Daily Avg', 'apocalypse-meow'); ?></th>
													<td>{{ Math.round(stats.total / stats.days * 100) / 100 }}</td>
												</tr>
											</tbody>
										</table>
									</td>
									<td class="middle">
										<table class="meow-meta" v-if="stats.fails.total">
											<tbody>
												<tr>
													<th scope="row"><?php echo esc_html__('Total', 'apocalypse-meow'); ?></th>
													<td>{{ stats.fails.total }}</td>
												</tr>
												<tr>
													<th scope="row"><?php echo esc_html__('Daily Avg', 'apocalypse-meow'); ?></th>
													<td>{{ Math.round(stats.fails.total / stats.days * 100) / 100 }}</td>
												</tr>
												<tr>
													<th scope="row">
														<?php echo esc_html__('Unique Usernames', 'apocalypse-meow'); ?>
														<span class="dashicons dashicons-editor-help meow-info-toggle" v-bind:class="{'is-active' : modal === 'usernames'}" v-on:click.prevent="toggleModal('usernames')"></span>
													</th>
													<td>{{ stats.fails.usernames.unique }}</td>
												</tr>
												<tr>
													<th scope="row">
														<?php echo esc_html__('Valid Users', 'apocalypse-meow'); ?>

														<span class="dashicons dashicons-editor-help meow-info-toggle" v-bind:class="{'is-active' : modal === 'valid'}" v-on:click.prevent="toggleModal('valid')"></span>
													</th>
													<td>{{ Math.round(stats.fails.usernames.valid / (stats.fails.usernames.invalid + stats.fails.usernames.valid) * 10000) / 100 }}%</td>
												</tr>
												<tr>
													<th scope="row">
														<?php echo esc_html__('w/ Invalid Username', 'apocalypse-meow'); ?>

														<span class="dashicons dashicons-editor-help meow-info-toggle" v-bind:class="{'is-active' : modal === 'invalid'}" v-on:click.prevent="toggleModal('invalid')"></span>
													</th>
													<td>{{ Math.round(stats.fails.usernames.invalid / (stats.fails.usernames.invalid + stats.fails.usernames.valid) * 10000) / 100 }}%</td>
												</tr>
												<tr v-if="stats.fails.enumeration > 0">
													<th scope="row"><?php echo esc_html__('Enumeration Attempts', 'apocalypse-meow'); ?></th>
													<td>{{ stats.fails.enumeration }}</td>
												</tr>
												<tr>
													<th scope="row"><?php echo esc_html__('Unique IPs', 'apocalypse-meow'); ?></th>
													<td>{{ stats.fails.ips }}</td>
												</tr>
												<tr>
													<th scope="row"><?php echo esc_html__('Unique Subnets', 'apocalypse-meow'); ?></th>
													<td>{{ stats.fails.subnets }}</td>
												</tr>
											</tbody>
										</table>
										<p v-else class="description"><?php echo esc_html__('No failures have been recorded.', 'apocalypse-meow'); ?></p>
									</td>
									<td>
										<table class="meow-meta" v-if="stats.bans.total">
											<tbody>
												<tr>
													<th scope="row"><?php echo esc_html__('Total', 'apocalypse-meow'); ?></th>
													<td>{{ stats.bans.total }}</td>
												</tr>
												<tr>
													<th scope="row"><?php echo esc_html__('Daily Avg', 'apocalypse-meow'); ?></th>
													<td>{{ Math.round(stats.bans.total / stats.days * 100) / 100 }}</td>
												</tr>
												<tr>
													<th scope="row"><?php echo esc_html__('Pardons', 'apocalypse-meow'); ?></th>
													<td>{{ stats.bans.pardons }}</td>
												</tr>
												<tr>
													<th scope="row">
														<?php echo esc_html__('While Banned', 'apocalypse-meow'); ?>

														<span class="dashicons dashicons-editor-help meow-info-toggle" v-bind:class="{'is-active' : modal === 'attempts'}" v-on:click.prevent="toggleModal('attempts')"></span>
													</th>
													<td>{{ Math.round(stats.bans.attempts / stats.bans.total * 100) / 100 }}</td>
												</tr>
											</tbody>
										</table>
										<p v-else class="description"><?php echo esc_html__('No bans have been recorded.', 'apocalypse-meow'); ?></p>
									</td>
								</tr>
							</tbody>
						</table>
					</div>
				</div>
			</div><!--.postbox-container-->

			<!-- Search -->
			<div class="postbox-container one">
				<!-- ==============================================
				DOWNLOAD
				=============================================== -->
				<div class="postbox">
					<h3 class="hndle"><?php echo esc_html__('Export Data', 'apocalypse-meow'); ?></h3>
					<div class="inside">
						<p v-if="!forms.download.loading && !download"><?php echo esc_html__('Click the button below to generate a CSV containing all the login data for your site.', 'apocalypse-meow'); ?></p>
						<p v-if="forms.download.loading && !download"><?php echo esc_html__('The CSV is being compiled. This might take a while if your site has a lot of data.', 'apocalypse-meow'); ?></p>

						<button type="button" class="button button-primary button-large" v-if="!download" v-on:click.prevent="downloadSubmit" v-bind:disabled="forms.download.loading"><?php echo esc_html__('Start Export', 'apocalypse-meow'); ?></button>

						<a class="button button-primary button-large" v-if="download" v-bind:href="download" v-bind:download="downloadName"><?php echo esc_html__('Download CSV', 'apocalypse-meow'); ?></a>
					</div>
				</div>

				<!-- ==============================================
				Status
				=============================================== -->
				<div class="postbox" v-if="hasStats">
					<h3 class="hndle"><?php echo esc_html__('Activity by Type', 'apocalypse-meow'); ?></h3>
					<div class="inside">
						<chartist
							ratio="ct-square"
							type="Pie"
							:data="stats.status"
							:options="pieOptions">
						</chartist>
					</div>
				</div>

				<!-- ==============================================
				Username
				=============================================== -->
				<div class="postbox" v-if="hasStats && stats.username.labels.length > 1">
					<h3 class="hndle"><?php echo esc_html__('Failures by Username', 'apocalypse-meow'); ?></h3>
					<div class="inside">
						<chartist
							ratio="ct-square"
							type="Pie"
							:data="stats.username"
							:options="pieOptions">
						</chartist>
					</div>
				</div>

				<!-- ==============================================
				Network Type
				=============================================== -->
				<div class="postbox" v-if="hasStats && stats.ip.series[0] && stats.ip.series[1]">
					<h3 class="hndle"><?php echo esc_html__('Failures by Network Type', 'apocalypse-meow'); ?></h3>
					<div class="inside">
						<chartist
							ratio="ct-square"
							type="Pie"
							:data="stats.ip"
							:options="pieOptions">
						</chartist>
					</div>
				</div>

			</div><!--.postbox-container-->

		</div><!--#post-body-->
	</div><!--#poststuff-->



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
