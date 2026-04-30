<?php
/**
 * Cron handler tests.
 *
 * @package KatsarovDesign\SocialMediaScheduler
 */

declare(strict_types=1);

use KatsarovDesign\SocialMediaScheduler\Cron\CronHandlers;
use KatsarovDesign\SocialMediaScheduler\Repository\PostRepository;

final class CronHandlersTest extends SmsTestCase {
	public function test_publish_tick_selects_due_deferred_platform_posts(): void {
		$posts = new PostRepository();
		$posts->create(
			array(
				'title'           => 'Due Instagram',
				'caption'         => 'Due caption',
				'platform'        => 'instagram',
				'socialAccountId' => 999999,
				'scheduledAt'     => gmdate( DATE_ATOM, time() - MINUTE_IN_SECONDS ),
				'status'          => 'SCHEDULED',
				'isStory'         => false,
			)
		);
		$posts->create(
			array(
				'title'           => 'Due Facebook Feed',
				'caption'         => 'Native scheduled by Facebook',
				'platform'        => 'facebook',
				'socialAccountId' => 999999,
				'scheduledAt'     => gmdate( DATE_ATOM, time() - MINUTE_IN_SECONDS ),
				'status'          => 'SCHEDULED',
				'isStory'         => false,
			)
		);
		$posts->create(
			array(
				'title'           => 'Future TikTok',
				'caption'         => 'Future caption',
				'platform'        => 'tiktok',
				'socialAccountId' => 999999,
				'scheduledAt'     => gmdate( DATE_ATOM, time() + HOUR_IN_SECONDS ),
				'status'          => 'SCHEDULED',
				'isStory'         => false,
			)
		);

		$result = CronHandlers::handle_publish_tick();

		$this->assertSame( 1, $result['checked'] );
		$this->assertSame( 1, $result['failed'] );
		$this->assertSame( 0, $result['skipped'] );
	}

	public function test_publish_tick_lock_skips_overlapping_runs(): void {
		set_transient( 'sms_publish_lock', 1, 90 );

		$result = CronHandlers::handle_publish_tick();

		$this->assertSame( 1, $result['skipped'] );
	}
}
