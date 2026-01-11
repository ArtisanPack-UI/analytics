<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Services;

/**
 * IP address anonymization service.
 *
 * Anonymizes IP addresses for privacy compliance by zeroing out
 * the last octet (IPv4) or last 80 bits (IPv6).
 *
 * @since   1.0.0
 *
 * @package ArtisanPackUI\Analytics\Services
 */
class IpAnonymizer
{
	/**
	 * Anonymize an IP address.
	 *
	 * For IPv4: Zeros the last octet (e.g., 192.168.1.100 -> 192.168.1.0)
	 * For IPv6: Zeros the last 80 bits (5 groups)
	 *
	 * @param string|null $ip The IP address to anonymize.
	 *
	 * @return string|null The anonymized IP address, or null if input was null.
	 *
	 * @since 1.0.0
	 */
	public function anonymize( ?string $ip ): ?string
	{
		if ( null === $ip || '' === $ip ) {
			return null;
		}

		if ( ! $this->isEnabled() ) {
			return $ip;
		}

		// Check if IPv6
		if ( str_contains( $ip, ':' ) ) {
			return $this->anonymizeIpv6( $ip );
		}

		return $this->anonymizeIpv4( $ip );
	}

	/**
	 * Check if IP anonymization is enabled.
	 *
	 * @return bool
	 *
	 * @since 1.0.0
	 */
	public function isEnabled(): bool
	{
		return config( 'artisanpack.analytics.local.anonymize_ip', true );
	}

	/**
	 * Anonymize an IPv4 address.
	 *
	 * Zeros the last octet for privacy while preserving geographic accuracy.
	 *
	 * @param string $ip The IPv4 address.
	 *
	 * @return string The anonymized IPv4 address.
	 *
	 * @since 1.0.0
	 */
	protected function anonymizeIpv4( string $ip ): string
	{
		$parts = explode( '.', $ip );

		if ( 4 !== count( $parts ) ) {
			return $ip;
		}

		$parts[3] = '0';

		return implode( '.', $parts );
	}

	/**
	 * Anonymize an IPv6 address.
	 *
	 * Zeros the last 80 bits (last 5 groups) for privacy.
	 *
	 * @param string $ip The IPv6 address.
	 *
	 * @return string The anonymized IPv6 address.
	 *
	 * @since 1.0.0
	 */
	protected function anonymizeIpv6( string $ip ): string
	{
		// Handle IPv4-mapped IPv6 addresses (::ffff:192.168.1.1)
		if ( preg_match( '/^::ffff:(\d+\.\d+\.\d+\.\d+)$/i', $ip, $matches ) ) {
			return '::ffff:' . $this->anonymizeIpv4( $matches[1] );
		}

		// Expand the IPv6 address to full form
		$expanded = $this->expandIpv6( $ip );

		if ( null === $expanded ) {
			return $ip;
		}

		$groups = explode( ':', $expanded );

		if ( 8 !== count( $groups ) ) {
			return $ip;
		}

		// Zero out the last 5 groups (80 bits)
		for ( $i = 3; $i < 8; $i++ ) {
			$groups[ $i ] = '0000';
		}

		// Compress the IPv6 address
		return $this->compressIpv6( implode( ':', $groups ) );
	}

	/**
	 * Expand an IPv6 address to its full form.
	 *
	 * @param string $ip The IPv6 address.
	 *
	 * @return string|null The expanded IPv6 address, or null on failure.
	 *
	 * @since 1.0.0
	 */
	protected function expandIpv6( string $ip ): ?string
	{
		// Use filter_var to validate and inet_pton/inet_ntop to normalize
		$packed = @inet_pton( $ip );

		if ( false === $packed ) {
			return null;
		}

		// Convert to hex representation
		$hex = bin2hex( $packed );

		if ( 32 !== strlen( $hex ) ) {
			return null;
		}

		// Split into 8 groups of 4 characters
		$groups = str_split( $hex, 4 );

		return implode( ':', $groups );
	}

	/**
	 * Compress an IPv6 address by removing leading zeros and using :: notation.
	 *
	 * @param string $ip The full IPv6 address.
	 *
	 * @return string The compressed IPv6 address, or the original IP on failure.
	 *
	 * @since 1.0.0
	 */
	protected function compressIpv6( string $ip ): string
	{
		$packed = @inet_pton( $ip );

		if ( false === $packed ) {
			return $ip;
		}

		$unpacked = @inet_ntop( $packed );

		if ( false === $unpacked ) {
			return $ip;
		}

		return $unpacked;
	}
}
