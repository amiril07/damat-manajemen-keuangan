<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');


$result = [];

// ── TEST 1: Buat PNG 1x1 via GD dan encode Base64 ────────────api
$img   = imagecreatetruecolor(1, 1);
$white = imagecolorallocate($img, 255, 255, 255);
imagefilledrectangle($img, 0, 0, 1, 1, $white);
ob_start();
imagepng($img, null, 6);
$pngBytes = ob_get_clean();
imagedestroy($img);

$b64 = base64_encode($pngBytes);
$result['imagepng_works']   = ($pngBytes !== false && strlen($pngBytes) > 0);
$result['png_b64_length']   = strlen($b64);

// ── TEST 2: Kirim ke Groq (model + format baru) ───────────────
$payload = json_encode([
    'model'           => 'meta-llama/llama-4-scout-17b-16e-instruct',
    'messages'        => [[
        'role'    => 'user',
        'content' => [
            ['type' => 'image_url', 'image_url' => ['url' => 'data:image/png;base64,' . $b64]],
            ['type' => 'text',      'text'       => 'Reply only with valid JSON: {"ok":true}'],
        ],
    ]],
    'max_tokens'      => 20,
    'temperature'     => 0,
    'response_format' => ['type' => 'json_object'],
]);

$result['payload_size_bytes'] = strlen($payload);

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $groqApiKey,
    ],
    CURLOPT_TIMEOUT        => 25,
    CURLOPT_CONNECTTIMEOUT => 10,
]);
$t0  = microtime(true);
$raw = curl_exec($ch);
$result['groq_ping'] = [
    'http_code'    => curl_getinfo($ch, CURLINFO_HTTP_CODE),
    'curl_error'   => curl_error($ch) ?: null,
    'curl_errno'   => curl_errno($ch),
    'elapsed_sec'  => round(microtime(true) - $t0, 3),
    'response_raw' => $raw ? substr($raw, 0, 600) : null,
];
curl_close($ch);

// ── TEST 3: Info server ───────────────────────────────────────
$result['server_software']   = $_SERVER['SERVER_SOFTWARE'] ?? 'unknown';
$result['https']             = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
$result['script_path']       = __FILE__;
$result['max_execution_time']= ini_get('max_execution_time');

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);