/* Crab ride booking: live fare estimates on the booking form, and auto-refresh while a trip is live.
 * The server re-checks and re-calculates everything, so this is only for display. */
(function () {
    'use strict';

    function money(n) { return 'RM ' + n.toFixed(2); }

    /* ---------- Booking form ---------- */
    var form = document.querySelector('[data-booking]');
    if (form) {
        var locations = JSON.parse(form.getAttribute('data-locations'));
        var fares = JSON.parse(form.getAttribute('data-fares'));
        var balance = parseFloat(form.getAttribute('data-balance')) || 0;
        var pickup = form.querySelector('[data-place="pickup"]');
        var dropoff = form.querySelector('[data-place="dropoff"]');

        function findPlace(name) {
            for (var city in locations) {
                if (locations[city][name]) return { city: city, lat: locations[city][name][0], lng: locations[city][name][1] };
            }
            return null;
        }
        // Same estimate as trip_distance_km() in includes/locations.php
        function distanceKm(a, b) {
            var d = Math.sqrt(Math.pow((a.lat - b.lat) * 111, 2) + Math.pow((a.lng - b.lng) * 111, 2)) * 1.3;
            return Math.max(1.5, Math.round(d * 100) / 100);
        }
        function fare(type, km) {
            var f = fares[type], dist = Math.round(km * f.per_km * 100) / 100;
            return { base: f.base, distance: dist, total: Math.round((f.base + dist) * 100) / 100 };
        }
        function selectedType() {
            var r = form.querySelector('input[name="service_type"]:checked');
            return r ? r.value : 'car';
        }
        function setSum(key, text) { form.querySelector('[data-sum="' + key + '"]').textContent = text; }

        // Only allow destinations in the pick-up's city
        function limitDropoffCity() {
            var from = findPlace(pickup.value);
            dropoff.querySelectorAll('optgroup').forEach(function (g) {
                g.disabled = !!from && g.getAttribute('data-city') !== from.city;
            });
            if (from && dropoff.value && (findPlace(dropoff.value) || {}).city !== from.city) dropoff.value = '';
            Array.prototype.forEach.call(dropoff.options, function (o) { o.disabled = o.value !== '' && o.value === pickup.value; });
            if (dropoff.value === pickup.value) dropoff.value = '';
        }

        function update() {
            limitDropoffCity();
            var a = findPlace(pickup.value), b = findPlace(dropoff.value);
            var meta = form.querySelector('[data-trip-meta]');
            var warn = form.querySelector('[data-wallet-warning]');
            if (!a || !b || pickup.value === dropoff.value) {
                Object.keys(fares).forEach(function (t) {
                    form.querySelector('[data-price="' + t + '"]').textContent = 'from ' + money(fares[t].base);
                });
                ['distance', 'time', 'base', 'per_km', 'total'].forEach(function (k) { setSum(k, '—'); });
                meta.textContent = 'Choose pick-up and destination to see prices';
                if (warn) warn.hidden = true;
                return;
            }
            var km = distanceKm(a, b);
            Object.keys(fares).forEach(function (t) {
                form.querySelector('[data-price="' + t + '"]').textContent = money(fare(t, km).total);
            });
            var type = selectedType(), f = fare(type, km), mins = Math.round(km * 2.5 + 5);
            meta.textContent = km.toFixed(1) + ' km · about ' + mins + ' min';
            setSum('distance', km.toFixed(1) + ' km');
            setSum('time', 'about ' + mins + ' min');
            setSum('base', money(f.base));
            setSum('per_km', money(f.distance) + ' (' + money(fares[type].per_km) + '/km)');
            setSum('total', money(f.total));
            if (warn) warn.hidden = f.total <= balance;
        }

        form.addEventListener('change', update);
        var swap = form.querySelector('[data-swap]');
        if (swap) swap.addEventListener('click', function () {
            var t = pickup.value;
            pickup.value = dropoff.value;
            limitDropoffCity();
            dropoff.value = t;
            update();
        });
        // Choosing a card in the dropdown also selects "Card"
        var cardSelect = form.querySelector('select[name="card_id"]');
        if (cardSelect) cardSelect.addEventListener('change', function () {
            form.querySelector('input[name="payment_method"][value="card"]').checked = true;
        });
        // Prevent double booking from a double click
        form.addEventListener('submit', function () {
            var btn = form.querySelector('.book-btn');
            setTimeout(function () { btn.disabled = true; btn.lastChild.textContent = ' Booking…'; }, 0);
        });
        update();
    }

    /* ---------- Wallet page: quick amounts vs typed amount, card number spacing ---------- */
    var custom = document.querySelector('[data-custom-amount]');
    if (custom) {
        var chips = document.querySelectorAll('.amount-chips input');
        custom.addEventListener('input', function () {
            if (custom.value !== '') chips.forEach(function (c) { c.checked = false; });
        });
        chips.forEach(function (c) { c.addEventListener('change', function () { custom.value = ''; }); });
    }
    var cardNo = document.querySelector('[data-card-number]');
    if (cardNo) cardNo.addEventListener('input', function () {
        var digits = cardNo.value.replace(/\D/g, '').slice(0, 19);
        cardNo.value = digits.replace(/(\d{4})(?=\d)/g, '$1 ');
    });
    var exp = document.querySelector('[data-card-expiry]');
    if (exp) exp.addEventListener('input', function () {
        var d = exp.value.replace(/\D/g, '').slice(0, 4);
        exp.value = d.length > 2 ? d.slice(0, 2) + '/' + d.slice(2) : d;
    });

    /* ---------- Driver request list: reload every N seconds while visible ---------- */
    var auto = document.querySelector('[data-auto-refresh]');
    if (auto) {
        var secs = parseInt(auto.getAttribute('data-auto-refresh'), 10) || 10;
        setInterval(function () {
            var busy = document.activeElement && /^(SELECT|INPUT|TEXTAREA)$/.test(document.activeElement.tagName);
            if (!document.hidden && !busy) location.reload();
        }, secs * 1000);
    }

    /* ---------- Live trip: refresh when the status or driver changes ---------- */
    var live = document.querySelector('[data-live-trip]');
    if (live && window.fetch) {
        var status = live.getAttribute('data-status'), driver = live.getAttribute('data-driver');
        var check = function () {
            fetch(live.getAttribute('data-poll'), { credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (d) {
                    if (d && (d.status !== status || String(d.driver) !== driver)) location.reload();
                })
                .catch(function () {})
                .then(function () { setTimeout(check, document.hidden ? 15000 : 4000); });
        };
        setTimeout(check, 4000);
    }
})();
