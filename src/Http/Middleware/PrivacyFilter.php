<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Privacy filter middleware for analytics tracking.
 *
 * Checks privacy settings and blocks tracking requests that should
 * be excluded based on IP, user agent, path, or consent status.
 *
 * @since   1.0.0
 *
 * @package ArtisanPackUI\Analytics\Http\Middleware
 */
class PrivacyFilter
{
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
		// Check if analytics is enabled
		if ( ! config( 'artisanpack.analytics.enabled', true ) ) {
			return $this->returnNoContent();
		}

		// Check Do Not Track header
		if ( $this->shouldRespectDnt( $request ) ) {
			return $this->returnNoContent();
		}

		// Check excluded IPs
		if ( $this->isExcludedIp( $request ) ) {
			return $this->returnNoContent();
		}

		// Check excluded user agents (bots, crawlers)
		if ( $this->isExcludedUserAgent( $request ) ) {
			return $this->returnNoContent();
		}

		// Check excluded paths
		if ( $this->isExcludedPath( $request ) ) {
			return $this->returnNoContent();
		}

		return $next( $request );
	}

	/**
	 * Check if we should respect the Do Not Track header.
	 *
	 * @param Request $request The incoming request.
	 *
	 * @return bool
	 *
	 * @since 1.0.0
	 */
	protected function shouldRespectDnt( Request $request ): bool
	{
		if ( ! config( 'artisanpack.analytics.privacy.respect_dnt', true ) ) {
			return false;
		}

		$dnt = $request->header( 'DNT' ) ?? $request->header( 'Sec-GPC' );

		return '1' === $dnt;
	}

	/**
	 * Check if the request IP is excluded from tracking.
	 *
	 * @param Request $request The incoming request.
	 *
	 * @return bool
	 *
	 * @since 1.0.0
	 */
	protected function isExcludedIp( Request $request ): bool
	{
		$excludedIps = config( 'artisanpack.analytics.privacy.excluded_ips', [] );
		$requestIp   = $request->ip();

		if ( empty( $excludedIps ) || null === $requestIp ) {
			return false;
		}

		foreach ( $excludedIps as $excludedIp ) {
			if ( $this->ipMatches( $requestIp, $excludedIp ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if an IP matches an exclusion rule (supports CIDR notation).
	 *
	 * @param string $ip           The IP address to check.
	 * @param string $exclusionIp The exclusion IP or CIDR range.
	 *
	 * @return bool
	 *
	 * @since 1.0.0
	 */
	protected function ipMatches( string $ip, string $exclusionIp ): bool
	{
		// Exact match
		if ( $ip === $exclusionIp ) {
			return true;
		}

		// CIDR match
		if ( str_contains( $exclusionIp, '/' ) ) {
			return $this->ipMatchesCidr( $ip, $exclusionIp );
		}

		return false;
	}

	/**
	 * Check if an IP matches a CIDR range.
	 *
	 * @param string $ip   The IP address to check.
	 * @param string $cidr The CIDR range.
	 *
	 * @return bool
	 *
	 * @since 1.0.0
	 */
	protected function ipMatchesCidr( string $ip, string $cidr ): bool
	{
		[ $subnet, $mask ] = explode( '/', $cidr );

		// Check if this is an IPv6 address
		if ( str_contains( $ip, ':' ) || str_contains( $subnet, ':' ) ) {
			return $this->ipv6MatchesCidr( $ip, $cidr );
		}

		// IPv4 handling
		$ipLong     = ip2long( $ip );
		$subnetLong = ip2long( $subnet );

		if ( false === $ipLong || false === $subnetLong ) {
			return false;
		}

		$mask = -1 << ( 32 - (int) $mask );

		return ( $ipLong & $mask ) === ( $subnetLong & $mask );
	}

	protected function ipv6MatchesCidr( string $ip, string $cidr ): bool
	{
		[ $subnet, $mask ] = explode( '/', $cidr );
		
		$ipBinary     = inet_pton( $ip );
		$subnetBinary = inet_pton( $subnet );
		
		if ( false === $ipBinary || false === $subnetBinary ) {
			return false;
		}
		
		$mask      = (int) $mask;
		$byteCount = strlen( $ipBinary );
		
		for ( $i = 0; $i < $byteCount; $i++ ) {
			$bitsToCheck = min( 8, $mask - ( $i * 8 ) );
			
			if ( $bitsToCheck <= 0 ) {
				break;
			}
			
			$maskByte = 0xFF << ( 8 - $bitsToCheck );
			
			if ( ( ord( $ipBinary[ $i ] ) & $maskByte ) !== ( ord( $subnetBinary[ $i ] ) & $maskByte ) ) {
				return false;
			}
		}
		
		return true;
	}

	/**
	 * Check if the request user agent matches an exclusion pattern.
	 *
	 * @param Request $request The incoming request.
	 *
	 * @return bool
	 *
	 * @since 1.0.0
	 */
	protected function isExcludedUserAgent( Request $request ): bool
	{
		$excludedPatterns = config( 'artisanpack.analytics.privacy.excluded_user_agents', [] );
		$userAgent        = $request->userAgent();

		if ( empty( $excludedPatterns ) || null === $userAgent ) {
			return false;
		}

		foreach ( $excludedPatterns as $pattern ) {
			$result = @preg_match( $pattern, $userAgent );
			
			if ( false === $result ) {
				// Log invalid pattern and continue
				logger()->warning( 'Invalid regex pattern in analytics excluded_user_agents', [
					'pattern' => $pattern,
				] );
				continue;
			}
			
			if ( 1 === $result ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if the request path matches an exclusion pattern.
	 *
	 * @param Request $request The incoming request.
	 *
	 * @return bool
	 *
	 * @since 1.0.0
	 */
	protected function isExcludedPath( Request $request ): bool
	{
		$excludedPaths = config( 'artisanpack.analytics.privacy.excluded_paths', [] );
		$path          = $request->input( 'path', $request->path() );

		if ( empty( $excludedPaths ) ) {
			return false;
		}

		foreach ( $excludedPaths as $excludedPath ) {
			if ( $this->pathMatches( $path, $excludedPath ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if a path matches an exclusion pattern (supports wildcards).
	 *
	 * @param string $path    The path to check.
	 * @param string $pattern The exclusion pattern.
	 *
	 * @return bool
	 *
	 * @since 1.0.0
	 */
	protected function pathMatches( string $path, string $pattern ): bool
	{
		// Normalize paths
		$path    = '/' . ltrim( $path, '/' );
		$pattern = '/' . ltrim( $pattern, '/' );

		// Exact match
		if ( $path === $pattern ) {
			return true;
		}

		// Wildcard match
		if ( str_contains( $pattern, '*' ) ) {
			// Escape regex metacharacters first, then convert escaped wildcards to regex
			$escaped = preg_quote( $pattern, '/' );
			$regex   = '/^' . str_replace( '\\*', '.*', $escaped ) . '$/';

			return 1 === preg_match( $regex, $path );
		}

		return false;
	}

	/**
	 * Return a 204 No Content response.
	 *
	 * This signals to the tracker that the request was received but
	 * no tracking occurred due to privacy settings.
	 *
	 * @return Response
	 *
	 * @since 1.0.0
	 */
	protected function returnNoContent(): Response
	{
		return response()->noContent();
	}
}
