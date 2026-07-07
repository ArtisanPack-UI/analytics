<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<title>{{ __( 'Analytics digest' ) }}</title>
</head>
<body style="margin:0;padding:24px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;color:#111;background:#f8fafc;">
	<main style="max-width:640px;margin:0 auto;background:#ffffff;padding:32px;border-radius:8px;">
		<header>
			<h1 style="font-size:20px;margin:0 0 4px;">
				@if ( 'monthly' === $cadence )
					{{ __( 'Your monthly analytics digest' ) }}
				@else
					{{ __( 'Your weekly analytics digest' ) }}
				@endif
			</h1>
			<p style="margin:0 0 24px;color:#475569;font-size:14px;">
				{{ $periodLabel }}
			</p>
		</header>

		<section aria-labelledby="digest-summary">
			<h2 id="digest-summary" style="font-size:16px;margin:0 0 8px;">
				{{ __( 'Summary' ) }}
			</h2>
			<p style="margin:0 0 24px;line-height:1.5;">
				{{ $summary }}
			</p>
		</section>

		@if ( ! empty( $keyPoints ) )
			<section aria-labelledby="digest-highlights">
				<h2 id="digest-highlights" style="font-size:16px;margin:0 0 8px;">
					{{ __( 'Highlights' ) }}
				</h2>
				<ul style="margin:0 0 24px;padding-left:20px;line-height:1.6;">
					@foreach ( $keyPoints as $point )
						<li>{{ $point }}</li>
					@endforeach
				</ul>
			</section>
		@endif

		@if ( ! empty( $caveats ) )
			<section aria-labelledby="digest-caveats">
				<h2 id="digest-caveats" style="font-size:16px;margin:0 0 8px;">
					{{ __( 'Worth watching' ) }}
				</h2>
				<ul style="margin:0 0 24px;padding-left:20px;line-height:1.6;color:#475569;">
					@foreach ( $caveats as $caveat )
						<li>{{ $caveat }}</li>
					@endforeach
				</ul>
			</section>
		@endif

		<footer style="border-top:1px solid #e2e8f0;padding-top:16px;color:#64748b;font-size:12px;">
			<p style="margin:0;">
				{{ __( 'You are receiving this because you opted in to analytics digest emails. You can change your cadence or unsubscribe from the analytics dashboard.' ) }}
			</p>
		</footer>
	</main>
</body>
</html>
