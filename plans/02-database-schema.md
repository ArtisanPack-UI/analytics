# Database Schema

**Purpose:** Define all database tables, relationships, and indexes for the analytics package
**Last Updated:** January 3, 2026

---

## Overview

The analytics package uses a normalized database schema optimized for both write performance (high-volume inserts) and read performance (dashboard queries). The schema supports:

- Multi-tenant data isolation via `site_id`
- Privacy-first design with anonymization options
- Efficient aggregation for dashboard queries
- Flexible event and goal tracking

---

## Entity Relationship Diagram

```
┌─────────────────┐       ┌─────────────────┐       ┌─────────────────┐
│    visitors     │       │    sessions     │       │   page_views    │
├─────────────────┤       ├─────────────────┤       ├─────────────────┤
│ id              │──┐    │ id              │──┐    │ id              │
│ site_id         │  │    │ site_id         │  │    │ site_id         │
│ fingerprint     │  │    │ visitor_id      │◀─┘    │ session_id      │◀─┘
│ first_seen_at   │  │    │ session_id      │       │ visitor_id      │
│ last_seen_at    │  └───▶│ started_at      │       │ path            │
│ ip_address      │       │ ended_at        │       │ title           │
│ user_agent      │       │ duration        │       │ referrer        │
│ country         │       │ entry_page      │       │ time_on_page    │
│ device_type     │       │ exit_page       │       │ created_at      │
│ browser         │       │ page_count      │       └────────┬────────┘
│ os              │       │ is_bounce       │                │
│ screen_*        │       │ referrer        │                │
│ language        │       │ utm_*           │                │
└─────────────────┘       └─────────────────┘                │
                                    │                        │
                                    │                        │
┌─────────────────┐                 │                        │
│     events      │                 │                        │
├─────────────────┤                 │                        │
│ id              │                 │                        │
│ site_id         │                 │                        │
│ session_id      │◀────────────────┘                        │
│ visitor_id      │                                          │
│ page_view_id    │◀─────────────────────────────────────────┘
│ name            │
│ category        │       ┌─────────────────┐       ┌─────────────────┐
│ properties      │       │      goals      │       │   conversions   │
│ created_at      │       ├─────────────────┤       ├─────────────────┤
└────────┬────────┘       │ id              │──┐    │ id              │
         │                │ site_id         │  │    │ site_id         │
         │                │ name            │  │    │ goal_id         │◀─┘
         │                │ type            │  │    │ session_id      │
         │                │ conditions      │  │    │ visitor_id      │
         │                │ value           │  │    │ event_id        │
         │                │ is_active       │  │    │ value           │
         │                └─────────────────┘  │    │ created_at      │
         │                                     │    └─────────────────┘
         │                                     │
         └─────────────────────────────────────┘

┌─────────────────┐       ┌─────────────────┐
│    consents     │       │   aggregates    │
├─────────────────┤       ├─────────────────┤
│ id              │       │ id              │
│ site_id         │       │ site_id         │
│ visitor_id      │       │ date            │
│ category        │       │ period          │
│ granted         │       │ metric          │
│ ip_address      │       │ dimension       │
│ granted_at      │       │ dimension_value │
│ revoked_at      │       │ value           │
│ expires_at      │       │ created_at      │
└─────────────────┘       └─────────────────┘
```

---

## Table Definitions

### 1. analytics_visitors

Stores unique visitor profiles based on fingerprinting.

```sql
CREATE TABLE analytics_visitors (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    site_id BIGINT UNSIGNED NULL,
    fingerprint VARCHAR(64) NOT NULL,
    user_id BIGINT UNSIGNED NULL,

    -- First and last activity
    first_seen_at TIMESTAMP NOT NULL,
    last_seen_at TIMESTAMP NOT NULL,

    -- Anonymized/hashed data
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(500) NULL,

    -- Geo data
    country VARCHAR(2) NULL,
    region VARCHAR(100) NULL,
    city VARCHAR(100) NULL,

    -- Device data
    device_type ENUM('desktop', 'tablet', 'mobile', 'other') DEFAULT 'other',
    browser VARCHAR(50) NULL,
    browser_version VARCHAR(20) NULL,
    os VARCHAR(50) NULL,
    os_version VARCHAR(20) NULL,

    -- Screen data
    screen_width SMALLINT UNSIGNED NULL,
    screen_height SMALLINT UNSIGNED NULL,
    viewport_width SMALLINT UNSIGNED NULL,
    viewport_height SMALLINT UNSIGNED NULL,

    -- Preferences
    language VARCHAR(10) NULL,
    timezone VARCHAR(50) NULL,

    -- Aggregates (denormalized for performance)
    total_sessions INT UNSIGNED DEFAULT 0,
    total_pageviews INT UNSIGNED DEFAULT 0,
    total_events INT UNSIGNED DEFAULT 0,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Indexes
    UNIQUE KEY unique_site_fingerprint (site_id, fingerprint),
    INDEX idx_site_last_seen (site_id, last_seen_at),
    INDEX idx_site_first_seen (site_id, first_seen_at),
    INDEX idx_user_id (user_id),
    INDEX idx_country (site_id, country),
    INDEX idx_device_type (site_id, device_type),
    INDEX idx_browser (site_id, browser)
);
```

