<?php

declare( strict_types=1 );

use ArtisanPackUI\Analytics\Http\Controllers\AnalyticsController;
use ArtisanPackUI\Analytics\Http\Controllers\AnalyticsQueryController;
use ArtisanPackUI\Analytics\Http\Controllers\ConsentController;
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
