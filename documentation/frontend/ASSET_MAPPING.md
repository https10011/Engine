# Asset Mapping

How the reference site's asset groups were handled, and where every image the AURUM
frontend uses comes from.

## 1. Policy

Three rules governed the mapping:

1. **No third-party artwork is shipped.** Provider game thumbnails, provider wordmarks,
   tenant logos, promotional banners and regulatory marks in `AH77_SOURCE/` are
   copyrighted works belonging to other parties. They are not copied, recoloured,
   cropped or traced.
2. **The UI still needs pictures.** Every registered game therefore gets an original
   SVG tile generated from its own theme metadata, and the shell has an original brand
   set and three original banners.
3. **Every image has a source path in this repository.** No asset is fetched from a
   CDN at runtime, which also satisfies the page's `img-src 'self' data:` CSP.

## 2. Reference asset groups → AURUM assets

| Reference group | Files in mirror | Size / format | AURUM decision | Files produced |
| --- | ---: | --- | --- | --- |
| Provider game thumbnails (`TCG_GAME_ICONS`) | 57 | 170×170 / 244×244 AVIF/PNG | Not used (third-party art). Generate a tile per registered engine game. | `public/assets/games/{gameId}.svg` — 22 files, 400×400 viewBox |
| Lobby promotional banners | 18 | 750×308 JPG/PNG | Not used (deposit/bonus advertising). Write three original slides about virtual credits. | `public/assets/banners/slide-{1,2,3}.svg`, 750×308 |
| Tenant logo and splash | 2 | 946×248 PNG, 1080×1920 WebP | Not used. Original AURUM wordmark, monogram, app icon. | `public/assets/brand/{aurum-logo,aurum-mark,app-icon,favicon}.svg` |
| Provider wordmarks (`RNG_LIST_VENDOR`) | 13 | 600×300 black PNG | Not used. Provider name is rendered as text from the catalogue. | — |
| Regulatory marks (PAGCOR/BMM) | 2+ | various | Not used. AURUM is not a licensed operator; the footer states that only virtual credits exist. | — |
| App icons / splash screens | several | PNG/WebP | Original SVG icons replace them. | `brand/favicon.svg`, `brand/app-icon.svg` |
| UI icon font / sprites | bundled | webpack chunks | Original 24×24 inline SVG icon set drawn for this project. | `public/assets/js/lib/icons.js` |
| Web fonts | none bundled | — | System font stack; no network font request. | — |
| Decorative GIFs | several | GIF | Not used. Motion is CSS only. | — |

## 3. Generated game tiles

`tools/generate_assets.php` reads a lobby JSON dump and writes one SVG per game:

```bash
curl -s http://127.0.0.1:3000/api/v1/games/lobby > /tmp/lobby.json
php tools/generate_assets.php /tmp/lobby.json public/assets
```

Each 400×400 tile contains:

* a diagonal gradient background from the module's `theme.base` → `theme.panel`;
* three seeded rings positioned by `crc32(game.id)`, so two games with the same theme
  still look different;
* a large central emblem from `theme.emblem` over a rounded frame;
* the provider short name and the text `ENGINE APPROXIMATION`, which is honest about
  the art being a generated stand-in rather than provider artwork;
* the game's own accent colours (`theme.accent`, `theme.accent2`).

Because the input is the live catalogue, re-running the generator after registering a
new game produces a usable tile without design work. Tiles are referenced as
`/assets/games/{id}.svg` by the lobby, search sheet, slot header and table headers.

## 4. Banners

The three slides are original 750×308 SVGs. They intentionally keep the reference's
aspect ratio so the carousel geometry feels familiar, but the copy is new and factual:

| Slide | Kicker | Subject |
| --- | --- | --- |
| 1 | Virtual credits | The floor is open; every result is server-settled |
| 2 | New accounts | Start with 1,000.00 credits; no card |
| 3 | How it works | The browser never decides an outcome |

They contain no deposit language, no bonus/prize promises and no third-party logos.

## 5. Brand files

| File | Purpose | Dimensions |
| --- | --- | --- |
| `brand/aurum-logo.svg` | header/footer wordmark with the "VIRTUAL CASINO" tagline | 320×84 viewBox |
| `brand/aurum-mark.svg` | square monogram used in the footer and dialogs | 84×84 viewBox |
| `brand/app-icon.svg` | 512×512 app icon (`apple-touch-icon`) | 512×512 |
| `brand/favicon.svg` | simplified 32×32 mark | 32×32 |

The logo was regenerated once after mobile review because the tagline was clipped; the
current viewBox is 320×84 with the tagline on its own baseline.

## 6. Icons

`public/assets/js/lib/icons.js` defines a 24×24 stroke set (`home`, `grid`, `search`,
`wallet`, `user`, `menu`, `close`, `flame`, `slots`, `cards`, `wheel`, `coin`, `shield`,
`sound`, `sound-off`, `eye`, `check`, `trophy`, `bolt`, `key`, `info`, `lock`, `spark`,
`clock`, `plus`, `minus`, `refresh`, `chevron-*`, `arrow-right`). They are inserted as
inline SVG (`icon(name)`), which inherits `currentColor` and needs no icon font.

This replaced emoji glyphs that rendered as tofu on systems without an emoji font
(observed in the headless verification container with `🔊` and `🃏`).

## 7. CSS and JS

No reference stylesheet or bundle is used. `app.css`, `lobby.css`, `games.css` and
`views.css` were written for this project; `js/app.js`, `lib/*` and `views/*` are
original ES modules. See [FRONTEND_ARCHITECTURE.md](FRONTEND_ARCHITECTURE.md).

## 8. Missing assets

Artwork that does not exist (provider clients, live-dealer video, jackpot feeds) is
documented in [MISSING_GAME_ASSETS.md](MISSING_GAME_ASSETS.md) with replacement paths.
No image is referenced that is not in this repository.
