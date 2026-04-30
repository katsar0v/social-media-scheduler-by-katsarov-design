<?php
/**
 * Post status lifecycle tests.
 *
 * @package KatsarovDesign\SocialMediaScheduler
 */

declare(strict_types=1);

use KatsarovDesign\SocialMediaScheduler\Domain\PostStatus;

final class PostStatusTest extends SmsTestCase {
	public function test_allows_editorial_progression_to_scheduled(): void {
		$this->assertTrue( PostStatus::can_transition( 'DRAFT', 'IN_REVIEW' ) );
		$this->assertTrue( PostStatus::can_transition( 'IN_REVIEW', 'APPROVED' ) );
		$this->assertTrue( PostStatus::can_transition( 'APPROVED', 'SCHEDULED' ) );
	}

	public function test_rejects_reopening_published_posts_as_drafts(): void {
		$this->assertFalse( PostStatus::can_transition( 'PUBLISHED', 'DRAFT' ) );
		$this->assertTrue( PostStatus::can_transition( 'PUBLISHED', 'CANCELLED' ) );
	}
}
