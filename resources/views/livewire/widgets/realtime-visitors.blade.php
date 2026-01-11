<div wire:poll.{{ $pollingInterval }}ms="poll">
	<div class="card bg-base-100 shadow-sm">
		<div class="card-body">
			{{-- Header --}}
			<div class="flex items-center justify-between">
				<h3 class="font-semibold text-base-content">
					{{ __( 'Active Visitors' ) }}
				</h3>
				<div class="flex items-center gap-2">
					{{-- Polling Toggle --}}
					<button
						type="button"
						wire:click="togglePolling"
						class="btn btn-ghost btn-xs"
						title="{{ $pollingEnabled ? __( 'Pause updates' ) : __( 'Resume updates' ) }}"
					>
						@if ( $pollingEnabled )
							<x-artisanpack-icon name="o-pause" class="w-4 h-4" />
						@else
							<x-artisanpack-icon name="o-play" class="w-4 h-4" />
						@endif
					</button>
					{{-- Refresh Button --}}
					<button
						type="button"
						wire:click="refreshData"
						class="btn btn-ghost btn-xs"
						title="{{ __( 'Refresh' ) }}"
					>
						<x-artisanpack-icon name="o-arrow-path" class="w-4 h-4" />
					</button>
				</div>
			</div>

			{{-- Main Display --}}
			<div class="flex flex-col items-center justify-center py-6">
				{{-- Status Indicator --}}
				<div class="relative mb-4">
					<div class="{{ $this->getStatusClass() }} {{ $this->getPulseClass() }}">
						<x-artisanpack-icon name="o-signal" class="w-8 h-8" />
					</div>
					@if ( $visitorCount > 0 )
						<span class="absolute -top-1 -right-1 flex h-3 w-3">
							<span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-success opacity-75"></span>
							<span class="relative inline-flex rounded-full h-3 w-3 bg-success"></span>
						</span>
					@endif
				</div>

				{{-- Visitor Count --}}
				<div
					class="text-5xl font-bold text-base-content mb-2"
					x-data="{ count: {{ $visitorCount }} }"
					x-init="$watch('$wire.visitorCount', value => {
						count = value;
					})"
				>
					<span x-text="count">{{ $visitorCount }}</span>
				</div>

				{{-- Label --}}
				<p class="text-base-content/70">
					{{ trans_choice( 'visitor online now|visitors online now', $visitorCount ) }}
				</p>

				{{-- Trend Indicator --}}
				@if ( $previousCount > 0 && $this->getTrend() !== 'stable' )
					<div class="mt-3 flex items-center gap-1 text-sm">
						@if ( $this->getTrend() === 'up' )
							<span class="text-success flex items-center gap-1">
								<x-artisanpack-icon name="o-arrow-up" class="w-4 h-4" />
								+{{ $this->getTrendDifference() }}
							</span>
						@else
							<span class="text-error flex items-center gap-1">
								<x-artisanpack-icon name="o-arrow-down" class="w-4 h-4" />
								-{{ $this->getTrendDifference() }}
							</span>
						@endif
						<span class="text-base-content/50">
							{{ __( 'since last update' ) }}
						</span>
					</div>
				@endif
			</div>

			{{-- Footer --}}
			<div class="flex items-center justify-between text-xs text-base-content/50">
				<span>
					{{ __( 'Active in last :minutes minutes', ['minutes' => $activeMinutes] ) }}
				</span>
				@if ( $lastUpdated )
					<span>
						{{ __( 'Updated' ) }}: {{ \Carbon\Carbon::parse( $lastUpdated )->diffForHumans() }}
					</span>
				@endif
			</div>
		</div>
	</div>
</div>
