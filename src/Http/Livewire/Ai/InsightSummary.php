<?php

/**
 * InsightSummary Livewire component.
 *
 * @package    ArtisanPack_UI
 * @subpackage Analytics
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.3.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Http\Livewire\Ai;

use ArtisanPackUI\Ai\Contracts\FeatureRegistry;
use ArtisanPackUI\Ai\Exceptions\FeatureDisabledException;
use ArtisanPackUI\Ai\Exceptions\FeatureError;
use ArtisanPackUI\Ai\Exceptions\MissingCredentialsException;
use ArtisanPackUI\Analytics\Ai\Agents\InsightSummaryAgent;
use Illuminate\View\View;
use Livewire\Component;
use Throwable;

/**
 * Trigger UI for the {@see InsightSummaryAgent}.
 *
 * @package    ArtisanPack_UI
 * @subpackage Analytics
 *
 * @since      1.3.0
 */
class InsightSummary extends Component
{
	public string $dateFrom = '';

	public string $dateTo = '';

	public ?string $compareFrom = null;

	public ?string $compareTo = null;

	/**
	 * @var array<string, mixed>
	 */
	public array $metrics = [];

	public bool $isLoading = false;

	public ?string $error = null;

	public ?string $summary = null;

	/**
	 * @var array<int, string>
	 */
	public array $highlights = [];

	/**
	 * @var array<int, string>
	 */
	public array $concerns = [];

	/**
	 * Mount the component.
	 *
	 * @since 1.3.0
	 *
	 * @param  string                $dateFrom     Range start (YYYY-MM-DD).
	 * @param  string                $dateTo       Range end (YYYY-MM-DD).
	 * @param  array<string, mixed>  $metrics      Metric payload.
	 * @param  string|null           $compareFrom  Optional comparison start.
	 * @param  string|null           $compareTo    Optional comparison end.
	 */
	public function mount(
		string $dateFrom = '',
		string $dateTo = '',
		array $metrics = [],
		?string $compareFrom = null,
		?string $compareTo = null,
	): void {
		$this->dateFrom    = $dateFrom;
		$this->dateTo      = $dateTo;
		$this->metrics     = $metrics;
		$this->compareFrom = $compareFrom;
		$this->compareTo   = $compareTo;
	}

	/**
	 * Run the agent and populate the summary.
	 *
	 * @since 1.3.0
	 *
	 * @return void
	 */
	public function summarize(): void
	{
		$this->error      = null;
		$this->summary    = null;
		$this->highlights = [];
		$this->concerns   = [];
		$this->isLoading  = true;

		try {
			$input = [
				'date_range' => [ 'from' => $this->dateFrom, 'to' => $this->dateTo ],
				'metrics'    => $this->metrics,
			];

			if ( null !== $this->compareFrom && null !== $this->compareTo ) {
				$input['compare_to'] = [ 'from' => $this->compareFrom, 'to' => $this->compareTo ];
			}

			$output = InsightSummaryAgent::for( $input )->run();

			$this->summary    = isset( $output['summary'] ) ? (string) $output['summary'] : '';
			$this->highlights = is_array( $output['highlights'] ?? null ) ? $output['highlights'] : [];
			$this->concerns   = is_array( $output['concerns'] ?? null ) ? $output['concerns'] : [];
		} catch ( FeatureDisabledException $exception ) {
			$this->error = __( 'This AI feature is disabled.' );
		} catch ( MissingCredentialsException $exception ) {
			$this->error = __( 'AI credentials are not configured.' );
		} catch ( FeatureError $exception ) {
			$this->error = $exception->getMessage();
		} catch ( Throwable $exception ) {
			$this->error = __( 'The AI agent could not complete this request.' );
		} finally {
			$this->isLoading = false;
		}
	}

	/**
	 * Determine whether this feature is enabled.
	 *
	 * @since 1.3.0
	 *
	 * @return bool
	 */
	public function getIsEnabledProperty(): bool
	{
		$registry = app( FeatureRegistry::class );
		$key      = 'analytics.insight_summary';

		if ( null === $registry->get( $key ) ) {
			return false;
		}

		return $registry->isToggleOn( $key );
	}

	/**
	 * Render the view.
	 *
	 * @since 1.3.0
	 *
	 * @return View
	 */
	public function render(): View
	{
		return view( 'artisanpack-analytics::livewire.ai.insight-summary' );
	}
}
