// The header and the account sheet: who is signed in, the balance, and the
// small amount of animation that accompanies a settled round.
//
// Kept out of `app.js` so a view can update the balance without importing the
// router, which would make `app.js` and every view import each other.

import { money } from './dom.js';
import { api } from './api.js';
import * as store from './store.js';

function setBalanceText(value) {
  document.querySelectorAll('[data-balance] .balance__value').forEach((node) => {
    node.textContent = value;
  });
  const accountBalance = document.querySelector('[data-account-balance]');
  if (accountBalance) accountBalance.textContent = value;
}

export function refreshHeader(state) {
  document.querySelectorAll('[data-when="anonymous"]').forEach((n) => { n.hidden = state.authenticated; });
  document.querySelectorAll('[data-when="authenticated"]').forEach((n) => { n.hidden = !state.authenticated; });
  document.querySelectorAll('[data-requires-auth]').forEach((n) => { n.hidden = !state.authenticated; });
  document.querySelectorAll('[data-requires-admin]').forEach((n) => { n.hidden = !store.isAdmin(); });

  const chip = document.querySelector('[data-balance]');
  const who = document.querySelector('[data-who]');
  const name = state.user ? (state.user.display_name || state.user.username) : '';

  if (chip) chip.hidden = !state.authenticated;
  if (who) {
    who.hidden = !state.authenticated;
    who.textContent = name;
  }

  setBalanceText(
    state.authenticated && state.balance
      ? money(state.balance.amount, state.balance.scale, state.balance.currency)
      : '—'
  );

  const accountName = document.querySelector('[data-account-name]');
  const accountSub = document.querySelector('[data-account-sub]');
  const avatar = document.querySelector('[data-account-avatar]');
  const links = document.querySelector('[data-account-links]');

  if (accountName) accountName.textContent = state.authenticated ? name : 'Guest';
  if (accountSub) {
    accountSub.textContent = state.authenticated
      ? `${state.user.username}${store.isAdmin() ? ' · administrator' : ' · player'}`
      : 'Not signed in · browsing is public';
  }
  if (avatar) avatar.textContent = (name || 'A').slice(0, 1).toUpperCase();
  if (links) links.hidden = !state.authenticated;
}

/** Play a short win or loss animation on the balance readout. */
export function flashBalance(kind) {
  const chip = document.querySelector('[data-balance]');
  if (!chip) return;

  chip.classList.remove('is-win', 'is-lose');
  if (!kind) return;

  // Force a reflow so the animation restarts when the class is re-added.
  void chip.offsetWidth;
  chip.classList.add(kind === 'win' ? 'is-win' : 'is-lose');
  setTimeout(() => chip.classList.remove('is-win', 'is-lose'), 620);
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
