<div class="analytics-ai-insight-summary" data-feature="analytics.insight_summary">
	@if ( ! $this->isEnabled )
		<p class="analytics-ai-insight-summary__disabled">
			{{ __( 'AI insight summaries are currently disabled.' ) }}
		</p>
	@else
		<button
			type="button"
			wire:click="summarize"
			wire:loading.attr="disabled"
			wire:target="summarize"
			@disabled( $isLoading || '' === trim( $dateFrom ) || '' === trim( $dateTo ) || [] === $metrics )
			class="analytics-ai-insight-summary__button"
		>
			<span wire:loading.remove wire:target="summarize">
				{{ __( 'Summarize this window' ) }}
			</span>
			<span wire:loading wire:target="summarize">
				{{ __( 'Summarizing…' ) }}
			</span>
		</button>

		@if ( null !== $error )
			<p class="analytics-ai-insight-summary__error" role="alert">
				{{ $error }}
			</p>
		@endif

		@if ( null !== $summary )
			<div class="analytics-ai-insight-summary__result">
				<h3 class="analytics-ai-insight-summary__heading">{{ __( 'Summary' ) }}</h3>
				<p class="analytics-ai-insight-summary__summary">{{ $summary }}</p>

				@if ( ! empty( $highlights ) )
					<h4 class="analytics-ai-insight-summary__subheading">{{ __( 'Highlights' ) }}</h4>
					<ul class="analytics-ai-insight-summary__list">
						@foreach ( $highlights as $index => $highlight )
							<li wire:key="hi-{{ $index }}">{{ $highlight }}</li>
						@endforeach
					</ul>
				@endif

				@if ( ! empty( $concerns ) )
					<h4 class="analytics-ai-insight-summary__subheading">{{ __( 'Concerns' ) }}</h4>
					<ul class="analytics-ai-insight-summary__list">
						@foreach ( $concerns as $index => $concern )
							<li wire:key="co-{{ $index }}">{{ $concern }}</li>
						@endforeach
					</ul>
				@endif
			</div>
		@endif
	@endif
</div>
