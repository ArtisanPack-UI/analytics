<?php

/**
 * AnomalyExplanation Livewire component.
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
use ArtisanPackUI\Analytics\Ai\Agents\AnomalyExplanationAgent;
use Illuminate\View\View;
use Livewire\Component;
use Throwable;

/**
 * Trigger UI for the {@see AnomalyExplanationAgent}.
 *
 * @package    ArtisanPack_UI
 * @subpackage Analytics
 *
 * @since      1.3.0
 */
class AnomalyExplanation extends Component
{
	/**
	 * @var array<string, mixed>
	 */
	public array $anomaly = [];

	/**
	 * @var array<string, mixed>
	 */
	public array $context = [];

	public bool $isLoading = false;

	public ?string $error = null;

	/**
	 * @var array<int, array{ cause: string, confidence: string, evidence: array<int, string> }>
	 */
	public array $hypotheses = [];

	/**
	 * @var array<int, string>
	 */
	public array $recommendedNextSteps = [];

	/**
	 * Mount the component.
	 *
	 * @since 1.3.0
	 *
	 * @param  array<string, mixed>  $anomaly  Anomaly payload.
	 * @param  array<string, mixed>  $context  Contextual signals.
	 */
	public function mount( array $anomaly = [], array $context = [] ): void
	{
		$this->anomaly = $anomaly;
		$this->context = $context;
	}

	/**
	 * Run the agent.
	 *
	 * @since 1.3.0
	 *
	 * @return void
	 */
	public function explain(): void
	{
		$this->error                = null;
		$this->hypotheses           = [];
		$this->recommendedNextSteps = [];
		$this->isLoading            = true;

		try {
			$output = AnomalyExplanationAgent::for( [
				'anomaly' => $this->anomaly,
				'context' => $this->context,
			] )->run();

			$this->hypotheses           = is_array( $output['hypotheses'] ?? null ) ? $output['hypotheses'] : [];
			$this->recommendedNextSteps = is_array( $output['recommended_next_steps'] ?? null ) ? $output['recommended_next_steps'] : [];
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
		$key      = 'analytics.explain_anomaly';

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
		return view( 'artisanpack-analytics::livewire.ai.anomaly-explanation' );
	}
}
