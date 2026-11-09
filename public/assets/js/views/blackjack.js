// The blackjack table.
//
// The engine owns every rule: the shoe, the deal, the dealer's play, the
// settlement and the payouts. This view sends `deal`, `hit`, `stand`, `double`,
// `split`, `insurance` and `surrender`, then renders the state the server
// returned. The hole card is hidden here only because the state says the hand
// is not finished — the moment the round completes the same field carries the
// real card, so nothing is inferred client-side.
//
// The available actions are derived from the state's `phase`, which is why the
// buttons match the table rules the session was created with rather than a
// hardcoded list.

import { el, mount, money, ratio, providerLabel } from '../lib/dom.js';
import { gameArt } from '../lib/art.js';
import { api, ApiError } from '../lib/api.js';
import { toast, errorState, applyTheme } from '../lib/ui.js';
import * as store from '../lib/store.js';
import * as sound from '../lib/sound.js';
import { flashBalance, syncBalance } from '../lib/shell.js';

const SUITS = { S: '♠', H: '♥', D: '♦', C: '♣' };
const RED = new Set(['H', 'D']);

function cardNode(code, { hidden = false, index = 0 } = {}) {
  if (hidden) {
    return el('div', {
      class: 'card card--back',
      style: { '--i': String(index) },
      'aria-label': 'Face-down card',
    }, [el('span', { class: 'card__back-mark', text: '♠', 'aria-hidden': 'true' })]);
  }

  const match = /^(10|[2-9AJQK])([SHDC])$/.exec(code || '');
  const rank = match ? match[1] : '?';
  const suit = match ? match[2] : 'S';

  return el('div', {
    class: `card ${RED.has(suit) ? 'card--red' : ''}`,
    style: { '--i': String(index) },
    'aria-label': `${rank} of ${suit}`,
  }, [
    el('span', { class: 'card__rank', text: rank }),
    el('span', { class: 'card__suit', text: SUITS[suit] || '', 'aria-hidden': 'true' }),
  ]);
}

const RESULT_LABEL = {
  player_blackjack: 'Blackjack!',
  player_win: 'You win',
  dealer_blackjack: 'Dealer blackjack',
  dealer_win: 'Dealer wins',
  push: 'Push',
  player_bust: 'Bust',
  dealer_bust: 'Dealer busts — you win',
  surrender: 'Surrendered',
};

