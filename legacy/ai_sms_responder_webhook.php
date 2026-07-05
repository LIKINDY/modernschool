<?php
session_start();
include('db_config.php');
include('config.php');

/**
 * Feature 4: Smart WhatsApp/SMS AI Responder
 * - POST JSON acts as webhook endpoint.
 * - Browser GET/POST renders a simulator UI for testing.
 */

function hasUsableKey($key) {
    $k = trim((string) $key);
    return $k !== '' && $k !== 'PUT_YOUR_AI_API_KEY_HERE';
}

function stripCodeFence($text) {
    $trimmed = trim((string) $text);
    if (strpos($trimmed, '```') === 0) {
        $trimmed = preg_replace('/^```[a-zA-Z]*\s*/', '', $trimmed);
        $trimmed = preg_replace('/\s*```$/', '', $trimmed);
    }
    return trim($trimmed);
}

function postJsonWithCurl($endpoint, array $headers, array $payload, $timeoutSeconds = 30) {
    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => (int) $timeoutSeconds
    ]);

    $rawResponse = curl_exec($ch);
    $curlErrNo = curl_errno($ch);
    $curlError = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'http_code' => $httpCode,
        'curl_errno' => $curlErrNo,
        'curl_error' => $curlError,
        'raw_response' => (string) $rawResponse
    ];
}

function callGemini($prompt) {
    if (!hasUsableKey(AI_GEMINI_API_KEY)) {
        return ['ok' => false, 'error' => 'Gemini key not configured.', 'http_code' => 0];
    }

    $models = [];
    foreach ([AI_GEMINI_MODEL, 'gemini-2.0-flash', 'gemini-1.5-flash-latest', 'gemini-1.5-pro-latest'] as $m) {
        $m = trim((string) $m);
        if ($m !== '' && !in_array($m, $models, true)) {
            $models[] = $m;
        }
    }

    $payload = [
        'contents' => [[ 'parts' => [[ 'text' => $prompt ]] ]],
        'generationConfig' => [
            'temperature' => 0.25,
            'maxOutputTokens' => 700,
            'responseMimeType' => 'application/json'
        ]
    ];

    foreach ($models as $model) {
        $endpoint = rtrim(AI_GEMINI_BASE_URL, '/') . '/' . $model . ':generateContent?key=' . urlencode(AI_GEMINI_API_KEY);
        $http = postJsonWithCurl($endpoint, ['Content-Type: application/json'], $payload, 30);
        if ((int)$http['curl_errno'] !== 0) {
            return ['ok' => false, 'error' => 'Network error: ' . $http['curl_error'], 'http_code' => 0];
        }

        $httpCode = (int)$http['http_code'];
        $raw = (string)$http['raw_response'];
        if ($httpCode >= 200 && $httpCode < 300) {
            $decoded = json_decode($raw, true);
            $text = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '';
            if (!is_array($decoded) || $text === '') {
                return ['ok' => false, 'error' => 'Gemini response invalid.', 'http_code' => $httpCode];
            }
            return ['ok' => true, 'content' => stripCodeFence($text), 'provider_used' => 'gemini'];
        }

        if ($httpCode === 404) {
            continue;
        }
        if ($httpCode === 429) {
            return ['ok' => false, 'error' => 'Gemini quota exceeded.', 'http_code' => 429];
        }
        return ['ok' => false, 'error' => 'Gemini HTTP ' . $httpCode . '. Raw: ' . substr($raw, 0, 220), 'http_code' => $httpCode];
    }

    return ['ok' => false, 'error' => 'No Gemini model available.', 'http_code' => 404];
}

