<?php

declare( strict_types=1 );

namespace Tests\Stubs;

use Illuminate\View\Component;

/**
 * Stub button component for testing.
 */
class ButtonComponent extends Component
{
	public string $label;

	public string $icon;

	public string $class;

	public function __construct(
		string $label = '',
		string $icon = '',
		string $class = '',
		?string $iconRight = null,
	) {
		$this->label = $label;
		$this->icon  = $icon;
		$this->class = $class;
	}

	public function render(): string
	{
		return '<button class="' . e( $this->class ) . '">' . e( $this->label ) . '{{ $slot ?? "" }}</button>';
	}
}
