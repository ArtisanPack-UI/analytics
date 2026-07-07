<?php

declare( strict_types=1 );

use ArtisanPackUI\Analytics\Jobs\SendDigestEmailJob;
use ArtisanPackUI\Analytics\Mail\DigestEmailMailable;
use ArtisanPackUI\Analytics\Models\AnalyticsDigestPreference;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Ai\AiAgentTestSetup;

beforeEach( function (): void {
	$this->prompter = AiAgentTestSetup::bootstrap( $this->app );

	if ( ! Schema::hasTable( 'analytics_digest_preferences' ) ) {
		Schema::create( 'analytics_digest_preferences', function ( $table ): void {
			$table->id();
			$table->unsignedBigInteger( 'user_id' );
			$table->string( 'cadence', 16 )->default( 'off' );
			$table->timestamp( 'last_sent_at' )->nullable();
			$table->timestamps();
			$table->unique( 'user_id' );
		} );
	}
} );

it( 'skips users whose preference is off', function (): void {
	AnalyticsDigestPreference::create( [ 'user_id' => 1, 'cadence' => 'off' ] );

	Mail::fake();

	( new SendDigestEmailJob( 1, 'user@example.test', [ [ 'metric' => 'pv', 'value' => 10 ] ], 'May 1 - May 7' ) )->handle();

	Mail::assertNothingOutgoing();
} );

it( 'skips users with no preference row', function (): void {
	Mail::fake();

	( new SendDigestEmailJob( 999, 'ghost@example.test', [ [ 'metric' => 'pv', 'value' => 10 ] ], 'May 1 - May 7' ) )->handle();

	Mail::assertNothingOutgoing();
} );

it( 'sends a mailable and marks last_sent_at when opted in', function (): void {
	$preference = AnalyticsDigestPreference::create( [ 'user_id' => 5, 'cadence' => 'weekly' ] );

	$this->prompter->queue( [
		'summary'    => 'Your site was up 12%.',
		'key_points' => [ 'A', 'B' ],
		'caveats'    => [],
	] );

	Mail::fake();

	( new SendDigestEmailJob( 5, 'user@example.test', [ [ 'metric' => 'pv', 'value' => 10 ] ], 'May 1 - May 7, 2026' ) )->handle();

	// The mailable implements ShouldQueue so Mail::to()->send() actually queues.
	Mail::assertQueued( DigestEmailMailable::class, function ( DigestEmailMailable $mail ): bool {
		return 'weekly' === $mail->cadence && 'May 1 - May 7, 2026' === $mail->periodLabel;
	} );

	$preference->refresh();
	expect( $preference->last_sent_at )->not->toBeNull();
} );

it( 'does not send when the feature toggle is off', function (): void {
	AnalyticsDigestPreference::create( [ 'user_id' => 7, 'cadence' => 'weekly' ] );

	app( ArtisanPackUI\Ai\Contracts\FeatureRegistry::class )->disable( 'analytics.digest_email' );

	Mail::fake();

	( new SendDigestEmailJob( 7, 'user@example.test', [ [ 'metric' => 'pv', 'value' => 10 ] ], 'May 1 - May 7' ) )->handle();

	Mail::assertNothingOutgoing();
} );

it( 'skips when last_sent_at is already inside the current cadence window', function (): void {
	// Preference sent 10 minutes ago should not re-send this week.
	$preference = AnalyticsDigestPreference::create( [
		'user_id'      => 8,
		'cadence'      => 'weekly',
		'last_sent_at' => now()->subMinutes( 10 ),
	] );

	Mail::fake();

	( new SendDigestEmailJob( 8, 'user@example.test', [ [ 'metric' => 'pv', 'value' => 10 ] ], 'This week' ) )->handle();

	Mail::assertNothingOutgoing();
	// Preference untouched — the queued FakeAgentPrompter response is unused.
	expect( $preference->fresh()->last_sent_at )->not->toBeNull();
} );

it( 'fails closed when the feature key is not registered', function (): void {
	AnalyticsDigestPreference::create( [ 'user_id' => 10, 'cadence' => 'weekly' ] );

	// Remove the feature from the registry entirely (simulating a fresh
	// install where auto-discovery hasn't run yet). The guard must not
	// bypass the toggle check just because the key is missing.
	$registry = app( ArtisanPackUI\Ai\Contracts\FeatureRegistry::class );

	// If the registry doesn't expose remove(), disable() has the same
	// externally-observable effect via `isToggleOn() === false`.
	if ( method_exists( $registry, 'reset' ) ) {
		$registry->reset();
	} else {
		$registry->disable( 'analytics.digest_email' );
	}

	Mail::fake();

	( new SendDigestEmailJob( 10, 'user@example.test', [ [ 'metric' => 'pv', 'value' => 10 ] ], 'This week' ) )->handle();

	Mail::assertNothingOutgoing();
} );
