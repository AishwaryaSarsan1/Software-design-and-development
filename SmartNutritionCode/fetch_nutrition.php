<?php
// C:\xampp\htdocs\smartnutrition\fetch_nutrition_api_ninjas.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
session_start();
if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

/* Put your API Ninjas key here (DO NOT SHARE) */
const API_NINJAS_KEY = ''; // <-- paste your API Ninjas key here

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid method']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
$q = trim((string)($payload['query'] ?? ''));

if ($q === '') {
    echo json_encode(['success' => false, 'error' => 'Empty query']);
    exit;
}

if (API_NINJAS_KEY === '') {
    echo json_encode(['success' => false, 'error' => 'API key not configured on server']);
    exit;
}

/* API Ninjas nutrition endpoint (GET with query param) */
$url = 'https://api.api-ninjas.com/v1/nutrition?query=' . urlencode($q);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPGET => true,
    CURLOPT_HTTPHEADER => [
        'X-Api-Key: ' . API_NINJAS_KEY,
        'Accept: application/json'
    ],
    CURLOPT_TIMEOUT => 12,
    CURLOPT_SSL_VERIFYPEER => true,
]);

$res = curl_exec($ch);
$err = curl_error($ch);
$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($err) {
    echo json_encode(['success' => false, 'error' => "Network error: $err"]);
    exit;
}

if ($code < 200 || $code >= 300) {
    // try to parse helpful message
    $data = json_decode((string)$res, true);
    $msg = "API Ninjas HTTP $code";
    if (is_array($data) && isset($data['error'])) $msg .= ': ' . $data['error'];
    echo json_encode(['success' => false, 'error' => $msg, 'raw' => $data ?? $res]);
    exit;
}

$data = json_decode((string)$res, true);
if (!is_array($data) || count($data) === 0) {
    echo json_encode(['success' => false, 'error' => 'No nutrition results returned', 'raw' => $data]);
    exit;
}

/* API Ninjas returns an array of matched items. We'll return the first item. */
$item = $data[0];

/* Normalize fields — API Ninjas uses keys like calories, protein_g, fat_total_g, carbohydrates_total_g */
$resultFood = [
    'name' => $item['name'] ?? '',
    'calories' => isset($item['calories']) ? (float)$item['calories'] : 0.0,
    'protein_g' => isset($item['protein_g']) ? (float)$item['protein_g'] : 0.0,
    'fat_total_g' => isset($item['fat_total_g']) ? (float)$item['fat_total_g'] : 0.0,
    'carbohydrates_total_g' => isset($item['carbohydrates_total_g']) ? (float)$item['carbohydrates_total_g'] : 0.0,
    'serving_size_g' => isset($item['serving_size_g']) ? (float)$item['serving_size_g'] : null,
    'raw' => $item
];

echo json_encode(['success' => true, 'food' => $resultFood]);
exit;
