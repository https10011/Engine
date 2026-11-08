// The only place the browser talks to the engine.
//
// Every call returns the decoded envelope and throws a typed `ApiError` on a
// failed one, so a view never inspects a status code. The client never computes
// a payout, never chooses a symbol and never decides whether a round won: it
// posts an action and renders the settled round the server returns.

import { getToken, clearSession } from './store.js';

export class ApiError extends Error {
  constructor(message, { status = 0, code = 'ERROR', details = null } = {}) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
    this.code = code;
    this.details = details;
  }
}

const BASE = '/api/v1';

async function request(method, path, { body, auth = true, idempotencyKey } = {}) {
  const headers = { Accept: 'application/json' };
  if (body !== undefined) headers['Content-Type'] = 'application/json';
  if (auth) {
    const token = getToken();
    if (token) headers.Authorization = `Bearer ${token}`;
  }
  if (idempotencyKey) headers['Idempotency-Key'] = idempotencyKey;

  const send = () => fetch(BASE + path, {
    method,
    headers,
    body: body === undefined ? undefined : JSON.stringify(body),
    // Authentication is the explicit bearer header. Same-origin credentials
    // are sent so a hosting gateway can recognise its own preview session;
    // no cookie is ever read by the engine, and a cross-origin request cannot
    // carry either the cookie or the token implicitly.
    credentials: 'same-origin',
  });

  let response;
  try {
    response = await send();

    // Some preview gateways answer the first non-document request of a fresh
    // session with an HTML 403 before their session cookie is settled. A safe
    // GET is retried once, and only when the body is not our JSON envelope, so
    // a genuine authorisation failure is never retried or hidden.
    const contentType = response.headers.get('content-type') || '';
    if (method === 'GET' && response.status === 403 && !contentType.includes('json')) {
      await new Promise((resolve) => setTimeout(resolve, 180));
      response = await send();
    }
  } catch (cause) {
    throw new ApiError('The server could not be reached.', { code: 'NETWORK' });
  }

  let envelope = null;
  const text = await response.text();
  if (text) {
    try {
      envelope = JSON.parse(text);
    } catch {
      throw new ApiError('The server sent a response that could not be read.', {
        status: response.status,
        code: 'MALFORMED',
      });
    }
  }

  if (response.status === 401 && auth) {
    // The token is gone or expired; drop it so the shell shows the signed-out
    // state rather than looping on failing requests.
    clearSession();
  }

  if (!response.ok || (envelope && envelope.ok === false)) {
    const error = (envelope && envelope.error) || {};
    throw new ApiError(error.message || `Request failed (${response.status}).`, {
      status: response.status,
      code: error.code || 'ERROR',
      details: error.details || null,
    });
  }

  return envelope ? envelope.data : null;
}

export const api = {
  // Public
  lobby: (category) => request('GET', `/games/lobby${category ? `?category=${encodeURIComponent(category)}` : ''}`, { auth: false }),
  games: (category) => request('GET', `/games${category ? `?category=${encodeURIComponent(category)}` : ''}`, { auth: false }),
  presentation: (gameId) => request('GET', `/games/${encodeURIComponent(gameId)}/presentation`, { auth: false }),

  // Auth
  login: (username, password) => request('POST', '/auth/login', { body: { username, password }, auth: false }),
  register: (username, password, displayName) => request('POST', '/auth/register', {
    body: { username, password, display_name: displayName },
    auth: false,
  }),
  me: () => request('GET', '/auth/me'),
  logout: () => request('POST', '/auth/logout', { body: {} }),

  // Play
  createSession: (gameId) => request('POST', '/sessions', { body: { game_id: gameId } }),
  sessions: () => request('GET', '/sessions'),
  session: (id) => request('GET', `/sessions/${encodeURIComponent(id)}`),
  closeSession: (id) => request('POST', `/sessions/${encodeURIComponent(id)}/close`, { body: {} }),

  action: (sessionId, action, { bet, payload, idempotencyKey } = {}) => {
    const body = { action };
    if (bet !== undefined && bet !== null) body.bet = bet;
    if (payload !== undefined) body.payload = payload;
    return request('POST', `/sessions/${encodeURIComponent(sessionId)}/actions`, { body, idempotencyKey });
  },

  // Wallet and history
  wallet: () => request('GET', '/wallet'),
  transactions: () => request('GET', '/wallet/transactions'),
  rounds: (limit = 25) => request('GET', `/history/rounds?limit=${limit}`),
  statistics: () => request('GET', '/statistics'),

  // Administration
  admin: {
    overview: () => request('GET', '/admin/overview'),
    globalProfile: () => request('GET', '/admin/probability/global'),
    saveGlobal: (values) => request('POST', '/admin/probability/global', { body: values }),
    gameProfile: (gameId) => request('GET', `/admin/probability/games/${encodeURIComponent(gameId)}`),
    saveGame: (gameId, values) => request('POST', `/admin/probability/games/${encodeURIComponent(gameId)}`, { body: values }),
    resetGame: (gameId) => request('POST', `/admin/probability/games/${encodeURIComponent(gameId)}/reset`, { body: {} }),
    games: () => request('GET', '/admin/probability/games'),
    audit: (limit = 100) => request('GET', `/admin/audit?limit=${limit}`),
    versions: (profile = 'global') => request('GET', `/admin/probability/versions?profile=${encodeURIComponent(profile)}`),
    restore: (profile, version) => request('POST', '/admin/probability/restore', { body: { profile, version } }),
    simulate: (values) => request('POST', '/admin/simulate', { body: values }),
    jackpots: (gameId) => request('GET', `/admin/jackpots${gameId ? `?game_id=${encodeURIComponent(gameId)}` : ''}`),
  },
};
