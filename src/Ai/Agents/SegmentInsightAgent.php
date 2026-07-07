<?php

/**
 * Segment insight agent.
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
 * Highlight patterns in a visitor segment vs. a baseline.
 *
 * ## Input
 *
 * ```
 * [
 *   'segment'  => [ 'type' => string, 'value' => string ], // required
 *   'metrics'  => array<string, mixed>,                    // required, segment metrics
 *   'baseline' => array<string, mixed>,                    // required, comparison metrics
 * ]
 * ```
 *
 * ## Output schema
 *
 * ```
 * {
 *   patterns: [
 *     { observation: string, significance: 'low'|'medium'|'high', suggested_action: string }
 *   ]
 * }
 * ```
 *
 * @package    ArtisanPack_UI
 * @subpackage Analytics
 *
 * @since      1.3.0
 */
class SegmentInsightAgent extends ArtisanPackAgent
{
	/**
	 * Supported significance bands.
	 *
	 * @since 1.3.0
	 *
	 * @var array<int, string>
	 */
	protected const SIGNIFICANCE_LEVELS = [ 'low', 'medium', 'high' ];

	/**
	 * {@inheritDoc}
	 */
	public string $featureKey = 'analytics.segment_insight';

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
	public function instructions(): string
	{
		return <<<'PROMPT'
You surface interesting patterns in a visitor segment relative to a baseline (typically "all visitors" or the previous period).

Requirements:
- Compare `metrics` (segment) against `baseline` field-by-field. Report only what differs meaningfully; do not narrate the whole payload.
- Return 1-6 patterns. Each pattern must reference concrete numbers from the payloads (e.g. "bounce rate is 62% vs. 41% baseline") — no generic "traffic is higher".
- `significance` is `high` for a large delta (≥50% relative or ≥20pp absolute), `medium` for meaningful but not dramatic differences, `low` for hints. It MUST be exactly one of: low, medium, high.
- `suggested_action` is one short imperative sentence (e.g. "add a lead capture form to /pricing for mobile visitors"). Never emit vague guidance like "investigate further".
- If the segment has too little data to compare (e.g. <20 sessions), return one low-significance pattern noting the low volume and suggesting to widen the window.

Return a JSON object with key `patterns` (array of {observation, significance, suggested_action}).
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
			'required'             => [ 'patterns' ],
			'properties'           => [
				'patterns' => [
					'type'  => 'array',
					'items' => [
						'type'                 => 'object',
						'additionalProperties' => false,
						'required'             => [ 'observation', 'significance', 'suggested_action' ],
						'properties'           => [
							'observation'      => [ 'type' => 'string' ],
							'significance'     => [ 'type' => 'string', 'enum' => self::SIGNIFICANCE_LEVELS ],
							'suggested_action' => [ 'type' => 'string' ],
						],
					],
				],
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
	 * @return array{ segment: array{ type: string, value: string }, metrics: array<string, mixed>, baseline: array<string, mixed> }
	 */
	protected function normalizeInput( mixed $input ): array
	{
		if ( ! is_array( $input ) ) {
			throw FeatureError::forFeature(
				$this->featureKey,
				'input must be an array with `segment`, `metrics`, and `baseline` keys.',
			);
		}

		if ( ! isset( $input['segment'] ) || ! is_array( $input['segment'] ) ) {
			throw FeatureError::forFeature( $this->featureKey, '`segment` must be an array with `type` and `value`.' );
		}

		$type  = isset( $input['segment']['type'] ) && is_string( $input['segment']['type'] ) ? trim( $input['segment']['type'] ) : '';
		$value = isset( $input['segment']['value'] ) && is_string( $input['segment']['value'] ) ? trim( $input['segment']['value'] ) : '';

		if ( '' === $type || '' === $value ) {
			throw FeatureError::forFeature( $this->featureKey, '`segment.type` and `segment.value` must be non-empty strings.' );
		}

		if ( ! isset( $input['metrics'] ) || ! is_array( $input['metrics'] ) || [] === $input['metrics'] ) {
			throw FeatureError::forFeature( $this->featureKey, '`metrics` must be a non-empty array.' );
		}

		if ( ! isset( $input['baseline'] ) || ! is_array( $input['baseline'] ) || [] === $input['baseline'] ) {
			throw FeatureError::forFeature( $this->featureKey, '`baseline` must be a non-empty array.' );
		}

		return [
			'segment'  => [ 'type' => $type, 'value' => $value ],
			'metrics'  => $input['metrics'],
			'baseline' => $input['baseline'],
		];
	}

	/**
	 * Assemble the structured message body for the prompter.
	 *
	 * @since 1.3.0
	 *
	 * @param  array{ segment: array{ type: string, value: string }, metrics: array<string, mixed>, baseline: array<string, mixed> }  $normalized  Normalized input.
	 *
	 * @return array<int, array<string, string>>
	 */
	protected function buildMessage( array $normalized ): array
	{
		try {
			$metricsJson  = json_encode( $normalized['metrics'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR );
			$baselineJson = json_encode( $normalized['baseline'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR );
		} catch ( JsonException $exception ) {
			throw FeatureError::forFeature(
				$this->featureKey,
				sprintf( 'metrics/baseline could not be serialized: %s', $exception->getMessage() ),
				$exception,
			);
		}

		return [
			[
				'type' => 'text',
				'text' => sprintf( 'Segment: %s = %s', $normalized['segment']['type'], $normalized['segment']['value'] ),
			],
			[ 'type' => 'text', 'text' => "Segment metrics:\n" . $metricsJson ],
			[ 'type' => 'text', 'text' => "Baseline metrics:\n" . $baselineJson ],
		];
	}

	/**
	 * Enforce output invariants.
	 *
	 * @since 1.3.0
	 *
	 * @param  array<string, mixed>  $output  Decoded model output.
	 *
	 * @return array{ patterns: array<int, array{ observation: string, significance: string, suggested_action: string }> }
	 */
	protected function validateOutput( array $output ): array
	{
		$rawPatterns = is_array( $output['patterns'] ?? null ) ? $output['patterns'] : [];
		$patterns    = [];

		foreach ( $rawPatterns as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			$observation  = isset( $entry['observation'] ) && is_string( $entry['observation'] ) ? trim( $entry['observation'] ) : '';
			$action       = isset( $entry['suggested_action'] ) && is_string( $entry['suggested_action'] ) ? trim( $entry['suggested_action'] ) : '';
			$significance = isset( $entry['significance'] ) && is_string( $entry['significance'] )
				? strtolower( trim( $entry['significance'] ) )
				: 'low';

			if ( ! in_array( $significance, self::SIGNIFICANCE_LEVELS, true ) ) {
				$significance = 'low';
			}

			if ( '' === $observation || '' === $action ) {
				continue;
			}

			$patterns[] = [
				'observation'      => $observation,
				'significance'     => $significance,
				'suggested_action' => $action,
			];
		}

		return [ 'patterns' => $patterns ];
	}
}
