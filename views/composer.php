<?php
/**
 * Composer admin page.
 *
 * @package KatsarovDesign\SocialMediaScheduler
 */

declare(strict_types=1);

$accounts = isset( $accounts ) && is_array( $accounts ) ? $accounts : array();
$settings = isset( $settings ) && is_array( $settings ) ? $settings : array();
?>
<div class="wrap sms-app sms-app--composer">
	<header class="sms-page-header">
		<div>
			<p class="sms-eyebrow"><?php esc_html_e( 'Create post', 'social-media-scheduler' ); ?></p>
			<h1 class="wp-heading-inline"><?php esc_html_e( 'New Post', 'social-media-scheduler' ); ?></h1>
		</div>
		<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=sms-calendar' ) ); ?>">
			<span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span>
			<?php esc_html_e( 'Calendar', 'social-media-scheduler' ); ?>
		</a>
	</header>

	<form class="sms-form sms-composer-form" id="sms-composer-form">
		<div class="sms-form-grid">
			<section class="sms-panel">
				<h2><?php esc_html_e( 'Content', 'social-media-scheduler' ); ?></h2>
				<label for="sms-caption"><?php esc_html_e( 'Caption', 'social-media-scheduler' ); ?></label>
				<textarea id="sms-caption" name="caption" rows="8"></textarea>

				<label for="sms-account"><?php esc_html_e( 'Destination', 'social-media-scheduler' ); ?></label>
				<select id="sms-account" name="socialAccountId" <?php disabled( empty( $accounts ) ); ?>>
					<option value=""><?php esc_html_e( 'Select connected account', 'social-media-scheduler' ); ?></option>
					<?php foreach ( $accounts as $account ) : ?>
						<?php if ( 'tiktok' === $account['platform'] ) : ?>
							<option value="<?php echo esc_attr( (string) $account['id'] ); ?>" data-platform="tiktok">
								<?php /* translators: %s: TikTok account display name. */ ?>
								<?php echo esc_html( sprintf( __( 'TikTok — %s', 'social-media-scheduler' ), $account['accountName'] ) ); ?>
							</option>
						<?php else : ?>
							<option value="<?php echo esc_attr( (string) $account['id'] ); ?>" data-platform="facebook">
								<?php /* translators: %s: Facebook page name. */ ?>
								<?php echo esc_html( sprintf( __( 'Meta — %s (Facebook)', 'social-media-scheduler' ), $account['accountName'] ) ); ?>
							</option>
							<option value="<?php echo esc_attr( (string) $account['id'] ); ?>" data-platform="instagram">
								<?php /* translators: %s: Instagram business account name. */ ?>
								<?php echo esc_html( sprintf( __( 'Meta — %s (Instagram)', 'social-media-scheduler' ), $account['accountName'] ) ); ?>
							</option>
						<?php endif; ?>
					<?php endforeach; ?>
				</select>
				<input type="hidden" id="sms-platform" name="platform" value="<?php echo esc_attr( (string) ( $settings['defaultPlatform'] ?? 'instagram' ) ); ?>" />

				<label class="sms-checkbox" for="sms-is-story">
					<input type="checkbox" id="sms-is-story" name="isStory" />
					<span><?php esc_html_e( 'Story post', 'social-media-scheduler' ); ?></span>
				</label>

				<label for="sms-scheduled-at"><?php esc_html_e( 'Date and time', 'social-media-scheduler' ); ?></label>
				<input id="sms-scheduled-at" name="scheduledAt" type="datetime-local" />

				<label for="sms-status"><?php esc_html_e( 'Status', 'social-media-scheduler' ); ?></label>
				<select id="sms-status" name="status">
					<option value="DRAFT"><?php esc_html_e( 'Draft', 'social-media-scheduler' ); ?></option>
					<option value="IN_REVIEW"><?php esc_html_e( 'In review', 'social-media-scheduler' ); ?></option>
					<option value="APPROVED"><?php esc_html_e( 'Approved', 'social-media-scheduler' ); ?></option>
					<option value="PUBLISHED"><?php esc_html_e( 'Publish or schedule', 'social-media-scheduler' ); ?></option>
					<option value="CANCELLED"><?php esc_html_e( 'Cancelled', 'social-media-scheduler' ); ?></option>
				</select>

				<label for="sms-notes"><?php esc_html_e( 'Notes', 'social-media-scheduler' ); ?></label>
				<textarea id="sms-notes" name="notes" rows="4"></textarea>
			</section>

			<aside class="sms-panel sms-panel--side">
				<h2><?php esc_html_e( 'Media', 'social-media-scheduler' ); ?></h2>
				<button class="button" id="sms-media-picker" type="button">
					<span class="dashicons dashicons-format-gallery" aria-hidden="true"></span>
					<?php esc_html_e( 'Choose Media', 'social-media-scheduler' ); ?>
				</button>
				<ul class="sms-media-list" id="sms-media-list"></ul>
			</aside>
		</div>

		<div class="sms-actions">
			<button class="button button-primary" type="submit">
				<span class="dashicons dashicons-saved" aria-hidden="true"></span>
				<?php esc_html_e( 'Save', 'social-media-scheduler' ); ?>
			</button>
			<button class="button" id="sms-publish-now" type="button">
				<span class="dashicons dashicons-megaphone" aria-hidden="true"></span>
				<?php esc_html_e( 'Publish Now', 'social-media-scheduler' ); ?>
			</button>
			<span class="sms-inline-status" id="sms-composer-status" aria-live="polite"></span>
		</div>
	</form>
</div>
