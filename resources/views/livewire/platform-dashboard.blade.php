<div class="platform-dashboard" aria-label="{{ __( 'Platform Analytics Dashboard' ) }}">
    {{-- Header with controls --}}
    <div class="flex flex-col gap-4 mb-6 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold">{{ __( 'Platform Dashboard' ) }}</h1>
            <p class="mt-1 text-sm text-base-content/70">{{ __( 'Analytics across all sites' ) }}</p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            {{-- Date Range Selector --}}
            <div class="form-control">
                <label for="date-range" class="sr-only">{{ __( 'Date Range' ) }}</label>
                <select
                    id="date-range"
                    wire:model.live="dateRange"
                    class="select select-bordered select-sm"
                    aria-label="{{ __( 'Select date range' ) }}"
                >
                    @foreach ( $dateRangeOptions as $value => $label )
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Export Button --}}
            <div class="dropdown dropdown-end">
                <label tabindex="0" class="btn btn-sm btn-outline">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    {{ __( 'Export' ) }}
                </label>
                <ul tabindex="0" class="p-2 shadow dropdown-content menu bg-base-100 rounded-box w-40" role="menu">
                    <li>
                        <button wire:click="exportReport('csv')" role="menuitem">
                            {{ __( 'Export CSV' ) }}
                        </button>
                    </li>
                    <li>
                        <button wire:click="exportReport('json')" role="menuitem">
                            {{ __( 'Export JSON' ) }}
                        </button>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    {{-- Platform Stats Cards --}}
    <div class="grid grid-cols-1 gap-4 mb-6 sm:grid-cols-2 lg:grid-cols-4">
        {{-- Total Sites --}}
        <div class="shadow stat bg-base-100 rounded-box">
            <div class="stat-figure text-primary">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" />
                </svg>
            </div>
            <div class="stat-title">{{ __( 'Total Sites' ) }}</div>
            <div class="stat-value text-primary">{{ number_format( $this->platformStats['total_sites'] ?? 0 ) }}</div>
            <div class="stat-desc">{{ __( 'Active sites' ) }}: {{ number_format( $this->platformStats['active_sites'] ?? 0 ) }}</div>
        </div>

        {{-- Total Visitors --}}
        <div class="shadow stat bg-base-100 rounded-box">
            <div class="stat-figure text-secondary">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
            </div>
            <div class="stat-title">{{ __( 'Total Visitors' ) }}</div>
            <div class="stat-value text-secondary">{{ number_format( $this->platformStats['total_visitors'] ?? 0 ) }}</div>
            <div class="stat-desc">{{ __( 'Unique visitors' ) }}</div>
        </div>

        {{-- Total Sessions --}}
        <div class="shadow stat bg-base-100 rounded-box">
            <div class="stat-figure text-accent">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                </svg>
            </div>
            <div class="stat-title">{{ __( 'Total Sessions' ) }}</div>
            <div class="stat-value text-accent">{{ number_format( $this->platformStats['total_sessions'] ?? 0 ) }}</div>
            <div class="stat-desc">{{ __( 'All sites combined' ) }}</div>
        </div>

        {{-- Total Page Views --}}
        <div class="shadow stat bg-base-100 rounded-box">
            <div class="stat-figure text-info">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
            </div>
            <div class="stat-title">{{ __( 'Page Views' ) }}</div>
            <div class="stat-value text-info">{{ number_format( $this->platformStats['total_page_views'] ?? 0 ) }}</div>
            <div class="stat-desc">{{ __( 'All pages viewed' ) }}</div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {{-- Top Sites by Traffic --}}
        <div class="shadow-xl card bg-base-100">
            <div class="card-body">
                <h2 class="card-title">{{ __( 'Top Sites by Traffic' ) }}</h2>

                @if ( $this->topSites->isEmpty() )
                    <div class="py-8 text-center text-base-content/50">
                        {{ __( 'No site data available for this period.' ) }}
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="table table-sm" aria-label="{{ __( 'Top sites by traffic' ) }}">
                            <thead>
                                <tr>
                                    <th scope="col">{{ __( 'Site' ) }}</th>
                                    <th scope="col" class="text-right">{{ __( 'Visitors' ) }}</th>
                                    <th scope="col" class="text-right">{{ __( 'Sessions' ) }}</th>
                                    <th scope="col" class="text-right">{{ __( 'Page Views' ) }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ( $this->topSites as $site )
                                    <tr>
                                        <td>
                                            <div class="flex items-center gap-2">
                                                <div class="w-2 h-2 rounded-full bg-primary"></div>
                                                <div>
                                                    <div class="font-medium">{{ $site->name }}</div>
                                                    <div class="text-xs opacity-50">{{ $site->domain }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-right">{{ number_format( $site->visitors_count ?? 0 ) }}</td>
                                        <td class="text-right">{{ number_format( $site->sessions_count ?? 0 ) }}</td>
                                        <td class="text-right">{{ number_format( $site->page_views_count ?? 0 ) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        {{-- Sites with Growth --}}
        <div class="shadow-xl card bg-base-100">
            <div class="card-body">
                <div class="flex items-center justify-between">
                    <h2 class="card-title">{{ __( 'Site Growth' ) }}</h2>
                    <select
                        wire:model.live="comparisonPeriod"
                        class="select select-bordered select-xs"
                        aria-label="{{ __( 'Comparison period' ) }}"
                    >
                        <option value="previous">{{ __( 'vs Previous Period' ) }}</option>
                        <option value="year">{{ __( 'vs Last Year' ) }}</option>
                    </select>
                </div>

                @if ( $this->sitesWithGrowth->isEmpty() )
                    <div class="py-8 text-center text-base-content/50">
                        {{ __( 'No growth data available for this period.' ) }}
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="table table-sm" aria-label="{{ __( 'Sites with growth metrics' ) }}">
                            <thead>
                                <tr>
                                    <th scope="col">{{ __( 'Site' ) }}</th>
                                    <th scope="col" class="text-right">{{ __( 'Current' ) }}</th>
                                    <th scope="col" class="text-right">{{ __( 'Previous' ) }}</th>
                                    <th scope="col" class="text-right">{{ __( 'Growth' ) }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ( $this->sitesWithGrowth as $site )
                                    @php
                                        $growthPercent = $site->growth_percent ?? 0;
                                        $isPositive = $growthPercent >= 0;
                                    @endphp
                                    <tr>
                                        <td>
                                            <div class="font-medium">{{ $site->name }}</div>
                                        </td>
                                        <td class="text-right">{{ number_format( $site->current_visitors ?? 0 ) }}</td>
                                        <td class="text-right">{{ number_format( $site->previous_visitors ?? 0 ) }}</td>
                                        <td class="text-right">
                                            <span class="{{ $isPositive ? 'text-success' : 'text-error' }} flex items-center justify-end gap-1">
                                                @if ( $isPositive )
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18" />
                                                    </svg>
                                                @else
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
                                                    </svg>
                                                @endif
                                                {{ number_format( abs( $growthPercent ), 1 ) }}%
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Loading Indicator --}}
    <div wire:loading.delay class="fixed inset-0 z-50 flex items-center justify-center bg-black/20">
        <div class="p-4 rounded-lg bg-base-100">
            <span class="loading loading-spinner loading-lg text-primary"></span>
            <span class="sr-only">{{ __( 'Loading...' ) }}</span>
        </div>
    </div>
</div>
