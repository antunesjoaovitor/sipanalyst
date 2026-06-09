<?php
// ─── Configuração ─────────────────────────────────────────────────────────
define('N8N_WEBHOOK_URL', 'https://n8n.junditech.com.br/webhook/sip-assistant');

// ─── CORS / Headers ───────────────────────────────────────────────────────
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido']);
    exit;
}

// ─── Lê o body da requisição ──────────────────────────────────────────────
$body = file_get_contents('php://input');
$raw  = json_decode($body, true);

if (!$raw || !isset($raw['message'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Payload inválido']);
    exit;
}

// Envia só message e type — sem histórico para não contaminar respostas
$data = [
    'message' => $raw['message'],
    'type'    => $raw['type'] ?? 'text'
];

// ─── Repassa pro n8n via cURL ─────────────────────────────────────────────
$ch = curl_init(N8N_WEBHOOK_URL);

curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($data),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 60,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Accept: application/json',
    ],
]);

$response  = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// ─── Tratamento de erro de rede ───────────────────────────────────────────
if ($curlError) {
    http_response_code(502);
    echo json_encode(['error' => 'Falha ao conectar com o n8n: ' . $curlError]);
    exit;
}

if ($httpCode < 200 || $httpCode >= 300) {
    http_response_code(502);
    echo json_encode(['error' => "n8n retornou HTTP $httpCode"]);
    exit;
}

// ─── Repassa a resposta do n8n pro frontend ───────────────────────────────
$decoded = json_decode($response, true);
if ($decoded === null) {
    $text = substr($response, 0, 3000);
    echo json_encode(['response' => $text]);
} else {
    echo $response;
}
