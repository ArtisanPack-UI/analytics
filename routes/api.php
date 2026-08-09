<?php

declare( strict_types=1 );

use ArtisanPackUI\Analytics\Http\Controllers\AnalyticsController;
use ArtisanPackUI\Analytics\Http\Controllers\AnalyticsQueryController;
use ArtisanPackUI\Analytics\Http\Controllers\Api\AiAgentApiController;
use ArtisanPackUI\Analytics\Http\Controllers\ConsentController;
use ArtisanPackUI\Analytics\Http\Controllers\SiteApiController;
use Illuminate\Support\Facades\Route;

/**
 * Analytics API Routes
 *
 * These routes provide API endpoints for analytics data collection and querying.
 * Collection endpoints are public but protected by rate limiting and privacy filters.
 * Query endpoints require authentication.
 *
 * @since 1.0.0
 */

/*
|--------------------------------------------------------------------------
| Data Collection Routes
|--------------------------------------------------------------------------
|
| These routes handle incoming analytics data from the JavaScript tracker.
| They are rate-limited and pass through privacy filters.
|
*/
Route::post( '/pageview', [ AnalyticsController::class, 'pageview' ] )
	->name( 'analytics.pageview' );

Route::post( '/event', [ AnalyticsController::class, 'event' ] )
	->name( 'analytics.event' );

Route::post( '/session/start', [ AnalyticsController::class, 'startSession' ] )
	->name( 'analytics.session.start' );

Route::post( '/session/end', [ AnalyticsController::class, 'endSession' ] )
	->name( 'analytics.session.end' );

Route::post( '/session/extend', [ AnalyticsController::class, 'extendSession' ] )
	->name( 'analytics.session.extend' );

Route::post( '/batch', [ AnalyticsController::class, 'batch' ] )
	->name( 'analytics.batch' );

Route::post( '/pageview/update', [ AnalyticsController::class, 'updatePageview' ] )
	->name( 'analytics.pageview.update' );

// Pre-consent tracking. Kept on its own path so the identifier-free payload
// gets its own validation surface, and cannot be confused with an identified
// beacon that merely forgot to send its visitor ID.
Route::post( '/anonymous/pageview', [ AnalyticsController::class, 'anonymousPageview' ] )
	->name( 'analytics.anonymous.pageview' );

/*
|--------------------------------------------------------------------------
| Consent Routes
|--------------------------------------------------------------------------
|
| These routes handle consent management for GDPR/CCPA compliance.
|
*/
Route::get( '/consent', [ ConsentController::class, 'status' ] )
	->name( 'analytics.consent.status' );

Route::post( '/consent', [ ConsentController::class, 'update' ] )
	->name( 'analytics.consent.update' );

/*
|--------------------------------------------------------------------------
| Query Routes (Authenticated)
|--------------------------------------------------------------------------
|
| These routes provide access to analytics data and require authentication.
|
*/
Route::middleware( config( 'artisanpack.analytics.dashboard_middleware', [ 'auth:sanctum' ] ) )->group( function (): void {
	Route::get( '/stats', [ AnalyticsQueryController::class, 'stats' ] )
		->name( 'analytics.stats' );

	Route::get( '/pages', [ AnalyticsQueryController::class, 'pages' ] )
		->name( 'analytics.pages' );

	Route::get( '/sources', [ AnalyticsQueryController::class, 'sources' ] )
		->name( 'analytics.sources' );

	Route::get( '/events', [ AnalyticsQueryController::class, 'events' ] )
		->name( 'analytics.events' );

	Route::get( '/devices', [ AnalyticsQueryController::class, 'devices' ] )
		->name( 'analytics.devices' );

	Route::get( '/countries', [ AnalyticsQueryController::class, 'countries' ] )
		->name( 'analytics.countries' );

	Route::get( '/realtime', [ AnalyticsQueryController::class, 'realtime' ] )
		->name( 'analytics.realtime' );

	Route::get( '/bots', [ AnalyticsQueryController::class, 'bots' ] )
		->name( 'analytics.bots' );

	Route::get( '/anonymous', [ AnalyticsQueryController::class, 'anonymous' ] )
		->name( 'analytics.anonymous' );

	Route::get( '/referrers', [ AnalyticsQueryController::class, 'referrers' ] )
		->name( 'analytics.referrers' );

	/*
	|--------------------------------------------------------------------------
	| AI Agent Endpoints (since 1.3.0)
	|--------------------------------------------------------------------------
	|
	| POST endpoints for the four Analytics AI agents. Each dispatches its
	| corresponding agent behind the `analytics.ai.use` gate and returns a
	| `{ data, feature_key }` envelope.
	|
	*/
	Route::prefix( 'ai' )->group( function (): void {
		Route::post( 'insight-summary', [ AiAgentApiController::class, 'insightSummary' ] )
			->name( 'analytics.api.ai.insight-summary' );

		Route::post( 'explain-anomaly', [ AiAgentApiController::class, 'explainAnomaly' ] )
			->name( 'analytics.api.ai.explain-anomaly' );

		Route::post( 'segment-insight', [ AiAgentApiController::class, 'segmentInsight' ] )
			->name( 'analytics.api.ai.segment-insight' );

		Route::post( 'digest-email', [ AiAgentApiController::class, 'digestEmail' ] )
			->name( 'analytics.api.ai.digest-email' );

		Route::post( 'digest-subscription', [ AiAgentApiController::class, 'saveDigestSubscription' ] )
			->name( 'analytics.api.ai.digest-subscription' );
	} );
} );

