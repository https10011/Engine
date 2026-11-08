// The administrator dashboard.
//
// Everything here is a control over the *server's* configuration service. The
// page never computes an outcome and never fakes a probability: it reads the
// effective profile the engine resolved, posts a candidate, and shows what the
// engine's own simulation driver observed against it.
//
// The six metrics are presented as six separate controls with their
// explanations attached, because the directive's central warning is that
// any-win, net-positive, RTP, jackpot, bonus and free-spin probabilities are
// different questions and must not be conflated. The panel says so in the
// interface, not only in the documentation.

import { el, mount, clear, money, percent } from '../lib/dom.js';
import { api, ApiError } from '../lib/api.js';
import { toast, errorState, loadingState } from '../lib/ui.js';
import * as sound from '../lib/sound.js';

const METRIC_ORDER = [
  'any_win_target',
  'net_positive_target',
  'rtp_target',
  'jackpot_probability',
  'bonus_trigger_probability',
  'free_spin_trigger_probability',
  'payout_scale',
];

const FALLBACK_METRICS = {
  any_win_target: { label: 'Any-win probability', unit: 'probability', help: 'The share of rounds returning anything at all.' },
  net_positive_target: { label: 'Net-positive probability', unit: 'probability', help: 'The share of rounds returning more than the stake.' },
  rtp_target: { label: 'RTP target', unit: 'ratio', help: 'Total returned per unit wagered over many rounds.' },
  jackpot_probability: { label: 'Jackpot probability', unit: 'probability', help: 'Chance of a jackpot trigger.' },
  bonus_trigger_probability: { label: 'Bonus trigger probability', unit: 'probability', help: 'Chance of starting a bonus feature.' },
  free_spin_trigger_probability: { label: 'Free spin trigger probability', unit: 'probability', help: 'Chance of awarding free spins.' },
  payout_scale: { label: 'Payout scale', unit: 'ratio', help: 'A multiplier applied to payouts.' },
};

const TABS = [
  { id: 'overview', label: 'Overview' },
  { id: 'global', label: 'Global settings' },
  { id: 'games', label: 'Individual games' },
  { id: 'simulate', label: 'Simulation' },
  { id: 'history', label: 'History & audit' },
];

