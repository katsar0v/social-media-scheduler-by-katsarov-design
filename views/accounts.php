<?php
/**
 * Accounts admin page.
 *
 * @package KatsarovDesign\SocialMediaScheduler
 */

declare(strict_types=1);

$accounts          = isset( $accounts ) && is_array( $accounts ) ? $accounts : array();
$meta_configured   = isset( $metaConfigured ) ? (bool) $metaConfigured : false;
$oauth_notice      = isset( $oauthNotice ) && is_array( $oauthNotice ) ? $oauthNotice : null;
$settings_url      = admin_url( 'admin.php?page=sms-settings' );
$tiktok_configured = isset( $tiktokConfigured ) ? (bool) $tiktokConfigured : false;
$meta_url          = $meta_configured ? wp_nonce_url( admin_url( 'admin-post.php?action=sms_oauth_meta_init' ), 'sms_oauth_init' ) : $settings_url;
$tiktok_url        = $tiktok_configured ? wp_nonce_url( admin_url( 'admin-post.php?action=sms_oauth_tiktok_init' ), 'sms_oauth_init' ) : $settings_url;
?>
<div class="wrap sms-app sms-app--accounts">
	<header class="sms-page-header">
		<div>
			<p class="sms-eyebrow"><?php esc_html_e( 'Publishing access', 'social-media-scheduler' ); ?></p>
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Connected Accounts', 'social-media-scheduler' ); ?></h1>
		</div>
		<div class="sms-actions">
			<a class="button button-primary" href="<?php echo esc_url( $meta_url ); ?>">
				<span class="dashicons dashicons-facebook-alt" aria-hidden="true"></span>
				<?php echo esc_html( $meta_configured ? __( 'Connect Meta', 'social-media-scheduler' ) : __( 'Configure Meta', 'social-media-scheduler' ) ); ?>
			</a>
			<a class="button" href="<?php echo esc_url( $tiktok_url ); ?>">
				<span class="dashicons dashicons-video-alt3" aria-hidden="true"></span>
				<?php echo esc_html( $tiktok_configured ? __( 'Connect TikTok', 'social-media-scheduler' ) : __( 'Configure TikTok', 'social-media-scheduler' ) ); ?>
			</a>
		</div>
	</header>

	<?php if ( null !== $oauth_notice && ! empty( $oauth_notice['message'] ) ) : ?>
		<div class="notice notice-<?php echo esc_attr( 'success' === $oauth_notice['type'] ? 'success' : 'error' ); ?>">
			<p><?php echo esc_html( (string) $oauth_notice['message'] ); ?></p>
		</div>
	<?php endif; ?>

	<div class="sms-inline-status" id="sms-accounts-status" aria-live="polite"></div>

	<section class="sms-panel">
		<table class="widefat striped sms-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Account', 'social-media-scheduler' ); ?></th>
					<th><?php esc_html_e( 'Provider', 'social-media-scheduler' ); ?></th>
					<th><?php esc_html_e( 'Connected', 'social-media-scheduler' ); ?></th>
					<th class="sms-table__actions"><?php esc_html_e( 'Actions', 'social-media-scheduler' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $accounts ) ) : ?>
					<tr>
						<td colspan="4"><?php esc_html_e( 'No accounts connected yet.', 'social-media-scheduler' ); ?></td>
					</tr>
				<?php endif; ?>
				<?php foreach ( $accounts as $account ) : ?>
					<tr data-account-id="<?php echo esc_attr( (string) $account['id'] ); ?>">
						<td>
							<strong><?php echo esc_html( (string) $account['accountName'] ); ?></strong><br />
							<span class="description"><?php echo esc_html( (string) $account['providerUserId'] ); ?></span>
						</td>
						<td><span class="sms-badge sms-badge--platform"><?php echo esc_html( ucfirst( (string) $account['platform'] ) ); ?></span></td>
						<td><?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( (string) $account['connectedAt'] ) ) ); ?></td>
						<td class="sms-table__actions">
							<button class="button-link-delete sms-disconnect-account" type="button" data-account-id="<?php echo esc_attr( (string) $account['id'] ); ?>">
								<?php esc_html_e( 'Disconnect', 'social-media-scheduler' ); ?>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</section>
</div>
