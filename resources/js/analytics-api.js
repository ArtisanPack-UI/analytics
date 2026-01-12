/**
 * ArtisanPack Analytics API
 *
 * A simplified, developer-friendly API for analytics tracking.
 * This wraps the core ArtisanPackAnalytics tracker with a cleaner interface.
 *
 * @version 1.0.0
 * @license MIT
 */
(function(window, document) {
    'use strict';

    // =========================================================================
    // Event Emitter
    // =========================================================================

    var EventEmitter = {
        _listeners: {},

        on: function(event, callback) {
            if (!this._listeners[event]) {
                this._listeners[event] = [];
            }
            this._listeners[event].push(callback);
            return this;
        },

        off: function(event, callback) {
            if (!this._listeners[event]) return this;

            if (callback) {
                var index = this._listeners[event].indexOf(callback);
                if (index > -1) {
                    this._listeners[event].splice(index, 1);
                }
            } else {
                delete this._listeners[event];
            }
            return this;
        },

        emit: function(event, data) {
            if (!this._listeners[event]) return;

            for (var i = 0; i < this._listeners[event].length; i++) {
                try {
                    this._listeners[event][i](data);
                } catch (e) {
                    console.error('[APAnalytics] Event listener error:', e);
                }
            }
        }
    };

    // =========================================================================
    // APAnalytics API
    // =========================================================================

    var APAnalytics = {
        version: '1.0.0',
        _enabled: true,
        _config: {},
        _initialized: false,

        /**
         * Configure the analytics instance.
         *
         * @param {Object} options Configuration options
         * @returns {APAnalytics}
         */
        configure: function(options) {
            this._config = Object.assign({}, this._config, options || {});

            // Pass config to core tracker if available
            if (window.ArtisanPackAnalytics) {
                window.ArtisanPackAnalytics.init(this._config);
            }

            EventEmitter.emit('configure', this._config);
            return this;
        },

        /**
         * Initialize the analytics API.
         *
         * @param {Object} options Configuration options
         * @returns {APAnalytics}
         */
        init: function(options) {
            if (this._initialized) {
                return this;
            }

            this.configure(options);
            this._initialized = true;
            EventEmitter.emit('init', this._config);

            return this;
        },

        /**
         * Enable analytics tracking.
         *
         * @returns {APAnalytics}
         */
        enable: function() {
            this._enabled = true;
            EventEmitter.emit('enable');
            return this;
        },

        /**
         * Disable analytics tracking.
         *
         * @returns {APAnalytics}
         */
        disable: function() {
            this._enabled = false;
            EventEmitter.emit('disable');
            return this;
        },

        /**
         * Check if analytics tracking is enabled.
         *
         * @returns {boolean}
         */
        isEnabled: function() {
            return this._enabled && this._getTracker() !== null;
        },

        /**
         * Track a page view.
         *
         * @param {string|null} path The page path (optional, defaults to current)
         * @param {string|null} title The page title (optional, defaults to document.title)
         * @param {Object|null} customData Additional custom data
         * @returns {APAnalytics}
         */
        trackPageView: function(path, title, customData) {
            if (!this._enabled) return this;

            var tracker = this._getTracker();
            if (!tracker) return this;

            var data = Object.assign({}, customData || {});

            if (path) {
                data.path = path;
            }

            if (title) {
                data.title = title;
            }

            tracker.pageView(data);
            EventEmitter.emit('pageview', data);

            return this;
        },

        /**
         * Track a custom event.
         *
         * @param {string} name Event name
         * @param {Object|null} properties Event properties
         * @param {number|null} value Monetary value
         * @param {string|null} category Event category
         * @returns {APAnalytics}
         */
        trackEvent: function(name, properties, value, category) {
            if (!this._enabled) return this;

            var tracker = this._getTracker();
            if (!tracker) return this;

            var props = Object.assign({}, properties || {});

            if (category) {
                props.category = category;
            }

            var options = {};
            if (value !== undefined && value !== null) {
                options.value = value;
            }

            tracker.event(name, props, options);
            EventEmitter.emit('event', { name: name, properties: props, value: value, category: category });

            return this;
        },

        /**
         * Track a form submission.
         *
         * @param {string} formId The form identifier
         * @param {Object|null} data Additional form data
         * @returns {APAnalytics}
         */
        trackForm: function(formId, data) {
            if (!this._enabled) return this;

            var tracker = this._getTracker();
            if (!tracker) return this;

            var props = Object.assign({ form_id: formId }, data || {});

            tracker.track.formSubmit(formId, props);
            EventEmitter.emit('form', { formId: formId, data: props });

            return this;
        },

        /**
         * Track a purchase.
         *
         * @param {number} value Purchase value
         * @param {string|null} currency Currency code (e.g., 'USD')
         * @param {Array|null} items Array of purchased items
         * @returns {APAnalytics}
         */
        trackPurchase: function(value, currency, items) {
            if (!this._enabled) return this;

            var tracker = this._getTracker();
            if (!tracker) return this;

            var props = {
                total: value,
                currency: currency || 'USD'
            };

            if (items && items.length) {
                props.items = items;
                props.item_count = items.length;
            }

            tracker.event('purchase', props, { value: value });
            EventEmitter.emit('purchase', { value: value, currency: currency, items: items });

            return this;
        },

        /**
         * Track a goal conversion.
         *
         * @param {number|string} goalId The goal identifier
         * @param {number|null} value Conversion value
         * @returns {APAnalytics}
         */
        trackConversion: function(goalId, value) {
            if (!this._enabled) return this;

            var tracker = this._getTracker();
            if (!tracker) return this;

            var props = {
                goal_id: goalId
            };

            var options = {};
            if (value !== undefined && value !== null) {
                props.value = value;
                options.value = value;
            }

            tracker.event('conversion', props, options);
            EventEmitter.emit('conversion', { goalId: goalId, value: value });

            return this;
        },

        /**
         * Track when a user clicks an element.
         *
         * @param {HTMLElement|string} element Element or selector
         * @param {Object|null} properties Additional properties
         * @returns {APAnalytics}
         */
        trackClick: function(element, properties) {
            if (!this._enabled) return this;

            var tracker = this._getTracker();
            if (!tracker) return this;

            var el = typeof element === 'string' ? document.querySelector(element) : element;
            if (!el) return this;

            tracker.track.click(el, properties);
            EventEmitter.emit('click', { element: el, properties: properties });

            return this;
        },

        /**
         * Track when a user adds an item to cart.
         *
         * @param {string} productId Product identifier
         * @param {Object|null} properties Additional properties
         * @returns {APAnalytics}
         */
        trackAddToCart: function(productId, properties) {
            if (!this._enabled) return this;

            var tracker = this._getTracker();
            if (!tracker) return this;

            tracker.track.addToCart(productId, properties);
            EventEmitter.emit('addToCart', { productId: productId, properties: properties });

            return this;
        },

        // =====================================================================
        // Consent Management
        // =====================================================================

        /**
         * Check if user has granted consent for a category.
         *
         * @param {string|null} type Consent category (defaults to 'analytics')
         * @returns {boolean}
         */
        hasConsent: function(type) {
            var tracker = this._getTracker();
            if (!tracker) return false;

            return tracker.consent.hasConsent(type || 'analytics');
        },

        /**
         * Grant consent for categories.
         *
         * @param {string|Array} categories Category or array of categories
         * @returns {APAnalytics}
         */
        setConsent: function(categories) {
            var tracker = this._getTracker();
            if (!tracker) return this;

            tracker.consent.grant(categories);
            EventEmitter.emit('consent:grant', { categories: categories });

            return this;
        },

        /**
         * Revoke consent for categories.
         *
         * @param {string|Array|null} categories Category or array (null = all)
         * @returns {APAnalytics}
         */
        revokeConsent: function(categories) {
            var tracker = this._getTracker();
            if (!tracker) return this;

            tracker.consent.revoke(categories);
            EventEmitter.emit('consent:revoke', { categories: categories });

            return this;
        },

        /**
         * Check if tracking is allowed (DNT and consent checks).
         *
         * @returns {boolean}
         */
        canTrack: function() {
            var tracker = this._getTracker();
            if (!tracker) return false;

            return tracker.consent.check();
        },

        // =====================================================================
        // Session & Visitor
        // =====================================================================

        /**
         * Get the current session ID.
         *
         * @returns {string|null}
         */
        getSessionId: function() {
            var tracker = this._getTracker();
            if (!tracker) return null;

            return tracker.session.getId();
        },

        /**
         * End the current session.
         *
         * @returns {APAnalytics}
         */
        endSession: function() {
            var tracker = this._getTracker();
            if (!tracker) return this;

            tracker.session.end();
            EventEmitter.emit('session:end');

            return this;
        },

        /**
         * Get the current visitor ID.
         *
         * @returns {string|null}
         */
        getVisitorId: function() {
            var tracker = this._getTracker();
            if (!tracker) return null;

            return tracker.visitor.getId();
        },

        // =====================================================================
        // Event Listeners
        // =====================================================================

        /**
         * Register an event listener.
         *
         * @param {string} event Event name
         * @param {Function} callback Callback function
         * @returns {APAnalytics}
         */
        on: function(event, callback) {
            EventEmitter.on(event, callback);
            return this;
        },

        /**
         * Remove an event listener.
         *
         * @param {string} event Event name
         * @param {Function|null} callback Specific callback or null for all
         * @returns {APAnalytics}
         */
        off: function(event, callback) {
            EventEmitter.off(event, callback);
            return this;
        },

        // =====================================================================
        // Debugging
        // =====================================================================

        /**
         * Enable or disable debug mode.
         *
         * @param {boolean} enabled Enable debug logging
         * @returns {APAnalytics}
         */
        debug: function(enabled) {
            var tracker = this._getTracker();
            if (tracker) {
                tracker.debug(enabled !== false);
            }
            return this;
        },

        // =====================================================================
        // Internal Methods
        // =====================================================================

        /**
         * Get the core tracker instance.
         *
         * @returns {Object|null}
         * @private
         */
        _getTracker: function() {
            return window.ArtisanPackAnalytics || null;
        }
    };

    // =========================================================================
    // Auto-bind Elements
    // =========================================================================

    /**
     * Auto-bind elements with data attributes for tracking.
     *
     * Usage:
     * <button data-ap-event="signup_click" data-ap-category="cta">Sign Up</button>
     * <form data-ap-form="contact_form">...</form>
     */
    function autoBind() {
        // Bind click events
        document.addEventListener('click', function(e) {
            var target = e.target.closest('[data-ap-event]');
            if (target) {
                var eventName = target.getAttribute('data-ap-event');
                var category = target.getAttribute('data-ap-category');
                var value = target.getAttribute('data-ap-value');

                var props = {};
                var dataAttrs = target.attributes;
                for (var i = 0; i < dataAttrs.length; i++) {
                    var attr = dataAttrs[i];
                    if (attr.name.indexOf('data-ap-prop-') === 0) {
                        var propName = attr.name.replace('data-ap-prop-', '');
                        props[propName] = attr.value;
                    }
                }

                var parsedValue = value ? parseFloat(value) : null;
                if (parsedValue !== null && isNaN(parsedValue)) {
                    parsedValue = null;
                }

                APAnalytics.trackEvent(
                    eventName,
                    Object.keys(props).length > 0 ? props : null,
                    parsedValue,
                    category
                );
            }
        }, true);

        // Bind form submissions
        document.addEventListener('submit', function(e) {
            var form = e.target.closest('[data-ap-form]');
            if (form) {
                var formId = form.getAttribute('data-ap-form');
                APAnalytics.trackForm(formId);
            }
        }, true);
    }

    // =========================================================================
    // Initialize
    // =========================================================================

    // Export to window
    window.APAnalytics = APAnalytics;

    // Auto-bind on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', autoBind);
    } else {
        autoBind();
    }

})(window, document);
