<?php

declare( strict_types=1 );

use ArtisanPackUI\Analytics\Data\DateRange;
use Carbon\Carbon;

beforeEach( function (): void {
	Carbon::setTestNow( Carbon::parse( '2026-01-10 12:00:00' ) );
} );

afterEach( function (): void {
	Carbon::setTestNow();
} );

test( 'can create date range for last 7 days', function (): void {
	$range = DateRange::lastDays( 7 );

	expect( $range->startDate->format( 'Y-m-d' ) )->toBe( '2026-01-03' );
	expect( $range->endDate->format( 'Y-m-d' ) )->toBe( '2026-01-10' );
} );

test( 'can create date range for today', function (): void {
	$range = DateRange::today();

	expect( $range->startDate->format( 'Y-m-d' ) )->toBe( '2026-01-10' );
	expect( $range->endDate->format( 'Y-m-d' ) )->toBe( '2026-01-10' );
} );

test( 'can create date range for yesterday', function (): void {
	$range = DateRange::yesterday();

	expect( $range->startDate->format( 'Y-m-d' ) )->toBe( '2026-01-09' );
	expect( $range->endDate->format( 'Y-m-d' ) )->toBe( '2026-01-09' );
} );

test( 'can create date range for this week', function (): void {
	$range = DateRange::thisWeek();

	expect( $range->startDate->isMonday() )->toBeTrue();
	expect( $range->endDate->isSunday() )->toBeTrue();
} );

test( 'can create date range for this month', function (): void {
	$range = DateRange::thisMonth();

	expect( $range->startDate->format( 'Y-m-d' ) )->toBe( '2026-01-01' );
	expect( $range->endDate->format( 'Y-m-d' ) )->toBe( '2026-01-31' );
} );

test( 'can create date range from strings', function (): void {
	$range = DateRange::fromStrings( '2026-01-01', '2026-01-15' );

	expect( $range->startDate->format( 'Y-m-d' ) )->toBe( '2026-01-01' );
	expect( $range->endDate->format( 'Y-m-d' ) )->toBe( '2026-01-15' );
} );

test( 'can get number of days in range', function (): void {
	$range = DateRange::lastDays( 7 );

	expect( $range->getDays() )->toBe( 8 );
} );

test( 'can generate cache key', function (): void {
	$range = DateRange::fromStrings( '2026-01-01', '2026-01-15' );
	$key   = $range->toKey();

	expect( $key )->toBe( '2026-01-01_2026-01-15' );
} );

test( 'can get previous period', function (): void {
	$range    = DateRange::fromStrings( '2026-01-10', '2026-01-16' );
	$previous = $range->getPreviousPeriod();

	expect( $previous->startDate->format( 'Y-m-d' ) )->toBe( '2026-01-03' );
	expect( $previous->endDate->format( 'Y-m-d' ) )->toBe( '2026-01-09' );
} );

test( 'can convert to array', function (): void {
	$range = DateRange::today();
	$array = $range->toArray();

	expect( $array )->toHaveKeys( [ 'start_date', 'end_date' ] );
	expect( $array['start_date'] )->toContain( '2026-01-10' );
	expect( $array['end_date'] )->toContain( '2026-01-10' );
} );

test( 'dateRangeLastDays helper works', function (): void {
	$range = dateRangeLastDays( 30 );

	expect( $range )->toBeInstanceOf( DateRange::class );
	expect( $range->getDays() )->toBe( 31 );
} );

test( 'dateRangeToday helper works', function (): void {
	$range = dateRangeToday();

	expect( $range )->toBeInstanceOf( DateRange::class );
	expect( $range->startDate->format( 'Y-m-d' ) )->toBe( '2026-01-10' );
} );

test( 'dateRangeThisMonth helper works', function (): void {
	$range = dateRangeThisMonth();

	expect( $range )->toBeInstanceOf( DateRange::class );
	expect( $range->startDate->format( 'Y-m-d' ) )->toBe( '2026-01-01' );
} );
