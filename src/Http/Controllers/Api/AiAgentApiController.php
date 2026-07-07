<?php

/**
 * AiAgentApiController.
 *
 * Single dispatch endpoint for the Analytics package's AI agents. React/Vue
 * frontends POST here with a feature key + input payload; the controller
 * resolves the registered agent, runs it, and returns the structured output.
 *
 * @package    ArtisanPack_UI
 * @subpackage Analytics
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.3.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Http\Controllers\Api;

use ArtisanPackUI\Ai\Contracts\FeatureRegistry;
use ArtisanPackUI\Ai\Exceptions\FeatureDisabledException;
use ArtisanPackUI\Ai\Exceptions\FeatureError;
use ArtisanPackUI\Ai\Exceptions\MissingCredentialsException;
use ArtisanPackUI\Analytics\Ai\Agents\AnomalyExplanationAgent;
use ArtisanPackUI\Analytics\Ai\Agents\DigestEmailAgent;
use ArtisanPackUI\Analytics\Ai\Agents\InsightSummaryAgent;
use ArtisanPackUI\Analytics\Ai\Agents\SegmentInsightAgent;
use ArtisanPackUI\Analytics\Http\Requests\Api\Ai\AnomalyExplanationAiRequest;
use ArtisanPackUI\Analytics\Http\Requests\Api\Ai\DigestEmailAiRequest;
use ArtisanPackUI\Analytics\Http\Requests\Api\Ai\DigestSubscriptionAiRequest;
use ArtisanPackUI\Analytics\Http\Requests\Api\Ai\InsightSummaryAiRequest;
use ArtisanPackUI\Analytics\Http\Requests\Api\Ai\SegmentInsightAiRequest;
use ArtisanPackUI\Analytics\Models\AnalyticsDigestPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Throwable;

/**
 * Handles API requests for the Analytics package's AI agents.
 *
 * @package    ArtisanPack_UI
 * @subpackage Analytics
 *
 * @since      1.3.0
 */
class AiAgentApiController extends Controller
{
	/**
	 * Feature-key → agent-class map for this package.
	 *
	 * @since 1.3.0
	 *
	 * @var array<string, class-string>
	 */
	protected const AGENTS = [
		'analytics.insight_summary'  => InsightSummaryAgent::class,
		'analytics.explain_anomaly'  => AnomalyExplanationAgent::class,
		'analytics.segment_insight'  => SegmentInsightAgent::class,
		'analytics.digest_email'     => DigestEmailAgent::class,
	];

	/**
	 * Summarize a date range.
	 *
	 * @since 1.3.0
	 */
	public function insightSummary( InsightSummaryAiRequest $request ): JsonResponse
	{
		return $this->dispatchAgent( 'analytics.insight_summary', $request->validated() );
	}

	/**
	 * Explain a detected anomaly.
	 *
	 * @since 1.3.0
	 */
	public function explainAnomaly( AnomalyExplanationAiRequest $request ): JsonResponse
	{
		return $this->dispatchAgent( 'analytics.explain_anomaly', $request->validated() );
	}

	/**
	 * Surface patterns in a visitor segment.
	 *
	 * @since 1.3.0
	 */
	public function segmentInsight( SegmentInsightAiRequest $request ): JsonResponse
	{
		return $this->dispatchAgent( 'analytics.segment_insight', $request->validated() );
	}

	/**
	 * Preview a digest-email body.
	 *
	 * @since 1.3.0
	 */
	public function digestEmail( DigestEmailAiRequest $request ): JsonResponse
	{
		return $this->dispatchAgent( 'analytics.digest_email', $request->validated() );
	}

	/**
	 * Persist the current user's digest-email opt-in preference.
	 *
	 * @since 1.3.0
	 */
	public function saveDigestSubscription( DigestSubscriptionAiRequest $request ): JsonResponse
	{
		$user = $request->user();

		if ( null === $user ) {
			return response()->json( [ 'message' => __( 'Unauthenticated.' ) ], 401 );
		}

		$preference = AnalyticsDigestPreference::query()->updateOrCreate(
			[ 'user_id' => $user->getAuthIdentifier() ],
			[ 'cadence' => $request->string( 'cadence' )->toString() ],
		);

		return response()->json( [
			'data' => [
				'cadence'      => $preference->cadence,
				'last_sent_at' => $preference->last_sent_at?->toIso8601String(),
			],
		] );
	}

	/**
	 * Run the agent associated with `$featureKey`.
	 *
	 * @since 1.3.0
	 *
	 * @param  string                $featureKey  Fully-qualified feature key.
	 * @param  array<string, mixed>  $input       Raw request payload.
	 *
	 * @return JsonResponse
	 */
	protected function dispatchAgent( string $featureKey, array $input ): JsonResponse
	{
		if ( ! isset( self::AGENTS[ $featureKey ] ) ) {
			return response()->json( [ 'message' => __( 'Unknown AI feature.' ) ], 404 );
		}

		$registry = app( FeatureRegistry::class );

		// Fail closed: `isToggleOn()` already returns false when the feature
		// key is not registered, so a missing key must not bypass the guard.
		if ( ! $registry->isToggleOn( $featureKey ) ) {
			return response()->json( [
				'message'     => __( 'This AI feature is disabled.' ),
				'feature_key' => $featureKey,
			], 409 );
		}

		$agentClass = self::AGENTS[ $featureKey ];

		try {
			$output = $agentClass::for( $input )->run();
		} catch ( FeatureDisabledException $exception ) {
			return response()->json( [
				'message'     => $exception->getMessage(),
				'feature_key' => $featureKey,
			], 409 );
		} catch ( MissingCredentialsException $exception ) {
			return response()->json( [
				'message'     => $exception->getMessage(),
				'feature_key' => $featureKey,
			], 412 );
		} catch ( FeatureError $exception ) {
			return response()->json( [
				'message'     => $exception->getMessage(),
				'feature_key' => $featureKey,
			], 422 );
		} catch ( Throwable $exception ) {
			return response()->json( [
				'message'     => __( 'AI agent failed to run.' ),
				'feature_key' => $featureKey,
			], 500 );
		}

		return response()->json( [
			'data'        => $output,
			'feature_key' => $featureKey,
		] );
	}
}
