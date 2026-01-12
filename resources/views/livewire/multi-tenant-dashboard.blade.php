<div class="multi-tenant-dashboard">
	{{-- Header with Site Selector --}}
	<div class="flex flex-col gap-4 mb-6 sm:flex-row sm:items-center sm:justify-between">
		<div>
			<h1 class="text-2xl font-bold text-gray-900 dark:text-white">
				{{ __( 'Analytics Dashboard' ) }}
			</h1>
			@if ( $currentSite )
				<p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
					{{ $currentSite->domain ?? $currentSite->name }}
				</p>
			@endif
		</div>

		<div class="flex items-center gap-4">
			{{-- Site Selector (only in multi-tenant mode with multiple sites) --}}
			@if ( $showSiteSelector )
				<livewire:artisanpack-analytics::site-selector />
			@endif

			{{-- Date Range Selector --}}
			<div class="relative">
				<select
					wire:model.live="dateRangePreset"
					class="block w-full px-4 py-2 pr-8 text-sm bg-white border border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-800 dark:border-gray-600 dark:text-white"
					aria-label="{{ __( 'Select date range' ) }}"
				>
					<option value="today">{{ __( 'Today' ) }}</option>
					<option value="yesterday">{{ __( 'Yesterday' ) }}</option>
					<option value="7d">{{ __( 'Last 7 days' ) }}</option>
					<option value="30d">{{ __( 'Last 30 days' ) }}</option>
					<option value="90d">{{ __( 'Last 90 days' ) }}</option>
					<option value="12m">{{ __( 'Last 12 months' ) }}</option>
					<option value="year">{{ __( 'This year' ) }}</option>
				</select>
			</div>
		</div>
	</div>

	{{-- Loading indicator during site change --}}
	<div
		wire:loading.flex
		wire:target="handleSiteChange"
		class="fixed inset-0 z-50 items-center justify-center bg-white/80 dark:bg-gray-900/80"
	>
		<div class="flex flex-col items-center gap-3">
			<svg class="w-10 h-10 text-blue-500 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
				<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
				<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
			</svg>
			<span class="text-sm font-medium text-gray-600 dark:text-gray-300">
				{{ __( 'Loading site data...' ) }}
			</span>
		</div>
	</div>

	{{-- Analytics Dashboard --}}
	@if ( $siteId )
		<livewire:artisanpack-analytics::analytics-dashboard
			:date-range-preset="$dateRangePreset"
			:site-id="$siteId"
			:key="'dashboard-' . $siteId"
		/>
	@else
		<div class="flex flex-col items-center justify-center p-12 text-center bg-white rounded-lg shadow dark:bg-gray-800">
			<svg class="w-16 h-16 mb-4 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
				<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
			</svg>
			<h3 class="mb-2 text-lg font-medium text-gray-900 dark:text-white">
				{{ __( 'No Site Selected' ) }}
			</h3>
			<p class="text-gray-500 dark:text-gray-400">
				{{ __( 'Please select a site to view analytics data.' ) }}
			</p>
		</div>
	@endif
</div>
