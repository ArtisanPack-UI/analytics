/**
 * Vue composable for managing user consent preferences.
 *
 * Integrates with the analytics consent API endpoints to check, grant,
 * and revoke consent for tracking categories (analytics, marketing, etc.).
 * Supports GDPR and CCPA compliance modes with localStorage persistence
 * and cookie synchronization.
 *
 * @since 1.1.0
 */

import { computed, onUnmounted, ref, type Ref } from 'vue';

import type { ConsentStatusItem, ConsentStatusResponse, ConsentUpdateResponse } from '../types';

/** Storage keys for consent data. */
const STORAGE_KEY = 'ap_analytics_consent';
const VISITOR_KEY = 'ap_visitor_id';
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
    loading: Ref<boolean>;
    /** Error message if the last operation failed. */
    error: Ref<string | null>;
    /** Whether consent is required by the application config. */
    consentRequired: Ref<boolean>;
    /** Current consent status by category key. */
    categories: Ref<Record<string, ConsentStatusItem>>;
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
 * Get the visitor ID from localStorage.
 */
function getVisitorId(): string | null {
    if ( typeof localStorage === 'undefined' ) {
        return null;
    }

    return localStorage.getItem( VISITOR_KEY );
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
 * Composable for managing consent preferences with API integration.
 */
export function useConsent( options: UseConsentOptions = {} ): UseConsentResult {
    const {
        apiPrefix = 'api/analytics',
        fetchOnMount = true,
        initialCategories = {},
        initialConsentRequired = false,
    } = options;

    const loading = ref( false );
    const error = ref<string | null>( null );
    const consentRequired = ref( initialConsentRequired );
    const categories = ref<Record<string, ConsentStatusItem>>( initialCategories );

    let abortController: AbortController | null = null;

    async function fetchStatus(): Promise<void> {
        const visitorId = getVisitorId();

        if ( ! visitorId ) {
            return;
        }

        abortController?.abort();
        const controller = new AbortController();
        abortController = controller;

        loading.value = true;
        error.value = null;

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
            consentRequired.value = json.consent_required;
            categories.value = json.categories ?? {};
        } catch ( err ) {
            if ( err instanceof DOMException && err.name === 'AbortError' ) {
                return;
            }

            error.value = err instanceof Error ? err.message : 'An unexpected error occurred';
        } finally {
            if ( ! controller.signal.aborted ) {
                loading.value = false;
            }
        }
    }

    async function updateConsent( updates: Record<string, boolean> ): Promise<void> {
        // Save previous state for rollback on server error
        const prev = { ...categories.value };
        const prevMergedState = Object.fromEntries(
            Object.entries( prev ).map( ( [ k, v ] ) => [ k, v.granted ] ),
        );

        // Apply changes optimistically so the UI updates immediately
        const next = { ...categories.value };

        for ( const [ key, granted ] of Object.entries( updates ) ) {
            if ( next[ key ] ) {
                next[ key ] = {
                    ...next[ key ],
                    granted,
                    granted_at: granted ? new Date().toISOString() : next[ key ].granted_at,
                };
            }
        }

        categories.value = next;

        // Persist the full merged state, not just the partial updates
        const mergedState = Object.fromEntries(
            Object.entries( next ).map( ( [ k, v ] ) => [ k, v.granted ] ),
        );
        persistLocally( mergedState );

        // Sync with server if a visitor ID is available
        const visitorId = getVisitorId();

        if ( ! visitorId ) {
            return;
        }

        loading.value = true;
        error.value = null;

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
            categories.value = json.categories ?? {};
        } catch ( err ) {
            error.value = err instanceof Error ? err.message : 'An unexpected error occurred';

            // Rollback optimistic update on server error
            categories.value = prev;
            persistLocally( prevMergedState );
        } finally {
            loading.value = false;
        }
    }

    function hasConsent( category: string ): boolean {
        return categories.value[ category ]?.granted ?? false;
    }

    async function grantConsent( cats: string[] ): Promise<void> {
        const updates: Record<string, boolean> = {};

        for ( const key of Object.keys( categories.value ) ) {
            updates[ key ] = categories.value[ key ].granted;
        }

        for ( const cat of cats ) {
            updates[ cat ] = true;
        }

        await updateConsent( updates );
    }

    async function revokeConsent( cats: string[] ): Promise<void> {
        const updates: Record<string, boolean> = {};

        for ( const key of Object.keys( categories.value ) ) {
            updates[ key ] = categories.value[ key ].granted;
        }

        for ( const cat of cats ) {
            updates[ cat ] = false;
        }

        await updateConsent( updates );
    }

    async function acceptAll(): Promise<void> {
        const updates: Record<string, boolean> = {};

        for ( const key of Object.keys( categories.value ) ) {
            updates[ key ] = true;
        }

        await updateConsent( updates );
    }

    async function rejectAll(): Promise<void> {
        const updates: Record<string, boolean> = {};

        for ( const key of Object.keys( categories.value ) ) {
            updates[ key ] = categories.value[ key ].required;
        }

        await updateConsent( updates );
    }

    if ( fetchOnMount ) {
        fetchStatus();
    }

    onUnmounted( () => {
        abortController?.abort();
    } );

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
