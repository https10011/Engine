// Local game artwork.
//
// The slot titles use the original art collected in AH77_SOURCE, converted to
// local WebP copies and matched by game identity rather than by position. The
// card and roulette tables reuse the card-room and wheel artwork from the same
// collection; coin-flip and the synthetic test modules keep generated emblems
// because the source collection has no matching coin artwork.
const ART = {
  'table.blackjack': '/assets/games/table.blackjack.webp',
  'table.roulette': '/assets/games/table.roulette.webp',
  'jili.super-ace': '/assets/games/jili.super-ace.webp',
  'jili.super-ace-deluxe': '/assets/games/jili.super-ace-deluxe.webp',
  'jili.super-ace-2': '/assets/games/jili.super-ace-2.webp',
  'jili.fortune-gems-2': '/assets/games/jili.fortune-gems-2.webp',
  'jili.fortune-gems-3': '/assets/games/jili.fortune-gems-3.webp',
  'jili.golden-empire': '/assets/games/jili.golden-empire.webp',
  'jili.fortune-garuda-1000': '/assets/games/jili.fortune-garuda-1000.webp',
  'pgsoft.pinata-wins': '/assets/games/pgsoft.pinata-wins.webp',
  'pgsoft.treasure-of-aztec': '/assets/games/pgsoft.treasure-of-aztec.webp',
  'pgsoft.wild-bounty-showdown': '/assets/games/pgsoft.wild-bounty-showdown.webp',
  'pragmatic.gates-of-olympus-1000': '/assets/games/pragmatic.gates-of-olympus-1000.webp',
  'pragmatic.sugar-rush-1000': '/assets/games/pragmatic.sugar-rush-1000.webp',
  'pragmatic.sweet-bonanza-1000': '/assets/games/pragmatic.sweet-bonanza-1000.webp',
  'slots.mahjong-hit': '/assets/games/slots.mahjong-hit.webp',
  'slots.sugar-bang-bang-2': '/assets/games/slots.sugar-bang-bang-2.webp',
  'slots.swerte-fiesta': '/assets/games/slots.swerte-fiesta.webp',
};

export function gameArt(gameId) {
  return ART[gameId] || `/assets/games/${gameId}.svg`;
}
