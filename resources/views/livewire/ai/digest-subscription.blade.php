<div class="analytics-ai-digest-subscription" data-feature="analytics.digest_email">
	@if ( ! $this->isEnabled )
		<p class="analytics-ai-digest-subscription__disabled">
			{{ __( 'AI digest emails are currently disabled.' ) }}
		</p>
	@else
		<form wire:submit.prevent="save" class="analytics-ai-digest-subscription__form">
			<fieldset>
				<legend class="analytics-ai-digest-subscription__legend">
					{{ __( 'Analytics digest email' ) }}
				</legend>
				<p class="analytics-ai-digest-subscription__hint">
					{{ __( 'Receive a plain-language summary of your site\'s traffic on your chosen cadence.' ) }}
				</p>

				<label class="analytics-ai-digest-subscription__option">
					<input type="radio" wire:model="cadence" value="off" />
					<span>{{ __( 'Off' ) }}</span>
				</label>

				<label class="analytics-ai-digest-subscription__option">
					<input type="radio" wire:model="cadence" value="weekly" />
					<span>{{ __( 'Weekly' ) }}</span>
				</label>

				<label class="analytics-ai-digest-subscription__option">
					<input type="radio" wire:model="cadence" value="monthly" />
					<span>{{ __( 'Monthly' ) }}</span>
				</label>
			</fieldset>

			<button
				type="submit"
				class="analytics-ai-digest-subscription__save"
				wire:loading.attr="disabled"
				wire:target="save"
			>
				{{ __( 'Save preference' ) }}
			</button>

			@if ( null !== $status )
				<p class="analytics-ai-digest-subscription__status" role="status">
					{{ $status }}
				</p>
			@endif
		</form>
	@endif
</div>
