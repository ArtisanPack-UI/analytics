/**
 * ArtisanPack Analytics Tracker
 *
 * A privacy-first, lightweight JavaScript analytics tracker.
 *
 * @version 1.0.0
 * @license MIT
 */
(function(window, document, undefined) {
    'use strict';

    // Prevent double initialization
    if (window.ArtisanPackAnalytics && window.ArtisanPackAnalytics._initialized) {
        return;
    }

    // =========================================================================
    // Configuration
    // =========================================================================

    var defaultConfig = {
        endpoint: '/api/analytics',
        sessionTimeout: 30 * 60 * 1000,
        heartbeatInterval: 15 * 1000,
        respectDNT: true,
        consentRequired: false,
        consentCategory: 'analytics',
        trackPageViews: true,
        trackPerformance: true,
        trackScrollDepth: true,
        trackEngagement: true,
        trackHashChanges: false,
        trackOutboundLinks: true,
        trackFileDownloads: true,
        downloadExtensions: ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip', 'rar', 'gz', 'tar', 'exe', 'dmg'],
        cookieLifetime: 365,
        sessionCookieName: '_ap_sid',
        visitorCookieName: '_ap_vid',
        batchSize: 10,
        batchInterval: 5000,
        debug: false
    };

    // Merge with server-provided config
    var config = Object.assign({}, defaultConfig, window.__ARTISANPACK_ANALYTICS_CONFIG__ || {});

    // =========================================================================
    // Utility Functions
    // =========================================================================

    function log() {
        if (config.debug && console && console.log) {
            console.log.apply(console, ['[ArtisanPack Analytics]'].concat(Array.prototype.slice.call(arguments)));
        }
    }

    function uuid() {
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
            var r = Math.random() * 16 | 0;
            var v = c === 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    }

    function now() {
        return Date.now ? Date.now() : new Date().getTime();
    }

    function extend(target) {
        for (var i = 1; i < arguments.length; i++) {
            var source = arguments[i];
            if (source) {
                for (var key in source) {
                    if (Object.prototype.hasOwnProperty.call(source, key)) {
                        target[key] = source[key];
                    }
                }
            }
        }
        return target;
    }

    // =========================================================================
    // Storage Module
    // =========================================================================

    var Storage = {
        setCookie: function(name, value, days) {
            var expires = '';
            if (days) {
                var date = new Date();
                date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
                expires = '; expires=' + date.toUTCString();
            }
            var secure = location.protocol === 'https:' ? '; Secure' : '';
            document.cookie = name + '=' + encodeURIComponent(value || '') + expires + '; path=/; SameSite=Lax' + secure;
        },

        getCookie: function(name) {
            var nameEQ = name + '=';
            var ca = document.cookie.split(';');
            for (var i = 0; i < ca.length; i++) {
                var c = ca[i];
                while (c.charAt(0) === ' ') {
                    c = c.substring(1, c.length);
                }
                if (c.indexOf(nameEQ) === 0) {
                    return decodeURIComponent(c.substring(nameEQ.length, c.length));
                }
            }
            return null;
        },

        deleteCookie: function(name) {
            document.cookie = name + '=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/';
        },

        setLocal: function(key, value) {
            try {
                localStorage.setItem('_ap_' + key, JSON.stringify(value));
            } catch (e) {
                log('localStorage not available');
            }
        },

        getLocal: function(key) {
            try {
                var item = localStorage.getItem('_ap_' + key);
                return item ? JSON.parse(item) : null;
            } catch (e) {
                return null;
            }
        },

        removeLocal: function(key) {
            try {
                localStorage.removeItem('_ap_' + key);
            } catch (e) {}
        }
    };

    // =========================================================================
    // Consent Module
    // =========================================================================

    var Consent = {
        _granted: null,

        init: function() {
            if (!config.consentRequired) {
                this._granted = true;
                return;
            }
            this._granted = Storage.getLocal('consent_' + config.consentCategory) === true;
        },

        check: function() {
            // Check DNT header
            if (config.respectDNT) {
                if (navigator.doNotTrack === '1' ||
                    navigator.doNotTrack === 'yes' ||
                    navigator.globalPrivacyControl === true ||
                    window.doNotTrack === '1') {
                    log('DNT enabled, tracking disabled');
                    return false;
                }
            }

            // Check consent
            if (config.consentRequired && !this._granted) {
                log('Consent not granted');
                return false;
            }

            return true;
        },

        grant: function(categories) {
            var cats = categories || [config.consentCategory];
            if (typeof cats === 'string') {
                cats = [cats];
            }
            for (var i = 0; i < cats.length; i++) {
                Storage.setLocal('consent_' + cats[i], true);
            }
            if (cats.indexOf(config.consentCategory) !== -1) {
                this._granted = true;
            }
            log('Consent granted:', cats);

            // Send consent update
            Transport.send('consent', {
                categories: cats,
                granted: true
            });
        },

        revoke: function(categories) {
            var cats = categories || [config.consentCategory];
            if (typeof cats === 'string') {
                cats = [cats];
            }
            for (var i = 0; i < cats.length; i++) {
                Storage.removeLocal('consent_' + cats[i]);
            }
            if (cats.indexOf(config.consentCategory) !== -1) {
                this._granted = false;
            }
            log('Consent revoked:', cats);

            // Send consent update
            Transport.send('consent', {
                categories: cats,
                granted: false
            });
        },

        hasConsent: function(category) {
            var cat = category || config.consentCategory;
            return Storage.getLocal('consent_' + cat) === true;
        }
    };

    // =========================================================================
    // Visitor Module
    // =========================================================================

    var Visitor = {
        _id: null,

        init: function() {
            this._id = Storage.getCookie(config.visitorCookieName);
            if (!this._id) {
                this._id = uuid();
                Storage.setCookie(config.visitorCookieName, this._id, config.cookieLifetime);
                log('New visitor:', this._id);
            } else {
                log('Returning visitor:', this._id);
            }
        },

        getId: function() {
            return this._id;
        },

        getFingerprint: function() {
            var data = [
                navigator.userAgent,
                screen.width + 'x' + screen.height,
                Intl.DateTimeFormat().resolvedOptions().timeZone,
                navigator.language
            ].join('|');

            // Simple hash function
            var hash = 0;
            for (var i = 0; i < data.length; i++) {
                var char = data.charCodeAt(i);
                hash = ((hash << 5) - hash) + char;
                hash = hash & hash;
            }
            return Math.abs(hash).toString(16);
        }
    };

    // =========================================================================
    // Session Module
    // =========================================================================

    var Session = {
        _id: null,
        _startedAt: null,
        _lastActivity: null,
        _heartbeatTimer: null,

        init: function() {
            var existingSession = Storage.getCookie(config.sessionCookieName);
            var sessionData = Storage.getLocal('session');

            if (existingSession && sessionData && !this._isExpired(sessionData.lastActivity)) {
                this._id = existingSession;
                this._startedAt = sessionData.startedAt;
                this._lastActivity = now();
                log('Resuming session:', this._id);
            } else {
                this._id = uuid();
                this._startedAt = now();
                this._lastActivity = now();
                log('New session:', this._id);

                // Start new session on server
                Transport.send('session/start', {
                    session_id: this._id,
                    entry_page: window.location.pathname,
                    referrer: document.referrer,
                    utm_source: this._getUrlParam('utm_source'),
                    utm_medium: this._getUrlParam('utm_medium'),
                    utm_campaign: this._getUrlParam('utm_campaign'),
                    utm_term: this._getUrlParam('utm_term'),
                    utm_content: this._getUrlParam('utm_content')
                });
            }

            this._save();
            this._startHeartbeat();
        },

        getId: function() {
            return this._id;
        },

        touch: function() {
            this._lastActivity = now();
            this._save();
        },

        end: function() {
            if (this._heartbeatTimer) {
                clearInterval(this._heartbeatTimer);
            }

            Transport.send('session/end', {
                session_id: this._id,
                exit_page: window.location.pathname
            }, true);

            Storage.deleteCookie(config.sessionCookieName);
            Storage.removeLocal('session');
            log('Session ended:', this._id);
        },

        _save: function() {
            Storage.setCookie(config.sessionCookieName, this._id);
            Storage.setLocal('session', {
                id: this._id,
                startedAt: this._startedAt,
                lastActivity: this._lastActivity
            });
        },

        _isExpired: function(lastActivity) {
            return (now() - lastActivity) > config.sessionTimeout;
        },

        _startHeartbeat: function() {
            var self = this;
            this._heartbeatTimer = setInterval(function() {
                if (Consent.check()) {
                    Transport.send('session/extend', {
                        session_id: self._id
                    });
                    self.touch();
                }
            }, config.heartbeatInterval);
        },

        _getUrlParam: function(name) {
            var results = new RegExp('[?&]' + name + '=([^&#]*)').exec(window.location.href);
            return results ? decodeURIComponent(results[1]) : null;
        }
    };

    // =========================================================================
    // Performance Module
    // =========================================================================

    var Performance = {
        getMetrics: function() {
            if (!window.performance || !window.performance.timing) {
                return {};
            }

            var timing = window.performance.timing;
            var navStart = timing.navigationStart;

            var metrics = {};

            if (timing.loadEventEnd > 0) {
                metrics.load_time = timing.loadEventEnd - navStart;
            }

            if (timing.domContentLoadedEventEnd > 0) {
                metrics.dom_ready_time = timing.domContentLoadedEventEnd - navStart;
            }

            // First Contentful Paint
            if (window.performance.getEntriesByType) {
                var paintEntries = window.performance.getEntriesByType('paint');
                for (var i = 0; i < paintEntries.length; i++) {
                    if (paintEntries[i].name === 'first-contentful-paint') {
                        metrics.first_contentful_paint = Math.round(paintEntries[i].startTime);
                        break;
                    }
                }
            }

            return metrics;
        }
    };

    // =========================================================================
    // Engagement Module
    // =========================================================================

    var Engagement = {
        _scrollDepth: 0,
        _scrollMilestones: [25, 50, 75, 100],
        _reachedMilestones: [],
        _pageLoadTime: null,
        _engagedTime: 0,
        _lastActiveTime: null,
        _isVisible: true,
        _engagementTimer: null,

        init: function() {
            var self = this;
            this._pageLoadTime = now();
            this._lastActiveTime = now();
            this._reachedMilestones = [];

            // Track scroll depth
            if (config.trackScrollDepth) {
                this._trackScroll();
            }

            // Track engaged time
            if (config.trackEngagement) {
                this._trackEngagement();
            }

            // Track visibility changes
            document.addEventListener('visibilitychange', function() {
                self._isVisible = !document.hidden;
                if (!self._isVisible) {
                    self._updateEngagedTime();
                } else {
                    self._lastActiveTime = now();
                }
            });

            // Send engagement data on page leave
            window.addEventListener('beforeunload', function() {
                self._sendEngagementData();
            });

            // Also handle pagehide for mobile
            window.addEventListener('pagehide', function() {
                self._sendEngagementData();
            });
        },

        _trackScroll: function() {
            var self = this;
            var ticking = false;

            window.addEventListener('scroll', function() {
                if (!ticking) {
                    window.requestAnimationFrame(function() {
                        self._calculateScrollDepth();
                        ticking = false;
                    });
                    ticking = true;
                }
            }, { passive: true });
        },

        _calculateScrollDepth: function() {
            var scrollTop = window.pageYOffset || document.documentElement.scrollTop;
            var docHeight = Math.max(
                document.body.scrollHeight,
                document.documentElement.scrollHeight,
                document.body.offsetHeight,
                document.documentElement.offsetHeight,
                document.body.clientHeight,
                document.documentElement.clientHeight
            );
            var winHeight = window.innerHeight;
            var scrollPercent = Math.round((scrollTop / (docHeight - winHeight)) * 100);

            if (scrollPercent > this._scrollDepth) {
                this._scrollDepth = Math.min(scrollPercent, 100);
            }

            // Track milestones
            for (var i = 0; i < this._scrollMilestones.length; i++) {
                var milestone = this._scrollMilestones[i];
                if (this._scrollDepth >= milestone && this._reachedMilestones.indexOf(milestone) === -1) {
                    this._reachedMilestones.push(milestone);
                    log('Scroll milestone reached:', milestone + '%');

                    // Send scroll milestone event
                    Analytics.event('scroll_depth', {
                        depth: milestone,
                        path: window.location.pathname
                    });
                }
            }
        },

        _trackEngagement: function() {
            var self = this;
            var inactivityThreshold = 10000; // 10 seconds

            // Track user activity
            var activityEvents = ['mousedown', 'mousemove', 'keydown', 'touchstart', 'scroll'];
            var activityHandler = function() {
                if (self._isVisible) {
                    self._updateEngagedTime();
                    self._lastActiveTime = now();
                }
            };

            for (var i = 0; i < activityEvents.length; i++) {
                document.addEventListener(activityEvents[i], activityHandler, { passive: true });
            }
        },

        _updateEngagedTime: function() {
            var currentTime = now();
            var timeSinceLastActive = currentTime - this._lastActiveTime;

            // Only count time as engaged if less than inactivity threshold
            if (timeSinceLastActive < 10000) {
                this._engagedTime += timeSinceLastActive;
            }
        },

        getTimeOnPage: function() {
            return Math.round((now() - this._pageLoadTime) / 1000);
        },

        getEngagedTime: function() {
            this._updateEngagedTime();
            return Math.round(this._engagedTime / 1000);
        },

        getScrollDepth: function() {
            return this._scrollDepth;
        },

        _sendEngagementData: function() {
            if (!Consent.check()) return;

            var data = {
                session_id: Session.getId(),
                path: window.location.pathname,
                time_on_page: this.getTimeOnPage(),
                engaged_time: this.getEngagedTime(),
                scroll_depth: this.getScrollDepth()
            };

            // Use sendBeacon for reliable delivery on page leave
            Transport.send('pageview/update', data, true);
        }
    };

    // =========================================================================
    // Outbound Link Tracking
    // =========================================================================

    var OutboundLinks = {
        init: function() {
            if (!config.trackOutboundLinks) return;

            var self = this;
            document.addEventListener('click', function(e) {
                var link = self._findLink(e.target);
                if (link && self._isOutbound(link)) {
                    self._trackOutbound(link);
                }
            }, true);
        },

        _findLink: function(element) {
            while (element && element !== document) {
                if (element.tagName === 'A' && element.href) {
                    return element;
                }
                element = element.parentNode;
            }
            return null;
        },

        _isOutbound: function(link) {
            try {
                var linkUrl = new URL(link.href, window.location.origin);
                return linkUrl.hostname !== window.location.hostname;
            } catch (e) {
                return false;
            }
        },

        _trackOutbound: function(link) {
            log('Outbound link clicked:', link.href);

            Analytics.event('outbound_link', {
                url: link.href,
                text: link.innerText || link.textContent,
                path: window.location.pathname
            });
        }
    };

    // =========================================================================
    // File Download Tracking
    // =========================================================================

    var Downloads = {
        init: function() {
            if (!config.trackFileDownloads) return;

            var self = this;
            document.addEventListener('click', function(e) {
                var link = self._findLink(e.target);
                if (link && self._isDownload(link)) {
                    self._trackDownload(link);
                }
            }, true);
        },

        _findLink: function(element) {
            while (element && element !== document) {
                if (element.tagName === 'A' && element.href) {
                    return element;
                }
                element = element.parentNode;
            }
            return null;
        },

        _isDownload: function(link) {
            if (link.hasAttribute('download')) return true;

            var href = link.href;
            var extension = href.split('.').pop().toLowerCase().split('?')[0];

            return config.downloadExtensions.indexOf(extension) !== -1;
        },

        _trackDownload: function(link) {
            var href = link.href;
            var filename = href.split('/').pop().split('?')[0];
            var extension = filename.split('.').pop().toLowerCase();

            log('File download:', filename);

            Analytics.event('file_download', {
                url: href,
                filename: filename,
                extension: extension,
                path: window.location.pathname
            });
        }
    };

    // =========================================================================
    // Transport Module
    // =========================================================================

    var Transport = {
        _queue: [],
        _batchTimer: null,

        send: function(endpoint, data, immediate) {
            if (!Consent.check()) {
                log('Tracking blocked by consent/DNT');
                return;
            }

            var payload = extend({
                visitor_id: Visitor.getId(),
                session_id: Session.getId(),
                fingerprint: Visitor.getFingerprint()
            }, data);

            if (immediate) {
                this._sendNow(endpoint, payload);
            } else {
                this._addToQueue(endpoint, payload);
            }
        },

        _sendNow: function(endpoint, data) {
            var url = config.endpoint + '/' + endpoint;
            var payload = JSON.stringify(data);

            log('Sending:', endpoint, data);

            // Prefer sendBeacon for reliability
            if (navigator.sendBeacon) {
                var blob = new Blob([payload], { type: 'application/json' });
                navigator.sendBeacon(url, blob);
            } else {
                // Fallback to XHR
                var xhr = new XMLHttpRequest();
                xhr.open('POST', url, true);
                xhr.setRequestHeader('Content-Type', 'application/json');
                xhr.send(payload);
            }
        },

        _addToQueue: function(endpoint, data) {
            this._queue.push({
                type: endpoint === 'pageview' ? 'pageview' : 'event',
                data: data,
                timestamp: new Date().toISOString()
            });

            // Start batch timer if not already running
            if (!this._batchTimer) {
                this._startBatchTimer();
            }

            // Flush immediately if batch is full
            if (this._queue.length >= config.batchSize) {
                this._flush();
            }
        },

        _startBatchTimer: function() {
            var self = this;
            this._batchTimer = setTimeout(function() {
                self._flush();
            }, config.batchInterval);
        },

        _flush: function() {
            if (this._batchTimer) {
                clearTimeout(this._batchTimer);
                this._batchTimer = null;
            }

            if (this._queue.length === 0) return;

            var items = this._queue.splice(0, config.batchSize);

            // If only one item, send directly
            if (items.length === 1) {
                var item = items[0];
                this._sendNow(item.type, item.data);
            } else {
                // Send as batch
                this._sendNow('batch', { items: items });
            }

            // Continue processing if more items in queue
            if (this._queue.length > 0) {
                this._startBatchTimer();
            }
        }
    };

    // =========================================================================
    // Main Analytics Object
    // =========================================================================

    var Analytics = {
        version: '1.0.0',
        _initialized: false,

        init: function(userConfig) {
            if (this._initialized) {
                log('Already initialized');
                return;
            }

            // Merge user config
            if (userConfig) {
                config = extend(config, userConfig);
            }

            log('Initializing with config:', config);

            // Initialize modules
            Consent.init();

            if (!Consent.check()) {
                log('Tracking disabled');
                this._initialized = true;
                return;
            }

            Visitor.init();
            Session.init();
            Engagement.init();
            OutboundLinks.init();
            Downloads.init();

            // Auto-track page view
            if (config.trackPageViews) {
                this._trackInitialPageView();
            }

            // Track hash changes for SPAs
            if (config.trackHashChanges) {
                var self = this;
                window.addEventListener('hashchange', function() {
                    self.pageView();
                });
            }

            this._initialized = true;
            log('Initialized successfully');
        },

        _trackInitialPageView: function() {
            // Wait for page to fully load to get performance metrics
            var self = this;

            if (document.readyState === 'complete') {
                setTimeout(function() {
                    self.pageView();
                }, 0);
            } else {
                window.addEventListener('load', function() {
                    setTimeout(function() {
                        self.pageView();
                    }, 0);
                });
            }
        },

        pageView: function(customData) {
            if (!Consent.check()) return;

            var data = extend({
                path: window.location.pathname,
                title: document.title,
                hash: window.location.hash,
                query_string: window.location.search,
                referrer: document.referrer,
                referrer_path: this._getReferrerPath(),
                screen_width: window.screen.width,
                screen_height: window.screen.height,
                viewport_width: window.innerWidth,
                viewport_height: window.innerHeight,
                language: navigator.language,
                timezone: Intl.DateTimeFormat().resolvedOptions().timeZone
            }, Performance.getMetrics(), customData || {});

            log('Page view:', data);
            Transport.send('pageview', data);
            Session.touch();
        },

        event: function(name, properties, options) {
            if (!Consent.check()) return;
            if (!name) {
                log('Event name is required');
                return;
            }

            var data = extend({
                name: name,
                properties: properties || {},
                path: window.location.pathname
            }, options || {});

            log('Event:', name, data);
            Transport.send('event', data);
            Session.touch();
        },

        // Convenience methods
        track: {
            click: function(element, properties) {
                Analytics.event('click', extend({
                    element: element ? element.tagName : null,
                    text: element ? (element.innerText || '').substring(0, 100) : null
                }, properties || {}));
            },

            formSubmit: function(formName, properties) {
                Analytics.event('form_submit', extend({
                    form_name: formName
                }, properties || {}));
            },

            purchase: function(orderId, total, properties) {
                Analytics.event('purchase', extend({
                    order_id: orderId,
                    total: total
                }, properties || {}), { value: total });
            },

            addToCart: function(productId, properties) {
                Analytics.event('add_to_cart', extend({
                    product_id: productId
                }, properties || {}));
            },

            outboundLink: function(url) {
                Analytics.event('outbound_link', { url: url });
            },

            fileDownload: function(filename, extension) {
                Analytics.event('file_download', {
                    filename: filename,
                    extension: extension
                });
            }
        },

        consent: {
            grant: function(categories) {
                Consent.grant(categories);
            },
            revoke: function(categories) {
                Consent.revoke(categories);
            },
            hasConsent: function(category) {
                return Consent.hasConsent(category);
            },
            check: function() {
                return Consent.check();
            }
        },

        session: {
            getId: function() {
                return Session.getId();
            },
            end: function() {
                Session.end();
            }
        },

        visitor: {
            getId: function() {
                return Visitor.getId();
            }
        },

        debug: function(enabled) {
            config.debug = enabled !== false;
            log('Debug mode:', config.debug ? 'enabled' : 'disabled');
        },

        _getReferrerPath: function() {
            if (!document.referrer) return null;
            try {
                var url = new URL(document.referrer);
                if (url.hostname === window.location.hostname) {
                    return url.pathname;
                }
            } catch (e) {}
            return null;
        }
    };

    // Export to window
    window.ArtisanPackAnalytics = Analytics;

    // Auto-initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            Analytics.init();
        });
    } else {
        Analytics.init();
    }

})(window, document);
