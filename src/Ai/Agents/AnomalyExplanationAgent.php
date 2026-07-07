<?php

/**
 * Anomaly explanation agent.
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
 * Explain a detected traffic anomaly with ranked hypotheses.
 *
 * ## Input
 *
 * ```
 * [
 *   'anomaly' => [
 *     'metric'    => string,   // e.g. "pageviews", "conversions"
 *     'direction' => string,   // "up" | "down"
 *     'magnitude' => float,    // percent change vs. expected
 *     'date'      => string,   // YYYY-MM-DD
 *   ],
 *   'context' => [
 *     'recent_content_changes' => array,
 *     'referrer_deltas'        => array,
 *     'campaign_launches'      => array,
 *   ],
 * ]
 * ```
 *
 * ## Output schema
 *
 * ```
 * {
 *   hypotheses: [
 *     { cause: string, confidence: 'low'|'medium'|'high', evidence: string[] }
 *   ],
 *   recommended_next_steps: string[]
 * }
 * ```
 *
 * @package    ArtisanPack_UI
 * @subpackage Analytics
 *
 * @since      1.3.0
 */
class AnomalyExplanationAgent extends ArtisanPackAgent
{
	/**
	 * Supported confidence bands.
	 *
	 * @since 1.3.0
	 *
	 * @var array<int, string>
	 */
	protected const CONFIDENCE_LEVELS = [ 'low', 'medium', 'high' ];

