/**
 * Invision Community
 * (c) Invision Power Services, Inc. - https://www.invisioncommunity.com
 *
 * Invision Community service worker
 */

// ------------------------------------------------
// Install/activate SW events
// ------------------------------------------------
self.addEventListener("install", (e) => {
	console.log("Service worker installed");
	e.waitUntil(
		CACHED_ASSETS.length
			? cacheAssets().then(() => {
					return self.skipWaiting();
			  })
			: self.skipWaiting()
	);
});

self.addEventListener("activate", (e) => {
	const cacheAllowList = [CACHE_NAME];

	// Clean up any caches that don't match our current cache key
	// Ensure we don't have outdated styles/assets/etc.
	e.waitUntil(
		Promise.all([
			caches.keys().then((cacheNames) => {
				return Promise.all(
					cacheNames.map((cacheName) => {
						if (cacheAllowList.indexOf(cacheName) === -1) {
							return caches.delete(cacheName);
						}
					})
				);
			}),
			self.clients.claim(),
		])
	);
});

const returnDefaultNotification = () => {
	return self.registration.showNotification(DEFAULT_NOTIFICATION_TITLE, {
		body: DEFAULT_NOTIFICATION_BODY,
		icon: NOTIFICATION_ICON,
		data: {
			url: BASE_URL,
		},
	});
};

// ------------------------------------------------
// Push notification event handler
// ------------------------------------------------
self.addEventListener("push", (e) => {
	// A couple of basic sanity checks
	if (!e.data) {
		console.log("Invalid notification data");
		return; // Invalid notification data
	}

	const pingData = e.data.json();
	const { id } = pingData;

	// We don't send the notification data in the push, otherwise we run into issues whereby
	// a user could have logged out but will still receive notification unintentionally.
	// Instead, we'll receive an ID in the push, then we'll ping the server to get
	// the actual content (and run our usual authorization checks)

	const promiseChain = fetch(`${BASE_URL}index.php?app=core&module=system&controller=notifications&do=fetchNotification&id=${id}`, {
		method: "POST",
		credentials: "include", // Must send cookies so we can check auth
	})
		.then((response) => {
			// Fetch went wrong - but we must show a notification, so just send a generic message
			if (!response.ok) {
				throw new Error("Invalid response");
			}

			return response.json();
		})
		.then((data) => {
			// The server returned an error - but we must show a notification, so just send a generic message
			if (data.error) {
				throw new Error("Server error");
			}

			const { body, url, grouped, groupedTitle, groupedUrl, icon, image } = data;
			let { title } = data;
			let tag;

			if (data.tag) {
				tag = data.tag.substr(0, 30);
			}

			let options = {
				body,
				icon: icon ? icon : NOTIFICATION_ICON,
				image: image ? image : null,
				data: {
					url,
				},
			};

			if (!tag || !grouped) {
				// This notification has no tag or grouped lang, so just send it
				// as a one-off thing
				return self.registration.showNotification(title, options);
			} else {
				return self.registration.getNotifications({ tag }).then((notifications) => {
					// Tagged notifications require some additional data
					options = {
						...options,
						tag,
						renotify: true, // Required, otherwise browsers won't renotify for this tag
						data: {
							...options.data,
							unseenCount: 1,
						},
					};

					if (notifications.length) {
						try {
							// Get the most recent notification and see if it has a count
							// If it does, increase the unseenCount by one and update the message
							// With this approach we'll always have a reference to the previous notification's count
							// and can simply increase and fire a new notification to tell the user
							const lastWithTag = notifications[notifications.length - 1];

							if (lastWithTag.data && typeof lastWithTag.data.unseenCount !== "undefined") {
								const unseenCount = lastWithTag.data.unseenCount + 1;

								options.data.unseenCount = unseenCount;
								options.body = pluralize(grouped.replace("{count}", unseenCount), unseenCount);

								if (groupedUrl) {
									options.data.url = groupedUrl ? groupedUrl : options.data.url;
								}

								if (groupedTitle) {
									title = pluralize(groupedTitle.replace("{count}", unseenCount), unseenCount);
								}

								lastWithTag.close();
							}
						} catch (err) {
							console.log(err);
						}
					}

					return self.registration.showNotification(title, options);
				});
			}
		})
		.catch((err) => {
			// The server returned an error - but we must show a notification, so just send a generic message
			return returnDefaultNotification();
		});

	e.waitUntil(promiseChain);
});

