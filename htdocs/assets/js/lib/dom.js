// Tiny DOM helpers.
//
// The application builds its views with plain DOM nodes rather than innerHTML
// strings. That matters here: a symbol name, a provider string or a game title
// ends up on screen, and string interpolation into innerHTML would turn a
// provider's stray `<` into markup. Creating nodes and setting `textContent`
// makes escaping structural rather than something a view has to remember.

export function el(tag, attrs = {}, children = []) {
  const node = document.createElement(tag);

  for (const [key, value] of Object.entries(attrs)) {
    if (value === null || value === undefined || value === false) continue;

    if (key === 'class') node.className = value;
    else if (key === 'text') node.textContent = String(value);
    else if (key === 'html') node.innerHTML = value; // only ever for inline icons we authored
    else if (key === 'dataset') Object.assign(node.dataset, value);
    else if (key === 'style' && typeof value === 'object') {
      for (const [property, propertyValue] of Object.entries(value)) {
        // Custom properties are not CSSStyleDeclaration members, so assigning
        // them directly silently does nothing. Use the setProperty API.
        if (property.startsWith('--')) node.style.setProperty(property, String(propertyValue));
        else node.style[property] = propertyValue;
      }
    }
    else if (key.startsWith('on') && typeof value === 'function') node.addEventListener(key.slice(2).toLowerCase(), value);
    else if (value === true) node.setAttribute(key, '');
    else node.setAttribute(key, String(value));
  }

  for (const child of [].concat(children)) {
    if (child === null || child === undefined || child === false) continue;
    node.append(child instanceof Node ? child : document.createTextNode(String(child)));
  }

  return node;
}

export function frag(children) {
  const f = document.createDocumentFragment();
  for (const child of [].concat(children)) {
    if (child === null || child === undefined || child === false) continue;
    f.append(child instanceof Node ? child : document.createTextNode(String(child)));
  }
  return f;
}

export function clear(node) {
  while (node.firstChild) node.removeChild(node.firstChild);
}

export function mount(node, children) {
  clear(node);
  node.append(frag(children));
}

/** Format a minor-unit amount using the currency scale the API reported. */
export function money(amount, scale = 2, currency = '') {
  const n = Number(amount || 0);
  const value = (n / Math.pow(10, scale)).toLocaleString(undefined, {
    minimumFractionDigits: scale,
    maximumFractionDigits: scale,
  });
  return currency ? `${value} ${currency}` : value;
}

/** Render a rational multiplier (`{numerator, denominator}`) as a short string. */
export function ratio(r) {
  if (!r) return '0';
  const num = Number(r.numerator ?? 0);
  const den = Number(r.denominator ?? 1) || 1;
  if (num === 0) return '0';
  const value = num / den;
  if (Number.isInteger(value)) return String(value);
  return value.toFixed(value < 1 ? 3 : 2).replace(/0+$/, '').replace(/\.$/, '');
}

export function percent(value) {
  if (value === null || value === undefined) return '—';
  return `${(Number(value) * 100).toFixed(2)}%`;
}

/**
 * Short provider label for tiles and headers.
 *
 * The engine reports provenance in full, e.g. "JILI Games (mechanics) /
 * Engine Original (configuration)". The parenthetical and the second half are
 * useful in a game's details, not on a 116px tile, so show the studio only.
 */
export function providerLabel(provider) {
  const first = String(provider || '').split('/')[0].replace(/\([^)]*\)/g, '').trim();
  if (first === '' || /\bengine\b/i.test(first)) {
    return 'Aurum Originals';
  }
  return first;
}
