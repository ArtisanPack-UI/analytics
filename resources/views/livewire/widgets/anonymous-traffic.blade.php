<div>
	{{-- Loading State --}}
	@if ( $isLoading )
		<x-artisanpack-card>
			<div class="h-4 bg-base-300 rounded w-1/4 mb-4 animate-pulse"></div>
			<div class="grid grid-cols-2 gap-4 mb-4">
				<div class="h-16 bg-base-300 rounded animate-pulse"></div>
				<div class="h-16 bg-base-300 rounded animate-pulse"></div>
			</div>
			@foreach ( range( 1, 5 ) as $i )
				<div class="flex justify-between py-2 animate-pulse">
					<div class="h-4 bg-base-300 rounded w-2/3"></div>
					<div class="h-4 bg-base-300 rounded w-16"></div>
				</div>
			@endforeach
		</x-artisanpack-card>
	@elseif ( ! $enabled )
		{{-- Feature is off: say so plainly rather than showing zeroes. --}}
		<x-artisanpack-card :title="__( 'Anonymous Traffic' )">
			<div class="flex flex-col items-center justify-center py-8 text-center text-base-content/60">
				<x-artisanpack-icon name="o-eye-slash" class="w-12 h-12 mb-2" />
				<p class="max-w-prose">
					{{ __( 'Anonymous mode is turned off, so visitors who have not granted consent are not counted at all. Enable it to record pre-consent page views.' ) }}
				</p>
			</div>
		</x-artisanpack-card>
	@elseif ( 0 === $anonymousPageviews )
		{{-- On, but nothing collected yet: also not a fault. --}}
		<x-artisanpack-card :title="__( 'Anonymous Traffic' )">
			<div class="flex flex-col items-center justify-center py-8 text-center text-base-content/60">
				<x-artisanpack-icon name="o-eye-slash" class="w-12 h-12 mb-2" />
				<p class="max-w-prose">
					{{ __( 'Anonymous mode is on, but no pre-consent page views have been recorded for this period.' ) }}
				</p>
			</div>
		</x-artisanpack-card>
	@else
		<x-artisanpack-card :title="__( 'Anonymous Traffic' )">
			<x-slot:menu>
				<x-artisanpack-button
					wire:click="refreshData"
					class="btn-ghost btn-xs"
					icon="o-arrow-path"
					spinner
					:tooltip="__( 'Refresh' )"
				/>
			</x-slot:menu>

			<p class="text-sm text-base-content/60 mb-4 max-w-prose">
				{{ __( 'Page views recorded before consent was granted. These rows carry no visitor or session, so they can be counted but never attributed: they cannot contribute to visitors, sessions, bounce rate or session duration anywhere on this dashboard.' ) }}
			</p>

			{{-- Summary stats --}}
			<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
				<div class="rounded-box bg-base-200 p-4">
					<div class="text-xs uppercase tracking-wide text-base-content/50">
						{{ __( 'Anonymous page views' ) }}
					</div>
					<div class="text-2xl font-bold font-mono">
						{{ number_format( $anonymousPageviews ) }}
					</div>
				</div>
				<div class="rounded-box bg-base-200 p-4">
					<div class="text-xs uppercase tracking-wide text-base-content/50">
						{{ __( 'Consented page views' ) }}
					</div>
					<div class="text-2xl font-bold font-mono">
						{{ number_format( $identifiedPageviews ) }}
					</div>
				</div>
				<div class="rounded-box bg-base-200 p-4">
					<div class="text-xs uppercase tracking-wide text-base-content/50">
						{{ __( '% of all page views' ) }}
					</div>
					<div class="text-2xl font-bold font-mono">
						{{ number_format( $anonymousPercentage, 1 ) }}%
					</div>
				</div>
			</div>

			{{-- Trend sparkline --}}
			@if ( ! empty( $trend ) )
				@php( $trendMax = $this->getTrendMax() )
				<div class="mb-6">
					<div class="text-xs uppercase tracking-wide text-base-content/50 mb-2">
						{{ __( 'Anonymous page view trend' ) }}
					</div>
					<div
						class="flex items-end gap-px h-16"
						role="img"
						aria-label="{{ __( 'Anonymous page views over time for the selected date range.' ) }}"
					>
						@foreach ( $trend as $point )
							<div
								class="flex-1 bg-secondary/60 rounded-t min-h-[2px]"
								style="height: {{ max( 2, (int) round( ( $point['pageviews'] / $trendMax ) * 100 ) ) }}%"
								title="{{ $point['date'] }}: {{ number_format( $point['pageviews'] ) }}"
							></div>
						@endforeach
					</div>
				</div>
			@endif

			<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
				{{-- Top pages --}}
				<div>
					<h4 class="text-sm font-semibold mb-2">{{ __( 'Top pages' ) }}</h4>
					@if ( $topPages->isEmpty() )
						<p class="text-sm text-base-content/50">{{ __( 'No pages recorded for this period' ) }}</p>
					@else
						<div class="overflow-x-auto">
							<table class="table table-sm">
								<thead>
									<tr>
										<th>{{ __( 'Path' ) }}</th>
										<th class="text-right">{{ __( 'Page views' ) }}</th>
									</tr>
								</thead>
								<tbody>
									@foreach ( $topPages as $page )
										<tr class="hover:bg-base-200/50" wire:key="anon-page-{{ md5( $page['path'] ) }}">
											<td class="max-w-xs truncate" title="{{ $page['path'] }}">{{ $page['path'] }}</td>
											<td class="text-right font-mono">{{ number_format( $page['views'] ) }}</td>
										</tr>
									@endforeach
								</tbody>
							</table>
						</div>
					@endif
				</div>

				{{-- Referring hosts --}}
				<div>
					<h4 class="text-sm font-semibold mb-2">{{ __( 'Referring hosts' ) }}</h4>
					@if ( $referringHosts->isEmpty() )
						<p class="text-sm text-base-content/50">{{ __( 'No referrers recorded for this period' ) }}</p>
					@else
						<div class="overflow-x-auto">
							<table class="table table-sm">
								<thead>
									<tr>
										<th>{{ __( 'Host' ) }}</th>
										<th class="text-right">{{ __( 'Page views' ) }}</th>
									</tr>
								</thead>
								<tbody>
									@foreach ( $referringHosts as $host )
										<tr class="hover:bg-base-200/50" wire:key="anon-host-{{ md5( $host['host'] ) }}">
											<td class="max-w-xs truncate" title="{{ $host['host'] }}">{{ $host['host'] }}</td>
											<td class="text-right font-mono">{{ number_format( $host['views'] ) }}</td>
										</tr>
									@endforeach
								</tbody>
							</table>
						</div>
					@endif
				</div>
			</div>

			{{-- Device split --}}
			@if ( $deviceBreakdown->isNotEmpty() )
				<div class="mt-6">
					<h4 class="text-sm font-semibold mb-2">{{ __( 'Devices' ) }}</h4>
					<div class="space-y-3">
						@foreach ( $deviceBreakdown as $device )
							<div wire:key="anon-device-{{ md5( $device['device_type'] ) }}">
								<div class="flex items-center justify-between mb-1">
									<span class="text-sm font-medium">{{ ucfirst( $device['device_type'] ) }}</span>
									<span class="text-sm text-base-content/70">
										{{ number_format( $device['views'] ) }} ({{ number_format( $device['percentage'], 1 ) }}%)
									</span>
								</div>
								<x-artisanpack-progress
									class="progress-secondary w-full"
									:value="$device['percentage']"
									:max="100"
								/>
							</div>
						@endforeach
					</div>
				</div>
			@endif
		</x-artisanpack-card>
	@endif
</div>
