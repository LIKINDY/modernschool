<?php
session_start();
include('db_config.php');
include('config.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

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

function postJsonWithCurl($endpoint, array $headers, array $payload, $timeoutSeconds = 40) {
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

function callGemini($prompt, $timeoutSeconds = 40) {
    $geminiKey = (string) AI_GEMINI_API_KEY;
    if (!hasUsableKey($geminiKey)) {
        return ['ok' => false, 'error' => 'Gemini API key is not configured.', 'http_code' => 0];
    }

    $models = [];
    foreach ([AI_GEMINI_MODEL, 'gemini-2.0-flash', 'gemini-1.5-flash-latest', 'gemini-1.5-pro-latest'] as $m) {
        $m = trim((string) $m);
        if ($m !== '' && !in_array($m, $models, true)) {
            $models[] = $m;
        }
    }

    $payload = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.3,
            'maxOutputTokens' => 2048,
            'responseMimeType' => 'application/json'
        ]
    ];

    $headers = ['Content-Type: application/json'];
    $attempted = [];

    foreach ($models as $model) {
        $attempted[] = $model;
        $endpoint = rtrim(AI_GEMINI_BASE_URL, '/') . '/' . $model . ':generateContent?key=' . urlencode($geminiKey);
        $http = postJsonWithCurl($endpoint, $headers, $payload, $timeoutSeconds);

        if ((int) $http['curl_errno'] !== 0) {
            return ['ok' => false, 'error' => 'Network/timeout error: ' . $http['curl_error'], 'http_code' => 0];
        }

        $httpCode = (int) $http['http_code'];
        $rawResponse = (string) $http['raw_response'];

        if ($httpCode >= 200 && $httpCode < 300) {
            $decoded = json_decode($rawResponse, true);
            $text = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '';
            if (!is_array($decoded) || $text === '') {
                return ['ok' => false, 'error' => 'Gemini response format is invalid.', 'http_code' => $httpCode];
            }
            return ['ok' => true, 'content' => stripCodeFence($text), 'provider_used' => 'gemini', 'model_used' => $model];
        }

        if ($httpCode === 404) {
            continue;
        }

        if ($httpCode === 429) {
            return ['ok' => false, 'error' => 'Gemini quota exceeded (HTTP 429).', 'http_code' => 429];
        }

        return ['ok' => false, 'error' => 'Gemini API HTTP error ' . $httpCode . '. Raw: ' . substr($rawResponse, 0, 400), 'http_code' => $httpCode];
    }

    return ['ok' => false, 'error' => 'No Gemini model available. Attempted: ' . implode(', ', $attempted), 'http_code' => 404];
}

function callOpenAi($prompt, $timeoutSeconds = 40) {
    $openAiKey = (string) AI_OPENAI_API_KEY;
    if (!hasUsableKey($openAiKey)) {
        return ['ok' => false, 'error' => 'OpenAI API key is not configured.', 'http_code' => 0];
    }

    $endpoint = AI_OPENAI_BASE_URL;
    $payload = [
        'model' => AI_OPENAI_MODEL,
        'temperature' => 0.3,
        'messages' => [
            ['role' => 'system', 'content' => 'You are a precise education analyst. Return only valid JSON.'],
            ['role' => 'user', 'content' => $prompt]
        ],
        'response_format' => ['type' => 'json_object']
    ];
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $openAiKey
    ];

    $http = postJsonWithCurl($endpoint, $headers, $payload, $timeoutSeconds);
    if ((int) $http['curl_errno'] !== 0) {
        return ['ok' => false, 'error' => 'Network/timeout error: ' . $http['curl_error'], 'http_code' => 0];
    }

    $httpCode = (int) $http['http_code'];
    $rawResponse = (string) $http['raw_response'];
    if ($httpCode < 200 || $httpCode >= 300) {
        return ['ok' => false, 'error' => 'OpenAI API HTTP error ' . $httpCode . '. Raw: ' . substr($rawResponse, 0, 400), 'http_code' => $httpCode];
    }

    $decoded = json_decode($rawResponse, true);
    $text = $decoded['choices'][0]['message']['content'] ?? '';
    if (!is_array($decoded) || $text === '') {
        return ['ok' => false, 'error' => 'OpenAI response format is invalid.', 'http_code' => $httpCode];
    }

    return ['ok' => true, 'content' => stripCodeFence($text), 'provider_used' => 'openai', 'model_used' => AI_OPENAI_MODEL];
}

