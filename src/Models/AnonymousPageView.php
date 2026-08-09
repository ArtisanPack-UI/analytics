<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Models;

use ArtisanPackUI\Analytics\Traits\BelongsToSite;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A page view recorded before analytics consent was granted.
 *
 * Written only when `privacy.anonymous_mode` is enabled. Carries no visitor,
 * session, fingerprint, IP or user agent — there is deliberately nothing here
 * that could tie two rows to the same person, so these rows support counting
 * and nothing else.
 *
 * Because they are a different kind of record, they live in their own table.
 * Nothing that counts visitors, sessions or returning visitors should ever
 * read from here, and keeping the rows out of `analytics_page_views` means
 * those queries stay correct without having to know this feature exists.
 *
 * @property int         $id
 * @property int|null    $site_id
 * @property string      $path
 * @property string|null $title
 * @property string|null $referrer_host
 * @property string|null $device_type
 * @property string|null $tenant_id
 * @property Carbon      $created_at
 *
 * @method static Builder forPath(string $path)
 * @method static Builder forSite(int $siteId)
 * @method static Builder betweenDates(Carbon $start, Carbon $end)
 *
 * @since   1.5.0
 *
 * @package ArtisanPackUI\Analytics\Models
 */
class AnonymousPageView extends Model
{
	use BelongsToSite;
	use HasFactory;

	/**
	 * These rows are written once and never updated, so there is no
	 * `updated_at` to maintain.
	 *
	 * @var bool
	 */
	public $timestamps = false;

	/**
	 * @var string
	 */
	protected $table = 'analytics_anonymous_page_views';

	/**
	 * @var list<string>
	 */
	protected $fillable = [
		'site_id',
		'path',
		'title',
		'referrer_host',
		'device_type',
		'tenant_id',
		'created_at',
	];

	/**
	 * Scope to a specific path.
	 *
	 * @param Builder $query The query builder.
	 * @param string  $path  The path to filter by.
	 *
	 * @return Builder
	 *
	 * @since 1.5.0
	 */
	public function scopeForPath( Builder $query, string $path ): Builder
	{
		return $query->where( 'path', $path );
	}

	/**
	 * Scope to a date range.
	 *
	 * @param Builder $query The query builder.
	 * @param Carbon  $start The start of the range.
	 * @param Carbon  $end   The end of the range.
	 *
	 * @return Builder
	 *
	 * @since 1.5.0
	 */
	public function scopeBetweenDates( Builder $query, Carbon $start, Carbon $end ): Builder
	{
		return $query->whereBetween( 'created_at', [ $start, $end ] );
	}

	/**
	 * @return array<string, string>
	 */
	protected function casts(): array
	{
		return [
			'created_at' => 'datetime',
		];
	}
}