// ------------------------------------------------
// Notification click event handler
// ------------------------------------------------
self.addEventListener("notificationclick", (e) => {
	const { data } = e.notification;

	e.waitUntil(
		self.clients.matchAll().then((clients) => {
			console.log(clients);

			// If we already have the site open, use that window
			if (clients.length > 0 && "navigate" in clients[0]) {
				if (data.url) {
					clients[0].navigate(data.url);
				} else {
					clients[0].navigate(BASE_URL);
				}

				return clients[0].focus();
			}

			// otherwise open a new window
			return self.clients.openWindow(data.url ? data.url : BASE_URL);
		})
	);
});

self.addEventListener("message", (e) => {
	switch (e.data.type) {
		case "MEMBER_ID":
			idbKeyval.set("member_id", parseInt(e.data.id)).then(() => log(`Member ID is set to ${e.data.id}`));
			break;
		case "LOGGED_IN":
			idbKeyval.set("logged_in", e.data.value).then(() => log(`Logged in status is set to ${e.data.value}`));
			break;
		case "IS_ACP":
			idbKeyval.set("is_acp", e.data.value).then(() => log(`Is Admin status is set to ${e.data.value}`));
			break;
	}
});

// ------------------------------------------------
// Fetch a resource - use to detect offline state
// ------------------------------------------------
self.addEventListener("fetch", (e) => {
	const { request } = e;

	if (!request.url.startsWith(BASE_URL) || (request.method === "GET" && request.mode !== "navigate")) {
		return;
	}

	// We intercept fetch requests in the following situations:
	// 1: GET requests where we're offline
	//    	Show a cached offline page instead
	// 2: GET requests where have a loggedIn cookie
	// 		This can happen either when we're a guest but have a logged in cookie, or when we're
	//		logged in but see a browser-cached guest page. We cachebust the url if we're within
	// 		the time when a cached page could exist,
	// 3: POST requests when we're a guest
	// 		We need to wrap POST requests in an additional call to get the correct csrf token first

	// Situation 1: offline GET requests
	if (request.mode === "navigate" && request.method === "GET" && !navigator.onLine) {
		e.respondWith(
			fetch(request).catch((err) => {
				return caches.open(CACHE_NAME).then((cache) => {
					console.log(`Browser appears to be offline: ${request.url}`);
					return cache.match(OFFLINE_URL);
				});
			})
		);
		return;
	}

	e.respondWith(
		new Promise((resolve, reject) => {
			// Fetch stored value from indexeddb
			idbKeyval
				.getMany(["logged_in", "member_id", "is_acp"])
				.then(([logged_in, member_id, is_acp]) => {
					log(`On navigation, logged_in is ${logged_in}`);
					log(`On navigation, is_acp is ${is_acp}`);
					log(`ServiceWorker cache busting is ${SW_CACHE_BUST}`);

					// Situation 2: GET requests where we have a logged_in cookie
					if (SW_CACHE_BUST && request.mode === "navigate" && request.method === "GET" && logged_in && !is_acp) {
						// Calculate whether we're outside of the window of the browser/CF having a cached version
						const outsideCacheWindow = Math.floor(Date.now() / 1000) - (HTTP_CACHE_DURATION + HTTP_CACHE_BUFFER) > logged_in;
						const curRequest = request.clone(); // Clone current request because we can't use it after reading it
						let curUrl = curRequest.url;

						// If we are, just resolve normally
						if (outsideCacheWindow || curUrl.match(/[\?\&]ct=/)) {
							resolve(fetch(request));
							return;
						}

						// If we're within the cache window, add a cache bust to the URL
						log(`Within potential cache window, adding cachebust to ${request.url}`);

						let ts = Math.round(new Date().getTime() / 1000);
						curUrl += curUrl.match(/\?/) ? "&ct=" + ts : "?ct=" + ts;
						log(`Request URL ${curUrl}`);

						const newRequest = new Request(curUrl, {
							method: curRequest.method,
							headers: curRequest.headers,
							mode: "same-origin",
							credentials: curRequest.credentials,
							redirect: "manual",
							cache: "reload",
							referrer: curRequest.referrer,
						});

						resolve(fetch(newRequest));
						return;
					}

					// Situation 3: POST requests when we're a guest
					if ( ( member_id === undefined || member_id === 0 ) && request.method === "POST" && !is_acp) {
						// Clone current request because we can't use it after reading headers later
						const curRequest = request.clone();

						log("Intercepting guest post request");

						// Grab the path so we can check in PHP if it's a CP_DIRECTORY URL
						let url = new URL(curRequest.url);
						let path = url.pathname;

						// First get the current csrf key from the server
						fetch(`${BASE_URL}index.php?app=core&module=system&controller=ajax&do=getCsrfKey&path=${path}`)
							.then((response) => response.json())
							.then((response) => {
								log(`Got new csrf key: ${response.key}`);

								// Create new header object starting with existing headers and adding csrf
								const headers = new Headers(curRequest.headers);
								headers.set("X-Csrf-Token", response.key);

								const newRequest = new Request(curRequest, {
									headers,
									credentials: curRequest.credentials,
									referrer: curRequest.referrer
								});

								// Send cloned request
								resolve(fetch(newRequest));
							});

						return;
					}

					resolve(fetch(request));
				})
				.catch((err) => {
					reject(err);
				});
		})
	);
});

