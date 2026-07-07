/**
 * useApi hook for authenticated API communication.
 *
 * @package    ArtisanPack_UI
 * @subpackage Analytics
 *
 * @since      1.3.0
 */

import { useCallback, useMemo } from 'react';

export class ApiValidationError extends Error {
	public readonly errors: Record<string, string[]>;
	public readonly status: number;

	constructor( message: string, errors: Record<string, string[]>, status: number ) {
		super( message );
		this.name = 'ApiValidationError';
		this.errors = errors;
		this.status = status;
	}
}

export class ApiError extends Error {
	public readonly status: number;

	constructor( message: string, status: number ) {
		super( message );
		this.name = 'ApiError';
		this.status = status;
	}
}

export interface UseApiOptions {
	baseUrl: string;
	csrfToken?: string;
	authorization?: string;
	credentials?: RequestCredentials;
}

export interface UseApiReturn {
	get: <T>( path: string, params?: Record<string, string> ) => Promise<T>;
	post: <T>( path: string, body?: unknown ) => Promise<T>;
}

function getMetaCsrfToken(): string | null {
	const meta = document.querySelector( 'meta[name="csrf-token"]' );

	return meta?.getAttribute( 'content' ) ?? null;
}

function getXsrfToken(): string | null {
	const match = document.cookie.match( /(?:^|;\s*)XSRF-TOKEN=([^;]*)/ );

	return match ? decodeURIComponent( match[1] ) : null;
}

export function useApi( options: UseApiOptions ): UseApiReturn {
	const { baseUrl, csrfToken, authorization, credentials = 'include' } = options;

	const buildHeaders = useCallback(
		(): Record<string, string> => {
			const headers: Record<string, string> = {
				Accept: 'application/json',
				'Content-Type': 'application/json',
			};

			const token = csrfToken ?? getMetaCsrfToken();

			if ( token ) {
				headers['X-CSRF-TOKEN'] = token;
			}

			const xsrf = getXsrfToken();

			if ( xsrf ) {
				headers['X-XSRF-TOKEN'] = xsrf;
			}

			if ( authorization ) {
				headers.Authorization = authorization;
			}

			return headers;
		},
		[ csrfToken, authorization ],
	);

	const buildUrl = useCallback(
		( path: string, params?: Record<string, string> ): string => {
			const url = new URL( `${ baseUrl }${ path }`, window.location.origin );

			if ( params ) {
				for ( const [ key, value ] of Object.entries( params ) ) {
					if ( value !== undefined && value !== '' ) {
						url.searchParams.set( key, value );
					}
				}
			}

			return url.toString();
		},
		[ baseUrl ],
	);

	const handleResponse = useCallback( async <T>( response: Response ): Promise<T> => {
		if ( 204 === response.status ) {
			return undefined as T;
		}

		if ( 422 === response.status ) {
			const data = await response.json().catch( () => ( {} ) );
			throw new ApiValidationError( data.message ?? 'Validation failed.', data.errors ?? {}, 422 );
		}

		if ( ! response.ok ) {
			let message = `Request failed with status ${ response.status }`;

			try {
				const data = await response.json();
				message = data.message ?? message;
			} catch {
				// Use default message
			}

			throw new ApiError( message, response.status );
		}

		return response.json() as Promise<T>;
	}, [] );

	const get = useCallback(
		async <T>( path: string, params?: Record<string, string> ): Promise<T> => {
			const response = await fetch( buildUrl( path, params ), {
				method: 'GET',
				headers: buildHeaders(),
				credentials,
			} );

			return handleResponse<T>( response );
		},
		[ buildUrl, buildHeaders, handleResponse, credentials ],
	);

	const post = useCallback(
		async <T>( path: string, body?: unknown ): Promise<T> => {
			const response = await fetch( buildUrl( path ), {
				method: 'POST',
				headers: buildHeaders(),
				credentials,
				body: body !== undefined ? JSON.stringify( body ) : undefined,
			} );

			return handleResponse<T>( response );
		},
		[ buildUrl, buildHeaders, handleResponse, credentials ],
	);

	return useMemo( () => ( { get, post } ), [ get, post ] );
}
