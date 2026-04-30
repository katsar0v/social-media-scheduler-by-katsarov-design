<?php
/**
 * Settings admin page.
 *
 * @package KatsarovDesign\SocialMediaScheduler
 */

declare(strict_types=1);

$settings          = isset( $settings ) && is_array( $settings ) ? $settings : array();
$meta_redirect_uri = rest_url( 'sms/v1/auth/meta/callback' );
$timezones         = array(
	'Europe/Sofia',
	'Europe/London',
	'Europe/Paris',
	'Europe/Berlin',
	'Europe/Madrid',
	'Europe/Rome',
	'Europe/Amsterdam',
	'Europe/Athens',
	'America/New_York',
	'America/Chicago',
	'America/Denver',
	'America/Los_Angeles',
	'Asia/Tokyo',
	'Asia/Shanghai',
	'Australia/Sydney',
	'UTC',
);
?>
<div class="wrap sms-app sms-app--settings">
	<header class="sms-page-header">
		<div>
			<p class="sms-eyebrow"><?php esc_html_e( 'Configuration', 'social-media-scheduler' ); ?></p>
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Settings', 'social-media-scheduler' ); ?></h1>
		</div>
	</header>

	<form class="sms-form sms-settings-form" id="sms-settings-form">
		<div class="sms-form-grid">
			<section class="sms-panel">
				<h2><?php esc_html_e( 'Editorial Defaults', 'social-media-scheduler' ); ?></h2>
				<label for="sms-timezone"><?php esc_html_e( 'Timezone', 'social-media-scheduler' ); ?></label>
				<select id="sms-timezone" name="timezone">
					<?php foreach ( $timezones as $timezone ) : ?>
						<option value="<?php echo esc_attr( $timezone ); ?>" <?php selected( (string) ( $settings['timezone'] ?? 'Europe/Sofia' ), $timezone ); ?>>
							<?php echo esc_html( $timezone ); ?>
						</option>
					<?php endforeach; ?>
				</select>

				<label for="sms-default-platform"><?php esc_html_e( 'Default Platform', 'social-media-scheduler' ); ?></label>
				<select id="sms-default-platform" name="defaultPlatform">
					<option value="instagram" <?php selected( (string) ( $settings['defaultPlatform'] ?? '' ), 'instagram' ); ?>><?php esc_html_e( 'Instagram', 'social-media-scheduler' ); ?></option>
					<option value="facebook" <?php selected( (string) ( $settings['defaultPlatform'] ?? '' ), 'facebook' ); ?>><?php esc_html_e( 'Facebook', 'social-media-scheduler' ); ?></option>
					<option value="tiktok" <?php selected( (string) ( $settings['defaultPlatform'] ?? '' ), 'tiktok' ); ?>><?php esc_html_e( 'TikTok', 'social-media-scheduler' ); ?></option>
				</select>

				<label for="sms-default-status"><?php esc_html_e( 'Default Status', 'social-media-scheduler' ); ?></label>
				<select id="sms-default-status" name="defaultPostStatus">
					<option value="DRAFT" <?php selected( (string) ( $settings['defaultPostStatus'] ?? '' ), 'DRAFT' ); ?>><?php esc_html_e( 'Draft', 'social-media-scheduler' ); ?></option>
					<option value="IN_REVIEW" <?php selected( (string) ( $settings['defaultPostStatus'] ?? '' ), 'IN_REVIEW' ); ?>><?php esc_html_e( 'In review', 'social-media-scheduler' ); ?></option>
					<option value="APPROVED" <?php selected( (string) ( $settings['defaultPostStatus'] ?? '' ), 'APPROVED' ); ?>><?php esc_html_e( 'Approved', 'social-media-scheduler' ); ?></option>
				</select>

				<label for="sms-brand-hashtags"><?php esc_html_e( 'Brand Hashtags', 'social-media-scheduler' ); ?></label>
				<textarea id="sms-brand-hashtags" name="brandHashtags" rows="3"><?php echo esc_textarea( (string) ( $settings['brandHashtags'] ?? '' ) ); ?></textarea>

				<label for="sms-calendar-week-start"><?php esc_html_e( 'Calendar Week Start', 'social-media-scheduler' ); ?></label>
				<select id="sms-calendar-week-start" name="calendarWeekStart">
					<option value="1" <?php selected( (int) ( $settings['calendarWeekStart'] ?? 1 ), 1 ); ?>><?php esc_html_e( 'Monday', 'social-media-scheduler' ); ?></option>
					<option value="0" <?php selected( (int) ( $settings['calendarWeekStart'] ?? 1 ), 0 ); ?>><?php esc_html_e( 'Sunday', 'social-media-scheduler' ); ?></option>
				</select>
			</section>

			<section class="sms-panel">
				<h2><?php esc_html_e( 'OAuth Apps', 'social-media-scheduler' ); ?></h2>
				<label for="sms-meta-app-id"><?php esc_html_e( 'Meta App ID', 'social-media-scheduler' ); ?></label>
				<input id="sms-meta-app-id" name="metaAppId" type="text" value="<?php echo esc_attr( (string) ( $settings['metaAppId'] ?? '' ) ); ?>" />

				<label for="sms-meta-app-secret"><?php esc_html_e( 'Meta App Secret', 'social-media-scheduler' ); ?></label>
				<input id="sms-meta-app-secret" name="metaAppSecret" type="password" value="<?php echo esc_attr( (string) ( $settings['metaAppSecret'] ?? '' ) ); ?>" autocomplete="new-password" />

				<label for="sms-meta-redirect-uri"><?php esc_html_e( 'Meta Redirect URI', 'social-media-scheduler' ); ?></label>
				<input id="sms-meta-redirect-uri" type="url" value="<?php echo esc_attr( $meta_redirect_uri ); ?>" readonly disabled />

				<label for="sms-tiktok-client-key"><?php esc_html_e( 'TikTok Client Key', 'social-media-scheduler' ); ?></label>
				<input id="sms-tiktok-client-key" name="tiktokClientKey" type="text" value="<?php echo esc_attr( (string) ( $settings['tiktokClientKey'] ?? '' ) ); ?>" />

				<label for="sms-tiktok-client-secret"><?php esc_html_e( 'TikTok Client Secret', 'social-media-scheduler' ); ?></label>
				<input id="sms-tiktok-client-secret" name="tiktokClientSecret" type="password" value="<?php echo esc_attr( (string) ( $settings['tiktokClientSecret'] ?? '' ) ); ?>" autocomplete="new-password" />

				<label for="sms-tiktok-redirect-uri"><?php esc_html_e( 'TikTok Redirect URI', 'social-media-scheduler' ); ?></label>
				<input id="sms-tiktok-redirect-uri" name="tiktokRedirectUri" type="url" value="<?php echo esc_attr( (string) ( $settings['tiktokRedirectUri'] ?? rest_url( 'sms/v1/auth/tiktok/callback' ) ) ); ?>" />

				<label for="sms-base-url"><?php esc_html_e( 'Base URL Override', 'social-media-scheduler' ); ?></label>
				<input id="sms-base-url" name="baseUrl" type="url" value="<?php echo esc_attr( (string) ( $settings['baseUrl'] ?? home_url() ) ); ?>" />

				<label class="sms-checkbox" for="sms-remove-on-uninstall">
					<input id="sms-remove-on-uninstall" name="removeOnUninstall" type="checkbox" <?php checked( ! empty( $settings['removeOnUninstall'] ) ); ?> />
					<span><?php esc_html_e( 'Remove plugin data during uninstall', 'social-media-scheduler' ); ?></span>
				</label>
			</section>
		</div>

		<div class="sms-actions">
			<button class="button button-primary" type="submit">
				<span class="dashicons dashicons-saved" aria-hidden="true"></span>
				<?php esc_html_e( 'Save Settings', 'social-media-scheduler' ); ?>
			</button>
			<span class="sms-inline-status" id="sms-settings-status" aria-live="polite"></span>
		</div>
	</form>
</div>
