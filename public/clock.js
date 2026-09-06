/* Gedeelde helpers voor de klokweergave. */

function formatDuration(totalSeconds) {
  const seconds = Math.max(0, Math.round(totalSeconds));
  const hh = String(Math.floor(seconds / 3600)).padStart(2, '0');
  const mm = String(Math.floor((seconds % 3600) / 60)).padStart(2, '0');
  const ss = String(seconds % 60).padStart(2, '0');
  return `${hh}:${mm}:${ss}`;
}

function remainingSeconds(endsAt) {
  return Math.max(0, (new Date(endsAt).getTime() - Date.now()) / 1000);
}

function playAlarm() {
  const AudioCtx = window.AudioContext || window.webkitAudioContext;
  if (!AudioCtx) return;
  const ctx = new AudioCtx();
  const beep = (start) => {
    const osc = ctx.createOscillator();
    const gain = ctx.createGain();
    osc.type = 'square';
    osc.frequency.value = 880;
    gain.gain.setValueAtTime(0.0001, ctx.currentTime + start);
    gain.gain.exponentialRampToValueAtTime(0.3, ctx.currentTime + start + 0.02);
    gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + start + 0.45);
    osc.connect(gain).connect(ctx.destination);
    osc.start(ctx.currentTime + start);
    osc.stop(ctx.currentTime + start + 0.5);
  };
  [0, 0.6, 1.2, 1.8].forEach(beep);
}

async function api(url, options) {
  const response = await fetch(url, {
    headers: { 'Content-Type': 'application/json' },
    ...options,
  });
  const data = await response.json().catch(() => ({}));
  if (!response.ok) throw new Error(data.error || 'Er ging iets mis');
  return data;
}
