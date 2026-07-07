<?php

/**
 * Insight summary agent.
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

use ArtisanPackUI\Ai\Agents\ArtisanPackAgent;
use ArtisanPackUI\Ai\Contracts\AgentPrompter;
use ArtisanPackUI\Ai\Credentials\Credentials;
use ArtisanPackUI\Ai\Exceptions\FeatureError;
use JsonException;

/**
 * Produce a plain-language summary of a metrics window.
 *
 * ## Input
 *
 * ```
 * [
 *   'date_range' => [ 'from' => string, 'to' => string ], // required (YYYY-MM-DD)
 *   'metrics'    => array<string, mixed>,                 // required
 *   'compare_to' => [ 'from' => string, 'to' => string ], // optional
 * ]
 * ```
 *
 * ## Output schema
 *
 * ```
 * {
 *   summary:    string,
 *   highlights: string[],
 *   concerns:   string[]
 * }
 * ```
 *
 * @package    ArtisanPack_UI
 * @subpackage Analytics
 *
 * @since      1.3.0
 */
class InsightSummaryAgent extends ArtisanPackAgent
{
	/**
	 * {@inheritDoc}
	 */
	public string $featureKey = 'analytics.insight_summary';

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
	public bool $stream = true;

	/**
	 * {@inheritDoc}
	 */
	public function instructions(): string
	{
		return <<<'PROMPT'
You summarize a website analytics window in plain, non-technical language for a busy site owner.

Requirements:
- Base every claim strictly on the provided `metrics` and (when present) `compare_to` numbers. Do NOT invent totals, percentages, or trends.
- `summary` is 1-3 sentences that state the headline story of the window (traffic direction, standout channel or page, notable change vs. comparison).
- `highlights` is 2-5 positive or neutral facts worth calling out (top page, best channel, growth vs. comparison). Reference concrete numbers.
- `concerns` is 0-5 items that a site owner should pay attention to (drops, bounce spikes, missing goal conversions, thin data). Empty array is fine when the window is clean.
- When `compare_to` is missing, describe the window in absolute terms and note that no comparison was provided.
- Avoid marketing fluff. Speak like a friendly analyst, not a copywriter.

Return a JSON object with keys: summary (string), highlights (array of strings), concerns (array of strings).
PROMPT;
	}

	/**
	 * {@inheritDoc}
	 */
	public function outputSchema(): array
	{
		return [
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => [ 'summary', 'highlights', 'concerns' ],
			'properties'           => [
				'summary'    => [ 'type' => 'string' ],
				'highlights' => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
				'concerns'   => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
			],
		];
	}

	/**
	 * {@inheritDoc}
	 */
	protected function execute( Credentials $credentials, string $model, string $instructions ): array
	{
		$normalized = $this->normalizeInput( $this->input() );

		$prompter = app( AgentPrompter::class );

		$result = $prompter->prompt(
			credentials: $credentials,
			model: $model,
			instructions: $instructions,
			message: $this->buildMessage( $normalized ),
			outputSchema: $this->outputSchema(),
		);

		return [
			'output'        => $this->validateOutput( $result['output'] ?? [] ),
			'input_tokens'  => (int) ( $result['input_tokens'] ?? 0 ),
			'output_tokens' => (int) ( $result['output_tokens'] ?? 0 ),
		];
	}

	/**
	 * Validate and shape-check the raw agent input.
	 *
	 * @since 1.3.0
	 *
	 * @param  mixed  $input  Raw agent input.
	 *
	 * @return array{ date_range: array{ from: string, to: string }, metrics: array<string, mixed>, compare_to: array{ from: string, to: string }|null }
	 */
	protected function normalizeInput( mixed $input ): array
	{
		if ( ! is_array( $input ) ) {
			throw FeatureError::forFeature(
				$this->featureKey,
				'input must be an array with `date_range` and `metrics` keys.',
			);
		}

		$dateRange = $this->normalizeDateRange( $input['date_range'] ?? null, 'date_range' );

		if ( ! isset( $input['metrics'] ) || ! is_array( $input['metrics'] ) || [] === $input['metrics'] ) {
			throw FeatureError::forFeature( $this->featureKey, '`metrics` must be a non-empty array.' );
		}

		$compareTo = null;

		if ( isset( $input['compare_to'] ) && null !== $input['compare_to'] ) {
			$compareTo = $this->normalizeDateRange( $input['compare_to'], 'compare_to' );
		}

		return [
			'date_range' => $dateRange,
			'metrics'    => $input['metrics'],
			'compare_to' => $compareTo,
		];
	}

