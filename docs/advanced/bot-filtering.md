---
title: Bot Filtering
---

# Bot Filtering

> **Since 1.2.0**

ArtisanPack UI Analytics includes a multi-layered bot filtering system that keeps automated traffic — AI training crawlers, SEO tools, scrapers, and headless browsers — out of your reported numbers. Bot-flagged data is still stored, but it is excluded from dashboard and query results by default so your analytics reflect real human visitors.

Bot detection is **enabled by default** and requires no configuration to start working.

## How It Works

Bot filtering combines four layers, each contributing to a confidence score between 0 and 100. A visitor whose score meets or exceeds the configured threshold (default `70`) is flagged as a bot.

1. **User agent matching** — The `DeviceDetector` matches requests against an expanded list of 60+ known bots, including AI crawlers (GPTBot, ClaudeBot, CCBot, PerplexityBot), regional crawlers (ByteSpider, Baidu, Sogou, Yandex), SEO tools (SEMrush, Ahrefs, Majestic), and scraper/automation tooling (curl, wget, Puppeteer, Playwright). A known match immediately scores `100`.
2. **Behavioral analysis** — The `BotDetector` service scores engagement and request patterns that betray automation even when the user agent looks like a real browser.
3. **JavaScript fingerprint signals** — The tracker script collects privacy-preserving browser signals (WebDriver flag, headless indicators, missing browser APIs) that feed the score.
4. **Whitelisting** — Trusted user agents and IP addresses bypass scoring entirely, both via config and via runtime database entries.

Scoring happens server-side and after the fact. The `AnalyzeBotTraffic` job runs on a schedule, evaluates recently seen visitors that have not yet been scored, and persists the result to the `is_bot`, `bot_score`, and `bot_detected_at` columns on the visitors table.

## Confidence Scoring

Each signal category contributes points toward the confidence score. Points are summed and capped at `100`. A known bot user agent short-circuits the rest of the scoring and returns `100` on its own.

| Category | Signal | Points |
|----------|--------|--------|
| `user_agent` | Known bot user agent | 100 |
| `user_agent` | Empty or missing user agent | 80 |
| `js_fingerprint` | WebDriver automation detected | 50 |
| `js_fingerprint` | Headless browser indicators | 40 |
| `js_fingerprint` | Missing browser APIs (no WebGL and no Canvas) | 30 |
| `engagement` | Zero engagement across 3 or more page views | 35 |
| `engagement` | Average page view time under one second | 25 |
| `request_patterns` | Rapid requests (more than 10 pages per minute) | 30 |
| `request_patterns` | Perfectly even request intervals | 20 |
| `request_patterns` | No referrer variation across 3 or more page views | 15 |

The thresholds are intentionally conservative so a single weak signal — common on privacy-focused browsers — never flags a real visitor on its own. It takes a combination of signals to cross the default threshold of `70`.

## Configuration

