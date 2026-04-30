<?php
/**
 * Post service validation parity tests.
 *
 * @package KatsarovDesign\SocialMediaScheduler
 */

declare(strict_types=1);

use KatsarovDesign\SocialMediaScheduler\Repository\PostRepository;
use KatsarovDesign\SocialMediaScheduler\Repository\SocialAccountRepository;
use KatsarovDesign\SocialMediaScheduler\Service\PostService;
use KatsarovDesign\SocialMediaScheduler\Service\PostValidationException;

final class PostServiceValidationTest extends SmsTestCase {
	private PostService $service;
	private array $account;

	public function set_up(): void {
		parent::set_up();
		$this->account = $this->create_meta_account();
		$this->service = new PostService( new PostRepository(), new SocialAccountRepository() );
	}

	public function test_rejects_empty_caption_for_non_story_posts(): void {
		$this->expectException( PostValidationException::class );

		$this->service->create(
			array(
				'caption'         => '   ',
				'platform'        => 'instagram',
				'socialAccountId' => (int) $this->account['id'],
				'scheduledAt'     => gmdate( DATE_ATOM, time() + DAY_IN_SECONDS ),
			)
		);
	}

	public function test_rejects_unknown_platform(): void {
		$this->expectException( PostValidationException::class );

		$this->service->create(
			array(
				'caption'         => 'Hello',
				'platform'        => 'myspace',
				'socialAccountId' => (int) $this->account['id'],
				'scheduledAt'     => gmdate( DATE_ATOM, time() + DAY_IN_SECONDS ),
			)
		);
	}

	public function test_future_published_intent_becomes_scheduled(): void {
		$post = $this->service->create(
			array(
				'caption'         => 'Future post',
				'platform'        => 'instagram',
				'socialAccountId' => (int) $this->account['id'],
				'scheduledAt'     => gmdate( DATE_ATOM, time() + DAY_IN_SECONDS ),
				'status'          => 'PUBLISHED',
			)
		);

		$this->assertSame( 'SCHEDULED', $post['status'] );
	}

	public function test_rejects_system_managed_input_statuses(): void {
		$this->expectException( PostValidationException::class );

		$this->service->create(
			array(
				'caption'         => 'Manual scheduled status',
				'platform'        => 'instagram',
				'socialAccountId' => (int) $this->account['id'],
				'scheduledAt'     => gmdate( DATE_ATOM, time() + DAY_IN_SECONDS ),
				'status'          => 'SCHEDULED',
			)
		);
	}
}
