// Inline icon set.
//
// Every icon is a small hand-authored SVG path (24×24, stroke-based, inherits
// `currentColor`). Icons are inlined rather than loaded as files so the shell
// needs no icon font, no sprite request and no third-party icon library.

const NS = 'http://www.w3.org/2000/svg';

const PATHS = {
  home: '<path d="M3 10.8 12 3.5l9 7.3"/><path d="M5.4 9.6V20.5h13.2V9.6"/><path d="M9.6 20.5v-5.4h4.8v5.4"/>',
  grid: '<rect x="3.4" y="3.4" width="7.2" height="7.2" rx="2"/><rect x="13.4" y="3.4" width="7.2" height="7.2" rx="2"/><rect x="3.4" y="13.4" width="7.2" height="7.2" rx="2"/><rect x="13.4" y="13.4" width="7.2" height="7.2" rx="2"/>',
  search: '<circle cx="10.8" cy="10.8" r="6.6"/><path d="m15.8 15.8 4.4 4.4"/>',
  wallet: '<path d="M3.6 7.6A2.6 2.6 0 0 1 6.2 5h11.2a2.6 2.6 0 0 1 2.6 2.6v9.8a2.6 2.6 0 0 1-2.6 2.6H6.2a2.6 2.6 0 0 1-2.6-2.6z"/><path d="M3.6 8.8h16.4"/><path d="M16.2 13.4h1.8"/>',
  user: '<circle cx="12" cy="8.4" r="3.9"/><path d="M4.6 20.4a7.4 7.4 0 0 1 14.8 0"/>',
  menu: '<path d="M4 7h16"/><path d="M4 12h16"/><path d="M4 17h16"/>',
  close: '<path d="m6 6 12 12"/><path d="m18 6-12 12"/>',
  'chevron-left': '<path d="m14.5 5.5-6.5 6.5 6.5 6.5"/>',
  'chevron-right': '<path d="m9.5 5.5 6.5 6.5-6.5 6.5"/>',
  'arrow-right': '<path d="M4.5 12h14"/><path d="m13 6.5 5.5 5.5-5.5 5.5"/>',
  flame: '<path d="M12 3.4c3.4 3.9 8 6.3 8 9.8a8 8 0 1 1-16 0c0-2.6 1.7-4.6 3.4-6.2.6 2.3 1.5 3.3 2.6 3.7.6-2.8.6-5.3 2-7.3Z"/><path d="M12 14.2c1.6 1.6 2.4 2.6 2.4 4a2.4 2.4 0 0 1-4.8 0c0-1.4.8-2.4 2.4-4Z"/>',
  slots: '<rect x="3.4" y="4" width="17.2" height="16" rx="2.6"/><path d="M8.2 4v16"/><path d="M15.8 4v16"/><path d="M11.2 9.2h.9"/><path d="M14.7 12h.9"/><path d="M5.6 14.4h.9"/>',
  cards: '<rect x="8.2" y="3.4" width="9.4" height="13.2" rx="1.8" transform="rotate(10 12.9 10)"/><rect x="5.2" y="7.2" width="9.4" height="13.2" rx="1.8" transform="rotate(-8 9.9 13.8)"/>',
  wheel: '<circle cx="12" cy="12" r="8.6"/><circle cx="12" cy="12" r="3.1"/><path d="M12 3.4v3.2"/><path d="M12 17.4v3.2"/><path d="M3.4 12h3.2"/><path d="M17.4 12h3.2"/>',
  coin: '<circle cx="12" cy="12" r="8.6"/><path d="M12 7.3v9.4"/><path d="M14.7 9.2a3.4 3.4 0 0 0-2.7-1c-1.5 0-2.6.8-2.6 2s1 1.8 2.6 2.2c1.7.4 2.8 1 2.8 2.3s-1.2 2.1-2.8 2.1a3.5 3.5 0 0 1-2.9-1.2"/>',
  shield: '<path d="M12 3.2 19.4 6v6.1c0 4.2-3 7.5-7.4 8.7-4.4-1.2-7.4-4.5-7.4-8.7V6z"/><path d="m8.9 12.1 2.2 2.2 4-4.3"/>',
  sound: '<path d="M4.4 9.4h3.2L12 5.6v12.8l-4.4-3.8H4.4z"/><path d="M15.4 8.6a4.8 4.8 0 0 1 0 6.8"/><path d="M18.2 6a8.4 8.4 0 0 1 0 12"/>',
  'sound-off': '<path d="M4.4 9.4h3.2L12 5.6v12.8l-4.4-3.8H4.4z"/><path d="m15.8 9.4 5 5.2"/><path d="m20.8 9.4-5 5.2"/>',
  eye: '<path d="M2.8 12S6 6.4 12 6.4 21.2 12 21.2 12 18 17.6 12 17.6 2.8 12 2.8 12Z"/><circle cx="12" cy="12" r="2.9"/>',
  'eye-off': '<path d="M4 4.4 20 20.4"/><path d="M9.6 9.8A2.9 2.9 0 0 0 12 14.9"/><path d="M6.1 7.2C3.9 8.8 2.8 12 2.8 12s3.2 5.6 9.2 5.6c1.5 0 2.8-.3 3.9-.9"/><path d="M10.2 6.6c.6-.1 1.2-.2 1.8-.2 6 0 9.2 5.6 9.2 5.6a17 17 0 0 1-2.1 2.7"/>',
  check: '<path d="m5 12.6 4.4 4.3L19 7.2"/>',
  trophy: '<path d="M7.4 4.4h9.2v3.2a4.6 4.6 0 0 1-9.2 0z"/><path d="M7.4 5.8H4.6v1.4a3.2 3.2 0 0 0 3.2 3.2"/><path d="M16.6 5.8h2.8v1.4a3.2 3.2 0 0 1-3.2 3.2"/><path d="M12 12.2v3.4"/><path d="M8.6 19.6h6.8"/><path d="M9.6 19.6c0-2 1-4 2.4-4s2.4 2 2.4 4"/>',
  bolt: '<path d="M13.4 2.8 5.6 13.4h5.4l-1.4 7.8 7.8-10.6h-5.4z"/>',
  key: '<circle cx="8.2" cy="12" r="3.4"/><path d="M11.6 12H21"/><path d="M17.6 12v3.2"/><path d="M20 12v2.2"/>',
  info: '<circle cx="12" cy="12" r="8.8"/><path d="M12 10.8v5.4"/><path d="M12 7.8h.01"/>',
  lock: '<rect x="4.6" y="10.4" width="14.8" height="10" rx="2.4"/><path d="M8.2 10.4V7.8a3.8 3.8 0 0 1 7.6 0v2.6"/>',
  spark: '<path d="M12 3.2 13.8 9l5.8 1.8-5.8 1.8L12 18.4l-1.8-5.8L4.4 10.8 10.2 9z"/><path d="M18.4 16.4l.7 2.1 2.1.7-2.1.7-.7 2.1-.7-2.1-2.1-.7 2.1-.7z"/>',
  clock: '<circle cx="12" cy="12" r="8.6"/><path d="M12 7.4V12l3 1.8"/>',
  plus: '<path d="M12 5.4v13.2"/><path d="M5.4 12h13.2"/>',
  minus: '<path d="M5.4 12h13.2"/>',
  refresh: '<path d="M20 11.2A8 8 0 0 0 6.4 6.6L4 9"/><path d="M4 4.4V9h4.6"/><path d="M4 12.8a8 8 0 0 0 13.6 4.6L20 15"/><path d="M20 19.6V15h-4.6"/>',
};

