/* Crab trip "road" progress (see includes/trip_road.php).
 * Not live tracking: the car drives along a drawn road and fills it like a progress bar,
 * always finishing within the given duration (5 s), whatever the real distance.
 *   to_pickup : driver (start of road) -> pick-up stop
 *   to_dropoff: pick-up stop -> destination (end of road)                                  */
(function () {
    'use strict';

    function ease(t) { return t < 0.5 ? 2 * t * t : 1 - Math.pow(-2 * t + 2, 2) / 2; }   // ease-in-out

    document.querySelectorAll('[data-trip-road]').forEach(function (root) {
        var road = root.querySelector('[data-road]');
        var fill = root.querySelector('[data-road-progress]');
        var car = root.querySelector('[data-road-car]');
        var percent = root.querySelector('[data-road-percent]');
        var status = root.querySelector('[data-road-status]');
        var aria = root.querySelector('[data-road-aria]');
        if (!road || !road.getTotalLength) return;

        var total = road.getTotalLength();
        var pickupAt = parseFloat(root.getAttribute('data-pickup-at')) || 0.3;
        var duration = Math.min(parseInt(root.getAttribute('data-duration'), 10) || 5000, 5000);   // never more than 5 s
        var toPickup = root.getAttribute('data-phase') === 'to_pickup';
        var from = toPickup ? 0 : pickupAt;
        var to = toPickup ? pickupAt : 1;

        function pointAt(frac) { return road.getPointAtLength(Math.max(0, Math.min(1, frac)) * total); }

        // Put the three stops on the road
        [['driver', 0], ['pickup', pickupAt], ['dropoff', 1]].forEach(function (s) {
            var g = root.querySelector('[data-stop="' + s[0] + '"]'), p = pointAt(s[1]);
            if (g) g.setAttribute('transform', 'translate(' + p.x.toFixed(1) + ' ' + p.y.toFixed(1) + ')');
        });

        fill.style.strokeDasharray = total + ' ' + total;

        // Place the car at a position (0..1 along the whole road), facing along the road
        function setPosition(frac) {
            var len = frac * total;
            fill.style.strokeDashoffset = (total - len).toFixed(2);
            var p = pointAt(frac);
            var a = road.getPointAtLength(Math.max(0, len - 2)), b = road.getPointAtLength(Math.min(total, len + 2));
            var angle = Math.atan2(b.y - a.y, b.x - a.x) * 180 / Math.PI;
            car.setAttribute('transform', 'translate(' + p.x.toFixed(1) + ' ' + p.y.toFixed(1) + ') rotate(' + angle.toFixed(1) + ')');
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

        setPosition(from);
        setPercent(0);

        if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            finish();   // no movement for people who asked for reduced motion — show the end state straight away
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
