<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Goal model for analytics.
 *
 * Defines conversion goals and their matching conditions.
 *
 * @property int $id
 * @property int|null $site_id
 * @property string $name
 * @property string|null $description
 * @property string $type
 * @property array $conditions
 * @property string $value_type
 * @property float|null $fixed_value
 * @property string|null $dynamic_value_path
 * @property bool $is_active
 * @property array|null $funnel_steps
 * @property int|string|null $tenant_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @method static Builder active()
 * @method static Builder ofType(string $type)
 * @method static Builder forSite(int $siteId)
 * @method static Builder forTenant(string|int $tenantId)
 *
 * @since   1.0.0
 */
class Goal extends Model
{
    use HasFactory;

    /**
     * Goal type constants.
     */
    public const TYPE_EVENT = 'event';

    public const TYPE_PAGEVIEW = 'pageview';

    public const TYPE_DURATION = 'duration';

    public const TYPE_PAGES_PER_SESSION = 'pages_per_session';

    /**
     * Value type constants.
     */
    public const VALUE_TYPE_NONE = 'none';

    public const VALUE_TYPE_FIXED = 'fixed';

    public const VALUE_TYPE_DYNAMIC = 'dynamic';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'analytics_goals';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'site_id',
        'name',
        'description',
        'type',
        'conditions',
        'value_type',
        'fixed_value',
        'dynamic_value_path',
        'is_active',
        'funnel_steps',
        'tenant_id',
    ];

    /**
     * Get the site that this goal belongs to.
     *
     * @return BelongsTo<Site, Goal>
     *
     * @since 1.0.0
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo( Site::class );
    }

    /**
     * Get the conversions for this goal.
     *
     * @return HasMany<Conversion, Goal>
     *
     * @since 1.0.0
     */
    public function conversions(): HasMany
    {
        return $this->hasMany( Conversion::class );
    }

    /**
     * Get the connection name for the model.
     */
    public function getConnectionName(): ?string
    {
        return config( 'artisanpack.analytics.local.connection' );
    }

    /**
     * Scope a query to filter by site.
     *
     * @param  Builder  $query  The query builder.
     * @param  int  $siteId  The site ID.
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
     * @param  Builder  $query  The query builder.
     * @param  int|string  $tenantId  The tenant ID.
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
     * Scope a query to get active goals.
     *
     * @param  Builder  $query  The query builder.
     *
     * @since 1.0.0
     */
    public function scopeActive( Builder $query ): Builder
    {
        return $query->where( 'is_active', true );
    }

    /**
     * Scope a query to filter by goal type.
     *
     * @param  Builder  $query  The query builder.
     * @param  string  $type  The goal type.
     *
     * @since 1.0.0
     */
    public function scopeOfType( Builder $query, string $type ): Builder
    {
        return $query->where( 'type', $type );
    }

    /**
     * Check if the goal matches the given subject.
     *
     * @param  Event|PageView|Session  $subject  The subject to match against.
     *
     * @since 1.0.0
     */
    public function matches( Event|PageView|Session $subject ): bool
    {
        return match ( $this->type ) {
            self::TYPE_EVENT             => $subject instanceof Event && $this->matchesEvent( $subject ),
            self::TYPE_PAGEVIEW          => $subject instanceof PageView && $this->matchesPageView( $subject ),
            self::TYPE_DURATION          => $subject instanceof Session && $this->matchesDuration( $subject ),
            self::TYPE_PAGES_PER_SESSION => $subject instanceof Session && $this->matchesPagesPerSession( $subject ),
            default                      => false,
        };
    }

    /**
     * Calculate the value for a conversion.
     *
     * @param  Event|PageView|Session  $subject  The subject of the conversion.
     *
     * @since 1.0.0
     */
    public function calculateValue( Event|PageView|Session $subject ): ?float
    {
        return match ( $this->value_type ) {
            self::VALUE_TYPE_FIXED   => $this->fixed_value,
            self::VALUE_TYPE_DYNAMIC => $this->extractDynamicValue( $subject ),
            default                  => null,
        };
    }

    /**
     * Check if the goal matches an event.
     *
     * @param  Event  $event  The event to match.
     *
     * @since 1.0.0
     */
    protected function matchesEvent( Event $event ): bool
    {
        $conditions = $this->conditions;

        // Check event name
        if ( isset( $conditions['event_name'] ) && $event->name !== $conditions['event_name'] ) {
            return false;
        }

        // Check event category
        if ( isset( $conditions['event_category'] ) && $event->category !== $conditions['event_category'] ) {
            return false;
        }

        // Check property matches with operator support
        if ( isset( $conditions['property_matches'] ) && is_array( $conditions['property_matches'] ) ) {
            foreach ( $conditions['property_matches'] as $key => $condition ) {
                $eventValue = data_get( $event->properties, $key );

                // If condition is an array with operator, use operator matching
                if ( is_array( $condition ) && ! empty( $condition ) ) {
                    if ( ! $this->matchesOperator( $eventValue, $condition ) ) {
                        return false;
                    }
                } else {
                    // Exact match - use strict comparison to avoid PHP type juggling issues
                    if ( $eventValue !== $condition ) {
                        return false;
                    }
                }
            }
        }

        // Check minimum value
        if ( isset( $conditions['min_value'] ) ) {
            if ( ( $event->value ?? 0 ) < $conditions['min_value'] ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Match a value against an operator condition.
     *
     * Supports operators: equals, not_equals, greater_than, less_than,
     * greater_or_equal, less_or_equal, contains, starts_with, ends_with,
     * in, not_in, regex, and their shorthand versions.
     *
     * @param  mixed  $value  The value to check.
     * @param  array<string, mixed>  $operator  The operator condition.
     *
     * @since 1.0.0
     */
    protected function matchesOperator( mixed $value, array $operator ): bool
    {
        $op     = array_key_first( $operator );
        $target = $operator[ $op ];

        return match ( $op ) {
            'equals', 'eq' => $value === $target,
            'not_equals', 'neq', 'ne' => $value !== $target,
            'greater_than', 'gt' => is_numeric( $value ) && $value > $target,
            'less_than', 'lt' => is_numeric( $value ) && $value < $target,
            'greater_or_equal', 'gte' => is_numeric( $value ) && $value >= $target,
            'less_or_equal', 'lte' => is_numeric( $value ) && $value <= $target,
            'contains'     => is_string( $value ) && str_contains( $value, (string) $target ),
            'not_contains' => is_string( $value ) && ! str_contains( $value, (string) $target ),
            'starts_with'  => is_string( $value ) && str_starts_with( $value, (string) $target ),
            'ends_with'    => is_string( $value ) && str_ends_with( $value, (string) $target ),
            'in'           => in_array( $value, (array) $target, true ),
            'not_in'       => ! in_array( $value, (array) $target, true ),
            'regex'        => $this->matchesRegex( $value, $target ),
            'is_null'      => null === $value,
            'is_not_null', 'not_null' => null !== $value,
            'is_empty' => empty( $value ),
            'is_not_empty', 'not_empty' => ! empty( $value ),
            default => false,
        };
    }

    /**
     * Check if a value matches a regex pattern.
     *
     * @param  mixed  $value  The value to check.
     * @param  mixed  $pattern  The regex pattern.
     *
     * @since 1.0.0
     */
    protected function matchesRegex( mixed $value, mixed $pattern ): bool
    {
        if ( ! is_string( $value ) || ! is_string( $pattern ) ) {
            return false;
        }

        $result = preg_match( $pattern, $value );

        // preg_match returns false on error, 0 on no match, 1 on match
        if ( false === $result ) {
            Log::warning( __( 'Invalid regex pattern in goal condition: :pattern', [
                'pattern' => $pattern,
            ] ) );

            return false;
        }

        return 1 === $result;
    }

    /**
     * Check if the goal matches a page view.
     *
     * @param  PageView  $pageView  The page view to match.
     *
     * @since 1.0.0
     */
    protected function matchesPageView( PageView $pageView ): bool
    {
        $conditions = $this->conditions;

        // Exact path match
        if ( isset( $conditions['path_exact'] ) ) {
            return $pageView->path === $conditions['path_exact'];
        }

        // Pattern match (wildcards using Str::is)
        if ( isset( $conditions['path_pattern'] ) ) {
            return Str::is( $conditions['path_pattern'], $pageView->path );
        }

        // Regex match
        if ( isset( $conditions['path_regex'] ) ) {
            return 1 === preg_match( $conditions['path_regex'], $pageView->path );
        }

        // Contains match
        if ( isset( $conditions['path_contains'] ) ) {
            return str_contains( $pageView->path, $conditions['path_contains'] );
        }

        // Starts with match
        if ( isset( $conditions['path_starts_with'] ) ) {
            return str_starts_with( $pageView->path, $conditions['path_starts_with'] );
        }

        // Ends with match
        if ( isset( $conditions['path_ends_with'] ) ) {
            return str_ends_with( $pageView->path, $conditions['path_ends_with'] );
        }

        return false;
    }

    /**
     * Check if the goal matches a session duration.
     *
     * @param  Session  $session  The session to match.
     *
     * @since 1.0.0
     */
    protected function matchesDuration( Session $session ): bool
    {
        return $session->duration >= ( $this->conditions['min_seconds'] ?? 0 );
    }

    /**
     * Check if the goal matches pages per session.
     *
     * @param  Session  $session  The session to match.
     *
     * @since 1.0.0
     */
    protected function matchesPagesPerSession( Session $session ): bool
    {
        return $session->page_count >= ( $this->conditions['min_pages'] ?? 0 );
    }

    /**
     * Extract dynamic value from the subject.
     *
     * @param  Event|PageView|Session  $subject  The subject.
     *
     * @since 1.0.0
     */
    protected function extractDynamicValue( Event|PageView|Session $subject ): ?float
    {
        if ( ! $this->dynamic_value_path || ! $subject instanceof Event ) {
            return null;
        }

        $value = data_get( $subject->properties, $this->dynamic_value_path);

        return is_numeric( $value) ? (float) $value : null;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'conditions'   => 'array',
            'funnel_steps' => 'array',
            'is_active'    => 'boolean',
            'fixed_value'  => 'float',
        ];
    }
}