**Laravel Migration:**

```php
Schema::create('analytics_visitors', function (Blueprint $table) {
    $table->id();
    $table->foreignId('site_id')->nullable()->index();
    $table->string('fingerprint', 64);
    $table->foreignId('user_id')->nullable()->index();

    $table->timestamp('first_seen_at');
    $table->timestamp('last_seen_at');

    $table->string('ip_address', 45)->nullable();
    $table->string('user_agent', 500)->nullable();

    $table->string('country', 2)->nullable();
    $table->string('region', 100)->nullable();
    $table->string('city', 100)->nullable();

    $table->enum('device_type', ['desktop', 'tablet', 'mobile', 'other'])->default('other');
    $table->string('browser', 50)->nullable();
    $table->string('browser_version', 20)->nullable();
    $table->string('os', 50)->nullable();
    $table->string('os_version', 20)->nullable();

    $table->unsignedSmallInteger('screen_width')->nullable();
    $table->unsignedSmallInteger('screen_height')->nullable();
    $table->unsignedSmallInteger('viewport_width')->nullable();
    $table->unsignedSmallInteger('viewport_height')->nullable();

    $table->string('language', 10)->nullable();
    $table->string('timezone', 50)->nullable();

    $table->unsignedInteger('total_sessions')->default(0);
    $table->unsignedInteger('total_pageviews')->default(0);
    $table->unsignedInteger('total_events')->default(0);

    $table->timestamps();

    $table->unique(['site_id', 'fingerprint']);
    $table->index(['site_id', 'last_seen_at']);
    $table->index(['site_id', 'first_seen_at']);
    $table->index(['site_id', 'country']);
    $table->index(['site_id', 'device_type']);
    $table->index(['site_id', 'browser']);
});
```

**Model:**

```php
class Visitor extends Model
{
    protected $table = 'analytics_visitors';

    protected $fillable = [
        'site_id', 'fingerprint', 'user_id',
        'first_seen_at', 'last_seen_at',
        'ip_address', 'user_agent',
        'country', 'region', 'city',
        'device_type', 'browser', 'browser_version', 'os', 'os_version',
        'screen_width', 'screen_height', 'viewport_width', 'viewport_height',
        'language', 'timezone',
        'total_sessions', 'total_pageviews', 'total_events',
    ];

    protected function casts(): array
    {
        return [
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'total_sessions' => 'integer',
            'total_pageviews' => 'integer',
            'total_events' => 'integer',
        ];
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(Session::class);
    }

    public function pageViews(): HasMany
    {
        return $this->hasMany(PageView::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function consents(): HasMany
    {
        return $this->hasMany(Consent::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'));
    }

    // Scopes
    public function scopeForSite(Builder $query, int $siteId): Builder
    {
        return $query->where('site_id', $siteId);
    }

    public function scopeSeenBetween(Builder $query, Carbon $start, Carbon $end): Builder
    {
        return $query->whereBetween('last_seen_at', [$start, $end]);
    }

    public function scopeNewBetween(Builder $query, Carbon $start, Carbon $end): Builder
    {
        return $query->whereBetween('first_seen_at', [$start, $end]);
    }
}
```

---

### 2. analytics_sessions

Tracks visitor sessions with timing and source information.

```sql
CREATE TABLE analytics_sessions (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    site_id BIGINT UNSIGNED NULL,
    visitor_id BIGINT UNSIGNED NOT NULL,
    session_id VARCHAR(36) NOT NULL,

    -- Timing
    started_at TIMESTAMP NOT NULL,
    ended_at TIMESTAMP NULL,
    last_activity_at TIMESTAMP NOT NULL,
    duration INT UNSIGNED DEFAULT 0,

    -- Navigation
    entry_page VARCHAR(2048) NOT NULL,
    exit_page VARCHAR(2048) NULL,
    page_count SMALLINT UNSIGNED DEFAULT 0,
    is_bounce BOOLEAN DEFAULT TRUE,

    -- Source
    referrer VARCHAR(2048) NULL,
    referrer_domain VARCHAR(255) NULL,
    referrer_type ENUM('direct', 'organic', 'social', 'referral', 'email', 'paid', 'other') DEFAULT 'direct',

    -- UTM Parameters
    utm_source VARCHAR(255) NULL,
    utm_medium VARCHAR(255) NULL,
    utm_campaign VARCHAR(255) NULL,
    utm_term VARCHAR(255) NULL,
    utm_content VARCHAR(255) NULL,

    -- Landing page data
    landing_page_title VARCHAR(500) NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Indexes
    UNIQUE KEY unique_session_id (session_id),
    INDEX idx_site_visitor (site_id, visitor_id),
    INDEX idx_site_started (site_id, started_at),
    INDEX idx_site_ended (site_id, ended_at),
    INDEX idx_referrer_type (site_id, referrer_type),
    INDEX idx_utm_source (site_id, utm_source),
    INDEX idx_is_bounce (site_id, is_bounce, started_at),

    FOREIGN KEY (visitor_id) REFERENCES analytics_visitors(id) ON DELETE CASCADE
);
```

