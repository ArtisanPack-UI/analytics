<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Services;

use ArtisanPackUI\Analytics\Models\PageView;
use ArtisanPackUI\Analytics\Models\Visitor;
use Illuminate\Support\Collection;

/**
 * Bot detection service.
 *
 * Implements a multi-signal confidence scoring system (0-100) for identifying
 * bot traffic. Each signal contributes points toward a bot confidence score
 * which is summed and capped at 100. A configurable threshold (default 70)
 * determines whether a visitor is considered a bot.
 *
 * @since   1.2.0
 *
 * @package ArtisanPackUI\Analytics\Services
 */
class BotDetector
{
	/**
	 * Score awarded when the user agent matches a known bot pattern.
	 *
	 * @var int
	 */
	protected const SCORE_KNOWN_BOT = 100;

	/**
	 * Score awarded when the user agent is empty or missing.
	 *
	 * @var int
	 */
	protected const SCORE_EMPTY_USER_AGENT = 80;

	/**
	 * Score awarded when headless browser indicators are present.
	 *
	 * @var int
	 */
	protected const SCORE_HEADLESS = 40;

	/**
	 * Score awarded when WebDriver automation is detected.
	 *
	 * @var int
	 */
	protected const SCORE_WEBDRIVER = 50;

	/**
	 * Score awarded when expected browser APIs are missing.
	 *
	 * @var int
	 */
	protected const SCORE_MISSING_APIS = 30;

	/**
	 * Score awarded for zero engagement across 3 or more page views.
	 *
	 * @var int
	 */
	protected const SCORE_ZERO_ENGAGEMENT = 35;

	/**
	 * Score awarded for rapid sequential requests.
	 *
	 * @var int
	 */
	protected const SCORE_RAPID_REQUESTS = 30;

	/**
	 * Score awarded when no referrer variation exists across a session.
	 *
	 * @var int
	 */
	protected const SCORE_NO_REFERRER_VARIATION = 15;

	/**
	 * Score awarded for perfectly timed request intervals.
	 *
	 * @var int
	 */
	protected const SCORE_PERFECT_INTERVALS = 20;

	/**
	 * Score awarded for suspiciously short page view times.
	 *
	 * @var int
	 */
	protected const SCORE_SHORT_PAGE_VIEWS = 25;

	/**
	 * Maximum possible bot confidence score.
	 *
	 * @var int
	 */
	protected const MAX_SCORE = 100;

	/**
	 * Create a new bot detector instance.
	 *
	 * @param DeviceDetector $deviceDetector The device detector used for user agent matching.
	 *
	 * @since 1.2.0
	 */
	public function __construct( protected DeviceDetector $deviceDetector )
	{
	}

	/**
	 * Calculate the bot confidence score for a visitor.
	 *
	 * @param Visitor $visitor The visitor to score.
	 *
	 * @return int A confidence score between 0 and 100.
	 *
	 * @since 1.2.0
	 */
	public function score( Visitor $visitor ): int
	{
		if ( $this->isWhitelisted( $visitor ) ) {
			return 0;
		}

		if ( $this->signalEnabled( 'user_agent' ) && $this->deviceDetector->isBot( $visitor->user_agent ) ) {
			return self::SCORE_KNOWN_BOT;
		}

		$score = 0;

		$score += $this->userAgentScore( $visitor );
		$score += $this->fingerprintScore( $visitor );
		$score += $this->engagementScore( $visitor );
		$score += $this->requestPatternScore( $visitor );

		return min( $score, self::MAX_SCORE );
	}

	/**
	 * Determine whether a visitor should be considered a bot.
	 *
	 * @param Visitor $visitor The visitor to evaluate.
	 *
	 * @return bool
	 *
	 * @since 1.2.0
	 */
	public function isBot( Visitor $visitor ): bool
	{
		if ( ! $this->enabled() ) {
			return false;
		}

		if ( $this->isWhitelisted( $visitor ) ) {
			return false;
		}

		return $this->score( $visitor ) >= $this->threshold();
	}

	/**
	 * Get the configured bot confidence threshold.
	 *
	 * @return int
	 *
	 * @since 1.2.0
	 */
	public function threshold(): int
	{
		return (int) config( 'artisanpack.analytics.bot_detection.threshold', 70 );
	}

	/**
	 * Determine whether bot detection is enabled.
	 *
	 * @return bool
	 *
	 * @since 1.2.0
	 */
	public function enabled(): bool
	{
		return (bool) config( 'artisanpack.analytics.bot_detection.enabled', true );
	}

