<?php

declare(strict_types=1);

use ArtisanPackUI\Analytics\Analytics;
use ArtisanPackUI\Analytics\Contracts\AnalyticsProviderInterface;
use ArtisanPackUI\Analytics\Data\EventData;
use ArtisanPackUI\Analytics\Data\PageViewData;
use ArtisanPackUI\Analytics\Events\PageViewTracked;
use ArtisanPackUI\Analytics\Models\PageView;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

/**
 * A real browser User-Agent so the ingest bot filter lets the beacon through.
 */
const FORWARDING_TEST_AGENT = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';

/**
 * Records every page view forwarded to it, standing in for a server-side
 * forwarder such as the GA4 Measurement Protocol adapter.
 */
final class RecordingForwardProvider implements AnalyticsProviderInterface
{
    /** @var list<string> */
    public static array $pageViewPaths = [];

    public function trackPageView(PageViewData $data): void
    {
        self::$pageViewPaths[] = $data->path;
    }

    public function trackEvent(EventData $data): void {}

    public function isEnabled(): bool
    {
        return true;
    }

    public function getName(): string
    {
        return 'recording_forward';
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return [];
    }
}

/**
 * Register the recording provider and make it active alongside `local`.
 */
function activateRecordingForwarder(): void
{
    RecordingForwardProvider::$pageViewPaths = [];

    $analytics = app(Analytics::class);
    $analytics->extend('recording_forward', fn (): AnalyticsProviderInterface => new RecordingForwardProvider);

    config()->set('artisanpack.analytics.active_providers', ['local', 'recording_forward']);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function pageViewPayload(array $overrides = []): array
{
    return array_merge([
        'visitor_id' => 'visitor-abc',
        'session_id' => '11111111-1111-4111-8111-111111111111',
        'path' => '/docs/getting-started',
        'title' => 'Getting Started',
    ], $overrides);
}

test('forwards an ingested page view to every active provider except local', function (): void {
    activateRecordingForwarder();

    test()
        ->withHeaders(['User-Agent' => FORWARDING_TEST_AGENT])
        ->postJson('/api/analytics/pageview', pageViewPayload())
        ->assertNoContent();

    // Local stored exactly one row — the local provider was skipped by the
    // forwarding listener, so it was not re-tracked into a duplicate.
    expect(PageView::query()->where('path', '/docs/getting-started')->count())->toBe(1);

    // The secondary provider received the same page view over the ingest path.
    expect(RecordingForwardProvider::$pageViewPaths)->toBe(['/docs/getting-started']);
});

test('forwards batched page views to every active provider except local', function (): void {
    activateRecordingForwarder();

    test()
        ->withHeaders(['User-Agent' => FORWARDING_TEST_AGENT])
        ->postJson('/api/analytics/batch', [
            'items' => [
                ['type' => 'pageview', 'data' => pageViewPayload(['path' => '/docs/a'])],
                ['type' => 'pageview', 'data' => pageViewPayload(['path' => '/docs/b'])],
            ],
        ])
        ->assertNoContent();

    expect(RecordingForwardProvider::$pageViewPaths)->toEqualCanonicalizing(['/docs/a', '/docs/b']);
});

test('forwards page views processed through the queued batch job', function (): void {
    activateRecordingForwarder();

    // Queue the ingest processing so the batch runs through ProcessBatchTracking
    // rather than the synchronous TrackingService path. The test queue
    // connection is `sync`, so the dispatched job executes inline.
    config()->set('artisanpack.analytics.local.queue_processing', true);

    test()
        ->withHeaders(['User-Agent' => FORWARDING_TEST_AGENT])
        ->postJson('/api/analytics/batch', [
            'items' => [
                ['type' => 'pageview', 'data' => pageViewPayload(['path' => '/docs/queued-a'])],
                ['type' => 'pageview', 'data' => pageViewPayload(['path' => '/docs/queued-b'])],
            ],
        ])
        ->assertNoContent();

    expect(RecordingForwardProvider::$pageViewPaths)->toEqualCanonicalizing(['/docs/queued-a', '/docs/queued-b']);
});

test('ingesting a page view dispatches the PageViewTracked event', function (): void {
    Event::fake([PageViewTracked::class]);

    test()
        ->withHeaders(['User-Agent' => FORWARDING_TEST_AGENT])
        ->postJson('/api/analytics/pageview', pageViewPayload())
        ->assertNoContent();

    Event::assertDispatched(PageViewTracked::class, function (PageViewTracked $event): bool {
        return $event->data->path === '/docs/getting-started';
    });
});
