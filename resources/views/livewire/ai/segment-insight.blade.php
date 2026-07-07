<div class="analytics-ai-segment-insight" data-feature="analytics.segment_insight">
	@if ( ! $this->isEnabled )
		<p class="analytics-ai-segment-insight__disabled">
			{{ __( 'AI segment insights are currently disabled.' ) }}
		</p>
	@else
		<button
			type="button"
			wire:click="analyze"
			wire:loading.attr="disabled"
			wire:target="analyze"
			@disabled( $isLoading || '' === trim( $segmentType ) || '' === trim( $segmentValue ) || [] === $metrics || [] === $baseline )
			class="analytics-ai-segment-insight__button"
		>
			<span wire:loading.remove wire:target="analyze">
				{{ __( 'Find patterns in this segment' ) }}
			</span>
			<span wire:loading wire:target="analyze">
				{{ __( 'Analyzing segment…' ) }}
			</span>
		</button>

		@if ( null !== $error )
			<p class="analytics-ai-segment-insight__error" role="alert">
				{{ $error }}
			</p>
		@endif

		@if ( ! empty( $patterns ) )
			<div class="analytics-ai-segment-insight__result">
				<h3 class="analytics-ai-segment-insight__heading">{{ __( 'Patterns' ) }}</h3>
				<ul class="analytics-ai-segment-insight__patterns">
					@foreach ( $patterns as $index => $pattern )
						<li wire:key="pa-{{ $index }}" class="analytics-ai-segment-insight__pattern">
							<div class="analytics-ai-segment-insight__pattern-header">
								<span class="analytics-ai-segment-insight__observation">{{ $pattern['observation'] }}</span>
								<span class="analytics-ai-segment-insight__significance analytics-ai-segment-insight__significance--{{ $pattern['significance'] }}">
									{{ __( ucfirst( $pattern['significance'] ) ) }}
								</span>
							</div>
							<p class="analytics-ai-segment-insight__action">
								<strong>{{ __( 'Suggested action:' ) }}</strong>
								{{ $pattern['suggested_action'] }}
							</p>
						</li>
					@endforeach
				</ul>
			</div>
		@endif
	@endif
</div>
