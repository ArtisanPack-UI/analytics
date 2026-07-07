<?php

/**
 * DigestSubscriptionAiRequest.
 *
 * @package    ArtisanPack_UI
 * @subpackage Analytics
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.3.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Http\Requests\Api\Ai;

use ArtisanPackUI\Analytics\Models\AnalyticsDigestPreference;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Validates requests to persist the digest-email opt-in preference.
 *
 * @package    ArtisanPack_UI
 * @subpackage Analytics
 *
 * @since      1.3.0
 */
class DigestSubscriptionAiRequest extends FormRequest
{
	/**
	 * {@inheritDoc}
	 */
	public function authorize(): bool
	{
		return Gate::allows( 'analytics.ai.use' );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function rules(): array
	{
		return [
			'cadence' => [
				'required',
				'string',
				'in:' . implode( ',', [
					AnalyticsDigestPreference::CADENCE_OFF,
					AnalyticsDigestPreference::CADENCE_WEEKLY,
					AnalyticsDigestPreference::CADENCE_MONTHLY,
				] ),
			],
		];
	}
}