**Laravel Migration:**

```php
Schema::create('analytics_sessions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('site_id')->nullable()->index();
    $table->foreignId('visitor_id')->constrained('analytics_visitors')->cascadeOnDelete();
    $table->uuid('session_id')->unique();

    $table->timestamp('started_at');
    $table->timestamp('ended_at')->nullable();
    $table->timestamp('last_activity_at');
    $table->unsignedInteger('duration')->default(0);

    $table->string('entry_page', 2048);
    $table->string('exit_page', 2048)->nullable();
    $table->unsignedSmallInteger('page_count')->default(0);
    $table->boolean('is_bounce')->default(true);

    $table->string('referrer', 2048)->nullable();
    $table->string('referrer_domain', 255)->nullable();
    $table->enum('referrer_type', ['direct', 'organic', 'social', 'referral', 'email', 'paid', 'other'])->default('direct');

    $table->string('utm_source', 255)->nullable();
    $table->string('utm_medium', 255)->nullable();
    $table->string('utm_campaign', 255)->nullable();
    $table->string('utm_term', 255)->nullable();
    $table->string('utm_content', 255)->nullable();

    $table->string('landing_page_title', 500)->nullable();

    $table->timestamps();

    $table->index(['site_id', 'visitor_id']);
    $table->index(['site_id', 'started_at']);
    $table->index(['site_id', 'ended_at']);
    $table->index(['site_id', 'referrer_type']);
    $table->index(['site_id', 'utm_source']);
    $table->index(['site_id', 'is_bounce', 'started_at']);
});
```

**Model:**

```php
class Session extends Model
{
    protected $table = 'analytics_sessions';

    protected $fillable = [
        'site_id', 'visitor_id', 'session_id',
        'started_at', 'ended_at', 'last_activity_at', 'duration',
        'entry_page', 'exit_page', 'page_count', 'is_bounce',
        'referrer', 'referrer_domain', 'referrer_type',
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
        'landing_page_title',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'duration' => 'integer',
            'page_count' => 'integer',
            'is_bounce' => 'boolean',
        ];
    }

    public function visitor(): BelongsTo
    {
        return $this->belongsTo(Visitor::class);
    }

    public function pageViews(): HasMany
    {
        return $this->hasMany(PageView::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function conversions(): HasMany
    {
        return $this->hasMany(Conversion::class);
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('ended_at')
            ->where('last_activity_at', '>=', now()->subMinutes(config('analytics.local.session_lifetime')));
    }

    public function scopeBounced(Builder $query): Builder
    {
        return $query->where('is_bounce', true);
    }

    public function scopeNotBounced(Builder $query): Builder
    {
        return $query->where('is_bounce', false);
    }

    public function scopeFromSource(Builder $query, string $source): Builder
    {
        return $query->where('utm_source', $source);
    }

    // Helpers
    public function isActive(): bool
    {
        return $this->ended_at === null &&
            $this->last_activity_at->gte(now()->subMinutes(config('analytics.local.session_lifetime')));
    }

    public function calculateDuration(): int
    {
        $end = $this->ended_at ?? $this->last_activity_at;
        return $end->diffInSeconds($this->started_at);
    }
}
```

---

### 3. analytics_page_views

Records individual page view events.

```sql
CREATE TABLE analytics_page_views (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    site_id BIGINT UNSIGNED NULL,
    session_id BIGINT UNSIGNED NOT NULL,
    visitor_id BIGINT UNSIGNED NOT NULL,

    -- Page data
    path VARCHAR(2048) NOT NULL,
    title VARCHAR(500) NULL,
    hash VARCHAR(255) NULL,
    query_string VARCHAR(2048) NULL,

    -- Referrer (for internal navigation)
    referrer_path VARCHAR(2048) NULL,

    -- Timing
    time_on_page INT UNSIGNED NULL,
    engaged_time INT UNSIGNED NULL,

    -- Performance metrics
    load_time INT UNSIGNED NULL,
    dom_ready_time INT UNSIGNED NULL,
    first_contentful_paint INT UNSIGNED NULL,

    -- Scroll depth (percentage)
    scroll_depth TINYINT UNSIGNED NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Indexes
    INDEX idx_site_session (site_id, session_id),
    INDEX idx_site_visitor (site_id, visitor_id),
    INDEX idx_site_path (site_id, path(255)),
    INDEX idx_site_created (site_id, created_at),
    INDEX idx_path_created (path(255), created_at),

    FOREIGN KEY (session_id) REFERENCES analytics_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (visitor_id) REFERENCES analytics_visitors(id) ON DELETE CASCADE
);
```

