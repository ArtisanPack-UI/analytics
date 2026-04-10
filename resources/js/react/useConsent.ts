/**
 * React hook for managing user consent preferences.
 *
 * Integrates with the analytics consent API endpoints to check, grant,
 * and revoke consent for tracking categories (analytics, marketing, etc.).
 * Supports GDPR and CCPA compliance modes with localStorage persistence
 * and cookie synchronization.
 *
 * @since 1.1.0
 */

import { useCallback, useEffect, useRef, useState } from 'react';

import type { ConsentStatusItem, ConsentStatusResponse, ConsentUpdateResponse } from '../types';

/** Storage keys for consent data. */
const STORAGE_KEY = 'ap_analytics_consent';
const VISITOR_KEY = 'ap_visitor_id';
const VISITOR_COOKIE = '_ap_vid';
const COOKIE_NAME = 'ap_consent';
const COOKIE_EXPIRY_DAYS = 365;

export interface UseConsentOptions {
    /** The API route prefix. Defaults to 'api/analytics'. */
    apiPrefix?: string;
    /** Whether to fetch consent status on mount. Defaults to true. */
    fetchOnMount?: boolean;
    /** Initial categories to populate before any API call. Useful for SSR or demos. */
    initialCategories?: Record<string, ConsentStatusItem>;
    /** Initial consent-required flag. Useful for SSR or demos. */
    initialConsentRequired?: boolean;
}

export interface UseConsentResult {
    /** Whether consent data is currently loading. */
    loading: boolean;
    /** Error message if the last operation failed. */
    error: string | null;
    /** Whether consent is required by the application config. */
    consentRequired: boolean;
    /** Current consent status by category key. */
    categories: Record<string, ConsentStatusItem>;
    /** Check if a specific category has been granted. */
    hasConsent: ( category: string ) => boolean;
    /** Grant consent for the given categories. */
    grantConsent: ( categories: string[] ) => Promise<void>;
    /** Revoke consent for the given categories. */
    revokeConsent: ( categories: string[] ) => Promise<void>;
    /** Accept all consent categories. */
    acceptAll: () => Promise<void>;
    /** Reject all non-required consent categories. */
    rejectAll: () => Promise<void>;
    /** Update consent for specific categories. */
    updateConsent: ( updates: Record<string, boolean> ) => Promise<void>;
    /** Refresh consent status from the server. */
    refresh: () => Promise<void>;
}

/**
 * Set a cookie value with expiry.
 */
function setCookie( name: string, value: string, days: number ): void {
    if ( typeof document === 'undefined' ) {
        return;
    }

    const date = new Date();
    date.setTime( date.getTime() + days * 24 * 60 * 60 * 1000 );
    document.cookie = `${name}=${encodeURIComponent( value )}; expires=${date.toUTCString()}; path=/; SameSite=Lax`;
}

/**
 * Get the CSRF token from the meta tag.
 */
function getCsrfToken(): string {
    if ( typeof document === 'undefined' ) {
        return '';
    }

    return document.querySelector( 'meta[name="csrf-token"]' )?.getAttribute( 'content' ) ?? '';
}

/**
 * Read a cookie value by name.
 */
function getCookie( name: string ): string | null {
    if ( typeof document === 'undefined' ) {
        return null;
    }

    const match = document.cookie.match( new RegExp( `(?:^|; )${name}=([^;]*)` ) );

    return match ? decodeURIComponent( match[ 1 ] ) : null;
}

/**
 * Generate a v4-style UUID.
 */
function generateUuid(): string {
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace( /[xy]/g, ( c ) => {
        const r = ( Math.random() * 16 ) | 0;

        return ( c === 'x' ? r : ( r & 0x3 ) | 0x8 ).toString( 16 );
    } );
}

/**
 * Get or create a visitor ID.
 *
 * Checks localStorage first, then falls back to the tracker cookie (_ap_vid).
 * If neither exists, generates a new UUID and persists it to both localStorage
 * and the visitor cookie so subsequent calls return the same ID.
 */
function getVisitorId(): string | null {
    if ( typeof localStorage === 'undefined' ) {
        return null;
    }

    // Check localStorage first
    let id = localStorage.getItem( VISITOR_KEY );

    if ( id ) {
        return id;
    }

    // Fall back to the tracker cookie
    id = getCookie( VISITOR_COOKIE );

    if ( id ) {
        localStorage.setItem( VISITOR_KEY, id );

        return id;
    }

    // Generate a new visitor ID
    id = generateUuid();
    localStorage.setItem( VISITOR_KEY, id );
    setCookie( VISITOR_COOKIE, id, COOKIE_EXPIRY_DAYS );

    return id;
}

/**
 * Persist consent categories to localStorage and cookie.
 */
function persistLocally( categories: Record<string, boolean> ): void {
    if ( typeof localStorage === 'undefined' ) {
        return;
    }

    const value = JSON.stringify( categories );
    localStorage.setItem( STORAGE_KEY, value );
    setCookie( COOKIE_NAME, value, COOKIE_EXPIRY_DAYS );
}

/**
 * Hook for managing consent preferences with API integration.
 */
