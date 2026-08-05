<?php

declare( strict_types=1 );

use ArtisanPackUI\Analytics\Contracts\AnalyticsProviderInterface;
use ArtisanPackUI\Analytics\Contracts\ProvidesTrackerScript;
use ArtisanPackUI\Analytics\Data\EventData;
use ArtisanPackUI\Analytics\Data\PageViewData;
use ArtisanPackUI\Analytics\Facades\Analytics;
use Illuminate\Support\Facades\Blade;

/**
 * A provider whose tracking half runs in the browser, declared through the
 * contract. Its server-side track methods are no-ops by design — which is
 * exactly why nothing rendering its snippet meant it contributed nothing at
 * all, in either direction, without failing.
 */
final class ContractedScriptProvider implements AnalyticsProviderInterface, ProvidesTrackerScript
{
	public function __construct( private string $name = 'contracted', private string $script = '<script>contracted()</script>' )
	{
	}

	public function trackPageView( PageViewData $data ): void
	{
	}

	public function trackEvent( EventData $data ): void
	{
	}

	public function isEnabled(): bool
	{
		return true;
	}

	public function getName(): string
	{
		return $this->name;
	}

	/** @return array<string, mixed> */
	public function getConfig(): array
	{
		return [];
	}

	public function trackerScript(): string
	{
		return $this->script;
	}
}

/**
 * A provider exposing trackerScript() without implementing the contract, as
 * artisanpack-ui/analytics-google 1.0 does — it shipped before the interface
 * existed.
 */
final class DuckTypedScriptProvider implements AnalyticsProviderInterface
{
	public function trackPageView( PageViewData $data ): void
	{
	}

	public function trackEvent( EventData $data ): void
	{
	}

	public function isEnabled(): bool
	{
		return true;
	}

	public function getName(): string
	{
		return 'duck-typed';
	}

	/** @return array<string, mixed> */
	public function getConfig(): array
	{
		return [];
	}

	public function trackerScript(): string
	{
		return '<script>duckTyped()</script>';
	}
}

/**
 * A provider that offers no snippet at all — the ordinary server-side case.
 */
final class ServerOnlyProvider implements AnalyticsProviderInterface
{
	public function trackPageView( PageViewData $data ): void
	{
	}

	public function trackEvent( EventData $data ): void
	{
	}

	public function isEnabled(): bool
	{
		return true;
	}

	public function getName(): string
	{
		return 'server-only';
	}

	/** @return array<string, mixed> */
	public function getConfig(): array
	{
		return [];
	}
}

/**
 * Register providers and mark them active.
 *
 * @param array<string, AnalyticsProviderInterface> $providers Keyed by name.
 */
function registerActiveProviders( array $providers ): void
{
	$analytics = app( ArtisanPackUI\Analytics\Analytics::class );

	foreach ( $providers as $name => $provider ) {
		$analytics->extend( $name, fn (): AnalyticsProviderInterface => $provider );
	}

	config()->set( 'artisanpack.analytics.active_providers', array_keys( $providers ) );
}

test( 'a provider implementing the contract has its snippet collected', function (): void {
	registerActiveProviders( [ 'contracted' => new ContractedScriptProvider() ] );

	expect( Analytics::trackerScripts() )->toBe( [ '<script>contracted()</script>' ] );
} );

test( 'a provider exposing trackerScript without the contract is still honoured', function (): void {
	// analytics-google 1.0 predates the interface. Requiring a matching release
	// of every sibling package before any snippet renders would defeat the
	// point of fixing this.
	registerActiveProviders( [ 'duck-typed' => new DuckTypedScriptProvider() ] );

	expect( Analytics::trackerScripts() )->toBe( [ '<script>duckTyped()</script>' ] );
} );

test( 'providers without a snippet contribute nothing and do not error', function (): void {
	registerActiveProviders( [ 'server-only' => new ServerOnlyProvider() ] );

	expect( Analytics::trackerScripts() )->toBe( [] );
} );

test( 'snippets are collected from every active provider in order', function (): void {
	registerActiveProviders( [
		'contracted'  => new ContractedScriptProvider(),
		'server-only' => new ServerOnlyProvider(),
		'duck-typed'  => new DuckTypedScriptProvider(),
	] );

	expect( Analytics::trackerScripts() )->toBe( [
		'<script>contracted()</script>',
		'<script>duckTyped()</script>',
	] );
} );

test( 'an unconfigured provider returning an empty snippet is skipped', function (): void {
	registerActiveProviders( [
		'blank'      => new ContractedScriptProvider( 'blank', '   ' ),
		'contracted' => new ContractedScriptProvider(),
	] );

	expect( Analytics::trackerScripts() )->toBe( [ '<script>contracted()</script>' ] );
} );

test( 'a provider that is registered but not active contributes nothing', function (): void {
	$analytics = app( ArtisanPackUI\Analytics\Analytics::class );
	$analytics->extend( 'contracted', fn (): AnalyticsProviderInterface => new ContractedScriptProvider() );

	config()->set( 'artisanpack.analytics.active_providers', [ 'local' ] );

	expect( Analytics::trackerScripts() )->not->toContain( '<script>contracted()</script>' );
} );

test( 'a throwing provider is skipped rather than taking down the page', function (): void {
	$exploding = new class implements AnalyticsProviderInterface, ProvidesTrackerScript {
		public function trackPageView( PageViewData $data ): void
		{
		}

		public function trackEvent( EventData $data ): void
		{
		}

		public function isEnabled(): bool
		{
			return true;
		}

		public function getName(): string
		{
			return 'exploding';
		}

		/** @return array<string, mixed> */
		public function getConfig(): array
		{
			return [];
		}

		public function trackerScript(): string
		{
			throw new RuntimeException( 'provider blew up' );
		}
	};

	registerActiveProviders( [
		'exploding'  => $exploding,
		'contracted' => new ContractedScriptProvider(),
	] );

	expect( Analytics::trackerScripts() )->toBe( [ '<script>contracted()</script>' ] );
} );

test( 'the @analyticsScripts directive emits provider snippets alongside the package tracker', function (): void {
	// The regression this fixes: the directive rendered only the package's own
	// tracker and never asked providers for theirs, so the seam was dangling at
	// both ends with nothing to indicate it.
	registerActiveProviders( [ 'contracted' => new ContractedScriptProvider() ] );

	$rendered = Blade::render( '@analyticsScripts' );

	expect( $rendered )
		->toContain( '<script>contracted()</script>' )
		->toContain( 'analytics' );
} );

test( 'provider snippets are not emitted when analytics is disabled', function (): void {
	registerActiveProviders( [ 'contracted' => new ContractedScriptProvider() ] );

	config()->set( 'artisanpack.analytics.enabled', false );

	expect( Blade::render( '@analyticsScripts' ) )->not->toContain( '<script>contracted()</script>' );
} );
