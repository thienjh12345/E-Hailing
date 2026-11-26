/* Crab in-app chat: live updates (polling) + send without reloading.
 * Without JavaScript the chat still works: the form posts normally and the page reloads. */
(function () {
    'use strict';
    var root = document.querySelector('[data-chat]');
    if (!root || !window.fetch) return;

    var log = root.querySelector('[data-chat-log]');
    var form = root.querySelector('[data-chat-form]');
    var input = root.querySelector('[data-chat-input]');
    var errorBox = root.querySelector('[data-chat-error]');
    var seen = root.querySelector('[data-seen]');
    var bottom = log.querySelector('#bottom');
    var lastId = parseInt(root.getAttribute('data-last'), 10) || 0;
    var otherRead = parseInt(root.getAttribute('data-other-read'), 10) || 0;
    var sending = false;
    var timer = null;

    function nearBottom() { return log.scrollHeight - log.scrollTop - log.clientHeight < 120; }
    function scrollDown() { log.scrollTop = log.scrollHeight; }

    /* "Seen" belongs under my message only when it's the latest one in the conversation. */
    function myLastId() {
        var all = log.querySelectorAll('.msg');
        var last = all.length ? all[all.length - 1] : null;
        return last && last.classList.contains('mine') ? parseInt(last.getAttribute('data-id'), 10) : 0;
    }
    function updateSeen() {
        var mid = myLastId();
        seen.hidden = !(mid && otherRead >= mid);
    }

    /* Build a message bubble safely (textContent — never innerHTML with user text). */
    function addMessage(m) {
        if (log.querySelector('.msg[data-id="' + m.id + '"]')) return;   // already shown
        var empty = log.querySelector('[data-chat-empty]');
        if (empty) empty.remove();

        var wrap = document.createElement('div');
        wrap.className = 'msg ' + (m.mine ? 'mine' : 'theirs') + ' role-' + m.role;
        wrap.setAttribute('data-id', m.id);
        if (!m.mine) {
            var who = document.createElement('span');
            who.className = 'msg-sender';
            who.textContent = m.sender;
            wrap.appendChild(who);
        }
        var bubble = document.createElement('div');
        bubble.className = 'msg-bubble';
        bubble.textContent = m.body;            // CSS keeps line breaks (white-space: pre-wrap)
        wrap.appendChild(bubble);
        var time = document.createElement('span');
        time.className = 'msg-time';
        time.textContent = m.time;
        wrap.appendChild(time);

        log.insertBefore(wrap, seen);
        if (m.id > lastId) lastId = m.id;
    }

    function poll() {
        var stick = nearBottom();
        fetch(root.getAttribute('data-poll') + '&after=' + lastId, { headers: { 'X-Requested-With': 'fetch' }, credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                if (!data) return;
                data.messages.forEach(addMessage);
                otherRead = data.otherRead;
                updateSeen();
                if (data.messages.length && stick) scrollDown();
            })
            .catch(function () { /* offline for a moment — try again next tick */ })
            .then(schedule);
    }
    function schedule() {
        clearTimeout(timer);
        timer = setTimeout(poll, document.hidden ? 15000 : 3000);   // slow down in background tabs
    }
    document.addEventListener('visibilitychange', function () { if (!document.hidden) poll(); });

    function showError(msg) {
        errorBox.textContent = msg || '';
        errorBox.hidden = !msg;
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (sending) return;
        var text = input.value.trim();
        if (!text) { input.focus(); return; }
        sending = true;
        showError('');
        var body = new FormData(form);
        fetch(root.getAttribute('data-send'), { method: 'POST', body: body, headers: { 'X-Requested-With': 'fetch' }, credentials: 'same-origin' })
            .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
            .then(function (res) {
                if (!res.ok) { showError(res.data.error || 'Message not sent. Please try again.'); return; }
                addMessage(res.data.message);
                input.value = '';
                autosize();
                updateSeen();
                scrollDown();
            })
            .catch(function () { showError('Message not sent — check your connection and try again.'); })
            .then(function () { sending = false; input.focus(); });
    });

    /* Enter sends, Shift+Enter makes a new line */
    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) {
            e.preventDefault();
            form.requestSubmit ? form.requestSubmit() : form.dispatchEvent(new Event('submit', { cancelable: true }));
        }
    });

    /* Grow the box with the text (up to ~6 lines) */
    function autosize() {
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, 160) + 'px';
    }
    input.addEventListener('input', autosize);

    autosize();
    updateSeen();
    scrollDown();
    if (bottom && location.hash === '#bottom') scrollDown();
    schedule();
})();