	/**
	 * {@inheritDoc}
	 */
	public string $featureKey = 'analytics.explain_anomaly';

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
You explain a detected analytics anomaly by proposing ranked hypotheses for its cause.

Requirements:
- Ground every hypothesis in the provided `context` (recent content changes, referrer deltas, campaign launches). Do NOT invent events or channels that aren't in the context.
- Return 1-5 hypotheses. Order them by how well they explain the anomaly's `direction` and `magnitude` — highest confidence first.
- `confidence` MUST be exactly one of `low`, `medium`, `high`.
- `evidence` for each hypothesis is 1-4 concrete items pulled from `context` (e.g. "referrer 'reddit.com' rose 340% on the same day", "content change to /pricing landed 2025-05-11"). Never emit generic strings like "traffic increased".
- `recommended_next_steps` is 2-4 concrete actions the site owner should take next (e.g. "verify UTM tagging on the new campaign", "audit the /pricing edit for a broken canonical").
- If the context is thin, say so — return one low-confidence hypothesis noting the missing context and a next step that gathers it.

Return a JSON object with keys: hypotheses (array of {cause, confidence, evidence[]}) and recommended_next_steps (array of strings).
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
			'required'             => [ 'hypotheses', 'recommended_next_steps' ],
			'properties'           => [
				'hypotheses'             => [
					'type'  => 'array',
					'items' => [
						'type'                 => 'object',
						'additionalProperties' => false,
						'required'             => [ 'cause', 'confidence', 'evidence' ],
						'properties'           => [
							'cause'      => [ 'type' => 'string' ],
							'confidence' => [ 'type' => 'string', 'enum' => self::CONFIDENCE_LEVELS ],
							'evidence'   => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
						],
					],
				],
				'recommended_next_steps' => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
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
	 * @return array{ anomaly: array<string, mixed>, context: array<string, mixed> }
	 */
	protected function normalizeInput( mixed $input ): array
	{
		if ( ! is_array( $input ) ) {
			throw FeatureError::forFeature(
				$this->featureKey,
				'input must be an array with `anomaly` and `context` keys.',
			);
		}

		if ( ! isset( $input['anomaly'] ) || ! is_array( $input['anomaly'] ) ) {
			throw FeatureError::forFeature( $this->featureKey, '`anomaly` must be an array.' );
		}

		foreach ( [ 'metric', 'direction', 'date' ] as $required ) {
			if ( ! isset( $input['anomaly'][ $required ] ) || ! is_string( $input['anomaly'][ $required ] ) || '' === trim( $input['anomaly'][ $required ] ) ) {
				throw FeatureError::forFeature(
					$this->featureKey,
					sprintf( '`anomaly.%s` must be a non-empty string.', $required ),
				);
			}
		}

		if ( ! isset( $input['anomaly']['magnitude'] ) || ! is_numeric( $input['anomaly']['magnitude'] ) ) {
			throw FeatureError::forFeature( $this->featureKey, '`anomaly.magnitude` must be numeric.' );
		}

		$context = isset( $input['context'] ) && is_array( $input['context'] ) ? $input['context'] : [];

		return [
			'anomaly' => $input['anomaly'],
			'context' => $context,
		];
	}

	/**
	 * Assemble the structured message body for the prompter.
	 *
	 * @since 1.3.0
	 *
	 * @param  array{ anomaly: array<string, mixed>, context: array<string, mixed> }  $normalized  Normalized input.
	 *
	 * @return array<int, array<string, string>>
	 */
	protected function buildMessage( array $normalized ): array
	{
		try {
			$anomalyJson = json_encode(
				$normalized['anomaly'],
				JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
			);
			$contextJson = json_encode(
				$normalized['context'],
				JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
			);
		} catch ( JsonException $exception ) {
			throw FeatureError::forFeature(
				$this->featureKey,
				sprintf( 'anomaly/context could not be serialized: %s', $exception->getMessage() ),
				$exception,
			);
		}

		return [
			[ 'type' => 'text', 'text' => "Anomaly:\n" . $anomalyJson ],
			[ 'type' => 'text', 'text' => "Context:\n" . $contextJson ],
		];
	}

	/**
	 * Enforce output invariants.
	 *
	 * @since 1.3.0
	 *
	 * @param  array<string, mixed>  $output  Decoded model output.
	 *
	 * @return array{ hypotheses: array<int, array{ cause: string, confidence: string, evidence: array<int, string> }>, recommended_next_steps: array<int, string> }
	 */
	protected function validateOutput( array $output ): array
	{
		$rawHypotheses = $this->coerceListField( $output['hypotheses'] ?? null, 'hypotheses' );
		$hypotheses    = [];

		foreach ( $rawHypotheses as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			$cause      = isset( $entry['cause'] ) && is_string( $entry['cause'] ) ? trim( $entry['cause'] ) : '';
			$confidence = isset( $entry['confidence'] ) && is_string( $entry['confidence'] ) ? strtolower( trim( $entry['confidence'] ) ) : 'low';
			$evidence   = $this->stringList( $entry['evidence'] ?? [] );

			if ( ! in_array( $confidence, self::CONFIDENCE_LEVELS, true ) ) {
				$confidence = 'low';
			}

			if ( '' === $cause ) {
				continue;
			}

			$hypotheses[] = [
				'cause'      => $cause,
				'confidence' => $confidence,
				'evidence'   => $evidence,
			];
		}

		return [
			'hypotheses'             => $hypotheses,
			'recommended_next_steps' => $this->stringList( $output['recommended_next_steps'] ?? [] ),
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

	/**
	 * Coerce a top-level list field into an array.
	 *
	 * Some providers (e.g. Anthropic Opus under certain schema shapes)
	 * return the whole nested payload as a JSON-encoded string instead of
	 * a real array. Decode transparently so the downstream validator can
	 * still iterate the entries.
	 *
	 * @since 1.3.0
	 *
	 * @param  mixed   $raw   Raw field from the model.
	 * @param  string  $key   The field key to look up when the model re-nests under the same key.
	 *
	 * @return array<int|string, mixed>
	 */
	protected function coerceListField( mixed $raw, string $key ): array
	{
		if ( is_array( $raw ) ) {
			return $raw;
		}

		if ( is_string( $raw ) && '' !== trim( $raw ) ) {
			$decoded = json_decode( $raw, true );

			if ( is_array( $decoded ) ) {
				if ( isset( $decoded[ $key ] ) && is_array( $decoded[ $key ] ) ) {
					return $decoded[ $key ];
				}

				return $decoded;
			}
		}

		return [];
	}
}