export function useConsent( options: UseConsentOptions = {} ): UseConsentResult {
    const {
        apiPrefix = 'api/analytics',
        fetchOnMount = true,
        initialCategories = {},
        initialConsentRequired = false,
    } = options;

    const [ loading, setLoading ] = useState( false );
    const [ error, setError ] = useState<string | null>( null );
    const [ consentRequired, setConsentRequired ] = useState( initialConsentRequired );
    const [ categories, setCategories ] = useState<Record<string, ConsentStatusItem>>( initialCategories );
    const abortRef = useRef<AbortController | null>( null );

    const fetchStatus = useCallback( async (): Promise<void> => {
        const visitorId = getVisitorId();

        if ( ! visitorId ) {
            return;
        }

        abortRef.current?.abort();
        const controller = new AbortController();
        abortRef.current = controller;

        setLoading( true );
        setError( null );

        try {
            const response = await fetch(
                `/${apiPrefix}/consent?visitor_id=${encodeURIComponent( visitorId )}`,
                {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    signal: controller.signal,
                },
            );

            if ( ! response.ok ) {
                throw new Error( `HTTP ${response.status}: ${response.statusText}` );
            }

            const json: ConsentStatusResponse = await response.json();
            setConsentRequired( json.consent_required );
            setCategories( json.categories ?? {} );
        } catch ( err ) {
            if ( err instanceof DOMException && err.name === 'AbortError' ) {
                return;
            }

            setError( err instanceof Error ? err.message : 'An unexpected error occurred' );
        } finally {
            if ( ! controller.signal.aborted ) {
                setLoading( false );
            }
        }
    }, [ apiPrefix ] );

    const updateConsent = useCallback( async ( updates: Record<string, boolean> ): Promise<void> => {
        // Save previous state for rollback on server error
        let prevCategories: Record<string, ConsentStatusItem> = {};
        let prevMergedState: Record<string, boolean> = {};
        let mergedState: Record<string, boolean> = {};

        // Apply changes optimistically so the UI updates immediately
        setCategories( ( prev ) => {
            prevCategories = prev;
            prevMergedState = Object.fromEntries(
                Object.entries( prev ).map( ( [ k, v ] ) => [ k, v.granted ] ),
            );

            const next = { ...prev };

            for ( const [ key, granted ] of Object.entries( updates ) ) {
                if ( next[ key ] ) {
                    next[ key ] = {
                        ...next[ key ],
                        granted,
                        granted_at: granted ? new Date().toISOString() : next[ key ].granted_at,
                    };
                }
            }

            mergedState = Object.fromEntries(
                Object.entries( next ).map( ( [ k, v ] ) => [ k, v.granted ] ),
            );

            return next;
        } );

        persistLocally( mergedState );

        // Sync with server if a visitor ID is available
        const visitorId = getVisitorId();

        if ( ! visitorId ) {
            return;
        }

        setLoading( true );
        setError( null );

        try {
            const response = await fetch( `/${apiPrefix}/consent`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
                credentials: 'same-origin',
                body: JSON.stringify( {
                    visitor_id: visitorId,
                    categories: updates,
                } ),
            } );

            if ( ! response.ok ) {
                throw new Error( `HTTP ${response.status}: ${response.statusText}` );
            }

            const json: ConsentUpdateResponse = await response.json();
            setCategories( json.categories ?? {} );
        } catch ( err ) {
            setError( err instanceof Error ? err.message : 'An unexpected error occurred' );

            // Rollback optimistic update on server error
            setCategories( prevCategories );
            persistLocally( prevMergedState );

            throw err;
        } finally {
            setLoading( false );
        }
    }, [ apiPrefix ] );

    const hasConsent = useCallback( ( category: string ): boolean => {
        return categories[ category ]?.granted ?? false;
    }, [ categories ] );

    const grantConsent = useCallback( async ( cats: string[] ): Promise<void> => {
        const updates: Record<string, boolean> = {};

        for ( const key of Object.keys( categories ) ) {
            updates[ key ] = categories[ key ].granted;
        }

        for ( const cat of cats ) {
            updates[ cat ] = true;
        }

        await updateConsent( updates );
    }, [ categories, updateConsent ] );

    const revokeConsent = useCallback( async ( cats: string[] ): Promise<void> => {
        const updates: Record<string, boolean> = {};

        for ( const key of Object.keys( categories ) ) {
            updates[ key ] = categories[ key ].granted;
        }

        for ( const cat of cats ) {
            updates[ cat ] = false;
        }

        await updateConsent( updates );
    }, [ categories, updateConsent ] );

    const acceptAll = useCallback( async (): Promise<void> => {
        const updates: Record<string, boolean> = {};

        for ( const key of Object.keys( categories ) ) {
            updates[ key ] = true;
        }

        await updateConsent( updates );
    }, [ categories, updateConsent ] );

    const rejectAll = useCallback( async (): Promise<void> => {
        const updates: Record<string, boolean> = {};

        for ( const key of Object.keys( categories ) ) {
            updates[ key ] = categories[ key ].required;
        }

        await updateConsent( updates );
    }, [ categories, updateConsent ] );

    useEffect( () => {
        if ( fetchOnMount ) {
            fetchStatus();
        }

        return () => {
            abortRef.current?.abort();
        };
    }, [ fetchStatus, fetchOnMount ] );

    return {
        loading,
        error,
        consentRequired,
        categories,
        hasConsent,
        grantConsent,
        revokeConsent,
        acceptAll,
        rejectAll,
        updateConsent,
        refresh: fetchStatus,
    };
}