**Laravel Migration:**

```php
Schema::create('analytics_page_views', function (Blueprint $table) {
    $table->id();
    $table->foreignId('site_id')->nullable()->index();
    $table->foreignId('session_id')->constrained('analytics_sessions')->cascadeOnDelete();
    $table->foreignId('visitor_id')->constrained('analytics_visitors')->cascadeOnDelete();

    $table->string('path', 2048);
    $table->string('title', 500)->nullable();
    $table->string('hash', 255)->nullable();
    $table->string('query_string', 2048)->nullable();

    $table->string('referrer_path', 2048)->nullable();

    $table->unsignedInteger('time_on_page')->nullable();
    $table->unsignedInteger('engaged_time')->nullable();

    $table->unsignedInteger('load_time')->nullable();
    $table->unsignedInteger('dom_ready_time')->nullable();
    $table->unsignedInteger('first_contentful_paint')->nullable();

    $table->unsignedTinyInteger('scroll_depth')->nullable();

    $table->timestamp('created_at')->useCurrent();

    $table->index(['site_id', 'session_id']);
    $table->index(['site_id', 'visitor_id']);
    $table->index(['site_id', 'created_at']);
});

// Add full-text index for path searching
DB::statement('CREATE INDEX idx_path ON analytics_page_views (path(255))');
```

**Model:**

```php
class PageView extends Model
{
    protected $table = 'analytics_page_views';

    public $timestamps = false;

    protected $fillable = [
        'site_id', 'session_id', 'visitor_id',
        'path', 'title', 'hash', 'query_string',
        'referrer_path',
        'time_on_page', 'engaged_time',
        'load_time', 'dom_ready_time', 'first_contentful_paint',
        'scroll_depth',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'time_on_page' => 'integer',
            'engaged_time' => 'integer',
            'load_time' => 'integer',
            'dom_ready_time' => 'integer',
            'first_contentful_paint' => 'integer',
            'scroll_depth' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (PageView $pageView) {
            $pageView->created_at = $pageView->created_at ?? now();
        });
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class);
    }

    public function visitor(): BelongsTo
    {
        return $this->belongsTo(Visitor::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    // Scopes
    public function scopeForPath(Builder $query, string $path): Builder
    {
        return $query->where('path', $path);
    }

    public function scopeForPaths(Builder $query, array $paths): Builder
    {
        return $query->whereIn('path', $paths);
    }

    public function scopeWithEngagement(Builder $query): Builder
    {
        return $query->whereNotNull('engaged_time')
            ->where('engaged_time', '>', 0);
    }
}
```

---

### 4. analytics_events

Stores custom events and interactions.

```sql
CREATE TABLE analytics_events (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    site_id BIGINT UNSIGNED NULL,
    session_id BIGINT UNSIGNED NULL,
    visitor_id BIGINT UNSIGNED NULL,
    page_view_id BIGINT UNSIGNED NULL,

    -- Event identification
    name VARCHAR(255) NOT NULL,
    category VARCHAR(100) NULL,
    action VARCHAR(100) NULL,
    label VARCHAR(255) NULL,

    -- Event data
    properties JSON NULL,
    value DECIMAL(15, 4) NULL,

    -- Source tracking
    source_package VARCHAR(100) NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Indexes
    INDEX idx_site_name (site_id, name),
    INDEX idx_site_category (site_id, category),
    INDEX idx_site_session (site_id, session_id),
    INDEX idx_site_created (site_id, created_at),
    INDEX idx_name_created (name, created_at),
    INDEX idx_source_package (site_id, source_package),

    FOREIGN KEY (session_id) REFERENCES analytics_sessions(id) ON DELETE SET NULL,
    FOREIGN KEY (visitor_id) REFERENCES analytics_visitors(id) ON DELETE SET NULL,
    FOREIGN KEY (page_view_id) REFERENCES analytics_page_views(id) ON DELETE SET NULL
);
```

**Laravel Migration:**

