<?php

declare( strict_types=1 );

namespace Tests\Stubs;

use Illuminate\View\Component;

/**
 * Stub select component for testing.
 */
class SelectComponent extends Component
{
	public string $label;

	public array $options;

	public string $optionValue;

	public string $optionLabel;

	public string $class;

	public function __construct(
		string $label = '',
		array $options = [],
		string $optionValue = 'value',
		string $optionLabel = 'label',
		string $class = '',
	) {
		$this->label       = $label;
		$this->options     = $options;
		$this->optionValue = $optionValue;
		$this->optionLabel = $optionLabel;
		$this->class       = $class;
	}

	public function render(): string
	{
		return '<select class="' . e( $this->class ) . '">{{ $slot }}</select>';
	}
}
