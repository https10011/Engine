// Coin Flip — the third table interface.
//
// The engine's example card game always calls heads: the module validates a
// `flip` action, draws one bit from the RNG and pays 2× when the call matches.
// This view therefore exposes one honest control ("Flip") and renders the
// `call`/`result` pair the server returns. It does not let the player pick a
// side, because the module offers no such action.

import { el, mount, money } from '../lib/dom.js';
import { api, ApiError } from '../lib/api.js';
import { toast, errorState, applyTheme } from '../lib/ui.js';
import * as store from '../lib/store.js';
import * as sound from '../lib/sound.js';
import { flashBalance, syncBalance } from '../lib/shell.js';

export async function renderCoinflip(context, presentation = null) {
  const gameId = context.route.id;
  let payload = presentation;

  if (!payload) {
    try {
      payload = await api.presentation(gameId);
    } catch (error) {
      return {
        node: errorState(
          error instanceof ApiError ? error.message : 'The game could not be loaded.',
          () => context.navigate('/'),
        ),
      };
    }
  }

  if (context.isStale()) return { node: el('div') };

  const game = payload.game;
  const theme = payload.theme || {};
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
  let busy = false;
  let destroyed = false;
  let flips = 0;

  const coin = el('div', { class: 'coin', 'aria-hidden': 'true' }, [
    el('div', { class: 'coin__face coin__face--heads' }, [
      el('span', { class: 'coin__mark', text: '♛' }),
      el('span', { class: 'coin__word', text: 'HEADS' }),
    ]),
    el('div', { class: 'coin__face coin__face--tails' }, [
      el('span', { class: 'coin__mark', text: '✦' }),
      el('span', { class: 'coin__word', text: 'TAILS' }),
    ]),
  ]);

  const status = el('p', { class: 'coin__status', text: 'The engine calls heads. Press flip to settle a round.', 'aria-live': 'polite' });
  const outcome = el('div', { class: 'coin__outcome', 'aria-live': 'polite' });
  const flipCount = el('span', { class: 'coin__count', text: 'No flips yet' });

  const betOutput = el('output', { class: 'mono', text: money(ladder[0], scale, currency) });
  const payoutOutput = el('span', { class: 'readout__value', text: '—' });

  const flipButton = el('button', {
    class: 'btn btn--gold btn--lg coin__button',
    type: 'button',
    onClick: () => flip(),
  }, 'Flip for heads');

  const historyList = el('div', { class: 'history-list' });
  const history = [];

  const rules = el('div', { class: 'rules' }, [
    el('ul', {}, [
      el('li', {}, [el('strong', { text: 'Call: ' }), 'heads (fixed by the module)']),
      el('li', {}, [el('strong', { text: 'Payout: ' }), '2× the stake when heads lands']),
      el('li', {}, [el('strong', { text: 'Reported RTP: ' }), `${(Number(game.rtp) * 100).toFixed(2)}%`]),
      el('li', {}, [el('strong', { text: 'Max win: ' }), `${game.max_win_multiplier}×`]),
      el('li', {}, [el('strong', { text: 'Bet limits: ' }), `${money(minBet, scale, currency)} – ${money(maxBet, scale, currency)}`]),
    ]),
    el('p', { class: 'muted', text: 'A single RNG draw settles each flip. This is the engine example module, not a provider table, and it plays with virtual credits only.' }),
  ]);

  const node = el('div', { class: 'fade-in' });

  const layout = el('div', { class: 'coin-page' }, [
    el('div', { class: 'game-head' }, [
      el('img', { class: 'game-head__art', src: `/assets/games/${game.id}.svg`, alt: '', width: '96', height: '96' }),
      el('div', { class: 'game-head__text' }, [
        el('p', { class: 'game-head__eyebrow', text: 'Table game · virtual credits' }),
        el('h1', { class: 'game-head__title', text: game.name }),
        el('p', { class: 'game-head__meta', text: `${game.provider} · ${(Number(game.rtp) * 100).toFixed(2)}% RTP` }),
      ]),
      el('span', { class: 'spacer' }),
      el('a', { class: 'btn btn--ghost btn--sm', href: '/', 'data-link': true }, 'Lobby'),
    ]),
    el('div', { class: 'coin-layout' }, [
      el('section', { class: 'coin-stage' }, [
        coin,
        outcome,
        status,
        flipCount,
      ]),
      el('aside', { class: 'coin-side' }, [
        el('div', { class: 'controls' }, [
          el('div', { class: 'controls__row' }, [
            el('div', { class: 'readout' }, [el('span', { class: 'readout__label', text: 'Stake' }), betOutput]),
            el('div', { class: 'stepper' }, [
              el('button', { type: 'button', 'aria-label': 'Decrease bet', onClick: () => changeBet(-1) }, '−'),
              el('button', { type: 'button', 'aria-label': 'Increase bet', onClick: () => changeBet(1) }, '+'),
            ]),
          ]),
          el('div', { class: 'controls__row' }, [
            el('div', { class: 'readout readout--win' }, [el('span', { class: 'readout__label', text: 'Payout' }), payoutOutput]),
            el('span', { class: 'spacer' }),
            flipButton,
          ]),
        ]),
        el('div', { class: 'panel' }, [
          el('div', { class: 'shelf__head' }, [el('h2', { class: 'shelf__title', text: 'This session' })]),
          historyList,
        ]),
      ]),
    ]),
    el('section', { class: 'panel coin-rules' }, [el('h2', { text: 'How a flip settles' }), rules]),
  ]);

  applyTheme(layout, theme);
  mount(node, [layout]);
  paintHistory();

  function changeBet(delta) {
    betIndex = Math.min(ladder.length - 1, Math.max(0, betIndex + delta));
    betOutput.textContent = money(ladder[betIndex], scale, currency);
    sound.sfx.click();
  }

  function paintHistory() {
    if (history.length === 0) {
      mount(historyList, [el('p', { class: 'muted', text: 'No flips yet in this session.' })]);
      return;
    }
    mount(historyList, history.map((row, index) => el('div', { class: 'history-list__row' }, [
      el('span', { text: `#${row.number ?? index + 1} ${row.result === 'heads' ? 'Heads' : 'Tails'}` }),
      el('span', { class: `net ${row.net > 0 ? 'net--up' : row.net < 0 ? 'net--down' : ''}`, text: money(row.net, scale, '') }),
    ])));
  }

  function cryptoId() {
    if (window.crypto?.randomUUID) return window.crypto.randomUUID();
    return `${Date.now()}-${Math.random().toString(16).slice(2)}`;
  }

  async function ensureSession() {
    if (session) return session;
    const data = await api.createSession(game.id);
    session = data.session;
    return session;
  }

  async function flip() {
    if (busy || destroyed) return;
    if (!store.getToken()) {
      toast('Sign in to play.');
      context.openLogin();
      return;
    }

    busy = true;
    flipButton.disabled = true;
    outcome.replaceChildren();
    coin.classList.remove('is-heads', 'is-tails');
    coin.classList.add('is-flipping');
    sound.sfx.click();

    const animation = new Promise((resolve) => setTimeout(resolve, 900));

    try {
      const active = await ensureSession();
      const data = await api.action(active.id, 'flip', {
        bet: ladder[betIndex],
        idempotencyKey: cryptoId(),
      });

      await animation;
      if (destroyed) return;

      const round = data.round;
      const detail = round.result?.data || {};
      const result = detail.result === 'tails' ? 'tails' : 'heads';
      const net = round.net?.amount ?? 0;
      const payout = round.payout?.amount ?? 0;

      flips += 1;
      coin.classList.remove('is-flipping');
      coin.classList.add(result === 'heads' ? 'is-heads' : 'is-tails');

      outcome.replaceChildren(el('strong', {
        class: result === 'heads' ? 'coin__result coin__result--win' : 'coin__result coin__result--lose',
        text: result === 'heads' ? 'Heads — you win' : 'Tails — the engine takes it',
      }));

      status.textContent = detail.call
        ? `The module called ${detail.call}; the coin landed ${result}.`
        : `The coin landed ${result}.`;
      flipCount.textContent = `${flips} flip${flips === 1 ? '' : 's'} this session`;
      payoutOutput.textContent = payout ? money(payout, scale, currency) : '—';

      history.unshift({
        number: round.round_number,
        result,
        net,
      });
      if (history.length > 20) history.pop();
      paintHistory();

      if (net > 0) sound.sfx.win(2);
      else sound.sfx.lose();
      flashBalance(net > 0 ? 'win' : net < 0 ? 'lose' : null);

      if (round.balance_after) store.setBalance(round.balance_after);
      else await syncBalance();
    } catch (error) {
      await animation;
      coin.classList.remove('is-flipping');
      toast(error instanceof ApiError ? error.message : 'The flip could not be settled.', 'error');
      sound.sfx.error();
    } finally {
      busy = false;
      flipButton.disabled = false;
    }
  }

  await ensureSession().catch(() => {
    // A missing session is reported when the player tries to flip; the page
    // still renders so the rules and bet limits are readable.
  });

  return {
    node,
    destroy() {
      destroyed = true;
      if (session) api.closeSession(session.id).catch(() => {});
    },
  };
}
