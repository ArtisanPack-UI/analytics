<?php

/**
 * AnalyticsDigestPreference model.
 *
 * @package    ArtisanPack_UI
 * @subpackage Analytics
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.3.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-user opt-in preference for the AI analytics digest email.
 *
 * @property int         $id
 * @property int         $user_id
 * @property string      $cadence
 * @property string|null $last_sent_at
 * @property string|null $created_at
 * @property string|null $updated_at
 *
 * @package    ArtisanPack_UI
 * @subpackage Analytics
 *
 * @since      1.3.0
 */
class AnalyticsDigestPreference extends Model
{
	public const CADENCE_OFF     = 'off';

	public const CADENCE_WEEKLY  = 'weekly';

	public const CADENCE_MONTHLY = 'monthly';

	/**
	 * Cadence values that should trigger a scheduled send.
	 *
	 * @var array<int, string>
	 */
	public const ACTIVE_CADENCES = [
		self::CADENCE_WEEKLY,
		self::CADENCE_MONTHLY,
	];

	/**
	 * {@inheritDoc}
	 */
	protected $table = 'analytics_digest_preferences';

	/**
	 * {@inheritDoc}
	 *
	 * @var array<int, string>
	 */
	protected $fillable = [
		'user_id',
		'cadence',
		'last_sent_at',
	];

	/**
	 * Whether this preference is opted in to receive digests.
	 *
	 * @since 1.3.0
	 *
	 * @return bool
	 */
	public function isOptedIn(): bool
	{
		return in_array( $this->cadence, self::ACTIVE_CADENCES, true );
	}

	/**
	 * Related user record.
	 *
	 * Resolves the application's configured `auth.providers.users.model`
	 * so this package doesn't hard-code an `App\Models\User` reference.
	 *
	 * @since 1.3.0
	 *
	 * @return BelongsTo<Model, self>
	 */
	public function user(): BelongsTo
	{
		/** @var class-string<Model> $userModel */
		$userModel = config( 'auth.providers.users.model', 'App\\Models\\User' );

		return $this->belongsTo( $userModel, 'user_id' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return array<string, string>
	 */
	protected function casts(): array
	{
		return [
			'user_id'      => 'integer',
			'last_sent_at' => 'datetime',
		];
	}
}