export async function renderBlackjack(context) {
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
  const minBet = game.min_bet.amount;
  const maxBet = game.max_bet.amount;
  const step = Math.max(1, game.bet_step.amount);

  const ladder = [];
  for (let amount = minBet; amount <= maxBet && ladder.length < 40; amount += step) ladder.push(amount);
  if (ladder.length === 0) ladder.push(minBet);

  let betIndex = 0;
  let session = null;
  let state = null;
  let busy = false;
  let destroyed = false;

  const dealerCards = el('div', { class: 'hand__cards', 'aria-label': 'Dealer hand' });
  const dealerTotal = el('span', { class: 'hand__total', text: '—' });
  const playerHands = el('div', { class: 'hands' });
  const status = el('p', { class: 'table__status', text: 'Place a bet and deal.', 'aria-live': 'polite' });
  const payoutReadout = el('span', { class: 'readout__value', text: '—' });

  const betOutput = el('output', { class: 'mono', text: money(ladder[0], scale, currency) });
  const actions = el('div', { class: 'table__actions' });

  const node = el('div', { class: 'fade-in' });

  /* -------------------------------------------------------------- rendering */

  function phase() {
    return state?.phase || (state?.event ? state.event : null);
  }

  function renderDealer() {
    const complete = state?.phase === 'complete' || state?.finished === true;
    const cards = state?.dealer || [];
    const hidden = !complete && cards.length > 1;

    mount(dealerCards, cards.map((code, index) =>
      cardNode(code, { hidden: hidden && index === 1, index })
    ));

    const total = state?.dealer_total ?? 0;
    dealerTotal.textContent = cards.length === 0 ? '—' : (hidden ? `${cards.length} cards` : String(total));
  }

  function renderHands() {
    const hands = state?.hands || [];
    const results = state?.results || [];

    if (hands.length === 0) {
      mount(playerHands, [el('p', { class: 'muted', text: 'No hand in play.' })]);
      return;
    }

    mount(playerHands, hands.map((cards, index) => {
      const stake = state?.stakes?.[index] ?? 0;
      const result = results[index];
      const active = state?.active_hand === index && state?.phase === 'player';

      return el('div', { class: `hand ${active ? 'hand--active' : ''}`, 'aria-label': `Hand ${index + 1}` }, [
        el('div', { class: 'hand__head' }, [
          el('span', { class: 'hand__label', text: hands.length > 1 ? `Hand ${index + 1}` : 'Your hand' }),
          el('span', { class: 'hand__meta mono', text: money(stake, scale, '') }),
          state?.doubled?.[index] ? el('span', { class: 'tag tag--info', text: 'Doubled' }) : null,
          state?.from_split?.[index] ? el('span', { class: 'tag', text: 'Split' }) : null,
        ]),
        el('div', { class: 'hand__cards' }, cards.map((code, i) => cardNode(code, { index: i }))),
        el('div', { class: 'hand__foot' }, [
          el('span', { class: 'hand__total', text: `Total ${handTotal(cards)}` }),
          result ? el('span', {
            class: `tag ${result.player_wins || result.result === 'dealer_bust' ? 'tag--win' : result.result === 'push' ? 'tag--info' : 'tag--lose'}`,
            text: RESULT_LABEL[result.result] || result.result,
          }) : null,
        ]),
      ]);
    }));
  }

  function handTotal(cards) {
    let total = 0;
    let aces = 0;
    for (const code of cards) {
      const rank = (code || '').slice(0, -1);
      if (rank === 'A') { aces += 1; total += 11; }
      else if (['K', 'Q', 'J'].includes(rank)) total += 10;
      else total += Number(rank) || 0;
    }
    while (total > 21 && aces > 0) { total -= 10; aces -= 1; }
    return total;
  }

  function renderActions() {
    const p = state?.phase;
    const inProgress = p === 'player' || p === 'insurance';
    const available = [];

    if (!inProgress) {
      available.push(action('Deal', 'deal', 'primary', { bet: ladder[betIndex] }));
      if (state && (state.phase === 'complete')) {
        available.push(action('Repeat bet', 'deal', 'ghost', { bet: ladder[betIndex] }));
      }
    } else if (p === 'insurance') {
      available.push(action('Take insurance', 'insurance', 'primary', { payload: { take: true }, bet: insuranceStake() }));
      available.push(action('Decline', 'insurance', 'ghost', { payload: { take: false } }));
    } else {
      const index = state?.active_hand ?? 0;
      const hand = state?.hands?.[index] || [];
      const stake = state?.stakes?.[index] ?? ladder[betIndex];
      const isSplitHand = Boolean(state?.from_split?.[index]);

      available.push(action('Hit', 'hit', 'primary'));
      available.push(action('Stand', 'stand', 'ghost'));

      const canDouble = hand.length === 2 && (!isSplitHand || rules.double_after_split);
      if (canDouble) available.push(action('Double', 'double', 'ghost', { bet: stake }));

      const pair = hand.length === 2 && rankOf(hand[0]) === rankOf(hand[1]);
      if (pair && state?.hands?.length < 4) {
        available.push(action('Split', 'split', 'ghost', { bet: stake }));
      }

      if (rules.surrender && !isSplitHand && hand.length === 2 && state?.hands?.length === 1) {
        available.push(action('Surrender', 'surrender', 'danger'));
      }
    }

    mount(actions, available);
  }

  function rankOf(code) {
    return (code || '').slice(0, -1);
  }

  function insuranceStake() {
    const stake = state?.stakes?.[0] ?? ladder[betIndex];
    return Math.floor(stake / 2);
  }

  function action(label, name, kind, extra = {}) {
    return el('button', {
      class: `btn btn--${kind}`,
      type: 'button',
      text: label,
      onClick: () => send(name, extra),
    });
  }

  function renderAll() {
    renderDealer();
    renderHands();
    renderActions();
    paintRules();
  }

  /* ------------------------------------------------------------------- play */

  async function ensureSession() {
    if (session) return session;
    const data = await api.createSession(game.id);
    session = data.session;
    state = data.session.state || null;
    renderAll();
    return session;
  }

  async function send(name, extra = {}) {
    if (busy || destroyed) return;
    if (!store.getToken()) { toast('Sign in to play.'); return; }

    busy = true;
    actions.querySelectorAll('button').forEach((b) => { b.setAttribute('disabled', ''); });
    if (name === 'deal') sound.sfx.card();

    try {
      const active = await ensureSession();
      const data = await api.action(active.id, name, {
        bet: extra.bet,
        payload: extra.payload,
        idempotencyKey: cryptoId(),
      });

      if (destroyed) return;
      const round = data.round;
      state = round.state || state;
      // The detail carries the settled hands and the dealer's full hand.
      if (round.result?.data) state = { ...state, ...round.result.data };

      renderAll();

      const net = round.net?.amount ?? 0;
      const payout = round.payout?.amount ?? 0;
      payoutReadout.textContent = payout ? money(payout, scale, currency) : '—';

      if (state.phase === 'complete') {
        const results = state.results || [];
        const won = results.some((r) => r && ['player_blackjack', 'player_win', 'dealer_bust'].includes(r.result));
        status.textContent = won
          ? `You won ${money(payout, scale, currency)}.`
          : results.some((r) => r && r.result === 'push')
            ? 'Push — your stake is returned.'
            : 'The house takes this one.';
        if (won) sound.sfx.win(2);
        else if (results.some((r) => r && r.result === 'push')) sound.sfx.chip();
        else sound.sfx.lose();
      } else if (state.phase === 'insurance') {
        status.textContent = 'The dealer shows an ace. Insurance?';
        sound.sfx.chip();
      } else {
        status.textContent = 'Your move.';
        sound.sfx.card();
      }

      flashBalance(net > 0 ? 'win' : net < 0 ? 'lose' : null);
      if (round.balance_after) store.setBalance(round.balance_after);
      else await syncBalance();
    } catch (error) {
      if (!destroyed) {
        sound.sfx.error();
        toast(error instanceof ApiError ? error.message : 'The action failed.', 'error');
      }
    } finally {
      busy = false;
      if (!destroyed) renderActions();
    }
  }

  function cryptoId() {
    if (window.crypto?.randomUUID) return window.crypto.randomUUID();
    return `${Date.now()}-${Math.random().toString(16).slice(2)}`;
  }

  /* -------------------------------------------------------------- controls */

  const betStepper = el('div', { class: 'stepper' }, [
    el('button', { type: 'button', 'aria-label': 'Decrease bet', onClick: () => changeBet(-1) }, '−'),
    betOutput,
    el('button', { type: 'button', 'aria-label': 'Increase bet', onClick: () => changeBet(1) }, '+'),
  ]);

  function changeBet(delta) {
    betIndex = Math.min(ladder.length - 1, Math.max(0, betIndex + delta));
    betOutput.textContent = money(ladder[betIndex], scale, currency);
    sound.sfx.click();
  }

  const rulesPanel = el('div', { class: 'rules' });
  function paintRules() {
    mount(rulesPanel, [
      el('ul', {}, [
        el('li', {}, [el('strong', { text: 'Decks: ' }), String(rules.decks ?? '—')]),
        el('li', {}, [el('strong', { text: 'Dealer: ' }), rules.dealer_hits_soft_17 ? 'hits soft 17' : 'stands on soft 17']),
        el('li', {}, [el('strong', { text: 'Blackjack pays: ' }), `${ratio(rules.blackjack_payout) || '3/2'}`]),
        el('li', {}, [el('strong', { text: 'Double after split: ' }), rules.double_after_split ? 'allowed' : 'not allowed']),
        el('li', {}, [el('strong', { text: 'Resplit aces: ' }), rules.resplit_aces ? 'allowed' : 'not allowed']),
        el('li', {}, [el('strong', { text: 'Surrender: ' }), rules.surrender ? 'available' : 'not available']),
        el('li', {}, [el('strong', { text: 'Insurance: ' }), rules.insurance ? 'offered on an ace' : 'not offered']),
      ]),
      el('p', { class: 'muted', text: 'Take a seat, place your bet and play your hand against the dealer.' }),
    ]);
  }

  /* -------------------------------------------------------------- assembly */

  const layout = el('div', { class: 'table table--blackjack' }, [
    el('div', { class: 'slot__head' }, [
      el('img', { class: 'slot__art', src: gameArt(game.id), alt: '', width: '46', height: '46' }),
      el('div', { class: 'slot__titles' }, [
        el('span', { class: 'slot__name', text: game.name }),
        el('span', { class: 'slot__provider', text: providerLabel(game.provider) }),
      ]),
      el('span', { class: 'spacer' }),
      el('a', { class: 'btn btn--ghost', href: '/', 'data-link': true }, 'Lobby'),
    ]),
    el('div', { class: 'table__felt' }, [
      el('div', { class: 'hand hand--dealer' }, [
        el('div', { class: 'hand__head' }, [
          el('span', { class: 'hand__label', text: 'Dealer' }),
          dealerTotal,
        ]),
        dealerCards,
      ]),
      playerHands,
    ]),
    el('div', { class: 'controls' }, [
      el('div', { class: 'controls__row' }, [
        el('div', { class: 'readout' }, [el('span', { class: 'readout__label', text: 'Bet' }), betOutput.cloneNode(true)]),
        betStepper,
        el('span', { class: 'spacer' }),
        el('div', { class: 'readout readout--win' }, [el('span', { class: 'readout__label', text: 'Payout' }), payoutReadout]),
      ]),
      actions,
      status,
    ]),
    el('aside', { class: 'slot__side' }, [el('div', { class: 'panel' }, [el('h2', { text: 'Table rules' }), rulesPanel])]),
  ]);

  applyTheme(layout, theme);
  mount(node, [layout]);

  await ensureSession();
  renderAll();

  return {
    node,
    destroy() {
      destroyed = true;
      if (session) api.closeSession(session.id).catch(() => {});
    },
  };
}
