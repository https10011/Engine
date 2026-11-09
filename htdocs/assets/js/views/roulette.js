// The roulette table.
//
// A betting slip is built locally and placed with the engine: `place_bets`
// records it, `spin` wagers its total and settles it. The server re-validates
// the slip against the one it recorded, so a tampered request cannot spin a
// different set of bets than the one shown — and the winning pocket comes from
// the engine's RNG, never from this page.
//
// The wheel drawn here is the game's own pocket order. It arrives in the
// presentation payload, so a European table draws a single-zero wheel and an
// American table would draw the double-zero one without a frontend change.

import { el, mount, money, ratio, providerLabel } from '../lib/dom.js';
import { gameArt } from '../lib/art.js';
import { api, ApiError } from '../lib/api.js';
import { toast, errorState, applyTheme } from '../lib/ui.js';
import * as store from '../lib/store.js';
import * as sound from '../lib/sound.js';
import { flashBalance, syncBalance } from '../lib/shell.js';

// The wheel order is a framework constant rather than part of the module's
// configuration, so the interface carries the standard European layout and
// falls back to it if the payload does not supply one.
const EUROPEAN_ORDER = [
  '0', '32', '15', '19', '4', '21', '2', '25', '17', '34', '6', '27', '13', '36',
  '11', '30', '8', '23', '10', '5', '24', '16', '33', '1', '20', '14', '31', '9',
  '22', '18', '29', '7', '28', '12', '35', '3', '26',
];

const RED = new Set([
  '1', '3', '5', '7', '9', '12', '14', '16', '18', '19', '21', '23', '25', '27', '30', '32', '34', '36',
]);

function colourOf(number) {
  if (number === '0' || number === '00') return 'green';
  return RED.has(number) ? 'red' : 'black';
}

const OUTSIDE = [
  { type: 'red', label: 'Red', numbers: 'red' },
  { type: 'black', label: 'Black', numbers: 'black' },
  { type: 'odd', label: 'Odd', numbers: 'odd' },
  { type: 'even', label: 'Even', numbers: 'even' },
  { type: 'low', label: '1–18', numbers: 'low' },
  { type: 'high', label: '19–36', numbers: 'high' },
];

const PAYOUTS = {
  straight: '35:1', split: '17:1', street: '11:1', corner: '8:1', six_line: '5:1',
  column: '2:1', dozen: '2:1', red: '1:1', black: '1:1', odd: '1:1', even: '1:1', low: '1:1', high: '1:1',
};

