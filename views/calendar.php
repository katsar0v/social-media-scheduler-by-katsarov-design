<?php
/**
 * Calendar admin page.
 *
 * @package KatsarovDesign\SocialMediaScheduler
 */

declare(strict_types=1);
?>
<div class="wrap sms-app sms-app--calendar">
	<header class="sms-page-header">
		<div>
			<p class="sms-eyebrow"><?php esc_html_e( 'Editorial calendar', 'social-media-scheduler' ); ?></p>
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Social Scheduler', 'social-media-scheduler' ); ?></h1>
		</div>
		<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=sms-new-post' ) ); ?>">
			<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
			<?php esc_html_e( 'New Post', 'social-media-scheduler' ); ?>
		</a>
	</header>

	<section class="sms-toolbar" aria-label="<?php esc_attr_e( 'Calendar controls', 'social-media-scheduler' ); ?>">
		<div class="sms-toolbar__group">
			<button class="button" id="sms-prev-month" type="button" aria-label="<?php esc_attr_e( 'Previous month', 'social-media-scheduler' ); ?>">
				<span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
			</button>
			<button class="button" id="sms-today" type="button"><?php esc_html_e( 'Today', 'social-media-scheduler' ); ?></button>
			<button class="button" id="sms-next-month" type="button" aria-label="<?php esc_attr_e( 'Next month', 'social-media-scheduler' ); ?>">
				<span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
			</button>
		</div>
		<h2 class="sms-month-label" id="sms-month-label">
			<span id="sms-month-label-text"></span>
			<span class="sms-calendar-loading-indicator" id="sms-calendar-loading-indicator" aria-hidden="true" hidden></span>
		</h2>
		<div class="sms-filter" role="group" aria-label="<?php esc_attr_e( 'Filter by status', 'social-media-scheduler' ); ?>">
			<button class="button sms-filter__button is-active" type="button" data-status="ALL"><?php esc_html_e( 'All', 'social-media-scheduler' ); ?></button>
			<button class="button sms-filter__button" type="button" data-status="DRAFT"><?php esc_html_e( 'Draft', 'social-media-scheduler' ); ?></button>
			<button class="button sms-filter__button" type="button" data-status="SCHEDULED"><?php esc_html_e( 'Scheduled', 'social-media-scheduler' ); ?></button>
			<button class="button sms-filter__button" type="button" data-status="PUBLISHED"><?php esc_html_e( 'Published', 'social-media-scheduler' ); ?></button>
			<button class="button sms-filter__button" type="button" data-status="FAILED"><?php esc_html_e( 'Failed', 'social-media-scheduler' ); ?></button>
		</div>
	</section>

	<div class="sms-inline-status" id="sms-calendar-status" aria-live="polite"></div>
	<div class="sms-calendar" id="sms-calendar-grid" aria-live="polite"></div>
</div>
