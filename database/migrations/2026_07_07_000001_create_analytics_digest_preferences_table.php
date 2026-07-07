<?php

declare( strict_types=1 );

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create the analytics_digest_preferences table.
 *
 * Stores per-user opt-in preferences for the AI analytics digest email.
 * `cadence` is one of `off`, `weekly`, `monthly`. Users default to `off`
 * until they explicitly subscribe from the Livewire/React/Vue component.
 *
 * @since 1.3.0
 */
return new class extends Migration
{
	/**
	 * Run the migrations.
	 */
	public function up(): void
	{
		Schema::create( 'analytics_digest_preferences', function ( Blueprint $table ) {
			$table->id();
			$table->unsignedBigInteger( 'user_id' );
			$table->string( 'cadence', 16 )->default( 'off' );
			$table->timestamp( 'last_sent_at' )->nullable();
			$table->timestamps();

			$table->unique( 'user_id' );
			$table->index( 'cadence' );
		} );
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists( 'analytics_digest_preferences' );
	}
};
