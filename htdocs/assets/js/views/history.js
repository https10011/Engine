// Play history and wallet activity.
//
// Read-only: the rounds the engine settled and the ledger entries the wallet
// service wrote. Nothing here is computed; the figures are the ones the server
// returned, which is what makes the page useful as a check on what actually
// happened.

import { el, mount, money } from '../lib/dom.js';
import { api, ApiError } from '../lib/api.js';
import { errorState, emptyState } from '../lib/ui.js';
import { ratio } from '../lib/dom.js';

export async function renderHistory(context) {
  const node = el('div', { class: 'fade-in' });

  const roundsBody = el('tbody');
  const ledgerBody = el('tbody');
  const roundsEmpty = el('p', { class: 'muted', text: 'No rounds yet.' });
  const ledgerEmpty = el('p', { class: 'muted', text: 'No wallet activity yet.' });

  const layout = el('div', { class: 'history' }, [
    el('section', { class: 'hero' }, [
      el('h1', { text: 'Your play' }),
      el('p', { text: 'Every round the engine settled for this account, and every movement in the simulated-credit wallet.' }),
    ]),
    el('div', { class: 'panel' }, [
      el('h2', { text: 'Recent rounds' }),
      roundsEmpty,
      el('div', { class: 'table-wrap' }, [el('table', { class: 'table', hidden: true }, [
        el('thead', {}, [el('tr', {}, [
          el('th', { text: 'Round' }),
          el('th', { text: 'Game' }),
          el('th', { text: 'Action' }),
          el('th', { class: 'num', text: 'Bet' }),
          el('th', { class: 'num', text: 'Payout' }),
          el('th', { class: 'num', text: 'Net' }),
          el('th', { text: 'When' }),
        ])]),
        roundsBody,
      ])]),
    ]),
    el('div', { class: 'panel' }, [
      el('h2', { text: 'Wallet activity' }),
      ledgerEmpty,
      el('div', { class: 'table-wrap' }, [el('table', { class: 'table', hidden: true }, [
        el('thead', {}, [el('tr', {}, [
          el('th', { text: 'Type' }),
          el('th', { text: 'Label' }),
          el('th', { class: 'num', text: 'Amount' }),
          el('th', { class: 'num', text: 'Balance after' }),
          el('th', { text: 'When' }),
        ])]),
        ledgerBody,
      ])]),
    ]),
  ]);

  mount(node, [layout]);

  try {
    const [roundsData, ledgerData] = await Promise.all([
      api.rounds(50),
      api.transactions(),
    ]);
    if (context.isStale()) return { node };

    const rounds = roundsData.rounds || [];
    const entries = ledgerData.transactions || ledgerData.entries || [];

    if (rounds.length > 0) {
      layout.querySelectorAll('table')[0].hidden = false;
      roundsEmpty.remove();
      mount(roundsBody, rounds.map((round) => {
        // `/history/rounds` reports flat minor-unit integers; accept a money
        // object too so the table keeps working if the envelope is adopted.
        const bet = minorOf(round.bet);
        const payout = minorOf(round.payout);
        const net = minorOf(round.net) || payout - bet;
        const scale = round.bet?.scale ?? 2;

        return el('tr', {}, [
          el('td', { class: 'mono', text: `#${round.round_id ?? round.round_number ?? round.id}` }),
          el('td', { text: round.game_id }),
          el('td', { text: round.action }),
          el('td', { class: 'num', text: money(bet, scale, '') }),
          el('td', { class: 'num', text: money(payout, scale, '') }),
          el('td', { class: 'num' }, [el('span', { class: net > 0 ? 'net--up' : net < 0 ? 'net--down' : '', text: money(net, scale, '') })]),
          el('td', { class: 'muted', text: formatWhen(round.created_at) }),
        ]);
      }));
    }

    if (entries.length > 0) {
      layout.querySelectorAll('table')[1].hidden = false;
      ledgerEmpty.remove();
      mount(ledgerBody, entries.map((entry) => {
        const scale = entry.amount?.scale ?? 2;
        const amount = minorOf(entry.amount ?? entry.amount_minor);
        // The ledger stores debits as positive values; show the direction as a
        // sign so a wager and a payout cannot be read as the same movement.
        const signed = entry.type === 'debit' ? -amount : amount;
        const after = minorOf(entry.balance_after ?? entry.balance_after_minor);
        return el('tr', {}, [
          el('td', { text: entry.type ?? entry.kind ?? '—' }),
          el('td', { text: entry.reason ?? entry.label ?? '—' }),
          el('td', { class: 'num' }, [el('span', { class: signed > 0 ? 'net--up' : signed < 0 ? 'net--down' : '', text: money(signed, scale, '') })]),
          el('td', { class: 'num', text: money(after, scale, '') }),
          el('td', { class: 'muted', text: formatWhen(entry.created_at) }),
        ]);
      }));
    }
  } catch (error) {
    if (context.isStale()) return { node };
    mount(node, [errorState(error instanceof ApiError ? error.message : 'Your history could not be loaded.', () => context.navigate('/history'))]);
  }

  return { node };
}

function minorOf(value) {
  if (typeof value === 'number') return value;
  return Number(value?.amount ?? 0);
}

function formatWhen(value) {
  if (!value) return '—';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return String(value);
  return date.toLocaleString();
}