export async function renderAdmin(context) {
  const node = el('div', { class: 'fade-in admin' });
  let active = 'overview';
  let metrics = FALLBACK_METRICS;

  const tabBar = el('div', { class: 'tabs tabs--admin', role: 'tablist', 'aria-label': 'Administrator sections' });
  const body = el('div', { class: 'admin__body' });

  const tabButtons = TABS.map((tab) => el('button', {
    class: 'tab',
    type: 'button',
    role: 'tab',
    text: tab.label,
    'aria-selected': tab.id === active ? 'true' : 'false',
    onClick: () => show(tab.id),
  }));

  mount(tabBar, tabButtons);
  mount(node, [
    el('section', { class: 'hero hero--admin' }, [
      el('h1', { text: 'Administrator panel' }),
      el('p', {
        text: 'Configure the simulated casino’s game odds. Every setting here is applied by the engine to newly '
          + 'initialised rounds; nothing on this page decides an outcome, and no setting can alter a round that has '
          + 'already settled. Virtual credits only.',
      }),
    ]),
    tabBar,
    body,
  ]);

  function setTab(id) {
    active = id;
    tabButtons.forEach((button, index) => {
      button.setAttribute('aria-selected', TABS[index].id === id ? 'true' : 'false');
    });
  }

  async function show(id) {
    setTab(id);
    mount(body, [loadingState(3)]);
    try {
      const loaders = {
        overview: renderOverview,
        global: renderGlobal,
        games: renderGames,
        simulate: renderSimulate,
        history: renderHistoryTab,
      };
      const section = await loaders[id]();
      if (context.isStale()) return;
      mount(body, [section]);
    } catch (error) {
      if (context.isStale()) return;
      if (error instanceof ApiError && (error.status === 401 || error.status === 403)) {
        mount(body, [errorState(
          'This account is not an administrator. Ask an operator to run `php bin/console user:admin --username=<you>`.',
          () => show(id)
        )]);
        return;
      }
      mount(body, [errorState(error instanceof ApiError ? error.message : 'That section could not be loaded.', () => show(id))]);
    }
  }

  /* ------------------------------------------------------------- overview */

  async function renderOverview() {
    const data = await api.admin.overview();
    metrics = data.metrics || metrics;

    const profile = data.global;
    const counts = data.counts;

    const summary = el('div', { class: 'admin__stats' }, [
      stat('Games registered', String(counts.games)),
      stat('Games enabled', String(counts.enabled)),
      stat('Per-game overrides', String(counts.overrides)),
      stat('Global profile', profile.enabled ? `v${profile.version}` : 'not active'),
    ]);

    const effectiveList = el('div', { class: 'metric-list' });
    mount(effectiveList, METRIC_ORDER.map((key) => metricRow(key, profile[key], metrics[key])));

    return el('div', { class: 'admin__section' }, [
      summary,
      el('div', { class: 'panel' }, [
        el('h2', { text: 'Global probability profile' }),
        el('p', { class: 'muted', text: profile.enabled
          ? 'This profile applies to every game without its own override.'
          : 'No global profile is active. Games run with the engine’s own behaviour until a profile is enabled.' }),
        effectiveList,
        el('div', { class: 'admin__actions' }, [
          el('button', { class: 'btn btn--primary', type: 'button', onClick: () => show('global') }, 'Edit global settings'),
          el('button', { class: 'btn btn--ghost', type: 'button', onClick: () => show('games') }, 'Per-game overrides'),
        ]),
      ]),
      metricExplainer(metrics),
    ]);
  }

  function stat(label, value) {
    return el('div', { class: 'stat' }, [
      el('span', { class: 'stat__label', text: label }),
      el('strong', { class: 'stat__value mono', text: value }),
    ]);
  }

  function metricRow(key, value, meta = {}) {
    const isProbability = meta.unit === 'probability';
    return el('div', { class: 'metric-row' }, [
      el('div', { class: 'metric-row__name' }, [
        el('strong', { text: meta.label || key }),
        el('small', { class: 'muted', text: meta.help || '' }),
      ]),
      el('span', { class: 'metric-row__value mono', text: value === null || value === undefined
        ? 'not configured'
        : isProbability ? percent(value) : String(value) }),
    ]);
  }

  function metricExplainer(metrics) {
    return el('div', { class: 'panel panel--note' }, [
      el('h2', { text: 'These are different questions' }),
      el('ul', { class: 'notes' }, [
        el('li', {}, [el('strong', { text: 'Any-win' }), ' counts a round that returns anything, including less than the stake.']),
        el('li', {}, [el('strong', { text: 'Net-positive' }), ' counts a round that returns more than the stake. It is the stricter measure.']),
        el('li', {}, [el('strong', { text: 'RTP' }), ' is total returned per unit wagered across many rounds — not a per-round chance.']),
        el('li', {}, [el('strong', { text: 'Jackpot, bonus and free-spin' }), ' probabilities are configured separately and only apply to games that have those mechanics.']),
      ]),
      el('p', { class: 'muted', text: 'A target is a distribution goal the engine steers towards by re-drawing a genuine outcome; it never rewrites a losing round into a winner. A target the mechanics cannot reach is reported as a gap in the simulation report rather than silently claimed.' }),
    ]);
  }

  /* --------------------------------------------------------------- global */

  async function renderGlobal() {
    const data = await api.admin.globalProfile();
    metrics = data.metrics || metrics;
    return profileEditor({
      title: 'Global probability settings',
      description: 'These values apply to every game that does not have its own override. Saving creates a new version and records the change in the audit log.',
      profile: data.profile,
      supported: METRIC_ORDER,
      onSave: async (values) => {
        const result = await api.admin.saveGlobal(values);
        toast(`Global profile saved (v${result.profile.version}). ${result.affected_games.length} games inherit it.`, 'win');
        sound.sfx.win(2);
      },
      extraActions: [],
    });
  }

  /* ---------------------------------------------------------------- games */

  async function renderGames() {
    const data = await api.admin.games();
    const games = data.games || [];

    const list = el('div', { class: 'game-configs' });

    mount(list, games.map((row) => {
      const card = el('div', { class: `config-card ${row.source === 'override' ? 'config-card--override' : ''}` }, [
        el('div', { class: 'config-card__head' }, [
          el('div', { class: 'config-card__titles' }, [
            el('strong', { text: row.game.name }),
            el('span', { class: 'muted mono', text: row.game.id }),
          ]),
          el('span', { class: `tag ${row.source === 'override' ? 'tag--info' : ''}`, text: row.source === 'override' ? 'override' : 'global' }),
        ]),
        el('div', { class: 'config-card__metrics' }, METRIC_ORDER
          .filter((key) => row.supported.includes(key))
          .map((key) => metricRow(key, row.effective[key], metrics[key]))),
        el('div', { class: 'config-card__actions' }, [
          el('button', {
            class: 'btn btn--ghost',
            type: 'button',
            onClick: () => editGame(row),
          }, row.source === 'override' ? 'Edit override' : 'Create override'),
          row.source === 'override'
            ? el('button', {
                class: 'btn btn--ghost',
                type: 'button',
                onClick: async () => {
                  await api.admin.resetGame(row.game.id);
                  toast(`${row.game.name} now follows the global profile.`);
                  show('games');
                },
              }, 'Reset to global')
            : null,
        ]),
      ]);

      return card;
    }));

    return el('div', { class: 'admin__section' }, [
      el('div', { class: 'panel panel--note' }, [
        el('h2', { text: 'Global and per-game configuration' }),
        el('p', { class: 'muted', text: 'A game with an override uses its own settings; every other game follows the global profile. The “effective” column below is resolved by the same service the round service asks, so what you see is what the engine will apply.' }),
      ]),
      list,
    ]);
  }

  async function editGame(row) {
    const data = await api.admin.gameProfile(row.game.id);
    const section = profileEditor({
      title: `Override for ${row.game.name}`,
      description: 'An override takes precedence over the global profile for this game only.',
      profile: data.override || data.effective,
      supported: data.supported,
      onSave: async (values) => {
        const result = await api.admin.saveGame(row.game.id, values);
        toast(`Override saved for ${row.game.name} (v${result.override.version}).`, 'win');
        sound.sfx.win(2);
      },
      extraActions: [el('button', { class: 'btn btn--ghost', type: 'button', onClick: () => show('games') }, 'Back to list')],
    });

    mount(body, [section]);
  }

  /* ------------------------------------------------------------ simulation */

  async function renderSimulate() {
    const gamesData = await api.admin.games();
    const games = (gamesData.games || []).filter((row) => row.game.enabled);

    let selected = games[0]?.game.id || '';
    let rounds = 20000;
    let seed = '';
    let lastReport = null;

    const gameSelect = el('select', { class: 'field__input', 'aria-label': 'Game to simulate' },
      games.map((row) => el('option', { value: row.game.id, text: row.game.name })));

    const roundsInput = el('input', {
      class: 'field__input', type: 'number', min: '100', max: '1000000', step: '1000',
      value: String(rounds), 'aria-label': 'Number of rounds',
    });

    const seedInput = el('input', {
      class: 'field__input', type: 'text', inputmode: 'numeric', placeholder: 'optional',
      'aria-label': 'Seed (optional)',
    });

    const report = el('div', { class: 'sim-report' });

    const run = el('button', { class: 'btn btn--primary', type: 'button' }, 'Run simulation');
    run.addEventListener('click', async () => {
      selected = gameSelect.value;
      rounds = Math.max(100, Math.min(1000000, Number(roundsInput.value) || 20000));
      seed = seedInput.value.trim();

      run.disabled = true;
      run.textContent = 'Simulating…';
      mount(report, [loadingState(2)]);

      try {
        const body = { game_id: selected, rounds };
        if (seed !== '') body.seed = Number(seed);
        lastReport = await api.admin.simulate(body);
        paintReport(report, lastReport);
        sound.sfx.win(1);
      } catch (error) {
        mount(report, [errorState(error instanceof ApiError ? error.message : 'The simulation failed.', () => run.click())]);
      } finally {
        run.disabled = false;
        run.textContent = 'Run simulation';
      }
    });

    return el('div', { class: 'admin__section' }, [
      el('div', { class: 'panel panel--note' }, [
        el('h2', { text: 'Probability verification' }),
        el('p', { class: 'muted', text: 'Runs the engine’s own simulation driver against the saved configuration for a game. The report distinguishes what was configured from what the sample observed, and it never touches a wallet, a session or a live round.' }),
      ]),
      el('div', { class: 'panel sim-form' }, [
        el('label', { class: 'field' }, [el('span', { class: 'field__label', text: 'Game' }), gameSelect]),
        el('label', { class: 'field' }, [el('span', { class: 'field__label', text: 'Rounds' }), roundsInput]),
        el('label', { class: 'field' }, [el('span', { class: 'field__label', text: 'Seed' }), seedInput]),
        el('div', { class: 'admin__actions' }, [run]),
      ]),
      report,
    ]);
  }

  function paintReport(host, report) {
    const observed = report.observed || {};
    const configured = report.configured || {};

    const rows = [
      ['Rounds', String(observed.rounds ?? report.rounds)],
      ['Winning rounds', String(observed.winning_rounds ?? '—')],
      ['Any-win frequency', fmt(observed.observed_hit_frequency)],
      ['Net-positive frequency', fmt(observed.observed_win_frequency)],
      ['Total wagered', money(observed.total_wagered_minor ?? 0, 2, '')],
      ['Total returned', money(observed.total_returned_minor ?? 0, 2, '')],
      ['Observed RTP', fmt(observed.observed_rtp)],
      ['RTP 95% interval', observed.observed_rtp_ci95
        ? `${fmt(observed.observed_rtp_ci95.low)} – ${fmt(observed.observed_rtp_ci95.high)}`
        : '—'],
      ['Max return multiple', `${observed.max_return_multiple ?? '—'}×`],
      ['Feature trigger rate', fmt(observed.feature_trigger_rate)],
    ];

    const comparison = el('div', { class: 'comparison' });
    mount(comparison, Object.entries(report.comparison || {}).map(([key, row]) => el('div', { class: 'comparison__row' }, [
      el('span', { class: 'comparison__name', text: (metrics[key]?.label) || key }),
      el('span', { class: 'mono', text: row.configured === null || row.configured === undefined ? 'not configured' : fmt(row.configured) }),
      el('span', { class: 'mono', text: row.observed === null || row.observed === undefined ? '—' : fmt(row.observed) }),
      el('span', {
        class: `mono ${Math.abs(Number(row.delta ?? 0)) > 0.02 ? 'net--down' : 'net--up'}`,
        text: row.delta === null || row.delta === undefined ? '—' : (row.delta >= 0 ? '+' : '') + Number(row.delta).toFixed(4),
      }),
    ])));

    mount(host, [
      el('div', { class: 'panel' }, [
        el('h2', { text: `${report.game_name} — ${report.rounds.toLocaleString()} rounds` }),
        el('table', { class: 'table' }, [
          el('thead', {}, [el('tr', {}, [
            el('th', { text: 'Observed figure' }), el('th', { class: 'num', text: 'Value' }),
          ])]),
          el('tbody', {}, rows.map(([label, value]) => el('tr', {}, [
            el('td', { text: label }), el('td', { class: 'num mono', text: value }),
          ]))),
        ]),
        el('p', { class: 'muted', text: report.note || '' }),
      ]),
      el('div', { class: 'panel' }, [
        el('h2', { text: 'Configured versus observed' }),
        el('div', { class: 'comparison comparison--head' }, [
          el('span', { text: 'Metric' }),
          el('span', { text: 'Configured' }),
          el('span', { text: 'Observed' }),
          el('span', { text: 'Delta' }),
        ]),
        comparison,
      ]),
      el('div', { class: 'panel panel--note' }, [
        el('h2', { text: 'Active configuration under test' }),
        el('div', { class: 'metric-list' }, METRIC_ORDER.map((key) => metricRow(key, configured[key], metrics[key]))),
      ]),
    ]);
  }

  /* --------------------------------------------------------------- history */

  async function renderHistoryTab() {
    const [versions, audit] = await Promise.all([api.admin.versions('global'), api.admin.audit(50)]);

    const versionRows = el('tbody');
    mount(versionRows, (versions.versions || []).map((row) => el('tr', {}, [
      el('td', { class: 'mono', text: `v${row.version}` }),
      el('td', { class: 'muted', text: formatWhen(row.created_at) }),
      el('td', { class: 'muted', text: row.created_by ? `user ${row.created_by}` : 'system' }),
      el('td', {}, [el('button', {
        class: 'btn btn--ghost',
        type: 'button',
        onClick: async () => {
          await api.admin.restore('global', row.version);
          toast(`Restored version ${row.version} as a new version.`, 'win');
          show('history');
        },
      }, 'Restore')]),
    ])));

    const auditRows = el('tbody');
    mount(auditRows, (audit.entries || []).map((row) => el('tr', {}, [
      el('td', { class: 'muted', text: formatWhen(row.created_at) }),
      el('td', { text: row.admin_username }),
      el('td', { text: row.action }),
      el('td', { class: 'mono', text: row.profile_name }),
      el('td', { text: row.description }),
      el('td', { class: 'mono', text: `v${row.version}` }),
    ])));

    return el('div', { class: 'admin__section' }, [
      el('div', { class: 'panel' }, [
        el('h2', { text: 'Configuration versions' }),
        el('p', { class: 'muted', text: 'History is append-only. Restoring a version re-saves it as a new version, so the record of what was configured when is preserved.' }),
        el('div', { class: 'table-wrap' }, [el('table', { class: 'table' }, [
          el('thead', {}, [el('tr', {}, [
            el('th', { text: 'Version' }), el('th', { text: 'When' }), el('th', { text: 'By' }), el('th', { text: '' }),
          ])]),
          versionRows,
        ])]),
      ]),
      el('div', { class: 'panel' }, [
        el('h2', { text: 'Audit log' }),
        el('div', { class: 'table-wrap' }, [el('table', { class: 'table' }, [
          el('thead', {}, [el('tr', {}, [
            el('th', { text: 'When' }), el('th', { text: 'Administrator' }), el('th', { text: 'Action' }),
            el('th', { text: 'Profile' }), el('th', { text: 'Description' }), el('th', { text: 'Version' }),
          ])]),
          auditRows,
        ])]),
      ]),
    ]);
  }

  /* ---------------------------------------------------------------- shared */

  /**
   * A profile editor: one control per metric, plus an enable toggle.
   *
   * Only the metrics the game supports are offered. An administrator is never
   * shown a control for a mechanic the game does not have, because a slider
   * that cannot do anything is worse than no slider.
   */
  function profileEditor({ title, description, profile, supported, onSave, extraActions }) {
    const values = {};
    const controls = el('div', { class: 'editor' });

    const enabled = el('input', { type: 'checkbox', checked: Boolean(profile.enabled) });

    for (const key of METRIC_ORDER) {
      if (supported && !supported.includes(key)) continue;
      const meta = metrics[key] || {};
      const isProbability = meta.unit === 'probability';
      const current = profile[key];

      if (isProbability) {
        const numberInput = el('input', {
          class: 'field__input', type: 'number', min: '0', max: '100', step: '0.1',
          value: current === null || current === undefined ? '' : String(Number(current) * 100),
          placeholder: 'not configured',
          'aria-label': `${meta.label} percent`,
        });
        const slider = el('input', {
          class: 'slider', type: 'range', min: '0', max: '100', step: '0.5',
          value: current === null || current === undefined ? '0' : String(Number(current) * 100),
          'aria-label': `${meta.label} slider`,
        });

        slider.addEventListener('input', () => { numberInput.value = slider.value; });
        numberInput.addEventListener('input', () => { slider.value = numberInput.value || '0'; });

        values[key] = () => (numberInput.value === '' ? null : Number(numberInput.value) / 100);

        controls.append(el('div', { class: 'editor__row' }, [
          el('div', { class: 'editor__label' }, [
            el('strong', { text: meta.label }),
            el('small', { class: 'muted', text: meta.help || '' }),
          ]),
          el('div', { class: 'editor__control' }, [slider, numberInput, el('span', { class: 'editor__unit', text: '%' })]),
        ]));
      } else {
        const input = el('input', {
          class: 'field__input', type: 'number', step: '0.01',
          value: current === null || current === undefined ? '' : String(current),
          placeholder: 'not configured',
          'aria-label': meta.label,
        });
        values[key] = () => (input.value === '' ? null : Number(input.value));

        controls.append(el('div', { class: 'editor__row' }, [
          el('div', { class: 'editor__label' }, [
            el('strong', { text: meta.label }),
            el('small', { class: 'muted', text: meta.help || '' }),
          ]),
          el('div', { class: 'editor__control' }, [input]),
        ]));
      }
    }

    const saveButton = el('button', { class: 'btn btn--primary', type: 'button' }, 'Save and apply');
    saveButton.addEventListener('click', async () => {
      const payload = { enabled: enabled.checked };
      for (const [key, read] of Object.entries(values)) payload[key] = read();

      saveButton.disabled = true;
      saveButton.textContent = 'Saving…';
      try {
        await onSave(payload);
      } catch (error) {
        toast(error instanceof ApiError ? error.message : 'The configuration could not be saved.', 'error');
        sound.sfx.error();
      } finally {
        saveButton.disabled = false;
        saveButton.textContent = 'Save and apply';
      }
    });

    return el('div', { class: 'panel' }, [
      el('h2', { text: title }),
      el('p', { class: 'muted', text: description }),
      el('label', { class: 'editor__toggle' }, [
        enabled,
        el('span', { text: 'Enable this profile (when off, the engine runs with its own behaviour)' }),
      ]),
      controls,
      el('div', { class: 'admin__actions' }, [saveButton, ...extraActions]),
    ]);
  }

  await show('overview');

  return { node };
}

function fmt(value) {
  if (value === null || value === undefined) return '—';
  const n = Number(value);
  if (Number.isNaN(n)) return String(value);
  return n.toFixed(4).replace(/0+$/, '').replace(/\.$/, '');
}

function formatWhen(value) {
  if (!value) return '—';
  const date = new Date(String(value).replace(' ', 'T') + 'Z');
  return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleString();
}
