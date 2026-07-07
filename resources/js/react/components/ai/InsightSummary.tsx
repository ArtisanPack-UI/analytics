/**
 * React trigger for the InsightSummaryAgent.
 *
 * @package    ArtisanPack_UI
 * @subpackage Analytics
 *
 * @since      1.3.0
 */

import type { FC } from 'react';

import { useAiAgent } from '../../hooks/useAiAgent';
import type { UseApiOptions } from '../../hooks/useApi';

interface DateRange {
	from: string;
	to: string;
}

interface InsightSummaryInput {
	date_range: DateRange;
	metrics: Record<string, unknown>;
	compare_to?: DateRange;
}

export interface InsightSummaryResult {
	summary: string;
	highlights: string[];
	concerns: string[];
}

export interface InsightSummaryProps {
	api: UseApiOptions;
	dateRange: DateRange;
	metrics: Record<string, unknown>;
	compareTo?: DateRange;
}

export const InsightSummary: FC<InsightSummaryProps> = ( { api, dateRange, metrics, compareTo } ) => {
	const { output, isLoading, error, run } = useAiAgent<InsightSummaryInput, InsightSummaryResult>(
		'analytics.insight_summary',
		api,
	);

	const disabled =
		isLoading ||
		dateRange.from.trim() === '' ||
		dateRange.to.trim() === '' ||
		Object.keys( metrics ).length === 0;

	const handleClick = (): void => {
		void run( {
			date_range: dateRange,
			metrics,
			...( compareTo ? { compare_to: compareTo } : {} ),
		} );
	};

	return (
		<div className="analytics-ai-insight-summary" data-feature="analytics.insight_summary">
			<button
				type="button"
				onClick={ handleClick }
				disabled={ disabled }
				className="analytics-ai-insight-summary__button"
			>
				{ isLoading ? 'Summarizing…' : 'Summarize this window' }
			</button>

			{ error && (
				<p className="analytics-ai-insight-summary__error" role="alert">
					{ error }
				</p>
			) }

			{ output && (
				<div className="analytics-ai-insight-summary__result">
					<h3 className="analytics-ai-insight-summary__heading">Summary</h3>
					<p className="analytics-ai-insight-summary__summary">{ output.summary }</p>

					{ output.highlights.length > 0 && (
						<>
							<h4 className="analytics-ai-insight-summary__subheading">Highlights</h4>
							<ul className="analytics-ai-insight-summary__list">
								{ output.highlights.map( ( item, idx ) => (
									<li key={ idx }>{ item }</li>
								) ) }
							</ul>
						</>
					) }

					{ output.concerns.length > 0 && (
						<>
							<h4 className="analytics-ai-insight-summary__subheading">Concerns</h4>
							<ul className="analytics-ai-insight-summary__list">
								{ output.concerns.map( ( item, idx ) => (
									<li key={ idx }>{ item }</li>
								) ) }
							</ul>
						</>
					) }
				</div>
			) }
		</div>
	);
};

export default InsightSummary;
