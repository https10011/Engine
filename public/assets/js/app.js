// Application shell: routing, authentication and the header.
//
// The router is a small hash-free history router over five view modules. It
// owns three things and nothing else: which view is mounted, who is signed in,
// and the header's balance. No view decides an outcome — a view calls the API
// and renders what comes back.

import { el, mount, clear, money } from './lib/dom.js';
import { api, ApiError } from './lib/api.js';
import * as store from './lib/store.js';
import { toast, errorState, loadingState } from './lib/ui.js';
import { refreshHeader, syncBalance } from './lib/shell.js';
import * as sound from './lib/sound.js';

import { renderLobby } from './views/lobby.js';
import { renderSlot } from './views/slot.js';
import { renderTable } from './views/table.js';
import { renderHistory } from './views/history.js';
import { renderAdmin } from './views/admin.js';

const main = document.querySelector('main[data-view]');
let currentView = null;
let renderToken = 0;

/* ------------------------------------------------------------------ routes */

function parseRoute() {
  const path = window.location.pathname.replace(/\/+$/, '') || '/';
  const parts = path.split('/').filter(Boolean);

  if (parts.length === 0) return { name: 'lobby' };
  if (parts[0] === 'slots') return parts[1] ? { name: 'slot', id: decodeURIComponent(parts[1]) } : { name: 'slots' };
  if (parts[0] === 'tables') {
    if (!parts[1]) return { name: 'tables' };
    return { name: 'table', id: decodeURIComponent(parts[1]) };
  }
  if (parts[0] === 'game' && parts[1]) return { name: 'game', id: decodeURIComponent(parts[1]) };
  if (parts[0] === 'history') return { name: 'history' };
  if (parts[0] === 'admin') return { name: 'admin' };
  return { name: 'notfound', path };
}

/* ------------------------------------------------------------------ render */

async function render() {
  const route = parseRoute();
  const token = ++renderToken;

  document.querySelectorAll('[data-nav]').forEach((link) => {
    const target = link.dataset.nav;
    const active =
      (target === 'lobby' && route.name === 'lobby') ||
      (target === 'slots' && (route.name === 'slots' || route.name === 'slot')) ||
      (target === 'tables' && (route.name === 'tables' || route.name === 'table')) ||
      (target === 'history' && route.name === 'history') ||
      (target === 'admin' && route.name === 'admin');
    if (active) link.setAttribute('aria-current', 'page');
    else link.removeAttribute('aria-current');
  });

  if (currentView && typeof currentView.destroy === 'function') {
    try { currentView.destroy(); } catch { /* a view's teardown must never break navigation */ }
  }
  currentView = null;
  clear(main);

  const guarded = (route.name === 'history' || route.name === 'admin');
  if (guarded && !store.getToken()) {
    mount(main, signInPrompt(route.name));
    return;
  }
  if (route.name === 'admin' && !store.isAdmin()) {
    mount(main, errorState('The administrator panel is restricted to administrator accounts.', null));
    return;
  }

  const context = {
    route,
    main,
    navigate,
    isStale: () => token !== renderToken,
  };

  const loaders = {
    lobby: renderLobby,
    slots: (ctx) => renderLobby({ ...ctx, lockedCategory: 'slot' }),
    tables: (ctx) => renderLobby({ ...ctx, lockedCategory: 'card' }),
    slot: renderSlot,
    table: renderTable,
    game: renderSlot,
    history: renderHistory,
    admin: renderAdmin,
    notfound: renderNotFound,
  };

  const loader = loaders[route.name] || renderNotFound;
  mount(main, loadingState(route.name === 'lobby' || route.name === 'slots' || route.name === 'tables' ? 8 : 3));

  try {
    const view = await loader(context);
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

function signInPrompt(where) {
  return el('div', { class: 'state' }, [
    el('h2', { text: 'Sign in to continue' }),
    el('p', { text: `The ${where} view needs a player account.` }),
    el('div', { class: 'state__actions' }, [
      el('button', { class: 'btn btn--primary', type: 'button', onClick: openLogin }, 'Sign in'),
    ]),
  ]);
}

function renderNotFound() {
  return {
    node: el('div', { class: 'state' }, [
      el('h2', { text: 'Page not found' }),
      el('p', { text: 'That address does not match a page in this casino.' }),
      el('div', { class: 'state__actions' }, [
        el('a', { class: 'btn btn--primary', href: '/', 'data-link': true }, 'Back to the lobby'),
      ]),
    ]),
  };
}

/* --------------------------------------------------------------- navigation */

export function navigate(path, { replace = false } = {}) {
  if (replace) window.history.replaceState({}, '', path);
  else window.history.pushState({}, '', path);
  render();
}

document.addEventListener('click', (event) => {
  const link = event.target.closest('a[data-link]');
  if (!link) return;
  if (link.target === '_blank' || event.metaKey || event.ctrlKey || event.shiftKey || event.button !== 0) return;

  const href = link.getAttribute('href');
  if (!href || !href.startsWith('/')) return;

  event.preventDefault();
  sound.sfx.click();
  navigate(href);
});

window.addEventListener('popstate', render);

/* ------------------------------------------------------------------- login */

const loginDialog = document.querySelector('[data-modal="login"]');

function openLogin() {
  if (!loginDialog) return;
  const form = loginDialog.querySelector('form');
  form.reset();
  form.querySelector('[data-error]').hidden = true;
  loginDialog.showModal();
}

async function submitLogin(event) {
  event.preventDefault();
  const form = event.currentTarget;
  const button = form.querySelector('[data-submit="login"]');
  const errorBox = form.querySelector('[data-error]');
  const username = form.elements.username.value.trim();
  const password = form.elements.password.value;

  errorBox.hidden = true;
  button.disabled = true;
  button.textContent = 'Signing in…';

  try {
    const data = await api.login(username, password);
    store.setSession(data.token, data.user, data.balance);
    loginDialog.close();
    toast(`Signed in as ${data.user.display_name || data.user.username}.`, 'win');
    await syncBalance();
    render();
  } catch (error) {
    errorBox.textContent = error instanceof ApiError ? error.message : 'Sign-in failed.';
    errorBox.hidden = false;
    sound.sfx.error();
  } finally {
    button.disabled = false;
    button.textContent = 'Sign in';
  }
}

async function logout() {
  try {
    await api.logout();
  } catch {
    // Revoking is best-effort; dropping the token locally is what matters.
  }
  store.clearSession();
  toast('Signed out.');
  render();
}

/* ------------------------------------------------------------------ wiring */

document.addEventListener('click', (event) => {
  const action = event.target.closest('[data-action]')?.dataset.action;
  if (!action) return;

  if (action === 'open-login') openLogin();
  else if (action === 'logout') logout();
  else if (action === 'close-modal') {
    event.target.closest('dialog')?.close();
  } else if (action === 'toggle-sound') {
    const on = sound.toggle();
    event.target.closest('[data-action]').textContent = on ? '🔊 Sound on' : '🔇 Sound off';
    if (on) sound.sfx.click();
  }
});

document.addEventListener('submit', (event) => {
  if (event.target.matches('[data-form="login"]')) submitLogin(event);
});

store.subscribe(refreshHeader);
store.load();
sound.init();
render();

// Reflect the signed-in user's balance on load, so a reloaded page shows the
// authoritative figure rather than a stale cached one.
if (store.getToken()) syncBalance();
