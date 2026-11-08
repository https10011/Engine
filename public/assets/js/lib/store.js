// Session state: the token, the player and the balance.
//
// Deliberately small. The client holds a bearer token and a cached balance; it
// holds no game state and computes no outcome. Everything that decides money
// lives on the server, and the only thing this store does is remember who is
// signed in between page views.
//
// The token lives in localStorage rather than a cookie on purpose: the API
// authenticates with an `Authorization: Bearer` header, so a cross-site
// request cannot carry it implicitly and there is no CSRF surface for the
// token itself.

const TOKEN_KEY = 'casino.token';
const USER_KEY = 'casino.user';

let token = null;
let user = null;
let balance = null;
const listeners = new Set();

function notify() {
  for (const fn of listeners) fn(snapshot());
}

export function subscribe(fn) {
  listeners.add(fn);
  fn(snapshot());
  return () => listeners.delete(fn);
}

export function snapshot() {
  return { token, user, balance, authenticated: Boolean(token) };
}

export function isAdmin() {
  return Boolean(user && user.role === 'admin');
}

export function load() {
  try {
    token = localStorage.getItem(TOKEN_KEY) || null;
    const raw = localStorage.getItem(USER_KEY);
    user = raw ? JSON.parse(raw) : null;
  } catch {
    token = null;
    user = null;
  }
  notify();
}

export function setSession(nextToken, nextUser, nextBalance) {
  token = nextToken || null;
  user = nextUser || null;
  if (nextBalance !== undefined) balance = nextBalance;

  try {
    if (token) localStorage.setItem(TOKEN_KEY, token);
    else localStorage.removeItem(TOKEN_KEY);

    if (user) localStorage.setItem(USER_KEY, JSON.stringify(user));
    else localStorage.removeItem(USER_KEY);
  } catch {
    // Private browsing with storage disabled: stay usable for this tab only.
  }

  notify();
}

export function setBalance(next) {
  balance = next;
  notify();
}

export function clearSession() {
  setSession(null, null, null);
}

export function getToken() {
  return token;
}

export function getUser() {
  return user;
}

export function getBalance() {
  return balance;
}
