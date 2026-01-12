<?php

declare( strict_types=1 );

namespace Tests\Stubs;

use Illuminate\View\Component;

/**
 * Stub card component for testing.
 */
class CardComponent extends Component
{
	public string $title;

	public string $subtitle;

	public string $class;

	public function __construct(
		string $title = '',
		string $subtitle = '',
		string $class = '',
	) {
		$this->title    = $title;
		$this->subtitle = $subtitle;
		$this->class    = $class;
	}

	public function render(): string
	{
		return '<div class="card ' . e( $this->class ) . '"><div class="card-body">{{ $slot }}</div></div>';
	}
}
