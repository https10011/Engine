// The header: who is signed in, the balance, and the balance animation.
//
// Kept out of `app.js` so a view can update the balance without importing the
// router, which would make `app.js` and every view import each other.

import { money } from './dom.js';
import { api } from './api.js';
import * as store from './store.js';

export function refreshHeader(state) {
  const balance = document.querySelector('[data-balance]');
  const who = document.querySelector('[data-who]');

  document.querySelectorAll('[data-when="anonymous"]').forEach((n) => { n.hidden = state.authenticated; });
  document.querySelectorAll('[data-when="authenticated"]').forEach((n) => { n.hidden = !state.authenticated; });
  document.querySelectorAll('[data-requires-auth]').forEach((n) => { n.hidden = !state.authenticated; });
  document.querySelectorAll('[data-requires-admin]').forEach((n) => { n.hidden = !store.isAdmin(); });

  if (state.authenticated) {
    if (balance) {
      balance.hidden = false;
      balance.querySelector('.balance__value').textContent = state.balance
        ? money(state.balance.amount, state.balance.scale, state.balance.currency)
        : '—';
    }
    if (who) {
      who.hidden = false;
      who.textContent = state.user ? state.user.display_name || state.user.username : '';
    }
  } else {
    if (balance) balance.hidden = true;
    if (who) who.hidden = true;
  }
}

/** Play a short win or loss animation on the balance readout. */
export function flashBalance(kind) {
  const balance = document.querySelector('[data-balance]');
  if (!balance) return;

  balance.classList.remove('is-win', 'is-lose');
  if (!kind) return;

  // Force a reflow so the animation restarts when the class is re-added.
  void balance.offsetWidth;
  balance.classList.add(kind === 'win' ? 'is-win' : 'is-lose');
  setTimeout(() => balance.classList.remove('is-win', 'is-lose'), 600);
}

export async function syncBalance() {
  if (!store.getToken()) return;
  try {
    const data = await api.wallet();
    store.setBalance(data.balance || data);
  } catch {
    // A failed refresh is not worth interrupting play for; the next action
    // returns an authoritative balance anyway.
  }
}
