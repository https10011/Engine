# Resource Gap Analysis

What the reference site offers, what this engine actually registers, and how the
difference is handled without inventing content.

## 1. Headline gap

| Resource | Reference mirror | This repository |
| --- | ---: | ---: |
| Game titles shown in the lobby | 49 cards in the captured snapshot | **22** registered modules (`GET /api/v1/games/lobby`) |
| Provider thumbnails | 57 files, 15 providers | 0 third-party; 22 generated original tiles |
| Playable game clients | provider iframes (not captured) | 4 real interfaces covering 22 modules |
| Promotional banners | 18 | 3 original slides |
| Categories | Hot, Slot, Fishing, Live Casino, Poker, Lottery, Sport, E-sport | `slot`, `card`, `roulette` |
| Deposit / withdrawal / bonus | yes (real-money framing) | none — virtual credits only |
| Member centre / lottery | separate bundles | `My play` (rounds + ledger) and `Admin` (operators) |
| Winners ticker, jackpot feed | present in snapshot | not faked; no such engine feed exists |

The reference is a multi-provider real-money casino. This project is a self-contained
engine with three frameworks and a small set of modules that execute end-to-end. The
frontend therefore reflects the engine, not the reference's catalogue.

## 2. Registered games and their interfaces

All 22 appear in the lobby, search, category filters and "max win" shelf. Each opens a
working interface; none is a static placeholder.

| # | Game id | Name | Category | RTP | Interface |
| ---: | --- | --- | --- | ---: | --- |
| 1 | `jili.fortune-garuda-1000` | Fortune Garuda 1000 | slot | 97.10% | slot cabinet |
| 2 | `jili.fortune-gems-2` | Fortune Gems 2 | slot | 97.00% | slot cabinet |
| 3 | `jili.fortune-gems-3` | Fortune Gems 3 | slot | 97.00% | slot cabinet |
| 4 | `jili.golden-empire` | Golden Empire | slot | 97.00% | slot cabinet |
| 5 | `jili.super-ace` | Super Ace | slot | 96.00% | slot cabinet |
| 6 | `jili.super-ace-2` | Super Ace 2 | slot | 97.00% | slot cabinet |
| 7 | `jili.super-ace-deluxe` | Super Ace Deluxe | slot | 97.00% | slot cabinet |
| 8 | `pgsoft.pinata-wins` | Pinata Wins | slot | 96.75% | slot cabinet |
| 9 | `pgsoft.treasure-of-aztec` | Treasure of Aztec | slot | 96.71% | slot cabinet |
| 10 | `pgsoft.wild-bounty-showdown` | Wild Bounty Showdown | slot | 96.75% | slot cabinet |
| 11 | `pragmatic.gates-of-olympus-1000` | Gates of Olympus 1000 | slot | 96.50% | slot cabinet |
| 12 | `pragmatic.sugar-rush-1000` | Sugar Rush 1000 | slot | 96.00% | slot cabinet |
| 13 | `pragmatic.sweet-bonanza-1000` | Sweet Bonanza 1000 | slot | 96.53% | slot cabinet |
| 14 | `slots.mahjong-hit` | Mahjong Hit | slot | 96.95% | slot cabinet |
| 15 | `slots.sugar-bang-bang-2` | Sugar Bang Bang 2 | slot | 96.50% | slot cabinet |
| 16 | `slots.swerte-fiesta` | Swerte Fiesta | slot | 96.50% | slot cabinet |
| 17 | `synthetic.slot3x3` | Synthetic Slot 3×3 | slot | 95.00% | slot cabinet (test fixture) |
| 18 | `table.blackjack` | Blackjack | card | 99.50% | blackjack table |
| 19 | `synthetic.blackjack` | Synthetic Blackjack | card | 99.50% | blackjack table (test fixture) |
| 20 | `coinflip.heads-tails` | Coin Flip | card | 50.00% | dedicated coin-flip view |
| 21 | `table.roulette` | Roulette | roulette | 97.30% | roulette wheel + felt |
| 22 | `synthetic.roulette` | Synthetic Roulette | roulette | 97.30% | roulette wheel + felt (test fixture) |

Names and RTPs are the values returned by the engine at build time. `synthetic.*` and
`coinflip.*` are engine examples, labelled as such in their provider line rather than
presented as commercial titles.

## 3. Gaps and how each is handled

| Gap | Handling |
| --- | --- |
| Reference games are provider clients; the engine has its own modules | The 17 slots share one metadata-driven cabinet; its reels, symbols, paytable and features come from each module's presentation payload |
| Reference has fishing/live/poker/lottery/sport categories | Not shown. Empty categories are not rendered, and the category rail is built from the live catalogue's counts |
| No provider artwork | Generated SVG tiles from theme metadata, labelled "engine approximation" |
| No deposit flow | No deposit button, no wallet top-up, no payment SDK. Balance comes from the engine's virtual ledger |
| No winners ticker or jackpot feed | Not fabricated. The lobby's trust strip reports catalogue counts and settlement facts instead |
| No member centre | `My play` shows real `GET /history/rounds` and `GET /wallet/transactions` data; `Admin` exposes the existing probability endpoints to admin accounts |
| No lottery module | No lottery UI |
| No localization switcher | UI copy is English only; the reference's language bundles are not reused |
| No installable app manifest | `theme-color`, `apple-touch-icon` and favicon are set; a full PWA manifest was not requested |

## 4. Adding a game later

1. Register the module with the engine.
2. Refresh the lobby JSON.
3. Run `php tools/generate_assets.php /tmp/lobby.json public/assets` to produce its
   tile.
4. Slots, blackjack and roulette are dispatched by category, so no view change is
   needed. A new category needs a route and a view (see
   [FRONTEND_ARCHITECTURE.md](FRONTEND_ARCHITECTURE.md)).

No manual list of 22 ids is maintained in the frontend; the lobby, filters, search and
shelves are derived from the API response.

## 5. What would still be missing relative to the reference

A full parity build would require provider game clients (not available), a payment and
KYC stack (out of scope and contrary to the project's virtual-credit invariant), 49
additional games with their own math, regulatory licensing, and the reference's
localization and member-centre features. Those are product decisions, not frontend
work, and are intentionally absent.
