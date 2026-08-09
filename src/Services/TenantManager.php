<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Services;

use ArtisanPackUI\Analytics\Models\Site;
use ArtisanPackUI\Core\Contracts\SiteResolver;
use ArtisanPackUI\Core\MultiTenancy\ChainSiteResolver;
use ArtisanPackUI\Core\MultiTenancy\SiteContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Manages multi-tenant site resolution and context.
 *
 * Since 1.5.0 this is a thin analytics-shaped view over
 * {@see SiteContext}, the ecosystem's single
 * site context. It owns no resolver chain of its own: resolution happens once,
 * in core, from one configuration, so a request cannot resolve to site 2 for
 * analytics while resolving to site 1 for another package. What this class
 * still adds is the `Site` model — core's contract is keyed on the identifier
 * because it cannot depend on this package's model, so the lookup lives here.
 *
 * A site pinned here is pinned for every package, and a site pinned by another
 * package is visible here.
 *
 * For Laravel Octane compatibility, call flush() after each request
 * to prevent state leakage between requests.
 *
 * @since   1.0.0
 *
 * @package ArtisanPackUI\Analytics\Services
 */
class TenantManager
{
	/**
	 * The shared site context.
	 *
	 * @var SiteContext
	 */
	protected SiteContext $context;

	/**
	 * The most recently loaded site, cached against its identifier.
	 *
	 * The context is deliberately not memoised — it re-resolves on every call
	 * so a worker looping over sites gets the right answer each time. Without
	 * this cache, every global scope on every query would re-query the sites
	 * table for the same record. The identifier is stored alongside the model
	 * so the cache invalidates itself the moment the context's answer changes.
	 *
	 * @var Site|null
	 */
	protected ?Site $cachedSite = null;

	/**
	 * The identifier the cached site was loaded for.
	 *
	 * @var int|string|null
	 */
	protected int|string|null $cachedSiteId = null;

	/**
	 * Whether an explicit "no site" is in force.
	 *
	 * Pinning "no site" is an instruction, not an absence of one, so the
	 * configured default site must not quietly fill the gap — a report asked to
	 * run across every site would otherwise silently run against one.
	 *
	 * @var bool
	 */
	protected bool $defaultSuppressed = false;

	/**
	 * How many nested `withoutSite()` calls are currently open.
	 *
	 * @var int
	 */
	protected int $withoutSiteDepth = 0;

	/**
	 * Whether an unusable site identifier has already been reported.
	 *
	 * @var bool
	 */
	protected bool $warnedAboutSiteId = false;

	/**
	 * The default site identifier the usability check was last run for.
	 *
	 * @var int|null
	 */
	protected ?int $checkedDefaultSiteId = null;

	/**
	 * Whether the checked default site exists and is active.
	 *
	 * @var bool
	 */
	protected bool $defaultSiteUsable = false;

	/**
	 * Create a new tenant manager.
	 *
	 * @param SiteContext $context The shared site context.
	 */
	public function __construct( SiteContext $context )
	{
		$this->context = $context;
	}

	/**
	 * Resolve the current site.
	 *
	 * @param Request|null $request Unused. Retained so existing callers keep
	 *                              working; resolvers read the request from the
	 *                              container themselves.
	 *
	 * @return Site|null The resolved site, or null if not found.
	 *
	 * @deprecated 1.5.0 Call `current()`. Resolution no longer takes a request.
	 * @since 1.0.0
	 */
	public function resolve( ?Request $request = null ): ?Site
	{
		return $this->current();
	}

	/**
	 * Get the current site.
	 *
	 * A pinned site is served whether or not it is active. Every shipped
	 * resolver, and `default_site_id`, require `is_active` — but those are
	 * automatic answers, whereas a pin is an explicit instruction from calling
	 * code, and deactivating a site must not silently redirect an
	 * administrative or maintenance task to a different one. A caller that
	 * needs the distinction should check `is_active` on what it gets back.
	 *
	 * Soft-deleted sites are the exception, and are never returned: the
	 * query keeps Site's soft-delete scope deliberately.
	 *
	 * @return Site|null The current site, or null if not set.
	 *
	 * @since 1.0.0
	 */
	public function current(): ?Site
	{
		$siteId = $this->currentId();

		if ( null === $siteId ) {
			$this->cachedSite   = null;
			$this->cachedSiteId = null;

			return null;
		}

		if ( $this->cachedSiteId === $siteId && null !== $this->cachedSite ) {
			return $this->cachedSite;
		}

		// Deliberately not withoutGlobalScopes(): Site carries the soft-delete
		// scope, and a deleted site must not come back as the current one.
		$site = Site::query()
			->where( 'id', $siteId )
			->first();

		$this->cachedSite   = $site;
		$this->cachedSiteId = null !== $site ? $siteId : null;

		return $site;
	}

