# Asset Mapping

Where every image the AURUM frontend uses comes from, and how the supplied
`AH77_SOURCE/` collection maps onto the current game catalogue.

## 1. Policy

The current frontend follows these rules:

1. **Use the supplied original art where it matches a game.** The provider game
   thumbnails in `AH77_SOURCE/` are the visual source for the slot catalogue.
   They are converted to local WebP files; no runtime request leaves the
   deployment.
2. **Match by game identity, not by file position.** A title uses the thumbnail
   that actually depicts that title. When a title has no matching source art,
   the generated fallback is kept instead of assigning an unrelated image.
3. **Keep all assets local.** Every `<img>` resolves under `/assets/`; there is
   no CDN, hotlink or third-party script. This satisfies `img-src 'self' data:`.
4. **Presentation is original.** Copy, banners, badges, layout, icons and the
   AURUM brand remain project artwork. Third-party logos and regulator marks
   are not reproduced.

## 2. Reference asset groups → current assets

| Reference group | Files in mirror | Current use |
| --- | ---: | --- |
| Provider game thumbnails (`TCG_GAME_ICONS`) | 57 | **Used** for 16 slot titles plus the blackjack card-room and roulette wheel tiles. Converted to `public/assets/games/*.webp`. |
| Lobby promotional banners | 18 | **Artwork used.** Character crops from three original banners become `public/assets/banners/feature-*.webp`; new copy overlays them. Deposit/bonus text is not reused. |
| Tenant logo and splash | 2 | Not used. AURUM has its own wordmark and mark. |
| Provider wordmarks (`RNG_LIST_VENDOR`) | 13 | Not used. Provider names are rendered as text. |
| Regulatory marks (PAGCOR/BMM) | 2+ | Not used. AURUM is not a licensed operator. |
| App icons / splash screens | several | Original SVG app icon and favicon. |
| UI icon font / sprites | bundled | Original inline 24×24 SVG icon set (`lib/icons.js`). |
| Web fonts | none bundled | System font stack; no network font request. |
| Decorative GIFs | several | Not used for static art; game motion is local CSS/JS. |

## 3. Game art mapping

Each file below is a 400×400 WebP derivative of the named source thumbnail,
cropped to a square and compressed locally.

| Game id | Source thumbnail | Output |
| --- | --- | --- |
| `jili.super-ace` | `JL/EN/JL0033.avif` | `jili.super-ace.webp` |
| `jili.super-ace-deluxe` | `JL/EN/JL0162.avif` | `jili.super-ace-deluxe.webp` |
| `jili.super-ace-2` | `JL/EN/JL0214.avif` | `jili.super-ace-2.webp` |
| `jili.fortune-gems-2` | `JL/EN/JL0045.avif` | `jili.fortune-gems-2.webp` |
| `jili.fortune-gems-3` | `JL/EN/JL0156.avif` | `jili.fortune-gems-3.webp` |
| `jili.golden-empire` | `JL/EN/JL0044.avif` | `jili.golden-empire.webp` |
| `jili.fortune-garuda-1000` | `JL/EN/696.avif` | `jili.fortune-garuda-1000.webp` |
| `pgsoft.pinata-wins` | `PG/EN/PG0144.avif` | `pgsoft.pinata-wins.webp` |
| `pgsoft.treasure-of-aztec` | `PG/EN/2160925.webp` | `pgsoft.treasure-of-aztec.webp` |
| `pgsoft.wild-bounty-showdown` | `PG/EN/PG0113.avif` | `pgsoft.wild-bounty-showdown.webp` |
| `pragmatic.gates-of-olympus-1000` | `PP/EN/PP0639.avif` | `pragmatic.gates-of-olympus-1000.webp` |
| `pragmatic.sugar-rush-1000` | `PP/EN/PP0696.avif` | `pragmatic.sugar-rush-1000.webp` |
| `pragmatic.sweet-bonanza-1000` | `PP/EN/PP0739.avif` | `pragmatic.sweet-bonanza-1000.webp` |
| `slots.mahjong-hit` | `MW/EN/MW0057.avif` | `slots.mahjong-hit.webp` |
| `slots.sugar-bang-bang-2` | `FC/EN/FC0062.avif` | `slots.sugar-bang-bang-2.webp` |
| `slots.swerte-fiesta` | `YB/EN/1-1068.webp` | `slots.swerte-fiesta.webp` |
| `table.blackjack` | live card-room `SEX/EN/MX-LIVE-001-11.avif` (cropped above the baccarat label) | `table.blackjack.webp` |
| `table.roulette` | `EG4/EN/EG4147.png` | `table.roulette.webp` |

`coinflip.heads-tails`, `synthetic.slot3x3`, `synthetic.blackjack` and
`synthetic.roulette` keep their generated SVG emblems because the collection
has no matching coin or synthetic-test artwork.

## 4. Lobby banners

The carousel no longer uses generated placeholder slides. It uses three
character crops from the supplied banners, with the original promotional text
removed and new short copy supplied by `lobby.js`:

| Slide | Source | Output | Copy theme |
| --- | --- | --- | --- |
| 1 | `1773316120760` character art | `feature-super-ace.webp` | Take a seat / play |
| 2 | `1773316241250` character art | `feature-gifts.webp` | Pick a favourite |
| 3 | `1773316164683` character art | `feature-roulette.webp` | Table games |

The old generated `slide-{1,2,3}.svg` files remain in the repository as unused
archival assets but are not referenced by the application.

## 5. Animation assets

Game feedback is local CSS/JS, not video or third-party animation libraries:

* slot reels use per-column transform/blur and a staggered stop;
* a win overlay shows the actual server payout, a tier label based on the
  server multiplier, rotating rays and falling coins;
* jackpot/free-spin announcements are driven by the returned `features`;
* blackjack cards deal with a 3D flip (`deal-in`), including the hole card
  when the state stops hiding it;
* roulette uses a long cubic-bezier rotation and lands on the server's winning
  pocket, with result-badge `pop`;
* the coin uses a 3D flip and lands on the returned face;
* every effect respects `prefers-reduced-motion`.

## 6. Icons

`public/assets/js/lib/icons.js` defines a 24×24 stroke set (`home`, `grid`,
`search`, `wallet`, `user`, `menu`, `close`, `flame`, `slots`, `cards`, `wheel`,
`coin`, `shield`, `sound`, `sound-off`, `eye`, `check`, `trophy`, `bolt`, `key`,
`info`, `lock`, `spark`, `clock`, `plus`, `minus`, `refresh`, `chevron-*`,
`arrow-right`). They are inline SVG and inherit `currentColor`.

## 7. Brand files

| File | Purpose | Dimensions |
| --- | --- | --- |
| `brand/aurum-logo.svg` | header/footer wordmark | 320×84 viewBox |
| `brand/aurum-mark.svg` | square monogram | 84×84 viewBox |
| `brand/app-icon.svg` | app icon | 512×512 |
| `brand/favicon.svg` | simplified mark | 32×32 |

## 8. CSS and JS

No reference stylesheet or bundle is used. `app.css`, `lobby.css`, `games.css`,
`views.css` and `admin.css` were written for this project; `js/app.js`,
`lib/*`, `views/*` and `admin.js` are original ES modules. See
[FRONTEND_ARCHITECTURE.md](FRONTEND_ARCHITECTURE.md).
