<?php

declare( strict_types=1 );

use ArtisanPackUI\Analytics\Http\Livewire\Ai\DigestSubscription;
use ArtisanPackUI\Analytics\Models\AnalyticsDigestPreference;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\Feature\Ai\AiAgentTestSetup;

beforeEach( function (): void {
	AiAgentTestSetup::bootstrap( $this->app );

	if ( ! Schema::hasTable( 'users' ) ) {
		Schema::create( 'users', function ( $table ): void {
			$table->id();
			$table->string( 'email' )->unique();
			$table->timestamps();
		} );
	}

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

it( 'persists a chosen cadence to the preferences table', function (): void {
	$user = new User();
	$user->forceFill( [ 'id' => 42, 'email' => 'a@b.test' ] )->save();

	$this->actingAs( $user );

	Livewire::test( DigestSubscription::class )
		->set( 'cadence', 'weekly' )
		->call( 'save' )
		->assertSet( 'status', __( 'Digest preference saved.' ) );

	$preference = AnalyticsDigestPreference::query()->where( 'user_id', 42 )->first();

	expect( $preference )->not->toBeNull();
	expect( $preference->cadence )->toBe( 'weekly' );
} );

it( 'renders the disabled state when the feature toggle is off', function (): void {
	app( ArtisanPackUI\Ai\Contracts\FeatureRegistry::class )->disable( 'analytics.digest_email' );

	Livewire::test( DigestSubscription::class )
		->assertSee( 'AI digest emails are currently disabled.' );
} );
