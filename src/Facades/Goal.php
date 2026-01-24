<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Facades;

use ArtisanPackUI\Analytics\Models\Goal as GoalModel;
use ArtisanPackUI\Analytics\Services\GoalMatcher;
use ArtisanPackUI\Analytics\Services\GoalService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;

/**
 * Goal facade.
 *
 * Provides a static interface to the GoalService for
 * managing analytics goals and conversions.
 *
 * @method static GoalModel create(array $attributes)
 * @method static GoalModel|null update(int|GoalModel $goal, array $attributes)
 * @method static bool delete(int|GoalModel $goal)
 * @method static Collection all(?int $siteId = null, string|int|null $tenantId = null)
 * @method static Collection active(?string $type = null, ?int $siteId = null, string|int|null $tenantId = null)
 * @method static GoalModel|null find(int $id)
 * @method static GoalModel|null findByName(string $name, ?int $siteId = null, string|int|null $tenantId = null)
 * @method static bool activate(int|GoalModel $goal)
 * @method static bool deactivate(int|GoalModel $goal)
 * @method static GoalMatcher matcher()
 *
 * @see GoalService
 * @since   1.0.0
 */
class Goal extends Facade
{
	/**
	 * Get the registered name of the component.
	 *
	 * @return string
	 *
	 * @since 1.0.0
	 */
	protected static function getFacadeAccessor(): string
	{
		return GoalService::class;
	}
}
