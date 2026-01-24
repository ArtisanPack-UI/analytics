<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rate limiting middleware for analytics tracking endpoints.
 *
 * Prevents abuse of tracking endpoints by limiting the number of
 * requests per IP address within a time window.
 *
 * @since   1.0.0
 *
 * @package ArtisanPackUI\Analytics\Http\Middleware
 */
class AnalyticsThrottle
{
	/**
	 * The rate limiter instance.
	 *
	 * @var RateLimiter
	 */
	protected RateLimiter $limiter;

	/**
	 * Create a new middleware instance.
	 *
	 * @param RateLimiter $limiter The rate limiter.
	 *
	 * @since 1.0.0
	 */
	public function __construct( RateLimiter $limiter )
	{
		$this->limiter = $limiter;
	}

	/**
	 * Handle an incoming request.
	 *
	 * @param Request $request The incoming request.
	 * @param Closure $next    The next middleware.
	 *
	 * @return Response
	 *
	 * @since 1.0.0
	 */
	public function handle( Request $request, Closure $next ): Response
	{
		// Check if rate limiting is enabled
		if ( ! config( 'artisanpack.analytics.rate_limiting.enabled', true ) ) {
			return $next( $request );
		}

		$key          = $this->resolveRequestSignature( $request );
		$maxAttempts  = config( 'artisanpack.analytics.rate_limiting.max_attempts', 60 );
		$decayMinutes = config( 'artisanpack.analytics.rate_limiting.decay_minutes', 1 );

		if ( $this->limiter->tooManyAttempts( $key, $maxAttempts ) ) {
			return $this->buildTooManyRequestsResponse( $key, $maxAttempts );
		}

		$this->limiter->hit( $key, $decayMinutes * 60 );

		$response = $next( $request );

		return $this->addRateLimitHeaders(
			$response,
			$maxAttempts,
			$this->limiter->remaining( $key, $maxAttempts ),
		);
	}

	/**
	 * Resolve the request signature for rate limiting.
	 *
	 * Uses IP address as the primary identifier. When IP is unavailable,
	 * falls back to a combination of session ID and user agent to create
	 * a unique signature for each client.
	 *
	 * @param Request $request The incoming request.
	 *
	 * @return string
	 *
	 * @since 1.0.0
	 */
	protected function resolveRequestSignature( Request $request ): string
	{
		$ip = $request->ip();

		if ( null !== $ip ) {
			return 'analytics_throttle:' . sha1( $ip );
		}

		// Fallback: build a unique key from available request attributes
		$fallbackParts = [];

		// Try to get session ID if available
		if ( $request->hasSession() ) {
			$fallbackParts[] = $request->session()->getId();
		}

		// Include user agent hash
		$userAgent = $request->userAgent();
		if ( null !== $userAgent ) {
			$fallbackParts[] = $userAgent;
		}

		// Include X-Forwarded-For header if present
		$forwardedFor = $request->header( 'X-Forwarded-For' );
		if ( null !== $forwardedFor ) {
			$fallbackParts[] = $forwardedFor;
		}

		// If we have fallback parts, use them; otherwise use a permissive shared key
		if ( ! empty( $fallbackParts ) ) {
			return 'analytics_throttle:' . sha1( implode( '|', $fallbackParts ) );
		}

		// Log warning for monitoring when no identifying information is available
		logger()->debug( 'Analytics throttle: Unable to identify client, using shared rate limit key' );

		return 'analytics_throttle:unknown_client';
	}

	/**
	 * Build a response for when too many requests have been made.
	 *
	 * @param string $key         The rate limit key.
	 * @param int    $maxAttempts The maximum attempts allowed.
	 *
	 * @return Response
	 *
	 * @since 1.0.0
	 */
	protected function buildTooManyRequestsResponse( string $key, int $maxAttempts ): Response
	{
		$retryAfter = $this->limiter->availableIn( $key );

		return response()->json( [
			'error'   => 'Too many requests',
			'message' => __( 'Rate limit exceeded. Please try again later.' ),
		], 429 )->withHeaders( [
			'Retry-After'               => $retryAfter,
			'X-RateLimit-Limit'         => $maxAttempts,
			'X-RateLimit-Remaining'     => 0,
			'X-RateLimit-Reset'         => time() + $retryAfter,
		] );
	}

	/**
	 * Add rate limit headers to the response.
	 *
	 * @param Response $response    The response.
	 * @param int      $maxAttempts The maximum attempts allowed.
	 * @param int      $remaining   The remaining attempts.
	 *
	 * @return Response
	 *
	 * @since 1.0.0
	 */
	protected function addRateLimitHeaders( Response $response, int $maxAttempts, int $remaining ): Response
	{
		$response->headers->add( [
			'X-RateLimit-Limit'     => $maxAttempts,
			'X-RateLimit-Remaining' => max( 0, $remaining ),
		] );

		return $response;
	}
}
