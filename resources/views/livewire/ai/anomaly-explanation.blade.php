<div class="analytics-ai-anomaly-explanation" data-feature="analytics.explain_anomaly">
	@if ( ! $this->isEnabled )
		<p class="analytics-ai-anomaly-explanation__disabled">
			{{ __( 'AI anomaly explanations are currently disabled.' ) }}
		</p>
	@else
		<button
			type="button"
			wire:click="explain"
			wire:loading.attr="disabled"
			wire:target="explain"
			@disabled( $isLoading || [] === $anomaly )
			class="analytics-ai-anomaly-explanation__button"
		>
			<span wire:loading.remove wire:target="explain">
				{{ __( 'Explain this anomaly' ) }}
			</span>
			<span wire:loading wire:target="explain">
				{{ __( 'Analyzing…' ) }}
			</span>
		</button>

		@if ( null !== $error )
			<p class="analytics-ai-anomaly-explanation__error" role="alert">
				{{ $error }}
			</p>
		@endif

		@if ( ! empty( $hypotheses ) )
			<div class="analytics-ai-anomaly-explanation__result">
				<h3 class="analytics-ai-anomaly-explanation__heading">{{ __( 'Hypotheses' ) }}</h3>
				<ul class="analytics-ai-anomaly-explanation__hypotheses">
					@foreach ( $hypotheses as $index => $hypothesis )
						<li wire:key="hy-{{ $index }}" class="analytics-ai-anomaly-explanation__hypothesis">
							<div class="analytics-ai-anomaly-explanation__hypothesis-header">
								<span class="analytics-ai-anomaly-explanation__cause">{{ $hypothesis['cause'] }}</span>
								<span class="analytics-ai-anomaly-explanation__confidence analytics-ai-anomaly-explanation__confidence--{{ $hypothesis['confidence'] }}">
									{{ __( ucfirst( $hypothesis['confidence'] ) ) }}
								</span>
							</div>
							@if ( ! empty( $hypothesis['evidence'] ) )
								<ul class="analytics-ai-anomaly-explanation__evidence">
									@foreach ( $hypothesis['evidence'] as $evidenceIndex => $evidence )
										<li wire:key="ev-{{ $index }}-{{ $evidenceIndex }}">{{ $evidence }}</li>
									@endforeach
								</ul>
							@endif
						</li>
					@endforeach
				</ul>

				@if ( ! empty( $recommendedNextSteps ) )
					<h4 class="analytics-ai-anomaly-explanation__subheading">{{ __( 'Recommended next steps' ) }}</h4>
					<ul class="analytics-ai-anomaly-explanation__steps">
						@foreach ( $recommendedNextSteps as $stepIndex => $step )
							<li wire:key="st-{{ $stepIndex }}">{{ $step }}</li>
						@endforeach
					</ul>
				@endif
			</div>
		@endif
	@endif
</div>