	/**
	 * Set the current site.
	 *
	 * Pins the site in the shared context, so every package that scopes by
	 * site sees it, not just this one.
	 *
	 * @param Site|null $site The site to set as current.
	 *
	 * @return static
	 *
	 * @since 1.0.0
	 */
	public function setCurrent( ?Site $site ): static
	{
		$this->context->setSiteId( $site?->id );

		$this->defaultSuppressed = null === $site;
		$this->cachedSite        = $site;
		$this->cachedSiteId      = $site?->id;

		return $this;
	}

	/**
	 * Get the current site ID.
	 *
	 * Answers from the shared context, falling back to the configured default
	 * site when nothing puts a site in context.
	 *
	 * @return int|null The current site ID, or null if not set.
	 *
	 * @since 1.0.0
	 */
	public function currentId(): ?int
	{
		$siteId = $this->context->currentSiteId();

		if ( null !== $siteId ) {
			// Deliberately stricter than is_numeric(), which accepts "12.5",
			// "1e3", " 12" and "-3" — each of which casts to an int that is
			// either a different site or no site at all. "12.5" becoming site
			// 12 would attribute this work to a real, wrong site.
			if ( is_int( $siteId ) || ( is_string( $siteId ) && 1 === preg_match( '/^\d+$/', $siteId ) ) ) {
				return (int) $siteId;
			}

			// Another package's resolver named a site this package cannot have
			// a record for — analytics site IDs are integers. Leave queries
			// unscoped, and do not reach for the default site: a site *is* in
			// context, so attributing this work to a different one would file
			// it under the wrong site rather than under none.
			$this->warnAboutUnusableSiteId( $siteId );

			return null;
		}

		if ( $this->defaultSuppressed || $this->withoutSiteDepth > 0 ) {
			return null;
		}

		// A null answer from a context that has something pinned means "no
		// site", deliberately — `withoutSite()` or `setSiteId( null )`, possibly
		// called by a sibling package that knows nothing about this one.
		// Substituting this package's default site there would scope work
		// another package asked to leave unscoped.
		if ( $this->context->isPinned() ) {
			return null;
		}

		return $this->defaultSiteId();
	}

	/**
	 * Check if a current site is set.
	 *
	 * @return bool
	 *
	 * @since 1.0.0
	 */
	public function hasCurrent(): bool
	{
		return null !== $this->currentId();
	}

	/**
	 * Execute a callback in the context of a specific site.
	 *
	 * The site context is restored after the callback completes, including
	 * when the callback throws, and the pinned site is visible to every
	 * package for the duration.
	 *
	 * @param int|Site|string $site     The site, or site identifier, to use as context.
	 * @param Closure         $callback The callback to execute.
	 *
	 * @return mixed The callback return value.
	 *
	 * @since 1.0.0
	 */
	public function forSite( Site|int|string $site, Closure $callback ): mixed
	{
		$siteId = $site instanceof Site ? $site->id : $site;

		$previousSite         = $this->cachedSite;
		$previousSiteId       = $this->cachedSiteId;
		$previouslySuppressed = $this->defaultSuppressed;

		$this->defaultSuppressed = false;

		if ( $site instanceof Site ) {
			$this->cachedSite   = $site;
			$this->cachedSiteId = $site->id;
		} else {
			$this->cachedSite   = null;
			$this->cachedSiteId = null;
		}

		try {
			return $this->context->forSite( $siteId, fn () => $callback( $site ) );
		} finally {
			$this->cachedSite        = $previousSite;
			$this->cachedSiteId      = $previousSiteId;
			$this->defaultSuppressed = $previouslySuppressed;
		}
	}

	/**
	 * Execute a callback without any site context.
	 *
	 * The site context is restored after the callback completes.
	 *
	 * @param Closure $callback The callback to execute.
	 *
	 * @return mixed The callback return value.
	 *
	 * @since 1.0.0
	 */
	public function withoutSite( Closure $callback ): mixed
	{
		$previousSite         = $this->cachedSite;
		$previousSiteId       = $this->cachedSiteId;
		$previouslySuppressed = $this->defaultSuppressed;

		$this->cachedSite   = null;
		$this->cachedSiteId = null;
		++$this->withoutSiteDepth;

		try {
			return $this->context->withoutSite( fn () => $callback() );
		} finally {
			--$this->withoutSiteDepth;
			$this->cachedSite   = $previousSite;
			$this->cachedSiteId = $previousSiteId;

			// Restored like the rest: a `setCurrent( null )` inside the
			// callback would otherwise outlive the scope and suppress the
			// default site for every later query in the request.
			$this->defaultSuppressed = $previouslySuppressed;
		}
	}

