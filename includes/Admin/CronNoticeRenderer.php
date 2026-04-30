<?php
/**
 * Cron admin notices.
 *
 * @package KatsarovDesign\SocialMediaScheduler
 */

declare(strict_types=1);

namespace KatsarovDesign\SocialMediaScheduler\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CronNoticeRenderer {
	private const DISMISS_ENABLED = 'sms_cron_enabled_notice_dismissed';

	public static function register(): void {
		add_action( 'admin_notices', array( self::class, 'render' ) );
		add_action( 'admin_init', array( self::class, 'handle_dismiss' ) );
	}

	public static function render(): void {
		if ( ! AdminMenu::is_plugin_page() || ! current_user_can( 'manage_social_scheduler' ) ) {
			return;
		}

		$wp_cron_disabled = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
		if ( $wp_cron_disabled ) {
			return;
		}

		$key = self::DISMISS_ENABLED;
		if ( get_user_meta( get_current_user_id(), $key, true ) ) {
			return;
		}

		$dismiss_url = wp_nonce_url(
			add_query_arg(
				array(
					'sms-dismiss-cron-notice' => 'enabled',
				)
			),
			'sms_dismiss_cron_notice'
		);

		$class   = 'notice notice-info is-dismissible';
		$message = __( 'WP-Cron is enabled. For reliable scheduled publishing, disable WP-Cron and configure a system cron job that calls wp-cron.php every minute.', 'social-media-scheduler' );
		$example = sprintf(
			/* translators: %s: WordPress cron URL. */
			__( 'Suggested cron command: * * * * * wget -q -O - %s >/dev/null 2>&1', 'social-media-scheduler' ),
			esc_url( site_url( 'wp-cron.php?doing_wp_cron' ) )
		);
		?>
		<div class="<?php echo esc_attr( $class ); ?>">
			<p><strong><?php esc_html_e( 'Social Scheduler cron', 'social-media-scheduler' ); ?></strong></p>
			<p><?php echo esc_html( $message ); ?></p>
			<p><code><?php echo esc_html( $example ); ?></code></p>
			<p><a href="<?php echo esc_url( $dismiss_url ); ?>"><?php esc_html_e( 'Dismiss this notice', 'social-media-scheduler' ); ?></a></p>
		</div>
		<?php
	}

	public static function handle_dismiss(): void {
		if ( empty( $_GET['sms-dismiss-cron-notice'] ) || 'enabled' !== $_GET['sms-dismiss-cron-notice'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		check_admin_referer( 'sms_dismiss_cron_notice' );

		update_user_meta( get_current_user_id(), self::DISMISS_ENABLED, 1 );
	}
}
