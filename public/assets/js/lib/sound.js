// Sound, synthesised in the browser.
//
// There are no audio files: a shared host with a strict CSP and no binary
// assets cannot rely on a licensed sound library, and inventing one would be
// worse than synthesising it. These are short WebAudio tones — a spin whoosh, a
// reel stop, a win chime whose pitch rises with the multiplier — so the
// "sound controls" the directive asks for are real controls over real sound.
//
// The AudioContext is created lazily on the first user gesture, because every
// browser blocks audio before one.

let ctx = null;
let enabled = true;

const STORAGE_KEY = 'casino.sound';

export function init() {
  try {
    const stored = localStorage.getItem(STORAGE_KEY);
    if (stored !== null) enabled = stored === '1';
  } catch {
    enabled = true;
  }
  return enabled;
}

export function isEnabled() {
  return enabled;
}

export function setEnabled(next) {
  enabled = Boolean(next);
  try {
    localStorage.setItem(STORAGE_KEY, enabled ? '1' : '0');
  } catch {
    // Storage disabled: the setting applies to this page view only.
  }
  return enabled;
}

export function toggle() {
  return setEnabled(!enabled);
}

function context() {
  if (!enabled) return null;
  if (ctx) return ctx;
  const Ctor = window.AudioContext || window.webkitAudioContext;
  if (!Ctor) return null;
  try {
    ctx = new Ctor();
  } catch {
    return null;
  }
  return ctx;
}

function tone({ freq = 440, ms = 120, type = 'sine', gain = 0.05, delay = 0, sweepTo = null }) {
  const audio = context();
  if (!audio) return;

  if (audio.state === 'suspended') audio.resume().catch(() => {});

  const start = audio.currentTime + delay;
  const osc = audio.createOscillator();
  const amp = audio.createGain();

  osc.type = type;
  osc.frequency.setValueAtTime(freq, start);
  if (sweepTo) osc.frequency.exponentialRampToValueAtTime(Math.max(40, sweepTo), start + ms / 1000);

  amp.gain.setValueAtTime(0.0001, start);
  amp.gain.exponentialRampToValueAtTime(gain, start + 0.012);
  amp.gain.exponentialRampToValueAtTime(0.0001, start + ms / 1000);

  osc.connect(amp).connect(audio.destination);
  osc.start(start);
  osc.stop(start + ms / 1000 + 0.02);
}

export const sfx = {
  click: () => tone({ freq: 320, ms: 60, type: 'triangle', gain: 0.03 }),
  spin: () => tone({ freq: 180, ms: 380, type: 'sawtooth', gain: 0.025, sweepTo: 520 }),
  reelStop: (index = 0) => tone({ freq: 300 + index * 40, ms: 70, type: 'square', gain: 0.03 }),
  win: (multiplier = 1) => {
    const base = 520;
    const steps = Math.min(5, Math.max(1, Math.ceil(Math.log10(Math.max(1, multiplier) + 1) + 1)));
    for (let i = 0; i < steps; i += 1) {
      tone({ freq: base * (1 + i * 0.18), ms: 150, type: 'triangle', gain: 0.05, delay: i * 0.09 });
    }
  },
  bigWin: () => {
    [523, 659, 784, 1046, 1318].forEach((freq, i) =>
      tone({ freq, ms: 240, type: 'triangle', gain: 0.06, delay: i * 0.11 })
    );
  },
  lose: () => tone({ freq: 200, ms: 200, type: 'sine', gain: 0.03, sweepTo: 120 }),
  card: () => tone({ freq: 420, ms: 55, type: 'square', gain: 0.025 }),
  chip: () => tone({ freq: 900, ms: 45, type: 'square', gain: 0.02 }),
  wheel: () => tone({ freq: 240, ms: 900, type: 'sawtooth', gain: 0.02, sweepTo: 150 }),
  error: () => tone({ freq: 160, ms: 220, type: 'sawtooth', gain: 0.04, sweepTo: 90 }),
};
