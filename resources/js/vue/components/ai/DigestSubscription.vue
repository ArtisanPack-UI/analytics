<!--
  Vue opt-in UI for the AI analytics digest email.

  @package    ArtisanPack_UI
  @subpackage Analytics

  @since      1.3.0
-->

<script setup lang="ts">
import { ref } from 'vue';

import { useApi, type UseApiOptions } from '../../composables/useApi';

export type DigestCadence = 'off' | 'weekly' | 'monthly';

const props = withDefaults(
	defineProps<{
		api: UseApiOptions;
		initialCadence?: DigestCadence;
		updatePath?: string;
	}>(),
	{
		initialCadence: 'off',
		updatePath: '/ai/digest-subscription',
	},
);

const apiClient = useApi( props.api );
const cadence = ref<DigestCadence>( props.initialCadence );
const isSaving = ref( false );
const status = ref<string | null>( null );
const error = ref<string | null>( null );

const OPTIONS: DigestCadence[] = [ 'off', 'weekly', 'monthly' ];

const save = async (): Promise<void> => {
	isSaving.value = true;
	status.value = null;
	error.value = null;

	try {
		await apiClient.post( props.updatePath, { cadence: cadence.value } );
		status.value = 'off' === cadence.value ? 'Digest emails turned off.' : 'Digest preference saved.';
	} catch ( caught ) {
		error.value = caught instanceof Error ? caught.message : 'Could not save preference.';
	} finally {
		isSaving.value = false;
	}
};
</script>

<template>
	<div class="analytics-ai-digest-subscription" data-feature="analytics.digest_email">
		<form class="analytics-ai-digest-subscription__form" @submit.prevent="save">
			<fieldset>
				<legend class="analytics-ai-digest-subscription__legend">
					Analytics digest email
				</legend>
				<p class="analytics-ai-digest-subscription__hint">
					Receive a plain-language summary of your site's traffic on your chosen cadence.
				</p>

				<label
					v-for="value in OPTIONS"
					:key="value"
					class="analytics-ai-digest-subscription__option"
				>
					<input
						type="radio"
						name="cadence"
						:value="value"
						:checked="cadence === value"
						@change="cadence = value"
					/>
					<span>{{ value.charAt( 0 ).toUpperCase() + value.slice( 1 ) }}</span>
				</label>
			</fieldset>

			<button
				type="submit"
				class="analytics-ai-digest-subscription__save"
				:disabled="isSaving"
			>
				<template v-if="isSaving">Saving…</template>
				<template v-else>Save preference</template>
			</button>

			<p v-if="status" class="analytics-ai-digest-subscription__status" role="status">
				{{ status }}
			</p>

			<p v-if="error" class="analytics-ai-digest-subscription__error" role="alert">
				{{ error }}
			</p>
		</form>
	</div>
</template>