/**
 * Build an SVG icon node.
 *
 * @param {string} name key in PATHS; unknown names fall back to a dot so a
 *                      typo shows something harmless rather than throwing.
 * @returns {SVGElement}
 */
export function icon(name) {
  const svg = document.createElementNS(NS, 'svg');
  svg.setAttribute('viewBox', '0 0 24 24');
  svg.setAttribute('fill', 'none');
  svg.setAttribute('stroke', 'currentColor');
  svg.setAttribute('stroke-width', '1.7');
  svg.setAttribute('stroke-linecap', 'round');
  svg.setAttribute('stroke-linejoin', 'round');
  svg.setAttribute('aria-hidden', 'true');
  svg.setAttribute('focusable', 'false');
  svg.classList.add('icon');
  svg.innerHTML = PATHS[name] || '<circle cx="12" cy="12" r="3" fill="currentColor" stroke="none"/>';
  return svg;
}

/** Replace every `[data-icon]` placeholder inside `root` with its icon. */
export function decorateIcons(root = document) {
  for (const slot of root.querySelectorAll('[data-icon]')) {
    if (slot.dataset.iconDone === '1') continue;
    slot.append(icon(slot.dataset.icon));
    slot.dataset.iconDone = '1';
  }
}

/** Icon markup for string contexts (only ever used with authored names). */
export function iconMarkup(name) {
  return `<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
    stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">${PATHS[name] || ''}</svg>`;
}
