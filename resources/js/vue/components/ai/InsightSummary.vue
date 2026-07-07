<!--
  Vue trigger for the InsightSummaryAgent.

  @package    ArtisanPack_UI
  @subpackage Analytics

  @since      1.3.0
-->

<script setup lang="ts">
import { computed } from 'vue';

import { useAiAgent } from '../../composables/useAiAgent';
import type { UseApiOptions } from '../../composables/useApi';

interface DateRange {
	from: string;
	to: string;
}

interface Input {
	date_range: DateRange;
	metrics: Record<string, unknown>;
	compare_to?: DateRange;
}

export interface InsightSummaryResult {
	summary: string;
	highlights: string[];
	concerns: string[];
}

const props = withDefaults(
	defineProps<{
		api: UseApiOptions;
		dateRange: DateRange;
		metrics: Record<string, unknown>;
		compareTo?: DateRange;
	}>(),
	{},
);

const { output, isLoading, error, run } = useAiAgent<Input, InsightSummaryResult>(
	'analytics.insight_summary',
	props.api,
);

const disabled = computed(
	() =>
		isLoading.value ||
		props.dateRange.from.trim() === '' ||
		props.dateRange.to.trim() === '' ||
		Object.keys( props.metrics ).length === 0,
);

const handleClick = (): void => {
	void run( {
		date_range: props.dateRange,
		metrics: props.metrics,
		...( props.compareTo ? { compare_to: props.compareTo } : {} ),
	} );
};
</script>

<template>
	<div class="analytics-ai-insight-summary" data-feature="analytics.insight_summary">
		<button
			type="button"
			class="analytics-ai-insight-summary__button"
			:disabled="disabled"
			@click="handleClick"
		>
			<template v-if="isLoading">Summarizing…</template>
			<template v-else>Summarize this window</template>
		</button>

		<p v-if="error" class="analytics-ai-insight-summary__error" role="alert">
			{{ error }}
		</p>

		<div v-if="output" class="analytics-ai-insight-summary__result">
			<h3 class="analytics-ai-insight-summary__heading">Summary</h3>
			<p class="analytics-ai-insight-summary__summary">{{ output.summary }}</p>

			<template v-if="output.highlights.length > 0">
				<h4 class="analytics-ai-insight-summary__subheading">Highlights</h4>
				<ul class="analytics-ai-insight-summary__list">
					<li v-for="( item, idx ) in output.highlights" :key="idx">{{ item }}</li>
				</ul>
			</template>

			<template v-if="output.concerns.length > 0">
				<h4 class="analytics-ai-insight-summary__subheading">Concerns</h4>
				<ul class="analytics-ai-insight-summary__list">
					<li v-for="( item, idx ) in output.concerns" :key="idx">{{ item }}</li>
				</ul>
			</template>
		</div>
	</div>
</template>
