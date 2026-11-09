// Application shell: routing, authentication, navigation and the global dialogs.
//
// The router is a small history router over the view modules. It owns four
// things and nothing else: which view is mounted, who is signed in, which
// global panel is open, and the header's balance. No view decides an outcome —
// a view calls the API and renders what comes back.

import { el, mount, clear, providerLabel } from './lib/dom.js';
import { gameArt } from './lib/art.js';
import { decorateIcons } from './lib/icons.js';
import { api, ApiError } from './lib/api.js';
import * as store from './lib/store.js';
import { toast, errorState, loadingState } from './lib/ui.js';
import { refreshHeader, syncBalance } from './lib/shell.js';
import * as sound from './lib/sound.js';

import { renderLobby } from './views/lobby.js';
import { renderSlot } from './views/slot.js';
import { renderTable } from './views/table.js';
import { renderHistory } from './views/history.js';

const main = document.querySelector('main[data-view]');
let currentView = null;
let renderToken = 0;
let cataloguePromise = null;
let searchGames = null;

/* ------------------------------------------------------------------- routes */

function parseRoute() {
  const path = window.location.pathname.replace(/\/+$/, '') || '/';
  const parts = path.split('/').filter(Boolean);

  if (parts.length === 0) return { name: 'lobby' };
  if (parts[0] === 'slots') return parts[1] ? { name: 'slot', id: decodeURIComponent(parts[1]) } : { name: 'slots' };
  if (parts[0] === 'tables') {
    if (!parts[1]) return { name: 'tables' };
    return { name: 'table', id: decodeURIComponent(parts[1]) };
  }
  if (parts[0] === 'roulette') {
    if (!parts[1]) return { name: 'roulette' };
    return { name: 'table', id: decodeURIComponent(parts[1]) };
  }
  if (parts[0] === 'game' && parts[1]) return { name: 'game', id: decodeURIComponent(parts[1]) };
  if (parts[0] === 'history') return { name: 'history' };
  return { name: 'notfound', path };
}

function navKey(route) {
  switch (route.name) {
    case 'lobby': return 'lobby';
    case 'slots':
    case 'slot': return 'slots';
    case 'tables':
    case 'table': return 'tables';
    case 'roulette': return 'roulette';
    case 'history': return 'history';
    default: return null;
  }
}

function titleFor(route) {
  const titles = {
    lobby: 'AURUM — Casino',
    slots: 'Slots · AURUM',
    slot: 'Slots · AURUM',
    tables: 'Table games · AURUM',
    table: 'Table game · AURUM',
    roulette: 'Roulette · AURUM',
    history: 'My play · AURUM',
    notfound: 'Not found · AURUM',
  };
  return titles[route.name] || 'AURUM';
}

/* ------------------------------------------------------------------- render */

async function render() {
  const route = parseRoute();
  const token = ++renderToken;

  document.title = titleFor(route);

  const key = navKey(route);
  document.querySelectorAll('[data-nav]').forEach((link) => {
    if (link.dataset.nav === key) link.setAttribute('aria-current', 'page');
    else link.removeAttribute('aria-current');
  });

  document.querySelectorAll('[data-auth-tab]').forEach((tab) => {
    if (key === 'history') tab.setAttribute('aria-current', 'page');
    else tab.removeAttribute('aria-current');
  });

  if (currentView && typeof currentView.destroy === 'function') {
    try { currentView.destroy(); } catch { /* a view's teardown must never break navigation */ }
  }
  currentView = null;
  clear(main);

  if (route.name === 'history' && !store.getToken()) {
    mount(main, signInPrompt(route.name));
    return;
  }

  const context = {
    route,
    main,
    navigate,
    isStale: () => token !== renderToken,
    openLogin,
    openRegister,
    getCatalogue: ensureCatalogue,
  };

  const loaders = {
    lobby: renderLobby,
    slots: (ctx) => renderLobby({ ...ctx, lockedCategory: 'slot' }),
    tables: (ctx) => renderLobby({ ...ctx, lockedCategory: 'card' }),
    roulette: (ctx) => renderLobby({ ...ctx, lockedCategory: 'roulette' }),
    slot: renderSlot,
    table: renderTable,
    game: renderSlot,
    history: renderHistory,
    notfound: renderNotFound,
  };

  document.body.dataset.view = route.name;

  mount(main, route.name === 'history'
    ? loadingState(3)
    : lobbySkeleton(route.name));

  try {
    const view = await (loaders[route.name] || renderNotFound)(context);
    if (token !== renderToken) return; // a newer navigation won
    currentView = view || null;
    if (view && view.node) mount(main, [view.node]);
    else if (view) mount(main, [view]);
  } catch (error) {
    if (token !== renderToken) return;
    const message = error instanceof ApiError ? error.message : 'The view could not be loaded.';
    mount(main, errorState(message, () => render()));
  }
}

