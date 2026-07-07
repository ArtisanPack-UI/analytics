<!--
  Vue trigger for the AnomalyExplanationAgent.

  @package    ArtisanPack_UI
  @subpackage Analytics

  @since      1.3.0
-->

<script setup lang="ts">
import { computed } from 'vue';

import { useAiAgent } from '../../composables/useAiAgent';
import type { UseApiOptions } from '../../composables/useApi';

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

interface Input {
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

const props = defineProps<{
	api: UseApiOptions;
	anomaly: AnomalyPayload;
	context: AnomalyContext;
}>();

const { output, isLoading, error, run } = useAiAgent<Input, AnomalyExplanationResult>(
	'analytics.explain_anomaly',
	props.api,
);

const disabled = computed( () => isLoading.value || ! props.anomaly.metric );

const handleClick = (): void => {
	void run( { anomaly: props.anomaly, context: props.context } );
};
</script>

<template>
	<div class="analytics-ai-anomaly-explanation" data-feature="analytics.explain_anomaly">
		<button
			type="button"
			class="analytics-ai-anomaly-explanation__button"
			:disabled="disabled"
			@click="handleClick"
		>
			<template v-if="isLoading">Analyzing…</template>
			<template v-else>Explain this anomaly</template>
		</button>

		<p v-if="error" class="analytics-ai-anomaly-explanation__error" role="alert">
			{{ error }}
		</p>

		<div v-if="output && output.hypotheses.length > 0" class="analytics-ai-anomaly-explanation__result">
			<h3 class="analytics-ai-anomaly-explanation__heading">Hypotheses</h3>
			<ul class="analytics-ai-anomaly-explanation__hypotheses">
				<li
					v-for="( hypothesis, idx ) in output.hypotheses"
					:key="idx"
					class="analytics-ai-anomaly-explanation__hypothesis"
				>
					<div class="analytics-ai-anomaly-explanation__hypothesis-header">
						<span class="analytics-ai-anomaly-explanation__cause">
							{{ hypothesis.cause }}
						</span>
						<span
							class="analytics-ai-anomaly-explanation__confidence"
							:class="`analytics-ai-anomaly-explanation__confidence--${ hypothesis.confidence }`"
						>
							{{ hypothesis.confidence }}
						</span>
					</div>
					<ul
						v-if="hypothesis.evidence.length > 0"
						class="analytics-ai-anomaly-explanation__evidence"
					>
						<li v-for="( item, eIdx ) in hypothesis.evidence" :key="eIdx">{{ item }}</li>
					</ul>
				</li>
			</ul>

			<template v-if="output.recommended_next_steps.length > 0">
				<h4 class="analytics-ai-anomaly-explanation__subheading">Recommended next steps</h4>
				<ul class="analytics-ai-anomaly-explanation__steps">
					<li v-for="( step, idx ) in output.recommended_next_steps" :key="idx">{{ step }}</li>
				</ul>
			</template>
		</div>
	</div>
</template>
