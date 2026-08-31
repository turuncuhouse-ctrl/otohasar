<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';

$user = require_api_auth();

$q = format_plate($_GET['q'] ?? '');
if (strlen($q) < 2) {
    json_response(['ok' => true, 'results' => []]);
}

$pdo = db();
$stmt = $pdo->prepare(
    'SELECT v.plate, v.brand, v.model, v.year, v.color, v.chassis_no,
            c.name AS customer_name, c.phone AS customer_phone, c.tc_vkn
     FROM vehicles v
     JOIN customers c ON c.id = v.customer_id
     WHERE v.plate LIKE ?
     ORDER BY v.plate
     LIMIT 10'
);
$stmt->execute(['%' . str_replace(' ', '%', $q) . '%']);
$results = $stmt->fetchAll();

json_response(['ok' => true, 'results' => $results]);
