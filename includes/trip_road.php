<?php
/**
 * "Road" trip progress shown to BOTH the passenger and the driver once a driver is assigned.
 *
 * This is NOT live GPS tracking: it's a loading bar drawn as a real street seen from above —
 * asphalt, lane markings, kerbs, pavements, trees and street lamps. The driver's car drives
 * along the left lane (Malaysia drives on the left) and a red route line fills in behind it,
 * always finishing within TRIP_ROAD_SECONDS seconds, whatever the real distance.
 *   - accepted : the car drives from where the driver started to the pick-up bay
 *   - ongoing  : the car drives from the pick-up bay to the drop-off bay
 * The card also holds the Emergency button (includes/emergency.php). While an emergency is
 * open, the car's hazard lights flash and an ambulance drives up behind it.
 * The animation is done by assets/js/trip-road.js, the emergency by assets/js/emergency.js.
 */

require_once __DIR__ . '/emergency.php';

const TRIP_ROAD_SECONDS = 5;          // the animation never takes longer than this
const TRIP_ROAD_PICKUP_AT = 0.32;     // where the pick-up bay sits along the road (0 = start, 1 = end)
const TRIP_ROAD_START_X = 70;         // road drawing: x of the start and the end of the route,
const TRIP_ROAD_END_X = 930;          // and y of the middle of the lane the car drives in
const TRIP_ROAD_LANE_Y = 85;

/** Paint colour for the car drawing, from the driver's "vehicle colour" (free text). */
function trip_road_car_colour(?string $colour): string
{
    $c = strtolower((string)$colour);
    $map = ['white' => '#F3F4F1', 'black' => '#1F2328', 'silver' => '#C3C8CE', 'grey' => '#7C838B', 'gray' => '#7C838B',
            'maroon' => '#7A1F2B', 'red' => '#C62828', 'blue' => '#1E5AA8', 'green' => '#2E7D4F', 'yellow' => '#E8B923',
            'orange' => '#E07020', 'brown' => '#7A4E2D', 'gold' => '#C9A54B', 'purple' => '#5B3A8C', 'beige' => '#D8C8A8'];
    foreach ($map as $word => $hex) {
        if (str_contains($c, $word)) return $hex;
    }
    return '#F3F4F1';
}

/** A car seen from above, pointing right, centred on 0,0 (58 × 28). */
function trip_road_car_svg(): string
{
    return '<ellipse class="veh-shadow" cx="2" cy="4" rx="31" ry="15"/>'
        . '<path class="car-halo" d="M -26 -14 H 17 Q 29 -14 29 -5 V 5 Q 29 14 17 14 H -26 Q -29 14 -29 11 V -11 Q -29 -14 -26 -14 Z"/>'
        . '<rect class="car-body" x="7" y="-17" width="5" height="4" rx="1.5"/><rect class="car-body" x="7" y="13" width="5" height="4" rx="1.5"/>'
        . '<path class="car-body" d="M -26 -14 H 17 Q 29 -14 29 -5 V 5 Q 29 14 17 14 H -26 Q -29 14 -29 11 V -11 Q -29 -14 -26 -14 Z"/>'
        . '<rect class="car-roof" x="-15" y="-11" width="21" height="22" rx="4"/>'
        . '<path class="car-glass" d="M 6 -11.5 L 15 -9 Q 17.5 0 15 9 L 6 11.5 Q 8 0 6 -11.5 Z"/>'
        . '<path class="car-glass" d="M -16 -10.5 L -23 -9 Q -25 0 -23 9 L -16 10.5 Q -17.5 0 -16 -10.5 Z"/>'
        . '<rect class="car-head" x="25.5" y="-12" width="3.5" height="6" rx="1.5"/><rect class="car-head" x="25.5" y="6" width="3.5" height="6" rx="1.5"/>'
        . '<rect class="car-tail" x="-29" y="-12" width="2.5" height="6" rx="1"/><rect class="car-tail" x="-29" y="6" width="2.5" height="6" rx="1"/>'
        . '<circle class="car-hazard" cx="27" cy="-12.5" r="3"/><circle class="car-hazard" cx="27" cy="12.5" r="3"/>'
        . '<circle class="car-hazard" cx="-27" cy="-12.5" r="3"/><circle class="car-hazard" cx="-27" cy="12.5" r="3"/>';
}