All bot detection settings live under the `bot_detection` key in `config/artisanpack/analytics.php`. See the [Configuration reference](Installation-Configuration#bot-detection) for the full block.

### Enabling and Disabling

```php
'bot_detection' => [
    'enabled' => env('ANALYTICS_BOT_DETECTION_ENABLED', true),
],
```

Setting `enabled` to `false` turns off scoring entirely; the `AnalyzeBotTraffic` job is not scheduled and no visitors are flagged.

### Adjusting the Threshold

```php
'bot_detection' => [
    'threshold' => env('ANALYTICS_BOT_DETECTION_THRESHOLD', 70),
],
```

The threshold is the minimum confidence score required to flag a visitor. Lower it to filter more aggressively; raise it to reduce false positives.

```dotenv
# More aggressive filtering
ANALYTICS_BOT_DETECTION_THRESHOLD=50

# More conservative filtering
ANALYTICS_BOT_DETECTION_THRESHOLD=85
```

### Toggling Individual Signals

```php
'bot_detection' => [
    'signals' => [
        'user_agent'       => true,
        'engagement'       => true,
        'request_patterns' => true,
        'js_fingerprint'   => true,
    ],
],
```

Disable any category to exclude it from scoring. A disabled category contributes zero points. For example, set `js_fingerprint` to `false` if you do not serve the tracker script or do not want to rely on client-side signals.

### Analysis Schedule

```php
'bot_detection' => [
    'analysis_interval' => env('ANALYTICS_BOT_DETECTION_INTERVAL', 15),
    'analysis_window'   => env('ANALYTICS_BOT_DETECTION_WINDOW', 60),
],
```

- `analysis_interval` — how often, in minutes, the `AnalyzeBotTraffic` job runs. The value is normalized down to the nearest divisor of 60 (1, 2, 3, 4, 5, 6, 10, 12, 15, 20, or 30) so the job fires at an even cadence within the hour.
- `analysis_window` — how far back, in minutes, the job looks for unscored visitors on each run.

The job is registered automatically when bot detection is enabled. Make sure your application's scheduler is running:

```bash
php artisan schedule:work
```

## Whitelisting

Whitelisted user agents and IP addresses bypass scoring entirely. There are two complementary sources.

### Config Whitelist

Static entries defined in configuration. User agents are matched as case-insensitive substrings; IPs must match exactly.

```php
'bot_detection' => [
    'whitelist' => [
        'user_agents' => ['Googlebot', 'bingbot'],
        'ips'         => ['203.0.113.5'],
    ],
],
```

Use the config whitelist for entries you want version-controlled and deployed with your application — for example, intentionally including Googlebot in your analytics.

### Runtime Whitelist

Database-backed entries managed at runtime with the [`analytics:whitelist`](Advanced-Artisan-Commands#analyticswhitelist) command. These supplement the config whitelist and are ideal for ad-hoc additions without a deployment.

```bash
# Whitelist a user agent
php artisan analytics:whitelist add --user-agent="Googlebot"

# Whitelist an IP address
php artisan analytics:whitelist add --ip=203.0.113.5

# List the combined config and database whitelist
php artisan analytics:whitelist list

# Remove a database entry (config entries cannot be removed here)
php artisan analytics:whitelist remove --ip=203.0.113.5
```

Runtime whitelist lookups fail gracefully: if the whitelist table has not been migrated or the database is briefly unavailable, scoring falls back to the config whitelist instead of erroring.

## Querying Bot Traffic

Dashboard and analytics queries exclude bots by default. The `AnalyticsQuery` service exposes fluent modifiers to change that scope for a single query:

```php
use ArtisanPackUI\Analytics\Services\AnalyticsQuery;

$query = app(AnalyticsQuery::class);

// Default: human traffic only
$query->getVisitors($range);

// Include bot traffic alongside human traffic
$query->includeBots()->getVisitors($range);

// Bot traffic only
$query->onlyBots()->getVisitors($range);

// Explicitly exclude bots (the default)
$query->excludeBots()->getVisitors($range);
```

`includeBots()` has an alias, `withBots()`. To pull a ready-made summary of filtered traffic, use `getBotStats()`:

```php
$stats = app(AnalyticsQuery::class)->getBotStats($range);

// [
//     'bot_visits'     => 1240,
//     'total_visits'   => 9810,
//     'bot_percentage' => 12.6,
//     'top_agents'     => [['user_agent' => 'GPTBot/1.0', 'visits' => 412], ...],
//     'trend'          => [['date' => '2026-05-01', 'visits' => 40], ...],
// ]
```

## Reviewing Detected Bots

Use the [`analytics:bots`](Advanced-Artisan-Commands#analyticsbots) command to list flagged visitors and their confidence scores from the CLI:

```bash
# List bots scoring 70 or higher (the default)
php artisan analytics:bots

# Lower the score floor and limit the results
php artisan analytics:bots --score=50 --limit=100

# Export the results to a CSV file
php artisan analytics:bots --export=csv
```

## Dashboard Widget

The [Bot Traffic widget](Components-Bot-Traffic) surfaces filtered traffic on your dashboard: total bot visits, the bot share of total traffic, the busiest bot user agents, and a bot-only visit trend.

```blade
<livewire:artisanpack-analytics::widgets.bot-traffic />
```

## Visitor Columns

Bot scoring persists three columns on the `analytics_visitors` table:

| Column | Type | Description |
|--------|------|-------------|
| `is_bot` | boolean | Whether the visitor's score met the threshold. Indexed. |
| `bot_score` | unsigned tiny integer | The confidence score (0–100), or `null` if not yet scored. |
| `bot_detected_at` | timestamp | When the visitor was last scored, or `null` if not yet scored. |

A `null` `bot_detected_at` is how the `AnalyzeBotTraffic` job identifies visitors that still need scoring.

## Out of Scope

Bot filtering is a post-hoc analytics feature, not a security control. The following are intentionally **not** part of this system:

- IP geolocation-based blocking
- Machine learning-based detection
- CAPTCHA or proof-of-work challenges
- Real-time blocking at the middleware level
- Bot traffic alerting or notifications

For request-level exclusions (for example, dropping obvious crawlers before they are ever recorded), see the user agent exclusions under [Privacy & Consent](Advanced-Privacy-Consent) and the `privacy.excluded_user_agents` setting.
