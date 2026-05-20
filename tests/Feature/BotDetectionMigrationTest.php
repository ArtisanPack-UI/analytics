<?php

declare( strict_types=1 );

use ArtisanPackUI\Analytics\Models\Visitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses( RefreshDatabase::class );

test( 'migration adds bot detection columns to analytics_visitors table', function (): void {
	expect( Schema::hasColumns( 'analytics_visitors', [
		'is_bot',
		'bot_score',
		'bot_detected_at',
	] ) )->toBeTrue();
} );

test( 'is_bot defaults to false for new visitors', function (): void {
	$visitor = Visitor::create( [
		'fingerprint'   => 'fingerprint-1',
		'first_seen_at' => now(),
		'last_seen_at'  => now(),
	] );

	expect( $visitor->fresh()->is_bot )->toBeFalse();
} );

test( 'bot detection columns are mass assignable and cast correctly', function (): void {
	$visitor = Visitor::create( [
		'fingerprint'     => 'fingerprint-2',
		'first_seen_at'   => now(),
		'last_seen_at'    => now(),
		'is_bot'          => true,
		'bot_score'       => 85,
		'bot_detected_at' => now(),
	] )->fresh();

	expect( $visitor->is_bot )->toBeTrue()
		->and( $visitor->bot_score )->toBe( 85 )
		->and( $visitor->bot_detected_at )->toBeInstanceOf( Carbon\Carbon::class );
} );

test( 'human scope filters to non-bot visitors', function (): void {
	Visitor::create( [
		'fingerprint'   => 'human-1',
		'first_seen_at' => now(),
		'last_seen_at'  => now(),
		'is_bot'        => false,
	] );
	Visitor::create( [
		'fingerprint'   => 'bot-1',
		'first_seen_at' => now(),
		'last_seen_at'  => now(),
		'is_bot'        => true,
	] );

	$humans = Visitor::human()->get();

	expect( $humans )->toHaveCount( 1 )
		->and( $humans->first()->fingerprint )->toBe( 'human-1' );
} );

test( 'bot scope filters to bot visitors', function (): void {
	Visitor::create( [
		'fingerprint'   => 'human-2',
		'first_seen_at' => now(),
		'last_seen_at'  => now(),
		'is_bot'        => false,
	] );
	Visitor::create( [
		'fingerprint'   => 'bot-2',
		'first_seen_at' => now(),
		'last_seen_at'  => now(),
		'is_bot'        => true,
	] );

	$bots = Visitor::bot()->get();

	expect( $bots )->toHaveCount( 1 )
		->and( $bots->first()->fingerprint )->toBe( 'bot-2' );
} );