/** A motorbike and rider seen from above, pointing right, centred on 0,0 (drawn a little larger than life so it stays visible). */
function trip_road_bike_svg(): string
{
    return '<g transform="scale(1.25)"><ellipse class="veh-shadow" cx="2" cy="3" rx="25" ry="10"/>'
        . '<rect class="car-halo" x="-25.5" y="-12" width="51" height="24" rx="12"/>'
        . '<rect class="bike-tyre" x="-24" y="-3" width="11" height="6" rx="3"/><rect class="bike-tyre" x="13" y="-2.5" width="11" height="5" rx="2.5"/>'
        . '<path class="car-body" d="M -16 -5.5 Q -17.5 0 -16 5.5 L 13 4 Q 16 0 13 -4 Z"/>'
        . '<path class="bike-bar" d="M 10 -11 V 11"/>'
        . '<ellipse class="bike-rider" cx="-3" cy="0" rx="7" ry="10.5"/>'
        . '<circle class="bike-helmet" cx="1" cy="0" r="5.5"/>'
        . '<rect class="car-head" x="22" y="-1.5" width="3" height="3" rx="1"/>'
        . '<circle class="car-hazard" cx="21" cy="-6" r="2.6"/><circle class="car-hazard" cx="21" cy="6" r="2.6"/>'
        . '<circle class="car-hazard" cx="-20" cy="-5" r="2.6"/><circle class="car-hazard" cx="-20" cy="5" r="2.6"/></g>';
}

/** An ambulance seen from above, pointing right, centred on 0,0 (74 × 32). */
function trip_road_ambulance_svg(): string
{
    return '<ellipse class="veh-shadow" cx="3" cy="4" rx="39" ry="17"/>'
        . '<rect class="amb-body" x="-37" y="-16" width="74" height="32" rx="5"/>'
        . '<path class="amb-cab" d="M 21 -16 H 32 Q 37 -16 37 -11 V 11 Q 37 16 32 16 H 21 Z"/>'
        . '<path class="car-glass" d="M 24 -12 L 31 -10 Q 33 0 31 10 L 24 12 Q 26 0 24 -12 Z"/>'
        . '<rect class="amb-stripe" x="-37" y="-16" width="58" height="4"/><rect class="amb-stripe" x="-37" y="12" width="58" height="4"/>'
        . '<rect class="amb-stripe" x="-18" y="-3" width="16" height="6" rx="1"/><rect class="amb-stripe" x="-13" y="-8" width="6" height="16" rx="1"/>'
        . '<rect class="amb-light amb-red" x="12" y="-13" width="7" height="12" rx="2"/><rect class="amb-light amb-blue" x="12" y="1" width="7" height="12" rx="2"/>';
}

/** A map pin standing on the roadside kerb at x (tip at the kerb, head above it). */
function trip_road_pin(float $x, string $class, string $inner): string
{
    return '<g class="road-pin ' . $class . '" transform="translate(' . $x . ' 57)">'
        . '<ellipse class="pin-shadow" cx="0" cy="0" rx="6" ry="2.2"/>'
        . '<path class="pin-body" d="M 0 0 C -4 -8 -12 -13 -12 -22 A 12 12 0 1 1 12 -22 C 12 -13 4 -8 0 0 Z"/>'
        . $inner . '</g>';
}

/** A tree seen from above. */
function trip_road_tree(float $x, float $y, float $r): string
{
    return '<circle class="rd-tree-shade" cx="' . ($x + 2.5) . '" cy="' . ($y + 3) . '" r="' . $r . '"/>'
        . '<circle class="rd-tree" cx="' . $x . '" cy="' . $y . '" r="' . $r . '"/>'
        . '<circle class="rd-tree-hi" cx="' . round($x - $r * .3, 1) . '" cy="' . round($y - $r * .3, 1) . '" r="' . round($r * .45, 1) . '"/>';
}