function lobbySkeleton(routeName) {
  const isGame = routeName === 'slot' || routeName === 'table' || routeName === 'game';
  if (isGame) return loadingState(4);
  return el('div', { 'aria-busy': 'true' }, [
    el('div', { class: 'skeleton skeleton--hero' }),
    el('div', { class: 'grid' }, Array.from({ length: 8 }, () => el('div', { class: 'skeleton skeleton--card' }))),
  ]);
}

function signInPrompt(where) {
  return el('div', { class: 'state' }, [
    el('h2', { text: 'Sign in to continue' }),
    el('p', { text: `The ${where === 'history' ? 'wallet and play history' : 'administrator panel'} needs a player account.` }),
    el('div', { class: 'state__actions' }, [
      el('button', { class: 'btn btn--gold', type: 'button', onClick: openLogin }, 'Log in'),
      el('button', { class: 'btn btn--ghost', type: 'button', onClick: openRegister }, 'Create an account'),
    ]),
  ]);
}

function renderNotFound() {
  return {
    node: el('div', { class: 'state' }, [
      el('h2', { text: 'Page not found' }),
      el('p', { text: 'That address does not match a page in this casino.' }),
      el('div', { class: 'state__actions' }, [
        el('a', { class: 'btn btn--gold', href: '/', 'data-link': true }, 'Back to the lobby'),
      ]),
    ]),
  };
}

/* --------------------------------------------------------------- navigation */

export function navigate(path, { replace = false } = {}) {
  if (replace) window.history.replaceState({}, '', path);
  else window.history.pushState({}, '', path);
  closeAllModals();
  render();
  window.scrollTo(0, 0);
}

document.addEventListener('click', (event) => {
  const link = event.target.closest('a[data-link]');
  if (!link) return;
  if (link.target === '_blank' || event.metaKey || event.ctrlKey || event.shiftKey || event.button !== 0) return;

  const href = link.getAttribute('href');
  if (!href || !href.startsWith('/')) return;

  event.preventDefault();

  // The wallet is the only public-looking destination that needs an account.
  if (link.hasAttribute('data-requires-auth') && !store.getToken()) {
    toast('Sign in to see your wallet and play history.');
    openLogin();
    return;
  }

  sound.sfx.click();
  navigate(href);
});

window.addEventListener('popstate', render);

/* ------------------------------------------------------------------- modals */

const modal = (name) => document.querySelector(`[data-modal="${name}"]`);

function showModal(name) {
  const dialog = modal(name);
  if (!dialog) return false;
  if (name === 'account') refreshHeader(store.snapshot());
  if (!dialog.open) dialog.showModal();
  return true;
}

function closeAllModals() {
  document.querySelectorAll('dialog[open]').forEach((dialog) => dialog.close());
}

function openLogin() {
  closeAllModals();
  const dialog = modal('login');
  if (!dialog) return;
  const form = dialog.querySelector('form');
  form.querySelector('[data-error]').hidden = true;
  showModal('login');
  setTimeout(() => form.elements.identifier?.focus(), 40);
}

function openRegister() {
  closeAllModals();
  const dialog = modal('register');
  if (!dialog) return;
  const form = dialog.querySelector('form');
  form.querySelector('[data-error]').hidden = true;
  form.reset();
  resetRegistrationStep(form);
  updateStrength('');
  showModal('register');
  setTimeout(() => form.elements.phone?.focus(), 40);
}

/* --------------------------------------------------------------- signing in */

function formError(form, message) {
  const box = form.querySelector('[data-error]');
  if (!box) return;
  box.textContent = message;
  box.hidden = message === '';
}

function setBusy(button, busy, label) {
  if (!button) return;
  if (busy) {
    button.dataset.label = button.textContent;
    button.textContent = label;
    button.disabled = true;
  } else {
    button.textContent = button.dataset.label || button.textContent;
    button.disabled = false;
  }
}

