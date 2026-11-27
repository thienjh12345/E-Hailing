/* Crab emergency (SOS) button on a live trip — for the passenger and the driver.
 *
 * !! SIMULATION ONLY !!  No real emergency service is contacted. Pressing "Emergency":
 *   1. asks to confirm, calling automatically after a 3-second countdown (Cancel stops it);
 *   2. tells the server (passenger|driver/emergency.php) — the first call "sends help" with a
 *      made-up arrival time, and the other person on the trip is alerted;
 *   3. plays a pretend phone call to 911: ringing, then an operator who says help is on the way
 *      (spoken aloud with the browser's text-to-speech when sound is on, and written out);
 *   4. shows whether help is on the way (with a countdown) or has arrived — in the pop-up, in a
 *      banner on the trip card, and on the road drawing (an ambulance drives up behind the car).
 * Both sides poll the server every few seconds, so each sees when the other one called.
 * The markup is in includes/emergency.php; the road drawing reacts in assets/js/trip-road.js.  */
(function () {
    'use strict';

    var root = document.querySelector('[data-trip-road][data-sos-url]');
    if (!root || !window.fetch || !window.Promise) return;
    var dialog = root.querySelector('[data-sos-dialog]');
    var openBtn = root.querySelector('[data-sos-open]');
    if (!dialog || !openBtn) return;

    var cfg = {
        url: root.getAttribute('data-sos-url'),
        csrf: root.getAttribute('data-sos-csrf'),
        ride: parseInt(root.getAttribute('data-sos-ride'), 10),
        viewer: root.getAttribute('data-sos-viewer'),          // 'passenger' | 'driver'
        other: root.getAttribute('data-sos-other'),            // 'Your driver' | 'Your passenger'
        number: root.getAttribute('data-sos-number')           // '911'
    };
    var otherLower = cfg.other.charAt(0).toLowerCase() + cfg.other.slice(1);

    function q(sel) { return root.querySelector(sel); }
    var el = {
        banner: q('[data-sos-banner]'), bannerTitle: q('[data-sos-banner-title]'), bannerSub: q('[data-sos-banner-sub]'),
        bannerEta: q('[data-sos-banner-eta]'), details: q('[data-sos-details]'),
        steps: dialog.querySelectorAll('[data-sos-step]'),
        count: q('[data-sos-count]'), countText: q('[data-sos-count-text]'), ring: q('[data-sos-ring]'),
        callNow: q('[data-sos-call]'), cancel: q('[data-sos-cancel]'),
        callStatus: q('[data-sos-call-status]'), transcript: q('[data-sos-transcript]'),
        statusTitle: q('[data-sos-status-title]'), log: q('[data-sos-log]'),
        help: q('[data-sos-help]'), helpTitle: q('[data-sos-help-title]'), helpPlace: q('[data-sos-help-place]'),
        helpEta: q('[data-sos-help-eta]'), helpFill: q('[data-sos-help-fill]'), stages: dialog.querySelectorAll('[data-sos-stage]'),
        footActions: q('[data-sos-foot-actions]'), again: q('[data-sos-again]'), againLabel: q('[data-sos-again-label]'),
        sound: q('[data-sos-sound]'), soundLabel: q('[data-sos-sound-label]'), close: q('[data-sos-close]')
    };

    function wait(ms) { return new Promise(function (r) { setTimeout(r, ms); }); }
    function clock(s) { s = Math.max(0, Math.ceil(s)); return Math.floor(s / 60) + ':' + ('0' + (s % 60)).slice(-2); }
    function etaWords(s) {
        if (s < 60) return Math.max(5, Math.round(s / 5) * 5) + ' seconds';
        var m = Math.round(s / 60);
        return m + (m === 1 ? ' minute' : ' minutes');
    }
    function store(kind) { try { return window[kind]; } catch (e) { return null; } }
    function getItem(kind, key) { try { var s = store(kind); return s ? s.getItem(key) : null; } catch (e) { return null; } }
    function setItem(kind, key, val) { try { var s = store(kind); if (s) s.setItem(key, val); } catch (e) { /* private mode etc. */ } }

    /* =====================================================================
     * State: the emergency as the server sees it (or null), plus a local countdown
     * =================================================================== */
    var em = null;            // {id, location, you_called_at, other_called_at, seconds_left, help_seconds, arrives_at}
    var syncedAt = 0;         // performance.now() when em.seconds_left was true
    var step = null;          // open step: 'confirm' | 'call' | 'status' | null when closed
    var statusMode = 'status';// 'status' | 'alert' (the other person just called)
    var helpShown = false;    // in the call, the help tracker appears once the operator has said help is coming
    var callFailed = false;
    var callToken = 0;        // changes whenever a call starts/stops, so old timers stop quietly
    var countTimer = null, callClock = null;

    function secondsLeft() { return em ? Math.max(0, em.seconds_left - (performance.now() - syncedAt) / 1000) : 0; }
    function arrived() { return !!em && secondsLeft() <= 0; }

    function setEmergency(next) {
        var same = next && em && next.id === em.id && Math.abs(secondsLeft() - next.seconds_left) < 1.5;
        if (same) {
            next.seconds_left = em.seconds_left;       // same help and the clocks agree: keep the smooth countdown
            em = next;
        } else {
            em = next || null;
            syncedAt = performance.now();
            root.dispatchEvent(new CustomEvent('crab:sos', { detail: em ? { left: em.seconds_left, total: em.help_seconds } : null }));
        }
        render();
    }

    function whoCalled() {
        if (!em) return '';
        if (em.you_called_at && em.other_called_at) return 'You and ' + otherLower + ' called ' + cfg.number;
        if (em.you_called_at) return 'You called ' + cfg.number + ' at ' + em.you_called_at;
        return cfg.other + ' called ' + cfg.number + ' at ' + em.other_called_at;
    }

    /* =====================================================================
     * Drawing the state: banner on the card, help tracker and status in the pop-up
     * =================================================================== */
    function render() {
        el.banner.hidden = !em;
        if (!em) { el.help.hidden = true; return; }
        var done = arrived(), left = secondsLeft();

        el.banner.classList.toggle('is-arrived', done);
        el.bannerTitle.textContent = done ? 'Help has arrived' : 'Emergency — help is on the way';
        el.bannerSub.textContent = whoCalled() + ' · ' + (done ? 'at ' : 'to ') + em.location;
        el.bannerEta.textContent = done ? '' : clock(left);

        el.help.classList.toggle('is-arrived', done);
        dialog.classList.toggle('help-is-here', done);
        el.helpTitle.textContent = done ? 'Help has arrived' : 'Help is on the way';
        el.helpPlace.textContent = (done ? 'At ' : 'To ') + em.location;
        el.helpEta.textContent = done ? 'Arrived ' + em.arrives_at : clock(left);
        el.helpFill.style.width = (100 * (1 - left / em.help_seconds)).toFixed(1) + '%';
        el.stages.forEach(function (li) {
            var k = li.getAttribute('data-sos-stage');
            li.className = (k !== 'arrived' || done) ? 'is-done' : 'is-current';
        });
        if (step) el.help.hidden = step === 'confirm' || (step === 'call' && !helpShown);

        el.statusTitle.textContent = statusMode === 'alert' && !done
            ? cfg.other + ' called ' + cfg.number
            : (done ? 'Help has arrived' : 'Help is on the way');
        var lines = [];
        if (em.you_called_at) lines.push('You called ' + cfg.number + ' at ' + em.you_called_at + '.');
        if (em.other_called_at) lines.push(cfg.other + ' called ' + cfg.number + ' at ' + em.other_called_at + '.');
        lines.push(done ? 'Help arrived at ' + em.location + ' at ' + em.arrives_at + '.'
                        : 'Help is going to ' + em.location + ' — expected around ' + em.arrives_at + '.');
        if (el.log.textContent !== lines.join('')) {
            el.log.innerHTML = '';
            lines.forEach(function (t) { var li = document.createElement('li'); li.textContent = t; el.log.appendChild(li); });
        }
        el.againLabel.textContent = callFailed && step === 'call' ? 'Try again'
            : (em.you_called_at ? 'Call ' + cfg.number + ' again' : 'Call ' + cfg.number + ' too');
    }
    setInterval(function () { if (em) render(); }, 500);

    /* =====================================================================
     * Sound: ringing tones (Web Audio) and the operator's voice (speech synthesis)
     * =================================================================== */
    var soundOn = getItem('localStorage', 'crab-sos-sound') !== '0';
    var actx = null, nodes = [];

    function audioUnlock() {      // must run inside a click, or the browser keeps audio muted
        try {
            var AC = window.AudioContext || window.webkitAudioContext;
            if (!actx && AC) actx = new AC();
            if (actx && actx.state === 'suspended') actx.resume();
        } catch (e) { actx = null; }
    }
    function tone(freqs, start, dur, vol) {
        if (!soundOn || !actx) return;
        try {
            var t = actx.currentTime + start, g = actx.createGain();
            g.gain.setValueAtTime(0, t);
            g.gain.linearRampToValueAtTime(vol, t + 0.03);
            g.gain.setValueAtTime(vol, t + dur - 0.05);
            g.gain.linearRampToValueAtTime(0, t + dur);
            g.connect(actx.destination);
            nodes.push(g);
            freqs.forEach(function (f) {
                var o = actx.createOscillator();
                o.frequency.value = f;
                o.connect(g);
                o.start(t);
                o.stop(t + dur + 0.05);
                nodes.push(o);
            });
        } catch (e) { /* no sound — the call still works */ }
    }
    function stopSound() {
        nodes.forEach(function (n) { try { n.disconnect(); } catch (e) { /* already done */ } });
        nodes = [];
        if (window.speechSynthesis) { try { window.speechSynthesis.cancel(); } catch (e) { /* ignore */ } }
    }
    function pickVoice() {
        var voices = (window.speechSynthesis && window.speechSynthesis.getVoices()) || [], best = null;
        for (var i = 0; i < voices.length; i++) {
            if (voices[i].lang === 'en-US') return voices[i];
            if (!best && /^en/i.test(voices[i].lang)) best = voices[i];
        }
        return best;
    }
    // Resolves when the line has been spoken — or, without sound, after time to read it.
    function speak(text) {
        return new Promise(function (resolve) {
            var finished = false, timer = null, reading = 900 + text.length * 40, t0 = performance.now();
            function done() { if (!finished) { finished = true; clearTimeout(timer); resolve(); } }
            var synth = window.speechSynthesis;
            if (!soundOn || !synth || !window.SpeechSynthesisUtterance) { timer = setTimeout(done, reading); return; }
            try {
                var u = new window.SpeechSynthesisUtterance(text), v = pickVoice();
                u.lang = 'en-US';
                u.rate = 1.03;
                if (v) u.voice = v;
                // Speaking: wait for the end (with a safety net). Never started (no voices): just reading time.
                u.onstart = function () { clearTimeout(timer); timer = setTimeout(done, 4000 + text.length * 90); };
                // A failed voice, or one that "finishes" far too quickly, still leaves time to read the line.
                u.onend = u.onerror = function (e) {
                    var spent = performance.now() - t0;
                    var real = e.type === 'end' && spent >= text.length * 30;
                    clearTimeout(timer);
                    timer = setTimeout(done, real ? 150 : Math.max(150, reading - spent));
                };
                timer = setTimeout(function () { try { synth.cancel(); } catch (e) { /* ignore */ } done(); }, reading + 1000);
                synth.speak(u);
            } catch (e) { clearTimeout(timer); timer = setTimeout(done, reading); }
        });
    }
    function renderSound() {
        el.sound.setAttribute('aria-pressed', soundOn ? 'true' : 'false');
        el.sound.classList.toggle('is-muted', !soundOn);
        el.soundLabel.textContent = soundOn ? 'Sound on' : 'Sound off';
    }
    renderSound();

    /* =====================================================================
     * The pop-up
     * =================================================================== */
    function showStep(name) {
        step = name;
        el.steps.forEach(function (s) { s.hidden = s.getAttribute('data-sos-step') !== name; });
        el.footActions.hidden = name === 'confirm';
        el.again.hidden = !(name === 'status' || (name === 'call' && callFailed));
        el.sound.hidden = name !== 'call';
        render();
        if (!dialog.open) {
            if (dialog.showModal) dialog.showModal(); else dialog.setAttribute('open', '');
        }
    }
    function closeDialog() {
        if (dialog.close) dialog.close(); else { dialog.removeAttribute('open'); onClosed(); }
    }
    function onClosed() {
        callToken++;
        clearInterval(countTimer); countTimer = null;
        clearInterval(callClock); callClock = null;
        stopSound();
        if (em && em.other_called_at) setItem('sessionStorage', seenKey(), '1');
        step = null;
        statusMode = 'status';
    }
    dialog.addEventListener('close', onClosed);

    function seenKey() { return 'crab-sos-seen-' + cfg.viewer + '-' + (em ? em.id : 0); }

    /* ---------- 1. Confirm, with a countdown ---------- */
    function openConfirm() {
        audioUnlock();
        callToken++;
        clearInterval(callClock); callClock = null;
        stopSound();
        callFailed = false;
        var n = 3;
        el.count.textContent = el.countText.textContent = n;
        el.ring.classList.remove('run');
        void el.ring.getBoundingClientRect();          // restart the ring animation
        el.ring.classList.add('run');
        showStep('confirm');
        el.callNow.focus();
        clearInterval(countTimer);
        countTimer = setInterval(function () {
            n--;
            if (n <= 0) { clearInterval(countTimer); countTimer = null; startCall(); }
            else el.count.textContent = el.countText.textContent = n;
        }, 1000);
    }

    /* ---------- 2. The pretend call ---------- */
    function send() {
        var body = new FormData();
        body.append('csrf', cfg.csrf);
        body.append('action', 'call');
        body.append('ride_id', cfg.ride);
        return fetch(cfg.url, { method: 'POST', body: body, credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } })
            .then(function (r) {
                return r.json().catch(function () { return {}; }).then(function (d) {
                    return r.ok && d.emergency ? { ok: true, emergency: d.emergency }
                                               : { ok: false, error: d.error || 'Your alert could not be sent.' };
                });
            })
            .catch(function () { return { ok: false, error: 'No connection — your alert could not be sent.' }; });
    }

    function addLine(who, text) {
        var li = document.createElement('li');
        li.className = 'sos-line sos-' + who;
        var name = document.createElement('small');
        name.textContent = who === 'op' ? cfg.number + ' operator' : (who === 'me' ? 'You' : 'Crab');
        var body = document.createElement('span');
        body.textContent = text;
        li.appendChild(name);
        li.appendChild(body);
        el.transcript.appendChild(li);
        el.transcript.scrollTop = el.transcript.scrollHeight;
        return li;
    }
    function typing() {
        var li = document.createElement('li');
        li.className = 'sos-line sos-op sos-typing';
        li.setAttribute('aria-hidden', 'true');
        li.innerHTML = '<span><i></i><i></i><i></i></span>';
        el.transcript.appendChild(li);
        el.transcript.scrollTop = el.transcript.scrollHeight;
        return li;
    }

    function startCall() {
        clearInterval(countTimer); countTimer = null;
        var token = ++callToken;
        var helpWasSent = !!em, helpWasThere = arrived();   // the other person (or an earlier call) already sent help
        callFailed = false;
        helpShown = false;
        el.transcript.innerHTML = '';
        el.callStatus.textContent = 'Calling ' + cfg.number + '…';
        el.close.textContent = 'End call';
        dialog.classList.add('is-ringing');
        dialog.classList.remove('is-connected', 'is-ended');
        showStep('call');
        tone([440, 480], 0, 1.2, 0.06);            // two rings
        tone([440, 480], 1.6, 1.1, 0.06);

        Promise.all([send(), wait(2800)]).then(function (r) {
            if (token !== callToken) return;
            var res = r[0];
            dialog.classList.remove('is-ringing');
            if (!res.ok) {
                callFailed = true;
                el.callStatus.textContent = 'Call failed';
                dialog.classList.add('is-ended');
                addLine('crab', res.error + ' Please try again.');
                el.close.textContent = 'Close';
                showStep('call');
                return;
            }
            setEmergency(res.emergency);
            connected(token, helpWasSent, helpWasThere);
        });
    }

    function connected(token, helpWasSent, helpWasThere) {
        dialog.classList.add('is-connected');
        tone([1150], 0, 0.09, 0.05);
        var t0 = performance.now();
        var tick = function () { el.callStatus.textContent = 'Connected · ' + clock((performance.now() - t0) / 1000); };
        tick();
        clearInterval(callClock);
        callClock = setInterval(tick, 500);

        var place = em.location;
        var helpLine = helpWasThere ? 'Our responders are already at ' + place + '. Please go to them now.'
            : helpWasSent ? 'Help is already on the way to ' + place + ' — about ' + etaWords(secondsLeft()) + ' away.'
            : 'Help is on the way to ' + place + '. They will be there in about ' + etaWords(secondsLeft()) + '.';
        var lines = [
            ['op', cfg.number + ', what is your emergency?'],
            ['me', (cfg.viewer === 'driver' ? 'I\'m the driver on Crab trip #' : 'I\'m a passenger on Crab trip #') + cfg.ride + '. We need help.'],
            ['op', 'Crab has shared your trip with us. I can see you are near ' + place + '.'],
            ['op', helpLine, true],
            ['op', (cfg.viewer === 'driver' ? 'Switch on your hazard lights and stay' : 'Stay') +
                   ' somewhere safe, away from traffic. If anyone is hurt, do not move them unless they are in danger.'],
            ['op', 'You can follow the help on your screen. Stay calm — we are with you.']
        ];
        if (em.other_called_at && !helpWasSent) lines.splice(3, 0, ['op', cfg.other + ' has called us too.']);

        (function next(i) {
            if (token !== callToken) return;
            if (i >= lines.length) return endCall(token, t0);
            var line = lines[i];
            var before = line[0] === 'op' && i > 0 ? typingThen(token) : Promise.resolve();
            before.then(function () {
                if (token !== callToken) return;
                addLine(line[0], line[1]);
                if (line[2]) { helpShown = true; render(); }
                return (line[0] === 'op' ? speak(line[1]) : wait(1300)).then(function () { return wait(350); });
            }).then(function () { next(i + 1); }, function () { /* call was stopped */ });
        })(0);
    }
    function typingThen(token) {
        var dots = typing();
        return wait(650).then(function () { dots.remove(); if (token !== callToken) throw new Error('stopped'); });
    }

    function endCall(token, t0) {
        if (token !== callToken) return;
        clearInterval(callClock); callClock = null;
        el.callStatus.textContent = 'Call ended · ' + clock((performance.now() - t0) / 1000);
        dialog.classList.add('is-ended');
        el.close.textContent = 'Close';
        el.sound.hidden = true;
        tone([480, 620], 0, 0.2, 0.05);
        tone([480, 620], 0.35, 0.2, 0.05);
        tone([480, 620], 0.7, 0.2, 0.05);
    }

    /* ---------- 3. Status (from the banner, or when the other person calls) ---------- */
    function openStatus(mode) {
        callToken++;
        clearInterval(callClock); callClock = null;
        stopSound();
        callFailed = false;
        statusMode = mode;
        dialog.classList.remove('is-ringing', 'is-connected', 'is-ended');
        el.close.textContent = 'Close';
        showStep('status');
    }

    function otherCalled() {
        if (step === 'call' && !callFailed) {
            addLine('crab', cfg.other + ' has also called ' + cfg.number + '.');
            setItem('sessionStorage', seenKey(), '1');
        } else if (step !== 'confirm' && getItem('sessionStorage', seenKey()) !== '1') {
            openStatus('alert');
            tone([880], 0, 0.18, 0.06);
            tone([880], 0.3, 0.18, 0.06);
            if (navigator.vibrate) { try { navigator.vibrate([200, 100, 200]); } catch (e) { /* ignore */ } }
        }
    }

    /* ---------- Buttons ---------- */
    openBtn.addEventListener('click', openConfirm);
    el.callNow.addEventListener('click', function () { audioUnlock(); startCall(); });
    el.cancel.addEventListener('click', closeDialog);
    el.close.addEventListener('click', closeDialog);
    el.again.addEventListener('click', openConfirm);
    el.details.addEventListener('click', function () { openStatus('status'); });
    el.sound.addEventListener('click', function () {
        soundOn = !soundOn;
        setItem('localStorage', 'crab-sos-sound', soundOn ? '1' : '0');
        if (!soundOn) stopSound(); else audioUnlock();
        renderSound();
    });
    // Clicking the dark area around the pop-up closes it (not during the countdown or the call)
    dialog.addEventListener('click', function (e) {
        if (e.target !== dialog || step !== 'status') return;
        var r = dialog.getBoundingClientRect();
        if (e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom) closeDialog();
    });

    /* =====================================================================
     * Start: show the button, load the saved state, keep it in sync with the server
     * =================================================================== */
    openBtn.hidden = false;
    var initial = null;
    try { initial = JSON.parse(root.getAttribute('data-sos-state') || 'null'); } catch (e) { initial = null; }
    setEmergency(initial);
    if (em && em.other_called_at && getItem('sessionStorage', seenKey()) !== '1') openStatus('alert');

    var stopped = false;
    function poll() {
        fetch(cfg.url, { credentials: 'same-origin', cache: 'no-store', headers: { 'X-Requested-With': 'fetch' } })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (d) {
                if (!d) return;
                if (d.ride !== cfg.ride) {           // the trip has ended (or been handed back)
                    stopped = true;
                    openBtn.hidden = true;
                    return;
                }
                var hadOther = !!(em && em.other_called_at);
                setEmergency(d.emergency);
                if (em && em.other_called_at && !hadOther) otherCalled();
            })
            .catch(function () { /* try again next time */ })
            .then(function () { if (!stopped) setTimeout(poll, document.hidden ? 15000 : 4000); });
    }
    setTimeout(poll, 4000);
})();