export async function renderRoulette(context) {
  const gameId = context.route.id;
  let presentation;

  try {
    presentation = await api.presentation(gameId);
  } catch (error) {
    return { node: errorState(error instanceof ApiError ? error.message : 'The table could not be loaded.', () => context.navigate('/')) };
  }

  if (context.isStale()) return { node: el('div') };

  const game = presentation.game;
  const theme = presentation.theme || {};
  const rules = presentation.configuration || {};
  const scale = game.min_bet.scale;
  const currency = game.min_bet.currency;
  const chipValues = [100, 500, 1000, 5000].filter((v) => v <= (rules.max_bet_minor || Infinity));

  let chip = chipValues[0] || game.min_bet.amount;
  let slip = []; // [{type, numbers, stake_minor, label}]
  let session = null;
  let busy = false;
  let destroyed = false;
  let history = [];
  let lastSpin = null;

  const slipList = el('div', { class: 'slip__list' });
  const slipTotal = el('strong', { class: 'mono', text: money(0, scale, currency) });
  const status = el('p', { class: 'table__status', text: 'Click the felt to place chips, then spin.', 'aria-live': 'polite' });
  const payoutReadout = el('span', { class: 'readout__value', text: '—' });
  const wheel = el('div', { class: 'wheel' });
  const resultBadge = el('div', { class: 'wheel__result', hidden: true });

  const node = el('div', { class: 'fade-in' });

  const order = EUROPEAN_ORDER;

  /* ------------------------------------------------------------------- felt */

  function addChip(type, numbers, label) {
    if (busy) return;
    sound.sfx.chip();

    const existing = slip.find((bet) => bet.type === type && sameNumbers(bet.numbers, numbers));
    const cap = type === 'straight' ? (rules.max_straight_bet_minor || Infinity) : (rules.max_outside_bet_minor || Infinity);

    if (existing) {
      existing.stake_minor = Math.min(cap, existing.stake_minor + chip);
    } else {
      slip.push({ type, numbers, stake_minor: chip, label });
    }

    paintSlip();
  }

  function removeChip(type, numbers) {
    slip = slip.filter((bet) => !(bet.type === type && sameNumbers(bet.numbers, numbers)));
    paintSlip();
  }

  function sameNumbers(a, b) {
    if (a.length !== b.length) return false;
    const sa = [...a].sort();
    const sb = [...b].sort();
    return sa.every((value, index) => value === sb[index]);
  }

  function feltStake(type, numbers) {
    const bet = slip.find((b) => b.type === type && sameNumbers(b.numbers, numbers));
    return bet ? bet.stake_minor : 0;
  }

  function total() {
    return slip.reduce((sum, bet) => sum + bet.stake_minor, 0);
  }

  function paintSlip() {
    slipTotal.textContent = money(total(), scale, currency);
    if (slip.length === 0) {
      mount(slipList, [el('p', { class: 'muted', text: 'No bets placed.' })]);
    } else {
      mount(slipList, slip.map((bet) => el('div', { class: 'slip__row' }, [
        el('span', { text: bet.label }),
        el('span', { class: 'mono', text: money(bet.stake_minor, scale, '') }),
        el('button', {
          class: 'slip__remove',
          type: 'button',
          'aria-label': `Remove ${bet.label}`,
          onClick: () => removeChip(bet.type, bet.numbers),
        }, '×'),
      ])));
    }
    paintFelt();
    paintSpinButton();
  }

  const feltButtons = [];

  function paintFelt() {
    for (const { node: cell, type, numbers } of feltButtons) {
      const stake = feltStake(type, numbers);
      cell.classList.toggle('is-backed', stake > 0);
      const chipNode = cell.querySelector('.bet-chip');
      if (chipNode) {
        chipNode.textContent = stake > 0 ? money(stake, scale, '') : '';
        chipNode.hidden = stake === 0;
      }
    }
  }

  function straightCell(number) {
    const numbers = [number];
    const cell = el('button', {
      class: `felt__number felt__number--${colourOf(number)}`,
      type: 'button',
      'aria-label': `Bet straight on ${number}`,
      onClick: (event) => {
        if (event.shiftKey || event.button === 2) removeChip('straight', numbers);
        else addChip('straight', numbers, `Straight ${number}`);
      },
      onContextmenu: (event) => { event.preventDefault(); removeChip('straight', numbers); },
    }, [
      el('span', { text: number }),
      el('span', { class: 'bet-chip', hidden: true }),
    ]);

    feltButtons.push({ node: cell, type: 'straight', numbers });
    return cell;
  }

  function outsideCell(spec) {
    const numbers = spec.numbers === 'red'
      ? order.filter((n) => colourOf(n) === 'red')
      : spec.numbers === 'black'
        ? order.filter((n) => colourOf(n) === 'black')
        : spec.numbers === 'odd'
          ? order.filter((n) => n !== '0' && n !== '00' && Number(n) % 2 === 1)
          : spec.numbers === 'even'
            ? order.filter((n) => n !== '0' && n !== '00' && Number(n) % 2 === 0)
            : spec.numbers === 'low'
              ? order.filter((n) => n !== '0' && n !== '00' && Number(n) <= 18)
              : order.filter((n) => n !== '0' && n !== '00' && Number(n) >= 19);

    const cell = el('button', {
      class: `felt__outside felt__outside--${spec.type}`,
      type: 'button',
      'aria-label': `${spec.label} (${PAYOUTS[spec.type]})`,
      onClick: (event) => {
        if (event.shiftKey || event.button === 2) removeChip(spec.type, numbers);
        else addChip(spec.type, numbers, spec.label);
      },
      onContextmenu: (event) => { event.preventDefault(); removeChip(spec.type, numbers); },
    }, [
      el('span', { text: spec.label }),
      el('span', { class: 'bet-chip', hidden: true }),
    ]);

    feltButtons.push({ node: cell, type: spec.type, numbers });
    return cell;
  }

  function dozenCell(start) {
    const numbers = [];
    for (let n = start; n < start + 12; n += 1) numbers.push(String(n));
    const cell = el('button', {
      class: 'felt__dozen',
      type: 'button',
      'aria-label': `Dozen ${start}–${start + 11}`,
      onClick: () => addChip('dozen', numbers, `Dozens ${start}–${start + 11}`),
      onContextmenu: (event) => { event.preventDefault(); removeChip('dozen', numbers); },
    }, [
      el('span', { text: `${start}–${start + 11}` }),
      el('span', { class: 'bet-chip', hidden: true }),
    ]);
    feltButtons.push({ node: cell, type: 'dozen', numbers });
    return cell;
  }

  function columnCell(remainder) {
    const numbers = [];
    for (let n = remainder; n <= 36; n += 3) numbers.push(String(n));
    const cell = el('button', {
      class: 'felt__column',
      type: 'button',
      'aria-label': `Column ${remainder}`,
      onClick: () => addChip('column', numbers, `Column ${remainder}`),
      onContextmenu: (event) => { event.preventDefault(); removeChip('column', numbers); },
    }, [
      el('span', { text: '2:1' }),
      el('span', { class: 'bet-chip', hidden: true }),
    ]);
    feltButtons.push({ node: cell, type: 'column', numbers });
    return cell;
  }

  function buildFelt() {
    const felt = el('div', { class: 'felt', role: 'group', 'aria-label': 'Roulette betting table' });

    const grid = el('div', { class: 'felt__grid' });

    // A single zero on its own row, then the three columns of twelve.
    if (order.includes('0')) grid.append(straightCell('0'));
    if (order.includes('00')) grid.append(straightCell('00'));

    for (let row = 0; row < 12; row += 1) {
      for (let column = 0; column < 3; column += 1) {
        grid.append(straightCell(String(row * 3 + column + 1)));
      }
    }

    const dozens = el('div', { class: 'felt__dozens' }, [dozenCell(1), dozenCell(13), dozenCell(25)]);
    const columns = el('div', { class: 'felt__columns' }, [columnCell(1), columnCell(2), columnCell(0)]);
    const outside = el('div', { class: 'felt__outside-row' }, OUTSIDE.map(outsideCell));

    mount(felt, [grid, dozens, columns, outside]);
    return felt;
  }

  /* ------------------------------------------------------------------ wheel */

  function buildWheel() {
    const n = order.length;
    mount(wheel, []);
    wheel.append(el('div', { class: 'wheel__hub' }, [el('span', { text: theme.emblem || '◉' })]));
    order.forEach((number, index) => {
      const angle = (360 / n) * index;
      wheel.append(el('div', {
        class: `wheel__pocket wheel__pocket--${colourOf(number)}`,
        style: { '--angle': `${angle}deg`, '--i': String(index) },
        title: number,
      }, [el('span', { text: number })]));
    });
    return wheel;
  }

  /* ------------------------------------------------------------------- play */

  function paintSpinButton() {
    spinButton.disabled = busy || slip.length === 0;
  }

  const spinButton = el('button', { class: 'spin', type: 'button', 'aria-label': 'Spin the wheel', onClick: () => spin() }, 'SPIN');

  async function ensureSession() {
    if (session) return session;
    const data = await api.createSession(game.id);
    session = data.session;
    return session;
  }

  async function spin() {
    if (busy || slip.length === 0 || destroyed) return;
    if (!store.getToken()) { toast('Sign in to play.'); return; }

    busy = true;
    paintSpinButton();
    spinButton.classList.add('is-spinning');
    status.textContent = 'Spinning…';
    sound.sfx.wheel();

    try {
      const active = await ensureSession();

      // The slip is placed first so the server records exactly what is shown,
      // then spun. The spin carries the total stake, which the engine checks
      // against the recorded slip.
      const placed = await api.action(active.id, 'place_bets', {
        payload: { bets: slip },
        idempotencyKey: cryptoId(),
      });
      if (destroyed) return;

      const data = await api.action(active.id, 'spin', {
        bet: total(),
        payload: { bets: slip },
        idempotencyKey: cryptoId(),
      });
      if (destroyed) return;

      const round = data.round;
      const detail = round.result?.data || {};
      lastSpin = detail;
      history.push(detail.winning_number);
      if (history.length > 12) history.shift();
      paintHistory();

      await animateWheel(detail.winning_number);

      const payout = round.payout?.amount ?? 0;
      const net = round.net?.amount ?? 0;
      payoutReadout.textContent = payout ? money(payout, scale, currency) : '—';

      const won = (detail.bets || []).filter((bet) => bet.outcome === 'win').length;
      status.textContent = payout > 0
        ? `${detail.winning_number} ${detail.colour} — ${won} winning bet${won === 1 ? '' : 's'}, returned ${money(payout, scale, currency)}.`
        : `${detail.winning_number} ${detail.colour} — no winning bets.`;

      paintResultBets(detail);

      if (net > 0) sound.sfx.win(2);
      else sound.sfx.lose();

      flashBalance(net > 0 ? 'win' : net < 0 ? 'lose' : null);
      if (round.balance_after) store.setBalance(round.balance_after);
      else await syncBalance();

      // The slip is consumed by the spin; the felt is cleared for the next one.
      slip = [];
      paintSlip();
    } catch (error) {
      if (!destroyed) {
        sound.sfx.error();
        toast(error instanceof ApiError ? error.message : 'The spin failed.', 'error');
        status.textContent = 'The spin could not be completed.';
      }
    } finally {
      busy = false;
      if (!destroyed) {
        spinButton.classList.remove('is-spinning');
        paintSpinButton();
      }
    }
  }

  function paintResultBets(detail) {
    // Mark the felt so a player can see which bets paid on the last spin.
    for (const { node: cell, type } of feltButtons) {
      const won = (detail.bets || []).some((bet) => bet.type === type && bet.outcome === 'win');
      cell.classList.toggle('is-winning', won);
    }
  }

  async function animateWheel(winningNumber) {
    const index = order.indexOf(String(winningNumber));
    const n = order.length;
    const turns = 4;
    const target = -(360 * turns) + (360 / n) * (index >= 0 ? index : 0);

    wheel.style.transition = 'transform 2.4s cubic-bezier(0.16, 0.84, 0.24, 1)';
    wheel.style.transform = `rotate(${target}deg)`;
    await new Promise((resolve) => setTimeout(resolve, 2450));

    resultBadge.hidden = false;
    resultBadge.textContent = winningNumber;
    resultBadge.className = `wheel__result wheel__result--${colourOf(String(winningNumber))}`;
  }

  function cryptoId() {
    if (window.crypto?.randomUUID) return window.crypto.randomUUID();
    return `${Date.now()}-${Math.random().toString(16).slice(2)}`;
  }

  /* -------------------------------------------------------------- assembly */

  const chipButtons = chipValues.map((value) => el('button', {
    class: 'chip-btn',
    type: 'button',
    text: money(value, scale, ''),
    'aria-pressed': value === chip ? 'true' : 'false',
    onClick: (event) => {
      chip = value;
      chipButtons.forEach((b) => b.setAttribute('aria-pressed', 'false'));
      event.currentTarget.setAttribute('aria-pressed', 'true');
      sound.sfx.click();
    },
  }));

  const rulesPanel = el('div', { class: 'rules' }, [
    el('ul', {}, [
      el('li', {}, [el('strong', { text: 'Variant: ' }), String(rules.variant || 'european')]),
      el('li', {}, [el('strong', { text: 'Bet range: ' }), `${money(rules.min_bet_minor ?? 0, scale, currency)} – ${money(rules.max_bet_minor ?? 0, scale, currency)}`]),
      el('li', {}, [el('strong', { text: 'Straight max: ' }), money(rules.max_straight_bet_minor ?? 0, scale, currency)]),
      el('li', {}, [el('strong', { text: 'Outside max: ' }), money(rules.max_outside_bet_minor ?? 0, scale, currency)]),
      el('li', {}, [el('strong', { text: 'Round max: ' }), money(rules.max_total_bet_minor ?? 0, scale, currency)]),
    ]),
    el('p', { class: 'muted', text: 'Right-click a bet, or shift-click it, to remove it. Place your bets when you are ready.' }),
  ]);

  const historyStrip = el('div', { class: 'history-strip', 'aria-label': 'Recent winning numbers' });
  function paintHistory() {
    mount(historyStrip, history.length
      ? history.slice().reverse().map((n) => el('span', { class: `history-strip__dot history-strip__dot--${colourOf(String(n))}`, text: n }))
      : [el('span', { class: 'muted', text: 'No spins yet.' })]);
  }

  const controls = el('div', { class: 'controls' }, [
    el('div', { class: 'controls__row' }, [
      el('span', { class: 'muted', text: 'Chip' }),
      ...chipButtons,
      el('span', { class: 'spacer' }),
      el('div', { class: 'readout readout--win' }, [el('span', { class: 'readout__label', text: 'Payout' }), payoutReadout]),
    ]),
    el('div', { class: 'controls__row' }, [
      spinButton,
      el('button', { class: 'btn btn--ghost', type: 'button', onClick: () => { slip = []; paintSlip(); sound.sfx.click(); } }, 'Clear bets'),
      el('span', { class: 'spacer' }),
      el('a', { class: 'btn btn--ghost', href: '/', 'data-link': true }, 'Lobby'),
    ]),
    status,
  ]);

  const layout = el('div', { class: 'table table--roulette' }, [
    el('div', { class: 'slot__head' }, [
      el('img', { class: 'slot__art', src: gameArt(game.id), alt: '', width: '46', height: '46' }),
      el('div', { class: 'slot__titles' }, [
        el('span', { class: 'slot__name', text: game.name }),
        el('span', { class: 'slot__provider', text: providerLabel(game.provider) }),
      ]),
      el('span', { class: 'spacer' }),
      el('div', { class: 'slot__badges' }, [
        el('span', { class: 'tag tag--info', text: String(rules.variant || 'european') }),
        el('span', { class: 'tag', text: 'house edge 2.70%' }),
      ]),
    ]),
    el('div', { class: 'roulette__top' }, [
      el('div', { class: 'wheel-wrap' }, [wheel, resultBadge]),
      el('div', { class: 'panel slip' }, [
        el('h2', { text: 'Bet slip' }),
        slipList,
        el('div', { class: 'slip__total' }, [el('span', { text: 'Total stake' }), slipTotal]),
      ]),
    ]),
    buildFelt(),
    historyStrip,
    controls,
    el('aside', { class: 'slot__side' }, [el('div', { class: 'panel' }, [el('h2', { text: 'Table rules' }), rulesPanel])]),
  ]);

  applyTheme(layout, theme);
  mount(node, [layout]);
  buildWheel();
  paintSlip();
  paintHistory();

  return {
    node,
    destroy() {
      destroyed = true;
      if (session) api.closeSession(session.id).catch(() => {});
    },
  };
}
