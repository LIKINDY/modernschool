<?php
session_start();
include('config.php');

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit();
}

$role = strtolower((string)($_SESSION['role'] ?? ''));
$allowedRoles = ['admin', 'teacher', 'class teacher', 'accountant'];
if (!in_array($role, $allowedRoles, true)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'This assistant is for staff only.']);
    exit();
}

function hasUsableKey($key) {
    $k = trim((string)$key);
    return $k !== '' && $k !== 'PUT_YOUR_AI_API_KEY_HERE';
}

function stripCodeFence($text) {
    $trimmed = trim((string)$text);
    if (strpos($trimmed, '```') === 0) {
        $trimmed = preg_replace('/^```[a-zA-Z]*\s*/', '', $trimmed);
        $trimmed = preg_replace('/\s*```$/', '', $trimmed);
    }
    return trim($trimmed);
}

function postJsonWithCurl($endpoint, array $headers, array $payload, $timeoutSeconds = 25) {
    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => (int)$timeoutSeconds
    ]);

    $rawResponse = curl_exec($ch);
    $curlErrNo = curl_errno($ch);
    $curlError = curl_error($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'http_code' => $httpCode,
        'curl_errno' => $curlErrNo,
        'curl_error' => $curlError,
        'raw_response' => (string)$rawResponse
    ];
}

function callGemini($prompt) {
    if (!hasUsableKey(AI_GEMINI_API_KEY)) {
        return ['ok' => false, 'error' => 'Gemini key not configured.', 'http_code' => 0];
    }

    $models = [];
    foreach ([AI_GEMINI_MODEL, 'gemini-2.0-flash', 'gemini-1.5-flash-latest', 'gemini-1.5-pro-latest'] as $m) {
        $m = trim((string)$m);
        if ($m !== '' && !in_array($m, $models, true)) {
            $models[] = $m;
        }
    }

    $payload = [
        'contents' => [[ 'parts' => [[ 'text' => $prompt ]] ]],
        'generationConfig' => [
            'temperature' => 0.2,
            'maxOutputTokens' => 900,
            'responseMimeType' => 'application/json'
        ]
    ];

    foreach ($models as $model) {
        $endpoint = rtrim(AI_GEMINI_BASE_URL, '/') . '/' . $model . ':generateContent?key=' . urlencode(AI_GEMINI_API_KEY);
        $http = postJsonWithCurl($endpoint, ['Content-Type: application/json'], $payload, 25);

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
        return ['ok' => false, 'error' => 'Gemini HTTP ' . $httpCode, 'http_code' => $httpCode];
    }

    return ['ok' => false, 'error' => 'No Gemini model available.', 'http_code' => 404];
}

