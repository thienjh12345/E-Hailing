/* Crab trip "road" progress (see includes/trip_road.php).
 * Not live tracking: a street seen from above works as a loading bar. The car drives along
 * the left lane and the red route line fills in behind it, always finishing within the given
 * duration (5 s), whatever the real distance.
 *   to_pickup : start of the road -> pick-up bay
 *   to_dropoff: pick-up bay       -> drop-off bay (end of the road)
 * Emergency (assets/js/emergency.js sends a "crab:sos" event on the card):
 *   the car's hazard lights flash and an ambulance drives up behind it, arriving exactly
 *   when the (pretend) help arrives.                                                        */
(function () {
    'use strict';

    function ease(t) { return t < 0.5 ? 2 * t * t : 1 - Math.pow(-2 * t + 2, 2) / 2; }   // ease-in-out
    var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    document.querySelectorAll('[data-trip-road]').forEach(function (root) {
        var car = root.querySelector('[data-road-car]');
        var fill = root.querySelector('[data-road-progress]');
        var casing = root.querySelector('[data-road-casing]');
        var amb = root.querySelector('[data-ambulance]');
        var percent = root.querySelector('[data-road-percent]');
        var status = root.querySelector('[data-road-status]');
        var aria = root.querySelector('[data-road-aria]');
        if (!car || !fill) return;

        var startX = parseFloat(root.getAttribute('data-start-x'));
        var endX = parseFloat(root.getAttribute('data-end-x'));
        var laneY = parseFloat(root.getAttribute('data-lane-y'));
        var pickupAt = parseFloat(root.getAttribute('data-pickup-at')) || 0.3;
        var duration = Math.min(parseInt(root.getAttribute('data-duration'), 10) || 5000, 5000);   // never more than 5 s
        var toPickup = root.getAttribute('data-phase') === 'to_pickup';
        var from = toPickup ? 0 : pickupAt;
        var to = toPickup ? pickupAt : 1;
        var carX = startX;

        function xAt(frac) { return startX + (endX - startX) * Math.max(0, Math.min(1, frac)); }

        // Place the car at a position (0..1 along the whole road) and fill the route up to it
        function setPosition(frac) {
            carX = xAt(frac);
            car.setAttribute('transform', 'translate(' + carX.toFixed(1) + ' ' + laneY + ')');
            fill.setAttribute('x2', carX.toFixed(1));
            casing.setAttribute('x2', carX.toFixed(1));
        }

        // Progress of THIS part of the trip, 0..100
        function setPercent(phaseProgress) {
            var pct = Math.round(phaseProgress * 100);
            percent.textContent = pct + '%';
            aria.setAttribute('aria-valuenow', pct);
        }

        function finish() {
            setPosition(to);
            setPercent(1);
            status.textContent = root.getAttribute('data-done-text');
            root.classList.add('arrived');
        }

        /* ---------- Emergency: ambulance driving up behind the car ---------- */
        var sos = null, ambTimer = null;
        var AMB_START = -60, AMB_GAP = 74;   // starts off the left edge, stops this far behind the car's centre

        function placeAmbulance() {
            ambTimer = null;
            if (!sos) return;
            var left = Math.max(0, sos.left - (performance.now() - sos.at) / 1000);
            var p = 1 - left / sos.total;
            var target = Math.max(AMB_START, carX - AMB_GAP);
            var x = AMB_START + (target - AMB_START) * ease(Math.max(0, Math.min(1, p)));
            amb.setAttribute('transform', 'translate(' + x.toFixed(1) + ' ' + laneY + ')');
            root.classList.toggle('help-arrived', left <= 0);
            if (left > 0) ambTimer = reduceMotion ? setTimeout(placeAmbulance, 1000) : requestAnimationFrame(placeAmbulance);
        }
        function stopAmbulance() {
            if (ambTimer !== null) { reduceMotion ? clearTimeout(ambTimer) : cancelAnimationFrame(ambTimer); ambTimer = null; }
        }

        // detail: null (no emergency) or {left: seconds until help arrives, total: seconds from call to arrival}
        root.addEventListener('crab:sos', function (e) {
            var d = e.detail;
            sos = d ? { left: d.left, total: Math.max(1, d.total), at: performance.now() } : null;
            root.classList.toggle('sos-on', !!sos);
            stopAmbulance();
            if (sos) placeAmbulance();
            else root.classList.remove('help-arrived');
        });

        /* ---------- The drive ---------- */
        setPosition(from);
        setPercent(0);

        // No movement for people who asked for reduced motion, and none during an emergency
        // (the car has stopped) — show the end state straight away.
        if (reduceMotion || root.hasAttribute('data-sos-active')) {
            finish();
            return;
        }

        var start = null;
        function frame(now) {
            if (start === null) start = now;
            var t = Math.min(1, (now - start) / duration);
            var k = ease(t);
            setPosition(from + (to - from) * k);
            setPercent(k);
            if (t < 1) requestAnimationFrame(frame);
            else finish();
        }
        requestAnimationFrame(frame);
    });
})();
