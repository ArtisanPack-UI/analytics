<?php

declare( strict_types=1 );

use ArtisanPackUI\Analytics\Http\Controllers\AnalyticsController;
use ArtisanPackUI\Analytics\Http\Controllers\AnalyticsQueryController;
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
