# Frontend Security Audit

Scope: the supplied `AH77_SOURCE/` collection and the AURUM frontend built in this
repository. The reference code was **inspected statically only** — no third-party
bundle, page or service was executed, and no request was made to any reference host.

## 1. Method

* `tools/inventory_source.php` enumerated all 164 files with size, type, dimensions and
  MD5 prefixes (see [AH77_SOURCE_INVENTORY.md](AH77_SOURCE_INVENTORY.md)).
* Files were opened as text (HTML, CSS, JS, JSON, manifests) or identified by magic
  bytes. Minified bundles were searched for endpoints, storage keys, SDK identifiers
  and credential-like strings.
* Nothing was run: the Vue app, webpack runtime, `vendor.encrypt.v2.dll.js`, the
  Telegram helper and the Sentry bundle were never loaded.
* No credentials, private API keys, token values or customer records were found in the
  export. The only environment-like values are public SDK/app identifiers.

## 2. Findings in the reference collection

| # | Finding | Where | Risk | AURUM response |
| ---: | --- | --- | --- | --- |
| R1 | Session material kept in `sessionStorage` under `MC_SESSION_INFO`; other keys (`PSW`, `trialName`, `WEBCLIP`) in web storage | mobile/member-centre bundles | Any XSS in the page can read the token; `PSW` suggests password material may be stored | Not reproduced. AURUM keeps its bearer token in `sessionStorage` or `localStorage` only when "keep me signed in" is chosen, never a password |
| R2 | Obfuscated 862 KB bundle `vendor.encrypt.v2.dll.js` (CryptoJS plus a minified payload) | `www.ah33c5.org/m/` | Unreviewable executable code; could encrypt/obfuscate exfiltration | Not shipped or reused |
| R3 | Marketing/analytics SDKs: Google Tag Manager, Facebook pixel, Telegram auth helper, Sentry | app bundles, `www.ah33.bet/common/v.1.0.1/tg-auth-web.html` | Third-party scripts receive user/device data; Telegram helper is a separate origin | No third-party scripts, pixels or remote auth helpers. CSP allows `script-src 'self'` |
| R4 | Five CDN libraries copied without Subresource Integrity (Swiper, html2canvas, NoSleep, Compressor.js, JSZip) | `cdnjs.cloudflare.com`, `cdn.jsdelivr.net` | Supply-chain and cache-poisoning risk if loaded live | Not used. AURUM has no dependencies in the browser beyond its own ES modules |
| R5 | Game thumbnails and promotional images loaded from `images.84815068.com` | image CDN | Hotlinking leaks referrer/IP; remote content can change | All art is local under `/assets`; `img-src 'self' data:` |
| R6 | Deposit, bonus and prize marketing; PAGCOR/BMM marks; 18+ gambling content | banners, footer | Regulatory and consumer-protection exposure; implies real-money play | No deposit/withdrawal/payment code; every screen states virtual credits only; no regulator marks |
| R7 | Web-push service worker manifest (`/wps/`) | `www.ah33c5.org/wps/` | Push subscription and notification surface | Not implemented |
| R8 | Snapshotted DOM with inline webpack runtime | `website-source-export/index.html` (1.4 MB) | Inline script hinders CSP; stale snapshot can mislead | The shell loads only same-origin modules; no inline executable script |

No finding in the reference collection was exploitable against this repository because
none of its code, endpoints or assets are used.

## 3. Controls in the AURUM frontend

### Transport and headers

`public/index.php` serves the shell with:

```
Content-Security-Policy: default-src 'self'; img-src 'self' data:;
  style-src 'self'; script-src 'self'; connect-src 'self';
  base-uri 'none'; form-action 'none'
X-Content-Type-Options: nosniff
Referrer-Policy: no-referrer
X-Frame-Options: DENY
```

The API client sends `credentials: 'same-origin'` and uses relative URLs only. There is
no `eval`, no `new Function`, no inline event-handler attribute and no remote asset.

### Rendering

* All engine/user strings are inserted with `textContent` (`el()` in `lib/dom.js`).
  `innerHTML` is used only for icon path data authored in `lib/icons.js` and for the
  three hard-coded banner headlines that contain an `<em>`.
* Values such as provider names, game names, symbols and error messages never enter
  markup.
* Links come from the router's own table, not from API data, so a catalogue cannot
  inject a `javascript:` URL.

### Authentication

* Passwords are sent only to `POST /api/v1/auth/login` and `POST /api/v1/auth/register`
  over the same origin; they are never written to storage.
* The bearer token is kept in `sessionStorage` by default, or `localStorage` when the
  player ticks "Keep me signed in". Logging out revokes the token server-side and clears
  both stores (`lib/store.js`).
* The UI hides authenticated navigation until a session exists, but authorisation is
  enforced by the API (`auth`, `admin`, session-ownership middleware), not by hiding
  controls.

### Registration endpoint (new)

`POST /api/v1/auth/register` is additive and reuses the existing repositories and
`AuthService` hashing. It validates:

* phone number, canonicalised to a single E.164-style value and backed by a unique
  index (duplicate → validation error, including different spacing/prefix formats);
* display name 2–64 characters, age 18–120;
* password length 10–128;
* a six-digit numeric `otp`. This is a **local demonstration**: the browser displays a
  generated code, any six digits are accepted, no SMS/provider is called, and the UI
  does not claim the number is independently verified.

User, credentials and wallet rows are written in one transaction. The only value
created is virtual credits (`REGISTRATION_STARTING_BALANCE_MINOR`, default 30000 =
exactly 300 credits), created only with the account, so retries cannot grant it twice.
An operator can disable the route with `ALLOW_SELF_REGISTRATION=false`.

Residual risk: self-service registration has no real phone/email verification or
CAPTCHA, so a public deployment can accumulate accounts. This is accepted for a private
virtual-credit app with friends and is bounded by the API rate limiter.

### Administrator authentication

`/admin.php` uses a separate PHP session cookie, not the player's bearer token. Login
verifies the password hash, requires `role = admin` and `status = active`, regenerates
the session id, applies an idle timeout and a login-attempt lockout, and re-reads the
role/status from the database on every request. Every mutation requires a CSRF token;
logout destroys the session and clears the cookie. Player credentials are rejected by
the administrator login.

### Client-side state

* Balance changes are rendered only from server responses (`round.balance_after`) or a
  fresh `/auth/me`; the UI never computes a payout locally.
* Idempotency keys are generated per action with `crypto.randomUUID`, so a retried
  request cannot double-settle a round.
* `api.js` retries one GET on a non-JSON 403 only (preview-gateway warm-up). It never
  retries POSTs and never retries a real JSON 403.

## 4. Verified

| Check | Result |
| --- | --- |
| Shell response headers | CSP, nosniff, no-referrer, `X-Frame-Options: DENY` present |
| Console errors on lobby, login, slot, blackjack, roulette, coinflip, search | none (mobile 390×844 and desktop 1440×900 runs) |
| Password in web storage | not observed after login |
| Third-party network requests during verification | none |
| Unknown scripts executed during the audit | none |

## 5. Limitations

* The reference bundles are minified; a full deobfuscation was out of scope and would
  not change the decision not to reuse them.
* No penetration test or fuzzing was performed. The engine's own API tests and
  [the backend security audit](../backend/SECURITY_AUDIT.md) cover server-side concerns.
* CSP `style-src 'self'` permits CSSOM writes (`element.style`) but not `<style>`
  injection; the frontend uses CSSOM only for the roulette wheel and theme variables.
