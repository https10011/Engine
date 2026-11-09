// The lobby and the game catalogue.
//
// One view serves the full lobby and the three category routes; the only
// difference is which filter starts active. Every card is built from
// `/api/v1/games/lobby`, which is public, so the floor renders before a player
// signs in. Opening a game needs an account and the view says so instead of
// silently failing.
//
// Nothing here invents a number: RTP, max win and bet limits are the engine's
// metadata, and the "max win" shelf is sorted by the multiplier the module
// reports.

import { el, mount, providerLabel } from '../lib/dom.js';
import { icon } from '../lib/icons.js';
import { gameArt } from '../lib/art.js';
import { api, ApiError } from '../lib/api.js';
import { toast, errorState, emptyState } from '../lib/ui.js';
import * as store from '../lib/store.js';
import * as sound from '../lib/sound.js';

const RECENT_KEY = 'aurum.recent';
const RECENT_LIMIT = 8;

const CATEGORY_LABEL = { slot: 'Slots', card: 'Table games', roulette: 'Roulette' };

// Artwork for the carousel is cropped from the original AH77 promotional
// banners; only the character art is used, with the lobby supplying its own
// copy. No promotional deposit claims travel with the images.
const SLIDES = [
  {
    kicker: 'Welcome to Aurum',
    title: 'Your table is ready. <em>Pull up a chair.</em>',
    sub: 'Slots, tables and roulette, all in one place.',
    cta: 'Explore the games',
    href: '/slots',
    art: '/assets/banners/feature-super-ace.webp',
  },
  {
    kicker: 'New players',
    title: 'Start with <em>300 credits</em>.',
    sub: 'Create your account in a minute and take a seat.',
    cta: 'Create an account',
    action: 'open-register',
    art: '/assets/banners/feature-gifts.webp',
  },
  {
    kicker: 'Private lobby',
    title: 'Good games, <em>good company.</em>',
    sub: 'A relaxed lobby built for you and your friends.',
    cta: 'See your play',
    href: '/history',
    requiresAuth: true,
    art: '/assets/banners/feature-roulette.webp',
  },
];

const PATHS = { all: '/', slot: '/slots', card: '/tables', roulette: '/roulette' };