function callOpenAi($prompt) {
    if (!hasUsableKey(AI_OPENAI_API_KEY)) {
        return ['ok' => false, 'error' => 'OpenAI key not configured.', 'http_code' => 0];
    }

    $payload = [
        'model' => AI_OPENAI_MODEL,
        'temperature' => 0.2,
        'messages' => [
            ['role' => 'system', 'content' => 'You are a precise staff helpdesk for a school system. Return only valid JSON.'],
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
        25
    );

    if ((int)$http['curl_errno'] !== 0) {
        return ['ok' => false, 'error' => 'Network error: ' . $http['curl_error'], 'http_code' => 0];
    }

    $httpCode = (int)$http['http_code'];
    $raw = (string)$http['raw_response'];
    if ($httpCode < 200 || $httpCode >= 300) {
        return ['ok' => false, 'error' => 'OpenAI HTTP ' . $httpCode, 'http_code' => $httpCode];
    }

    $decoded = json_decode($raw, true);
    $text = $decoded['choices'][0]['message']['content'] ?? '';
    if (!is_array($decoded) || $text === '') {
        return ['ok' => false, 'error' => 'OpenAI response invalid.', 'http_code' => $httpCode];
    }
    return ['ok' => true, 'content' => stripCodeFence($text), 'provider_used' => 'openai'];
}

function callAssistantAi($prompt) {
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

function getAssistantSessionHistory(): array {
    $history = $_SESSION['staff_ai_memory'] ?? [];
    return is_array($history) ? $history : [];
}

function saveAssistantSessionTurn(string $question, string $answer, string $confidence, array $pages = []): void {
    $history = getAssistantSessionHistory();
    $history[] = [
        'q' => trim($question),
        'a' => trim($answer),
        'confidence' => $confidence,
        'pages' => array_values($pages),
        'at' => date('Y-m-d H:i:s')
    ];

    if (count($history) > 6) {
        $history = array_slice($history, -6);
    }

    $_SESSION['staff_ai_memory'] = $history;
}

function buildHistoryPromptBlock(array $history): string {
    if (empty($history)) {
        return 'No previous conversation context.';
    }

    $recent = array_slice($history, -4);
    $lines = [];
    foreach ($recent as $turn) {
        $q = trim((string)($turn['q'] ?? ''));
        $a = trim((string)($turn['a'] ?? ''));
        if ($q !== '' || $a !== '') {
            $lines[] = 'Q: ' . $q;
            $lines[] = 'A: ' . $a;
        }
    }

    return empty($lines) ? 'No previous conversation context.' : implode("\n", $lines);
}

function getManualPlaybook() {
    return [
        [
            'id' => 'student_upload',
            'keywords' => ['upload student', 'upload students', 'import student', 'import students', 'add student', 'register student', 'enroll student', 'kupakia wanafunzi', 'ingiza wanafunzi'],
            'answer' => "To upload students correctly:\n1) Open students.php.\n2) Click Import Students (or open import_students.php).\n3) Download the template from download_template.php and fill required columns.\n4) Save the file as .xlsx/.csv.\n5) Upload and run import.\n6) Confirm imported records in students.php and fix any row errors shown by the importer.",
            'suggested_pages' => ['students.php', 'import_students.php', 'download_template.php']
        ],
        [
            'id' => 'marks_entry',
            'keywords' => ['enter marks', 'save marks', 'record marks', 'matokeo', 'results entry', 'marks entry'],
            'answer' => "To enter results:\n1) Open result.php and choose the correct level.\n2) Go to the correct entry page (marks_entry.php, marks_entry_olevel.php, or marks_entry_alevel.php).\n3) Select class, term, and academic year carefully.\n4) Enter marks and save.\n5) Re-open the sheet/broadsheet to verify totals and grades.",
            'suggested_pages' => ['result.php', 'marks_entry.php', 'marks_entry_olevel.php', 'marks_entry_alevel.php']
        ],
        [
            'id' => 'payments',
            'keywords' => ['payment', 'pay fees', 'fee balance', 'receipt', 'finance', 'malipo', 'ada'],
            'answer' => "For finance and receipts:\n1) Record payment in make_payment.php.\n2) View payment list in payment_list.php.\n3) Search one student payment history in student_payment_lookup.php.\n4) Print receipt from print_receipt.php (from payment list/search results).",
            'suggested_pages' => ['make_payment.php', 'payment_list.php', 'student_payment_lookup.php', 'print_receipt.php']
        ],
        [
            'id' => 'attendance',
            'keywords' => ['attendance', 'present', 'absent', 'mahudhurio'],
            'answer' => "To manage attendance:\n1) Open attendance.php.\n2) Select class and date.\n3) Mark present/absent status.\n4) Save and review attendance history before leaving the page.",
            'suggested_pages' => ['attendance.php']
        ]
    ];
}

function detectManualTopic($question, array $playbook) {
    $q = strtolower(trim((string)$question));
    if ($q === '') {
        return null;
    }

    $best = null;
    $bestScore = 0;

    foreach ($playbook as $topic) {
        $score = 0;
        foreach (($topic['keywords'] ?? []) as $keyword) {
            $k = strtolower(trim((string)$keyword));
            if ($k !== '' && strpos($q, $k) !== false) {
                $score++;
            }
        }

        if ($score > $bestScore) {
            $bestScore = $score;
            $best = $topic;
        }
    }

    if ($bestScore > 0 && is_array($best)) {
        $best['match_score'] = $bestScore;
        return $best;
    }

    return null;
}

function buildSuggestedActions(array $pages): array {
    $actions = [];
    foreach ($pages as $p) {
        $page = trim((string)$p);
        if ($page === '') {
            continue;
        }
        $label = ucwords(str_replace(['.php', '_', '-'], ['', ' ', ' '], $page));
        $actions[] = [
            'label' => $label,
            'page' => $page
        ];
    }

    return $actions;
}

$raw = file_get_contents('php://input');
$data = json_decode((string)$raw, true);
$question = trim((string)($data['question'] ?? ''));
$pageContext = trim((string)($data['page_context'] ?? ''));

if ($question === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Question is required.']);
    exit();
}

$playbook = getManualPlaybook();
$matchedTopic = detectManualTopic($question, $playbook);
if (is_array($matchedTopic)) {
    $pages = array_values((array)($matchedTopic['suggested_pages'] ?? []));
    $answer = (string)($matchedTopic['answer'] ?? '');
    $confidence = ((int)($matchedTopic['match_score'] ?? 0) >= 2) ? 'High' : 'Medium';
    saveAssistantSessionTurn($question, $answer, $confidence, $pages);

    echo json_encode([
        'ok' => true,
        'answer' => $answer,
        'suggested_pages' => $pages,
        'suggested_actions' => buildSuggestedActions($pages),
        'confidence' => $confidence,
        'provider_used' => 'manual_playbook',
        'warning' => null
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

$staffName = (string)($_SESSION['fullname'] ?? 'Staff');
$roleName = (string)($_SESSION['role'] ?? 'staff');
$historyBlock = buildHistoryPromptBlock(getAssistantSessionHistory());

$prompt = "You are SYSTEM in-app assistant for staff guidance.
Current user role: {$roleName}
Staff name: {$staffName}
Current page context: {$pageContext}

Recent conversation context:
{$historyBlock}

Staff question:
{$question}

System modules commonly used:
- teacher_dashboard.php (teacher portal)
- result.php (results hub)
- marks_entry.php, marks_entry_olevel.php, marks_entry_alevel.php (marks entry)
- students.php / students_list.php (student management)
- fee_settings.php, make_payment.php, payment_list.php (finance)
- ai_lesson_quiz_generator.php, ai_predictive_analytics.php, ai_auto_comments.php (AI features)

Rules:
- Provide practical step-by-step guidance.
- Keep answer concise but complete.
- Mention relevant page names exactly when needed.
- If uncertain, say what to verify.
- Return only valid JSON.

JSON schema:
{
  \"answer\": \"string\",
  \"suggested_pages\": [\"string\", \"string\"]
}";

$ai = callAssistantAi($prompt);

if (!$ai['ok']) {
    $fallback = "Hatua za haraka: 1) Nenda result.php au teacher_dashboard.php kulingana na kazi yako. 2) Chagua module husika (marks, students, payments). 3) Hakikisha umechagua year/term sahihi kabla ya kuhifadhi. Ukikwama, taja page na error ili nipate kuelekeza hatua kwa hatua.";
    $fallbackPages = ['result.php', 'teacher_dashboard.php'];
    saveAssistantSessionTurn($question, $fallback, 'Low', $fallbackPages);

    echo json_encode([
        'ok' => true,
        'answer' => $fallback,
        'suggested_pages' => $fallbackPages,
        'suggested_actions' => buildSuggestedActions($fallbackPages),
        'confidence' => 'Low',
        'warning' => 'AI temporarily unavailable; fallback answer used.'
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

$decoded = json_decode((string)$ai['content'], true);
$answer = is_array($decoded) ? trim((string)($decoded['answer'] ?? '')) : '';
$pages = is_array($decoded) ? ($decoded['suggested_pages'] ?? []) : [];
if ($answer === '') {
    $answer = 'Tafadhali eleza unachotaka kufanya kwa detail kidogo (mfano page uliopo na hatua uliyofika), nitakupa mwongozo sahihi wa hatua kwa hatua.';
}

$confidence = ($ai['provider_used'] ?? '') === 'openai' || ($ai['provider_used'] ?? '') === 'gemini' ? 'Medium' : 'Low';
$safePages = is_array($pages) ? array_values($pages) : [];
saveAssistantSessionTurn($question, $answer, $confidence, $safePages);

echo json_encode([
    'ok' => true,
    'answer' => $answer,
    'suggested_pages' => $safePages,
    'suggested_actions' => buildSuggestedActions($safePages),
    'confidence' => $confidence,
    'provider_used' => $ai['provider_used'] ?? null,
    'warning' => $ai['warning'] ?? null
], JSON_UNESCAPED_UNICODE);