function callAiPredictive($prompt) {
    $provider = strtolower((string) AI_PROVIDER);
    if ($provider === 'openai') {
        return callOpenAi($prompt, 40);
    }

    $gem = callGemini($prompt, 40);
    if ($gem['ok']) {
        return $gem;
    }

    if ((int) ($gem['http_code'] ?? 0) === 429 && hasUsableKey(AI_OPENAI_API_KEY)) {
        $open = callOpenAi($prompt, 40);
        if ($open['ok']) {
            $open['warning'] = 'Gemini quota exceeded; OpenAI fallback used.';
        }
        return $open;
    }

    return $gem;
}

function tableExists($conn, $tableName) {
    $sql = "SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $tableName);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    return ((int) ($res['c'] ?? 0)) > 0;
}

function fetchRecentThreeExams($conn, $studentDbId) {
    $records = [];

    if (tableExists($conn, 'marks')) {
        $sql = "SELECT id, year AS academic_year, term AS exam_type, total_100 AS score_100, grade
                FROM marks
                WHERE student_id = ? AND total_100 IS NOT NULL
                ORDER BY id DESC
                LIMIT 3";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $studentDbId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $records[] = [
                'source' => 'marks',
                'academic_year' => $row['academic_year'],
                'exam_type' => $row['exam_type'],
                'score_100' => (float) $row['score_100'],
                'grade' => $row['grade'] ?? ''
            ];
        }
    }

    $needed = 3 - count($records);
    if ($needed > 0 && tableExists($conn, 'primary_marks')) {
        $sql = "SELECT id, academic_year, exam_type, total_mark AS score_100, grade
                FROM primary_marks
                WHERE student_id = ? AND total_mark IS NOT NULL
                ORDER BY id DESC
                LIMIT ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ii', $studentDbId, $needed);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $records[] = [
                'source' => 'primary_marks',
                'academic_year' => $row['academic_year'] ?? '',
                'exam_type' => $row['exam_type'] ?? '',
                'score_100' => (float) $row['score_100'],
                'grade' => $row['grade'] ?? ''
            ];
        }
    }

    $needed = 3 - count($records);
    if ($needed > 0 && tableExists($conn, 'olevel_marks')) {
        $sql = "SELECT id, academic_year, exam_type, total_score AS score_100, grade
                FROM olevel_marks
                WHERE student_id = ? AND total_score IS NOT NULL
                ORDER BY id DESC
                LIMIT ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ii', $studentDbId, $needed);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $records[] = [
                'source' => 'olevel_marks',
                'academic_year' => $row['academic_year'] ?? '',
                'exam_type' => $row['exam_type'] ?? '',
                'score_100' => (float) $row['score_100'],
                'grade' => $row['grade'] ?? ''
            ];
        }
    }

    return array_slice($records, 0, 3);
}

