<?php

declare( strict_types=1 );

namespace Tests\Stubs;

use Illuminate\View\Component;

/**
 * Stub menu item component for testing.
 */
class MenuItemComponent extends Component
{
	public string $label;

	public string $icon;

	public function __construct(
		string $label = '',
		string $icon = '',
	) {
		$this->label = $label;
		$this->icon  = $icon;
	}

	public function render(): string
	{
		return '<li>' . e( $this->label ) . '{{ $slot ?? "" }}</li>';
	}
}