```php
Schema::create('analytics_events', function (Blueprint $table) {
    $table->id();
    $table->foreignId('site_id')->nullable()->index();
    $table->foreignId('session_id')->nullable()->constrained('analytics_sessions')->nullOnDelete();
    $table->foreignId('visitor_id')->nullable()->constrained('analytics_visitors')->nullOnDelete();
    $table->foreignId('page_view_id')->nullable()->constrained('analytics_page_views')->nullOnDelete();

    $table->string('name', 255);
    $table->string('category', 100)->nullable();
    $table->string('action', 100)->nullable();
    $table->string('label', 255)->nullable();

    $table->json('properties')->nullable();
    $table->decimal('value', 15, 4)->nullable();

    $table->string('source_package', 100)->nullable();

    $table->timestamp('created_at')->useCurrent();

    $table->index(['site_id', 'name']);
    $table->index(['site_id', 'category']);
    $table->index(['site_id', 'session_id']);
    $table->index(['site_id', 'created_at']);
    $table->index(['name', 'created_at']);
    $table->index(['site_id', 'source_package']);
});
```

**Model:**

```php
class Event extends Model
{
    protected $table = 'analytics_events';

    public $timestamps = false;

    protected $fillable = [
        'site_id', 'session_id', 'visitor_id', 'page_view_id',
        'name', 'category', 'action', 'label',
        'properties', 'value',
        'source_package',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'properties' => 'array',
            'value' => 'decimal:4',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Event $event) {
            $event->created_at = $event->created_at ?? now();
        });
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class);
    }

    public function visitor(): BelongsTo
    {
        return $this->belongsTo(Visitor::class);
    }

    public function pageView(): BelongsTo
    {
        return $this->belongsTo(PageView::class);
    }

    public function conversion(): HasOne
    {
        return $this->hasOne(Conversion::class);
    }

    // Scopes
    public function scopeNamed(Builder $query, string $name): Builder
    {
        return $query->where('name', $name);
    }

    public function scopeInCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    public function scopeFromPackage(Builder $query, string $package): Builder
    {
        return $query->where('source_package', $package);
    }

    // Predefined event scopes
    public function scopeFormSubmissions(Builder $query): Builder
    {
        return $query->where('name', 'form_submitted');
    }

    public function scopePurchases(Builder $query): Builder
    {
        return $query->where('name', 'purchase');
    }

    public function scopeBookings(Builder $query): Builder
    {
        return $query->where('name', 'booking_created');
    }
}
```

---

### 5. analytics_goals

Defines conversion goals and their matching conditions.

```sql
CREATE TABLE analytics_goals (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    site_id BIGINT UNSIGNED NULL,

    -- Goal definition
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,

    -- Goal type
    type ENUM('event', 'pageview', 'duration', 'pages_per_session') NOT NULL,

    -- Matching conditions (JSON for flexibility)
    conditions JSON NOT NULL,

    -- Value assignment
    value_type ENUM('fixed', 'dynamic', 'none') DEFAULT 'none',
    fixed_value DECIMAL(15, 4) NULL,
    dynamic_value_path VARCHAR(255) NULL,

    -- Status
    is_active BOOLEAN DEFAULT TRUE,

    -- Funnel configuration (optional)
    funnel_steps JSON NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Indexes
    INDEX idx_site_active (site_id, is_active),
    INDEX idx_site_type (site_id, type)
);
```

**Conditions JSON Structure:**

```json
// Event goal
{
    "type": "event",
    "event_name": "form_submitted",
    "event_category": "contact",
    "property_matches": {
        "form_name": "Contact Form"
    }
}

// Page view goal
{
    "type": "pageview",
    "path_pattern": "/thank-you*",
    "path_exact": null
}

// Duration goal
{
    "type": "duration",
    "min_seconds": 300
}

// Pages per session goal
{
    "type": "pages_per_session",
    "min_pages": 5
}
```

**Funnel Steps JSON Structure:**

```json
{
    "steps": [
        {"name": "Product View", "type": "pageview", "path_pattern": "/products/*"},
        {"name": "Add to Cart", "type": "event", "event_name": "add_to_cart"},
        {"name": "Checkout Started", "type": "pageview", "path_pattern": "/checkout"},
        {"name": "Purchase", "type": "event", "event_name": "purchase"}
    ]
}
```

**Laravel Migration:**

```php
Schema::create('analytics_goals', function (Blueprint $table) {
    $table->id();
    $table->foreignId('site_id')->nullable()->index();

    $table->string('name', 255);
    $table->text('description')->nullable();

    $table->enum('type', ['event', 'pageview', 'duration', 'pages_per_session']);

    $table->json('conditions');

    $table->enum('value_type', ['fixed', 'dynamic', 'none'])->default('none');
    $table->decimal('fixed_value', 15, 4)->nullable();
    $table->string('dynamic_value_path', 255)->nullable();

    $table->boolean('is_active')->default(true);

    $table->json('funnel_steps')->nullable();

    $table->timestamps();

    $table->index(['site_id', 'is_active']);
    $table->index(['site_id', 'type']);
});
```

**Model:**

