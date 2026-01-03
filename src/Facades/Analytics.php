<?php

namespace ArtisanPackUI\Analytics\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \ArtisanPackUI\Analytics\A11y
 */
class Analytics extends Facade
{
	/**
	 * Get the registered name of the component.
	 *
	 * @return string
	 */
	protected static function getFacadeAccessor()
	{
		return 'analytics';
	}
}
