<?php

/**
 * DigestEmailMailable.
 *
 * @package    ArtisanPack_UI
 * @subpackage Analytics
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.3.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Renders the AI-generated analytics digest as an HTML email.
 *
 * The payload has already been produced by {@see \ArtisanPackUI\Analytics\Ai\Agents\DigestEmailAgent}
 * — this mailable just renders the shape into the digest email view.
 *
 * @package    ArtisanPack_UI
 * @subpackage Analytics
 *
 * @since      1.3.0
 */
class DigestEmailMailable extends Mailable implements ShouldQueue
{
	use Queueable;
	use SerializesModels;

	/**
	 * Constructor.
	 *
	 * @since 1.3.0
	 *
	 * @param  string                $cadence   `weekly` or `monthly`.
	 * @param  string                $periodLabel   Human-readable window label (e.g. "May 1 - May 7, 2026").
	 * @param  array<string, mixed>  $digest    Structured digest from {@see DigestEmailAgent} — summary/key_points/caveats.
	 */
	public function __construct(
		public readonly string $cadence,
		public readonly string $periodLabel,
		public readonly array $digest,
	) {
	}

	/**
	 * Build the message envelope.
	 *
	 * @since 1.3.0
	 *
	 * @return Envelope
	 */
	public function envelope(): Envelope
	{
		$subject = 'monthly' === $this->cadence
			? __( 'Your monthly analytics digest — :period', [ 'period' => $this->periodLabel ] )
			: __( 'Your weekly analytics digest — :period', [ 'period' => $this->periodLabel ] );

		return new Envelope(
			subject: $subject,
		);
	}

	/**
	 * Build the message content.
	 *
	 * @since 1.3.0
	 *
	 * @return Content
	 */
	public function content(): Content
	{
		return new Content(
			view: 'artisanpack-analytics::emails.digest',
			with: [
				'cadence'     => $this->cadence,
				'periodLabel' => $this->periodLabel,
				'summary'     => (string) ( $this->digest['summary'] ?? '' ),
				'keyPoints'   => is_array( $this->digest['key_points'] ?? null ) ? $this->digest['key_points'] : [],
				'caveats'     => is_array( $this->digest['caveats'] ?? null ) ? $this->digest['caveats'] : [],
			],
		);
	}
}