	/**
	 * Normalize a `{from,to}` date-range structure.
	 *
	 * @since 1.3.0
	 *
	 * @param  mixed   $value  Raw range.
	 * @param  string  $label  Field name for error messages.
	 *
	 * @return array{ from: string, to: string }
	 */
	protected function normalizeDateRange( mixed $value, string $label ): array
	{
		if ( ! is_array( $value ) ) {
			throw FeatureError::forFeature(
				$this->featureKey,
				sprintf( '`%s` must be an array with `from` and `to` keys.', $label ),
			);
		}

		$from = isset( $value['from'] ) && is_string( $value['from'] ) ? trim( $value['from'] ) : '';
		$to   = isset( $value['to'] ) && is_string( $value['to'] ) ? trim( $value['to'] ) : '';

		if ( '' === $from || '' === $to ) {
			throw FeatureError::forFeature(
				$this->featureKey,
				sprintf( '`%s.from` and `%s.to` must be non-empty strings.', $label, $label ),
			);
		}

		return [ 'from' => $from, 'to' => $to ];
	}

	/**
	 * Assemble the structured message body for the prompter.
	 *
	 * @since 1.3.0
	 *
	 * @param  array{ date_range: array{ from: string, to: string }, metrics: array<string, mixed>, compare_to: array{ from: string, to: string }|null }  $normalized  Normalized input.
	 *
	 * @return array<int, array<string, string>>
	 */
	protected function buildMessage( array $normalized ): array
	{
		$parts = [
			[
				'type' => 'text',
				'text' => sprintf(
					'Date range: %s to %s',
					$normalized['date_range']['from'],
					$normalized['date_range']['to'],
				),
			],
		];

		if ( null !== $normalized['compare_to'] ) {
			$parts[] = [
				'type' => 'text',
				'text' => sprintf(
					'Compare to: %s to %s',
					$normalized['compare_to']['from'],
					$normalized['compare_to']['to'],
				),
			];
		}

		try {
			$metricsJson = json_encode(
				$normalized['metrics'],
				JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
			);
		} catch ( JsonException $exception ) {
			throw FeatureError::forFeature(
				$this->featureKey,
				sprintf( 'metrics could not be serialized for the model: %s', $exception->getMessage() ),
				$exception,
			);
		}

		$parts[] = [
			'type' => 'text',
			'text' => "Metrics:\n" . $metricsJson,
		];

		return $parts;
	}

	/**
	 * Enforce output invariants.
	 *
	 * @since 1.3.0
	 *
	 * @param  array<string, mixed>  $output  Decoded model output.
	 *
	 * @return array{ summary: string, highlights: array<int, string>, concerns: array<int, string> }
	 */
	protected function validateOutput( array $output ): array
	{
		return [
			'summary'    => isset( $output['summary'] ) ? trim( (string) $output['summary'] ) : '',
			'highlights' => $this->stringList( $output['highlights'] ?? [] ),
			'concerns'   => $this->stringList( $output['concerns'] ?? [] ),
		];
	}

	/**
	 * Filter a raw list into a clean array of non-empty strings.
	 *
	 * @since 1.3.0
	 *
	 * @param  mixed  $raw  Raw list from the model.
	 *
	 * @return array<int, string>
	 */
	protected function stringList( mixed $raw ): array
	{
		if ( ! is_array( $raw ) ) {
			return [];
		}

		$out = [];

		foreach ( $raw as $value ) {
			if ( is_string( $value ) && '' !== trim( $value ) ) {
				$out[] = trim( $value );
			}
		}

		return $out;
	}
}
