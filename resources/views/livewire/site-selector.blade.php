<div
	x-data="{ open: @entangle('isOpen') }"
	x-on:keydown.escape.window="open = false"
	x-on:click.away="open = false"
	class="relative inline-block text-left"
>
	<span id="site-selector-label" class="sr-only">
		{{ __( 'Select a site' ) }}
	</span>

	{{-- Trigger Button --}}
	<button
		x-on:click="open = !open"
		type="button"
		class="inline-flex items-center justify-between w-full gap-2 px-4 py-2 text-sm font-medium bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:bg-gray-800 dark:border-gray-600 dark:text-white dark:hover:bg-gray-700"
		aria-haspopup="listbox"
		x-bind:aria-expanded="open"
		aria-label="{{ __( 'Select site: :name', ['name' => $selectedSite?->name ?? __( 'None selected' )] ) }}"
	>
		<span class="flex items-center gap-2">
			@if ( $selectedSite )
				<span class="flex items-center justify-center w-6 h-6 text-xs font-semibold text-white bg-blue-500 rounded">
					{{ strtoupper( substr( $selectedSite->name, 0, 1 ) ) }}
				</span>
				<span class="truncate max-w-[150px]">{{ $selectedSite->name }}</span>
				@if ( $selectedSite->domain )
					<span class="hidden text-xs text-gray-500 sm:inline dark:text-gray-400">
						({{ $selectedSite->domain }})
					</span>
				@endif
			@else
				<span class="text-gray-500 dark:text-gray-400">
					{{ __( 'Select a site...' ) }}
				</span>
			@endif
		</span>
		<svg
			class="w-5 h-5 text-gray-400 transition-transform duration-200"
			x-bind:class="{ 'rotate-180': open }"
			xmlns="http://www.w3.org/2000/svg"
			viewBox="0 0 20 20"
			fill="currentColor"
			aria-hidden="true"
		>
			<path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
		</svg>
	</button>

	{{-- Dropdown Menu --}}
	<div
		x-show="open"
		x-trap.noscroll="open"
		x-effect="open && $nextTick(() => $focus.first())"
		x-transition:enter="transition ease-out duration-100"
		x-transition:enter-start="transform opacity-0 scale-95"
		x-transition:enter-end="transform opacity-100 scale-100"
		x-transition:leave="transition ease-in duration-75"
		x-transition:leave-start="transform opacity-100 scale-100"
		x-transition:leave-end="transform opacity-0 scale-95"
		class="absolute right-0 z-50 mt-2 origin-top-right bg-white border border-gray-200 rounded-md shadow-lg w-72 dark:bg-gray-800 dark:border-gray-700"
		role="listbox"
		aria-labelledby="site-selector-label"
		tabindex="-1"
		x-on:keydown.up.prevent="$focus.wrap().previous()"
		x-on:keydown.down.prevent="$focus.wrap().next()"
		style="display: none;"
	>
		<div class="py-1 max-h-60 overflow-y-auto">
			@forelse ( $sites as $site )
				<button
					wire:click="selectSite({{ $site->id }})"
					type="button"
					class="flex items-center w-full gap-3 px-4 py-2 text-sm text-left transition-colors hover:bg-gray-100 focus:bg-gray-100 focus:outline-none dark:hover:bg-gray-700 dark:focus:bg-gray-700 {{ $selectedSiteId === $site->id ? 'bg-blue-50 dark:bg-blue-900/20' : '' }}"
					role="option"
					aria-selected="{{ $selectedSiteId === $site->id ? 'true' : 'false' }}"
				>
					<span class="flex items-center justify-center w-8 h-8 text-xs font-semibold text-white bg-blue-500 rounded shrink-0">
						{{ strtoupper( substr( $site->name, 0, 1 ) ) }}
					</span>
					<span class="flex-1 min-w-0">
						<span class="block font-medium text-gray-900 truncate dark:text-white">
							{{ $site->name }}
						</span>
						@if ( $site->domain )
							<span class="block text-xs text-gray-500 truncate dark:text-gray-400">
								{{ $site->domain }}
							</span>
						@endif
					</span>
					@if ( $selectedSiteId === $site->id )
						<svg class="w-5 h-5 text-blue-500 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
							<path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
						</svg>
					@endif
				</button>
			@empty
				<div class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
					{{ __( 'No sites available' ) }}
				</div>
			@endforelse
		</div>
	</div>

	{{-- Loading indicator during site change --}}
	<div
		wire:loading
		wire:target="selectSite"
		class="absolute inset-0 flex items-center justify-center bg-white/50 dark:bg-gray-800/50 rounded-md"
	>
		<svg class="w-5 h-5 text-blue-500 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
			<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
			<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
		</svg>
	</div>
</div>
