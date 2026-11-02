<?php
/**
 * Title: Newsman admin settings
 *
 * @package NewsmanApp for WordPress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template partial included within a method; variables are method-local.

/**
 * Current class for output
 *
 * @var \Newsman\Admin\Settings\Newsman $this
 */

$this->is_oauth();
$health_checks = $this->get_health_checks();
$health_rows   = array(
	'credentials'      => array( __( 'API credentials', 'newsman' ), $health_checks->check_credentials() ),
	'list'             => array( __( 'Email list', 'newsman' ), $health_checks->check_list() ),
	'api_access'       => array( __( 'Allow API access', 'newsman' ), $health_checks->check_api_access() ),
	'remarketing'      => array( __( 'Remarketing', 'newsman' ), $health_checks->check_remarketing() ),
	'action_scheduler' => array( __( 'Action Scheduler', 'newsman' ), $health_checks->check_action_scheduler() ),
	'woocommerce'      => array( __( 'WooCommerce', 'newsman' ), $health_checks->check_woocommerce() ),
);
$health_icons  = array(
	'good'        => array( 'dashicons-yes-alt', __( 'OK', 'newsman' ) ),
	'recommended' => array( 'dashicons-warning', __( 'Needs attention', 'newsman' ) ),
	'critical'    => array( 'dashicons-dismiss', __( 'Problem', 'newsman' ) ),
	'info'        => array( 'dashicons-info', __( 'Information', 'newsman' ) ),
);
?>
<div class="tabset-img">
	<img alt="<?php echo esc_attr__( 'NewsMAN', 'newsman' ); ?>" title="<?php echo esc_attr__( 'NewsMAN', 'newsman' ); ?>" src="<?php echo esc_url( NEWSMAN_PLUGIN_URL ); ?>src/img/logo.png"/>
	<a class="nzm-link" href="https://www.newsman.com/" target="_blank">www.newsman.com</a>
