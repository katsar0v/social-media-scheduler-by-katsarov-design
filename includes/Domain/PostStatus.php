<?php
/**
 * Post lifecycle status enum.
 *
 * @package KatsarovDesign\SocialMediaScheduler
 */

declare(strict_types=1);

namespace KatsarovDesign\SocialMediaScheduler\Domain;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

enum PostStatus: string {
	case DRAFT     = 'DRAFT';
	case IN_REVIEW = 'IN_REVIEW';
	case APPROVED  = 'APPROVED';
	case SCHEDULED = 'SCHEDULED';
	case PUBLISHED = 'PUBLISHED';
	case FAILED    = 'FAILED';
	case CANCELLED = 'CANCELLED';

	/**
	 * @return list<string>
	 */
	public static function values(): array {
		return array_map(
			static fn ( self $status ): string => $status->value,
			self::cases()
		);
	}

	/**
	 * @return list<string>
	 */
	public static function selectable_values(): array {
		return array(
			self::DRAFT->value,
			self::IN_REVIEW->value,
			self::APPROVED->value,
			self::PUBLISHED->value,
			self::CANCELLED->value,
		);
	}

	/**
	 * @return list<string>
	 */
	public static function selectableValues(): array {
		return self::selectable_values();
	}

	/**
	 * @return list<string>
	 */
	public static function creatable_values(): array {
		return self::selectable_values();
	}

	/**
	 * @return list<string>
	 */
	public static function creatableValues(): array {
		return self::creatable_values();
	}

	public static function is_valid( string $status ): bool {
		return null !== self::tryFrom( $status );
	}

	public static function can_transition( self|string $from, self|string $to ): bool {
		$from_status = $from instanceof self ? $from : self::tryFrom( $from );
		$to_status   = $to instanceof self ? $to : self::tryFrom( $to );

		if ( null === $from_status || null === $to_status ) {
			return false;
		}

		return in_array( $to_status->value, self::transition_values()[ $from_status->value ], true );
	}

	public static function canTransition( self|string $from, self|string $to ): bool {
		return self::can_transition( $from, $to );
	}

	/**
	 * @return array<string,list<string>>
	 */
	public static function transition_values(): array {
		return array(
			self::DRAFT->value     => array(
				self::DRAFT->value,
				self::IN_REVIEW->value,
				self::APPROVED->value,
				self::SCHEDULED->value,
				self::PUBLISHED->value,
				self::CANCELLED->value,
			),
			self::IN_REVIEW->value => array(
				self::DRAFT->value,
				self::IN_REVIEW->value,
				self::APPROVED->value,
				self::CANCELLED->value,
			),
			self::APPROVED->value  => array(
				self::DRAFT->value,
				self::IN_REVIEW->value,
				self::APPROVED->value,
				self::SCHEDULED->value,
				self::PUBLISHED->value,
				self::CANCELLED->value,
			),
			self::SCHEDULED->value => array(
				self::DRAFT->value,
				self::APPROVED->value,
				self::SCHEDULED->value,
				self::PUBLISHED->value,
				self::FAILED->value,
				self::CANCELLED->value,
			),
			self::PUBLISHED->value => array(
				self::PUBLISHED->value,
				self::CANCELLED->value,
			),
			self::FAILED->value    => array(
				self::DRAFT->value,
				self::APPROVED->value,
				self::SCHEDULED->value,
				self::PUBLISHED->value,
				self::FAILED->value,
				self::CANCELLED->value,
			),
			self::CANCELLED->value => array(
				self::DRAFT->value,
				self::CANCELLED->value,
			),
		);
	}
}
