<?php

/**
 * SegmentInsight Livewire component.
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
use ArtisanPackUI\Analytics\Ai\Agents\SegmentInsightAgent;
use Illuminate\View\View;
use Livewire\Component;
use Throwable;

/**
 * Trigger UI for the {@see SegmentInsightAgent}.
 *
 * @package    ArtisanPack_UI
 * @subpackage Analytics
 *
 * @since      1.3.0
 */
class SegmentInsight extends Component
{
	public string $segmentType = '';

	public string $segmentValue = '';

	/**
	 * @var array<string, mixed>
	 */
	public array $metrics = [];

	/**
	 * @var array<string, mixed>
	 */
	public array $baseline = [];

	public bool $isLoading = false;

	public ?string $error = null;

	/**
	 * @var array<int, array{ observation: string, significance: string, suggested_action: string }>
	 */
	public array $patterns = [];

	/**
	 * Mount the component.
	 *
	 * @since 1.3.0
	 *
	 * @param  string                $segmentType   Segment dimension (e.g. "device", "country").
	 * @param  string                $segmentValue  Segment value (e.g. "mobile", "US").
	 * @param  array<string, mixed>  $metrics       Segment metrics.
	 * @param  array<string, mixed>  $baseline      Baseline metrics.
	 */
	public function mount(
		string $segmentType = '',
		string $segmentValue = '',
		array $metrics = [],
		array $baseline = [],
	): void {
		$this->segmentType  = $segmentType;
		$this->segmentValue = $segmentValue;
		$this->metrics      = $metrics;
		$this->baseline     = $baseline;
	}

	/**
	 * Run the agent.
	 *
	 * @since 1.3.0
	 *
	 * @return void
	 */
	public function analyze(): void
	{
		$this->error     = null;
		$this->patterns  = [];
		$this->isLoading = true;

		try {
			$output = SegmentInsightAgent::for( [
				'segment'  => [ 'type' => $this->segmentType, 'value' => $this->segmentValue ],
				'metrics'  => $this->metrics,
				'baseline' => $this->baseline,
			] )->run();

			$this->patterns = is_array( $output['patterns'] ?? null ) ? $output['patterns'] : [];
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
		$key      = 'analytics.segment_insight';

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
		return view( 'artisanpack-analytics::livewire.ai.segment-insight' );
	}
}