async function submitLogin(event) {
  event.preventDefault();
  const form = event.target;
  const button = form.querySelector('[data-submit="login"]');
  const phone = form.elements.identifier.value.trim();
  const password = form.elements.password.value;
  const remember = Boolean(form.elements.remember?.checked);

  formError(form, '');

  if (phone.replace(/\D/g, '').length < 8) {
    formError(form, 'Enter the phone number you registered with.');
    form.elements.identifier.focus();
    return;
  }
  if (password.length < 10) {
    formError(form, 'Enter your password (at least 10 characters).');
    form.elements.password.focus();
    return;
  }

  setBusy(button, true, 'Signing in…');
  try {
    const data = await api.login(phone, password);
    store.setSession(data.token, data.user, data.balance, { remember });
    closeAllModals();
    toast(`Welcome back, ${data.user.display_name}.`, 'win');
    await syncBalance();
    render();
  } catch (error) {
    formError(form, error instanceof ApiError ? error.message : 'Sign-in failed. Try again.');
    sound.sfx.error();
  } finally {
    setBusy(button, false);
  }
}

/* --------------------------------------------------------- registration & OTP */

let pendingRegistration = null;
let pendingOtp = '';

function randomOtp() {
  if (window.crypto?.getRandomValues) {
    const value = new Uint32Array(1);
    window.crypto.getRandomValues(value);
    return String(value[0] % 1_000_000).padStart(6, '0');
  }
  return String(Math.floor(Math.random() * 1_000_000)).padStart(6, '0');
}

function notifyOtp(code, phone) {
  // The browser notification is the primary demonstration. The code is also
  // printed inside the dialog, which is the fallback when notifications are
  // blocked, unsupported or still awaiting permission.
  const display = document.querySelector('[data-otp-code]');
  if (display) display.textContent = code;

  if (!('Notification' in window)) return;

  const notify = () => {
    try {
      new Notification('AURUM verification code', {
        body: `Your six-digit demo code is ${code}. Enter it to finish creating your account.`,
        tag: 'aurum-otp',
      });
    } catch {
      // Some browsers allow the API but block construction in this context.
    }
  };

  if (Notification.permission === 'granted') {
    notify();
  } else if (Notification.permission === 'default') {
    Notification.requestPermission().then((permission) => {
      if (permission === 'granted') notify();
    }).catch(() => {});
  }
}

function resetRegistrationStep(form) {
  form.dataset.step = 'details';
  pendingRegistration = null;
  pendingOtp = '';
  const details = form.querySelector('[data-register-details]');
  const otp = form.querySelector('[data-register-otp]');
  const submit = form.querySelector('[data-submit="register"]');
  const back = form.querySelector('[data-action="register-back"]');
  if (details) details.hidden = false;
  if (otp) otp.hidden = true;
  if (submit) submit.textContent = 'Continue';
  if (back) back.hidden = true;
  const code = form.querySelector('[data-otp-code]');
  if (code) code.textContent = '------';
}

function beginOtp(form) {
  pendingOtp = randomOtp();
  notifyOtp(pendingOtp, pendingRegistration?.phone || '');
  form.dataset.step = 'otp';
  form.querySelector('[data-register-details]').hidden = true;
  form.querySelector('[data-register-otp]').hidden = false;
  form.querySelector('[data-submit="register"]').textContent = 'Verify and create account';
  form.querySelector('[data-action="register-back"]').hidden = false;
  form.elements.otp.value = '';
  setTimeout(() => form.elements.otp?.focus(), 40);
}

async function submitRegister(event) {
  event.preventDefault();
  const form = event.target;
  const button = form.querySelector('[data-submit="register"]');

  formError(form, '');

  if (form.dataset.step !== 'otp') {
    const phone = form.elements.phone.value.trim();
    const displayName = form.elements.display_name.value.trim();
    const age = Number(form.elements.age.value);
    const password = form.elements.password.value;
    const confirm = form.elements.password_confirmation.value;

    if (phone.replace(/\D/g, '').length < 8 || phone.replace(/\D/g, '').length > 15) {
      formError(form, 'Enter a valid phone number.');
      form.elements.phone.focus();
      return;
    }
    if (displayName.length < 2) {
      formError(form, 'Choose a display name with at least 2 characters.');
      form.elements.display_name.focus();
      return;
    }
    if (!Number.isInteger(age) || age < 18 || age > 120) {
      formError(form, 'You must be between 18 and 120 to register.');
      form.elements.age.focus();
      return;
    }
    if (password.length < 10) {
      formError(form, 'Choose a password with at least 10 characters.');
      form.elements.password.focus();
      return;
    }
    if (password !== confirm) {
      formError(form, 'The two passwords do not match.');
      form.elements.password_confirmation.focus();
      return;
    }

    pendingRegistration = { phone, display_name: displayName, age, password };
    beginOtp(form);
    return;
  }

  const otp = form.elements.otp.value.trim();
  if (!/^\d{6}$/.test(otp)) {
    formError(form, 'Enter any six digits to continue.');
    form.elements.otp.focus();
    return;
  }
  if (!pendingRegistration) {
    resetRegistrationStep(form);
    formError(form, 'Please enter your details again.');
    return;
  }

  setBusy(button, true, 'Creating account…');
  try {
    const data = await api.register({ ...pendingRegistration, otp });
    store.setSession(data.token, data.user, data.balance, { remember: true });
    closeAllModals();
    resetRegistrationStep(form);
    form.reset();
    toast(`Welcome, ${data.user.display_name}. Your 300 credits are ready.`, 'win');
    await syncBalance();
    render();
  } catch (error) {
    formError(form, error instanceof ApiError ? error.message : 'Registration failed. Try again.');
    sound.sfx.error();
  } finally {
    setBusy(button, false);
    if (form.dataset.step === 'otp') button.textContent = 'Verify and create account';
  }
}

