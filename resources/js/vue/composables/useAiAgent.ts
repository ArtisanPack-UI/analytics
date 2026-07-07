/**
 * useAiAgent composable — thin wrapper for the Analytics AI endpoints.
 *
 * @package    ArtisanPack_UI
 * @subpackage Analytics
 *
 * @since      1.3.0
 */

import { ref, type Ref } from 'vue';

import { ApiError, ApiValidationError, useApi, type UseApiOptions } from './useApi';

export type AnalyticsAiFeatureKey =
	| 'analytics.insight_summary'
	| 'analytics.explain_anomaly'
	| 'analytics.segment_insight'
	| 'analytics.digest_email';

const ENDPOINT_MAP: Record<AnalyticsAiFeatureKey, string> = {
	'analytics.insight_summary': '/ai/insight-summary',
	'analytics.explain_anomaly': '/ai/explain-anomaly',
	'analytics.segment_insight': '/ai/segment-insight',
	'analytics.digest_email': '/ai/digest-email',
};

export interface AgentResponse<TOutput> {
	data: TOutput;
	feature_key: AnalyticsAiFeatureKey;
}

export interface UseAiAgentReturn<TInput, TOutput> {
	output: Ref<TOutput | null>;
	isLoading: Ref<boolean>;
	error: Ref<string | null>;
	run: ( input: TInput ) => Promise<TOutput | null>;
	reset: () => void;
}

export function useAiAgent<TInput, TOutput>(
	feature: AnalyticsAiFeatureKey,
	options: UseApiOptions,
): UseAiAgentReturn<TInput, TOutput> {
	const api = useApi( options );
	const output = ref<TOutput | null>( null ) as Ref<TOutput | null>;
	const isLoading = ref<boolean>( false );
	const error = ref<string | null>( null );

	const reset = (): void => {
		output.value = null;
		error.value = null;
	};

	const run = async ( input: TInput ): Promise<TOutput | null> => {
		isLoading.value = true;
		error.value = null;

		try {
			const response = await api.post<AgentResponse<TOutput>>( ENDPOINT_MAP[ feature ], input );
			output.value = response.data;
			return response.data;
		} catch ( caught ) {
			if ( caught instanceof ApiValidationError ) {
				const firstField = Object.keys( caught.errors )[0];
				error.value = firstField ? caught.errors[ firstField ][0] : caught.message;
			} else if ( caught instanceof ApiError ) {
				error.value = caught.message;
			} else if ( caught instanceof Error ) {
				error.value = caught.message;
			} else {
				error.value = 'Request failed.';
			}

			return null;
		} finally {
			isLoading.value = false;
		}
	};

	return { output, isLoading, error, run, reset };
}
