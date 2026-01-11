<?php

declare( strict_types=1 );

namespace Tests\Stubs;

use Illuminate\View\Component;

/**
 * Stub icon component for testing.
 */
class IconComponent extends Component
{
	public string $name;

	public string $class;

	public function __construct( string $name = '', string $class = '' )
	{
		$this->name  = $name;
		$this->class = $class;
	}

	public function render(): string
	{
		return '<svg class="' . e( $this->class ) . '"></svg>';
	}
}