/* ---------------------------------------------------------------- password UX */

function updateStrength(value) {
  const meter = document.querySelector('[data-strength]');
  const label = document.querySelector('[data-strength-label]');
  if (!meter) return;

  let score = 0;
  if (value.length >= 10) score += 1;
  if (value.length >= 14) score += 1;
  if (/[A-Z]/.test(value) && /[a-z]/.test(value) && /\d/.test(value)) score += 1;
  if (/[^A-Za-z0-9]/.test(value) || value.length >= 20) score += 1;

  meter.dataset.score = String(score);
  if (label) {
    label.textContent = score === 0
      ? 'Use 10 or more characters.'
      : score === 1 ? 'Weak — add length or variety.'
        : score === 2 ? 'Fair — a longer password is stronger.'
          : score === 3 ? 'Good password.' : 'Strong password.';
  }
}

/* ---------------------------------------------------------------- logout */

async function logout() {
  try {
    await api.logout();
  } catch {
    // Revoking is best-effort; dropping the token locally is what matters.
  }
  store.clearSession();
  closeAllModals();
  toast('Signed out. Your credits stay on the account.');
  navigate('/', { replace: true });
}

/* ---------------------------------------------------------------- catalogue */

function ensureCatalogue() {
  if (!cataloguePromise) {
    cataloguePromise = api.lobby()
      .then((data) => {
        const games = data.games || [];
        const count = document.querySelector('[data-catalogue-count]');
        if (count) count.textContent = String(games.length);
        return games;
      })
      .catch((error) => {
        cataloguePromise = null;
        throw error;
      });
  }
  return cataloguePromise;
}

function gameRoute(game) {
  if (game.category === 'slot') return `/slots/${encodeURIComponent(game.id)}`;
  return `/tables/${encodeURIComponent(game.id)}`;
}

function launchGame(game) {
  if (!store.getToken()) {
    closeAllModals();
    toast('Sign in to play — browsing is public, the games need an account.');
    openLogin();
    return;
  }
  navigate(gameRoute(game));
}

/* ---------------------------------------------------------------- search */

const searchDialog = modal('search');
const searchInput = searchDialog?.querySelector('[data-search-input]');
const searchResults = searchDialog?.querySelector('[data-search-results]');

function openSearch() {
  if (!searchDialog) return;
  closeAllModals();
  showModal('search');
  searchInput.value = '';
  mount(searchResults, [el('p', { class: 'muted', text: 'Loading the catalogue…' })]);

  ensureCatalogue()
    .then((games) => {
      searchGames = games;
      paintSearch('');
    })
    .catch(() => {
      mount(searchResults, [el('p', { class: 'muted', text: 'The catalogue could not be loaded.' })]);
    });

  setTimeout(() => searchInput.focus(), 40);
}

function paintSearch(query) {
  if (!searchResults || !searchGames) return;
  const needle = query.trim().toLowerCase();

  const results = (needle
    ? searchGames.filter(({ game }) => `${game.name} ${game.provider} ${(game.tags || []).join(' ')} ${game.category}`.toLowerCase().includes(needle))
    : searchGames
  ).slice(0, 48);

  if (results.length === 0) {
    mount(searchResults, [
      el('p', { class: 'muted', text: `No games match “${query.trim()}”. Try a provider or a shorter word.` }),
    ]);
    return;
  }

  mount(searchResults, results.map(({ game }) => el('button', {
    class: 'search-row',
    type: 'button',
    onClick: () => { const picked = game; closeAllModals(); launchGame(picked); },
  }, [
    el('img', { src: gameArt(game.id), alt: '', loading: 'lazy' }),
    el('span', { class: 'search-row__text' }, [
      el('strong', { text: game.name }),
      el('span', { text: `${providerLabel(game.provider)} · ${game.category}` }),
    ]),
    el('span', { class: `tag ${game.category === 'slot' ? 'tag--info' : ''} search-row__tag`, text: `${(game.rtp * 100).toFixed(1)}% RTP` }),
  ])));
}

