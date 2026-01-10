<?php

declare( strict_types=1 );

use ArtisanPackUI\Analytics\Http\Controllers\TrackerController;
use Illuminate\Support\Facades\Route;

/**
 * Analytics Web Routes
 *
 * These routes provide web endpoints for the analytics dashboard
 * and the JavaScript tracker script.
 *
 * @since 1.0.0
 */

/*
|--------------------------------------------------------------------------
| Tracker Script Route
|--------------------------------------------------------------------------
|
| This route serves the JavaScript analytics tracker script.
|
*/
Route::get( '/js/analytics.js', [ TrackerController::class, 'script' ] )
	->name( 'analytics.tracker.script' );

Route::get( '/js/analytics.min.js', [ TrackerController::class, 'minifiedScript' ] )
	->name( 'analytics.tracker.script.min' );
