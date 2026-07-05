<?php
session_start();
include('db_config.php');
include('config.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$currentRole = strtolower((string) ($_SESSION['role'] ?? ''));
$isParentUser = ($currentRole === 'parent');
$backUrl = 'teacher_dashboard.php';
if ($isParentUser) {
    $backUrl = 'student_dashboard.php';
} elseif ($currentRole === 'admin') {
    $backUrl = 'admin_dashboard.php';
} elseif ($currentRole === 'accountant') {
    $backUrl = 'Accountant.php';
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

function postJsonWithCurl($endpoint, array $headers, array $payload, $timeoutSeconds = 35) {
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

function callGemini($prompt, $timeoutSeconds = 35) {
    if (!hasUsableKey(AI_GEMINI_API_KEY)) {
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
        'contents' => [[ 'parts' => [[ 'text' => $prompt ]] ]],
        'generationConfig' => [
            'temperature' => 0.35,
            'maxOutputTokens' => 1000,
            'responseMimeType' => 'application/json'
        ]
    ];

    foreach ($models as $model) {
        $endpoint = rtrim(AI_GEMINI_BASE_URL, '/') . '/' . $model . ':generateContent?key=' . urlencode(AI_GEMINI_API_KEY);
        $http = postJsonWithCurl($endpoint, ['Content-Type: application/json'], $payload, $timeoutSeconds);

        if ((int) $http['curl_errno'] !== 0) {
            return ['ok' => false, 'error' => 'Network/timeout error: ' . $http['curl_error'], 'http_code' => 0];
        }

        $httpCode = (int) $http['http_code'];
        $raw = (string) $http['raw_response'];

        if ($httpCode >= 200 && $httpCode < 300) {
            $decoded = json_decode($raw, true);
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
        return ['ok' => false, 'error' => 'Gemini API HTTP error ' . $httpCode . '. Raw: ' . substr($raw, 0, 300), 'http_code' => $httpCode];
    }

    return ['ok' => false, 'error' => 'No available Gemini model found.', 'http_code' => 404];
}

function callOpenAi($prompt, $timeoutSeconds = 35) {
    if (!hasUsableKey(AI_OPENAI_API_KEY)) {
        return ['ok' => false, 'error' => 'OpenAI API key is not configured.', 'http_code' => 0];
    }

    $payload = [
        'model' => AI_OPENAI_MODEL,
        'temperature' => 0.35,
        'messages' => [
            ['role' => 'system', 'content' => 'You are a professional teacher assistant. Return only valid JSON.'],
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
        $timeoutSeconds
    );

    if ((int) $http['curl_errno'] !== 0) {
        return ['ok' => false, 'error' => 'Network/timeout error: ' . $http['curl_error'], 'http_code' => 0];
    }

    $httpCode = (int) $http['http_code'];
    $raw = (string) $http['raw_response'];
    if ($httpCode < 200 || $httpCode >= 300) {
        return ['ok' => false, 'error' => 'OpenAI API HTTP error ' . $httpCode . '. Raw: ' . substr($raw, 0, 300), 'http_code' => $httpCode];
    }

    $decoded = json_decode($raw, true);
    $text = $decoded['choices'][0]['message']['content'] ?? '';
    if (!is_array($decoded) || $text === '') {
        return ['ok' => false, 'error' => 'OpenAI response format is invalid.', 'http_code' => $httpCode];
    }
    return ['ok' => true, 'content' => stripCodeFence($text), 'provider_used' => 'openai', 'model_used' => AI_OPENAI_MODEL];
}

function callAiForComment($prompt) {
    $provider = strtolower((string) AI_PROVIDER);
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

function parseAcademicYearRange($academicYear) {
    $academicYear = trim((string) $academicYear);
    if (preg_match('/^(\d{4})\/(\d{4})$/', $academicYear, $m)) {
        return [(int)$m[1], (int)$m[2]];
    }
    $y = (int) date('Y');
    return [$y, $y];
}

function buildCommentPrompt($studentProfile, $terminalData) {
    $profileJson = json_encode($studentProfile, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    $terminalJson = json_encode($terminalData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    return "Andika maoni ya mwalimu kwa kadi ya ripoti kwa lugha ya Kiswahili.

Taarifa za mwanafunzi:
{$profileJson}

Taarifa za terminal:
{$terminalJson}

Masharti:
- Maoni yawe ya kitaaluma, ya kujenga, na ya heshima.
- Yasiseme uongo; yaendane na alama na attendance.
- Toa strengths, maeneo ya kuboresha, na ushauri mfupi wa hatua inayofuata.
- Urefu wa comment kuu uwe sentensi 3 hadi 5.
- Return ONLY valid JSON.

JSON schema:
{
  \"teacher_comment_sw\": \"string\",
  \"strengths\": [\"string\", \"string\"],
  \"improvement_areas\": [\"string\", \"string\"],
  \"next_step\": \"string\"
}";
}

$studentQuery = trim($_POST['student_query'] ?? '');
$academicYear = trim($_POST['academic_year'] ?? '2025/2026');
$term = trim($_POST['term'] ?? 'Term 1');

$errorMsg = '';
$infoMsg = '';
$student = null;
$terminalData = null;
$aiData = null;
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

        if ($isParentUser) {
            $stmt = $conn->prepare("SELECT id, student_id, fullname, class_name, stream FROM students WHERE id = ? LIMIT 1");
            $selfId = (int) $_SESSION['user_id'];
            $stmt->bind_param('i', $selfId);
            $stmt->execute();
            $res = $stmt->get_result();
            $studentQuery = (string) ($_SESSION['fullname'] ?? $studentQuery);
        } else {
            $stmt = $conn->prepare($sql);
            $like = '%' . $studentQuery . '%';
            $stmt->bind_param('sss', $studentQuery, $like, $studentQuery);
            $stmt->execute();
            $res = $stmt->get_result();
        }

        if (!$res || $res->num_rows === 0) {
            $errorMsg = 'Student not found.';
        } else {
            $student = $res->fetch_assoc();

            $marksStmt = $conn->prepare("SELECT
                    COALESCE(SUM(total_100), 0) AS total_marks,
                    COALESCE(AVG(total_100), 0) AS avg_marks,
                    COUNT(*) AS subject_count
                FROM marks
                WHERE student_id = ? AND year = ? AND term = ?");
            $marksStmt->bind_param('iss', $student['id'], $academicYear, $term);
            $marksStmt->execute();
            $marksRow = $marksStmt->get_result()->fetch_assoc();

            $attendancePct = 0;
            if ($attCheck = $conn->query("SHOW TABLES LIKE 'student_attendance'")) {
                if ($attCheck->num_rows > 0) {
                    [$startYear, $endYear] = parseAcademicYearRange($academicYear);
                    $attendanceStmt = $conn->prepare("SELECT
                            SUM(CASE WHEN status = 'P' THEN 1 ELSE 0 END) AS present_days,
                            SUM(CASE WHEN status = 'A' THEN 1 ELSE 0 END) AS absent_days
                        FROM student_attendance
                        WHERE student_id = ?
                          AND YEAR(attendance_date) BETWEEN ? AND ?");
                    $attendanceStmt->bind_param('iii', $student['id'], $startYear, $endYear);
                    $attendanceStmt->execute();
                    $attRow = $attendanceStmt->get_result()->fetch_assoc();

                    $present = (int)($attRow['present_days'] ?? 0);
                    $absent = (int)($attRow['absent_days'] ?? 0);
                    $totalDays = $present + $absent;
                    $attendancePct = $totalDays > 0 ? round(($present / $totalDays) * 100, 1) : 0;
                }
            }

            $subjectCount = (int)($marksRow['subject_count'] ?? 0);
            if ($subjectCount <= 0) {
                $errorMsg = 'No marks data found for selected year and term.';
            } else {
                $terminalData = [
                    'academic_year' => $academicYear,
                    'term' => $term,
                    'subject_count' => $subjectCount,
                    'total_marks' => round((float)($marksRow['total_marks'] ?? 0), 1),
                    'average_marks' => round((float)($marksRow['avg_marks'] ?? 0), 1),
                    'attendance_percentage' => $attendancePct
                ];

                $profile = [
                    'student_name' => $student['fullname'],
                    'student_id' => $student['student_id'],
                    'class_name' => $student['class_name'] ?? '',
                    'stream' => $student['stream'] ?? ''
                ];

                $prompt = buildCommentPrompt($profile, $terminalData);
                $aiResult = callAiForComment($prompt);

                if (!$aiResult['ok']) {
                    $errorMsg = $aiResult['error'];
                } else {
                    if (!empty($aiResult['warning'])) {
                        $infoMsg = (string)$aiResult['warning'];
                    }
                    $rawAi = (string)$aiResult['content'];
                    $aiData = json_decode($rawAi, true);
                    if (!is_array($aiData) || empty($aiData['teacher_comment_sw'])) {
                        $errorMsg = 'AI returned invalid comment format. See raw output below.';
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
    <title>AI Auto-Comments (Report Card)</title>
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
            background: linear-gradient(135deg, #0f766e 0%, #0d9488 55%, #14b8a6 100%);
            color: #fff;
            padding: 14px 18px;
        }
        .chat-body {
            padding: 18px;
            min-height: 360px;
            background: #f8fafc;
        }
        .bubble {
            max-width: 80%;
            border-radius: 14px;
            padding: 12px 14px;
            margin-bottom: 12px;
            line-height: 1.5;
            border: 1px solid transparent;
        }
        .bubble-user {
            margin-left: auto;
            background: #ccfbf1;
            border-color: #5eead4;
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
        .composer .input-wrap {
            border: 1px solid #cbd5e1;
            border-radius: 16px;
            padding: 8px;
            background: #fff;
        }
        .composer input, .composer select {
            border: 1px solid #e2e8f0 !important;
            box-shadow: none !important;
        }
        .kpi {
            border: 1px solid #99f6e4;
            background: #f0fdfa;
            border-radius: 10px;
            padding: 8px 10px;
        }
        pre { white-space: pre-wrap; word-break: break-word; }
    </style>
</head>
<body>

<div class="chat-shell">
    <div class="chat-header d-flex justify-content-between align-items-center">
        <div>
            <h5 class="m-0 fw-bold"><i class="fas fa-comment-dots me-2"></i>AI Auto-Comments (Feature 3)</h5>
            <small>Generate constructive report-card comments in Kiswahili</small>
        </div>
        <a href="<?= htmlspecialchars($backUrl) ?>" class="btn btn-sm btn-light rounded-pill px-3">Back</a>
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
                <strong>Teacher:</strong> Nipe maoni ya report card kwa mwanafunzi "<?= htmlspecialchars($studentQuery) ?>" kwa <?= htmlspecialchars($term) ?> (<?= htmlspecialchars($academicYear) ?>).
            </div>
        <?php endif; ?>

        <?php if ($student && $terminalData): ?>
            <div class="bubble bubble-ai">
                <div class="fw-bold mb-1"><i class="fas fa-user-graduate me-1 text-success"></i><?= htmlspecialchars($student['fullname']) ?></div>
                <div class="small text-muted mb-2">ID: <?= htmlspecialchars($student['student_id']) ?> | Class: <?= htmlspecialchars($student['class_name'] ?? 'N/A') ?> <?= !empty($student['stream']) ? '- ' . htmlspecialchars($student['stream']) : '' ?></div>
                <div class="d-flex flex-wrap gap-2">
                    <div class="kpi"><small class="text-muted d-block">Total Marks</small><strong><?= number_format((float)$terminalData['total_marks'], 1) ?></strong></div>
                    <div class="kpi"><small class="text-muted d-block">Average</small><strong><?= number_format((float)$terminalData['average_marks'], 1) ?>%</strong></div>
                    <div class="kpi"><small class="text-muted d-block">Attendance</small><strong><?= number_format((float)$terminalData['attendance_percentage'], 1) ?>%</strong></div>
                    <div class="kpi"><small class="text-muted d-block">Subjects</small><strong><?= number_format((int)$terminalData['subject_count']) ?></strong></div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (is_array($aiData) && !empty($aiData['teacher_comment_sw'])): ?>
            <div class="bubble bubble-ai">
                <div class="fw-bold mb-2"><i class="fas fa-robot me-1 text-primary"></i>AI Teacher Comment</div>
                <p class="mb-3"><?= htmlspecialchars($aiData['teacher_comment_sw']) ?></p>

                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="border rounded p-2 h-100 bg-light-subtle">
                            <div class="small text-muted fw-bold mb-1">Nguvu za Mwanafunzi</div>
                            <ul class="mb-0">
                                <?php foreach (($aiData['strengths'] ?? []) as $item): ?>
                                    <li><?= htmlspecialchars($item) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="border rounded p-2 h-100 bg-light-subtle">
                            <div class="small text-muted fw-bold mb-1">Maeneo ya Kuboresha</div>
                            <ul class="mb-0">
                                <?php foreach (($aiData['improvement_areas'] ?? []) as $item): ?>
                                    <li><?= htmlspecialchars($item) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="mt-3"><strong>Hatua Inayofuata:</strong> <?= htmlspecialchars($aiData['next_step'] ?? '') ?></div>
            </div>
        <?php endif; ?>

        <?php if ($rawAi !== '' && (!is_array($aiData) || empty($aiData['teacher_comment_sw']))): ?>
            <div class="bubble bubble-ai">
                <div class="fw-bold mb-2">Raw AI Output</div>
                <pre class="mb-0"><?= htmlspecialchars($rawAi) ?></pre>
            </div>
        <?php endif; ?>
    </div>

    <div class="composer">
        <form method="POST">
            <div class="input-wrap">
                <div class="row g-2 align-items-center">
                    <div class="col-md-5">
                        <input type="text" name="student_query" class="form-control" value="<?= htmlspecialchars($studentQuery) ?>" placeholder="Andika Student ID au Jina" <?= $isParentUser ? 'readonly' : '' ?> required>
                    </div>
                    <div class="col-md-3">
                        <select name="academic_year" class="form-select">
                            <?php for ($i = 2015; $i <= 2036; $i++): $y = $i . '/' . ($i + 1); ?>
                                <option value="<?= $y ?>" <?= ($academicYear === $y) ? 'selected' : '' ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="term" class="form-select">
                            <?php foreach (['Term 1','Term 2','Terminal','Final','Midterm'] as $t): ?>
                                <option value="<?= $t ?>" <?= ($term === $t) ? 'selected' : '' ?>><?= $t ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-success w-100 rounded-pill">
                            <i class="fas fa-paper-plane me-1"></i>Generate
                        </button>
                    </div>
                </div>
            </div>
            <small class="text-muted d-block mt-2">Inatumia total marks, average, na attendance percentage kutengeneza comment ya Kiswahili.</small>
        </form>
    </div>
</div>

</body>
</html>