```php
class Goal extends Model
{
    protected $table = 'analytics_goals';

    protected $fillable = [
        'site_id', 'name', 'description', 'type',
        'conditions', 'value_type', 'fixed_value', 'dynamic_value_path',
        'is_active', 'funnel_steps',
    ];

    protected function casts(): array
    {
        return [
            'conditions' => 'array',
            'funnel_steps' => 'array',
            'is_active' => 'boolean',
            'fixed_value' => 'decimal:4',
        ];
    }

    public function conversions(): HasMany
    {
        return $this->hasMany(Conversion::class);
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    // Helpers
    public function matches(Event|PageView|Session $subject): bool
    {
        return match($this->type) {
            'event' => $this->matchesEvent($subject),
            'pageview' => $this->matchesPageView($subject),
            'duration' => $this->matchesDuration($subject),
            'pages_per_session' => $this->matchesPagesPerSession($subject),
            default => false,
        };
    }

    protected function matchesEvent(Event $event): bool
    {
        $conditions = $this->conditions;

        if (isset($conditions['event_name']) && $event->name !== $conditions['event_name']) {
            return false;
        }

        if (isset($conditions['event_category']) && $event->category !== $conditions['event_category']) {
            return false;
        }

        if (isset($conditions['property_matches'])) {
            foreach ($conditions['property_matches'] as $key => $value) {
                if (($event->properties[$key] ?? null) !== $value) {
                    return false;
                }
            }
        }

        return true;
    }

    protected function matchesPageView(PageView $pageView): bool
    {
        $conditions = $this->conditions;

        if (isset($conditions['path_exact'])) {
            return $pageView->path === $conditions['path_exact'];
        }

        if (isset($conditions['path_pattern'])) {
            return Str::is($conditions['path_pattern'], $pageView->path);
        }

        return false;
    }

    protected function matchesDuration(Session $session): bool
    {
        return $session->duration >= ($this->conditions['min_seconds'] ?? 0);
    }

    protected function matchesPagesPerSession(Session $session): bool
    {
        return $session->page_count >= ($this->conditions['min_pages'] ?? 0);
    }

    public function calculateValue(Event|PageView|Session $subject): ?float
    {
        return match($this->value_type) {
            'fixed' => $this->fixed_value,
            'dynamic' => $this->extractDynamicValue($subject),
            default => null,
        };
    }

    protected function extractDynamicValue(Event|PageView|Session $subject): ?float
    {
        if (!$this->dynamic_value_path || !$subject instanceof Event) {
            return null;
        }

        return data_get($subject->properties, $this->dynamic_value_path);
    }
}
```

---

### 6. analytics_conversions

Records goal completions.

```sql
CREATE TABLE analytics_conversions (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    site_id BIGINT UNSIGNED NULL,
    goal_id BIGINT UNSIGNED NOT NULL,
    session_id BIGINT UNSIGNED NULL,
    visitor_id BIGINT UNSIGNED NULL,
    event_id BIGINT UNSIGNED NULL,
    page_view_id BIGINT UNSIGNED NULL,

    -- Conversion value
    value DECIMAL(15, 4) NULL,

    -- Metadata
    metadata JSON NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Indexes
    INDEX idx_site_goal (site_id, goal_id),
    INDEX idx_site_created (site_id, created_at),
    INDEX idx_goal_created (goal_id, created_at),
    INDEX idx_session (session_id),
    INDEX idx_visitor (visitor_id),

    FOREIGN KEY (goal_id) REFERENCES analytics_goals(id) ON DELETE CASCADE,
    FOREIGN KEY (session_id) REFERENCES analytics_sessions(id) ON DELETE SET NULL,
    FOREIGN KEY (visitor_id) REFERENCES analytics_visitors(id) ON DELETE SET NULL,
    FOREIGN KEY (event_id) REFERENCES analytics_events(id) ON DELETE SET NULL,
    FOREIGN KEY (page_view_id) REFERENCES analytics_page_views(id) ON DELETE SET NULL
);
```

**Laravel Migration:**

```php
Schema::create('analytics_conversions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('site_id')->nullable()->index();
    $table->foreignId('goal_id')->constrained('analytics_goals')->cascadeOnDelete();
    $table->foreignId('session_id')->nullable()->constrained('analytics_sessions')->nullOnDelete();
    $table->foreignId('visitor_id')->nullable()->constrained('analytics_visitors')->nullOnDelete();
    $table->foreignId('event_id')->nullable()->constrained('analytics_events')->nullOnDelete();
    $table->foreignId('page_view_id')->nullable()->constrained('analytics_page_views')->nullOnDelete();

    $table->decimal('value', 15, 4)->nullable();

    $table->json('metadata')->nullable();

    $table->timestamp('created_at')->useCurrent();

    $table->index(['site_id', 'goal_id']);
    $table->index(['site_id', 'created_at']);
    $table->index(['goal_id', 'created_at']);
});
```

**Model:**

