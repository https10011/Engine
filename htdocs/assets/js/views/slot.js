// The slot interface.
//
// One view renders all sixteen slots, but not identically. It is driven by
// `/api/v1/games/{id}/presentation`: the reel count and per-reel row counts
// come from the game, so a 3-4-5-4-3 ways game draws a ragged cabinet and a
// 5x3 payline game draws a rectangle. The symbol glyphs, the paytable rows and
// the feature list are the module's own, so a themed cabinet cannot disagree
// with the engine about what a symbol pays.
//
// The client never decides a result. It posts `spin`, waits, and animates the
// grid and the wins the server returned.

import { el, mount, money, ratio, providerLabel } from '../lib/dom.js';
import { gameArt } from '../lib/art.js';
import { icon } from '../lib/icons.js';
import { api, ApiError } from '../lib/api.js';
import { toast, errorState, applyTheme } from '../lib/ui.js';
import * as store from '../lib/store.js';
import * as sound from '../lib/sound.js';
import { flashBalance, syncBalance } from '../lib/shell.js';
import { renderTable } from './table.js';

// A short, stable glyph per symbol id. The engine's ids are terse (`T`, `GC`,
// `JW`, `SC`); a themed cabinet shows a face a player recognises, and this maps
// the common ids to one. An unknown id falls back to its own initials, so a new
// game still renders without a frontend change.
const GLYPHS = {
  T: '10', J: 'J', Q: 'Q', K: 'K', A: 'A',
  GC: '♠', JW: '★', SC: '✦', BONUS: '◆', WILD: '★',
  // Mahjong and themed sets
  DRAGON: '中', BAMBOO: '竹', CIRCLE: '筒', FLOWER: '✿',
  // Gems / fruit
  RUBY: '◆', EMERALD: '◈', SAPPHIRE: '●', DIAMOND: '♦', COIN: '⬤',
};

function glyph(symbol) {
  if (!symbol) return '?';
  if (GLYPHS[symbol.id]) return GLYPHS[symbol.id];
  const name = symbol.name || symbol.id || '?';
  return String(name).slice(0, 2);
}