	/**
	 * Determine whether a visitor is whitelisted from bot scoring.
	 *
	 * @param Visitor $visitor The visitor to check.
	 *
	 * @return bool
	 *
	 * @since 1.2.0
	 */
	public function isWhitelisted( Visitor $visitor ): bool
	{
		/** @var array<int, string> $userAgents */
		$userAgents = config( 'artisanpack.analytics.bot_detection.whitelist.user_agents', [] );

		if ( null !== $visitor->user_agent && '' !== $visitor->user_agent ) {
			$userAgentLower = strtolower( $visitor->user_agent );

			foreach ( $userAgents as $pattern ) {
				if ( '' !== $pattern && str_contains( $userAgentLower, strtolower( $pattern ) ) ) {
					return true;
				}
			}
		}

		/** @var array<int, string> $ips */
		$ips = config( 'artisanpack.analytics.bot_detection.whitelist.ips', [] );

		if ( null !== $visitor->ip_address && in_array( $visitor->ip_address, $ips, true ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Score user agent signals that do not match a known bot pattern.
	 *
	 * @param Visitor $visitor The visitor to score.
	 *
	 * @return int
	 *
	 * @since 1.2.0
	 */
	protected function userAgentScore( Visitor $visitor ): int
	{
		if ( ! $this->signalEnabled( 'user_agent' ) ) {
			return 0;
		}

		if ( null === $visitor->user_agent || '' === trim( $visitor->user_agent ) ) {
			return self::SCORE_EMPTY_USER_AGENT;
		}

		return 0;
	}

	/**
	 * Score JavaScript fingerprint signals.
	 *
	 * @param Visitor $visitor The visitor to score.
	 *
	 * @return int
	 *
	 * @since 1.2.0
	 */
	protected function fingerprintScore( Visitor $visitor ): int
	{
		if ( ! $this->signalEnabled( 'js_fingerprint' ) ) {
			return 0;
		}

		$pageViews = $this->pageViews( $visitor );
		$score     = 0;

		if ( $this->fingerprintFlag( $pageViews, 'webdriver' ) ) {
			$score += self::SCORE_WEBDRIVER;
		}

		if ( $this->fingerprintFlag( $pageViews, 'headless' ) ) {
			$score += self::SCORE_HEADLESS;
		}

		if ( $this->fingerprintFlag( $pageViews, 'missing_apis' ) ) {
			$score += self::SCORE_MISSING_APIS;
		}

		return $score;
	}

	/**
	 * Score engagement signals.
	 *
	 * @param Visitor $visitor The visitor to score.
	 *
	 * @return int
	 *
	 * @since 1.2.0
	 */
	protected function engagementScore( Visitor $visitor ): int
	{
		if ( ! $this->signalEnabled( 'engagement' ) ) {
			return 0;
		}

		$pageViews = $this->pageViews( $visitor );
		$score     = 0;

		if ( $this->hasZeroEngagement( $pageViews ) ) {
			$score += self::SCORE_ZERO_ENGAGEMENT;
		}

		if ( $this->hasShortPageViews( $pageViews ) ) {
			$score += self::SCORE_SHORT_PAGE_VIEWS;
		}

		return $score;
	}

	/**
	 * Score request pattern signals.
	 *
	 * @param Visitor $visitor The visitor to score.
	 *
	 * @return int
	 *
	 * @since 1.2.0
	 */
	protected function requestPatternScore( Visitor $visitor ): int
	{
		if ( ! $this->signalEnabled( 'request_patterns' ) ) {
			return 0;
		}

		$pageViews = $this->pageViews( $visitor );
		$score     = 0;

		if ( $this->hasRapidRequests( $pageViews ) ) {
			$score += self::SCORE_RAPID_REQUESTS;
		}

		if ( $this->hasNoReferrerVariation( $pageViews ) ) {
			$score += self::SCORE_NO_REFERRER_VARIATION;
		}

		if ( $this->hasPerfectIntervals( $pageViews ) ) {
			$score += self::SCORE_PERFECT_INTERVALS;
		}

		return $score;
	}

	/**
	 * Determine whether the visitor shows zero engagement across 3+ page views.
	 *
	 * @param Collection<int, PageView> $pageViews The visitor's page views.
	 *
	 * @return bool
	 *
	 * @since 1.2.0
	 */
	protected function hasZeroEngagement( Collection $pageViews ): bool
	{
		if ( $pageViews->count() < 3 ) {
			return false;
		}

		foreach ( $pageViews as $pageView ) {
			if ( (int) $pageView->engaged_time > 0 || (int) $pageView->scroll_depth > 0 ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Determine whether the visitor's average page view time is under one second.
	 *
	 * @param Collection<int, PageView> $pageViews The visitor's page views.
	 *
	 * @return bool
	 *
	 * @since 1.2.0
	 */
	protected function hasShortPageViews( Collection $pageViews ): bool
	{
		$times = $pageViews
			->map( fn ( PageView $pageView ): ?int => $pageView->time_on_page )
			->filter( fn ( ?int $time ): bool => null !== $time )
			->values();

		if ( $times->count() < 3 ) {
			return false;
		}

		return $times->avg() < 1;
	}

	/**
	 * Determine whether the visitor made requests faster than 10 pages per minute.
	 *
	 * @param Collection<int, PageView> $pageViews The visitor's page views.
	 *
	 * @return bool
	 *
	 * @since 1.2.0
	 */
	protected function hasRapidRequests( Collection $pageViews ): bool
	{
		$timestamps = $this->timestamps( $pageViews );

		if ( $timestamps->count() < 2 ) {
			return false;
		}

		$spanSeconds = $timestamps->last() - $timestamps->first();

		if ( $spanSeconds <= 0 ) {
			return true;
		}

		$pagesPerMinute = $timestamps->count() / ( $spanSeconds / 60 );

		return $pagesPerMinute > 10;
	}

	/**
	 * Determine whether the visitor used a single referrer across 3+ page views.
	 *
	 * @param Collection<int, PageView> $pageViews The visitor's page views.
	 *
	 * @return bool
	 *
	 * @since 1.2.0
	 */
	protected function hasNoReferrerVariation( Collection $pageViews ): bool
	{
		if ( $pageViews->count() < 3 ) {
			return false;
		}

		return $pageViews
			->map( fn ( PageView $pageView ): ?string => $pageView->referrer_path )
			->unique()
			->count() <= 1;
	}

	/**
	 * Determine whether requests arrived at perfectly even intervals.
	 *
	 * @param Collection<int, PageView> $pageViews The visitor's page views.
	 *
	 * @return bool
	 *
	 * @since 1.2.0
	 */
	protected function hasPerfectIntervals( Collection $pageViews ): bool
	{
		$timestamps = $this->timestamps( $pageViews );

		if ( $timestamps->count() < 3 ) {
			return false;
		}

		$intervals = [];

		for ( $index = 1; $index < $timestamps->count(); $index++ ) {
			$intervals[] = $timestamps->get( $index ) - $timestamps->get( $index - 1 );
		}

		// Even intervals are only suspicious when there is an actual gap between requests.
		if ( max( $intervals ) <= 0 ) {
			return false;
		}

		return 1 === count( array_unique( $intervals ) );
	}

	/**
	 * Get the visitor's page views as a collection.
	 *
	 * @param Visitor $visitor The visitor.
	 *
	 * @return Collection<int, PageView>
	 *
	 * @since 1.2.0
	 */
	protected function pageViews( Visitor $visitor ): Collection
	{
		/** @var Collection<int, PageView> $pageViews */
		$pageViews = $visitor->pageViews;

		return $pageViews;
	}

	/**
	 * Get the sorted page view timestamps in epoch seconds.
	 *
	 * @param Collection<int, PageView> $pageViews The visitor's page views.
	 *
	 * @return Collection<int, int>
	 *
	 * @since 1.2.0
	 */
	protected function timestamps( Collection $pageViews ): Collection
	{
		return $pageViews
			->map( fn ( PageView $pageView ): ?int => $pageView->created_at?->getTimestamp() )
			->filter( fn ( ?int $timestamp ): bool => null !== $timestamp )
			->sort()
			->values();
	}

	/**
	 * Determine whether any page view fingerprint reports the given flag.
	 *
	 * @param Collection<int, PageView> $pageViews The visitor's page views.
	 * @param string                    $flag      The fingerprint flag key.
	 *
	 * @return bool
	 *
	 * @since 1.2.0
	 */
	protected function fingerprintFlag( Collection $pageViews, string $flag ): bool
	{
		foreach ( $pageViews as $pageView ) {
			$customData = $pageView->custom_data;

			if ( ! is_array( $customData ) ) {
				continue;
			}

			$fingerprint = is_array( $customData['fingerprint'] ?? null )
				? $customData['fingerprint']
				: $customData;

			if ( ! empty( $fingerprint[ $flag ] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Determine whether a signal category is enabled in configuration.
	 *
	 * @param string $signal The signal category key.
	 *
	 * @return bool
	 *
	 * @since 1.2.0
	 */
	protected function signalEnabled( string $signal ): bool
	{
		return (bool) config( "artisanpack.analytics.bot_detection.signals.{$signal}", true );
	}
}
