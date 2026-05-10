<?php
$pageTitle = 'Shift Map';
require_once __DIR__ . '/config/database.php';

if (!isLoggedIn()) {
    redirect('/carechain/login.php');
}

$role = getUserRole();
$userId = (int) $_SESSION['user_id'];

$sql = "
    SELECT s.id, s.title, s.shift_date, s.start_time, s.end_time, s.total_pay, s.urgency,
           s.facility_id, fp.facility_name, fp.city, fp.latitude AS lat, fp.longitude AS lng
    FROM shifts s
    JOIN facility_profiles fp ON s.facility_id = fp.user_id
    WHERE s.status = 'open'
      AND s.shift_date >= CURDATE()
      AND fp.latitude IS NOT NULL
      AND fp.longitude IS NOT NULL
      AND (
          s.escrow_status IN ('funded', 'released')
          OR (s.escrow_tx_signature IS NOT NULL AND TRIM(s.escrow_tx_signature) != '')
      )
";
$params = [];
if ($role === 'facility') {
    $sql .= " AND s.facility_id = ? ";
    $params[] = $userId;
}

$sql .= " ORDER BY s.facility_id ASC, s.shift_date ASC, s.start_time ASC ";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$byFacility = [];
foreach ($rows as $r) {
    $fid = (int) $r['facility_id'];
    if (!isset($byFacility[$fid])) {
        $byFacility[$fid] = [
            'facility_name' => $r['facility_name'],
            'city' => $r['city'],
            'lat' => (float) $r['lat'],
            'lng' => (float) $r['lng'],
            'shifts' => [],
        ];
    }
    $byFacility[$fid]['shifts'][] = $r;
}

$markers = [];
foreach ($byFacility as $fid => $g) {
    $shiftItems = [];
    foreach ($g['shifts'] as $s) {
        $dateLabel = date('D j M', strtotime($s['shift_date']));
        $label = $s['title'] . ' — ' . $dateLabel . ' — €' . number_format((float) $s['total_pay'], 2);
        $shiftItems[] = [
            'url' => '/carechain/shifts.php?id=' . (int) $s['id'],
            'label' => $label,
        ];
    }
    $markers[] = [
        'lat' => $g['lat'],
        'lng' => $g['lng'],
        'facilityName' => $g['facility_name'],
        'city' => $g['city'],
        'shifts' => $shiftItems,
    ];
}

$center = [53.35, -6.26];
$zoom = 12;
if (!empty($markers)) {
    $latSum = 0;
    $lngSum = 0;
    foreach ($markers as $m) {
        $latSum += $m['lat'];
        $lngSum += $m['lng'];
    }
    $center = [$latSum / count($markers), $lngSum / count($markers)];
}

$mapPayload = [
    'center' => $center,
    'zoom' => $zoom,
    'markers' => $markers,
];
$mapJson = json_encode(
    $mapPayload,
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
);

$extraHead = '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="" />';
$extraScripts = '<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>'
    . '<script src="/carechain/assets/js/shift-map.js"></script>';

require_once __DIR__ . '/includes/header.php';
?>

<div class="container map-page">
    <div class="page-header">
        <div>
            <h1>Shifts near you</h1>
            <p class="map-page-lead">Nursing homes with open roles — tap a pin for shifts, or tap the map for a shortcut to the full list.</p>
        </div>
        <a href="/carechain/shifts.php" class="btn btn-outline">All shifts</a>
    </div>

    <?php if (empty($markers)): ?>
        <div class="card map-empty">
            <p><strong>No mappable open shifts yet.</strong></p>
            <p class="text-muted">Facilities need latitude and longitude on their profile, and shifts must be open with a future date. Import <code>sql/seed_prototype.sql</code> for a demo, or add coordinates to your facility profile in the database.</p>
            <a href="/carechain/shifts.php" class="btn btn-primary">Go to shifts</a>
        </div>
    <?php else: ?>
        <div id="carechainShiftMap" class="carechain-shift-map" role="application" aria-label="Map of nursing homes with open shifts"></div>
        <p class="map-footnote text-muted">Map data &copy; OpenStreetMap contributors. Pin locations are illustrative for the hackathon prototype.</p>
    <?php endif; ?>
</div>

<script>
window.__CARECHAIN_MAP__ = <?= $mapJson !== false ? $mapJson : '{}' ?>;
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
