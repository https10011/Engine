// Session state: the token, the player and the balance.
//
// Deliberately small. The client holds a bearer token and a cached balance; it
// holds no game state and computes no outcome. Everything that decides money
// lives on the server, and the only thing this store does is remember who is
// signed in between page views.
//
// The token lives in storage rather than a cookie on purpose: the API
// authenticates with an `Authorization: Bearer` header, so a cross-site
// request cannot carry it implicitly and there is no CSRF surface for the
// token itself.
//
// "Keep me signed in" chooses between localStorage (survives closing the
// browser) and sessionStorage (cleared when the tab closes). Both are cleared
// on sign-out and on a 401.

const TOKEN_KEY = 'aurum.token';
const USER_KEY = 'aurum.user';

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

function read(key) {
  try {
    return localStorage.getItem(key) ?? sessionStorage.getItem(key);
  } catch {
    return null;
  }
}

export function load() {
  try {
    token = read(TOKEN_KEY);
    const raw = read(USER_KEY);
    user = raw ? JSON.parse(raw) : null;
  } catch {
    token = null;
    user = null;
  }
  notify();
}

export function setSession(nextToken, nextUser, nextBalance, { remember = true } = {}) {
  token = nextToken || null;
  user = nextUser || null;
  if (nextBalance !== undefined) balance = nextBalance;

  try {
    localStorage.removeItem(TOKEN_KEY);
    localStorage.removeItem(USER_KEY);
    sessionStorage.removeItem(TOKEN_KEY);
    sessionStorage.removeItem(USER_KEY);

    if (token) {
      const store = remember ? localStorage : sessionStorage;
      store.setItem(TOKEN_KEY, token);
      if (user) store.setItem(USER_KEY, JSON.stringify(user));
    }
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