```php
class Conversion extends Model
{
    protected $table = 'analytics_conversions';

    public $timestamps = false;

    protected $fillable = [
        'site_id', 'goal_id', 'session_id', 'visitor_id', 'event_id', 'page_view_id',
        'value', 'metadata', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'value' => 'decimal:4',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Conversion $conversion) {
            $conversion->created_at = $conversion->created_at ?? now();
        });
    }

    public function goal(): BelongsTo
    {
        return $this->belongsTo(Goal::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class);
    }

    public function visitor(): BelongsTo
    {
        return $this->belongsTo(Visitor::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function pageView(): BelongsTo
    {
        return $this->belongsTo(PageView::class);
    }

    // Scopes
    public function scopeForGoal(Builder $query, int $goalId): Builder
    {
        return $query->where('goal_id', $goalId);
    }

    public function scopeWithValue(Builder $query): Builder
    {
        return $query->whereNotNull('value')->where('value', '>', 0);
    }
}
```

---

### 7. analytics_consents

Tracks user consent for analytics tracking.

```sql
CREATE TABLE analytics_consents (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    site_id BIGINT UNSIGNED NULL,
    visitor_id BIGINT UNSIGNED NULL,

    -- Consent details
    category VARCHAR(50) NOT NULL DEFAULT 'analytics',
    granted BOOLEAN NOT NULL,

    -- Tracking
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(500) NULL,

    -- Timestamps
    granted_at TIMESTAMP NULL,
    revoked_at TIMESTAMP NULL,
    expires_at TIMESTAMP NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Indexes
    INDEX idx_site_visitor (site_id, visitor_id),
    INDEX idx_site_category (site_id, category),
    INDEX idx_expires (expires_at),

    FOREIGN KEY (visitor_id) REFERENCES analytics_visitors(id) ON DELETE CASCADE
);
```

**Laravel Migration:**

```php
Schema::create('analytics_consents', function (Blueprint $table) {
    $table->id();
    $table->foreignId('site_id')->nullable()->index();
    $table->foreignId('visitor_id')->nullable()->constrained('analytics_visitors')->cascadeOnDelete();

    $table->string('category', 50)->default('analytics');
    $table->boolean('granted');

    $table->string('ip_address', 45)->nullable();
    $table->string('user_agent', 500)->nullable();

    $table->timestamp('granted_at')->nullable();
    $table->timestamp('revoked_at')->nullable();
    $table->timestamp('expires_at')->nullable();

    $table->timestamps();

    $table->index(['site_id', 'visitor_id']);
    $table->index(['site_id', 'category']);
    $table->index('expires_at');
});
```

**Model:**

```php
class Consent extends Model
{
    protected $table = 'analytics_consents';

    protected $fillable = [
        'site_id', 'visitor_id',
        'category', 'granted',
        'ip_address', 'user_agent',
        'granted_at', 'revoked_at', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'granted' => 'boolean',
            'granted_at' => 'datetime',
            'revoked_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function visitor(): BelongsTo
    {
        return $this->belongsTo(Visitor::class);
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('granted', true)
            ->whereNull('revoked_at')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    public function scopeForCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    // Helpers
    public function isActive(): bool
    {
        if (!$this->granted || $this->revoked_at !== null) {
            return false;
        }

        return $this->expires_at === null || $this->expires_at->isFuture();
    }

    public function revoke(): void
    {
        $this->update([
            'granted' => false,
            'revoked_at' => now(),
        ]);
    }
}
```

---

### 8. analytics_aggregates

Pre-computed aggregates for fast dashboard queries.

```sql
CREATE TABLE analytics_aggregates (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    site_id BIGINT UNSIGNED NULL,

    -- Time dimension
    date DATE NOT NULL,
    period ENUM('hour', 'day', 'week', 'month') NOT NULL DEFAULT 'day',
    hour TINYINT UNSIGNED NULL,

    -- Metric identification
    metric VARCHAR(50) NOT NULL,

    -- Dimension (optional grouping)
    dimension VARCHAR(50) NULL,
    dimension_value VARCHAR(255) NULL,

    -- Aggregated values
    value BIGINT NOT NULL DEFAULT 0,
    value_sum DECIMAL(20, 4) NULL,
    value_avg DECIMAL(20, 4) NULL,
    value_min DECIMAL(20, 4) NULL,
    value_max DECIMAL(20, 4) NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Indexes
    UNIQUE KEY unique_aggregate (site_id, date, period, hour, metric, dimension, dimension_value),
    INDEX idx_site_date_metric (site_id, date, metric),
    INDEX idx_site_period_date (site_id, period, date),
    INDEX idx_metric_date (metric, date)
);
```

**Aggregate Metrics:**

