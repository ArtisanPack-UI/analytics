<div>
	{{-- Dashboard Header --}}
	<div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
		<h1 class="text-2xl font-bold text-base-content">
			{{ __( 'Analytics Dashboard' ) }}
		</h1>

		<div class="flex flex-wrap items-center gap-2">
			{{-- Date Range Selector --}}
			<div class="dropdown dropdown-end">
				<label tabindex="0" class="btn btn-sm btn-outline gap-2">
					<x-artisanpack-icon name="o-calendar" class="w-4 h-4" />
					{{ $this->getDateRangeLabel() }}
					<x-artisanpack-icon name="o-chevron-down" class="w-4 h-4" />
				</label>
				<ul tabindex="0" class="dropdown-content z-[1] menu p-2 shadow bg-base-100 rounded-box w-52">
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

			{{-- Export Dropdown --}}
			<div class="dropdown dropdown-end">
				<label tabindex="0" class="btn btn-sm btn-outline gap-2">
					<x-artisanpack-icon name="o-arrow-down-tray" class="w-4 h-4" />
					{{ __( 'Export' ) }}
				</label>
				<ul tabindex="0" class="dropdown-content z-[1] menu p-2 shadow bg-base-100 rounded-box w-40">
					<li>
						<button type="button" wire:click="exportCsv">
							<x-artisanpack-icon name="o-document-text" class="w-4 h-4" />
							{{ __( 'CSV' ) }}
						</button>
					</li>
					<li>
						<button type="button" wire:click="exportJson">
							<x-artisanpack-icon name="o-code-bracket" class="w-4 h-4" />
							{{ __( 'JSON' ) }}
						</button>
					</li>
				</ul>
			</div>

			{{-- Refresh Button --}}
			<button
				type="button"
				wire:click="refreshData"
				class="btn btn-sm btn-outline"
				title="{{ __( 'Refresh' ) }}"
			>
				<x-artisanpack-icon name="o-arrow-path" class="w-4 h-4" wire:loading.class="animate-spin" wire:target="refreshData" />
			</button>
		</div>
	</div>

	{{-- Tabs --}}
	<div class="tabs tabs-boxed mb-6">
		@foreach ( $this->getTabs() as $key => $tab )
			<button
				type="button"
				wire:click="switchTab('{{ $key }}')"
				class="tab gap-2 {{ $activeTab === $key ? 'tab-active' : '' }}"
			>
				<x-artisanpack-icon name="o-{{ $tab['icon'] }}" class="w-4 h-4" />
				{{ $tab['label'] }}
			</button>
		@endforeach
	</div>

	{{-- Loading Overlay --}}
	<div wire:loading.flex wire:target="refreshData, switchTab, setDateRange" class="fixed inset-0 bg-base-100/50 z-50 items-center justify-center">
		<div class="loading loading-spinner loading-lg text-primary"></div>
	</div>

	{{-- Tab Content --}}
	<div class="space-y-6">
		{{-- Overview Tab --}}
		@if ( $activeTab === 'overview' )
			{{-- Stats Cards --}}
			<livewire:artisanpack-analytics::stats-cards
				:date-range-preset="$dateRangePreset"
				:site-id="$siteId"
				:stats="$stats"
			/>

			{{-- Chart --}}
			<div class="card bg-base-100 shadow-sm">
				<div class="card-body">
					<h3 class="font-semibold text-base-content mb-4">
						{{ __( 'Visitors Over Time' ) }}
					</h3>
					<div
						style="height: 300px"
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
								this.chart = new Chart(ctx, {
									type: 'line',
									data: @js( $chartData ),
									options: {
										responsive: true,
										maintainAspectRatio: false,
										plugins: {
											legend: {
												position: 'bottom',
											}
										},
										scales: {
											y: {
												beginAtZero: true
											}
										}
									}
								});
							}
						}"
						x-init="init"
						wire:ignore
					>
						<canvas x-ref="canvas"></canvas>
					</div>
				</div>
			</div>

			{{-- Quick Stats Row --}}
			<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
				{{-- Top Pages Preview --}}
				<div class="card bg-base-100 shadow-sm">
					<div class="card-body">
						<div class="flex items-center justify-between mb-4">
							<h3 class="font-semibold text-base-content">
								{{ __( 'Top Pages' ) }}
							</h3>
							<button
								type="button"
								wire:click="switchTab('pages')"
								class="btn btn-ghost btn-xs"
							>
								{{ __( 'View All' ) }}
							</button>
						</div>
						<div class="overflow-x-auto">
							<table class="table table-sm">
								<tbody>
									@forelse ( $topPages->take( 5 ) as $page )
										<tr class="hover:bg-base-200/50">
											<td class="max-w-xs truncate">{{ $page['path'] }}</td>
											<td class="text-right font-mono">{{ number_format( $page['views'] ?? 0 ) }}</td>
										</tr>
									@empty
										<tr>
											<td colspan="2" class="text-center text-base-content/50">
												{{ __( 'No data available' ) }}
											</td>
										</tr>
									@endforelse
								</tbody>
							</table>
						</div>
					</div>
				</div>

				{{-- Traffic Sources Preview --}}
				<div class="card bg-base-100 shadow-sm">
					<div class="card-body">
						<div class="flex items-center justify-between mb-4">
							<h3 class="font-semibold text-base-content">
								{{ __( 'Traffic Sources' ) }}
							</h3>
							<button
								type="button"
								wire:click="switchTab('traffic')"
								class="btn btn-ghost btn-xs"
							>
								{{ __( 'View All' ) }}
							</button>
						</div>
						<div class="overflow-x-auto">
							<table class="table table-sm">
								<tbody>
									@forelse ( $trafficSources->take( 5 ) as $source )
										<tr class="hover:bg-base-200/50">
											<td>{{ $source['source'] ?? __( 'Direct' ) }}</td>
											<td class="text-right font-mono">{{ number_format( $source['sessions'] ?? 0 ) }}</td>
										</tr>
									@empty
										<tr>
											<td colspan="2" class="text-center text-base-content/50">
												{{ __( 'No data available' ) }}
											</td>
										</tr>
									@endforelse
								</tbody>
							</table>
						</div>
					</div>
				</div>
			</div>
		@endif

		{{-- Pages Tab --}}
		@if ( $activeTab === 'pages' )
			<livewire:artisanpack-analytics::top-pages
				:date-range-preset="$dateRangePreset"
				:site-id="$siteId"
				:limit="20"
			/>
		@endif

		{{-- Traffic Tab --}}
		@if ( $activeTab === 'traffic' )
			<livewire:artisanpack-analytics::traffic-sources
				:date-range-preset="$dateRangePreset"
				:site-id="$siteId"
				:limit="15"
				:show-chart="true"
			/>
		@endif

		{{-- Audience Tab --}}
		@if ( $activeTab === 'audience' )
			<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
				{{-- Device Breakdown --}}
				<div class="card bg-base-100 shadow-sm">
					<div class="card-body">
						<h3 class="font-semibold text-base-content mb-4">
							{{ __( 'Devices' ) }}
						</h3>
						@if ( $deviceBreakdown->isEmpty() )
							<div class="flex flex-col items-center justify-center py-8 text-base-content/50">
								<x-artisanpack-icon name="o-device-phone-mobile" class="w-12 h-12 mb-2" />
								<p>{{ __( 'No device data available' ) }}</p>
							</div>
						@else
							<div class="space-y-3">
								@php
									$totalDeviceSessions = $deviceBreakdown->sum( 'sessions' );
								@endphp
								@foreach ( $deviceBreakdown as $device )
									<div>
										<div class="flex items-center justify-between mb-1">
											<span class="text-sm font-medium">{{ ucfirst( $device['device'] ?? __( 'Unknown' ) ) }}</span>
											<span class="text-sm text-base-content/70">{{ number_format( $device['sessions'] ?? 0 ) }}</span>
										</div>
										<progress
											class="progress progress-primary w-full"
											value="{{ $totalDeviceSessions > 0 ? ( ( $device['sessions'] ?? 0 ) / $totalDeviceSessions ) * 100 : 0 }}"
											max="100"
										></progress>
									</div>
								@endforeach
							</div>
						@endif
					</div>
				</div>

				{{-- Browser Breakdown --}}
				<div class="card bg-base-100 shadow-sm">
					<div class="card-body">
						<h3 class="font-semibold text-base-content mb-4">
							{{ __( 'Browsers' ) }}
						</h3>
						@if ( $browserBreakdown->isEmpty() )
							<div class="flex flex-col items-center justify-center py-8 text-base-content/50">
								<x-artisanpack-icon name="o-globe-alt" class="w-12 h-12 mb-2" />
								<p>{{ __( 'No browser data available' ) }}</p>
							</div>
						@else
							<div class="overflow-x-auto">
								<table class="table table-sm">
									<tbody>
										@foreach ( $browserBreakdown as $browser )
											<tr class="hover:bg-base-200/50">
												<td>{{ $browser['browser'] ?? __( 'Unknown' ) }}</td>
												<td class="text-right font-mono">{{ number_format( $browser['sessions'] ?? 0 ) }}</td>
											</tr>
										@endforeach
									</tbody>
								</table>
							</div>
						@endif
					</div>
				</div>

				{{-- Country Breakdown --}}
				<div class="card bg-base-100 shadow-sm lg:col-span-2">
					<div class="card-body">
						<h3 class="font-semibold text-base-content mb-4">
							{{ __( 'Countries' ) }}
						</h3>
						@if ( $countryBreakdown->isEmpty() )
							<div class="flex flex-col items-center justify-center py-8 text-base-content/50">
								<x-artisanpack-icon name="o-map" class="w-12 h-12 mb-2" />
								<p>{{ __( 'No country data available' ) }}</p>
							</div>
						@else
							<div class="overflow-x-auto">
								<table class="table table-sm">
									<thead>
										<tr>
											<th>{{ __( 'Country' ) }}</th>
											<th class="text-right">{{ __( 'Sessions' ) }}</th>
											<th class="text-right">{{ __( 'Visitors' ) }}</th>
										</tr>
									</thead>
									<tbody>
										@foreach ( $countryBreakdown as $country )
											<tr class="hover:bg-base-200/50">
												<td class="flex items-center gap-2">
													@if ( ! empty( $country['country_code'] ) )
														<span class="text-xl">{{ country_flag( $country['country_code'] ) }}</span>
													@endif
													{{ $country['country'] ?? __( 'Unknown' ) }}
												</td>
												<td class="text-right font-mono">{{ number_format( $country['sessions'] ?? 0 ) }}</td>
												<td class="text-right font-mono">{{ number_format( $country['visitors'] ?? 0 ) }}</td>
											</tr>
										@endforeach
									</tbody>
								</table>
							</div>
						@endif
					</div>
				</div>
			</div>
		@endif
	</div>
</div>
