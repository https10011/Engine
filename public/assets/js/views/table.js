// Table-game dispatcher.
//
// `/tables/{id}` is one route for two games, and `/slots/{id}` falls through to
// here when the id turns out to be a table game. The category in the
// presentation payload decides which view runs, so a new table game is a new
// module plus one line here rather than a new route.

import { renderBlackjack } from './blackjack.js';
import { renderRoulette } from './roulette.js';
import { renderCoinflip } from './coinflip.js';
import { errorState } from '../lib/ui.js';

export async function renderTable(context, presentation = null) {
  let payload = presentation;

  if (!payload) {
    const { api } = await import('../lib/api.js');
    try {
      payload = await api.presentation(context.route.id);
    } catch (error) {
      return { node: errorState(error.message || 'The table could not be loaded.', () => context.navigate('/')) };
    }
  }

  if (context.isStale()) {
    return { node: document.createElement('div') };
  }

  // Coin Flip is registered under the `card` category because it is a
  // single-action card game, but it is not blackjack. Dispatch on the module
  // id/tags before the category so it gets its own interface.
  const tags = payload.game?.tags || [];
  if (payload.game?.id === 'coinflip.heads-tails' || tags.includes('example')) {
    return renderCoinflip(context, payload);
  }

  switch (payload.category) {
    case 'card':
      return renderBlackjack(context);
    case 'roulette':
      return renderRoulette(context);
    default:
      return {
        node: errorState(
          `${payload.game?.name || 'That game'} has no table interface yet.`,
          () => context.navigate('/')
        ),
      };
  }
}
