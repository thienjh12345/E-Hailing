/* Crab admin — small progressive enhancements. Every page still works without JS. */
(function () {
    'use strict';

    /* Mobile sidebar toggle */
    var sidebar = document.getElementById('sidebar');
    var scrim = document.getElementById('scrim');
    var menuBtn = document.getElementById('menuBtn');
    function setMenu(open) {
        if (!sidebar) return;
        sidebar.classList.toggle('open', open);
        scrim.classList.toggle('show', open);
        menuBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
    }
    if (menuBtn) menuBtn.addEventListener('click', function () { setMenu(!sidebar.classList.contains('open')); });
    if (scrim) scrim.addEventListener('click', function () { setMenu(false); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') setMenu(false); });

    /* Confirm destructive actions: <button data-confirm="Are you sure?"> */
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-confirm]');
        if (btn && !window.confirm(btn.getAttribute('data-confirm'))) e.preventDefault();
    });

    /* Whole table row clickable: <tr data-href="..."> */
    document.addEventListener('click', function (e) {
        var row = e.target.closest('tr[data-href]');
        if (!row || e.target.closest('a, button, input, form')) return;
        window.location.href = row.getAttribute('data-href');
    });

    /* Chart metric toggle (Rides / Revenue) */
    document.querySelectorAll('.chart-card').forEach(function (card) {
        var tabs = card.querySelectorAll('[data-show]');
        var title = card.querySelector('[data-chart-title]');
        var sub = card.querySelector('[data-chart-sub]');
        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                var key = tab.getAttribute('data-show');
                tabs.forEach(function (t) { t.setAttribute('aria-selected', t === tab ? 'true' : 'false'); });
                card.querySelectorAll('[data-chart]').forEach(function (c) { c.hidden = c.getAttribute('data-chart') !== key; });
                if (title) title.textContent = key === 'revenue' ? 'Revenue per day' : 'Rides per day';
                if (sub) sub.textContent = sub.getAttribute('data-' + key);
            });
        });
    });

    /* Chart hover tooltips: highlight the hovered bar, dim the rest */
    document.querySelectorAll('.chart-card').forEach(function (card) {
        var tip = card.querySelector('.tooltip');
        card.querySelectorAll('svg.chart').forEach(function (svg) {
            var hits = svg.querySelectorAll('.hit');
            var bars = svg.querySelectorAll('.bar');
            hits.forEach(function (hit, i) {
                hit.addEventListener('mouseenter', function () {
                    // Bars are only drawn for non-zero values, so match by x position.
                    var hx = parseFloat(hit.getAttribute('x')), hw = parseFloat(hit.getAttribute('width'));
                    svg.classList.add('hovering');
                    bars.forEach(function (b) {
                        var bx = b.getBBox().x;
                        b.classList.toggle('on', bx >= hx && bx < hx + hw);
                    });
                    tip.innerHTML = '';
                    var strong = document.createElement('strong');
                    strong.textContent = hit.getAttribute('data-val');
                    tip.appendChild(strong);
                    tip.appendChild(document.createTextNode(hit.getAttribute('data-tip')));
                    var cardBox = card.getBoundingClientRect();
                    var box = hit.getBoundingClientRect();
                    var bar = Array.prototype.find.call(bars, function (b) { return b.classList.contains('on'); });
                    var top = bar ? bar.getBoundingClientRect().top : box.bottom;
                    tip.style.left = (box.left + box.width / 2 - cardBox.left) + 'px';
                    tip.style.top = (top - cardBox.top - 8) + 'px';
                    tip.hidden = false;
                });
            });
            svg.addEventListener('mouseleave', function () {
                svg.classList.remove('hovering');
                bars.forEach(function (b) { b.classList.remove('on'); });
                tip.hidden = true;
            });
        });
    });
})();

/* Profile photo: preview the chosen image before uploading */
(function () {
    'use strict';
    document.querySelectorAll('[data-photo-input]').forEach(function (input) {
        input.addEventListener('change', function () {
            var file = input.files && input.files[0];
            var drop = input.closest('.file-drop');
            var card = input.closest('.photo-card');
            var preview = card && card.querySelector('[data-photo-preview] .avatar');
            drop.classList.toggle('chosen', !!file);
            var label = drop.querySelector('strong');
            if (label) label.textContent = file ? file.name : 'Choose a photo';
            if (!file || !preview || !/^image\//.test(file.type)) return;
            var img = document.createElement('img');
            img.alt = '';
            img.src = URL.createObjectURL(file);
            preview.textContent = '';
            preview.appendChild(img);
        });
    });
})();