function callOpenAi($prompt) {
    if (!hasUsableKey(AI_OPENAI_API_KEY)) {
        return ['ok' => false, 'error' => 'OpenAI key not configured.', 'http_code' => 0];
    }

    $payload = [
        'model' => AI_OPENAI_MODEL,
        'temperature' => 0.25,
        'messages' => [
            ['role' => 'system', 'content' => 'You are a polite school customer support assistant. Return only valid JSON.'],
            ['role' => 'user', 'content' => $prompt]
        ],
        'response_format' => ['type' => 'json_object']
    ];

    $http = postJsonWithCurl(
        AI_OPENAI_BASE_URL,
        [
            'Content-Type: application/json',
            'Authorization: Bearer ' . AI_OPENAI_API_KEY
        ],
        $payload,
        30
    );

    if ((int)$http['curl_errno'] !== 0) {
        return ['ok' => false, 'error' => 'Network error: ' . $http['curl_error'], 'http_code' => 0];
    }
    $httpCode = (int)$http['http_code'];
    $raw = (string)$http['raw_response'];
    if ($httpCode < 200 || $httpCode >= 300) {
        return ['ok' => false, 'error' => 'OpenAI HTTP ' . $httpCode . '. Raw: ' . substr($raw, 0, 220), 'http_code' => $httpCode];
    }

    $decoded = json_decode($raw, true);
    $text = $decoded['choices'][0]['message']['content'] ?? '';
    if (!is_array($decoded) || $text === '') {
        return ['ok' => false, 'error' => 'OpenAI response invalid.', 'http_code' => $httpCode];
    }
    return ['ok' => true, 'content' => stripCodeFence($text), 'provider_used' => 'openai'];
}

function callAiResponder($prompt) {
    $provider = strtolower((string)AI_PROVIDER);
    if ($provider === 'openai') {
        return callOpenAi($prompt);
    }

    $gem = callGemini($prompt);
    if ($gem['ok']) {
        return $gem;
    }
    if ((int)($gem['http_code'] ?? 0) === 429 && hasUsableKey(AI_OPENAI_API_KEY)) {
        $open = callOpenAi($prompt);
        if ($open['ok']) {
            $open['warning'] = 'Gemini quota exceeded; OpenAI fallback used.';
        }
        return $open;
    }
    return $gem;
}

function normalizePhone($phone) {
    $digits = preg_replace('/\D+/', '', (string)$phone);
    if ($digits === '') {
        return '';
    }
    if (strpos($digits, '255') === 0) {
        return $digits;
    }
    if (strpos($digits, '0') === 0) {
        return '255' . substr($digits, 1);
    }
    return $digits;
}

function findStudentByParentPhone($conn, $phone) {
    $norm = normalizePhone($phone);
    if ($norm === '') {
        return null;
    }
    $tail9 = substr($norm, -9);

    $sanitizeExpr = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(%s, '+', ''), ' ', ''), '-', ''), '(', ''), ')', '')";
    $fields = ['phone', 'parent_phone', 'parent2_phone', 'parent3_phone'];
    $whereParts = [];
    foreach ($fields as $f) {
        $whereParts[] = sprintf($sanitizeExpr, $f) . " LIKE CONCAT('%', ?, '%')";
    }

    $sql = "SELECT id, student_id, fullname, class_name, stream, academic_year, phone, parent_phone, parent2_phone, parent3_phone
            FROM students
            WHERE status = 'active' AND (" . implode(' OR ', $whereParts) . ")
            ORDER BY id DESC
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ssss', $tail9, $tail9, $tail9, $tail9);
    $stmt->execute();
    $res = $stmt->get_result();
    if (!$res || $res->num_rows === 0) {
        return null;
    }
    return $res->fetch_assoc();
}