// ------------------------------------------------
// Helpers
// ------------------------------------------------
const log = (message) => {
	if (DEBUG) {
		if (typeof message === "string") {
			message = `SW: ${message}`;
		}

		console.log(message);
	}
};

const cacheAssets = () => {
	return caches.open(CACHE_NAME).then((cache) => {
		return cache.addAll(CACHED_ASSETS);
	});
};

const pluralize = (word, params) => {
	let i = 0;

	if (!Array.isArray(params)) {
		params = [params];
	}

	word = word.replace(/\{(!|\d+?)?#(.*?)\}/g, (a, b, c, d) => {
		// {# [1:count][?:counts]}
		if (!b || b == "!") {
			b = i;
			i++;
		}

		let value;
		let fallback;
		let output = "";
		let replacement = params[b] + "";

		c.replace(/\[(.+?):(.+?)\]/g, (w, x, y, z) => {
			if (x == "?") {
				fallback = y.replace("#", replacement);
			} else if (x.charAt(0) == "%" && x.substring(1) == replacement.substring(0, x.substring(1).length)) {
				value = y.replace("#", replacement);
			} else if (x.charAt(0) == "*" && x.substring(1) == replacement.substr(-x.substring(1).length)) {
				value = y.replace("#", replacement);
			} else if (x == replacement) {
				value = y.replace("#", replacement);
			}
		});

		output = a.replace(/^\{/, "").replace(/\}$/, "").replace("!#", "");
		output = output.replace(b + "#", replacement).replace("#", replacement);
		output = output.replace(/\[.+\]/, value == null ? fallback : value).trim();

		return output;
	});

	return word;
};

// https://github.com/jakearchibald/idb-keyval#readme
const idbKeyval = (function (t) {
	"use strict";
	function e(t) {
		return new Promise((e, n) => {
			(t.oncomplete = t.onsuccess = () => e(t.result)), (t.onabort = t.onerror = () => n(t.error));
		});
	}
	function n(t, n) {
		const r = indexedDB.open(t);
		r.onupgradeneeded = () => r.result.createObjectStore(n);
		const o = e(r);
		return (t, e) => o.then((r) => e(r.transaction(n, t).objectStore(n)));
	}
	let r;
	function o() {
		return r || (r = n("keyval-store", "keyval")), r;
	}
	function u(t, n) {
		return t(
			"readonly",
			(t) => (
				(t.openCursor().onsuccess = function () {
					this.result && (n(this.result), this.result.continue());
				}),
				e(t.transaction)
			)
		);
	}
	return (
		(t.clear = function (t = o()) {
			return t("readwrite", (t) => (t.clear(), e(t.transaction)));
		}),
		(t.createStore = n),
		(t.del = function (t, n = o()) {
			return n("readwrite", (n) => (n.delete(t), e(n.transaction)));
		}),
		(t.entries = function (t = o()) {
			const e = [];
			return u(t, (t) => e.push([t.key, t.value])).then(() => e);
		}),
		(t.get = function (t, n = o()) {
			return n("readonly", (n) => e(n.get(t)));
		}),
		(t.getMany = function (t, n = o()) {
			return n("readonly", (n) => Promise.all(t.map((t) => e(n.get(t)))));
		}),
		(t.keys = function (t = o()) {
			const e = [];
			return u(t, (t) => e.push(t.key)).then(() => e);
		}),
		(t.promisifyRequest = e),
		(t.set = function (t, n, r = o()) {
			return r("readwrite", (r) => (r.put(n, t), e(r.transaction)));
		}),
		(t.setMany = function (t, n = o()) {
			return n("readwrite", (n) => (t.forEach((t) => n.put(t[1], t[0])), e(n.transaction)));
		}),
		(t.update = function (t, n, r = o()) {
			return r(
				"readwrite",
				(r) =>
					new Promise((o, u) => {
						r.get(t).onsuccess = function () {
							try {
								r.put(n(this.result), t), o(e(r.transaction));
							} catch (t) {
								u(t);
							}
						};
					})
			);
		}),
		(t.values = function (t = o()) {
			const e = [];
			return u(t, (t) => e.push(t.value)).then(() => e);
		}),
		t
	);
})({});
