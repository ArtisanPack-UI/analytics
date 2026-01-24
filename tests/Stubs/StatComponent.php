<?php

declare( strict_types=1 );

namespace Tests\Stubs;

use Illuminate\View\Component;

/**
 * Stub stat component for testing.
 */
class StatComponent extends Component
{
	public string $title;

	public string $value;

	public string $description;

	public string $icon;

	public string $class;

	public function __construct(
		string $title = '',
		string $value = '',
		string $description = '',
		string $icon = '',
		string $class = '',
	) {
		$this->title       = $title;
		$this->value       = $value;
		$this->description = $description;
		$this->icon        = $icon;
		$this->class       = $class;
	}

	public function render(): string
	{
		return '<div class="stat ' . e( $this->class ) . '"><div class="stat-title">' . e( $this->title ) . '</div><div class="stat-value">' . e( $this->value ) . '</div></div>';
	}
}