	/**
	 * Clear the current site context.
	 *
	 * Releases the pinned site for every package, handing resolution back to
	 * the configured resolvers.
	 *
	 * @return static
	 *
	 * @since 1.0.0
	 */
	public function forget(): static
	{
		$this->context->forget();

		$this->resetCaches();

		return $this;
	}

	/**
	 * Flush state for Laravel Octane compatibility.
	 *
	 * Call this method after each request to prevent state leakage
	 * between requests when running under Octane. This can be registered
	 * as a listener for Octane's RequestTerminated event.
	 *
	 * @return static
	 *
	 * @since 1.0.0
	 */
	public function flush(): static
	{
		$this->context->flush();

		$this->resetCaches();

		return $this;
	}

	/**
	 * Get the shared site context this manager delegates to.
	 *
	 * @return SiteContext The shared context.
	 *
	 * @since 1.5.0
	 */
	public function context(): SiteContext
	{
		return $this->context;
	}

	/**
	 * Get the resolvers backing site resolution.
	 *
	 * These come from `artisanpack.core.multi_tenant.resolvers` and are shared
	 * with every other package, so the list may contain resolvers this package
	 * knows nothing about.
	 *
	 * @return array<int, SiteResolver> The resolvers, in the order they are asked.
	 *
	 * @since 1.0.0
	 */
	public function getResolvers(): array
	{
		$resolver = $this->context->resolver();

		return $resolver instanceof ChainSiteResolver ? $resolver->resolvers() : [ $resolver ];
	}

	/**
	 * Log a site identifier this package cannot use, once per instance.
	 *
	 * Once, because this is reached from every scoped query: logging each time
	 * would bury the fault it is reporting.
	 *
	 * @param int|string $siteId The identifier the shared context returned.
	 *
	 * @return void
	 *
	 * @since 1.5.0
	 */
	protected function warnAboutUnusableSiteId( int|string $siteId ): void
	{
		if ( $this->warnedAboutSiteId ) {
			return;
		}

		$this->warnedAboutSiteId = true;

		// The identifier goes in the context array rather than the message: it
		// can originate in a request header, and a value carrying newlines
		// interpolated into a log line can forge log entries.
		Log::warning(
			__(
				'[Analytics] The shared site context resolved to an identifier that is not an analytics site ID,'
					. ' so analytics queries are not being scoped by site. Analytics sites are identified by'
					. ' integer IDs; check the resolvers in "artisanpack.core.multi_tenant.resolvers".',
			),
			[ 'site_id' => $siteId ],
		);
	}

	/**
	 * Clear the locally cached site and default-site lookups.
	 *
	 * @return void
	 *
	 * @since 1.5.0
	 */
	protected function resetCaches(): void
	{
		$this->cachedSite           = null;
		$this->cachedSiteId         = null;
		$this->defaultSuppressed    = false;
		$this->checkedDefaultSiteId = null;
		$this->defaultSiteUsable    = false;
	}

	/**
	 * Get the configured default site ID.
	 *
	 * Kept from before the move to the shared context: an application that
	 * names a default site expects its analytics to land there when nothing
	 * else identifies one. The site still has to exist and be active, so a
	 * stale identifier leaves the context empty rather than silently attaching
	 * every visit to a deleted site.
	 *
	 * @return int|null The default site ID, or null when none is usable.
	 *
	 * @since 1.5.0
	 */
	protected function defaultSiteId(): ?int
	{
		$defaultSiteId = config( 'artisanpack.analytics.multi_tenant.default_site_id' );

		if ( null === $defaultSiteId || '' === $defaultSiteId || ! is_numeric( $defaultSiteId ) ) {
			return null;
		}

		$defaultSiteId = (int) $defaultSiteId;

		// The existence check is memoised because this runs from every model's
		// global scope, on every query. Left unmemoised it would put a second
		// query in front of each one. `forget()` and `flush()` clear it, which
		// covers the case of the default site being created or deactivated
		// within a single process.
		if ( $this->checkedDefaultSiteId === $defaultSiteId ) {
			return $this->defaultSiteUsable ? $defaultSiteId : null;
		}

		$this->checkedDefaultSiteId = $defaultSiteId;
		$this->defaultSiteUsable    = Site::query()
			->where( 'id', $defaultSiteId )
			->where( 'is_active', true )
			->exists();

		return $this->defaultSiteUsable ? $defaultSiteId : null;
	}
}
