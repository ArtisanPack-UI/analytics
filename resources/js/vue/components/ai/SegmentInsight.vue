<!--
  Vue trigger for the SegmentInsightAgent.

  @package    ArtisanPack_UI
  @subpackage Analytics

  @since      1.3.0
-->

<script setup lang="ts">
import { computed } from 'vue';

import { useAiAgent } from '../../composables/useAiAgent';
import type { UseApiOptions } from '../../composables/useApi';

export interface Segment {
	type: string;
	value: string;
}

interface Input {
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

const props = defineProps<{
	api: UseApiOptions;
	segment: Segment;
	metrics: Record<string, unknown>;
	baseline: Record<string, unknown>;
}>();

const { output, isLoading, error, run } = useAiAgent<Input, SegmentInsightResult>(
	'analytics.segment_insight',
	props.api,
);

const disabled = computed(
	() =>
		isLoading.value ||
		props.segment.type.trim() === '' ||
		props.segment.value.trim() === '' ||
		Object.keys( props.metrics ).length === 0 ||
		Object.keys( props.baseline ).length === 0,
);

const handleClick = (): void => {
	void run( {
		segment: props.segment,
		metrics: props.metrics,
		baseline: props.baseline,
	} );
};
</script>

<template>
	<div class="analytics-ai-segment-insight" data-feature="analytics.segment_insight">
		<button
			type="button"
			class="analytics-ai-segment-insight__button"
			:disabled="disabled"
			@click="handleClick"
		>
			<template v-if="isLoading">Analyzing segment…</template>
			<template v-else>Find patterns in this segment</template>
		</button>

		<p v-if="error" class="analytics-ai-segment-insight__error" role="alert">
			{{ error }}
		</p>

		<div v-if="output && output.patterns.length > 0" class="analytics-ai-segment-insight__result">
			<h3 class="analytics-ai-segment-insight__heading">Patterns</h3>
			<ul class="analytics-ai-segment-insight__patterns">
				<li
					v-for="( pattern, idx ) in output.patterns"
					:key="idx"
					class="analytics-ai-segment-insight__pattern"
				>
					<div class="analytics-ai-segment-insight__pattern-header">
						<span class="analytics-ai-segment-insight__observation">
							{{ pattern.observation }}
						</span>
						<span
							class="analytics-ai-segment-insight__significance"
							:class="`analytics-ai-segment-insight__significance--${ pattern.significance }`"
						>
							{{ pattern.significance }}
						</span>
					</div>
					<p class="analytics-ai-segment-insight__action">
						<strong>Suggested action: </strong>
						{{ pattern.suggested_action }}
					</p>
				</li>
			</ul>
		</div>
	</div>
</template>
