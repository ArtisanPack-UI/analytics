/**
 * Drives resources/js/tracker.js in anonymous (pre-consent) mode inside a Node
 * VM context and reports what it emitted and what it wrote.
 *
 * The privacy claim this feature makes is negative — no identifiers, no
 * cookies, no storage — and a negative claim is exactly what source-text
 * assertions cannot check. The stub DOM here records every cookie assignment
 * and every localStorage write so the tests can assert on them.
 *
 * Usage: node tracker-anonymous-harness.mjs /path/to/tracker.js ['{"anonymousMode":true}']
 * Emits a JSON report on stdout.
 */
import { readFileSync } from 'node:fs';
import { createContext, runInContext } from 'node:vm';

const trackerPath = process.argv[2];
const configOverrides = process.argv[3] ? JSON.parse(process.argv[3]) : {};

const beacons = [];
const storageWrites = [];
const cookieWrites = [];
const windowListeners = {};
const documentListeners = {};

const location = {
    pathname: '/',
    search: '',
    hash: '',
    hostname: 'example.test',
    href: 'https://example.test/',
    protocol: 'https:',
};

function applyUrl(url) {
    if (typeof url !== 'string') return;
    const [pathAndQuery, hash] = url.split('#');
    const [pathname, search] = pathAndQuery.split('?');
    location.pathname = pathname || location.pathname;
    location.search = search ? '?' + search : '';
    location.hash = hash ? '#' + hash : '';
    location.href = 'https://example.test' + location.pathname + location.search + location.hash;
}

function listenerBag(bag) {
    return {
        addEventListener(type, fn) { (bag[type] = bag[type] || []).push(fn); },
        removeEventListener(type, fn) { bag[type] = (bag[type] || []).filter((f) => f !== fn); },
    };
}

const history = {
    pushState(state, title, url) { applyUrl(url); },
    replaceState(state, title, url) { applyUrl(url); },
};

const store = new Map();
const storage = {
    getItem: (k) => (store.has(k) ? store.get(k) : null),
    setItem: (k, v) => { storageWrites.push(String(k)); store.set(k, String(v)); },
    removeItem: (k) => store.delete(k),
};

const stubElement = () => ({
    style: {}, getContext: () => null, setAttribute() {}, appendChild() {},
    removeChild() {}, addEventListener() {},
    getBoundingClientRect: () => ({ top: 0, left: 0, width: 0, height: 0 }),
});

const document = {
    ...listenerBag(documentListeners),
    readyState: 'complete',
    title: 'Home',
    referrer: '',
    hidden: false,
    visibilityState: 'visible',
    documentElement: { scrollTop: 0, scrollHeight: 1000, clientHeight: 800 },
    body: { scrollTop: 0, scrollHeight: 1000, clientHeight: 800, offsetHeight: 1000 },
    createElement: stubElement,
    querySelector: () => null,
    querySelectorAll: () => [],
    getElementById: () => null,
    get cookie() { return ''; },
    set cookie(value) { cookieWrites.push(String(value)); },
};

const navigator = {
    userAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15',
    language: 'en-US',
    languages: ['en-US'],
    doNotTrack: configOverrides.__doNotTrack ?? null,
    globalPrivacyControl: configOverrides.__globalPrivacyControl ?? undefined,
    hardwareConcurrency: 8,
    sendBeacon(url, data) {
        let parsed = null;
        const raw = typeof data === 'string' ? data : data && data.__body;
        try { parsed = JSON.parse(raw || '{}'); } catch { /* not JSON */ }
        beacons.push({ url: String(url), data: parsed });
        return true;
    },
};

delete configOverrides.__doNotTrack;
delete configOverrides.__globalPrivacyControl;

const screen = { width: 2560, height: 1440, colorDepth: 24 };

const window = {
    ...listenerBag(windowListeners),
    location, history, document, navigator, screen,
    innerWidth: 1440, innerHeight: 900,
    localStorage: storage,
    sessionStorage: storage,
    performance: { now: () => Date.now(), timing: {}, getEntriesByType: () => [] },
    __ARTISANPACK_ANALYTICS_CONFIG__: Object.assign({
        endpoint: '/api/analytics',
        consentRequired: true,
        respectDNT: true,
        batchInterval: 20,
        debug: false,
    }, configOverrides),
};
window.window = window;
window.self = window;

const context = createContext({
    window, document, navigator, screen, location, history,
    localStorage: storage, sessionStorage: storage,
    performance: window.performance,
    setTimeout, clearTimeout, setInterval: () => 0, clearInterval: () => {},
    console, JSON, Math, Date, Intl, URL, Object, Array, String, Number, Error,
    encodeURIComponent, decodeURIComponent, isNaN, parseInt, parseFloat,
    Blob: class { constructor(parts) { this.__body = parts.join(''); } },
    XMLHttpRequest: class {
        open(method, url) { this.__url = url; }
        setRequestHeader() {}
        send(body) {
            let parsed = null;
            try { parsed = JSON.parse(body || '{}'); } catch { /* not JSON */ }
            beacons.push({ url: String(this.__url), data: parsed });
        }
    },
});

runInContext(readFileSync(trackerPath, 'utf8'), context);

const tracker = window.ArtisanPackAnalytics;
const wait = (ms) => new Promise((r) => setTimeout(r, ms));

await wait(80);

const snapshot = () => ({
    beacons: beacons.map((b) => ({ url: b.url, keys: b.data ? Object.keys(b.data).sort() : [], data: b.data })),
    storageWrites: [...storageWrites],
    cookieWrites: [...cookieWrites],
});

const report = { afterLoad: snapshot(), steps: [] };

async function step(label, fn) {
    beacons.length = 0;
    storageWrites.length = 0;
    cookieWrites.length = 0;
    await fn();
    await wait(80);
    report.steps.push({ label, ...snapshot() });
}

await step('spa navigation while anonymous', () => history.pushState({}, '', '/docs/one'));
await step('consent granted mid-visit', () => { tracker.consent.grant(['analytics']); });
await step('spa navigation after consent', () => history.pushState({}, '', '/docs/two'));

console.log(JSON.stringify(report, null, 2));
