/* Live aftelklok, waarschuwings-/verlopen-status en alarm bij het einde. */

(function () {
  'use strict';

  var SOUND_STORAGE_KEY = 'certif-clock-sound';
  var SOUND_DEFAULT = 'beep';
  var SOUND_CHOICES = ['beep', 'bell', 'chime', 'alert'];
  var VOLUME_STORAGE_KEY = 'certif-clock-volume';
  var VOLUME_DEFAULT = 80;
  // Ververs de pagina kort na het aflopen van de timer zodat de status
  // ("Afgelopen" en daarna opnieuw "Vrij") klopt met de database.
  var REFRESH_AFTER_EXPIRY_MS = 3000;
  var ADMIN_POLL_INTERVAL_MS = 10000;

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

  function getVolume() {
    try {
      var stored = window.localStorage.getItem(VOLUME_STORAGE_KEY);
      if (stored !== null && stored !== '') {
        var value = parseInt(stored, 10);
        if (!isNaN(value) && value >= 0 && value <= 100) {
          return value;
        }
      }
    } catch (error) { /* localStorage niet beschikbaar */ }
    return VOLUME_DEFAULT;
  }

  function setVolume(value) {
    var volume = Math.max(0, Math.min(100, parseInt(value, 10) || 0));
    try {
      window.localStorage.setItem(VOLUME_STORAGE_KEY, String(volume));
    } catch (error) { /* stille fallback: geldt dan enkel voor deze sessie */ }
    return volume;
  }

  /* Activeert het geluid. Wordt automatisch bij het laden van de pagina en
     bij de eerste gebruikersinteractie opgeroepen, zodat er nooit manueel op
     "Geluid activeren" geklikt moet worden. */
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
    var volume = getVolume() / 100;
    try {
      var audio = new Audio('/assets/sounds/' + sound + '.mp3');
      audio.volume = volume;
      var result = audio.play();
      if (result && typeof result.catch === 'function') {
        result.catch(function () { playFallbackAlarm(sound, volume); });
      }
    } catch (error) {
      playFallbackAlarm(sound, volume);
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
  function playFallbackAlarm(sound, volume) {
    if (!audioContext) return;
    var level = typeof volume === 'number' ? volume : 1;
    if (sound === 'bell') {
      [660, 660 * 2.76, 660 * 5.4].forEach(function (frequency, index) {
        tone(frequency, 0, 1.8, 'sine', (0.3 / (index + 1)) * level);
      });
      return;
    }
    if (sound === 'chime') {
      [659.25, 783.99, 1046.5, 1318.51].forEach(function (frequency, index) {
        tone(frequency, index * 0.28, 1.1, 'sine', 0.25 * level);
      });
      return;
    }
    if (sound === 'alert') {
      for (var i = 0; i < 3; i++) {
        tone(880, i * 0.64, 0.28, 'square', 0.22 * level);
        tone(1174.66, i * 0.64 + 0.32, 0.28, 'square', 0.22 * level);
      }
      return;
    }
    // beep (standaard)
    [0, 0.6, 1.2, 1.8].forEach(function (start) {
      tone(880, start, 0.45, 'square', 0.3 * level);
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

  /* Kleurniveau van de klok op basis van het percentage resterende tijd
     t.o.v. de totale duur: groen (>80%), oranje (20-80%), rood (<20%). */
  function applyColor(element, remaining, totalDuration) {
    var percentRemaining = totalDuration > 0 ? (remaining / totalDuration) * 100 : 100;
    element.classList.toggle('is-warning', remaining > 0 && percentRemaining <= 80 && percentRemaining > 20);
    element.classList.toggle('is-danger', remaining > 0 && percentRemaining <= 20);
    element.classList.toggle('is-expired', remaining <= 0);
  }

  function tick() {
    var now = Date.now();
    document.querySelectorAll('.clock[data-ends-at]').forEach(function (element) {
      var totalDuration = parseInt(element.getAttribute('data-duration-seconds') || '0', 10);
      var paused = element.getAttribute('data-paused') === 'true';

      var remaining;
      if (paused) {
        // Bij een gepauzeerde certificatie staat de tijd stil: toon de
        // resterende tijd op het moment van pauzeren, zonder verder af te tellen.
        remaining = parseFloat(element.getAttribute('data-remaining-seconds') || '0');
        element.textContent = formatDuration(remaining);
        element.classList.remove('is-warning', 'is-danger', 'is-expired');
        return;
      }

      var endsAt = Date.parse(element.getAttribute('data-ends-at'));
      if (isNaN(endsAt)) return;

      remaining = Math.max(0, (endsAt - now) / 1000);
      element.textContent = formatDuration(remaining);
      applyColor(element, remaining, totalDuration);

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
        clock.setAttribute('data-duration-seconds', String(certification.durationSeconds));
        clock.setAttribute('data-paused', certification.paused ? 'true' : 'false');
        clock.setAttribute('data-remaining-seconds', String(certification.remainingSeconds));
        clock.classList.remove('clock--idle');
        clock.classList.toggle('is-paused', !!certification.paused);
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
        clock.classList.remove('is-paused', 'is-warning', 'is-danger', 'is-expired');
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

  /* Ververst enkel de klokken en badges van het admin-dashboard, zonder de
     rest van de pagina (en dus zonder het "Certificatie starten"-formulier)
     te verstoren. */
  function updateAdminBoards(boards) {
    boards.forEach(function (state) {
      var card = document.querySelector('.board-card[data-board="' + state.board + '"]');
      if (!card) return;

      var badge = card.querySelector('.badge');
      var clock = card.querySelector('.clock[data-ends-at], .clock--idle');
      var certification = state.certification;

      if (badge) {
        badge.classList.remove('badge--live', 'badge--idle', 'badge--paused');
        if (!state.running) {
          badge.classList.add('badge--idle');
        } else if (certification.paused) {
          badge.classList.add('badge--paused');
        } else {
          badge.classList.add('badge--live');
        }
      }

      if (clock && certification) {
        clock.setAttribute('data-ends-at', certification.endsAt);
        clock.setAttribute('data-duration-seconds', String(certification.durationSeconds));
        clock.setAttribute('data-paused', certification.paused ? 'true' : 'false');
        clock.setAttribute('data-remaining-seconds', String(certification.remainingSeconds));
        clock.classList.remove('clock--idle');
        clock.classList.toggle('is-paused', !!certification.paused);
      }
    });
  }

  function pollAdminBoards() {
    var section = document.querySelector('[data-admin-boards]');
    if (!section) return;
    var url = section.getAttribute('data-poll-url');
    if (!url) return;

    fetch(url, { headers: { Accept: 'application/json' } })
      .then(function (response) { return response.ok ? response.json() : null; })
      .then(function (data) { if (data && data.boards) updateAdminBoards(data.boards); })
      .catch(function () { /* stille herhaling bij een tijdelijke netwerkfout */ });
  }

  function initSoundControls() {
    document.querySelectorAll('select[data-sound-select]').forEach(function (select) {
      select.value = getSelectedSound();
      select.addEventListener('change', function () {
        setSelectedSound(select.value);
      });
    });

    document.querySelectorAll('input[data-volume-select]').forEach(function (input) {
      input.value = String(getVolume());
      input.addEventListener('input', function () {
        setVolume(input.value);
      });
    });

    document.querySelectorAll('[data-enable-sound]').forEach(function (button) {
      button.addEventListener('click', function () {
        enableSound();
        button.textContent = document.body.getAttribute('data-sound-enabled') || button.textContent;
        button.disabled = true;
      });
    });

    document.querySelectorAll('[data-test-sound]').forEach(function (button) {
      button.addEventListener('click', function () {
        enableSound();
        playAlarm();
      });
    });
  }

  function findCsrfToken(container) {
    var input = container ? container.querySelector('input[name="csrf_token"]') : null;
    if (input) return input.value;
    var globalInput = document.querySelector('input[name="csrf_token"]');
    return globalInput ? globalInput.value : '';
  }

  /* Extra tijd toevoegen via de +1 min / +5 min knoppen (AJAX). De custom
     invoer valt terug op een normale POST naar admin.php (werkt ook zonder JS). */
  function initExtendControls() {
    document.querySelectorAll('[data-extend-controls]').forEach(function (container) {
      var certificationId = container.getAttribute('data-certification-id');
      container.querySelectorAll('button[data-extend-seconds]').forEach(function (button) {
        button.addEventListener('click', function () {
          var seconds = parseInt(button.getAttribute('data-extend-seconds'), 10);
          if (!seconds || button.disabled) return;
          button.disabled = true;
          var body = new URLSearchParams();
          body.set('certification_id', certificationId);
          body.set('extension_seconds', String(seconds));
          body.set('csrf_token', findCsrfToken(container));

          fetch('/api/extend-certification.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', Accept: 'application/json' },
            body: body.toString(),
          })
            .then(function (response) { return response.json().then(function (data) { return { ok: response.ok, data: data }; }); })
            .then(function (result) {
              if (result.ok) {
                window.location.reload();
              } else {
                window.alert(result.data && result.data.error ? result.data.error : 'Kon geen extra tijd toevoegen.');
                button.disabled = false;
              }
            })
            .catch(function () {
              window.alert('Kon geen extra tijd toevoegen.');
              button.disabled = false;
            });
        });
      });
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    initSoundControls();
    initExtendControls();

    // Geluid staat steeds automatisch aan: geen manuele klik meer nodig.
    // Bij browsers die pas na een gebruikersgebaar geluid toestaan, wordt dit
    // bij de eerste klik/toetsaanslag alsnog (stil) opnieuw geprobeerd.
    enableSound();
    ['click', 'keydown', 'touchstart'].forEach(function (eventName) {
      document.addEventListener(eventName, enableSound, { once: true, passive: true });
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

    // Ververst enkel de klokken/badges van het admin-dashboard (o.a. voor
    // auto-sluiten), zonder het "Certificatie starten"-formulier te hinderen.
    if (document.querySelector('[data-admin-boards]')) {
      setInterval(pollAdminBoards, ADMIN_POLL_INTERVAL_MS);
    }
  });
})();