| Metric | Description | Dimensions |
|--------|-------------|------------|
| `pageviews` | Total page views | path, country, device_type |
| `visitors` | Unique visitors | country, device_type, browser |
| `sessions` | Total sessions | referrer_type, utm_source |
| `bounce_rate` | Bounce rate percentage | entry_page |
| `avg_duration` | Average session duration | - |
| `avg_pages` | Average pages per session | - |
| `events` | Event counts | name, category |
| `conversions` | Conversion counts | goal_id |
| `conversion_value` | Total conversion value | goal_id |

**Laravel Migration:**

```php
Schema::create('analytics_aggregates', function (Blueprint $table) {
    $table->id();
    $table->foreignId('site_id')->nullable()->index();

    $table->date('date');
    $table->enum('period', ['hour', 'day', 'week', 'month'])->default('day');
    $table->unsignedTinyInteger('hour')->nullable();

    $table->string('metric', 50);

    $table->string('dimension', 50)->nullable();
    $table->string('dimension_value', 255)->nullable();

    $table->bigInteger('value')->default(0);
    $table->decimal('value_sum', 20, 4)->nullable();
    $table->decimal('value_avg', 20, 4)->nullable();
    $table->decimal('value_min', 20, 4)->nullable();
    $table->decimal('value_max', 20, 4)->nullable();

    $table->timestamps();

    $table->unique(['site_id', 'date', 'period', 'hour', 'metric', 'dimension', 'dimension_value'], 'unique_aggregate');
    $table->index(['site_id', 'date', 'metric']);
    $table->index(['site_id', 'period', 'date']);
    $table->index(['metric', 'date']);
});
```

**Model:**

```php
class Aggregate extends Model
{
    protected $table = 'analytics_aggregates';

    protected $fillable = [
        'site_id', 'date', 'period', 'hour',
        'metric', 'dimension', 'dimension_value',
        'value', 'value_sum', 'value_avg', 'value_min', 'value_max',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'hour' => 'integer',
            'value' => 'integer',
            'value_sum' => 'decimal:4',
            'value_avg' => 'decimal:4',
            'value_min' => 'decimal:4',
            'value_max' => 'decimal:4',
        ];
    }

    // Scopes
    public function scopeForMetric(Builder $query, string $metric): Builder
    {
        return $query->where('metric', $metric);
    }

    public function scopeForPeriod(Builder $query, string $period): Builder
    {
        return $query->where('period', $period);
    }

    public function scopeForDateRange(Builder $query, Carbon $start, Carbon $end): Builder
    {
        return $query->whereBetween('date', [$start, $end]);
    }

    public function scopeForDimension(Builder $query, string $dimension, ?string $value = null): Builder
    {
        $query->where('dimension', $dimension);

        if ($value !== null) {
            $query->where('dimension_value', $value);
        }

        return $query;
    }

    public function scopeNoDimension(Builder $query): Builder
    {
        return $query->whereNull('dimension');
    }
}
```

---

## Data Retention

The package includes automatic data retention based on configuration:

```php
// Jobs/CleanupOldData.php

class CleanupOldData implements ShouldQueue
{
    public function handle(): void
    {
        $retentionDays = config('analytics.privacy.data_retention');

        if ($retentionDays === null) {
            return; // Unlimited retention
        }

        $cutoff = now()->subDays($retentionDays);

        // Delete old page views
        PageView::where('created_at', '<', $cutoff)->delete();

        // Delete old events
        Event::where('created_at', '<', $cutoff)->delete();

        // Delete old sessions
        Session::where('started_at', '<', $cutoff)->delete();

        // Delete orphaned visitors (no recent sessions)
        Visitor::where('last_seen_at', '<', $cutoff)
            ->whereDoesntHave('sessions', function ($q) use ($cutoff) {
                $q->where('started_at', '>=', $cutoff);
            })
            ->delete();

        // Keep aggregates longer (configurable)
        $aggregateRetention = config('analytics.privacy.aggregate_retention', $retentionDays * 2);
        Aggregate::where('date', '<', now()->subDays($aggregateRetention))->delete();

        // Delete expired consents
        Consent::where('expires_at', '<', now())->delete();
    }
}
```

---

## Indexes Strategy

### Write Optimization

- Minimal indexes on high-write tables (page_views, events)
- Use of `created_at` timestamp without `updated_at` where updates are rare
- Batch inserts for high-volume tracking

### Read Optimization

- Composite indexes for common query patterns
- Pre-computed aggregates for dashboard queries
- Covering indexes for count-only queries

### Multi-Tenant Optimization

- `site_id` as first column in all composite indexes
- Efficient filtering for tenant isolation

---

## Related Documents

- [01-architecture.md](./01-architecture.md) - Overall architecture
- [03-local-analytics-engine.md](./03-local-analytics-engine.md) - Data collection details
- [07-privacy-compliance.md](./07-privacy-compliance.md) - Data retention policies

---

*Last Updated: January 3, 2026*
