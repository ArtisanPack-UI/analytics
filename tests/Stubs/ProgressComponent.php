<?php

declare( strict_types=1 );

namespace Tests\Stubs;

use Illuminate\View\Component;

/**
 * Stub progress component for testing.
 */
class ProgressComponent extends Component
{
	public int|float $value;

	public int|float $max;

	public string $class;

	public function __construct(
		int|float $value = 0,
		int|float $max = 100,
		string $class = '',
	) {
		$this->value = $value;
		$this->max   = $max;
		$this->class = $class;
	}

	public function render(): string
	{
		return '<progress class="progress ' . e( $this->class ) . '" value="' . $this->value . '" max="' . $this->max . '"></progress>';
	}
}