/*
|--------------------------------------------------------------------------
| API Key Authenticated Routes (Multi-Tenant)
|--------------------------------------------------------------------------
|
| These routes require API key authentication and are used for multi-tenant
| integrations where each site has its own API key.
|
*/
Route::middleware( [ 'analytics.api-key' ] )->prefix( 'v1' )->group( function (): void {
	/*
	|--------------------------------------------------------------------------
	| Tracking Endpoints
	|--------------------------------------------------------------------------
	*/
	Route::post( '/track/pageview', [ AnalyticsController::class, 'pageview' ] )
		->name( 'analytics.api.track.pageview' );

	Route::post( '/track/event', [ AnalyticsController::class, 'event' ] )
		->name( 'analytics.api.track.event' );

	Route::post( '/track/session/start', [ AnalyticsController::class, 'startSession' ] )
		->name( 'analytics.api.track.session.start' );

	Route::post( '/track/session/end', [ AnalyticsController::class, 'endSession' ] )
		->name( 'analytics.api.track.session.end' );

	Route::post( '/track/batch', [ AnalyticsController::class, 'batch' ] )
		->name( 'analytics.api.track.batch' );

	/*
	|--------------------------------------------------------------------------
	| Query Endpoints
	|--------------------------------------------------------------------------
	*/
	Route::get( '/stats', [ AnalyticsQueryController::class, 'stats' ] )
		->name( 'analytics.api.stats' );

	Route::get( '/visitors', [ AnalyticsQueryController::class, 'visitors' ] )
		->name( 'analytics.api.visitors' );

	Route::get( '/pages', [ AnalyticsQueryController::class, 'pages' ] )
		->name( 'analytics.api.pages' );

	Route::get( '/events', [ AnalyticsQueryController::class, 'events' ] )
		->name( 'analytics.api.events' );

	Route::get( '/sources', [ AnalyticsQueryController::class, 'sources' ] )
		->name( 'analytics.api.sources' );

	Route::get( '/devices', [ AnalyticsQueryController::class, 'devices' ] )
		->name( 'analytics.api.devices' );

	Route::get( '/countries', [ AnalyticsQueryController::class, 'countries' ] )
		->name( 'analytics.api.countries' );

	Route::get( '/realtime', [ AnalyticsQueryController::class, 'realtime' ] )
		->name( 'analytics.api.realtime' );

	Route::get( '/bots', [ AnalyticsQueryController::class, 'bots' ] )
		->name( 'analytics.api.bots' );

	Route::get( '/anonymous', [ AnalyticsQueryController::class, 'anonymous' ] )
		->name( 'analytics.api.anonymous' );

	Route::get( '/referrers', [ AnalyticsQueryController::class, 'referrers' ] )
		->name( 'analytics.api.referrers' );

	/*
	|--------------------------------------------------------------------------
	| Site Management Endpoints
	|--------------------------------------------------------------------------
	*/
	Route::get( '/site', [ SiteApiController::class, 'show' ] )
		->name( 'analytics.api.site.show' );

	Route::put( '/site/settings', [ SiteApiController::class, 'updateSettings' ] )
		->name( 'analytics.api.site.settings' );

	Route::get( '/site/stats', [ SiteApiController::class, 'stats' ] )
		->name( 'analytics.api.site.stats' );

	Route::get( '/site/goals', [ SiteApiController::class, 'goals' ] )
		->name( 'analytics.api.site.goals' );

	// Note: API key rotation is intentionally NOT exposed via API key auth
	// to prevent key holders from locking out site owners.
	// Rotation should be done via dashboard with session auth.
} );
