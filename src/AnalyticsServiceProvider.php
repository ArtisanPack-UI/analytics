<?php

namespace ArtisanPackUI\Analytics;

use Illuminate\Support\ServiceProvider;

class AnalyticsServiceProvider extends ServiceProvider
{

	public function register(): void
	{
		$this->app->singleton( 'analytics', function ( $app ) {
			return new Analytics();
		} );
	}
}
