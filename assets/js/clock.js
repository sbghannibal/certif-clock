/* Live aftelklok, waarschuwings-/verlopen-status en alarm bij het einde. */

(function () {
  'use strict';

  var soundEnabled = false;
  var audioContext = null;

  function formatDuration(totalSeconds) {
    var seconds = Math.max(0, Math.round(totalSeconds));
    var hh = String(Math.floor(seconds / 3600)).padStart(2, '0');
    var mm = String(Math.floor((seconds % 3600) / 60)).padStart(2, '0');
    var ss = String(seconds % 60).padStart(2, '0');
    return hh + ':' + mm + ':' + ss;
  }

  function enableSound() {
    var AudioCtx = window.AudioContext || window.webkitAudioContext;
    if (!AudioCtx) return;
    if (!audioContext) audioContext = new AudioCtx();
    if (audioContext.state === 'suspended') audioContext.resume();
    soundEnabled = true;
  }

  function playAlarm() {
    if (!soundEnabled || !audioContext) return;
    [0, 0.6, 1.2, 1.8].forEach(function (start) {
      var oscillator = audioContext.createOscillator();
      var gain = audioContext.createGain();
      oscillator.type = 'square';
      oscillator.frequency.value = 880;
      gain.gain.setValueAtTime(0.0001, audioContext.currentTime + start);
      gain.gain.exponentialRampToValueAtTime(0.3, audioContext.currentTime + start + 0.02);
      gain.gain.exponentialRampToValueAtTime(0.0001, audioContext.currentTime + start + 0.45);
      oscillator.connect(gain).connect(audioContext.destination);
      oscillator.start(audioContext.currentTime + start);
      oscillator.stop(audioContext.currentTime + start + 0.5);
    });
  }

  function tick() {
    var now = Date.now();
    document.querySelectorAll('.clock[data-ends-at]').forEach(function (element) {
      var endsAt = Date.parse(element.getAttribute('data-ends-at'));
      if (isNaN(endsAt)) return;

      var remaining = Math.max(0, (endsAt - now) / 1000);
      element.textContent = formatDuration(remaining);
      element.classList.toggle('is-warning', remaining > 0 && remaining <= 300);
      element.classList.toggle('is-expired', remaining <= 0);

      if (remaining <= 0 && element.dataset.alarmPlayed !== 'true') {
        element.dataset.alarmPlayed = 'true';
        playAlarm();
        var message = document.querySelector('[data-expired-message]');
        if (message) message.hidden = false;
      }
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-enable-sound]').forEach(function (button) {
      button.addEventListener('click', function () {
        enableSound();
        button.textContent = 'Geluid staat aan';
        button.disabled = true;
      });
    });

    tick();
    setInterval(tick, 1000);

    // Haalt periodiek de status op zodat een nieuwe of gestopte certificatie zichtbaar wordt.
    if (document.querySelector('[data-board-view]')) {
      setInterval(function () {
        window.location.reload();
      }, 60000);
    }
  });
})();
