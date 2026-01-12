<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics;

use ArtisanPackUI\Analytics\Auth\ApiKeyGuard;
use ArtisanPackUI\Analytics\Console\Commands\CacheClearCommand;
use ArtisanPackUI\Analytics\Console\Commands\CleanupCommand;
use ArtisanPackUI\Analytics\Console\Commands\GoalsListCommand;
use ArtisanPackUI\Analytics\Console\Commands\InstallCommand;
use ArtisanPackUI\Analytics\Console\Commands\RealtimeCommand;
use ArtisanPackUI\Analytics\Console\Commands\SiteApiKeyCommand;
use ArtisanPackUI\Analytics\Console\Commands\SiteCreateCommand;
use ArtisanPackUI\Analytics\Console\Commands\SitesListCommand;
use ArtisanPackUI\Analytics\Console\Commands\StatsCommand;
use ArtisanPackUI\Analytics\Contracts\AnalyticsServiceInterface;
use ArtisanPackUI\Analytics\Http\Livewire\AnalyticsDashboard;
use ArtisanPackUI\Analytics\Http\Livewire\MultiTenantDashboard;
use ArtisanPackUI\Analytics\Http\Livewire\PageAnalytics;
use ArtisanPackUI\Analytics\Http\Livewire\PlatformDashboard;
use ArtisanPackUI\Analytics\Http\Livewire\SiteSelector;
use ArtisanPackUI\Analytics\Http\Livewire\Widgets\RealtimeVisitors;
use ArtisanPackUI\Analytics\Http\Livewire\Widgets\StatsCards;
use ArtisanPackUI\Analytics\Http\Livewire\Widgets\TopPages;
use ArtisanPackUI\Analytics\Http\Livewire\Widgets\TrafficSources;
use ArtisanPackUI\Analytics\Http\Livewire\Widgets\VisitorsChart;
use ArtisanPackUI\Analytics\Http\Middleware\AnalyticsThrottle;
use ArtisanPackUI\Analytics\Http\Middleware\AuthenticateWithApiKey;
use ArtisanPackUI\Analytics\Http\Middleware\PrivacyFilter;
use ArtisanPackUI\Analytics\Http\Middleware\ResolveSite;
use ArtisanPackUI\Analytics\Http\Middleware\TenantResolver;
use ArtisanPackUI\Analytics\Services\AnalyticsQuery;
use ArtisanPackUI\Analytics\Services\ConsentService;
use ArtisanPackUI\Analytics\Services\CrossTenantReporting;
use ArtisanPackUI\Analytics\Services\DataDeletionService;
use ArtisanPackUI\Analytics\Services\DataExportService;
use ArtisanPackUI\Analytics\Services\EventProcessor;
use ArtisanPackUI\Analytics\Services\FunnelAnalyzer;
use ArtisanPackUI\Analytics\Services\GoalMatcher;
use ArtisanPackUI\Analytics\Services\GoalService;
use ArtisanPackUI\Analytics\Services\IpAnonymizer;
use ArtisanPackUI\Analytics\Services\PrivacyIntegration;
use ArtisanPackUI\Analytics\Services\SiteSettingsService;
use ArtisanPackUI\Analytics\Services\TenantManager;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
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

        // Register TenantManager as singleton
        $this->app->singleton( TenantManager::class, function () {
            $manager = new TenantManager;

            // Register resolvers from config
            $resolvers = config( 'artisanpack.analytics.multi_tenant.resolvers', [] );

            if ( ! empty( $resolvers ) ) {
                $manager->registerResolversFromConfig( $resolvers );
            }

            return $manager;
        } );

        // Register SiteSettingsService
        $this->app->singleton( SiteSettingsService::class, function ( $app ) {
            return new SiteSettingsService(
                $app->make( TenantManager::class ),
            );
        } );

        // Register CrossTenantReporting
        $this->app->singleton( CrossTenantReporting::class, function () {
            return new CrossTenantReporting;
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
        $this->mergeConfiguration();
        $this->publishConfiguration();
        $this->publishMigrations();
        $this->publishViews();
        $this->publishTracker();
        $this->registerMiddleware();
        $this->registerRoutes();
        $this->registerCommands();
        $this->registerBuiltInProviders();
        $this->registerLivewireComponents();
        $this->registerBladeDirectives();
        $this->registerPrivacyHooks();
        $this->registerAuthGuard();
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
     * @since 1.0.0
     */
    protected function registerRoutes(): void
    {
        $routePrefix     = config( 'artisanpack.analytics.route_prefix', 'api/analytics' );
        $routeMiddleware = config( 'artisanpack.analytics.route_middleware', ['api', 'analytics'] );

        // Register API routes
        Route::prefix( $routePrefix )
            ->middleware( $routeMiddleware )
            ->group( __DIR__ . '/../routes/api.php' );

        // Register web routes for dashboard and tracker script
        $dashboardRoute = config( 'artisanpack.analytics.dashboard_route' );

        if ( $dashboardRoute ) {
            Route::middleware( config( 'artisanpack.analytics.dashboard_middleware', ['web', 'auth'] ) )
                ->group( __DIR__ . '/../routes/web.php' );
        }
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
                StatsCommand::class,
                CleanupCommand::class,
                CacheClearCommand::class,
                SitesListCommand::class,
                SiteCreateCommand::class,
                SiteApiKeyCommand::class,
                GoalsListCommand::class,
                RealtimeCommand::class,
            ] );
        }
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
     * @since 1.0.0
     */
    protected function registerLivewireComponents(): void
    {
        // Only register if Livewire is available
        if ( ! class_exists( \Livewire\Livewire::class ) ) {
            return;
        }

        // Register widgets
        \Livewire\Livewire::component( 'artisanpack-analytics::stats-cards', StatsCards::class );
        \Livewire\Livewire::component( 'artisanpack-analytics::visitors-chart', VisitorsChart::class );
        \Livewire\Livewire::component( 'artisanpack-analytics::top-pages', TopPages::class );
        \Livewire\Livewire::component( 'artisanpack-analytics::traffic-sources', TrafficSources::class );
        \Livewire\Livewire::component( 'artisanpack-analytics::realtime-visitors', RealtimeVisitors::class );

        // Register main components
        \Livewire\Livewire::component( 'artisanpack-analytics::dashboard', AnalyticsDashboard::class );
        \Livewire\Livewire::component( 'artisanpack-analytics::analytics-dashboard', AnalyticsDashboard::class );
        \Livewire\Livewire::component( 'artisanpack-analytics::page-analytics', PageAnalytics::class );

        // Register multi-tenant components
        \Livewire\Livewire::component( 'artisanpack-analytics::site-selector', SiteSelector::class );
        \Livewire\Livewire::component( 'artisanpack-analytics::multi-tenant-dashboard', MultiTenantDashboard::class );
        \Livewire\Livewire::component( 'artisanpack-analytics::platform-dashboard', PlatformDashboard::class );
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
        // @analyticsScripts - Output tracker script
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

            return "<?php if (config('artisanpack.analytics.privacy.consent_required', false) === false || (function_exists('analyticsHasConsent') && analyticsHasConsent(null, {$category}))): ?>";
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

                return "<?php echo \\Livewire\\Livewire::mount('artisanpack-analytics::' . {$type})->html(); ?>";
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
}
