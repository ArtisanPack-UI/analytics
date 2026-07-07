<?php

/**
 * GatheringDigestItems event.
 *
 * @package    ArtisanPack_UI
 * @subpackage Analytics
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.3.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Events;

/**
 * Fired by `analytics:dispatch-digests` once per opted-in user before the
 * digest job is enqueued. Host apps listen for this event and mutate
 * `$items` to feed the AI agent with their metrics of choice.
 *
 * If no listener populates `$items`, the dispatcher logs a warning and
 * skips that user — the paid AI agent is never run on empty input.
 *
 * @package    ArtisanPack_UI
 * @subpackage Analytics
 *
 * @since      1.3.0
 */
class GatheringDigestItems
{
	/**
	 * Metrics / top pages / referrer deltas the AI agent will summarize.
	 * Listeners assign into this array.
	 *
	 * @var array<int, mixed>
	 */
	public array $items = [];

	/**
	 * Constructor.
	 *
	 * @since 1.3.0
	 *
	 * @param  int     $userId   Recipient user id.
	 * @param  string  $cadence  `weekly` or `monthly`.
	 */
	public function __construct(
		public readonly int $userId,
		public readonly string $cadence,
	) {
	}
}
