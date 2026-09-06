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

  function updateBoardView(data) {
    var section = document.querySelector('[data-board-view]');
    if (!section) return;

    var clock = section.querySelector('.clock');
    var meta = section.querySelector('[data-board-meta]');
    var expiredMessage = section.querySelector('[data-expired-message]');
    var certification = data.certification;

    if (certification) {
      if (clock) {
        clock.setAttribute('data-ends-at', certification.endsAt);
        clock.classList.remove('clock--idle');
      }
      if (meta) {
        meta.innerHTML =
          'PERID <strong data-board-perid>' + escapeHtml(certification.perid) + '</strong> \u00b7 ' +
          '<span data-board-location>' + escapeHtml(certification.location) + '</span>';
      }
      if (clock && clock.dataset.alarmPlayed === 'true' && !certification.finished) {
        clock.dataset.alarmPlayed = 'false';
        if (expiredMessage) expiredMessage.hidden = true;
      }
    } else {
      if (clock) {
        clock.setAttribute('data-ends-at', '');
        clock.classList.add('clock--idle');
        clock.textContent = '--:--:--';
        clock.dataset.alarmPlayed = 'false';
      }
      if (meta) {
        meta.textContent = 'Er loopt momenteel geen certificatie op dit bord.';
      }
      if (expiredMessage) expiredMessage.hidden = true;
    }
  }

  function escapeHtml(value) {
    var div = document.createElement('div');
    div.textContent = String(value);
    return div.innerHTML;
  }

  function pollBoardView() {
    var section = document.querySelector('[data-board-view]');
    if (!section) return;
    var url = section.getAttribute('data-poll-url');
    if (!url) return;

    fetch(url, { headers: { Accept: 'application/json' } })
      .then(function (response) { return response.ok ? response.json() : null; })
      .then(function (data) { if (data) updateBoardView(data); })
      .catch(function () { /* stille herhaling bij een tijdelijke netwerkfout */ });
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-enable-sound]').forEach(function (button) {
      button.addEventListener('click', function () {
        enableSound();
        button.textContent = 'Geluid staat aan';
        button.disabled = true;
      });
    });

    // Voorkomt dubbele submits (bv. dubbelklikken op "Starten" of "Stoppen").
    document.querySelectorAll('form').forEach(function (form) {
      form.addEventListener('submit', function () {
        if (form.dataset.submitted === 'true') {
          return;
        }
        form.dataset.submitted = 'true';
        form.querySelectorAll('button[type="submit"]').forEach(function (button) {
          button.disabled = true;
          button.dataset.originalText = button.textContent;
          button.textContent = 'Bezig...';
        });
      });
    });

    tick();
    setInterval(tick, 1000);

    // Ververst de klok van dit bord zonder volledige paginaherlaad, zodat een
    // net gestarte of gestopte certificatie meteen zichtbaar is.
    if (document.querySelector('[data-board-view]')) {
      pollBoardView();
      setInterval(pollBoardView, 5000);
    }
  });
})();
