<?php

declare( strict_types=1 );

use ArtisanPackUI\Analytics\Http\Resources\BrowserBreakdownResource;
use ArtisanPackUI\Analytics\Http\Resources\CountryBreakdownResource;
use ArtisanPackUI\Analytics\Http\Resources\DeviceBreakdownResource;
use ArtisanPackUI\Analytics\Http\Resources\EventBreakdownResource;
use ArtisanPackUI\Analytics\Http\Resources\PageViewTimeSeriesResource;
use ArtisanPackUI\Analytics\Http\Resources\StatsResource;
use ArtisanPackUI\Analytics\Http\Resources\TopPageResource;
use ArtisanPackUI\Analytics\Http\Resources\TrafficSourceResource;
use Illuminate\Http\Request;

test( 'stats resource transforms data correctly', function (): void {
	$data = [
		'pageviews'            => 1500,
		'visitors'             => 800,
		'sessions'             => 1000,
		'bounce_rate'          => 45.5,
		'avg_session_duration' => 120,
		'pages_per_session'    => 1.5,
		'realtime_visitors'    => 5,
		'comparison'           => [
			'pageviews' => ['value' => 1200, 'change' => 25.0, 'trend' => 'up'],
		],
	];

	$resource = ( new StatsResource( $data ) )->toArray( new Request );

	expect( $resource )
		->toHaveKey( 'pageviews', 1500 )
		->toHaveKey( 'visitors', 800 )
		->toHaveKey( 'sessions', 1000 )
		->toHaveKey( 'bounce_rate', 45.5 )
		->toHaveKey( 'avg_session_duration', 120 )
		->toHaveKey( 'pages_per_session', 1.5 )
		->toHaveKey( 'realtime_visitors', 5 )
		->toHaveKey( 'comparison' );
} );

test( 'stats resource provides defaults for missing data', function (): void {
	$resource = ( new StatsResource( [] ) )->toArray( new Request );

	expect( $resource )
		->toHaveKey( 'pageviews', 0 )
		->toHaveKey( 'visitors', 0 )
		->toHaveKey( 'sessions', 0 )
		->toHaveKey( 'bounce_rate', 0.0 )
		->toHaveKey( 'avg_session_duration', 0 )
		->toHaveKey( 'pages_per_session', 0.0 )
		->toHaveKey( 'realtime_visitors', 0 )
		->toHaveKey( 'comparison', null );
} );

test( 'top page resource transforms data correctly', function (): void {
	$data = [
		'path'         => '/about',
		'title'        => 'About Us',
		'views'        => 350,
		'unique_views' => 280,
	];

	$resource = ( new TopPageResource( $data ) )->toArray( new Request );

	expect( $resource )
		->toHaveKey( 'path', '/about' )
		->toHaveKey( 'title', 'About Us' )
		->toHaveKey( 'views', 350 )
		->toHaveKey( 'unique_views', 280 );
} );

test( 'traffic source resource transforms data correctly', function (): void {
	$data = [
		'source'   => 'google',
		'medium'   => 'organic',
		'sessions' => 500,
		'visitors' => 400,
	];

	$resource = ( new TrafficSourceResource( $data ) )->toArray( new Request );

	expect( $resource )
		->toHaveKey( 'source', 'google' )
		->toHaveKey( 'medium', 'organic' )
		->toHaveKey( 'sessions', 500 )
		->toHaveKey( 'visitors', 400 );
} );

test( 'device breakdown resource transforms data correctly', function (): void {
	$data = [
		'device_type' => 'desktop',
		'sessions'    => 600,
		'percentage'  => 60.0,
	];

	$resource = ( new DeviceBreakdownResource( $data ) )->toArray( new Request );

	expect( $resource )
		->toHaveKey( 'device_type', 'desktop' )
		->toHaveKey( 'sessions', 600 )
		->toHaveKey( 'percentage', 60.0 );
} );

test( 'browser breakdown resource transforms data correctly', function (): void {
	$data = [
		'browser'    => 'Chrome',
		'version'    => '120.0',
		'sessions'   => 400,
		'percentage' => 40.0,
	];

	$resource = ( new BrowserBreakdownResource( $data ) )->toArray( new Request );

	expect( $resource )
		->toHaveKey( 'browser', 'Chrome' )
		->toHaveKey( 'version', '120.0' )
		->toHaveKey( 'sessions', 400 )
		->toHaveKey( 'percentage', 40.0 );
} );

test( 'country breakdown resource transforms data correctly', function (): void {
	$data = [
		'country'      => 'United States',
		'country_code' => 'US',
		'sessions'     => 300,
		'percentage'   => 30.0,
	];

	$resource = ( new CountryBreakdownResource( $data ) )->toArray( new Request );

	expect( $resource )
		->toHaveKey( 'country', 'United States' )
		->toHaveKey( 'country_code', 'US' )
		->toHaveKey( 'sessions', 300 )
		->toHaveKey( 'percentage', 30.0 );
} );

test( 'page view time series resource transforms data correctly', function (): void {
	$data = [
		'date'      => '2024-01-15',
		'pageviews' => 100,
		'visitors'  => 80,
	];

	$resource = ( new PageViewTimeSeriesResource( $data ) )->toArray( new Request );

	expect( $resource )
		->toHaveKey( 'date', '2024-01-15' )
		->toHaveKey( 'pageviews', 100 )
		->toHaveKey( 'visitors', 80 );
} );

test( 'event breakdown resource transforms data correctly', function (): void {
	$data = [
		'name'        => 'button_click',
		'category'    => 'engagement',
		'count'       => 250,
		'total_value' => 50.0,
		'percentage'  => 25.0,
	];

	$resource = ( new EventBreakdownResource( $data ) )->toArray( new Request );

	expect( $resource )
		->toHaveKey( 'name', 'button_click' )
		->toHaveKey( 'category', 'engagement' )
		->toHaveKey( 'count', 250 )
		->toHaveKey( 'total_value', 50.0 )
		->toHaveKey( 'percentage', 25.0 );
} );
