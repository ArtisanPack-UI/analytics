<?php

declare( strict_types=1 );

use ArtisanPackUI\Analytics\Http\Controllers\InertiaDashboardController;
use Illuminate\Support\Facades\Route;

/**
 * Analytics Inertia Dashboard Routes
 *
 * These routes serve the analytics dashboard using Inertia.js responses,
 * returning page props for React/Vue dashboard components. Only registered
 * when the dashboard_driver config is set to 'inertia'.
 *
 * @since 1.1.0
 */

$dashboardRoute = config( 'artisanpack.analytics.dashboard_route', 'analytics' );

Route::prefix( $dashboardRoute )->group( function (): void {
	Route::get( '/', [ InertiaDashboardController::class, 'index' ] )
		->name( 'analytics.dashboard' );

	Route::get( '/pages', [ InertiaDashboardController::class, 'pages' ] )
		->name( 'analytics.dashboard.pages' );

	Route::get( '/traffic', [ InertiaDashboardController::class, 'traffic' ] )
		->name( 'analytics.dashboard.traffic' );

	Route::get( '/audience', [ InertiaDashboardController::class, 'audience' ] )
		->name( 'analytics.dashboard.audience' );

	Route::get( '/events', [ InertiaDashboardController::class, 'events' ] )
		->name( 'analytics.dashboard.events' );

	Route::get( '/realtime', [ InertiaDashboardController::class, 'realtime' ] )
		->name( 'analytics.dashboard.realtime' );
} );