/** A street lamp seen from above: pole on the pavement, arm reaching over the road, light pool below (seen in dark mode). */
function trip_road_lamp(float $x, bool $north, string $u): string
{
    [$pole, $head, $glow] = $north ? [48, 63, 78] : [180, 160, 150];
    return '<ellipse class="rd-glow" fill="url(#glow-' . $u . ')" cx="' . $x . '" cy="' . $glow . '" rx="58" ry="34"/>'
        . '<path class="rd-lamp-arm" d="M ' . $x . ' ' . $pole . ' V ' . $head . '"/>'
        . '<circle class="rd-lamp-pole" cx="' . $x . '" cy="' . $pole . '" r="3"/>'
        . '<rect class="rd-lamp-head" x="' . ($x - 6) . '" y="' . ($head - 3.5) . '" width="12" height="7" rx="2.5"/>';
}

/**
 * @param array  $ride   ride row with status, pickup/dropoff addresses, distance_km, id, driver_id,
 *                       and the driver's name/plate/vehicle (driver_name, plate_no, vehicle_color, vehicle_type)
 * @param string $viewer 'passenger' or 'driver'
 */
function trip_road(array $ride, string $viewer): string
{
    if (!emergency_ride_is_live($ride)) return '';
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

    $sos = emergency_view(emergency_for_ride($ride), $viewer);
    $u = 'r' . (int)$ride['id'];                     // keeps the <defs> ids unique on the page
    $Y = TRIP_ROAD_LANE_Y;
    $sx = TRIP_ROAD_START_X;
    $ex = TRIP_ROAD_END_X;
    $px = round($sx + ($ex - $sx) * TRIP_ROAD_PICKUP_AT, 1);
    $isBike = ($ride['vehicle_type'] ?? $ride['service_type'] ?? '') === 'bike';

    // Zebra crossing stripes and roadside trees
    $zebra = '';
    for ($y = 67; $y <= 158; $y += 13) $zebra .= '<rect x="560" y="' . $y . '" width="44" height="7"/>';
    $trees = '';
    foreach ([[150, 31, 12], [250, 29, 13], [430, 31, 12], [612, 28, 14], [760, 31, 12], [860, 29, 13],
              [55, 203, 14], [130, 213, 12], [228, 205, 13], [362, 208, 15], [548, 206, 13], [672, 211, 14], [880, 206, 13], [962, 214, 12]] as $t) {
        $trees .= trip_road_tree(...$t);
    }
    $lamps = trip_road_lamp(200, true, $u) . trip_road_lamp(505, true, $u) . trip_road_lamp(700, true, $u)
           . trip_road_lamp(290, false, $u) . trip_road_lamp(640, false, $u) . trip_road_lamp(905, false, $u);

    $svg = '<svg viewBox="0 0 1000 230" role="img" aria-label="Trip progress: the car drives along the road from the driver, to the pick-up point, to the destination" preserveAspectRatio="xMidYMid meet">'
        . '<defs>'
        . '<filter id="grain-' . $u . '" x="0" y="0" width="100%" height="100%"><feTurbulence type="fractalNoise" baseFrequency=".85" numOctaves="2" seed="7" stitchTiles="stitch"/><feColorMatrix type="saturate" values="0"/><feComposite in2="SourceGraphic" operator="in"/></filter>'
        . '<pattern id="kerb-' . $u . '" width="24" height="5" patternUnits="userSpaceOnUse"><rect class="rd-kerb-a" width="12" height="5"/><rect class="rd-kerb-b" x="12" width="12" height="5"/></pattern>'
        . '<pattern id="kerbv-' . $u . '" width="5" height="24" patternUnits="userSpaceOnUse"><rect class="rd-kerb-a" width="5" height="12"/><rect class="rd-kerb-b" y="12" width="5" height="12"/></pattern>'
        . '<pattern id="pave-' . $u . '" width="22" height="10" patternUnits="userSpaceOnUse"><rect class="rd-pave" width="22" height="10"/><path class="rd-joint" d="M 21.5 0 V 10"/></pattern>'
        . '<radialGradient id="glow-' . $u . '"><stop offset="0" stop-color="#FFE3A1" stop-opacity=".5"/><stop offset="1" stop-color="#FFE3A1" stop-opacity="0"/></radialGradient>'
        . '</defs>'

        // Verges: grass, shop-house roofs on the north side, a bus shelter on the south side
        . '<rect class="rd-grass" width="1000" height="230"/>'
        . '<g class="rd-grass-2"><ellipse cx="330" cy="30" rx="60" ry="9"/><ellipse cx="690" cy="36" rx="40" ry="6"/><ellipse cx="460" cy="212" rx="70" ry="10"/><ellipse cx="930" cy="196" rx="50" ry="7"/></g>'
        . '<g class="rd-roof"><rect x="0" y="0" width="118" height="21"/><rect x="290" y="0" width="120" height="18"/><rect x="455" y="0" width="128" height="20"/><rect x="784" y="0" width="56" height="22"/><rect x="890" y="0" width="110" height="19"/></g>'
        . '<g class="rd-roof-2"><rect x="124" y="0" width="74" height="23"/><rect x="640" y="0" width="100" height="21"/></g>'
        . '<g class="rd-ridge"><path d="M 0 10 H 118 M 124 11 H 198 M 290 9 H 410 M 455 10 H 583 M 640 10 H 740 M 784 11 H 840 M 890 9 H 1000"/></g>'
        . '<g class="rd-shelter"><rect x="414" y="186" width="66" height="13" rx="2"/><path d="M 418 192.5 H 476"/></g>'

        // Side road joining from the south, pavements and kerbs
        . '<rect class="rd-asphalt" x="744" y="160" width="72" height="70"/>'
        . '<rect class="rd-grain" x="744" y="160" width="72" height="70" filter="url(#grain-' . $u . ')"/>'
        . '<rect fill="url(#pave-' . $u . ')" x="0" y="44" width="1000" height="9"/>'
        . '<rect fill="url(#pave-' . $u . ')" x="0" y="175" width="739" height="9"/><rect fill="url(#pave-' . $u . ')" x="821" y="175" width="179" height="9"/>'
        . '<rect fill="url(#pave-' . $u . ')" x="730" y="184" width="9" height="46"/><rect fill="url(#pave-' . $u . ')" x="821" y="184" width="9" height="46"/>'
        . '<rect fill="url(#kerb-' . $u . ')" x="0" y="53" width="1000" height="5"/>'
        . '<rect fill="url(#kerb-' . $u . ')" x="0" y="170" width="744" height="5"/><rect fill="url(#kerb-' . $u . ')" x="816" y="170" width="184" height="5"/>'
        . '<rect fill="url(#kerbv-' . $u . ')" x="739" y="175" width="5" height="55"/><rect fill="url(#kerbv-' . $u . ')" x="816" y="175" width="5" height="55"/>'

        // The main road: asphalt with grain, worn tyre tracks, a patch and a manhole cover
        . '<rect class="rd-asphalt" x="0" y="58" width="1000" height="112"/>'
        . '<rect class="rd-grain" x="0" y="58" width="1000" height="112" filter="url(#grain-' . $u . ')"/>'
        . '<g class="rd-wear"><rect x="0" y="72" width="1000" height="6"/><rect x="0" y="92" width="1000" height="6"/><rect x="0" y="129" width="1000" height="6"/><rect x="0" y="149" width="1000" height="6"/></g>'
        . '<rect class="rd-patch" x="452" y="121" width="64" height="30" rx="2"/>'
        . '<circle class="rd-manhole" cx="668" cy="99" r="7"/>'

        // Road markings: edge lines, broken centre line, zebra crossing, give-way lines, pick-up and drop-off bays
        . '<g class="rd-paint">'
        . '<rect x="0" y="62" width="1000" height="2.5"/><rect x="0" y="164.5" width="744" height="2.5"/><rect x="816" y="164.5" width="184" height="2.5"/>'
        . $zebra
        . '</g>'
        . '<path class="rd-dash" d="M 0 114 H 550 M 614 114 H 1000"/>'
        . '<path class="rd-dash rd-dash-thin" d="M 748 172 H 814 M 748 177.5 H 814 M 780 186 V 230"/>'
        . '<rect class="rd-bay" x="' . ($px - 46) . '" y="67" width="92" height="43" rx="2"/>'
        . '<text class="rd-bay-text" x="' . $px . '" y="106" text-anchor="middle">PICK-UP</text>'
        . '<rect class="rd-bay" x="' . ($ex - 46) . '" y="67" width="92" height="43" rx="2"/>'
        . '<text class="rd-bay-text" x="' . $ex . '" y="106" text-anchor="middle">DROP-OFF</text>'

        // Street lamps (light pools show in dark mode), trees, a car parked at the far kerb
        . '<g class="rd-lamps">' . $lamps . '</g>'
        . '<g class="rd-trees">' . $trees . '</g>'
        . '<g class="road-vehicle rd-parked" transform="translate(150 150) rotate(180) scale(.92)" style="--car: #8A939C">' . trip_road_car_svg() . '</g>'

        // The route: dotted all the way, solid red behind the car (this is the "loading bar")
        . '<path class="road-route" d="M ' . $sx . ' ' . $Y . ' H ' . $ex . '"/>'
        . '<line class="road-progress-casing" data-road-casing x1="' . $sx . '" y1="' . $Y . '" x2="' . $sx . '" y2="' . $Y . '"/>'
        . '<line class="road-progress" data-road-progress x1="' . $sx . '" y1="' . $Y . '" x2="' . $sx . '" y2="' . $Y . '"/>'

        // Stops: pins on the kerb
        . trip_road_pin($sx, 'pin-driver', '<text class="pin-letter" x="0" y="-17.5" text-anchor="middle">D</text>')
        . trip_road_pin($px, 'pin-pickup', '<circle class="pin-dot" cx="0" cy="-22" r="4.5"/>')
        . trip_road_pin($ex, 'pin-dropoff', '<path class="pin-flag" d="M -4 -29 V -15 M -4 -29 H 5 L 3 -25.5 L 5 -22 H -4"/>')

        // Emergency: an ambulance that drives up behind the car (shown by trip-road.js)
        . '<g class="road-vehicle road-ambulance" data-ambulance transform="translate(-60 ' . $Y . ')">' . trip_road_ambulance_svg() . '</g>'

        // The driver's car (or motorbike), in the colour of the real vehicle
        . '<g class="road-vehicle road-car" data-road-car transform="translate(' . $sx . ' ' . $Y . ')" style="--car: ' . trip_road_car_colour($ride['vehicle_color'] ?? null) . '">'
        . ($isBike ? trip_road_bike_svg() : trip_road_car_svg()) . '</g>'
        . '</svg>';

    return '<article class="card trip-road" data-trip-road'
        . ' data-phase="' . ($toPickup ? 'to_pickup' : 'to_dropoff') . '"'
        . ' data-pickup-at="' . TRIP_ROAD_PICKUP_AT . '"'
        . ' data-start-x="' . $sx . '" data-end-x="' . $ex . '" data-lane-y="' . $Y . '"'
        . ' data-duration="' . (TRIP_ROAD_SECONDS * 1000) . '"'
        . ' data-done-text="' . e($done) . '"'
        . emergency_attrs($ride, $viewer, $sos) . '>'
        . '<header class="card-head"><div><h2>' . e($title) . '</h2>'
        . '<p class="muted">Trip #' . (int)$ride['id'] . ' · ' . e(number_format((float)$ride['distance_km'], 1)) . ' km · progress shown for illustration, not live GPS</p></div>'
        . '<div class="trip-road-tools"><span class="road-percent" data-road-percent>0%</span>' . emergency_button() . '</div></header>'
        . emergency_banner()
        . '<div class="road-map">' . $svg . '</div>'
        . '<div class="road-steps">'
        . '<div><span class="dot driver"></span><small class="muted">Driver</small><strong>' . e($driverLabel) . '</strong></div>'
        . '<div><span class="dot start"></span><small class="muted">Pick-up</small><strong>' . e($ride['pickup_address']) . '</strong></div>'
        . '<div><span class="dot end"></span><small class="muted">Destination</small><strong>' . e($ride['dropoff_address']) . '</strong></div>'
        . '</div>'
        . '<p class="road-status" data-road-status role="status" aria-live="polite">' . e($going) . '</p>'
        . '<div class="sr-only" role="progressbar" aria-label="Trip progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" data-road-aria></div>'
        . '<noscript><p class="small muted">' . e($done) . '</p></noscript>'
        . emergency_dialog($ride)
        . '</article>';
}
