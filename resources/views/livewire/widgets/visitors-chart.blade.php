<div>
	{{-- Loading State --}}
	@if ( $isLoading )
		<div class="card bg-base-100 shadow-sm">
			<div class="card-body">
				<div class="h-4 bg-base-300 rounded w-1/4 mb-4 animate-pulse"></div>
				<div class="bg-base-300 rounded animate-pulse" style="height: {{ $height }}px"></div>
			</div>
		</div>
	@else
		<div class="card bg-base-100 shadow-sm">
			<div class="card-body">
				{{-- Header --}}
				<div class="flex items-center justify-between mb-4">
					<h3 class="font-semibold text-base-content">
						{{ __( 'Visitors Over Time' ) }}
					</h3>

					{{-- Granularity Selector --}}
					<div class="flex gap-1">
						@foreach ( ['hour' => __( 'Hourly' ), 'day' => __( 'Daily' ), 'week' => __( 'Weekly' ), 'month' => __( 'Monthly' )] as $key => $label )
							<button
								type="button"
								wire:click="setGranularity('{{ $key }}')"
								class="btn btn-xs {{ $granularity === $key ? 'btn-primary' : 'btn-ghost' }}"
							>
								{{ $label }}
							</button>
						@endforeach
					</div>
				</div>

				{{-- Chart Container or Empty State --}}
				@if ( ! empty( $chartData['labels'] ) )
					<div
						style="height: {{ $height }}px"
						x-data="{
							chart: null,
							init() {
								this.renderChart();
								$wire.on('chartDataUpdated', () => {
									this.renderChart();
								});
							},
							renderChart() {
								if (this.chart) {
									this.chart.destroy();
								}
								const ctx = this.$refs.canvas.getContext('2d');
								this.chart = new Chart(ctx, @js( $this->getChartConfig() ));
							}
						}"
						x-init="init"
						wire:ignore
					>
						<canvas x-ref="canvas"></canvas>
					</div>
				@else
					<div
						class="flex flex-col items-center justify-center text-base-content/50"
						style="height: {{ $height }}px"
					>
						<x-artisanpack-icon name="o-chart-bar" class="w-12 h-12 mb-2" />
						<p>{{ __( 'No data available for this period' ) }}</p>
					</div>
				@endif
			</div>
		</div>
	@endif
</div>