export async function renderSlot(context) {
  const gameId = context.route.id;
  const node = el('div', { class: 'fade-in' });

  let presentation;
  try {
    presentation = await api.presentation(gameId);
  } catch (error) {
    const message = error instanceof ApiError && error.status === 404
      ? `No game is registered under “${gameId}”.`
      : error instanceof ApiError ? error.message : 'The game could not be loaded.';
    return { node: errorState(message, () => context.navigate('/')) };
  }

  if (context.isStale()) return { node };

  const game = presentation.game;
  const theme = presentation.theme || {};

  if (game.category !== 'slot') {
    // A table game reached through the slot route: hand it to the right view.
    return renderTable(context, presentation);
  }

  const slot = presentation.slot;
  const reels = presentation.layout.reels;
  const rows = presentation.layout.rows;
  // The presenter sends symbols as a list, so index them by id before any
  // lookup. Building the map from `Object.entries` treated the list as though
  // it were keyed by id, leaving every lookup undefined.
  const symbolsById = Object.fromEntries(
    (Array.isArray(slot.symbols) ? slot.symbols : Object.values(slot.symbols || {}))
      .filter(Boolean)
      .map((symbol) => [symbol.id, symbol])
  );
  const scale = game.min_bet.scale;
  const currency = game.min_bet.currency;

  // The bet ladder starts at the game's own minimum and steps by its own step,
  // so the interface cannot offer a wager the engine will reject.
  const minBet = game.min_bet.amount;
  const maxBet = game.max_bet.amount;
  const step = Math.max(1, game.bet_step.amount);
  const ladder = [];
  for (let amount = minBet; amount <= maxBet && ladder.length < 40; amount += step) {
    ladder.push(amount);
  }
  if (ladder.length === 0) ladder.push(minBet);

  let betIndex = 0;
  let session = null;
  let spinning = false;
  let freeSpinsRemaining = 0;
  let destroyed = false;

  const grid = el('div', { class: 'reels', role: 'img', 'aria-label': `${game.name} reels` });
  const message = el('p', { class: 'muted', text: 'Press spin to play.', 'aria-live': 'polite' });
  const winReadout = el('div', { class: 'readout readout--win' }, [
    el('span', { class: 'readout__label', text: 'Win' }),
    el('span', { class: 'readout__value', text: '—' }),
  ]);
  const betReadout = el('output', { class: 'mono', text: money(ladder[0], scale, currency) });
  const spinButton = el('button', {
    class: 'spin',
    type: 'button',
    'aria-label': 'Spin the reels',
    onClick: () => spin(),
  }, 'SPIN');

  const soundIcon = el('span', { class: 'sound-toggle__icon' });
  const soundLabel = el('span', { text: sound.isEnabled() ? 'Sound on' : 'Sound off' });
  const soundButton = el('button', {
    class: 'sound-toggle',
    type: 'button',
    'aria-pressed': sound.isEnabled() ? 'true' : 'false',
    onClick: () => paintSound(sound.toggle()),
  }, [soundIcon, soundLabel]);

  function paintSound(on) {
    mount(soundIcon, [icon(on ? 'sound' : 'sound-off')]);
    soundLabel.textContent = on ? 'Sound on' : 'Sound off';
    soundButton.setAttribute('aria-pressed', on ? 'true' : 'false');
  }
  paintSound(sound.isEnabled());

  function drawGrid(symbolIds, winningCells = new Set(), options = {}) {
    mount(grid, []);
    const dim = options.dim !== false && winningCells.size > 0;

    for (let r = 0; r < reels; r += 1) {
      const column = el('div', { class: 'reel', style: { '--reel-rows': String(rows[r] || 3) } });
      for (let c = 0; c < (rows[r] || 3); c += 1) {
        const id = symbolIds?.[r]?.[c];
        const symbol = symbolsById[id] || { id, name: id || '' };
        const isWin = winningCells.has(`${r}:${c}`);
        const cell = el('div', {
          class: [
            'cell',
            symbol.is_wild ? 'cell--wild' : '',
            symbol.is_scatter ? 'cell--scatter' : '',
            symbol.type === 'bonus' ? 'cell--bonus' : '',
            symbol.type === 'blank' ? 'cell--blank' : '',
            isWin ? 'cell--win' : '',
            dim && !isWin ? 'cell--dim' : '',
          ].filter(Boolean).join(' '),
          title: symbol.name,
        }, [
          el('span', { text: glyph(symbol), 'aria-hidden': 'true' }),
          el('span', { class: 'cell__label', text: symbol.name }),
        ]);
        column.append(cell);
      }
      grid.append(column);
    }
  }

  // A neutral cabinet face before the first spin: the game's own symbols, no
  // win lines and no readout. It is decoration, not a result.
  function drawIdleGrid() {
    const ids = Object.keys(symbolsById);
    if (ids.length === 0) {
      drawGrid(null);
      return;
    }
    const face = [];
    for (let r = 0; r < reels; r += 1) {
      face.push(Array.from({ length: rows[r] || 3 }, (_, c) => ids[(r * 2 + c) % ids.length]));
    }
    drawGrid(face);
  }

  function winningCellsFrom(result) {
    const set = new Set();
    const evaluations = result?.result?.data?.evaluations || [];
    for (const evaluation of evaluations) {
      for (const win of evaluation.wins || []) {
        for (const position of win.positions || []) {
          set.add(`${position.reel}:${position.row}`);
        }
      }
    }
    return set;
  }

  function showOverlay(kind, title, sub, ms = 1400) {
    const overlay = el('div', { class: `overlay overlay--${kind}` }, [
      el('div', { class: 'overlay__title', text: title }),
      sub ? el('div', { class: 'overlay__sub', text: sub }) : null,
    ]);
    cabinet.append(overlay);
    setTimeout(() => overlay.remove(), ms);
  }

  function showBigWin(payout, multiplier) {
    const tier = multiplier >= 100 ? 'MEGA WIN' : multiplier >= 50 ? 'SUPER WIN' : multiplier >= 20 ? 'BIG WIN' : 'NICE WIN';
    const coins = Array.from({ length: 14 }, (_, index) => el('span', {
      class: 'overlay__coin',
      style: { '--i': String(index) },
      'aria-hidden': 'true',
    }, '₱'));

    const overlay = el('div', { class: 'overlay overlay--win overlay--celebration' }, [
      el('div', { class: 'overlay__rays', 'aria-hidden': 'true' }),
      el('div', { class: 'overlay__coins', 'aria-hidden': 'true' }, coins),
      el('div', { class: 'overlay__title', text: tier }),
      el('div', { class: 'overlay__count', text: money(payout, scale, currency) }),
      el('div', { class: 'overlay__sub', text: `${multiplier.toFixed(1)}× your bet` }),
    ]);
    cabinet.append(overlay);
    setTimeout(() => overlay.remove(), 2800);
  }

  async function ensureSession() {
    if (session) return session;
    const data = await api.createSession(game.id);
    session = data.session;
    return session;
  }

  async function spin() {
    if (spinning || destroyed) return;

    if (!store.getToken()) {
      toast('Sign in to spin.');
      return;
    }

    spinning = true;
    spinButton.disabled = true;
    spinButton.classList.add('is-spinning');
    grid.classList.add('is-spinning');
    winReadout.querySelector('.readout__value').textContent = '—';
    sound.sfx.spin();

    try {
      const active = await ensureSession();
      const free = freeSpinsRemaining > 0;

      // A free spin is wagered with nothing: the engine debits the paid spins
      // and the feature pool carries the free ones.
      const data = await api.action(active.id, 'spin', {
        bet: free ? undefined : ladder[betIndex],
        idempotencyKey: cryptoId(),
      });

      if (destroyed) return;
      const round = data.round;

      // Animate the reels stopping one at a time, then paint the real grid.
      await animateStop(reels);
      grid.classList.remove('is-spinning');

      const cellSet = winningCellsFrom(round);
      drawGrid(round.result?.data?.grid || round.state?.last_grid, cellSet);

      const payout = round.payout?.amount ?? 0;
      const net = round.net?.amount ?? 0;
      const multiplier = Number(round.payout_multiplier?.numerator ?? 0) / Number(round.payout_multiplier?.denominator ?? 1);

      winReadout.querySelector('.readout__value').textContent = money(payout, scale, currency);
      winReadout.classList.remove('is-pulsing');
      void winReadout.offsetWidth;
      winReadout.classList.add('is-pulsing');

      // The free-spin pool is read from the server's own state, not guessed.
      freeSpinsRemaining = Number(round.result?.data?.free_spins_remaining ?? 0);
      updateSpinLabel();

      if (round.result?.data?.free_spin) {
        message.textContent = `Free spin — ${freeSpinsRemaining} remaining.`;
      } else if (net > 0) {
        message.textContent = `You won ${money(payout, scale, currency)}.`;
      } else if (payout > 0) {
        message.textContent = `Returned ${money(payout, scale, currency)}.`;
      } else {
        message.textContent = 'No win this spin.';
      }

      const jackpot = announceFeatures(round, payout);

      if (payout > 0) {
        if (multiplier >= 20) sound.sfx.bigWin();
        else sound.sfx.win(multiplier);
        if (!jackpot && multiplier >= 15) showBigWin(payout, multiplier);
      } else if (!free) {
        sound.sfx.lose();
      }

      flashBalance(net > 0 ? 'win' : net < 0 ? 'lose' : null);
      if (round.balance_after) store.setBalance(round.balance_after);
      else await syncBalance();

      appendHistory(round);
    } catch (error) {
      if (!destroyed) {
        grid.classList.remove('is-spinning');
        sound.sfx.error();
        toast(error instanceof ApiError ? error.message : 'The spin failed.', 'error');
      }
    } finally {
      spinning = false;
      if (!destroyed) {
        spinButton.classList.remove('is-spinning');
        updateSpinLabel();
      }
    }
  }

  function announceFeatures(round, payout) {
    let jackpot = false;
    for (const feature of round.features || []) {
      if (feature.type === 'free_spins' && feature.status === 'started') {
        const count = feature.remaining ?? '';
        showOverlay('feature', 'FREE SPINS', `${count} spins awarded`, 1800);
        sound.sfx.bigWin();
      }
      if (feature.type === 'jackpot' && feature.status === 'started') {
        jackpot = true;
        const tier = feature.detail?.tier ? String(feature.detail.tier).toUpperCase() + ' JACKPOT' : 'JACKPOT';
        showOverlay('jackpot', tier, money(payout, scale, currency), 3000);
        sound.sfx.bigWin();
      }
    }
    return jackpot;
  }

  function updateSpinLabel() {
    if (freeSpinsRemaining > 0) {
      spinButton.textContent = 'FREE';
      spinButton.disabled = false;
      betReadout.textContent = 'Free spin';
    } else {
      spinButton.textContent = 'SPIN';
      spinButton.disabled = spinning;
      betReadout.textContent = money(ladder[betIndex], scale, currency);
    }
  }

  async function animateStop(count) {
    for (let i = 0; i < count; i += 1) {
      const column = grid.children[i];
      if (column) {
        column.classList.add('reel--stopping');
        setTimeout(() => column.classList.remove('reel--stopping'), 240);
      }
      sound.sfx.reelStop(i);
      await delay(70);
    }
    await delay(120);
  }

  const delay = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

  function cryptoId() {
    if (window.crypto?.randomUUID) return window.crypto.randomUUID();
    return `${Date.now()}-${Math.random().toString(16).slice(2)}`;
  }

  /* ------------------------------------------------------------- side panel */

  const historyRows = [];
  function appendHistory(round) {
    historyRows.unshift({
      number: round.round_number,
      bet: round.bet?.amount ?? 0,
      payout: round.payout?.amount ?? 0,
      net: round.net?.amount ?? 0,
    });
    if (historyRows.length > 40) historyRows.pop();
    if (sidePanel?.dataset.tab === 'history') paintHistory();
  }

  const historyList = el('div', { class: 'history-list' });
  function paintHistory() {
    if (historyRows.length === 0) {
      mount(historyList, [el('p', { class: 'muted', text: 'No spins yet in this session.' })]);
      return;
    }
    mount(historyList, historyRows.map((row) => el('div', { class: 'history-list__row' }, [
      el('span', { text: `#${row.number}` }),
      el('span', { class: 'mono', text: money(row.bet, scale, '') }),
      el('span', { class: `net ${row.net > 0 ? 'net--up' : row.net < 0 ? 'net--down' : ''}`, text: money(row.net, scale, '') }),
    ])));
  }

  const sidePanel = el('aside', { class: 'slot__side' });

  function panelTab(id, label, content) {
    return { id, label, content };
  }

  const tabs = [
    panelTab('paytable', 'Paytable', paytablePanel()),
    panelTab('rules', 'Rules', rulesPanel()),
    panelTab('history', 'Session', historyList),
  ];

  function paintSide(active = 'paytable') {
    sidePanel.dataset.tab = active;
    const tabBar = el('div', { class: 'tabs', role: 'tablist' }, tabs.map((tab) =>
      el('button', {
        class: 'tab',
        type: 'button',
        role: 'tab',
        text: tab.label,
        'aria-selected': tab.id === active ? 'true' : 'false',
        onClick: () => paintSide(tab.id),
      })
    ));
    const body = tabs.find((tab) => tab.id === active)?.content;
    if (active === 'history') paintHistory();
    mount(sidePanel, [el('div', { class: 'panel' }, [tabBar, body])]);
  }

  function paytablePanel() {
    const wrap = el('div', { class: 'paytable' });
    const line = slot.paytable?.line_payouts || {};
    const scatter = slot.paytable?.scatter_payouts || {};
    const ids = Object.keys(line);

    // Order by the five-of-a-kind value so the strongest symbol is on top,
    // which is how a player reads a paytable.
    ids.sort((a, b) => (line[b]?.[5] ?? line[b]?.[4] ?? line[b]?.[3] ?? 0) - (line[a]?.[5] ?? line[a]?.[4] ?? line[a]?.[3] ?? 0));

    for (const id of ids) {
      const symbol = symbolsById[id] || { id, name: id };
      const pays = Object.entries(line[id]).sort((a, b) => Number(a[0]) - Number(b[0]));
      wrap.append(el('div', { class: 'paytable__row' }, [
        el('span', { class: 'paytable__symbol', text: glyph(symbol) }),
        el('span', { class: 'paytable__name' }, [
          symbol.name,
          el('small', { text: `${pays[0]?.[0] ?? 3}+ on a line` }),
        ]),
        el('span', { class: 'paytable__pays' }, pays.map(([count, units]) =>
          el('span', { text: `${count}×${units}` })
        )),
      ]));
    }

    for (const [id, pays] of Object.entries(scatter)) {
      const symbol = symbolsById[id] || { id, name: id };
      const entries = Object.entries(pays).sort((a, b) => Number(a[0]) - Number(b[0]));
      wrap.append(el('div', { class: 'paytable__row' }, [
        el('span', { class: 'paytable__symbol', text: glyph(symbol) }),
        el('span', { class: 'paytable__name' }, [symbol.name, el('small', { text: 'anywhere (scatter)' })]),
        el('span', { class: 'paytable__pays' }, entries.map(([count, units]) => el('span', { text: `${count}×${units}` }))),
      ]));
    }

    return wrap;
  }

  function rulesPanel() {
    const features = (slot.features || []).map(featureName).filter(Boolean);
    const wilds = Object.values(symbolsById).filter((s) => s.is_wild).map((s) => s.name);
    const scatters = Object.values(symbolsById).filter((s) => s.is_scatter).map((s) => s.name);

    return el('div', { class: 'rules' }, [
      el('p', { text: theme.pitch || '' }),
      el('ul', {}, [
        el('li', {}, [el('strong', { text: 'Grid: ' }), `${reels} reels` + (presentation.layout.rectangular ? ` × ${rows[0]} rows` : `, rows ${rows.join('-')}`)]),
        el('li', {}, [el('strong', { text: 'Bet range: ' }), `${money(minBet, scale, currency)} – ${money(maxBet, scale, currency)}`]),
        el('li', {}, [el('strong', { text: 'Maximum win: ' }), `${game.max_win_multiplier}× the bet`]),
        el('li', {}, [el('strong', { text: 'Volatility: ' }), game.volatility]),
        el('li', {}, [el('strong', { text: 'Published RTP: ' }), game.rtp === null ? 'not published' : `${(game.rtp * 100).toFixed(2)}%`]),
        wilds.length ? el('li', {}, [el('strong', { text: 'Wild: ' }), wilds.join(', ') + ' substitutes for paying symbols.']) : null,
        scatters.length ? el('li', {}, [el('strong', { text: 'Scatter: ' }), scatters.join(', ') + ' pays anywhere and can trigger a feature.']) : null,
        features.length ? el('li', {}, [el('strong', { text: 'Features: ' }), features.join(', ') + '.']) : null,
      ]),
      el('p', { class: 'muted', text: 'Choose your bet, set the reels in motion and see where they land.' }),
    ]);
  }

  function featureName(type) {
    return {
      free_spins: 'free spins',
      cascade: 'cascading reels',
      expanding_reels: 'expanding reels',
      combo_multiplier: 'rising multiplier',
      scatter_prize_ladder: 'scatter prize ladder',
      jackpot: 'jackpot',
      bonus_wheel: 'bonus wheel',
      nudge: 'nudges',
      symbol_multiplier: 'symbol multipliers',
      wild_replication: 'wild replication',
      persistent_multiplier: 'persistent multiplier',
      star_card: 'star cards',
      position_multiplier: 'position multipliers',
      symbol_progression: 'symbol progression',
      symbol_split: 'symbol split',
      auxiliary_reel: 'auxiliary reel',
    }[type] || type.replace(/_/g, ' ');
  }

  /* ---------------------------------------------------------------- assembly */

  const cabinet = el('div', { class: 'cabinet' });
  drawIdleGrid();
  mount(cabinet, [grid]);

  const controls = el('div', { class: 'controls' }, [
    el('div', { class: 'controls__row' }, [
      el('div', { class: 'readout' }, [
        el('span', { class: 'readout__label', text: 'Bet' }),
        betReadout,
      ]),
      el('div', { class: 'stepper' }, [
        el('button', { type: 'button', 'aria-label': 'Decrease bet', onClick: () => changeBet(-1) }, '−'),
        el('span', { class: 'sr-only', text: 'Bet per spin' }),
        el('button', { type: 'button', 'aria-label': 'Increase bet', onClick: () => changeBet(1) }, '+'),
      ]),
      el('button', { class: 'btn btn--ghost', type: 'button', onClick: () => changeBet(ladder.length - 1) }, 'Max bet'),
      el('span', { class: 'spacer' }),
      winReadout,
    ]),
    el('div', { class: 'controls__row' }, [
      spinButton,
      el('span', { class: 'spacer' }),
      soundButton,
      el('a', { class: 'btn btn--ghost', href: '/', 'data-link': true }, 'Lobby'),
    ]),
    message,
  ]);

  function changeBet(delta) {
    if (freeSpinsRemaining > 0) return;
    betIndex = Math.min(ladder.length - 1, Math.max(0, betIndex + delta));
    betReadout.textContent = money(ladder[betIndex], scale, currency);
    sound.sfx.click();
  }

  paintSide();

  const stage = el('div', { class: 'slot__stage' }, [
    el('div', { class: 'slot__head' }, [
      el('img', { class: 'slot__art', src: gameArt(game.id), alt: '', width: '46', height: '46' }),
      el('div', { class: 'slot__titles' }, [
        el('span', { class: 'slot__name', text: game.name }),
        el('span', { class: 'slot__provider', text: providerLabel(game.provider) }),
      ]),
      el('span', { class: 'spacer' }),
      el('div', { class: 'slot__badges' }, [
        el('span', { class: 'tag tag--info', text: `${game.volatility} volatility` }),
        el('span', { class: 'tag', text: `max ${game.max_win_multiplier}×` }),
        game.rtp !== null ? el('span', { class: 'tag', text: `RTP ${(game.rtp * 100).toFixed(2)}%` }) : null,
      ]),
    ]),
    cabinet,
    controls,
  ]);

  const layout = el('div', { class: 'slot' }, [stage, sidePanel]);
  applyTheme(layout, theme);
  mount(node, [layout]);

  updateSpinLabel();

  return {
    node,
    destroy() {
      destroyed = true;
      // Closing the session releases the engine's per-session state. It is
      // best-effort: a session that has already expired closes with a no-op.
      if (session) api.closeSession(session.id).catch(() => {});
    },
  };
}
