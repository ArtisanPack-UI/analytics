<div>
	@if ( $isLoading )
		{{-- Loading State --}}
		@if ( $compact )
			<div class="flex items-center gap-4 animate-pulse">
				<div class="h-8 bg-base-300 rounded w-20"></div>
				<div class="h-8 bg-base-300 rounded w-20"></div>
			</div>
		@else
			<div class="card bg-base-100 shadow-sm">
				<div class="card-body">
					<div class="h-4 bg-base-300 rounded w-1/4 mb-4 animate-pulse"></div>
					<div class="grid grid-cols-3 gap-4">
						@foreach ( range( 1, 3 ) as $i )
							<div class="h-16 bg-base-300 rounded animate-pulse"></div>
						@endforeach
					</div>
					@if ( $showChart )
						<div class="h-24 bg-base-300 rounded mt-4 animate-pulse"></div>
					@endif
				</div>
			</div>
		@endif
	@else
		@if ( $compact )
			{{-- Compact View --}}
			<div class="flex items-center gap-6 text-sm">
				<div class="flex items-center gap-2">
					<x-artisanpack-icon name="o-eye" class="w-4 h-4 text-base-content/50" />
					<span class="font-medium">{{ $this->getFormattedPageViews() }}</span>
					<span class="text-base-content/50">{{ __( 'views' ) }}</span>
				</div>
				<div class="flex items-center gap-2">
					<x-artisanpack-icon name="o-users" class="w-4 h-4 text-base-content/50" />
					<span class="font-medium">{{ $this->getFormattedVisitors() }}</span>
					<span class="text-base-content/50">{{ __( 'visitors' ) }}</span>
				</div>
				<div class="flex items-center gap-2">
					<x-artisanpack-icon name="o-arrow-uturn-left" class="w-4 h-4 text-base-content/50" />
					<span class="font-medium">{{ $this->getFormattedBounceRate() }}</span>
					<span class="text-base-content/50">{{ __( 'bounce' ) }}</span>
				</div>
			</div>
		@else
			{{-- Full View --}}
			<div class="card bg-base-100 shadow-sm">
				<div class="card-body">
					{{-- Header --}}
					<div class="flex items-center justify-between mb-4">
						<div>
							<h3 class="font-semibold text-base-content">
								{{ __( 'Page Analytics' ) }}
							</h3>
							<p class="text-sm text-base-content/50 mt-1">{{ $path }}</p>
						</div>
						<div class="flex items-center gap-2">
							{{-- Date Range --}}
							<div class="dropdown dropdown-end">
								<label tabindex="0" class="btn btn-ghost btn-xs gap-1">
									<x-artisanpack-icon name="o-calendar" class="w-4 h-4" />
									{{ $this->getDateRangeLabel() }}
								</label>
								<ul tabindex="0" class="dropdown-content z-[1] menu p-2 shadow bg-base-100 rounded-box w-44">
									@foreach ( $this->getDateRangePresets() as $preset => $label )
										<li>
											<button
												type="button"
												wire:click="setDateRange('{{ $preset }}')"
												class="{{ $dateRangePreset === $preset ? 'active' : '' }}"
											>
												{{ $label }}
											</button>
										</li>
									@endforeach
								</ul>
							</div>
							{{-- Refresh --}}
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

					{{-- Stats Grid --}}
					<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
						{{-- Page Views --}}
						<div class="stat bg-base-200/50 rounded-lg p-4">
							<div class="stat-figure text-primary">
								<x-artisanpack-icon name="o-eye" class="w-6 h-6" />
							</div>
							<div class="stat-title text-xs">{{ __( 'Page Views' ) }}</div>
							<div class="stat-value text-xl">{{ $this->getFormattedPageViews() }}</div>
						</div>

						{{-- Visitors --}}
						<div class="stat bg-base-200/50 rounded-lg p-4">
							<div class="stat-figure text-secondary">
								<x-artisanpack-icon name="o-users" class="w-6 h-6" />
							</div>
							<div class="stat-title text-xs">{{ __( 'Visitors' ) }}</div>
							<div class="stat-value text-xl">{{ $this->getFormattedVisitors() }}</div>
						</div>

						{{-- Bounce Rate --}}
						<div class="stat bg-base-200/50 rounded-lg p-4">
							<div class="stat-figure text-accent">
								<x-artisanpack-icon name="o-arrow-uturn-left" class="w-6 h-6" />
							</div>
							<div class="stat-title text-xs">{{ __( 'Bounce Rate' ) }}</div>
							<div class="stat-value text-xl">{{ $this->getFormattedBounceRate() }}</div>
						</div>
					</div>

					{{-- Sparkline Chart --}}
					@if ( $showChart && $viewsOverTime->isNotEmpty() )
						<div
							style="height: 80px"
							x-data="{
								chart: null,
								init() {
									this.renderChart();
								},
								renderChart() {
									if (this.chart) {
										this.chart.destroy();
									}
									const ctx = this.$refs.canvas.getContext('2d');
									this.chart = new Chart(ctx, @js( $this->getSparklineConfig() ));
								}
							}"
							x-init="init"
							wire:ignore
						>
							<canvas x-ref="canvas"></canvas>
						</div>
					@endif
				</div>
			</div>
		@endif
	@endif
</div>
