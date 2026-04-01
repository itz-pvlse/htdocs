<?php
require_once 'config/db.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents("php://input"), true);
if (empty($input['conversation'])) {
    echo json_encode(['error' => 'No conversation provided']);
    exit;
}

$GROQ_API_KEY = "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"; // 🔑 Replace with your actual key

$payload = [
    "model" => "llama-3.3-70b-versatile",
    "messages" => $input['conversation'],
    "temperature" => 0.5,
    "max_tokens" => 400
];

$ch = curl_init("https://api.groq.com/openai/v1/chat/completions");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer {$GROQ_API_KEY}",
        "Content-Type: application/json"
    ],
    CURLOPT_POSTFIELDS => json_encode($payload)
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    echo json_encode(['error' => 'Groq AI request failed']);
    exit;
}

$data = json_decode($response, true);
$reply = $data['choices'][0]['message']['content'] ?? 'No reply from AI';
$reply = nl2br(htmlspecialchars($reply));

echo json_encode(['reply' => $reply]);