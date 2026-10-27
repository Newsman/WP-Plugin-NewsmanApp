/**
 * Runs the order received purchase script (Purchase::get_js()) against a fake
 * Newsman tracker that behaves like t.js where the delivery guard depends on it:
 *
 * - until t.js loads, _nzm is the stub array of the remarketing snippet, which
 *   t.js replaces with a new object and replays (callbacks survive);
 * - _nzm.identify( data, skipPageView, callback ) stores the tracking id cookie,
 *   then runs the callback;
 * - a purchase from a visitor without a tracking id is dropped;
 * - a purchase hit is listed in _nzm.stats.requests, kept in localStorage
 *   nzm_retry until an OK response, then _nzm.onPurchase() fires.
 *
 * Usage: node purchase-guard-harness.js <script.js> '<scenario JSON>'
 *
 * Scenario: { "storage": {initial localStorage}, "storageThrows": bool,
 *             "views": [ { "cookie": bool, "tracker": "load"|"blocked",
 *                          "identify": "ok"|"fail", "response": "ok"|"fail" } ] }
 * Every view is a new page load sharing localStorage and cookies.
 * Prints JSON: { views: [ {purchaseHits, droppedPurchases, identifies, pageviews,
 *                          purchaseIds, errors} ], storage: {final localStorage} }
 */

'use strict';

const fs = require('fs');
const vm = require('vm');

const TRACKER_LOAD_MS = 50;
const IDENTIFY_MS = 200;
const RESPONSE_MS = 150;
const RUN_UNTIL_MS = 70000;

const script = fs.readFileSync(process.argv[2], 'utf8');
const scenario = JSON.parse(process.argv[3]);

const store = Object.assign({}, scenario.storage || {});
let cookies = '';

function createStorage() {
	return {
		getItem(key) {
			if (scenario.storageThrows) {
				throw new Error('SecurityError');
			}
			return Object.prototype.hasOwnProperty.call(store, key) ? store[key] : null;
		},
		setItem(key, value) {
			if (scenario.storageThrows) {
				throw new Error('QuotaExceededError');
			}
			store[key] = String(value);
		},
		removeItem(key) {
			delete store[key];
		},
	};
}

// The tracker's own retry queue keeps working when the page cannot use storage.
function readRetry() {
	try {
		return JSON.parse(store.nzm_retry || '[]');
	} catch (e) {
		return [];
	}
}

function writeRetry(list) {
	store.nzm_retry = JSON.stringify(list);
}

function runView(view) {
	const result = {
		purchaseHits: 0,
		droppedPurchases: 0,
		identifies: 0,
		pageviews: 0,
		purchaseIds: [],
		errors: [],
	};
	let clock = 0;
	let timers = [];
	let timerSeq = 0;

	function setTimeoutFake(fn, ms) {
		timers.push({ at: clock + (ms || 0), seq: timerSeq++, fn });
		return timerSeq;
	}

	const win = {
		JSON,
		Date: { now: () => 1700000000000 + clock },
		Array,
		Object,
		RegExp,
		String,
		decodeURIComponent,
		encodeURIComponent,
		setTimeout: setTimeoutFake,
		localStorage: createStorage(),
		document: {},
	};
	win.window = win;
	Object.defineProperty(win.document, 'cookie', {
		get: () => cookies,
		set: (value) => {
			cookies = cookies ? cookies + '; ' + value : value;
		},
	});
	if (view.cookie) {
		win.document.cookie = '_nzm_id_95219=known-visitor';
	}

	// Stub of the remarketing snippet.
	win._nzm = [];
	['identify', 'track', 'run'].forEach((m) => {
		win._nzm[m] = function () {
			win._nzm.push([m].concat([].slice.call(arguments)));
		};
	});

	function loadTracker() {
		const queue = win._nzm;
		let identified = /(?:^|;\s*)_nzm_id_[^=]*=[^;]/.test(cookies);
		let action = null;
		const tracker = { stats: { requests: [] } };

		tracker.identify = function (data, skipPageView, callback) {
			result.identifies++;
			if (view.identify === 'fail') {
				return;
			}
			setTimeoutFake(() => {
				identified = true;
				win.document.cookie = '_nzm_id_95219=new-visitor';
				if (typeof callback === 'function') {
					callback();
				}
			}, IDENTIFY_MS);
		};
		tracker.track = function () {};
		tracker.run = function (command) {
			const args = [].slice.call(arguments, 1);
			if ('ec:setAction' === command) {
				action = { name: args[0], data: args[1] || {} };
				return;
			}
			if ('send' !== command || 'pageview' !== args[0]) {
				return;
			}
			result.pageviews++;
			const current = action;
			action = null;
			if (!current || 'purchase' !== current.name) {
				return;
			}
			if (!identified) {
				result.droppedPurchases++;
				return;
			}
			const id = String(current.data.id);
			const url = 'https://t.newsmanapp.com/jt/hit.gif?p%5Bpa%5D=purchase&p%5Bti%5D=' + encodeURIComponent(id);
			result.purchaseHits++;
			result.purchaseIds.push(id);
			tracker.stats.requests.push(url);
			const retry = readRetry();
			retry.push({ url, tries: 0 });
			writeRetry(retry);
			setTimeoutFake(() => {
				if ('fail' === view.response) {
					return;
				}
				writeRetry(readRetry().filter((entry) => entry.url !== url));
				if (typeof win._nzm.onPurchase === 'function') {
					win._nzm.onPurchase();
				}
			}, RESPONSE_MS);
		};

		win._nzm = tracker;
		queue.forEach((entry) => {
			tracker[entry[0]].apply(tracker, entry.slice(1));
		});
	}

	const context = vm.createContext(win);
	try {
		vm.runInContext(script, context);
	} catch (e) {
		result.errors.push(String(e && e.message ? e.message : e));
	}
	if ('blocked' !== view.tracker) {
		setTimeoutFake(loadTracker, TRACKER_LOAD_MS);
	}

	while (timers.length) {
		timers.sort((a, b) => a.at - b.at || a.seq - b.seq);
		const next = timers.shift();
		if (next.at > RUN_UNTIL_MS) {
			break;
		}
		clock = next.at;
		try {
			next.fn();
		} catch (e) {
			result.errors.push(String(e && e.message ? e.message : e));
		}
	}

	return result;
}

const views = (scenario.views || [{}]).map(runView);
process.stdout.write(JSON.stringify({ views, storage: store }));
