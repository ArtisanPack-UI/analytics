<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Resolvers;

use ArtisanPackUI\Analytics\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Resolves site by reading a custom header.
 *
 * Reads the site ID or UUID from a custom HTTP header
 * (default: X-Site-ID) and resolves the corresponding site.
 *
 * Nothing authenticates that header, and since 1.5.0 the site this resolver
 * returns is the site every ArtisanPack UI package scopes its data by — not
 * only analytics. A caller who can set a header could otherwise name the site
 * a sibling package serves records from. So the header is only believed where
 * an operator has said it can be: `multi_tenant.trust_site_header` must be on,
 * and where `multi_tenant.trusted_site_header_ips` is set the request must
 * come from one of those addresses. Both are off by default, which means this
 * resolver answers nothing until it is deliberately switched on.
 *
 * @since   1.0.0
 *
 * @package ArtisanPackUI\Analytics\Resolvers
 */
class HeaderResolver extends AbstractSiteResolver
{
	/**
	 * The request attribute recording that an untrusted header was logged.
	 *
	 * @var string
	 */
	protected const WARNED_ATTRIBUTE = 'artisanpack.analytics.untrusted_site_header_logged';

	/**
	 * The header name to read the site ID from.
	 *
	 * @var string
	 */
	protected string $headerName;

	/**
	 * Create a new header resolver.
	 *
	 * @param string|null $headerName The header name to use.
	 */
	public function __construct( ?string $headerName = null )
	{
		$this->headerName = $headerName ?? config( 'artisanpack.analytics.multi_tenant.site_header', 'X-Site-ID' );
	}

	/**
	 * Resolve the current site from the request header.
	 *
	 * @param Request $request The incoming HTTP request.
	 *
	 * @return Site|null The resolved site, or null if not found.
	 *
	 * @since 1.0.0
	 */
	public function resolve( Request $request ): ?Site
	{
		$siteIdentifier = $request->header( $this->headerName );

		if ( null === $siteIdentifier || '' === $siteIdentifier ) {
			return null;
		}

		if ( ! $this->headerIsTrusted( $request ) ) {
			$this->warnAboutUntrustedHeader( $request );

			return null;
		}

		// Check if it's a UUID or numeric ID
		if ( $this->isUuid( $siteIdentifier ) ) {
			return Site::query()
				->where( 'uuid', $siteIdentifier )
				->where( 'is_active', true )
				->first();
		}

		if ( is_numeric( $siteIdentifier ) ) {
			return Site::query()
				->where( 'id', (int) $siteIdentifier )
				->where( 'is_active', true )
				->first();
		}

		return null;
	}

	/**
	 * Get the priority of this resolver.
	 *
	 * @return int
	 *
	 * @since 1.0.0
	 */
	public function priority(): int
	{
		return 50;
	}

	/**
	 * Determine whether this request is allowed to name its own site.
	 *
	 * The condition is configuration, not route placement: a resolver listed
	 * in the shared chain runs on every route in the application, so "it is
	 * only mounted on trusted routes" was never something this class could
	 * rely on.
	 *
	 * @param Request $request The incoming HTTP request.
	 *
	 * @return bool True when the header may be believed.
	 *
	 * @since 1.5.0
	 */
	protected function headerIsTrusted( Request $request ): bool
	{
		if ( ! config( 'artisanpack.analytics.multi_tenant.trust_site_header', false ) ) {
			return false;
		}

		$trustedIps = $this->trustedIps();

		if ( [] === $trustedIps ) {
			return true;
		}

		$peer = $this->peerAddress( $request );

		if ( null === $peer ) {
			return false;
		}

		return IpUtils::checkIp( $peer, $trustedIps );
	}

	/**
	 * Get the address the request arrived from.
	 *
	 * Deliberately `REMOTE_ADDR` rather than `Request::ip()`. `ip()` reports
	 * the client named in `X-Forwarded-For` wherever `TrustProxies` is
	 * configured — so on a gateway deployment, which is the one this allowlist
	 * exists for, it reports the end user rather than the gateway and the
	 * allowlist would never match. Worse, an application trusting all proxies
	 * would let a caller forge `X-Forwarded-For` and satisfy the allowlist with
	 * a header, which is the thing being defended against. The peer address is
	 * the one value in a request the caller cannot choose.
	 *
	 * @param Request $request The incoming HTTP request.
	 *
	 * @return string|null The peer address, or null when there is none.
	 *
	 * @since 1.5.0
	 */
	protected function peerAddress( Request $request ): ?string
	{
		$peer = $request->server( 'REMOTE_ADDR' );

		if ( ! is_string( $peer ) || '' === trim( $peer ) ) {
			return null;
		}

		return trim( $peer );
	}

	/**
	 * Get the addresses permitted to name a site by header.
	 *
	 * Accepts an array or a comma-separated string, so the list can come from
	 * an environment variable. Entries may be single addresses or CIDR ranges,
	 * IPv4 or IPv6.
	 *
	 * @return list<string> The configured addresses, empty when unrestricted.
	 *
	 * @since 1.5.0
	 */
	protected function trustedIps(): array
	{
		$configured = config( 'artisanpack.analytics.multi_tenant.trusted_site_header_ips', [] );

		if ( is_string( $configured ) ) {
			$configured = explode( ',', $configured );
		}

		if ( ! is_array( $configured ) ) {
			return [];
		}

		return array_values( array_filter(
			array_map(
				static fn ( mixed $ip ): string => is_string( $ip ) ? trim( $ip ) : '',
				$configured,
			),
			static fn ( string $ip ): bool => '' !== $ip,
		) );
	}

	/**
	 * Record that a site header arrived on a request not permitted to send one.
	 *
	 * Logged once per request rather than once per resolution, because
	 * resolvers are asked afresh for every scoped query and a request carrying
	 * the header would otherwise write a line per query.
	 *
	 * @param Request $request The incoming HTTP request.
	 *
	 * @return void
	 *
	 * @since 1.5.0
	 */
	protected function warnAboutUntrustedHeader( Request $request ): void
	{
		if ( true === $request->attributes->get( self::WARNED_ATTRIBUTE, false ) ) {
			return;
		}

		$request->attributes->set( self::WARNED_ATTRIBUTE, true );

		// The header value stays out of the message: it is caller-controlled,
		// and a value carrying newlines interpolated into a log line can forge
		// log entries. It is truncated for the same reason — a caller can send
		// kilobytes, and a rejected header should not be a way to fill a disk.
		Log::notice(
			'[Analytics] Ignoring a site header on a request not permitted to send one.'
				. ' Set "artisanpack.analytics.multi_tenant.trust_site_header" — and, where the header'
				. ' comes from a known gateway, "trusted_site_header_ips" — to allow it.',
			[
				'header' => $this->headerName,
				'value'  => Str::limit( (string) $request->header( $this->headerName ), 100 ),
				'peer'   => $this->peerAddress( $request ),
				'path'   => $request->path(),
			],
		);
	}

	/**
	 * Check if a string is a valid UUID.
	 *
	 * @param string $value The value to check.
	 *
	 * @return bool
	 *
	 * @since 1.0.0
	 */
	protected function isUuid( string $value ): bool
	{
		return (bool) preg_match(
			'/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
			$value,
		);
	}
}
