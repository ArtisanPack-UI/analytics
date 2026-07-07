/**
 * React trigger for the SegmentInsightAgent.
 *
 * @package    ArtisanPack_UI
 * @subpackage Analytics
 *
 * @since      1.3.0
 */

import type { FC } from 'react';

import { useAiAgent } from '../../hooks/useAiAgent';
import type { UseApiOptions } from '../../hooks/useApi';

export interface Segment {
	type: string;
	value: string;
}

interface SegmentInsightInput {
	segment: Segment;
	metrics: Record<string, unknown>;
	baseline: Record<string, unknown>;
}

export interface Pattern {
	observation: string;
	significance: 'low' | 'medium' | 'high';
	suggested_action: string;
}

export interface SegmentInsightResult {
	patterns: Pattern[];
}

export interface SegmentInsightProps {
	api: UseApiOptions;
	segment: Segment;
	metrics: Record<string, unknown>;
	baseline: Record<string, unknown>;
}

export const SegmentInsight: FC<SegmentInsightProps> = ( { api, segment, metrics, baseline } ) => {
	const { output, isLoading, error, run } = useAiAgent<SegmentInsightInput, SegmentInsightResult>(
		'analytics.segment_insight',
		api,
	);

	const disabled =
		isLoading ||
		segment.type.trim() === '' ||
		segment.value.trim() === '' ||
		Object.keys( metrics ).length === 0 ||
		Object.keys( baseline ).length === 0;

	const handleClick = (): void => {
		void run( { segment, metrics, baseline } );
	};

	return (
		<div className="analytics-ai-segment-insight" data-feature="analytics.segment_insight">
			<button
				type="button"
				onClick={ handleClick }
				disabled={ disabled }
				className="analytics-ai-segment-insight__button"
			>
				{ isLoading ? 'Analyzing segment…' : 'Find patterns in this segment' }
			</button>

			{ error && (
				<p className="analytics-ai-segment-insight__error" role="alert">
					{ error }
				</p>
			) }

			{ output && output.patterns.length > 0 && (
				<div className="analytics-ai-segment-insight__result">
					<h3 className="analytics-ai-segment-insight__heading">Patterns</h3>
					<ul className="analytics-ai-segment-insight__patterns">
						{ output.patterns.map( ( pattern, idx ) => (
							<li key={ idx } className="analytics-ai-segment-insight__pattern">
								<div className="analytics-ai-segment-insight__pattern-header">
									<span className="analytics-ai-segment-insight__observation">
										{ pattern.observation }
									</span>
									<span
										className={ `analytics-ai-segment-insight__significance analytics-ai-segment-insight__significance--${ pattern.significance }` }
									>
										{ pattern.significance }
									</span>
								</div>
								<p className="analytics-ai-segment-insight__action">
									<strong>Suggested action: </strong>
									{ pattern.suggested_action }
								</p>
							</li>
						) ) }
					</ul>
				</div>
			) }
		</div>
	);
};

export default SegmentInsight;
