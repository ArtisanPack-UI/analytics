<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics;

use ArtisanPackUI\Analytics\Ai\Agents\AnomalyExplanationAgent;
use ArtisanPackUI\Analytics\Ai\Agents\DigestEmailAgent;
use ArtisanPackUI\Analytics\Ai\Agents\InsightSummaryAgent;
use ArtisanPackUI\Analytics\Ai\Agents\SegmentInsightAgent;
use ArtisanPackUI\Analytics\Auth\ApiKeyGuard;
use ArtisanPackUI\Analytics\Console\Commands\BotsListCommand;
use ArtisanPackUI\Analytics\Console\Commands\CacheClearCommand;
use ArtisanPackUI\Analytics\Console\Commands\CleanupCommand;
use ArtisanPackUI\Analytics\Console\Commands\DispatchDigestsCommand;
use ArtisanPackUI\Analytics\Console\Commands\GoalsListCommand;
use ArtisanPackUI\Analytics\Console\Commands\InstallCommand;
use ArtisanPackUI\Analytics\Console\Commands\InstallFrontendCommand;
use ArtisanPackUI\Analytics\Console\Commands\RealtimeCommand;
use ArtisanPackUI\Analytics\Console\Commands\SiteApiKeyCommand;
use ArtisanPackUI\Analytics\Console\Commands\SiteCreateCommand;
use ArtisanPackUI\Analytics\Console\Commands\SitesListCommand;
use ArtisanPackUI\Analytics\Console\Commands\StatsCommand;
use ArtisanPackUI\Analytics\Console\Commands\WhitelistCommand;
use ArtisanPackUI\Analytics\Contracts\AnalyticsServiceInterface;
use ArtisanPackUI\Analytics\Contracts\SiteResolverInterface;
use ArtisanPackUI\Analytics\Http\Middleware\AnalyticsThrottle;
use ArtisanPackUI\Analytics\Http\Middleware\AuthenticateWithApiKey;
use ArtisanPackUI\Analytics\Http\Middleware\PrivacyFilter;
use ArtisanPackUI\Analytics\Http\Middleware\ResolveSite;
use ArtisanPackUI\Analytics\Http\Middleware\TenantResolver;
use ArtisanPackUI\Analytics\Jobs\AnalyzeBotTraffic;
use ArtisanPackUI\Analytics\Resolvers\LegacySiteResolverAdapter;
use ArtisanPackUI\Analytics\Services\AnalyticsQuery;
use ArtisanPackUI\Analytics\Services\BotDetector;
use ArtisanPackUI\Analytics\Services\ConsentService;
use ArtisanPackUI\Analytics\Services\CrossTenantReporting;
use ArtisanPackUI\Analytics\Services\DataDeletionService;
use ArtisanPackUI\Analytics\Services\DataExportService;
use ArtisanPackUI\Analytics\Services\DeviceDetector;
use ArtisanPackUI\Analytics\Services\EventProcessor;
use ArtisanPackUI\Analytics\Services\FunnelAnalyzer;
use ArtisanPackUI\Analytics\Services\GoalMatcher;
use ArtisanPackUI\Analytics\Services\GoalService;
use ArtisanPackUI\Analytics\Services\IpAnonymizer;
use ArtisanPackUI\Analytics\Services\PrivacyIntegration;
use ArtisanPackUI\Analytics\Services\SiteSettingsService;
use ArtisanPackUI\Analytics\Services\TenantManager;
use ArtisanPackUI\Core\Contracts\SiteResolver;
use ArtisanPackUI\Core\MultiTenancy\SiteContext;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider for the Analytics package.
 *
 * Bootstraps the Analytics package by registering configuration, views,
 * database migrations, routes, middleware, and commands. Configuration is
 * merged into the main artisanpack.php config file following the ArtisanPack
 * UI package conventions.
 *
 * @since   1.0.0
 *
 * @package ArtisanPackUI\Analytics
 */
class AnalyticsServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * This method merges the package's local configuration and binds
     * core services to the container.
     *
     * @since 1.0.0
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/analytics.php',
            'artisanpack-analytics-temp',
        );

        // Merge configuration early so it's available for service registration
        $this->mergeConfiguration();

        // Register the main Analytics manager
        $this->app->singleton( Analytics::class, function ( $app ) {
            return new Analytics( $app );
        } );

        $this->app->singleton( 'analytics', function ( $app ) {
            return $app->make( Analytics::class );
        } );

        // Bind interface to implementation
        $this->app->bind( AnalyticsServiceInterface::class, Analytics::class );

        // Register the AnalyticsQuery service
        $this->app->bind( AnalyticsQuery::class, function ( $app ) {
            return new AnalyticsQuery(
                $app->make( Analytics::class ),
            );
        } );

        // Register the GoalMatcher service
        $this->app->bind( GoalMatcher::class, function ( $app ) {
            return new GoalMatcher;
        } );

        // Register the GoalService
        $this->app->bind( GoalService::class, function ( $app ) {
            return new GoalService(
                $app->make( GoalMatcher::class ),
            );
        } );

        // Register the EventProcessor service
        $this->app->bind( EventProcessor::class, function ( $app ) {
            return new EventProcessor(
                $app->make( GoalMatcher::class ),
            );
        } );

        // Register the FunnelAnalyzer service
        $this->app->bind( FunnelAnalyzer::class, function ( $app ) {
            return new FunnelAnalyzer;
        } );

        // Register the IpAnonymizer service
        $this->app->singleton( IpAnonymizer::class, function () {
            return new IpAnonymizer;
        } );

        // Register the ConsentService
        $this->app->singleton( ConsentService::class, function ( $app ) {
            return new ConsentService(
                $app->make( IpAnonymizer::class ),
            );
        } );

        // Register the DataExportService
        $this->app->singleton( DataExportService::class, function () {
            return new DataExportService;
        } );

        // Register the DataDeletionService
        $this->app->singleton( DataDeletionService::class, function () {
            return new DataDeletionService;
        } );

        // Register the PrivacyIntegration service
        $this->app->singleton( PrivacyIntegration::class, function ( $app ) {
            return new PrivacyIntegration(
                $app->make( DataExportService::class ),
                $app->make( DataDeletionService::class ),
                $app->make( ConsentService::class ),
            );
        } );

        // Register TenantManager over the ecosystem's shared site context.
        //
        // Scoped rather than singleton, to match the context it wraps: Laravel
        // forgets scoped instances between Octane requests and between queue
        // jobs, so a site pinned by one job cannot scope the next job's data,
        // and neither can the Site model this manager caches.
        $this->app->scoped( TenantManager::class, function ( $app ) {
            return new TenantManager(
                $app->make( SiteContext::class ),
            );
        } );

        // Register SiteSettingsService.
        //
        // Scoped rather than singleton, to match the TenantManager it holds. A
        // singleton captures the first job's scoped TenantManager and keeps
        // consulting it after the container has forgotten it, so a site pinned
        // by one queue job decides the settings the next job reads.
        $this->app->scoped( SiteSettingsService::class, function ( $app ) {
            return new SiteSettingsService(
                $app->make( TenantManager::class ),
            );
        } );

        // Register CrossTenantReporting
        $this->app->singleton( CrossTenantReporting::class, function () {
            return new CrossTenantReporting;
        } );

        // Register the BotDetector service
        $this->app->singleton( BotDetector::class, function ( $app ) {
            return new BotDetector(
                $app->make( DeviceDetector::class ),
            );
        } );
    }

    /**
     * Bootstrap any application services.
     *
     * This method publishes assets, registers middleware, routes,
     * and commands.
     *
     * @since 1.0.0
     */
    public function boot(): void
    {
        Support\HookAliases::register();

        $this->mergeConfiguration();

        // The bridge runs here rather than in register() because it reads the
        // merged configuration. A provider that resolves SiteContext during its
        // own boot() before this one boots therefore gets the un-bridged chain,
        // and because the binding is scoped it keeps it for the rest of the
        // request. Nothing in this package does that, but an application whose
        // provider does should list this one earlier in bootstrap/providers.php.
        $this->bridgeLegacyMultiTenantConfig();
        $this->publishConfiguration();
        $this->publishMigrations();
        $this->publishViews();
        $this->publishTracker();
        $this->publishReactComponents();
        $this->publishVueComponents();
        $this->registerMiddleware();
        $this->registerRoutes();
        $this->registerCommands();
        $this->registerScheduledJobs();
        $this->registerBuiltInProviders();
        $this->registerLivewireComponents();
        $this->registerBladeDirectives();
        $this->registerPrivacyHooks();
        $this->registerAuthGuard();
        $this->registerInertiaSharedData();
        $this->registerAiGate();
        $this->registerAiLivewireComponents();
    }

    /**
     * Declare AI features owned by this package.
     *
     * Auto-discovered by artisanpack-ui/ai when the ai package is installed.
     * Each entry maps a fully-qualified feature key to the agent class that
     * fulfills it, along with a human-readable label and description for the
     * admin UI.
     *
     * @since 1.3.0
     *
     * @return array<string, array<string, mixed>>
     */
    public function aiFeatures(): array
    {
        return [
            'analytics.insight_summary' => [
                'agent'       => InsightSummaryAgent::class,
                'package'     => 'artisanpack-ui/analytics',
                'label'       => __( 'Summarize insights' ),
                'description' => __( 'Plain-language narrative summary of a date range with highlights and concerns.' ),
            ],
            'analytics.explain_anomaly' => [
                'agent'       => AnomalyExplanationAgent::class,
                'package'     => 'artisanpack-ui/analytics',
                'label'       => __( 'Explain anomaly' ),
                'description' => __( 'Rank likely causes of a detected traffic anomaly with evidence and next steps.' ),
            ],
            'analytics.segment_insight' => [
                'agent'       => SegmentInsightAgent::class,
                'package'     => 'artisanpack-ui/analytics',
                'label'       => __( 'Segment insight' ),
                'description' => __( 'Surface patterns in a visitor segment relative to a baseline.' ),
            ],
            'analytics.digest_email' => [
                'agent'       => DigestEmailAgent::class,
                'package'     => 'artisanpack-ui/analytics',
                'label'       => __( 'Digest email' ),
                'description' => __( 'Compose the AI narrative body of the opt-in weekly/monthly analytics digest.' ),
            ],
        ];
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<int, string>
     *
     * @since 1.0.0
     */
    public function provides(): array
    {
        return [
            Analytics::class,
            'analytics',
            AnalyticsServiceInterface::class,
            AnalyticsQuery::class,
            GoalMatcher::class,
            GoalService::class,
            EventProcessor::class,
            FunnelAnalyzer::class,
            IpAnonymizer::class,
            ConsentService::class,
            DataExportService::class,
            DataDeletionService::class,
            PrivacyIntegration::class,
            TenantManager::class,
            SiteSettingsService::class,
            CrossTenantReporting::class,
        ];
    }

    /**
     * Carry a pre-1.5 multi-tenant configuration onto the shared one.
     *
     * Site resolution moved to `artisanpack-ui/core` in 1.5.0 so that every
     * package in an installation resolves one site from one configuration.
     * Applications configured before that switched tenancy on with
     * `artisanpack.analytics.multi_tenant.enabled` and listed their resolvers
     * under the same block, and core's own switch defaults to off — so without
     * this bridge an upgrade would silently stop scoping analytics by site,
     * pooling every site's visits into one dashboard with nothing in the logs
     * to explain it.
     *
     * The legacy resolvers go in front of whatever the shared list already
     * holds, which preserves the order they resolved in before: an API key or
     * `X-Site-ID` header identifies the site a tracking request is *for*,
     * which is not always the site the request is *served from*.
     *
     * What the bridge deliberately does not carry over is trust. It moves a
     * list that used to steer analytics onto one that steers every package, so
     * `HeaderResolver` arriving here would otherwise let an unauthenticated
     * header choose the site a sibling package serves — ahead of
     * `DomainResolver`, so ahead of the host as well. That resolver gates
     * itself on `multi_tenant.trust_site_header`, which is off by default, so
     * an upgrade that prepends it prepends something inert until an operator
     * decides otherwise.
     *
     * Applications that have migrated set the core keys and either switch the
     * analytics flag off or empty its resolver list; nothing is bridged then.
     * A shared list that is already populated counts as migrated too: prepending
     * onto it would silently reorder resolution for every package in the
     * installation, putting analytics' shipped defaults in front of resolvers
     * the application chose deliberately.
     *
     * @return void
     *
     * @since 1.5.0
     */
    protected function bridgeLegacyMultiTenantConfig(): void
    {
        if ( ! config( 'artisanpack.analytics.multi_tenant.enabled', false ) ) {
            return;
        }

        $config = $this->app->make( 'config' );

        if ( ! $config->get( 'artisanpack.core.multi_tenant.enabled', false ) ) {
            $config->set( 'artisanpack.core.multi_tenant.enabled', true );
        }

        $legacyResolvers = $config->get( 'artisanpack.analytics.multi_tenant.resolvers', [] );
        $sharedResolvers = $config->get( 'artisanpack.core.multi_tenant.resolvers', [] );

        // A shared list that is not a list is a configuration fault, and core
        // reports it as one when the resolver is built. Merging into it here
        // would turn that report into a confusing one about analytics.
        if ( ! is_array( $legacyResolvers ) || [] === $legacyResolvers || ! is_array( $sharedResolvers ) ) {
            return;
        }

        // A populated shared list is positive evidence the application migrated,
        // so leave the order it chose alone and say why nothing was carried over.
        if ( [] !== array_values( $sharedResolvers ) ) {
            Log::warning( __(
                '[Analytics] Ignoring the deprecated "artisanpack.analytics.multi_tenant.resolvers" list because'
                    . ' "artisanpack.core.multi_tenant.resolvers" is already configured. Prepending onto it would'
                    . ' reorder site resolution for every ArtisanPack UI package. Move any resolver you still need'
                    . ' to the core list and remove the deprecated one.',
            ) );

            return;
        }

        $merged = $this->adaptLegacySiteResolvers( array_values( $legacyResolvers ) );

        $config->set( 'artisanpack.core.multi_tenant.resolvers', array_values( array_unique( $merged ) ) );

        Log::notice( __(
            '[Analytics] Bridging the deprecated "artisanpack.analytics.multi_tenant" settings onto'
                . ' "artisanpack.core.multi_tenant", which every ArtisanPack UI package now resolves sites from.'
                . ' Move your resolvers to the core key and switch tenancy on there; support for the analytics'
                . ' key will be removed in 2.0.',
        ) );
    }

    /**
     * Make sure every bridged resolver can actually go into the shared chain.
     *
     * Core builds the chain with `$container->make( $class )` and rejects
     * anything that is not a `SiteResolver`. A resolver written against the 1.4
     * shape — `resolve()` and `priority()`, nothing more — is not one, so an
     * application that upgrades with a custom resolver in its list would have
     * every scoped query throw. Binding the adapter against the legacy class
     * name means core makes an adapter where it asked for the legacy class, and
     * the resolver keeps resolving through its existing `resolve()`.
     *
     * @param array<int, mixed> $resolvers The configured legacy resolver list.
     *
     * @return array<int, mixed> The same list, with legacy classes now resolvable.
     *
     * @since 1.5.0
     */
    protected function adaptLegacySiteResolvers( array $resolvers ): array
    {
        foreach ( $resolvers as $resolverClass ) {
            if ( ! is_string( $resolverClass ) || ! class_exists( $resolverClass ) ) {
                continue;
            }

            if ( is_a( $resolverClass, SiteResolver::class, true ) ) {
                continue;
            }

            if ( ! is_a( $resolverClass, SiteResolverInterface::class, true )
                && ! method_exists( $resolverClass, 'resolve' ) ) {
                continue;
            }

            $this->app->bind(
                $resolverClass,
                fn (): LegacySiteResolverAdapter => new LegacySiteResolverAdapter( $resolverClass ),
            );

            Log::notice( __(
                '[Analytics] Adapting the deprecated site resolver ":resolver" onto the shared site-resolution'
                    . ' contract. Implement ArtisanPackUI\\Core\\Contracts\\SiteResolver, or extend'
                    . ' ArtisanPackUI\\Analytics\\Resolvers\\AbstractSiteResolver; this adapter goes away in 2.0.',
                [ 'resolver' => $resolverClass ],
            ) );
        }

        return $resolvers;
    }

    /**
     * Register the default `analytics.ai.use` authorization gate.
     *
     * The AI API endpoints gate on this ability so that paid quota isn't spent
     * by every authenticated user. Ships with a permissive default (any
     * authenticated user can use AI features) so upgrades are non-breaking;
     * installers should override this gate to enforce a stricter policy.
     *
     * @since 1.3.0
     *
     * @return void
     */
    protected function registerAiGate(): void
    {
        $gate = \Illuminate\Support\Facades\Gate::getFacadeRoot();

        if ( method_exists( $gate, 'has' ) && $gate->has( 'analytics.ai.use' ) ) {
            return;
        }

        \Illuminate\Support\Facades\Gate::define(
            'analytics.ai.use',
            static function ( $user = null ): bool {
                return null !== $user;
            },
        );
    }

    /**
     * Register the AI Livewire components (since 1.3.0).
     *
     * The dashboard components are wired via `addNamespace` above, but each
     * AI trigger is bound by its explicit alias so consumers get a stable
     * `artisanpack-analytics::ai-*` handle even when the Livewire 3 code
     * path is used.
     *
     * @since 1.3.0
     *
     * @return void
     */
    protected function registerAiLivewireComponents(): void
    {
        if ( ! class_exists( \Livewire\Livewire::class ) ) {
            return;
        }

        // Use dot-notation for the sub-namespace segment so Livewire's finder
        // can round-trip the alias back to the `Ai\*` class. Hyphens between
        // words in the class name are fine — dots separate class segments.
        \Livewire\Livewire::component( 'artisanpack-analytics::ai.insight-summary', Http\Livewire\Ai\InsightSummary::class );
        \Livewire\Livewire::component( 'artisanpack-analytics::ai.anomaly-explanation', Http\Livewire\Ai\AnomalyExplanation::class );
        \Livewire\Livewire::component( 'artisanpack-analytics::ai.segment-insight', Http\Livewire\Ai\SegmentInsight::class );
        \Livewire\Livewire::component( 'artisanpack-analytics::ai.digest-subscription', Http\Livewire\Ai\DigestSubscription::class );
    }

    /**
     * Merges the package's default configuration with the user's customizations.
     *
     * Supports both standalone usage (config at 'analytics.*') and integration
     * with the core package (config at 'artisanpack.analytics.*'). The merge
     * priority is: artisanpack.analytics > analytics > package defaults.
     *
     * @since 1.0.0
     */
    protected function mergeConfiguration(): void
    {
        $packageDefaults = config( 'artisanpack-analytics-temp', [] );

        // Support standalone config at 'analytics.*' (without core package)
        $standaloneConfig = config( 'analytics', [] );

        // Support core package integration at 'artisanpack.analytics.*'
        $artisanpackConfig = config( 'artisanpack.analytics', [] );

        // Merge with priority: artisanpack.analytics > analytics > package defaults
        $mergedConfig = array_replace_recursive(
            $packageDefaults,
            $standaloneConfig,
            $artisanpackConfig,
        );

        config( ['artisanpack.analytics' => $mergedConfig] );
    }

    /**
     * Publish the configuration file.
     *
     * Provides multiple publish tags:
     * - 'analytics-config': Publishes to config/artisanpack/analytics.php (for core package integration)
     * - 'analytics-config-standalone': Publishes to config/analytics.php (for standalone usage)
     * - 'artisanpack-package-config': Used by core package scaffold command
     *
     * @since 1.0.0
     */
    protected function publishConfiguration(): void
    {
        if ( $this->app->runningInConsole() ) {
            // For core package integration (config/artisanpack/analytics.php)
            $this->publishes( [
                __DIR__ . '/../config/analytics.php' => config_path( 'artisanpack/analytics.php' ),
            ], 'analytics-config' );

            // For standalone usage (config/analytics.php)
            $this->publishes( [
                __DIR__ . '/../config/analytics.php' => config_path( 'analytics.php' ),
            ], 'analytics-config-standalone' );

            // For core package scaffold command
            $this->publishes( [
                __DIR__ . '/../config/analytics.php' => config_path( 'artisanpack/analytics.php' ),
            ], 'artisanpack-package-config' );
        }
    }

    /**
     * Publish the database migrations.
     *
     * @since 1.0.0
     */
    protected function publishMigrations(): void
    {
        $this->loadMigrationsFrom( __DIR__ . '/../database/migrations' );

        if ( $this->app->runningInConsole() ) {
            $this->publishes( [
                __DIR__ . '/../database/migrations' => database_path( 'migrations' ),
            ], 'analytics-migrations' );
        }
    }

    /**
     * Publish the views.
     *
     * @since 1.0.0
     */
    protected function publishViews(): void
    {
        $this->loadViewsFrom( __DIR__ . '/../resources/views', 'artisanpack-analytics' );

        if ( $this->app->runningInConsole() ) {
            $this->publishes( [
                __DIR__ . '/../resources/views' => resource_path( 'views/vendor/artisanpack-analytics' ),
            ], 'analytics-views' );
        }
    }

    /**
     * Publish the JavaScript tracker.
     *
     * @since 1.0.0
     */
    protected function publishTracker(): void
    {
        if ( $this->app->runningInConsole() ) {
            $this->publishes( [
                __DIR__ . '/../resources/js' => public_path( 'vendor/analytics/js' ),
            ], 'analytics-tracker' );
        }
    }

    /**
     * Publish the React dashboard components.
     *
     * Publishes React TypeScript components to the consuming application's
     * resources directory for use with Inertia.js.
     *
     * @since 1.1.0
     */
    protected function publishReactComponents(): void
    {
        if ( $this->app->runningInConsole() ) {
            $this->publishes( [
                __DIR__ . '/../resources/js/react' => resource_path( 'js/vendor/artisanpack-analytics/react' ),
                __DIR__ . '/../resources/js/types' => resource_path( 'js/vendor/artisanpack-analytics/types' ),
            ], 'analytics-react' );

            // Focused publish tag for just the AI trigger components + hooks (since 1.3.0).
            $this->publishes( [
                __DIR__ . '/../resources/js/react/components/ai'       => resource_path( 'js/vendor/artisanpack-analytics/react/components/ai' ),
                __DIR__ . '/../resources/js/react/hooks/useAiAgent.ts' => resource_path( 'js/vendor/artisanpack-analytics/react/hooks/useAiAgent.ts' ),
                __DIR__ . '/../resources/js/react/hooks/useApi.ts'     => resource_path( 'js/vendor/artisanpack-analytics/react/hooks/useApi.ts' ),
            ], 'analytics-react-ai' );
        }
    }

    /**
     * Publish the Vue dashboard components.
     *
     * Publishes Vue SFC components to the consuming application's
     * resources directory for use with Inertia.js.
     *
     * @since 1.1.0
     */
    protected function publishVueComponents(): void
    {
        if ( $this->app->runningInConsole() ) {
            $this->publishes( [
                __DIR__ . '/../resources/js/vue'   => resource_path( 'js/vendor/artisanpack-analytics/vue' ),
                __DIR__ . '/../resources/js/types' => resource_path( 'js/vendor/artisanpack-analytics/types' ),
            ], 'analytics-vue' );

            // Focused publish tag for just the AI trigger components + composables (since 1.3.0).
            $this->publishes( [
                __DIR__ . '/../resources/js/vue/components/ai'             => resource_path( 'js/vendor/artisanpack-analytics/vue/components/ai' ),
                __DIR__ . '/../resources/js/vue/composables/useAiAgent.ts' => resource_path( 'js/vendor/artisanpack-analytics/vue/composables/useAiAgent.ts' ),
                __DIR__ . '/../resources/js/vue/composables/useApi.ts'     => resource_path( 'js/vendor/artisanpack-analytics/vue/composables/useApi.ts' ),
            ], 'analytics-vue-ai' );
        }
    }

    /**
     * Register the middleware.
     *
     * @since 1.0.0
     */
    protected function registerMiddleware(): void
    {
        /** @var Router $router */
        $router = $this->app->make( Router::class );

        // Register individual middleware
        $router->aliasMiddleware( 'analytics.throttle', AnalyticsThrottle::class );
        $router->aliasMiddleware( 'analytics.privacy', PrivacyFilter::class );
        $router->aliasMiddleware( 'analytics.tenant', TenantResolver::class );
        $router->aliasMiddleware( 'analytics.site', ResolveSite::class );
        $router->aliasMiddleware( 'analytics.api-key', AuthenticateWithApiKey::class );

        // Register middleware group
        $router->middlewareGroup( 'analytics', [
            AnalyticsThrottle::class,
            PrivacyFilter::class,
            TenantResolver::class,
        ] );

        // Register middleware group for API key authenticated routes
        $router->middlewareGroup( 'analytics-api', [
            AuthenticateWithApiKey::class,
            AnalyticsThrottle::class,
        ] );
    }

    /**
     * Register the routes.
     *
     * Registers API routes for data collection and querying, web routes
     * for the tracker script, and dashboard routes based on the configured
     * dashboard_driver ('livewire' or 'inertia').
     *
     * @since 1.0.0
     * @since 1.1.0 Added Inertia dashboard route support via dashboard_driver config.
     */
    protected function registerRoutes(): void
    {
        $routePrefix     = config( 'artisanpack.analytics.route_prefix', 'api/analytics' );
        $routeMiddleware = config( 'artisanpack.analytics.route_middleware', ['api', 'analytics'] );

        // Register API routes
        Route::prefix( $routePrefix )
            ->middleware( $routeMiddleware )
            ->group( __DIR__ . '/../routes/api.php' );

        // Register web routes for the tracker script. These carry no
        // middleware and no dashboard gate: the script is a public static
        // asset every visitor's browser fetches, so putting it behind the
        // dashboard's `auth` middleware redirected anonymous visitors to the
        // login page and left them untracked, and gating it on
        // `dashboard_route` meant an application that switched the dashboard
        // off lost tracking with it.
        Route::group( [], __DIR__ . '/../routes/web.php' );

        // Register dashboard routes based on the configured driver
        $this->registerDashboardRoutes();
    }

    /**
     * Register dashboard routes based on the configured driver.
     *
     * When 'livewire', Livewire components handle the dashboard rendering.
     * When 'inertia', dedicated routes return Inertia::render() responses
     * with analytics data as page props for React/Vue dashboards.
     *
     * @since 1.1.0
     */
    protected function registerDashboardRoutes(): void
    {
        $driver         = config( 'artisanpack.analytics.dashboard_driver', 'livewire' );
        $dashboardRoute = config( 'artisanpack.analytics.dashboard_route' );

        if ( ! $dashboardRoute || 'inertia' !== $driver ) {
            return;
        }

        // Only register Inertia routes if inertia-laravel is installed
        if ( ! class_exists( \Inertia\Inertia::class ) ) {
            return;
        }

        Route::middleware( config( 'artisanpack.analytics.dashboard_middleware', ['web', 'auth'] ) )
            ->group( __DIR__ . '/../routes/inertia.php' );
    }

    /**
     * Register the console commands.
     *
     * @since 1.0.0
     */
    protected function registerCommands(): void
    {
        if ( $this->app->runningInConsole() ) {
            $this->commands( [
                InstallCommand::class,
                InstallFrontendCommand::class,
                StatsCommand::class,
                CleanupCommand::class,
                CacheClearCommand::class,
                SitesListCommand::class,
                SiteCreateCommand::class,
                SiteApiKeyCommand::class,
                GoalsListCommand::class,
                RealtimeCommand::class,
                BotsListCommand::class,
                WhitelistCommand::class,
                DispatchDigestsCommand::class,
            ] );
        }
    }

    /**
     * Register the scheduled bot analysis job.
     *
     * Schedules the AnalyzeBotTraffic job on a configurable interval. The job
     * is only scheduled when bot detection is enabled. The configured interval
     * is snapped to a divisor of 60 so the job fires at evenly spaced minutes
     * within every hour rather than producing an uneven gap at the hour mark.
     *
     * @since 1.2.0
     */
    protected function registerScheduledJobs(): void
    {
        $this->app->booted( function (): void {
            $schedule = $this->app->make( Schedule::class );

            if ( (bool) config( 'artisanpack.analytics.bot_detection.enabled', true ) ) {
                $interval = $this->botAnalysisIntervalMinutes();

                $schedule->job( new AnalyzeBotTraffic() )
                    ->cron( sprintf( '*/%d * * * *', $interval ) )
                    ->name( 'analytics-analyze-bot-traffic' )
                    ->withoutOverlapping();
            }

            // Digest email dispatcher: iterates opted-in preferences and
            // enqueues SendDigestEmailJob for any user whose cadence window
            // is due. Host apps override the schedule (or opt out entirely)
            // via `artisanpack.analytics.digest.schedule`.
            $digestSchedule = (string) config( 'artisanpack.analytics.digest.schedule', 'hourly' );

            if ( 'off' === $digestSchedule ) {
                return;
            }

            $entry = $schedule->command( 'analytics:dispatch-digests' )
                ->name( 'analytics-dispatch-digests' )
                ->withoutOverlapping();

            if ( method_exists( $entry, $digestSchedule ) ) {
                $entry->{$digestSchedule}();
            } else {
                $entry->cron( $digestSchedule );
            }
        } );
    }

    /**
     * Resolve the bot analysis interval, snapped to a divisor of 60 minutes.
     *
     * @return int A minute interval that divides evenly into an hour (1-30).
     *
     * @since 1.2.0
     */
    protected function botAnalysisIntervalMinutes(): int
    {
        $configured = max( 1, (int) config( 'artisanpack.analytics.bot_detection.analysis_interval', 15 ) );

        $divisors = [ 1, 2, 3, 4, 5, 6, 10, 12, 15, 20, 30 ];
        $interval = 1;

        foreach ( $divisors as $divisor ) {
            if ( $divisor <= $configured ) {
                $interval = $divisor;
            }
        }

        return $interval;
    }

    /**
     * Register built-in analytics providers.
     *
     * @since 1.0.0
     */
    protected function registerBuiltInProviders(): void
    {
        /** @var Analytics $analytics */
        $analytics = $this->app->make( Analytics::class );

        // Register local provider
        $analytics->extend( 'local', function ( $app ) {
            return $app->make( Providers\LocalAnalyticsProvider::class );
        } );

        // Register external providers if their configurations exist
        if ( config( 'artisanpack.analytics.providers.google.enabled' ) ) {
            $analytics->extend( 'google', function ( $app ) {
                return $app->make( Providers\GoogleAnalyticsProvider::class );
            } );
        }

        if ( config( 'artisanpack.analytics.providers.plausible.enabled' ) ) {
            $analytics->extend( 'plausible', function ( $app ) {
                return $app->make( Providers\PlausibleProvider::class );
            } );
        }
    }

    /**
     * Register Livewire components.
     *
     * Uses the addNamespace API for Livewire 4, falling back to
     * individual component() calls for Livewire 3.
     *
     * @since 1.0.0
     * @since 1.1.0 Updated to use addNamespace for Livewire 4 support.
     */
    protected function registerLivewireComponents(): void
    {
        // Only register if Livewire is available
        if ( ! class_exists( \Livewire\Livewire::class ) ) {
            return;
        }

        // Livewire 4 supports addNamespace for automatic class resolution.
        // Check against LivewireManager (not the Facade) for method_exists.
        if ( method_exists( \Livewire\LivewireManager::class, 'addNamespace' ) ) {
            \Livewire\Livewire::addNamespace(
                namespace: 'artisanpack-analytics',
                classNamespace: 'ArtisanPackUI\\Analytics\\Http\\Livewire',
                classPath: __DIR__ . '/Http/Livewire',
                classViewPath: __DIR__ . '/../resources/views/livewire',
            );

            return;
        }

        // Livewire 3 fallback: register each component individually.
        \Livewire\Livewire::component( 'artisanpack-analytics::analytics-dashboard', Http\Livewire\AnalyticsDashboard::class );
        \Livewire\Livewire::component( 'artisanpack-analytics::page-analytics', Http\Livewire\PageAnalytics::class );
        \Livewire\Livewire::component( 'artisanpack-analytics::site-selector', Http\Livewire\SiteSelector::class );
        \Livewire\Livewire::component( 'artisanpack-analytics::multi-tenant-dashboard', Http\Livewire\MultiTenantDashboard::class );
        \Livewire\Livewire::component( 'artisanpack-analytics::platform-dashboard', Http\Livewire\PlatformDashboard::class );
        \Livewire\Livewire::component( 'artisanpack-analytics::widgets.stats-cards', Http\Livewire\Widgets\StatsCards::class );
        \Livewire\Livewire::component( 'artisanpack-analytics::widgets.visitors-chart', Http\Livewire\Widgets\VisitorsChart::class );
        \Livewire\Livewire::component( 'artisanpack-analytics::widgets.top-pages', Http\Livewire\Widgets\TopPages::class );
        \Livewire\Livewire::component( 'artisanpack-analytics::widgets.traffic-sources', Http\Livewire\Widgets\TrafficSources::class );
        \Livewire\Livewire::component( 'artisanpack-analytics::widgets.realtime-visitors', Http\Livewire\Widgets\RealtimeVisitors::class );
        \Livewire\Livewire::component( 'artisanpack-analytics::widgets.bot-traffic', Http\Livewire\Widgets\BotTraffic::class );
        \Livewire\Livewire::component( 'artisanpack-analytics::widgets.anonymous-traffic', Http\Livewire\Widgets\AnonymousTraffic::class );
    }

    /**
     * Register Blade directives for analytics.
     *
     * @return void
     *
     * @since 1.0.0
     */
    protected function registerBladeDirectives(): void
    {
        // @analyticsScripts - Output tracker script.
        //
        // Any array passed to the directive reaches the component's `config`
        // prop, which emits it as a page-level override ahead of the tracker.
        // Until 1.5.0 the expression was accepted and then dropped on the floor,
        // because the component had no prop to receive it.
        Blade::directive( 'analyticsScripts', function ( $expression ): string {
            $config = $expression ?: '[]';

            return "<?php echo view('artisanpack-analytics::components.tracker-script', ['config' => {$config}])->render(); ?>";
        } );

        // @analyticsConsentBanner - Output consent banner
        Blade::directive( 'analyticsConsentBanner', function (): string {
            return "<?php echo view('artisanpack-analytics::components.consent-banner')->render(); ?>";
        } );

        // @analyticsConsent('type') / @endanalyticsConsent - Conditional consent block
        Blade::directive( 'analyticsConsent', function ( $expression ): string {
            $category = $expression ?: "'analytics'";

            // Check consent from cookie (set by consent banner) for server-side rendering
            // Use $_COOKIE directly to read the unencrypted JS-set cookie
            return "<?php
                \$__consentRequired = config('artisanpack.analytics.privacy.consent_required', false);
                \$__hasConsent = false;
                if ( ! \$__consentRequired ) {
                    \$__hasConsent = true;
                } else {
                    \$__consentCookie = isset( \$_COOKIE['ap_consent'] ) ? urldecode( \$_COOKIE['ap_consent'] ) : null;
                    if ( \$__consentCookie ) {
                        \$__consentData = json_decode( \$__consentCookie, true );
                        \$__category = {$category};
                        \$__hasConsent = isset( \$__consentData[ \$__category ] ) && \$__consentData[ \$__category ] === true;
                    }
                }
                if ( \$__hasConsent ): ?>";
        } );

        Blade::directive( 'endanalyticsConsent', function (): string {
            return '<?php endif; ?>';
        } );

        // @analyticsPageView - Track page view inline
        Blade::directive( 'analyticsPageView', function ( $expression ): string {
            if ( '' === $expression || '()' === $expression ) {
                return '<?php trackPageView(request()->path()); ?>';
            }

            return "<?php trackPageView({$expression}); ?>";
        } );

        // @analyticsEvent - Track event inline
        Blade::directive( 'analyticsEvent', function ( $expression ): string {
            return "<?php trackEvent({$expression}); ?>";
        } );

        // Only register Livewire directives if Livewire is available
        if ( class_exists( \Livewire\Livewire::class ) ) {
            // @analyticsDashboard - Render dashboard Livewire component
            Blade::directive( 'analyticsDashboard', function (): string {
                return "<?php echo \\Livewire\\Livewire::mount('artisanpack-analytics::analytics-dashboard')->html(); ?>";
            } );

            // @analyticsWidget - Render specific widget
            Blade::directive( 'analyticsWidget', function ( $expression ): string {
                $type = $expression ?: "'stats-cards'";

                return "<?php echo \\Livewire\\Livewire::mount('artisanpack-analytics::widgets.' . {$type})->html(); ?>";
            } );

            // @analyticsPageStats - Show page statistics for current or specified path
            Blade::directive( 'analyticsPageStats', function ( $expression ): string {
                if ( '' === $expression || '()' === $expression ) {
                    return "<?php echo \\Livewire\\Livewire::mount('artisanpack-analytics::page-analytics', ['path' => request()->path()])->html(); ?>";
                }

                return "<?php echo \\Livewire\\Livewire::mount('artisanpack-analytics::page-analytics', ['path' => {$expression}])->html(); ?>";
            } );
        }
    }

    /**
     * Register privacy package integration hooks.
     *
     * These hooks allow the future ArtisanPack UI Privacy package
     * to integrate with the analytics package for GDPR compliance.
     *
     * @since 1.0.0
     */
    protected function registerPrivacyHooks(): void
    {
        /** @var PrivacyIntegration $privacyIntegration */
        $privacyIntegration = $this->app->make( PrivacyIntegration::class );
        $privacyIntegration->register();
    }

    /**
     * Register the analytics API key authentication guard.
     *
     * @return void
     *
     * @since 1.0.0
     */
    protected function registerAuthGuard(): void
    {
        Auth::extend( 'analytics-api', function ( $app, $name, array $config ) {
            return new ApiKeyGuard(
                $app->make( TenantManager::class ),
                $app->make( 'request' ),
            );
        } );
    }

    /**
     * Register shared Inertia props for analytics data.
     *
     * When the dashboard driver is 'inertia' and share_data is enabled,
     * common analytics data (current site, consent status, analytics config)
     * is shared as Inertia shared props on all requests.
     *
     * @return void
     *
     * @since 1.1.0
     */
    protected function registerInertiaSharedData(): void
    {
        $driver = config( 'artisanpack.analytics.dashboard_driver', 'livewire' );

        if ( 'inertia' !== $driver ) {
            return;
        }

        if ( ! config( 'artisanpack.analytics.inertia.share_data', true ) ) {
            return;
        }

        if ( ! class_exists( \Inertia\Inertia::class ) ) {
            return;
        }

        \Inertia\Inertia::share( 'analytics', function () {
            $shared = [
                'enabled'          => config( 'artisanpack.analytics.enabled', true ),
                'consent_required' => config( 'artisanpack.analytics.privacy.consent_required', false ),
                'dashboard_route'  => config( 'artisanpack.analytics.dashboard_route', 'analytics' ),
                'realtime_enabled' => config( 'artisanpack.analytics.dashboard.realtime_enabled', true ),
            ];

            // Include current site info if multi-tenant is enabled
            if ( analyticsMultiTenancyEnabled() ) {
                $tenantManager = $this->app->make( TenantManager::class );

                // current() rather than hasCurrent(): the latter only says an
                // identifier is in context, and a sibling package can pin one
                // that has no row in `sites`. Reading ->id off that null threw
                // on every Inertia render.
                $site = $tenantManager->current();

                if ( null !== $site ) {
                    $shared['site'] = [
                        'id'     => $site->id,
                        'name'   => $site->name,
                        'domain' => $site->domain,
                    ];
                }
            }

            return $shared;
        } );
    }
}