function buildPredictivePrompt($student, array $examRows) {
    $marksJson = json_encode($examRows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    $studentJson = json_encode([
        'student_name' => $student['fullname'] ?? '',
        'student_id' => $student['student_id'] ?? '',
        'class_name' => $student['class_name'] ?? '',
        'stream' => $student['stream'] ?? ''
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    return "You are an educational data assistant for a school MIS.
Analyze the student's latest 3 exam records and provide short predictive analytics.

Student profile:
{$studentJson}

Exam records (latest 3):
{$marksJson}

Rules:
- Be practical and concise for teachers.
- Predict likely next exam score range out of 100.
- Identify trend and risk level.
- Provide an IEP recommendation with concrete actions.
- Keep tone professional and supportive.
- Return ONLY valid JSON.

Return JSON schema:
{
  \"student_summary\": \"string\",
  \"prediction\": {
    \"likely_next_score_range\": \"e.g. 62-70\",
    \"trend\": \"Improving|Stable|Declining\",
    \"risk_level\": \"Low|Medium|High\",
    \"confidence\": \"Low|Medium|High\"
  },
  \"iep_recommendation\": {
    \"learning_goals\": [\"string\", \"string\"],
    \"teacher_actions\": [\"string\", \"string\", \"string\"],
    \"parent_actions\": [\"string\", \"string\"],
    \"follow_up_weeks\": 4
  },
  \"short_feedback\": \"string\"
}";
}

$studentQuery = trim($_POST['student_query'] ?? '');
$errorMsg = '';
$infoMsg = '';
$student = null;
$examRows = [];
$analysis = null;
$rawAi = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($studentQuery === '') {
        $errorMsg = 'Please enter student name or student ID.';
    } else {
        $sql = "SELECT id, student_id, fullname, class_name, stream
                FROM students
                WHERE student_id = ? OR fullname LIKE ?
                ORDER BY CASE WHEN student_id = ? THEN 0 ELSE 1 END, fullname ASC
                LIMIT 1";
        $stmt = $conn->prepare($sql);
        $like = '%' . $studentQuery . '%';
        $stmt->bind_param('sss', $studentQuery, $like, $studentQuery);
        $stmt->execute();
        $res = $stmt->get_result();

        if (!$res || $res->num_rows === 0) {
            $errorMsg = 'Student not found.';
        } else {
            $student = $res->fetch_assoc();
            $examRows = fetchRecentThreeExams($conn, (int) $student['id']);

            if (count($examRows) < 3) {
                $errorMsg = 'Not enough exam records. At least 3 records are required for prediction.';
            } else {
                $prompt = buildPredictivePrompt($student, $examRows);
                $ai = callAiPredictive($prompt);

                if (!$ai['ok']) {
                    $errorMsg = $ai['error'];
                } else {
                    if (!empty($ai['warning'])) {
                        $infoMsg = (string) $ai['warning'];
                    }
                    $rawAi = (string) $ai['content'];
                    $analysis = json_decode($rawAi, true);
                    if (!is_array($analysis) || !isset($analysis['prediction']) || !isset($analysis['iep_recommendation'])) {
                        $errorMsg = 'AI returned invalid JSON structure. See raw output below.';
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Predictive Analytics</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #eef2f7; }
        .chat-shell {
            max-width: 980px;
            margin: 20px auto;
            background: #fff;
            border: 1px solid #dbe4f0;
            border-radius: 18px;
            box-shadow: 0 12px 35px rgba(15, 23, 42, 0.08);
            overflow: hidden;
        }
        .chat-header {
            background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 60%, #3b82f6 100%);
            color: #fff;
            padding: 14px 18px;
        }
        .chat-body {
            padding: 18px;
            min-height: 380px;
            background: #f8fafc;
        }
        .bubble {
            max-width: 78%;
            border-radius: 14px;
            padding: 12px 14px;
            margin-bottom: 12px;
            line-height: 1.5;
            border: 1px solid transparent;
        }
        .bubble-user {
            margin-left: auto;
            background: #dbeafe;
            border-color: #93c5fd;
        }
        .bubble-ai {
            margin-right: auto;
            background: #ffffff;
            border-color: #dbe4f0;
        }
        .composer {
            border-top: 1px solid #e2e8f0;
            background: #fff;
            padding: 12px;
        }
        .composer .input-group {
            border: 1px solid #cbd5e1;
            border-radius: 999px;
            overflow: hidden;
            background: #fff;
        }
        .composer input {
            border: 0;
            box-shadow: none !important;
            padding-left: 14px;
        }
        .badge-kpi {
            border: 1px solid #bfdbfe;
            background: #eff6ff;
            color: #1d4ed8;
        }
        pre { white-space: pre-wrap; word-break: break-word; }
    </style>
</head>
<body>

<div class="chat-shell">
    <div class="chat-header d-flex justify-content-between align-items-center">
        <div>
            <h5 class="m-0 fw-bold"><i class="fas fa-chart-line me-2"></i>AI Predictive Analytics (Feature 2)</h5>
            <small>Student performance forecast + IEP recommendation</small>
        </div>
        <a href="teacher_dashboard.php" class="btn btn-sm btn-light rounded-pill px-3">Back</a>
    </div>

    <div class="chat-body">
        <?php if ($errorMsg !== ''): ?>
            <div class="alert alert-danger mb-3"><strong>Error:</strong> <?= htmlspecialchars($errorMsg) ?></div>
        <?php endif; ?>

        <?php if ($infoMsg !== ''): ?>
            <div class="alert alert-warning mb-3"><?= htmlspecialchars($infoMsg) ?></div>
        <?php endif; ?>

        <?php if ($studentQuery !== ''): ?>
            <div class="bubble bubble-user">
                <strong>Teacher:</strong> Analyze student "<?= htmlspecialchars($studentQuery) ?>" using past 3 exams and suggest IEP.
            </div>
        <?php endif; ?>

        <?php if ($student && !empty($examRows)): ?>
            <div class="bubble bubble-ai">
                <div class="fw-bold mb-1"><i class="fas fa-user-graduate me-1 text-primary"></i><?= htmlspecialchars($student['fullname']) ?></div>
                <div class="small text-muted mb-2">ID: <?= htmlspecialchars($student['student_id']) ?> | Class: <?= htmlspecialchars($student['class_name'] ?? 'N/A') ?> <?= !empty($student['stream']) ? '- ' . htmlspecialchars($student['stream']) : '' ?></div>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($examRows as $m): ?>
                        <span class="badge badge-kpi rounded-pill px-3 py-2">
                            <?= htmlspecialchars($m['exam_type']) ?> <?= htmlspecialchars($m['academic_year']) ?>: <?= number_format((float)$m['score_100'], 1) ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (is_array($analysis)): ?>
            <div class="bubble bubble-ai">
                <div class="fw-bold mb-2"><i class="fas fa-robot me-1 text-success"></i>AI Prediction</div>
                <p class="mb-2"><?= htmlspecialchars($analysis['student_summary'] ?? '') ?></p>
                <div class="row g-2 mb-2">
                    <div class="col-md-3"><div class="p-2 border rounded bg-light"><small class="text-muted d-block">Trend</small><strong><?= htmlspecialchars($analysis['prediction']['trend'] ?? 'N/A') ?></strong></div></div>
                    <div class="col-md-3"><div class="p-2 border rounded bg-light"><small class="text-muted d-block">Risk</small><strong><?= htmlspecialchars($analysis['prediction']['risk_level'] ?? 'N/A') ?></strong></div></div>
                    <div class="col-md-3"><div class="p-2 border rounded bg-light"><small class="text-muted d-block">Next Score</small><strong><?= htmlspecialchars($analysis['prediction']['likely_next_score_range'] ?? 'N/A') ?></strong></div></div>
                    <div class="col-md-3"><div class="p-2 border rounded bg-light"><small class="text-muted d-block">Confidence</small><strong><?= htmlspecialchars($analysis['prediction']['confidence'] ?? 'N/A') ?></strong></div></div>
                </div>

                <div class="mt-3">
                    <h6 class="fw-bold mb-2">IEP Recommendation</h6>
                    <div class="small text-muted mb-1">Learning Goals</div>
                    <ul>
                        <?php foreach (($analysis['iep_recommendation']['learning_goals'] ?? []) as $item): ?>
                            <li><?= htmlspecialchars($item) ?></li>
                        <?php endforeach; ?>
                    </ul>

                    <div class="small text-muted mb-1">Teacher Actions</div>
                    <ul>
                        <?php foreach (($analysis['iep_recommendation']['teacher_actions'] ?? []) as $item): ?>
                            <li><?= htmlspecialchars($item) ?></li>
                        <?php endforeach; ?>
                    </ul>

                    <div class="small text-muted mb-1">Parent Actions</div>
                    <ul>
                        <?php foreach (($analysis['iep_recommendation']['parent_actions'] ?? []) as $item): ?>
                            <li><?= htmlspecialchars($item) ?></li>
                        <?php endforeach; ?>
                    </ul>

                    <p class="mb-1"><strong>Follow-up (weeks):</strong> <?= htmlspecialchars((string)($analysis['iep_recommendation']['follow_up_weeks'] ?? '4')) ?></p>
                    <p class="mb-0"><strong>Short Feedback:</strong> <?= htmlspecialchars($analysis['short_feedback'] ?? '') ?></p>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($rawAi !== '' && !is_array($analysis)): ?>
            <div class="bubble bubble-ai">
                <div class="fw-bold mb-2">Raw AI Output</div>
                <pre class="mb-0"><?= htmlspecialchars($rawAi) ?></pre>
            </div>
        <?php endif; ?>
    </div>

    <div class="composer">
        <form method="POST">
            <div class="input-group">
                <span class="input-group-text bg-white border-0"><i class="fas fa-message text-primary"></i></span>
                <input
                    type="text"
                    name="student_query"
                    class="form-control"
                    value="<?= htmlspecialchars($studentQuery) ?>"
                    placeholder="Andika Student ID au Jina, mf: HV/2024/7702"
                    required
                >
                <button type="submit" class="btn btn-primary rounded-pill px-4 m-1">
                    <i class="fas fa-paper-plane me-1"></i>Analyze
                </button>
            </div>
            <small class="text-muted d-block mt-2">AI itatumia records 3 za mwisho za mtihani kutengeneza prediction na IEP.</small>
        </form>
    </div>
</div>

</body>
</html>
