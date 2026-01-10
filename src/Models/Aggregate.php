<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Aggregate model for analytics.
 *
 * Pre-computed aggregates for fast dashboard queries.
 *
 * @property int             $id
 * @property int|null        $site_id
 * @property Carbon          $date
 * @property string          $period
 * @property int|null        $hour
 * @property string          $metric
 * @property string|null     $dimension
 * @property string|null     $dimension_value
 * @property int             $value
 * @property float|null      $value_sum
 * @property float|null      $value_avg
 * @property float|null      $value_min
 * @property float|null      $value_max
 * @property int|string|null $tenant_id
 * @property Carbon          $created_at
 * @property Carbon          $updated_at
 *
 * @method static Builder forMetric(string $metric)
 * @method static Builder forPeriod(string $period)
 * @method static Builder forDateRange(Carbon $start, Carbon $end)
 * @method static Builder forDimension(string $dimension, ?string $value = null)
 * @method static Builder noDimension()
 * @method static Builder forSite(int $siteId)
 * @method static Builder forTenant(string|int $tenantId)
 *
 * @since   1.0.0
 *
 * @package ArtisanPackUI\Analytics\Models
 */
class Aggregate extends Model
{
	use HasFactory;

	/**
	 * Period constants.
	 */
	public const PERIOD_HOUR  = 'hour';
	public const PERIOD_DAY   = 'day';
	public const PERIOD_WEEK  = 'week';
	public const PERIOD_MONTH = 'month';

	/**
	 * Metric constants.
	 */
	public const METRIC_PAGEVIEWS        = 'pageviews';
	public const METRIC_VISITORS         = 'visitors';
	public const METRIC_SESSIONS         = 'sessions';
	public const METRIC_BOUNCE_RATE      = 'bounce_rate';
	public const METRIC_AVG_DURATION     = 'avg_duration';
	public const METRIC_AVG_PAGES        = 'avg_pages';
	public const METRIC_EVENTS           = 'events';
	public const METRIC_CONVERSIONS      = 'conversions';
	public const METRIC_CONVERSION_VALUE = 'conversion_value';

	/**
	 * Dimension constants.
	 */
	public const DIMENSION_PATH           = 'path';
	public const DIMENSION_COUNTRY        = 'country';
	public const DIMENSION_DEVICE_TYPE    = 'device_type';
	public const DIMENSION_BROWSER        = 'browser';
	public const DIMENSION_REFERRER_TYPE  = 'referrer_type';
	public const DIMENSION_UTM_SOURCE     = 'utm_source';
	public const DIMENSION_EVENT_NAME     = 'event_name';
	public const DIMENSION_EVENT_CATEGORY = 'event_category';
	public const DIMENSION_GOAL_ID        = 'goal_id';

	/**
	 * The table associated with the model.
	 *
	 * @var string
	 */
	protected $table = 'analytics_aggregates';

	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array<int, string>
	 */
	protected $fillable = [
		'site_id',
		'date',
		'period',
		'hour',
		'metric',
		'dimension',
		'dimension_value',
		'value',
		'value_sum',
		'value_avg',
		'value_min',
		'value_max',
		'tenant_id',
	];

	/**
	 * Get the site that this aggregate belongs to.
	 *
	 * @return BelongsTo<Site, Aggregate>
	 *
	 * @since 1.0.0
	 */
	public function site(): BelongsTo
	{
		return $this->belongsTo( Site::class );
	}

	/**
	 * Get the connection name for the model.
	 *
	 * @return string|null
	 */
	public function getConnectionName(): ?string
	{
		return config( 'artisanpack.analytics.local.connection' );
	}

	/**
	 * Scope a query to filter by site.
	 *
	 * @param Builder $query  The query builder.
	 * @param int     $siteId The site ID.
	 *
	 * @return Builder
	 *
	 * @since 1.0.0
	 */
	public function scopeForSite( Builder $query, int $siteId ): Builder
	{
		return $query->where( 'site_id', $siteId );
	}

	/**
	 * Scope a query to filter by tenant.
	 *
	 * @param Builder    $query    The query builder.
	 * @param int|string $tenantId The tenant ID.
	 *
	 * @return Builder
	 *
	 * @since 1.0.0
	 */
	public function scopeForTenant( Builder $query, string|int $tenantId ): Builder
	{
		if ( config( 'artisanpack.analytics.multi_tenant.enabled', false ) ) {
			return $query->where( 'tenant_id', $tenantId );
		}

		return $query;
	}

	/**
	 * Scope a query to filter by metric.
	 *
	 * @param Builder $query  The query builder.
	 * @param string  $metric The metric to filter by.
	 *
	 * @return Builder
	 *
	 * @since 1.0.0
	 */
	public function scopeForMetric( Builder $query, string $metric ): Builder
	{
		return $query->where( 'metric', $metric );
	}

	/**
	 * Scope a query to filter by period.
	 *
	 * @param Builder $query  The query builder.
	 * @param string  $period The period to filter by.
	 *
	 * @return Builder
	 *
	 * @since 1.0.0
	 */
	public function scopeForPeriod( Builder $query, string $period ): Builder
	{
		return $query->where( 'period', $period );
	}

	/**
	 * Scope a query to filter by date range.
	 *
	 * @param Builder $query The query builder.
	 * @param Carbon  $start The start date.
	 * @param Carbon  $end   The end date.
	 *
	 * @return Builder
	 *
	 * @since 1.0.0
	 */
	public function scopeForDateRange( Builder $query, Carbon $start, Carbon $end ): Builder
	{
		return $query->whereBetween( 'date', [ $start, $end ] );
	}

	/**
	 * Scope a query to filter by dimension.
	 *
	 * @param Builder     $query     The query builder.
	 * @param string      $dimension The dimension to filter by.
	 * @param string|null $value     Optional dimension value.
	 *
	 * @return Builder
	 *
	 * @since 1.0.0
	 */
	public function scopeForDimension( Builder $query, string $dimension, ?string $value = null ): Builder
	{
		$query->where( 'dimension', $dimension );

		if ( null !== $value ) {
			$query->where( 'dimension_value', $value );
		}

		return $query;
	}

	/**
	 * Scope a query to get aggregates without dimension.
	 *
	 * @param Builder $query The query builder.
	 *
	 * @return Builder
	 *
	 * @since 1.0.0
	 */
	public function scopeNoDimension( Builder $query ): Builder
	{
		return $query->whereNull( 'dimension' );
	}

	/**
	 * Get the attributes that should be cast.
	 *
	 * @return array<string, string>
	 */
	protected function casts(): array
	{
		return [
			'date'      => 'date',
			'hour'      => 'integer',
			'value'     => 'integer',
			'value_sum' => 'decimal:4',
			'value_avg' => 'decimal:4',
			'value_min' => 'decimal:4',
			'value_max' => 'decimal:4',
		];
	}
}