</div>
<div class="nzm-tabset">
	<input type="radio" name="tabset" id="tabNewsman" aria-controls="" checked>
	<label for="tabNewsman" id="newsmanBtn"><?php echo esc_html__( 'NewsMAN', 'newsman' ); ?></label>
	<input type="radio" name="tabset" id="tabSync" aria-controls="">
	<label for="tabSync" id="syncBtn"><?php echo esc_html__( 'Sync', 'newsman' ); ?></label>
	<input type="radio" name="tabset" id="tabRemarketing" aria-controls="">
	<label for="tabRemarketing" id="remarketingBtn"><?php echo esc_html__( 'Remarketing', 'newsman' ); ?></label>
	<?php if ( $this->is_woo_commerce_exists() ) : ?>
		<input type="radio" name="tabset" id="tabSms" aria-controls="">
		<label for="tabSms" id="smsBtn"><?php echo esc_html__( 'SMS', 'newsman' ); ?></label>
	<?php endif; ?>
	<input type="radio" name="tabset" id="tabSettings" aria-controls="">
	<label for="tabSettings" id="settingsBtn"><?php echo esc_html__( 'Settings', 'newsman' ); ?></label>
	<div class="tab-panels">
		<section id="tabNewsman" class="tab-panel">
			<div class="wrap">
				<h2><?php echo esc_html__( 'NewsMAN Info', 'newsman' ); ?></h2>
				<table class="form-table newsman-tbl-fixed">
					<tr>
						<th scope="row">
							<strong><?php echo esc_html__( 'Newsman Plugin Version', 'newsman' ); ?></strong>
						</th>
						<td><?php echo esc_html( NEWSMAN_VERSION ); ?></td>
					</tr>
					<tr>
						<th scope="row">
							&nbsp;
						</th>
						<td>
							<button type="button" class="button button-primary" style="vertical-align: middle;" onclick="if(confirm('<?php echo esc_js( __( 'Reconfigure with Newsman Oauth ?', 'newsman' ) ); ?>')) { window.location.href = '<?php echo esc_url( admin_url( 'admin.php?page=NewsmanOauth' ) ); ?>'; }">
								<?php echo esc_html__( 'Reconfigure with Newsman Oauth', 'newsman' ); ?>
							</button>
							<span style="font-size: 16px; vertical-align: middle;">
								&nbsp;<?php echo esc_html__( 'Site ', 'newsman' ); ?><strong><?php echo esc_html( get_bloginfo( 'name' ) ); ?></strong><?php echo ' (ID: <strong>' . esc_html( get_current_blog_id() ) . '</strong>)'; ?>
							</span>
						</td>
					</tr>
				</table>
				<h3 class="nzm-health-title"><?php echo esc_html__( 'Status', 'newsman' ); ?></h3>
				<table class="form-table newsman-tbl-fixed nzm-health">
					<?php foreach ( $health_rows as $health_key => $health_row ) : ?>
						<?php
						$health_check = $health_row[1];
						$health_icon  = isset( $health_icons[ $health_check['status'] ] ) ? $health_icons[ $health_check['status'] ] : $health_icons['info'];
						?>
					<tr class="nzm-health-row nzm-health-row-<?php echo esc_attr( $health_key ); ?>">
						<th scope="row">
							<strong><?php echo esc_html( $health_row[0] ); ?></strong>
						</th>
						<td>
							<span class="nzm-health-status nzm-health-<?php echo esc_attr( $health_check['status'] ); ?>">
								<span class="dashicons <?php echo esc_attr( $health_icon[0] ); ?>" aria-hidden="true"></span>
								<span class="screen-reader-text"><?php echo esc_html( $health_icon[1] ); ?>:</span>
								<?php echo esc_html( $health_check['value'] ); ?>
							</span>
							<p class="description">
								<?php echo esc_html( $health_check['description'] ); ?>
								<?php if ( ! empty( $health_check['action_url'] ) ) : ?>
									<a class="nzm-link" href="<?php echo esc_url( $health_check['action_url'] ); ?>"><?php echo esc_html( $health_check['action_label'] ); ?></a>
								<?php endif; ?>
							</p>
							<?php if ( 'remarketing' === $health_key && 'good' === $health_check['status'] ) : ?>
							<div class="nzm-remarketing-check">
								<p>
									<button type="button" class="button" id="nzm-remarketing-check"><?php echo esc_html__( 'Check the remarketing scripts on the storefront', 'newsman' ); ?></button>
									<span class="spinner" id="nzm-remarketing-check-spinner"></span>
								</p>
								<p class="description"><?php echo esc_html__( 'Opens the homepage, a product page and a category page in the background and looks for the NewsMAN scripts. Administrators do not get these scripts on the storefront, so the check adds a one-time code to the page addresses. The scripts are not run and nothing is sent to NewsMAN.', 'newsman' ); ?></p>
								<div id="nzm-remarketing-check-results" class="nzm-remarketing-check-results" aria-live="polite"></div>
								<p class="description nzm-remarketing-check-note">
									<span class="dashicons dashicons-info" aria-hidden="true"></span>
									<?php echo esc_html__( 'This check only looks for the scripts in the page source, which is not enough to confirm that tracking works. When needed, check the installation in your NewsMAN account: newsman.app > Integrations > NewsMAN Remarketing > Check installation button.', 'newsman' ); ?>
								</p>
							</div>
							<?php endif; ?>
						</td>
					</tr>
					<?php endforeach; ?>
					<tr class="nzm-health-row nzm-health-row-exports">
						<th scope="row">
							<strong><?php echo esc_html__( 'Last exports', 'newsman' ); ?></strong>
						</th>
						<td>
							<table class="nzm-health-exports">
								<?php foreach ( $health_checks->get_last_exports() as $health_export ) : ?>
								<tr class="nzm-health-export-<?php echo esc_attr( $health_export['type'] ); ?>">
									<th scope="row"><?php echo esc_html( $health_export['label'] ); ?></th>
									<td><?php echo esc_html( $health_export['value'] ); ?></td>
								</tr>
								<?php endforeach; ?>
							</table>
							<p class="description"><?php echo esc_html__( 'When NewsMAN last read each type of data from this site.', 'newsman' ); ?></p>
						</td>
					</tr>
					<?php if ( $this->can_view_site_health() ) : ?>
					<tr>
						<th scope="row">
							&nbsp;
						</th>
						<td>
							<a class="nzm-link" href="<?php echo esc_url( admin_url( 'site-health.php' ) ); ?>"><?php echo esc_html__( 'The same checks are also in Tools > Site Health.', 'newsman' ); ?></a>
						</td>
					</tr>
					<?php endif; ?>
				</table>
				<table class="form-table newsman-tbl-fixed">
					<tr>
						<th scope="row">
							<strong><?php echo esc_html__( 'Widget', 'newsman' ); ?></strong>
						</th>
						<td>
							<p><?php echo esc_html__( 'Transform your website visitors into subscribers and customers. Generate easy to integrate pop-ups, embedded signup forms and web layers to convert more.', 'newsman' ); ?></p>
							<p><?php echo esc_html__( 'Log in to your NewsMAN account: Select List -> Settings -> Subscription forms -> Create/Edit form -> Modal window -> Activate modal window for newsletter subscription -> Select embedded form. Copy paste Shortcode newsman_subscribe_widget', 'newsman' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<strong><?php echo esc_html__( 'Documentation', 'newsman' ); ?></strong>
						</th>
						<td>
							<p>
								<a class="nzm-link" href="https://github.com/Newsman/WP-Plugin-NewsmanApp/blob/master/configuration-en.md" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'Configuration guide (English)', 'newsman' ); ?></a>
							</p>
							<p>
								<a class="nzm-link" href="https://github.com/Newsman/WP-Plugin-NewsmanApp/blob/master/configuration-ro.md" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'Ghid de configurare (Romana)', 'newsman' ); ?></a>
							</p>
						</td>
					</tr>
				</table>
			</div>
		</section>
	</div>
</div>
