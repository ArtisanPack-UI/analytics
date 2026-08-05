/**
 * Drives resources/js/tracker.js inside a controlled VM context and reports
 * the beacons it emits, so SPA navigation behaviour can be asserted for real
 * rather than by grepping the source.
 *
 * Usage: node tracker-spa-harness.mjs /path/to/tracker.js ['{"trackHistoryChanges":false}']
 * Emits a JSON report on stdout.
 */
import { readFileSync } from 'node:fs';
import { createContext, runInContext } from 'node:vm';

const trackerPath = process.argv[2];
const configOverrides = process.argv[3] ? JSON.parse(process.argv[3]) : {};

const beacons = [];
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
        addEventListener(type, fn) {
            (bag[type] = bag[type] || []).push(fn);
        },
        removeEventListener(type, fn) {
            bag[type] = (bag[type] || []).filter((f) => f !== fn);
        },
    };
}

const history = {
    pushState(state, title, url) { applyUrl(url); },
    replaceState(state, title, url) { applyUrl(url); },
};

const store = new Map();
const storage = {
    getItem: (k) => (store.has(k) ? store.get(k) : null),
    setItem: (k, v) => store.set(k, String(v)),
    removeItem: (k) => store.delete(k),
};

const stubElement = () => ({
    style: {},
    getContext: () => null,
    setAttribute() {},
    appendChild() {},
    removeChild() {},
    addEventListener() {},
    getBoundingClientRect: () => ({ top: 0, left: 0, width: 0, height: 0 }),
});

const document = {
    ...listenerBag(documentListeners),
    readyState: 'complete',
    title: 'Home',
    referrer: '',
    cookie: '',
    hidden: false,
    visibilityState: 'visible',
    documentElement: { scrollTop: 0, scrollHeight: 1000, clientHeight: 800 },
    body: { scrollTop: 0, scrollHeight: 1000, clientHeight: 800, offsetHeight: 1000 },
    createElement: stubElement,
    querySelector: () => null,
    querySelectorAll: () => [],
    getElementById: () => null,
};

const navigator = {
    userAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15',
    language: 'en-US',
    languages: ['en-US'],
    doNotTrack: null,
    hardwareConcurrency: 8,
    sendBeacon(url, data) {
        let parsed = null;
        const raw = typeof data === 'string' ? data : data && data.__body;
        try { parsed = JSON.parse(raw || '{}'); } catch { /* not JSON */ }
        beacons.push({ url: String(url), data: parsed });
        return true;
    },
};

const screen = { width: 2560, height: 1440, colorDepth: 24 };

const window = {
    ...listenerBag(windowListeners),
    location,
    history,
    document,
    navigator,
    screen,
    innerWidth: 1440,
    innerHeight: 900,
    localStorage: storage,
    sessionStorage: storage,
    performance: { now: () => Date.now(), timing: {}, getEntriesByType: () => [] },
    __ARTISANPACK_ANALYTICS_CONFIG__: Object.assign({
        endpoint: '/api/analytics',
        consentRequired: false,
        respectDNT: false,
        batchInterval: 20,
        debug: false,
    }, configOverrides),
};
window.window = window;
window.self = window;

const context = createContext({
    window, document, navigator, screen, location, history,
    localStorage: storage,
    sessionStorage: storage,
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

const wait = (ms) => new Promise((r) => setTimeout(r, ms));
const fire = (bag, type) => (bag[type] || []).forEach((fn) => fn({}));

const isPageView = (b) => /\/pageview$/.test(b.url) || /\/batch$/.test(b.url);
const isUpdate = (b) => /pageview\/update$/.test(b.url);

const pathsOf = (list) => list.flatMap((b) =>
    b.data && Array.isArray(b.data.items)
        ? b.data.items.map((i) => i.data && i.data.path)
        : [b.data && b.data.path]
).filter(Boolean);

await wait(80);
const report = { initialPageViews: pathsOf(beacons.filter(isPageView)), steps: [] };

async function step(label, fn) {
    beacons.length = 0;
    fn();
    await wait(80);
    report.steps.push({
        label,
        pageViewPaths: pathsOf(beacons.filter(isPageView)),
        engagementUpdates: beacons.filter(isUpdate).map((b) => ({
            path: b.data && b.data.path,
            scroll_depth: b.data && b.data.scroll_depth,
        })),
    });
}

await step('pushState -> /docs/one', () => history.pushState({}, '', '/docs/one'));
await step('replaceState same url (router state sync)', () => history.replaceState({}, '', '/docs/one'));
await step('pushState -> /docs/two', () => history.pushState({}, '', '/docs/two'));
await step('hash-only change on same path', () => history.pushState({}, '', '/docs/two#section'));
await step('popstate back -> /docs/one', () => { applyUrl('/docs/one'); fire(windowListeners, 'popstate'); });
await step('query string change', () => history.pushState({}, '', '/docs/one?page=2'));

console.log(JSON.stringify(report, null, 2));