if (searchInput) {
  searchInput.addEventListener('input', (event) => paintSearch(event.target.value));
  searchInput.addEventListener('keydown', (event) => {
    if (event.key === 'Enter') {
      event.preventDefault();
      const first = searchResults?.querySelector('.search-row');
      first?.click();
    }
  });
}

/* ------------------------------------------------------------------ wiring */

document.addEventListener('click', (event) => {
  const control = event.target.closest('[data-action]');
  if (!control) return;
  const action = control.dataset.action;

  if (action === 'open-login') openLogin();
  else if (action === 'open-register') openRegister();
  else if (action === 'register-back') {
    const form = control.closest('form');
    resetRegistrationStep(form);
    setTimeout(() => form.elements.phone?.focus(), 40);
  } else if (action === 'resend-otp') {
    const form = control.closest('form');
    if (pendingRegistration) {
      pendingOtp = randomOtp();
      notifyOtp(pendingOtp, pendingRegistration.phone);
      toast('A new demo code is ready.');
      form.elements.otp.value = '';
      form.elements.otp.focus();
    }
  } else if (action === 'social-login') {
    toast(`${control.dataset.provider} sign-in is coming soon. Use your phone number to continue.`);
  }
  else if (action === 'forgot-password') { closeAllModals(); showModal('forgot'); }
  else if (action === 'open-account') { closeAllModals(); refreshHeader(store.snapshot()); showModal('account'); }
  else if (action === 'open-menu') {
    const drawer = modal('menu');
    if (drawer) {
      drawer.showModal();
      control.setAttribute('aria-expanded', 'true');
    }
  } else if (action === 'open-search') openSearch();
  else if (action === 'logout') logout();
  else if (action === 'close-modal') {
    const dialog = control.closest('dialog');
    dialog?.close();
    if (dialog?.dataset.modal === 'menu') {
      document.querySelector('[data-action="open-menu"]')?.setAttribute('aria-expanded', 'false');
    }
  } else if (action === 'toggle-password') {
    const field = control.closest('.field__with-action');
    const input = field?.querySelector('input');
    if (!input) return;
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    control.setAttribute('aria-pressed', show ? 'true' : 'false');
    control.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    const slot = control.querySelector('[data-icon]');
    if (slot) {
      slot.replaceChildren();
      slot.dataset.icon = show ? 'eye-off' : 'eye';
      slot.dataset.iconDone = '0';
      decorateIcons(control);
    }
  } else if (action === 'toggle-sound') {
    const on = sound.toggle();
    syncSoundLabels(on);
    toast(on ? 'Sound on.' : 'Sound off.');
    if (on) sound.sfx.click();
  }
});

document.addEventListener('submit', (event) => {
  if (event.target.matches('[data-form="login"]')) submitLogin(event);
  else if (event.target.matches('[data-form="register"]')) submitRegister(event);
});

document.addEventListener('input', (event) => {
  if (event.target.matches('[data-form="register"] [name="password"]')) updateStrength(event.target.value);
  if (event.target.closest('form')?.matches('[data-form]')) {
    const form = event.target.closest('form');
    const box = form.querySelector('[data-error]');
    if (box && !box.hidden) box.hidden = true;
  }
});

// A click on a dialog's backdrop closes it. The dialog element itself is the
// only target that covers the backdrop area.
document.querySelectorAll('dialog').forEach((dialog) => {
  dialog.addEventListener('click', (event) => {
    if (event.target === dialog) dialog.close();
  });
});

function syncSoundLabels(enabled = sound.isEnabled()) {
  document.querySelectorAll('[data-sound-label]').forEach((node) => {
    node.textContent = enabled ? 'Sound on' : 'Sound off';
  });
}

/* ------------------------------------------------------------------- start */

decorateIcons(document);
syncSoundLabels();
store.subscribe(refreshHeader);
store.load();
sound.init();
render();

// Reflect the signed-in user's balance on load, so a reloaded page shows the
// authoritative figure rather than a stale cached one.
if (store.getToken()) syncBalance();
