/**
 * React trigger for the AnomalyExplanationAgent.
 *
 * @package    ArtisanPack_UI
 * @subpackage Analytics
 *
 * @since      1.3.0
 */

import type { FC } from 'react';

import { useAiAgent } from '../../hooks/useAiAgent';
import type { UseApiOptions } from '../../hooks/useApi';

export interface AnomalyPayload {
	metric: string;
	direction: 'up' | 'down';
	magnitude: number;
	date: string;
}

export interface AnomalyContext {
	recent_content_changes?: unknown[];
	referrer_deltas?: unknown[];
	campaign_launches?: unknown[];
	[key: string]: unknown;
}

interface AnomalyExplanationInput {
	anomaly: AnomalyPayload;
	context: AnomalyContext;
}

export interface Hypothesis {
	cause: string;
	confidence: 'low' | 'medium' | 'high';
	evidence: string[];
}

export interface AnomalyExplanationResult {
	hypotheses: Hypothesis[];
	recommended_next_steps: string[];
}

export interface AnomalyExplanationProps {
	api: UseApiOptions;
	anomaly: AnomalyPayload;
	context: AnomalyContext;
}

export const AnomalyExplanation: FC<AnomalyExplanationProps> = ( { api, anomaly, context } ) => {
	const { output, isLoading, error, run } = useAiAgent<AnomalyExplanationInput, AnomalyExplanationResult>(
		'analytics.explain_anomaly',
		api,
	);

	const disabled = isLoading || ! anomaly.metric;

	const handleClick = (): void => {
		void run( { anomaly, context } );
	};

	return (
		<div className="analytics-ai-anomaly-explanation" data-feature="analytics.explain_anomaly">
			<button
				type="button"
				onClick={ handleClick }
				disabled={ disabled }
				className="analytics-ai-anomaly-explanation__button"
			>
				{ isLoading ? 'Analyzing…' : 'Explain this anomaly' }
			</button>

			{ error && (
				<p className="analytics-ai-anomaly-explanation__error" role="alert">
					{ error }
				</p>
			) }

			{ output && output.hypotheses.length > 0 && (
				<div className="analytics-ai-anomaly-explanation__result">
					<h3 className="analytics-ai-anomaly-explanation__heading">Hypotheses</h3>
					<ul className="analytics-ai-anomaly-explanation__hypotheses">
						{ output.hypotheses.map( ( hypothesis, idx ) => (
							<li key={ idx } className="analytics-ai-anomaly-explanation__hypothesis">
								<div className="analytics-ai-anomaly-explanation__hypothesis-header">
									<span className="analytics-ai-anomaly-explanation__cause">
										{ hypothesis.cause }
									</span>
									<span
										className={ `analytics-ai-anomaly-explanation__confidence analytics-ai-anomaly-explanation__confidence--${ hypothesis.confidence }` }
									>
										{ hypothesis.confidence }
									</span>
								</div>
								{ hypothesis.evidence.length > 0 && (
									<ul className="analytics-ai-anomaly-explanation__evidence">
										{ hypothesis.evidence.map( ( item, eIdx ) => (
											<li key={ eIdx }>{ item }</li>
										) ) }
									</ul>
								) }
							</li>
						) ) }
					</ul>

					{ output.recommended_next_steps.length > 0 && (
						<>
							<h4 className="analytics-ai-anomaly-explanation__subheading">
								Recommended next steps
							</h4>
							<ul className="analytics-ai-anomaly-explanation__steps">
								{ output.recommended_next_steps.map( ( step, idx ) => (
									<li key={ idx }>{ step }</li>
								) ) }
							</ul>
						</>
					) }
				</div>
			) }
		</div>
	);
};

export default AnomalyExplanation;
