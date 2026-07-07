/**
 * AI trigger components for the Analytics package.
 *
 * @package    ArtisanPack_UI
 * @subpackage Analytics
 *
 * @since      1.3.0
 */

export { AnomalyExplanation } from './AnomalyExplanation';
export type {
	AnomalyContext,
	AnomalyExplanationProps,
	AnomalyExplanationResult,
	AnomalyPayload,
	Hypothesis,
} from './AnomalyExplanation';

export { DigestSubscription } from './DigestSubscription';
export type { DigestCadence, DigestSubscriptionProps } from './DigestSubscription';

export { InsightSummary } from './InsightSummary';
export type { InsightSummaryProps, InsightSummaryResult } from './InsightSummary';

export { SegmentInsight } from './SegmentInsight';
export type {
	Pattern,
	Segment,
	SegmentInsightProps,
	SegmentInsightResult,
} from './SegmentInsight';