function getBalanceSummary($conn, $studentId, $className, $academicYear) {
    $expectedByCategory = [];
    $paidByCategory = [];

    $fs = $conn->prepare("SELECT category_name, SUM(amount) AS expected
                         FROM fee_settings
                         WHERE class_name = ? AND academic_year = ?
                         GROUP BY category_name");
    $fs->bind_param('ss', $className, $academicYear);
    $fs->execute();
    $fsRes = $fs->get_result();
    while ($row = $fsRes->fetch_assoc()) {
        $expectedByCategory[$row['category_name']] = (float)($row['expected'] ?? 0);
    }

    $pay = $conn->prepare("SELECT category, SUM(amount_paid) AS paid
                          FROM payments
                          WHERE student_id = ? AND (academic_year = ? OR academic_year = '' OR academic_year IS NULL)
                          GROUP BY category");
    $pay->bind_param('is', $studentId, $academicYear);
    $pay->execute();
    $payRes = $pay->get_result();
    while ($row = $payRes->fetch_assoc()) {
        $paidByCategory[$row['category']] = (float)($row['paid'] ?? 0);
    }

    $allCats = array_unique(array_merge(array_keys($expectedByCategory), array_keys($paidByCategory)));
    $summary = [];
    $totalExpected = 0;
    $totalPaid = 0;
    $totalBalance = 0;

    foreach ($allCats as $cat) {
        $expected = (float)($expectedByCategory[$cat] ?? 0);
        $paid = (float)($paidByCategory[$cat] ?? 0);
        $balance = max($expected - $paid, 0);
        $summary[] = [
            'category' => $cat,
            'expected' => $expected,
            'paid' => $paid,
            'balance' => $balance
        ];
        $totalExpected += $expected;
        $totalPaid += $paid;
        $totalBalance += $balance;
    }

    return [
        'categories' => $summary,
        'total_expected' => $totalExpected,
        'total_paid' => $totalPaid,
        'total_balance' => $totalBalance
    ];
}

function buildResponderPrompt($incomingText, $student, $balanceSummary) {
    $studentData = json_encode([
        'student_name' => $student['fullname'] ?? '',
        'student_id' => $student['student_id'] ?? '',
        'class' => $student['class_name'] ?? '',
        'stream' => $student['stream'] ?? '',
        'academic_year' => $student['academic_year'] ?? ''
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    $balanceData = json_encode($balanceSummary, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    return "You are an AI assistant replying to a school parent via SMS/WhatsApp.
Incoming parent message: {$incomingText}

Student data:
{$studentData}

Balance summary:
{$balanceData}

Requirements:
- Reply politely and naturally.
- Keep message concise (max 3 short paragraphs).
- Mention total balance in TZS.
- If there is no balance, say fees are up to date.
- Do not include markdown.
- Return only valid JSON.

JSON schema:
{
  \"intent\": \"balance|general\",
  \"reply\": \"string\"
}";
}

function processIncomingMessage($conn, $fromPhone, $messageText) {
    $messageText = trim((string)$messageText);
    $fromPhone = trim((string)$fromPhone);

    if ($fromPhone === '' || $messageText === '') {
        return [
            'ok' => false,
            'reply' => 'Samahani, taarifa hazijakamilika. Tafadhali tuma namba ya simu na ujumbe sahihi.',
            'error' => 'Missing phone or message.'
        ];
    }

    $student = findStudentByParentPhone($conn, $fromPhone);
    if (!$student) {
        return [
            'ok' => false,
            'reply' => 'Samahani, hatukupata mwanafunzi anayehusishwa na namba hii. Tafadhali wasiliana na ofisi ya shule kwa uhakiki wa namba.',
            'error' => 'Student not found for phone.'
        ];
    }

    $balance = getBalanceSummary($conn, (int)$student['id'], (string)$student['class_name'], (string)$student['academic_year']);
    $prompt = buildResponderPrompt($messageText, $student, $balance);
    $ai = callAiResponder($prompt);

    if (!$ai['ok']) {
        $fallback = 'Habari mzazi wa ' . $student['fullname'] . '. '
            . ($balance['total_balance'] > 0
                ? ('Salio la ada lililobaki ni TZS ' . number_format($balance['total_balance'], 0) . '. Tafadhali kamilisha malipo kwa wakati.')
                : 'Hakuna deni kwa sasa, ada ziko sawa. Asante kwa ushirikiano wako.');

        return [
            'ok' => true,
            'reply' => $fallback,
            'warning' => 'AI unavailable, fallback message used.',
            'student' => $student,
            'balance' => $balance
        ];
    }

    $aiJson = json_decode((string)$ai['content'], true);
    $reply = is_array($aiJson) ? trim((string)($aiJson['reply'] ?? '')) : '';
    if ($reply === '') {
        $reply = 'Habari mzazi wa ' . $student['fullname'] . '. Salio la sasa ni TZS ' . number_format($balance['total_balance'], 0) . '.';
    }

    return [
        'ok' => true,
        'reply' => $reply,
        'intent' => (is_array($aiJson) ? ($aiJson['intent'] ?? 'general') : 'general'),
        'student' => $student,
        'balance' => $balance,
        'provider_used' => $ai['provider_used'] ?? null,
        'warning' => $ai['warning'] ?? null
    ];
}

// Webhook mode: POST JSON body (for external SMS/WhatsApp provider).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $payload = json_decode((string)$raw, true);

    $from = $payload['from'] ?? '';
    $message = $payload['message'] ?? '';
    $result = processIncomingMessage($conn, $from, $message);

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => $result['ok'],
        'reply' => $result['reply'],
        'intent' => $result['intent'] ?? null,
        'provider_used' => $result['provider_used'] ?? null,
        'warning' => $result['warning'] ?? null,
        'meta' => [
            'student_id' => $result['student']['student_id'] ?? null,
            'total_balance' => $result['balance']['total_balance'] ?? null
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

// Simulator mode (browser form).
$simPhone = trim($_POST['sim_phone'] ?? '');
$simMessage = trim($_POST['sim_message'] ?? 'What is my child\'s balance?');
$simResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simulate_submit'])) {
    $simResult = processIncomingMessage($conn, $simPhone, $simMessage);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI SMS/WhatsApp Responder</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #eef2f7; }
        .shell {
            max-width: 980px;
            margin: 20px auto;
            background: #fff;
            border: 1px solid #dbe4f0;
            border-radius: 18px;
            box-shadow: 0 12px 35px rgba(15, 23, 42, 0.08);
            overflow: hidden;
        }
        .head {
            background: linear-gradient(135deg, #334155 0%, #1e293b 70%, #0f172a 100%);
            color: #fff;
            padding: 14px 18px;
        }
        .bodyx {
            padding: 18px;
            min-height: 360px;
            background: #f8fafc;
        }
        .bubble { max-width: 82%; border-radius: 14px; padding: 12px 14px; margin-bottom: 12px; border: 1px solid transparent; }
        .bubble-user { margin-left: auto; background: #dbeafe; border-color: #93c5fd; }
        .bubble-ai { margin-right: auto; background: #ffffff; border-color: #dbe4f0; }
        .composer { border-top: 1px solid #e2e8f0; background: #fff; padding: 12px; }
        .kbd { font-size: 11px; padding: 2px 7px; border-radius: 8px; border: 1px solid #cbd5e1; background: #f8fafc; }
    </style>
</head>
<body>
<div class="shell">
    <div class="head d-flex justify-content-between align-items-center">
        <div>
            <h5 class="m-0 fw-bold"><i class="fas fa-message me-2"></i>Smart WhatsApp/SMS AI Responder (Feature 4)</h5>
            <small>Webhook + simulator for parent balance enquiries</small>
        </div>
        <a href="admin_dashboard.php" class="btn btn-sm btn-light rounded-pill px-3">Back</a>
    </div>

    <div class="bodyx">
        <div class="alert alert-info py-2">
            <strong>Webhook endpoint:</strong> <span class="kbd">POST JSON to this same URL</span> with keys <span class="kbd">from</span> and <span class="kbd">message</span>.
        </div>

        <?php if ($simPhone !== ''): ?>
            <div class="bubble bubble-user">
                <div class="small text-muted mb-1">Parent (<?= htmlspecialchars($simPhone) ?>)</div>
                <?= htmlspecialchars($simMessage) ?>
            </div>
        <?php endif; ?>

        <?php if (is_array($simResult)): ?>
            <div class="bubble bubble-ai">
                <div class="small text-muted mb-1">AI Reply<?= !empty($simResult['provider_used']) ? ' via ' . htmlspecialchars($simResult['provider_used']) : '' ?></div>
                <?= nl2br(htmlspecialchars((string)$simResult['reply'])) ?>
                <?php if (!empty($simResult['warning'])): ?>
                    <div class="small text-warning mt-2">Note: <?= htmlspecialchars((string)$simResult['warning']) ?></div>
                <?php endif; ?>
                <?php if (!empty($simResult['student'])): ?>
                    <div class="small text-muted mt-2">
                        Student: <?= htmlspecialchars($simResult['student']['fullname']) ?> (<?= htmlspecialchars($simResult['student']['student_id']) ?>)
                        | Balance: TZS <?= number_format((float)($simResult['balance']['total_balance'] ?? 0), 0) ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="composer">
        <form method="POST">
            <div class="row g-2 align-items-center">
                <div class="col-md-4">
                    <input type="text" class="form-control" name="sim_phone" value="<?= htmlspecialchars($simPhone) ?>" placeholder="Parent phone e.g. 0712345678" required>
                </div>
                <div class="col-md-6">
                    <input type="text" class="form-control" name="sim_message" value="<?= htmlspecialchars($simMessage) ?>" placeholder="Incoming message from parent" required>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-dark w-100 rounded-pill" name="simulate_submit" value="1">
                        <i class="fas fa-paper-plane me-1"></i>Simulate
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
</body>
</html>
