// Small presentation helpers shared by every view: toasts, loading and error
// states, and the game catalogue query.

import { el, mount, clear } from './dom.js';

const toastHost = () => document.querySelector('[data-toasts]');

export function toast(message, kind = 'info', ms = 4200) {
  const host = toastHost();
  if (!host) return;

  const node = el('div', { class: `toast toast--${kind}`, role: 'status', text: message });
  host.append(node);
  setTimeout(() => {
    node.style.opacity = '0';
    setTimeout(() => node.remove(), 220);
  }, ms);
}

export function loadingState(count = 6) {
  return el('div', { class: 'grid', 'aria-busy': 'true' }, Array.from({ length: count }, () =>
    el('div', { class: 'skeleton skeleton--card' })
  ));
}

export function errorState(message, onRetry) {
  return el('div', { class: 'state' }, [
    el('h2', { text: 'Something went wrong' }),
    el('p', { text: message }),
    el('div', { class: 'state__actions' }, [
      onRetry ? el('button', { class: 'btn btn--primary', type: 'button', onClick: onRetry }, 'Try again') : null,
    ]),
  ]);
}

export function emptyState(title, message, action = null) {
  return el('div', { class: 'state' }, [
    el('h2', { text: title }),
    el('p', { text: message }),
    action ? el('div', { class: 'state__actions' }, [action]) : null,
  ]);
}

export function setBusy(node, busy) {
  if (!node) return;
  node.setAttribute('aria-busy', busy ? 'true' : 'false');
  node.querySelectorAll('button, input, select').forEach((control) => {
    if (busy) control.setAttribute('disabled', '');
    else control.removeAttribute('disabled');
  });
}

/** Apply a game theme to a subtree by setting the CSS custom properties. */
export function applyTheme(node, theme) {
  if (!theme) return node;
  const map = {
    '--card-base': theme.base,
    '--card-panel': theme.panel,
    '--card-accent': theme.accent,
    '--card-accent2': theme.accent2,
    '--accent': theme.accent,
    '--accent-2': theme.accent2,
    '--panel': theme.panel,
    '--ink': theme.ink,
  };
  for (const [key, value] of Object.entries(map)) {
    if (value) node.style.setProperty(key, value);
  }
  return node;
}

export { el, mount, clear };
