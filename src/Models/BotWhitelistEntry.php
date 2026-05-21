<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Bot whitelist entry model.
 *
 * Represents a user agent pattern or IP address that bypasses bot scoring.
 * Entries are managed at runtime via the analytics:whitelist command and
 * supplement the static config whitelist consumed by the BotDetector.
 *
 * @property int    $id
 * @property string $type
 * @property string $value
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @method static Builder userAgents()
 * @method static Builder ips()
 *
 * @since   1.2.0
 *
 * @package ArtisanPackUI\Analytics\Models
 */
class BotWhitelistEntry extends Model
{
	/**
	 * Whitelist entry type for user agent patterns.
	 *
	 * @var string
	 */
	public const TYPE_USER_AGENT = 'user_agent';

	/**
	 * Whitelist entry type for IP addresses.
	 *
	 * @var string
	 */
	public const TYPE_IP = 'ip';

	/**
	 * The table associated with the model.
	 *
	 * @var string
	 */
	protected $table = 'analytics_bot_whitelist';

	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array<int, string>
	 */
	protected $fillable = [
		'type',
		'value',
	];

	/**
	 * Scope the query to user agent entries.
	 *
	 * @param Builder $query The query builder.
	 *
	 * @return Builder
	 *
	 * @since 1.2.0
	 */
	public function scopeUserAgents( Builder $query ): Builder
	{
		return $query->where( 'type', self::TYPE_USER_AGENT );
	}

	/**
	 * Scope the query to IP address entries.
	 *
	 * @param Builder $query The query builder.
	 *
	 * @return Builder
	 *
	 * @since 1.2.0
	 */
	public function scopeIps( Builder $query ): Builder
	{
		return $query->where( 'type', self::TYPE_IP );
	}
}
