<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Enums;

/**
 * Standard event types for analytics tracking.
 *
 * Defines core, form, ecommerce, and booking event types
 * with automatic category inference support.
 *
 * @since 1.0.0
 */
enum EventType: string
{
    /**
     * Get the category for this event type.
     *
     * @return string|null The category name or null for core events.
     *
     * @since 1.0.0
     */
    public function getCategory(): ?string
    {
        return match ( $this ) {
            // Form events
            self::FORM_VIEW,
            self::FORM_START,
            self::FORM_SUBMIT,
            self::FORM_ERROR => 'forms',

            // Ecommerce events
            self::PRODUCT_VIEW,
            self::ADD_TO_CART,
            self::REMOVE_FROM_CART,
            self::BEGIN_CHECKOUT,
            self::ADD_PAYMENT_INFO,
            self::PURCHASE,
            self::REFUND => 'ecommerce',

            // Booking events
            self::SERVICE_VIEW,
            self::BOOKING_START,
            self::TIME_SELECTED,
            self::BOOKING_CREATED,
            self::BOOKING_CANCELLED => 'booking',

            // Engagement events
            self::CLICK,
            self::SCROLL,
            self::DOWNLOAD,
            self::SEARCH,
            self::OUTBOUND_LINK,
            self::VIDEO_PLAY,
            self::VIDEO_COMPLETE => 'engagement',

            // Core/session events have no category
            default => null,
        };
    }

    /**
     * Get the required properties for this event type.
     *
     * @return array<int, string> List of required property names.
     *
     * @since 1.0.0
     */
    public function getRequiredProperties(): array
    {
        return match ( $this ) {
            self::FORM_SUBMIT       => ['form_id'],
            self::FORM_VIEW         => ['form_id'],
            self::FORM_START        => ['form_id'],
            self::FORM_ERROR        => ['form_id', 'error_type'],
            self::PURCHASE          => ['order_id', 'total'],
            self::REFUND            => ['order_id'],
            self::PRODUCT_VIEW      => ['product_id'],
            self::ADD_TO_CART       => ['product_id'],
            self::REMOVE_FROM_CART  => ['product_id'],
            self::BOOKING_CREATED   => ['booking_id', 'service_id'],
            self::BOOKING_CANCELLED => ['booking_id'],
            self::SERVICE_VIEW      => ['service_id'],
            self::BOOKING_START     => ['service_id'],
            self::TIME_SELECTED     => ['service_id', 'datetime'],
            self::DOWNLOAD          => ['file_name'],
            self::OUTBOUND_LINK     => ['url'],
            self::SEARCH            => ['query'],
            self::SCROLL            => ['depth'],
            self::VIDEO_PLAY        => ['video_id'],
            self::VIDEO_COMPLETE    => ['video_id'],
            default                 => [],
        };
    }

    /**
     * Check if this event type typically has a value.
     *
     * @return bool True if this event type usually has a value.
     *
     * @since 1.0.0
     */
    public function hasValue(): bool
    {
        return match ( $this ) {
            self::PURCHASE,
            self::REFUND,
            self::ADD_TO_CART,
            self::BOOKING_CREATED => true,
            default               => false,
        };
    }

    /**
     * Infer the category from an event name string.
     *
     * @param  string  $eventName  The event name.
     *
     * @return string|null The inferred category or null.
     *
     * @since 1.0.0
     */
    public static function inferCategory( string $eventName ): ?string
    {
        // Try to get from enum first
        $type = self::tryFrom( $eventName );

        if ( null !== $type ) {
            return $type->getCategory();
        }

        // Infer from name patterns
        return match ( true ) {
            str_starts_with( $eventName, 'form_' )                                                  => 'forms',
            str_starts_with( $eventName, 'product_' )                                               => 'ecommerce',
            str_starts_with( $eventName, 'booking_' )                                               => 'booking',
            str_starts_with( $eventName, 'service_' )                                               => 'booking',
            str_starts_with( $eventName, 'video_' )                                                 => 'engagement',
            in_array( $eventName, ['add_to_cart', 'remove_from_cart', 'purchase', 'refund'], true ) => 'ecommerce',
            in_array( $eventName, ['click', 'scroll', 'download', 'search'], true )                 => 'engagement',
            default                                                                                 => null,
        };
    }

    /**
     * Get all event types for a specific category.
     *
     * @param  string  $category  The category to filter by.
     *
     * @return array<int, EventType> Event types in the category.
     *
     * @since 1.0.0
     */
    public static function forCategory( string $category ): array
    {
        return array_filter(
            self::cases(),
            fn ( EventType $type ) => $type->getCategory() === $category,
        );
    }

    /**
     * Get all core (session-related) event types.
     *
     * @return array<int, EventType>
     *
     * @since 1.0.0
     */
    public static function coreEvents(): array
    {
        return [
            self::PAGE_VIEW,
            self::SESSION_START,
            self::SESSION_END,
        ];
    }

    /**
     * Get all form event types.
     *
     * @return array<int, EventType>
     *
     * @since 1.0.0
     */
    public static function formEvents(): array
    {
        return self::forCategory( 'forms' );
    }

    /**
     * Get all ecommerce event types.
     *
     * @return array<int, EventType>
     *
     * @since 1.0.0
     */
    public static function ecommerceEvents(): array
    {
        return self::forCategory( 'ecommerce' );
    }

    /**
     * Get all booking event types.
     *
     * @return array<int, EventType>
     *
     * @since 1.0.0
     */
    public static function bookingEvents(): array
    {
        return self::forCategory( 'booking' );
    }

    /**
     * Get all engagement event types.
     *
     * @return array<int, EventType>
     *
     * @since 1.0.0
     */
    public static function engagementEvents(): array
    {
        return self::forCategory( 'engagement' );
    }
    // Core Events
    case PAGE_VIEW      = 'page_view';
    case SESSION_START  = 'session_start';
    case SESSION_END    = 'session_end';
    case CLICK          = 'click';
    case SCROLL         = 'scroll';
    case SEARCH         = 'search';
    case DOWNLOAD       = 'download';
    case OUTBOUND_LINK  = 'outbound_link';
    case VIDEO_PLAY     = 'video_play';
    case VIDEO_COMPLETE = 'video_complete';

    // Form Events
    case FORM_VIEW   = 'form_view';
    case FORM_START  = 'form_start';
    case FORM_SUBMIT = 'form_submitted';
    case FORM_ERROR  = 'form_error';

    // Ecommerce Events
    case PRODUCT_VIEW     = 'product_view';
    case ADD_TO_CART      = 'add_to_cart';
    case REMOVE_FROM_CART = 'remove_from_cart';
    case BEGIN_CHECKOUT   = 'begin_checkout';
    case ADD_PAYMENT_INFO = 'add_payment_info';
    case PURCHASE         = 'purchase';
    case REFUND           = 'refund';

    // Booking Events
    case SERVICE_VIEW      = 'service_view';
    case BOOKING_START     = 'booking_start';
    case TIME_SELECTED     = 'time_selected';
    case BOOKING_CREATED   = 'booking_created';
    case BOOKING_CANCELLED = 'booking_cancelled';
}
