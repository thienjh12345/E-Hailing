/* Crab map picker for the booking page (passenger/book.php).
 *
 *  - Drag the map; the pin stays in the middle and lifts up a little while the map moves.
 *  - When the map stops, the address under the pin is looked up (OpenStreetMap Nominatim).
 *  - "Set pick-up here" / "Set destination here" saves the point into hidden form fields.
 *  - Search: instant matches from Crab's place list + OpenStreetMap search on Enter / Search.
 *
 * The server re-checks everything (service area, same city, distance, fare).
 * If Leaflet can't load, the page keeps the plain list of places (the fallback).          */
(function () {
    'use strict';
    var root = document.querySelector('[data-place-picker]');
    if (!root) return;
    if (!window.L) return;   // map library didn't load: keep the fallback lists

    var form = root.closest('form');
    var AREAS = JSON.parse(root.getAttribute('data-areas'));
    var PLACES = JSON.parse(root.getAttribute('data-places'));
    var CITIES = JSON.parse(root.getAttribute('data-cities'));
    var NOMINATIM = 'https://nominatim.openstreetmap.org';
    var FIELDS = ['pickup', 'dropoff'];
    var WORD = { pickup: 'pick-up', dropoff: 'destination' };

    var $ = function (sel) { return root.querySelector(sel); };
    var mapEl = $('[data-pp-map]'), pin = $('[data-pp-pin]'), sheetAddr = $('[data-pp-address]'),
        sheetNote = $('[data-pp-note]'), confirmBtn = $('[data-pp-confirm]');

    // Show the map, drop the fallback lists so they are not submitted
    $('[data-pp-mapwrap]').hidden = false;
    var fallback = $('[data-pp-fallback]');
    if (fallback) fallback.remove();

    /* ---------- helpers ---------- */
    function km(aLat, aLng, bLat, bLng) { return Math.sqrt(Math.pow((aLat - bLat) * 111, 2) + Math.pow((aLng - bLng) * 111, 2)); }
    function cityFor(lat, lng) {
        for (var c in AREAS) if (km(lat, lng, AREAS[c].lat, AREAS[c].lng) <= AREAS[c].radius_km) return c;
        return null;
    }
    function input(field, part) { return root.querySelector('[data-pp-value="' + field + '-' + part + '"]'); }
    function getPoint(field) {
        var lat = parseFloat(input(field, 'lat').value), lng = parseFloat(input(field, 'lng').value);
        return isNaN(lat) || isNaN(lng) ? null : { lat: lat, lng: lng, address: input(field, 'address').value };
    }
    function setPoint(field, p) {
        input(field, 'lat').value = p ? p.lat.toFixed(7) : '';
        input(field, 'lng').value = p ? p.lng.toFixed(7) : '';
        input(field, 'address').value = p ? p.address : '';
        var label = root.querySelector('[data-pp-label="' + field + '"]');
        label.textContent = p ? p.address : 'Choose on the map';
        label.classList.toggle('muted', !p);
        var err = root.querySelector('[data-pp-error="' + field + '"]');
        if (err && p) err.hidden = true;
        drawMarkers();
        form.dispatchEvent(new Event('change', { bubbles: true }));   // updates the prices (booking.js)
    }
    function nearestPlace(lat, lng) {
        var best = null, bestKm = 0.6;   // only call it "near X" when within 600 m
        for (var c in PLACES) for (var name in PLACES[c]) {
            var d = km(lat, lng, PLACES[c][name][0], PLACES[c][name][1]);
            if (d < bestKm) { bestKm = d; best = name; }
        }
        return best;
    }

    /* ---------- map ---------- */
    var start = getPoint('pickup') || getPoint('dropoff');
    var home = AREAS[root.getAttribute('data-default-city')] || AREAS[Object.keys(AREAS)[0]];
    var map = L.map(mapEl, { zoomControl: true, attributionControl: true })
        .setView(start ? [start.lat, start.lng] : [home.lat, home.lng], start ? 16 : home.zoom);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors'
    }).addTo(map);

    var active = getPoint('pickup') && !getPoint('dropoff') ? 'dropoff' : 'pickup';
    var markers = {};
    function drawMarkers() {
        FIELDS.forEach(function (f) {
            if (markers[f]) { map.removeLayer(markers[f]); markers[f] = null; }
            var p = getPoint(f);
            if (!p || f === active) return;   // the one being edited is shown by the centre pin
            markers[f] = L.marker([p.lat, p.lng], {
                icon: L.divIcon({ className: 'pp-marker pp-marker-' + f, html: '<span></span>', iconSize: [18, 18], iconAnchor: [9, 9] }),
                keyboard: false, interactive: false
            }).addTo(map);
        });
    }

    function setActive(field, fly) {
        active = field;
        root.querySelectorAll('[data-pp-tab]').forEach(function (b) {
            var on = b.getAttribute('data-pp-tab') === field;
            b.classList.toggle('active', on);
            b.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
        pin.className = 'pp-pin pp-pin-' + field;
        confirmBtn.textContent = 'Set ' + WORD[field] + ' here';
        drawMarkers();
        var p = getPoint(field);
        if (fly && p) map.setView([p.lat, p.lng], Math.max(map.getZoom(), 16));
        else lookupCentre();
    }

    /* ---------- the pin lifts while the map moves ---------- */
    map.on('movestart', function () {
        pin.classList.add('lifting');
        confirmBtn.disabled = true;
        sheetAddr.textContent = 'Release to set the pin…';
        sheetNote.textContent = '';
    });
    map.on('moveend', function () {
        pin.classList.remove('lifting');
        lookupCentre();
    });

    /* ---------- address under the pin (reverse geocoding, polite + cached) ---------- */
    var current = null;           // {lat, lng, address, city}
    var pendingLabel = null;      // set when the user picked a search result: use its name, no lookup needed
    var cache = {}, lastRequest = 0, lookupTimer = null, lookupSeq = 0;

    function labelFrom(r) {
        var a = r.address || {}, parts = [];
        [r.name, [a.house_number, a.road].filter(Boolean).join(' '), a.suburb || a.neighbourhood || a.quarter || a.village,
         a.city || a.town || a.municipality || a.county || a.state].forEach(function (x) {
            if (x && parts.indexOf(x) === -1) parts.push(x);
        });
        return parts.length ? parts.join(', ') : (r.display_name || '');
    }

    function showCurrent() {
        var other = getPoint(active === 'pickup' ? 'dropoff' : 'pickup');
        var otherCity = other ? cityFor(other.lat, other.lng) : null;
        sheetAddr.textContent = current.address;
        if (!current.city) {
            sheetNote.textContent = 'Crab isn’t available here yet — we operate in the Klang Valley and Kota Kinabalu.';
            confirmBtn.disabled = true;
        } else if (otherCity && otherCity !== current.city) {
            sheetNote.textContent = 'Pick-up and destination must be in the same city (' + CITIES[otherCity] + ').';
            confirmBtn.disabled = true;
        } else {
            sheetNote.textContent = CITIES[current.city];
            confirmBtn.disabled = false;
        }
    }

    function lookupCentre() {
        var c = map.getCenter(), lat = c.lat, lng = c.lng;
        var city = cityFor(lat, lng);
        var near = nearestPlace(lat, lng);
        var fallbackLabel = near ? 'Near ' + near : 'Pinned location (' + lat.toFixed(5) + ', ' + lng.toFixed(5) + ')';
        current = { lat: lat, lng: lng, city: city, address: pendingLabel || fallbackLabel };
        if (pendingLabel) { pendingLabel = null; showCurrent(); return; }
        showCurrent();
        if (!city) return;   // no need to look up addresses outside the service area

        var key = lat.toFixed(4) + ',' + lng.toFixed(4);   // ~10 m
        if (cache[key]) { current.address = cache[key]; showCurrent(); return; }

        sheetAddr.textContent = 'Finding the address…';
        confirmBtn.disabled = true;   // wait for the address (max ~6 s, then the fallback label is used)
        clearTimeout(lookupTimer);
        var seq = ++lookupSeq;
        var wait = Math.max(500, 1100 - (Date.now() - lastRequest));   // at most ~1 request per second
        lookupTimer = setTimeout(function () {
            lastRequest = Date.now();
            var ctrl = window.AbortController ? new AbortController() : null;
            if (ctrl) setTimeout(function () { ctrl.abort(); }, 6000);
            fetch(NOMINATIM + '/reverse?format=jsonv2&zoom=18&addressdetails=1&accept-language=en&lat=' + lat + '&lon=' + lng, ctrl ? { signal: ctrl.signal } : {})
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (r) {
                    if (seq !== lookupSeq) return;          // the map moved again meanwhile
                    var label = r && !r.error ? labelFrom(r) : '';
                    if (label) cache[key] = label;
                    current.address = label || fallbackLabel;
                    showCurrent();
                })
                .catch(function () { if (seq === lookupSeq) { current.address = fallbackLabel; showCurrent(); } });
        }, wait);
    }

    /* ---------- buttons ---------- */
    confirmBtn.addEventListener('click', function () {
        if (!current || confirmBtn.disabled) return;
        var field = active;
        setPoint(field, { lat: current.lat, lng: current.lng, address: current.address.slice(0, 255) });
        var other = field === 'pickup' ? 'dropoff' : 'pickup';
        if (!getPoint(other)) setActive(other, false);   // next: set the other one
        else {
            sheetNote.textContent = (field === 'pickup' ? 'Pick-up' : 'Destination') + ' set ✓';
            drawMarkers();
        }
    });

    root.querySelectorAll('[data-pp-tab]').forEach(function (b) {
        b.addEventListener('click', function () { setActive(b.getAttribute('data-pp-tab'), true); });
    });

    $('[data-pp-swap]').addEventListener('click', function () {
        var a = getPoint('pickup'), b = getPoint('dropoff');
        setPoint('pickup', b);
        setPoint('dropoff', a);
        setActive(active, true);
    });

    /* ---------- search ---------- */
    var box = $('[data-pp-search-box]'), q = $('[data-pp-search-input]'), list = $('[data-pp-results]');
    $('[data-pp-search-open]').addEventListener('click', function () {
        box.hidden = !box.hidden;
        if (!box.hidden) { q.focus(); showLocal(); }
    });
    $('[data-pp-search-close]').addEventListener('click', function () { box.hidden = true; });

    function resultItem(label, sub, lat, lng, ok) {
        var li = document.createElement('li');
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.disabled = !ok;
        var strong = document.createElement('strong'); strong.textContent = label;
        var small = document.createElement('small'); small.textContent = ok ? sub : sub + ' · outside Crab’s service area';
        btn.appendChild(strong); btn.appendChild(small);
        btn.addEventListener('click', function () {
            box.hidden = true;
            pendingLabel = label;   // use the result's own name — no address lookup needed
            var moved = false, onStart = function () { moved = true; };
            map.once('movestart', onStart);
            map.setView([lat, lng], 17);   // may move instantly (long jumps) or animate; either way 'moveend' runs lookupCentre
            if (!moved) { map.off('movestart', onStart); lookupCentre(); }   // already there: nothing will move
        });
        li.appendChild(btn);
        return li;
    }
    function message(text) {
        var li = document.createElement('li');
        li.className = 'pp-msg';
        li.textContent = text;
        return li;
    }

    // Crab's own place list: instant, works offline
    function showLocal() {
        var term = q.value.trim().toLowerCase();
        list.textContent = '';
        var shown = 0;
        for (var c in PLACES) for (var name in PLACES[c]) {
            if (shown >= 6) break;
            if (term && name.toLowerCase().indexOf(term) === -1) continue;
            list.appendChild(resultItem(name, CITIES[c] + ' · Popular place', PLACES[c][name][0], PLACES[c][name][1], true));
            shown++;
        }
        if (!shown && term) list.appendChild(message('Press Search to look for “' + q.value.trim() + '” on the map.'));
    }
    q.addEventListener('input', showLocal);

    var searchSeq = 0;
    function searchOnline() {
        var term = q.value.trim();
        if (term.length < 3) { showLocal(); list.appendChild(message('Type at least 3 letters to search the map.')); return; }
        showLocal();
        var loading = message('Searching the map…');
        list.appendChild(loading);
        var seq = ++searchSeq;
        var area = AREAS[(current && current.city) || root.getAttribute('data-default-city')] || home;
        var d = 0.6;   // prefer results around the current city, but allow others
        var viewbox = [area.lng - d, area.lat + d, area.lng + d, area.lat - d].join(',');
        var wait = Math.max(0, 1100 - (Date.now() - lastRequest));
        setTimeout(function () {
            lastRequest = Date.now();
            var ctrl = window.AbortController ? new AbortController() : null;
            if (ctrl) setTimeout(function () { ctrl.abort(); }, 8000);   // don't leave "Searching…" forever
            fetch(NOMINATIM + '/search?format=jsonv2&addressdetails=1&accept-language=en&countrycodes=my&limit=8&viewbox=' + viewbox + '&q=' + encodeURIComponent(term), ctrl ? { signal: ctrl.signal } : {})
                .then(function (r) { return r.ok ? r.json() : []; })
                .then(function (rows) {
                    if (seq !== searchSeq) return;
                    loading.remove();
                    if (!rows.length) { list.appendChild(message('No places found for “' + term + '”. Try another name, or drag the map.')); return; }
                    rows.forEach(function (r) {
                        var lat = parseFloat(r.lat), lng = parseFloat(r.lon), city = cityFor(lat, lng);
                        var label = labelFrom(r) || r.display_name;
                        var sub = city ? CITIES[city] : (r.address && (r.address.state || r.address.city)) || 'Malaysia';
                        list.appendChild(resultItem(label, sub, lat, lng, !!city));
                    });
                })
                .catch(function () {
                    if (seq !== searchSeq) return;
                    loading.textContent = 'Map search isn’t available right now — pick a popular place above or drag the map.';
                });
        }, wait);
    }
    $('[data-pp-search-go]').addEventListener('click', searchOnline);
    q.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); searchOnline(); }   // don't submit the booking form
        if (e.key === 'Escape') { box.hidden = true; }
    });

    /* ---------- don't submit without both points ---------- */
    form.addEventListener('submit', function (e) {
        var missing = FIELDS.filter(function (f) { return !getPoint(f); });
        if (!missing.length) return;
        e.preventDefault();
        missing.forEach(function (f) {
            var err = root.querySelector('[data-pp-error="' + f + '"]');
            err.textContent = 'Set your ' + WORD[f] + ' on the map.';
            err.hidden = false;
        });
        setActive(missing[0], false);
        root.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }, true);   // runs before booking.js disables the button

    setActive(active, false);
    drawMarkers();
    setTimeout(function () { map.invalidateSize(); }, 100);   // the map box was hidden until now
})();
