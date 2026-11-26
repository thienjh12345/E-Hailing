<?php
/**
 * "Road" trip progress shown to BOTH the passenger and the driver once a driver is assigned.
 *
 * This is NOT live GPS tracking: it's a progress bar drawn as a winding road
 * (driver → pick-up → destination). A car drives along the road and fills it in,
 * always finishing within TRIP_ROAD_SECONDS seconds, whatever the real distance.
 *   - accepted : the car drives from the driver to the pick-up point
 *   - ongoing  : the car drives from the pick-up point to the destination
 * The animation is done by assets/js/trip-road.js.
 */

const TRIP_ROAD_SECONDS = 5;          // the animation never takes longer than this
const TRIP_ROAD_PICKUP_AT = 0.32;     // where the pick-up stop sits along the road (0 = start, 1 = end)

/**
 * @param array  $ride   ride row with status, pickup/dropoff addresses, distance_km, id
 *                       and the driver's name/plate (driver_name/plate_no)
 * @param string $viewer 'passenger' or 'driver'
 */
function trip_road(array $ride, string $viewer): string
{
    if (!in_array($ride['status'], ['accepted', 'ongoing'], true)) return '';
    $toPickup = $ride['status'] === 'accepted';
    $driverLabel = trim(($ride['driver_name'] ?? 'Driver') . (!empty($ride['plate_no']) ? ' · ' . $ride['plate_no'] : ''));

    if ($viewer === 'driver') {
        $title = $toPickup ? 'Heading to the pick-up point' : 'Heading to the destination';
        $done  = $toPickup ? 'You\'ve reached the pick-up point — press “Start trip” once the passenger is in.' : 'You\'ve reached the destination — press “Complete trip”.';
        $going = $toPickup ? 'Driving to the pick-up point…' : 'Driving to the destination…';
    } else {
        $title = $toPickup ? 'Your driver is on the way' : 'On the way to your destination';
        $done  = $toPickup ? 'Your driver is arriving at the pick-up point.' : 'Arriving at your destination.';
        $going = $toPickup ? 'Your driver is heading to you…' : 'Heading to your destination…';
    }

    // One winding road across a stylised map. All stops/car are placed along it by trip-road.js.
    $road = 'M 50 205 C 150 205, 170 120, 265 118 S 400 210, 505 172 S 625 58, 730 76 S 860 182, 950 92';

    $h = '<article class="card trip-road" data-trip-road'
        . ' data-phase="' . ($toPickup ? 'to_pickup' : 'to_dropoff') . '"'
        . ' data-pickup-at="' . TRIP_ROAD_PICKUP_AT . '"'
        . ' data-duration="' . (TRIP_ROAD_SECONDS * 1000) . '"'
        . ' data-done-text="' . e($done) . '">'
        . '<header class="card-head"><div><h2>' . e($title) . '</h2>'
        . '<p class="muted">Trip #' . (int)$ride['id'] . ' · ' . e(number_format((float)$ride['distance_km'], 1)) . ' km · progress shown for illustration, not live GPS</p></div>'
        . '<span class="road-percent" data-road-percent>0%</span></header>'

        . '<div class="road-map">'
        . '<svg viewBox="0 0 1000 260" role="img" aria-label="Trip progress along the road from the driver, to the pick-up point, to the destination" preserveAspectRatio="xMidYMid meet">'
        // map-like background: blocks, a park and a river
        . '<rect class="map-ground" x="0" y="0" width="1000" height="260"/>'
        . '<path class="map-river" d="M 0 40 C 120 60, 200 10, 330 30 S 520 70, 640 20 S 860 10, 1000 35 L 1000 0 L 0 0 Z"/>'
        . '<rect class="map-park" x="560" y="170" width="150" height="70" rx="14"/>'
        . '<rect class="map-park" x="120" y="40" width="90" height="50" rx="12"/>'
        . '<g class="map-blocks">'
        . '<rect x="20" y="70" width="80" height="60" rx="6"/><rect x="300" y="160" width="110" height="80" rx="6"/>'
        . '<rect x="330" y="50" width="120" height="55" rx="6"/><rect x="780" y="150" width="90" height="85" rx="6"/>'
        . '<rect x="870" y="20" width="110" height="45" rx="6"/><rect x="600" y="95" width="70" height="40" rx="6"/>'
        . '<rect x="20" y="225" width="160" height="30" rx="6"/><rect x="890" y="200" width="100" height="50" rx="6"/>'
        . '</g>'
        . '<path class="map-street" d="M 0 145 L 1000 140"/><path class="map-street" d="M 470 0 L 455 260"/><path class="map-street" d="M 760 0 L 790 260"/>'
        // the road (the "progress bar")
        . '<path class="road-base" d="' . $road . '" data-road/>'
        . '<path class="road-progress" d="' . $road . '" data-road-progress/>'
        . '<path class="road-center" d="' . $road . '"/>'
        // stops
        . '<g class="road-stop stop-driver" data-stop="driver"><circle r="13"/><text y="5" text-anchor="middle">D</text></g>'
        . '<g class="road-stop stop-pickup" data-stop="pickup"><circle r="13"/><circle class="stop-inner" r="5"/></g>'
        . '<g class="road-stop stop-dropoff" data-stop="dropoff"><path d="M -2 6 L -2 -26 L 18 -18 L -2 -10" /><circle r="6"/></g>'
        // the car (drawn pointing right, centred on 0,0)
        . '<g class="road-car" data-road-car transform="translate(50 205)">'
        . '<rect x="-17" y="-10" width="34" height="20" rx="7" class="car-body"/>'
        . '<rect x="-3" y="-7" width="11" height="14" rx="3" class="car-glass"/>'
        . '<rect x="11" y="-8" width="4" height="4" rx="1" class="car-light"/><rect x="11" y="4" width="4" height="4" rx="1" class="car-light"/>'
        . '</g>'
        . '</svg></div>'

        . '<div class="road-steps">'
        . '<div><span class="dot driver"></span><small class="muted">Driver</small><strong>' . e($driverLabel) . '</strong></div>'
        . '<div><span class="dot start"></span><small class="muted">Pick-up</small><strong>' . e($ride['pickup_address']) . '</strong></div>'
        . '<div><span class="dot end"></span><small class="muted">Destination</small><strong>' . e($ride['dropoff_address']) . '</strong></div>'
        . '</div>'
        . '<p class="road-status" data-road-status role="status" aria-live="polite">' . e($going) . '</p>'
        . '<div class="sr-only" role="progressbar" aria-label="Trip progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" data-road-aria></div>'
        . '<noscript><p class="small muted">' . e($done) . '</p></noscript>'
        . '</article>';

    return $h;
}
