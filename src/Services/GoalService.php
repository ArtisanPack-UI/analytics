<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Services;

use ArtisanPackUI\Analytics\Models\Goal;
use Illuminate\Support\Collection;

/**
 * Goal management service.
 *
 * Provides CRUD operations for analytics goals and integrates
 * with the GoalMatcher for conversion tracking.
 *
 * @since 1.0.0
 */
class GoalService
{
	/**
	 * The GoalMatcher instance.
	 */
	protected GoalMatcher $matcher;

	/**
	 * Create a new GoalService instance.
	 *
	 * @param GoalMatcher $matcher The goal matcher service.
	 *
	 * @since 1.0.0
	 */
	public function __construct( GoalMatcher $matcher )
	{
		$this->matcher = $matcher;
	}

	/**
	 * Create a new goal.
	 *
	 * @param array<string, mixed> $attributes The goal attributes.
	 *
	 * @return Goal The created goal.
	 *
	 * @since 1.0.0
	 */
	public function create( array $attributes ): Goal
	{
		return Goal::create( $attributes );
	}

	/**
	 * Update an existing goal.
	 *
	 * @param Goal|int             $goal       The goal or goal ID.
	 * @param array<string, mixed> $attributes The attributes to update.
	 *
	 * @return Goal|null The updated goal, or null if not found.
	 *
	 * @since 1.0.0
	 */
	public function update( int|Goal $goal, array $attributes ): ?Goal
	{
		$goal = $goal instanceof Goal ? $goal : Goal::find( $goal );

		if ( null === $goal ) {
			return null;
		}

		$goal->update( $attributes );

		return $goal->fresh();
	}

	/**
	 * Delete a goal.
	 *
	 * @param Goal|int $goal The goal or goal ID.
	 *
	 * @return bool True if deleted, false otherwise.
	 *
	 * @since 1.0.0
	 */
	public function delete( int|Goal $goal ): bool
	{
		$goal = $goal instanceof Goal ? $goal : Goal::find( $goal );

		if ( null === $goal ) {
			return false;
		}

		return (bool) $goal->delete();
	}

	/**
	 * Get all goals.
	 *
	 * @param int|null        $siteId   Filter by site ID.
	 * @param int|string|null $tenantId Filter by tenant ID.
	 *
	 * @return Collection<int, Goal>
	 *
	 * @since 1.0.0
	 */
	public function all( ?int $siteId = null, string|int|null $tenantId = null ): Collection
	{
		$query = Goal::query();

		if ( null !== $siteId ) {
			$query->where( function ( $q ) use ( $siteId ): void {
				$q->where( 'site_id', $siteId )
					->orWhereNull( 'site_id' );
			} );
		}

		if ( null !== $tenantId && config( 'artisanpack.analytics.multi_tenant.enabled', false ) ) {
			$query->where( function ( $q ) use ( $tenantId ): void {
				$q->where( 'tenant_id', $tenantId )
					->orWhereNull( 'tenant_id' );
			} );
		}

		return $query->get();
	}

	/**
	 * Get active goals.
	 *
	 * @param string|null     $type     Filter by goal type.
	 * @param int|null        $siteId   Filter by site ID.
	 * @param int|string|null $tenantId Filter by tenant ID.
	 *
	 * @return Collection<int, Goal>
	 *
	 * @since 1.0.0
	 */
	public function active( ?string $type = null, ?int $siteId = null, string|int|null $tenantId = null ): Collection
	{
		$query = Goal::query()->where( 'is_active', true );

		if ( null !== $type ) {
			$query->where( 'type', $type );
		}

		if ( null !== $siteId ) {
			$query->where( function ( $q ) use ( $siteId ): void {
				$q->where( 'site_id', $siteId )
					->orWhereNull( 'site_id' );
			} );
		}

		if ( null !== $tenantId && config( 'artisanpack.analytics.multi_tenant.enabled', false ) ) {
			$query->where( function ( $q ) use ( $tenantId ): void {
				$q->where( 'tenant_id', $tenantId )
					->orWhereNull( 'tenant_id' );
			} );
		}

		return $query->get();
	}

	/**
	 * Find a goal by ID.
	 *
	 * @param int $id The goal ID.
	 *
	 * @return Goal|null The goal, or null if not found.
	 *
	 * @since 1.0.0
	 */
	public function find( int $id ): ?Goal
	{
		return Goal::find( $id );
	}

	/**
	 * Find a goal by name.
	 *
	 * @param string          $name     The goal name.
	 * @param int|null        $siteId   Filter by site ID.
	 * @param int|string|null $tenantId Filter by tenant ID.
	 *
	 * @return Goal|null The goal, or null if not found.
	 *
	 * @since 1.0.0
	 */
	public function findByName( string $name, ?int $siteId = null, string|int|null $tenantId = null ): ?Goal
	{
		$query = Goal::query()->where( 'name', $name );

		if ( null !== $siteId ) {
			$query->where( function ( $q ) use ( $siteId ): void {
				$q->where( 'site_id', $siteId )
					->orWhereNull( 'site_id' );
			} );
		}

		if ( null !== $tenantId && config( 'artisanpack.analytics.multi_tenant.enabled', false ) ) {
			$query->where( function ( $q ) use ( $tenantId ): void {
				$q->where( 'tenant_id', $tenantId )
					->orWhereNull( 'tenant_id' );
			} );
		}

		return $query->first();
	}

	/**
	 * Activate a goal.
	 *
	 * @param Goal|int $goal The goal or goal ID.
	 *
	 * @return bool True if activated, false otherwise.
	 *
	 * @since 1.0.0
	 */
	public function activate( int|Goal $goal ): bool
	{
		$goal = $goal instanceof Goal ? $goal : Goal::find( $goal );

		if ( null === $goal ) {
			return false;
		}

		return $goal->update( [ 'is_active' => true ] );
	}

	/**
	 * Deactivate a goal.
	 *
	 * @param Goal|int $goal The goal or goal ID.
	 *
	 * @return bool True if deactivated, false otherwise.
	 *
	 * @since 1.0.0
	 */
	public function deactivate( int|Goal $goal ): bool
	{
		$goal = $goal instanceof Goal ? $goal : Goal::find( $goal );

		if ( null === $goal ) {
			return false;
		}

		return $goal->update( [ 'is_active' => false ] );
	}

	/**
	 * Get the GoalMatcher instance.
	 *
	 * @return GoalMatcher
	 *
	 * @since 1.0.0
	 */
	public function matcher(): GoalMatcher
	{
		return $this->matcher;
	}
}
