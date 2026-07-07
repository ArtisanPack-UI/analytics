<?php

/**
 * Digest email agent.
 *
 * @package    ArtisanPack_UI
 * @subpackage Analytics
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.3.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Ai\Agents;

use ArtisanPackUI\Ai\Agents\SummarizationAgent;

/**
 * Compose an analytics digest email body from a period's items.
 *
 * Extends {@see SummarizationAgent} — inherits the input contract
 * (`items`, `focus`, `length`) and returns the same shape
 * (`summary`, `key_points`, `caveats`). The digest surface biases the
 * prompt toward a friendly narrative suitable for a weekly or monthly
 * subscriber email.
 *
 * @package    ArtisanPack_UI
 * @subpackage Analytics
 *
 * @since      1.3.0
 */
class DigestEmailAgent extends SummarizationAgent
{
	/**
	 * {@inheritDoc}
	 */
	public string $featureKey = 'analytics.digest_email';

	/**
	 * {@inheritDoc}
	 */
	public string $package = 'artisanpack-ui/analytics';

	/**
	 * {@inheritDoc}
	 */
	public string $defaultModel = 'claude-sonnet-4-6';

	/**
	 * {@inheritDoc}
	 */
	public bool $stream = false;

	/**
	 * {@inheritDoc}
	 */
	public function instructions(): string
	{
		return <<<'PROMPT'
You compose the narrative body of an analytics digest email sent to a site owner on their chosen cadence (weekly or monthly).

Requirements:
- Base every claim strictly on the provided items (metrics, top pages, referrer deltas, notable events). Do NOT invent numbers.
- `summary` is 2-4 sentences — the "here's what happened this period" narrative that reads like a friendly analyst wrote it. Lead with the headline metric change.
- `key_points` is 3-7 bulletable highlights the reader would care about (top page, growth channel, standout referrer, goal conversion count). Reference concrete numbers.
- `caveats` is 0-4 entries for missing context, thin data, or anomalies worth investigating separately. Empty array is fine when the period is clean.
- Write in second person ("your site", "your top page") — subscribers are reading about their own analytics.
- No marketing fluff, no emoji, no clickbait phrasing. Plain and honest.

Return a JSON object with keys: summary (string), key_points (array of strings), caveats (array of strings).
PROMPT;
	}
}
