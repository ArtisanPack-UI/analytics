<?php

declare( strict_types=1 );

namespace Tests\Stubs;

use Illuminate\View\Component;

/**
 * Stub loading component for testing.
 */
class LoadingComponent extends Component
{
	public string $class;

	public function __construct( string $class = '' )
	{
		$this->class = $class;
	}

	public function render(): string
	{
		return '<span class="loading ' . e( $this->class ) . '"></span>';
	}
}
