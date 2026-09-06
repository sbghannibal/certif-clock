/* Live aftelklok, waarschuwings-/verlopen-status en alarm bij het einde. */

(function () {
  'use strict';

  var SOUND_STORAGE_KEY = 'certif-clock-sound';
  var SOUND_DEFAULT = 'beep';
  var SOUND_CHOICES = ['beep', 'bell', 'chime', 'alert'];
  // Ververs de pagina kort na het aflopen van de timer zodat de status
  // ("Afgelopen" en daarna opnieuw "Vrij") klopt met de database.
  var REFRESH_AFTER_EXPIRY_MS = 3000;

  var soundEnabled = false;
  var audioContext = null;
  var expiryReloadScheduled = false;

  function formatDuration(totalSeconds) {
    var seconds = Math.max(0, Math.round(totalSeconds));
    var hh = String(Math.floor(seconds / 3600)).padStart(2, '0');
    var mm = String(Math.floor((seconds % 3600) / 60)).padStart(2, '0');
    var ss = String(seconds % 60).padStart(2, '0');
    return hh + ':' + mm + ':' + ss;
  }

  function getSelectedSound() {
    try {
      var stored = window.localStorage.getItem(SOUND_STORAGE_KEY);
      if (stored && SOUND_CHOICES.indexOf(stored) !== -1) {
        return stored;
      }
    } catch (error) { /* localStorage niet beschikbaar (bv. private mode) */ }
    return SOUND_DEFAULT;
  }

  function setSelectedSound(value) {
    if (SOUND_CHOICES.indexOf(value) === -1) return;
    try {
      window.localStorage.setItem(SOUND_STORAGE_KEY, value);
    } catch (error) { /* stille fallback: de keuze geldt dan enkel voor deze sessie */ }
  }

  function enableSound() {
    var AudioCtx = window.AudioContext || window.webkitAudioContext;
    if (AudioCtx) {
      if (!audioContext) audioContext = new AudioCtx();
      if (audioContext.state === 'suspended') audioContext.resume();
    }
    soundEnabled = true;
  }

  /* Speelt het gekozen alarm af via het audiobestand; als dat niet lukt
     (offline, ontbrekend bestand) valt hij terug op WebAudio-tonen. */
  function playAlarm() {
    if (!soundEnabled) return;
    var sound = getSelectedSound();
    try {
      var audio = new Audio('/assets/sounds/' + sound + '.mp3');
      var result = audio.play();
      if (result && typeof result.catch === 'function') {
        result.catch(function () { playFallbackAlarm(sound); });
      }
    } catch (error) {
      playFallbackAlarm(sound);
    }
  }

  function tone(frequency, start, duration, type, peak) {
    var oscillator = audioContext.createOscillator();
    var gain = audioContext.createGain();
    oscillator.type = type || 'sine';
    oscillator.frequency.value = frequency;
    gain.gain.setValueAtTime(0.0001, audioContext.currentTime + start);
    gain.gain.exponentialRampToValueAtTime(peak || 0.3, audioContext.currentTime + start + 0.02);
    gain.gain.exponentialRampToValueAtTime(0.0001, audioContext.currentTime + start + duration);
    oscillator.connect(gain).connect(audioContext.destination);
    oscillator.start(audioContext.currentTime + start);
    oscillator.stop(audioContext.currentTime + start + duration + 0.05);
  }

  /* WebAudio-varianten per geluid, gebruikt als de mp3 niet afgespeeld kan worden. */
  function playFallbackAlarm(sound) {
    if (!audioContext) return;
    if (sound === 'bell') {
      [660, 660 * 2.76, 660 * 5.4].forEach(function (frequency, index) {
        tone(frequency, 0, 1.8, 'sine', 0.3 / (index + 1));
      });
      return;
    }
    if (sound === 'chime') {
      [659.25, 783.99, 1046.5, 1318.51].forEach(function (frequency, index) {
        tone(frequency, index * 0.28, 1.1, 'sine', 0.25);
      });
      return;
    }
    if (sound === 'alert') {
      for (var i = 0; i < 3; i++) {
        tone(880, i * 0.64, 0.28, 'square', 0.22);
        tone(1174.66, i * 0.64 + 0.32, 0.28, 'square', 0.22);
      }
      return;
    }
    // beep (standaard)
    [0, 0.6, 1.2, 1.8].forEach(function (start) {
      tone(880, start, 0.45, 'square', 0.3);
    });
  }

  function scheduleExpiryReload() {
    if (expiryReloadScheduled) return;
    expiryReloadScheduled = true;
    setTimeout(function () { window.location.reload(); }, REFRESH_AFTER_EXPIRY_MS);
  }

  /* Zet badges van lopende borden meteen op "Afgelopen" zodra de timer om is. */
  function markExpiredBadges() {
    var label = document.body.getAttribute('data-expired-label') || '';
    if (label === '') return;
    document.querySelectorAll('.badge--live').forEach(function (badge) {
      var card = badge.closest('.board-card');
      if (!card) return;
      var clock = card.querySelector('.clock[data-ends-at]');
      if (!clock || !clock.classList.contains('is-expired')) return;
      badge.classList.remove('badge--live');
      badge.classList.add('badge--idle');
      badge.textContent = label;
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
        markExpiredBadges();
        scheduleExpiryReload();
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
          'PERID <strong data-board-perid>' + escapeHtml(certification.perid) + '</strong> · ' +
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
        meta.textContent = section.getAttribute('data-idle-message') || '';
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

  function initSoundControls() {
    document.querySelectorAll('select[data-sound-select]').forEach(function (select) {
      select.value = getSelectedSound();
      select.addEventListener('change', function () {
        setSelectedSound(select.value);
      });
    });

    document.querySelectorAll('[data-enable-sound]').forEach(function (button) {
      button.addEventListener('click', function () {
        enableSound();
        button.textContent = document.body.getAttribute('data-sound-enabled') || button.textContent;
        button.disabled = true;
      });
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    initSoundControls();

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
          button.textContent = document.body.getAttribute('data-submitting') || button.textContent;
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
