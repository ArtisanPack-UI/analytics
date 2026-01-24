<?php

declare( strict_types=1 );

namespace Tests\Stubs;

use Illuminate\View\Component;

/**
 * Stub dropdown component for testing.
 */
class DropdownComponent extends Component
{
	public bool $right;

	public function __construct( bool $right = false )
	{
		$this->right = $right;
	}

	public function render(): string
	{
		return '<div class="dropdown">{{ $slot }}</div>';
	}
}
