<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['query'])) {
    echo json_encode(['success' => false, 'message' => 'No query provided']);
    exit;
}

$query = trim($_POST['query']);

$api_url = "https://router.huggingface.co/api/chain";
$api_key = "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";

$data = [
    "model" => "google/flan-t5-small",
    "inputs" => "Provide details about this car: $query. Include make, model, year, color, body type, fuel type, transmission, and engine size in plain text."
];

$ch = curl_init($api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $api_key",
    "Content-Type: application/json"
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

$response = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch)) {
    echo json_encode(['success' => false, 'message' => 'Curl error: ' . curl_error($ch)]);
    exit;
}

curl_close($ch);

if ($httpcode !== 200) {
    echo json_encode(['success' => false, 'message' => 'AI API error, HTTP code: ' . $httpcode]);
    exit;
}

$result = json_decode($response, true);

$description = "No details available for this car.";
if (isset($result['generated_text'])) {
    $description = $result['generated_text'];
}

echo json_encode([
    'success' => true,
    'description' => $description
]);