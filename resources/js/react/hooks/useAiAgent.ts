/**
 * useAiAgent hook — thin wrapper over useApi for the Analytics AI endpoints.
 *
 * @package    ArtisanPack_UI
 * @subpackage Analytics
 *
 * @since      1.3.0
 */

import { useCallback, useState } from 'react';

import { ApiError, ApiValidationError, useApi, type UseApiOptions } from './useApi';

/** Supported AI feature keys owned by the Analytics package. */
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
	output: TOutput | null;
	isLoading: boolean;
	error: string | null;
	run: ( input: TInput ) => Promise<TOutput | null>;
	reset: () => void;
}

export function useAiAgent<TInput, TOutput>(
	feature: AnalyticsAiFeatureKey,
	options: UseApiOptions,
): UseAiAgentReturn<TInput, TOutput> {
	const api = useApi( options );
	const [ output, setOutput ] = useState<TOutput | null>( null );
	const [ isLoading, setIsLoading ] = useState<boolean>( false );
	const [ error, setError ] = useState<string | null>( null );

	const reset = useCallback( () => {
		setOutput( null );
		setError( null );
	}, [] );

	const run = useCallback(
		async ( input: TInput ): Promise<TOutput | null> => {
			setIsLoading( true );
			setError( null );

			try {
				const response = await api.post<AgentResponse<TOutput>>( ENDPOINT_MAP[ feature ], input );
				setOutput( response.data );
				return response.data;
			} catch ( caught ) {
				if ( caught instanceof ApiValidationError ) {
					const firstField = Object.keys( caught.errors )[0];
					setError( firstField ? caught.errors[ firstField ][0] : caught.message );
				} else if ( caught instanceof ApiError ) {
					setError( caught.message );
				} else if ( caught instanceof Error ) {
					setError( caught.message );
				} else {
					setError( 'Request failed.' );
				}

				return null;
			} finally {
				setIsLoading( false );
			}
		},
		[ api, feature ],
	);

	return { output, isLoading, error, run, reset };
}