export async function renderLobby(context) {
  const locked = context.lockedCategory || null;
  let filter = locked || 'all';
  let query = '';
  let games = [];
  let timer = null;
  let slide = 0;
  let destroyed = false;

  const node = el('div', { class: 'fade-in' });
  const banner = buildBanner();
  const search = buildSearchbar();
  const cats = el('div', { class: 'cat-rail', role: 'group', 'aria-label': 'Game categories' });
  const results = el('div');

  mount(node, [banner.node, search.bar, cats, results]);

  /* ------------------------------------------------------------- categories */

  function paintCategories() {
    const counts = { all: games.length };
    for (const g of games) counts[g.game.category] = (counts[g.game.category] || 0) + 1;

    const options = [
      { key: 'all', label: 'All games', icon: 'grid' },
      { key: 'slot', label: 'Slots', icon: 'slots' },
      { key: 'card', label: 'Tables', icon: 'cards' },
      { key: 'roulette', label: 'Roulette', icon: 'wheel' },
    ];

    mount(cats, options.map((option) => el('button', {
      class: 'cat',
      type: 'button',
      'aria-pressed': filter === option.key ? 'true' : 'false',
      onClick: () => {
        if (locked && option.key !== locked) {
          context.navigate(PATHS[option.key]);
          return;
        }
        filter = option.key;
        paintCategories();
        paintResults();
      },
    }, [
      el('span', { class: 'cat__icon' }, [icon(option.icon)]),
      el('span', { text: option.label }),
      el('span', { class: 'cat__count', text: String(counts[option.key] ?? 0) }),
    ])));
  }

  /* ---------------------------------------------------------------- results */

  function paintResults() {
    if (query) {
      const needle = query.toLowerCase();
      const matches = games.filter(({ game }) => {
        const haystack = `${game.name} ${game.provider} ${(game.tags || []).join(' ')} ${game.category}`.toLowerCase();
        return haystack.includes(needle);
      }).filter(({ game }) => filter === 'all' || game.category === filter);

      if (matches.length === 0) {
        mount(results, [emptyState(
          'No games match',
          `Nothing matches “${query}”${filter === 'all' ? '' : ` in ${CATEGORY_LABEL[filter] || filter}`}.`,
          el('button', { class: 'btn btn--ghost', type: 'button', onClick: () => { search.input.value = ''; query = ''; paintResults(); } }, 'Clear search'),
        )]);
        return;
      }

      mount(results, [
        el('p', { class: 'results-meta' }, [el('strong', { text: String(matches.length) }), ' games found']),
        el('div', { class: 'tiles' }, matches.map(tile)),
      ]);
      return;
    }

    if (filter !== 'all') {
      const filtered = games.filter(({ game }) => game.category === filter);
      mount(results, [
        el('div', { class: 'section' }, [
          el('div', { class: 'shelf__head' }, [
            el('h2', { class: 'shelf__title', text: CATEGORY_LABEL[filter] || filter }),
            el('span', { class: 'shelf__badge', text: `${filtered.length} games` }),
          ]),
          filtered.length
            ? el('div', { class: 'tiles' }, filtered.map(tile))
            : emptyState('Nothing here yet', 'This category has no enabled games.'),
        ]),
        trustStrip(),
      ]);
      return;
    }

    const topWin = [...games]
      .sort((a, b) => (b.game.max_win_multiplier || 0) - (a.game.max_win_multiplier || 0))
      .slice(0, 10);
    const slots = games.filter(({ game }) => game.category === 'slot');
    const tables = games.filter(({ game }) => game.category !== 'slot');
    const recent = recentGames(games);

    mount(results, [
      recent.length ? shelf('Continue playing', 'Your last few titles on this device', recent) : null,
      shelf('Biggest max win', 'The biggest wins on the floor', topWin),
      shelf('Slot games', `${slots.length} slots to choose from`, slots),
      shelf('Tables & roulette', `${tables.length} games at the tables`, tables),
      el('div', { class: 'section' }, [
        el('div', { class: 'shelf__head' }, [
          el('h2', { class: 'shelf__title', text: 'All games' }),
          el('span', { class: 'shelf__badge', text: `${games.length}` }),
        ]),
        el('div', { class: 'tiles' }, games.map(tile)),
      ]),
      trustStrip(),
    ]);
  }

  function shelf(title, note, entries, href = null) {
    if (entries.length === 0) return null;
    return el('div', { class: 'section shelf' }, [
      el('div', { class: 'shelf__head' }, [
        el('h2', { class: 'shelf__title', text: title }),
        el('span', { class: 'shelf__note', text: note }),
        href ? el('a', { class: 'shelf__more', href, 'data-link': true, text: 'See all' }) : null,
      ]),
      el('div', { class: 'shelf__rail' }, entries.map(tile)),
    ]);
  }

  function tile(entry) {
    const { game } = entry;
    const hot = Number(game.max_win_multiplier) >= 1000;
    return el('button', {
      class: game.category === 'slot' ? 'tile' : 'tile tile--table',
      type: 'button',
      'aria-label': `Play ${game.name} — ${(Number(game.rtp) * 100).toFixed(2)}% RTP`,
      onClick: () => open(game),
    }, [
      el('span', { class: 'tile__art' }, [
        el('img', { src: gameArt(game.id), alt: '', loading: 'lazy', width: '300', height: '300' }),
        hot ? el('span', { class: 'tile__hot', text: 'HOT' }) : null,
        el('span', { class: 'tile__rtp', text: `${(Number(game.rtp) * 100).toFixed(1)}%` }),
      ]),
      el('span', { class: 'tile__label', text: game.name }),
      el('span', { class: 'tile__sub', text: providerLabel(game.provider) }),
    ]);
  }

  function trustStrip() {
    const counts = games.reduce((acc, { game }) => {
      acc[game.category] = (acc[game.category] || 0) + 1;
      return acc;
    }, {});

    return el('div', { class: 'trust' }, [
      trust('shield', 'Settled instantly', 'Every round is handled the moment you play.'),
      trust('lock', 'Your account, your history', 'Your balance and past rounds stay with you.'),
      trust('bolt', `${games.length} games, one balance`, `${counts.slot || 0} slots, ${counts.card || 0} card games and ${counts.roulette || 0} roulette titles.`),
    ]);
  }

  function trust(name, title, body) {
    return el('div', { class: 'trust__item' }, [
      el('span', { class: 'trust__icon' }, [icon(name)]),
      el('div', {}, [el('strong', { text: title }), el('span', { text: body })]),
    ]);
  }

  /* ----------------------------------------------------------------- open */

  function open(game) {
    sound.sfx.click();

    if (!store.getToken()) {
      toast('Sign in to play — the lobby is public, the games need an account.');
      context.openLogin();
      return;
    }

    rememberRecent(game.id);
    context.navigate(game.category === 'slot'
      ? `/slots/${encodeURIComponent(game.id)}`
      : `/tables/${encodeURIComponent(game.id)}`);
  }

  /* ---------------------------------------------------------------- banner */

  function buildBanner() {
    const track = el('div', { class: 'banner__track' });
    const dots = el('div', { class: 'banner__dots', role: 'tablist', 'aria-label': 'Promotions' });

    const slides = SLIDES.map((item, index) => el('div', {
      class: 'banner__slide',
      role: 'group',
      'aria-roledescription': 'slide',
      'aria-label': `${index + 1} of ${SLIDES.length}`,
    }, [
      el('img', { class: 'banner__art', src: item.art, alt: '', fetchpriority: index === 0 ? 'high' : 'low' }),
      el('div', { class: 'banner__copy' }, [
        el('span', { class: 'banner__kicker', text: item.kicker }),
        el('h2', { class: 'banner__title', html: item.title }),
        el('p', { class: 'banner__sub', text: item.sub }),
        item.action
          ? el('button', { class: 'banner__cta', type: 'button', 'data-action': item.action }, [item.cta, icon('arrow-right')])
          : el('a', {
            class: 'banner__cta',
            href: item.href,
            'data-link': true,
            ...(item.requiresAuth ? { 'data-requires-auth': true } : {}),
          }, [item.cta, icon('arrow-right')]),
      ]),
    ]));

    mount(track, slides);

    const dotButtons = SLIDES.map((item, index) => el('button', {
      class: 'banner__dot',
      type: 'button',
      'aria-label': `Show promotion ${index + 1}`,
      'aria-current': index === 0 ? 'true' : 'false',
      onClick: () => go(index),
    }));
    mount(dots, dotButtons);

    const prev = el('button', { class: 'banner__arrow banner__arrow--prev', type: 'button', 'aria-label': 'Previous promotion', onClick: () => go(slide - 1) }, [icon('chevron-left')]);
    const next = el('button', { class: 'banner__arrow banner__arrow--next', type: 'button', 'aria-label': 'Next promotion', onClick: () => go(slide + 1) }, [icon('chevron-right')]);

    const section = el('section', {
      class: 'banner',
      'aria-roledescription': 'carousel',
      'aria-label': 'Promotions and how the casino works',
    }, [track, prev, next, dots]);

    // Touch swipe, because the reference is a mobile-first page.
    let touchX = null;
    section.addEventListener('touchstart', (event) => { touchX = event.touches[0].clientX; }, { passive: true });
    section.addEventListener('touchend', (event) => {
      if (touchX === null) return;
      const delta = event.changedTouches[0].clientX - touchX;
      if (Math.abs(delta) > 45) go(delta < 0 ? slide + 1 : slide - 1);
      touchX = null;
    }, { passive: true });

    section.addEventListener('mouseenter', stop);
    section.addEventListener('mouseleave', start);
    section.addEventListener('focusin', stop);
    section.addEventListener('focusout', start);

    function go(index) {
      slide = (index + SLIDES.length) % SLIDES.length;
      slides.forEach((node, i) => node.classList.toggle('is-active', i === slide));
      dotButtons.forEach((dot, i) => dot.setAttribute('aria-current', i === slide ? 'true' : 'false'));
      stop();
      start();
    }

    function start() {
      stop();
      if (window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) return;
      timer = window.setInterval(() => go(slide + 1), 6500);
    }

    function stop() {
      if (timer) {
        window.clearInterval(timer);
        timer = null;
      }
    }

    slides[0].classList.add('is-active');
    start();

    return { node: section, stop };
  }

  /* ---------------------------------------------------------------- search */

  function buildSearchbar() {
    const input = el('input', {
      type: 'search',
      placeholder: 'Search games, providers or tags',
      'aria-label': 'Search games, providers or tags',
      oninput: (event) => { query = event.target.value.trim(); paintResults(); },
    });

    const bar = el('div', { class: 'lobby-toolbar' }, [
      el('label', { class: 'searchbar' }, [icon('search'), input]),
    ]);

    return { bar, input };
  }

  /* ----------------------------------------------------------------- load */

  async function load() {
    try {
      const data = await api.lobby();
      if (context.isStale() || destroyed) return;
      games = data.games || [];
      paintCategories();
      paintResults();
    } catch (error) {
      if (context.isStale() || destroyed) return;
      mount(results, [errorState(
        error instanceof ApiError ? error.message : 'The catalogue could not be loaded.',
        load,
      )]);
    }
  }

  await load();

  return {
    node,
    destroy() {
      destroyed = true;
      if (timer) window.clearInterval(timer);
      banner.stop?.();
    },
  };
}

/* ------------------------------------------------------------------ recent */

function rememberRecent(gameId) {
  try {
    const previous = JSON.parse(localStorage.getItem(RECENT_KEY) || '[]');
    const next = [gameId, ...previous.filter((id) => id !== gameId)].slice(0, RECENT_LIMIT);
    localStorage.setItem(RECENT_KEY, JSON.stringify(next));
  } catch {
    // Storage may be unavailable; recent games are a convenience only.
  }
}

function recentGames(games) {
  if (!store.getToken()) return [];
  try {
    const ids = JSON.parse(localStorage.getItem(RECENT_KEY) || '[]');
    if (!Array.isArray(ids) || ids.length === 0) return [];
    const index = new Map(games.map((entry) => [entry.game.id, entry]));
    return ids.map((id) => index.get(id)).filter(Boolean);
  } catch {
    return [];
  }
}

