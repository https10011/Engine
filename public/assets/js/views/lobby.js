// The lobby and the game catalogue.
//
// One view serves three routes — the full lobby, the slot category and the
// table category — because they are the same catalogue with a different filter.
// Cards are built from `/api/v1/games/lobby`, so the lobby renders before a
// player signs in.

import { el, frag, mount, money, percent } from '../lib/dom.js';
import { api, ApiError } from '../lib/api.js';
import { toast, errorState, emptyState, applyTheme } from '../lib/ui.js';
import * as store from '../lib/store.js';
import * as sound from '../lib/sound.js';

const CATEGORIES = [
  { value: '', label: 'All games' },
  { value: 'slot', label: 'Slots' },
  { value: 'card', label: 'Card tables' },
  { value: 'roulette', label: 'Roulette' },
];

export async function renderLobby(context) {
  const locked = context.lockedCategory || null;
  let category = locked || '';
  let query = '';

  const node = el('div', { class: 'fade-in' });
  const toolbar = el('div', { class: 'toolbar' });
  const chips = el('div', { class: 'chips', role: 'group', 'aria-label': 'Game categories' });
  const search = el('input', {
    class: 'search',
    type: 'search',
    placeholder: 'Search games…',
    'aria-label': 'Search games by name or provider',
    oninput: (event) => { query = event.target.value.trim().toLowerCase(); paint(); },
  });

  if (!locked) {
    for (const option of CATEGORIES) {
      const chip = el('button', {
        class: 'chip',
        type: 'button',
        text: option.label,
        'aria-pressed': option.value === category ? 'true' : 'false',
        onClick: () => {
          category = option.value;
          chips.querySelectorAll('.chip').forEach((c) => c.setAttribute('aria-pressed', 'false'));
          chip.setAttribute('aria-pressed', 'true');
          load();
        },
      });
      chips.append(chip);
    }
    toolbar.append(chips, el('span', { class: 'spacer' }), search);
  }

  const hero = el('section', { class: 'hero' }, [
    el('h1', { text: locked === 'slot' ? 'Slot games' : locked === 'card' ? 'Table games' : 'The floor' }),
    el('p', {
      text: locked === 'slot'
        ? 'Sixteen titles, each with its own symbols, paytable and feature set. Outcomes are produced by the engine, never by this page.'
        : locked === 'card'
          ? 'Blackjack and roulette, played against the engine with configurable table rules.'
          : 'Sixteen slots and two table games, all settled by the server. Pick a title to open its own interface.',
    }),
  ]);

  const results = el('div', { class: 'results' });

  mount(node, [hero, toolbar, results]);

  let games = [];

  function paint() {
    const filtered = query
      ? games.filter((card) => {
          const haystack = `${card.game.name} ${card.game.provider} ${(card.game.tags || []).join(' ')}`.toLowerCase();
          return haystack.includes(query);
        })
      : games;

    if (filtered.length === 0) {
      mount(results, [emptyState(
        'No games match',
        query ? `Nothing matches “${query}”.` : 'This category has no games yet.',
        el('button', { class: 'btn', type: 'button', onClick: () => { search.value = ''; query = ''; paint(); } }, 'Clear search')
      )]);
      return;
    }

    mount(results, [el('div', { class: 'grid' }, filtered.map(card))]);
  }

  function card(entry) {
    const { game, theme } = entry;
    const node = el('button', {
      class: 'card',
      type: 'button',
      'aria-label': `Play ${game.name}`,
      onClick: () => open(game, theme),
    });

    applyTheme(node, theme);

    mount(node, [
      el('div', { class: 'card__art' }, [
        el('span', { class: 'card__category', text: game.category }),
        el('span', { class: 'card__emblem', text: theme.emblem || '◆', 'aria-hidden': 'true' }),
      ]),
      el('div', { class: 'card__body' }, [
        el('strong', { class: 'card__title', text: game.name }),
        el('span', { class: 'card__pitch', text: theme.pitch || '' }),
        el('div', { class: 'card__stats' }, [
          el('span', { class: 'card__stat' }, ['RTP ', el('strong', { text: percent(game.rtp) })]),
          el('span', { class: 'card__stat' }, ['Max ', el('strong', { text: `${game.max_win_multiplier}×` })]),
          el('span', { class: 'card__stat' }, ['From ', el('strong', { text: money(game.min_bet.amount, game.min_bet.scale, '') })]),
        ]),
      ]),
    ]);

    return node;
  }

  async function open(game, theme) {
    sound.sfx.click();

    if (!store.getToken()) {
      toast('Sign in to play — the lobby is public, the games need an account.');
      document.querySelector('[data-action="open-login"]')?.click();
      return;
    }

    if (game.category === 'slot') {
      context.navigate(`/slots/${encodeURIComponent(game.id)}`);
    } else if (game.category === 'roulette') {
      context.navigate(`/tables/${encodeURIComponent(game.id)}`);
    } else {
      context.navigate(`/tables/${encodeURIComponent(game.id)}`);
    }
  }

  async function load() {
    mount(results, [el('div', { class: 'grid' }, Array.from({ length: 8 }, () =>
      el('div', { class: 'skeleton skeleton--card' })
    ))]);

    try {
      const data = await api.lobby(category || undefined);
      if (context.isStale()) return;
      games = data.games || [];
      paint();
    } catch (error) {
      if (context.isStale()) return;
      mount(results, [errorState(
        error instanceof ApiError ? error.message : 'The catalogue could not be loaded.',
        load
      )]);
    }
  }

  await load();

  return { node };
}
