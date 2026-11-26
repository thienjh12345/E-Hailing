<?php
/**
 * Places passengers can book between, grouped by city, and the fare formula.
 *
 * Crab has no maps API, so pick-up / drop-off are chosen from this list. A trip must
 * start and end in the same city, and only drivers working in that city see it.
 * The fare formula is the same one used for the demo trips in database/seed.sql.
 */

const CITIES = [
    'klang_valley'  => 'Klang Valley',
    'kota_kinabalu' => 'Kota Kinabalu',
];

/** city => [place name => [lat, lng]] */
const LOCATIONS = [
    'klang_valley' => [
        '1 Utama, Petaling Jaya'   => [3.1503, 101.6157],
        'Bangsar South'            => [3.1106, 101.6656],
        'Bukit Bintang'            => [3.1466, 101.7108],
        'Cheras Leisure Mall'      => [3.0889, 101.7425],
        'Damansara Heights'        => [3.1520, 101.6599],
        'IOI City Mall, Putrajaya' => [2.9701, 101.7135],
        'Kepong Metropolitan Park' => [3.2139, 101.6347],
        'KL Sentral'               => [3.1340, 101.6865],
        'KLCC, Kuala Lumpur'       => [3.1579, 101.7116],
        'KLIA Terminal 1'          => [2.7456, 101.7072],
        'Mid Valley Megamall'      => [3.1176, 101.6774],
        'Pavilion KL'              => [3.1490, 101.7134],
        'Setia Alam'               => [3.1046, 101.4553],
        'Subang Jaya SS15'         => [3.0770, 101.5887],
        'Sunway Pyramid'           => [3.0731, 101.6070],
        'TAR UMT, Setapak'         => [3.2160, 101.7266],
        'Wangsa Maju LRT'          => [3.2056, 101.7319],
    ],
    'kota_kinabalu' => [
        '1Borneo Hypermall'                  => [6.0377, 116.1179],
        'Damai Plaza, Luyang'                => [5.9650, 116.0880],
        'Donggongon Town, Penampang'         => [5.9175, 116.1092],
        'Gaya Street, Kota Kinabalu'         => [5.9839, 116.0767],
        'Imago Shopping Mall, Kota Kinabalu' => [5.9700, 116.0667],
        'Inanam Town'                        => [6.0130, 116.1290],
        'Kota Kinabalu International Airport' => [5.9372, 116.0515],
        'Likas Bay'                          => [5.9980, 116.0960],
        'Lintas Square, Luyang'              => [5.9590, 116.0940],
        'Menggatal Town'                     => [6.0560, 116.1505],
        'Oceanus Waterfront Mall'            => [5.9800, 116.0720],
        'Putatan Town'                       => [5.8890, 116.0560],
        'Suria Sabah Shopping Mall'          => [5.9858, 116.0797],
        'Sutera Harbour'                     => [5.9674, 116.0581],
        'Tanjung Aru Beach'                  => [5.9497, 116.0430],
        'Universiti Malaysia Sabah (UMS)'    => [6.0339, 116.1219],
    ],
];

/** Pricing per service type (RM). Same numbers as the demo data. */
const FARES = [
    'car'     => ['base' => 4.00, 'per_km' => 1.20, 'label' => 'Crab Car',     'seats' => '4 seats',          'desc' => 'Affordable everyday rides'],
    'premium' => ['base' => 7.00, 'per_km' => 2.10, 'label' => 'Crab Premium', 'seats' => '4 seats',          'desc' => 'Newer, more spacious cars'],
    'bike'    => ['base' => 2.50, 'per_km' => 0.80, 'label' => 'Crab Bike',    'seats' => '1 rider + helmet', 'desc' => 'Beat the traffic'],
];

/** [city, lat, lng] for a place name, or null if it isn't on the list. */
function find_location(string $name): ?array
{
    foreach (LOCATIONS as $city => $places) {
        if (isset($places[$name])) return [$city, $places[$name][0], $places[$name][1]];
    }
    return null;
}

/** Road distance estimate: straight line × 1.3, at least 1.5 km. */
function trip_distance_km(float $lat1, float $lng1, float $lat2, float $lng2): float
{
    return max(1.5, round(sqrt((($lat1 - $lat2) * 111) ** 2 + (($lng1 - $lng2) * 111) ** 2) * 1.3, 2));
}

/** Fare breakdown for a service type and distance. */
function trip_fare(string $type, float $km): array
{
    $f = FARES[$type];
    $distanceCharge = round($km * $f['per_km'], 2);
    return ['base' => $f['base'], 'per_km' => $f['per_km'], 'distance' => $distanceCharge, 'total' => round($f['base'] + $distanceCharge, 2)];
}

/** Rough trip time in minutes (city traffic). */
function trip_minutes(float $km): int
{
    return (int)round($km * 2.5 + 5);
}
