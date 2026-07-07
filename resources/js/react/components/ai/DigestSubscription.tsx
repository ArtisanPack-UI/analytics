/**
 * React opt-in UI for the AI analytics digest email.
 *
 * @package    ArtisanPack_UI
 * @subpackage Analytics
 *
 * @since      1.3.0
 */

import { useCallback, useState, type FC } from 'react';

import { useApi, type UseApiOptions } from '../../hooks/useApi';

export type DigestCadence = 'off' | 'weekly' | 'monthly';

export interface DigestSubscriptionProps {
	api: UseApiOptions;
	initialCadence?: DigestCadence;
	/** Endpoint that persists the user preference (POST). Defaults to `/ai/digest-subscription`. */
	updatePath?: string;
}

export const DigestSubscription: FC<DigestSubscriptionProps> = ( {
	api,
	initialCadence = 'off',
	updatePath = '/ai/digest-subscription',
} ) => {
	const apiClient = useApi( api );
	const [ cadence, setCadence ] = useState<DigestCadence>( initialCadence );
	const [ isSaving, setIsSaving ] = useState( false );
	const [ status, setStatus ] = useState<string | null>( null );
	const [ error, setError ] = useState<string | null>( null );

	const save = useCallback( async (): Promise<void> => {
		setIsSaving( true );
		setStatus( null );
		setError( null );

		try {
			await apiClient.post( updatePath, { cadence } );
			setStatus( cadence === 'off' ? 'Digest emails turned off.' : 'Digest preference saved.' );
		} catch ( caught ) {
			setError( caught instanceof Error ? caught.message : 'Could not save preference.' );
		} finally {
			setIsSaving( false );
		}
	}, [ apiClient, cadence, updatePath ] );

	return (
		<div className="analytics-ai-digest-subscription" data-feature="analytics.digest_email">
			<form
				className="analytics-ai-digest-subscription__form"
				onSubmit={ ( event ) => {
					event.preventDefault();
					void save();
				} }
			>
				<fieldset>
					<legend className="analytics-ai-digest-subscription__legend">
						Analytics digest email
					</legend>
					<p className="analytics-ai-digest-subscription__hint">
						Receive a plain-language summary of your site&apos;s traffic on your chosen cadence.
					</p>

					{ ( [ 'off', 'weekly', 'monthly' ] as DigestCadence[] ).map( ( value ) => (
						<label key={ value } className="analytics-ai-digest-subscription__option">
							<input
								type="radio"
								name="cadence"
								value={ value }
								checked={ cadence === value }
								onChange={ () => setCadence( value ) }
							/>
							<span>{ value.charAt( 0 ).toUpperCase() + value.slice( 1 ) }</span>
						</label>
					) ) }
				</fieldset>

				<button
					type="submit"
					disabled={ isSaving }
					className="analytics-ai-digest-subscription__save"
				>
					{ isSaving ? 'Saving…' : 'Save preference' }
				</button>

				{ status && (
					<p className="analytics-ai-digest-subscription__status" role="status">
						{ status }
					</p>
				) }

				{ error && (
					<p className="analytics-ai-digest-subscription__error" role="alert">
						{ error }
					</p>
				) }
			</form>
		</div>
	);
};

export default DigestSubscription;
