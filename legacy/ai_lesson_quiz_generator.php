<?php
session_start();
include('config.php');

// Optional: basic auth gate if your system uses session user check.
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

/**
 * Build quiz prompt using Bloom's Taxonomy levels.
 */
function buildQuizPrompt($topic, $classLevel) {
    $topicSafe = trim($topic);
    $classSafe = trim($classLevel);

    return "You are an expert teacher assistant.
Generate a multiple-choice quiz for topic: {$topicSafe} and class level: {$classSafe}.

Rules:
- Use Bloom's Taxonomy and include at least one question from each level:
  Remember, Understand, Apply, Analyze, Evaluate, Create.
- Generate exactly 12 questions.
- Each question must have 4 options (A, B, C, D).
- Provide one correct answer per question.
- Add a short explanation for why the answer is correct.
- Language: English (simple classroom style).
- Return ONLY valid JSON. No markdown, no extra text.

Output JSON schema:
{
  \"topic\": \"string\",
  \"class_level\": \"string\",
  \"questions\": [
    {
      \"number\": 1,
      \"blooms_level\": \"Remember|Understand|Apply|Analyze|Evaluate|Create\",
      \"question\": \"string\",
      \"options\": {
        \"A\": \"string\",
        \"B\": \"string\",
        \"C\": \"string\",
        \"D\": \"string\"
      },
      \"correct_answer\": \"A|B|C|D\",
      \"explanation\": \"string\"
    }
  ]
}";
}

/**
 * Remove common code fence wrappers if model returns markdown.
 */
function stripCodeFence($text) {
    $trimmed = trim((string) $text);
    if (strpos($trimmed, '```') === 0) {
        $trimmed = preg_replace('/^```[a-zA-Z]*\s*/', '', $trimmed);
        $trimmed = preg_replace('/\s*```$/', '', $trimmed);
    }
    return trim($trimmed);
}

function hasUsableKey($key) {
    $k = trim((string) $key);
    return $k !== '' && $k !== 'PUT_YOUR_AI_API_KEY_HERE';
}

/**
 * Execute HTTP POST JSON request with cURL.
 */
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

/**
 * Gemini call with model fallback to reduce 404 model errors.
 */
function callGeminiWithFallback($prompt, $timeoutSeconds = 40) {
    $geminiKey = (string) AI_GEMINI_API_KEY;
    if (!hasUsableKey($geminiKey)) {
        return ['ok' => false, 'error' => 'Gemini API key is not configured.', 'http_code' => 0];
    }

    $configuredModel = trim((string) AI_GEMINI_MODEL);
    $fallbackModels = [
        $configuredModel,
        'gemini-2.0-flash',
        'gemini-1.5-flash-latest',
        'gemini-1.5-pro-latest'
    ];

    $models = [];
    foreach ($fallbackModels as $m) {
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
            'temperature' => 0.4,
            'maxOutputTokens' => 4096,
            'responseMimeType' => 'application/json'
        ]
    ];
    $headers = ['Content-Type: application/json'];

    $attempted = [];
    $lastHttpError = '';

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
            if (!is_array($decoded)) {
                return ['ok' => false, 'error' => 'Failed to parse Gemini API response JSON.', 'http_code' => $httpCode];
            }

            $text = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '';
            if ($text === '') {
                return ['ok' => false, 'error' => 'Gemini response did not contain text output.', 'http_code' => $httpCode];
            }

            return [
                'ok' => true,
                'content' => stripCodeFence($text),
                'raw' => $decoded,
                'model_used' => $model
            ];
        }

        if ($httpCode === 429) {
            return [
                'ok' => false,
                'error' => 'Gemini quota exceeded (HTTP 429). Please top up billing/plan or use OpenAI fallback.',
                'http_code' => 429,
                'raw' => $rawResponse
            ];
        }

        // If model not found, continue trying fallback models.
        if ($httpCode === 404) {
            $lastHttpError = 'HTTP 404 for model ' . $model . '. Raw: ' . substr($rawResponse, 0, 300);
            continue;
        }

        return ['ok' => false, 'error' => 'Gemini API HTTP error ' . $httpCode . '. Raw: ' . substr($rawResponse, 0, 500), 'http_code' => $httpCode];
    }

    return [
        'ok' => false,
        'error' => 'No working Gemini model found. Attempted: ' . implode(', ', $attempted) . '. ' . $lastHttpError,
        'http_code' => 404
    ];
}

function callOpenAiApi($prompt, $timeoutSeconds = 40) {
    $openAiKey = (string) AI_OPENAI_API_KEY;
    if (!hasUsableKey($openAiKey)) {
        return ['ok' => false, 'error' => 'OpenAI API key is not configured.', 'http_code' => 0];
    }

    $endpoint = AI_OPENAI_BASE_URL;
    $payload = [
        'model' => AI_OPENAI_MODEL,
        'temperature' => 0.4,
        'messages' => [
            ['role' => 'system', 'content' => 'You are a precise teaching assistant that returns only valid JSON.'],
            ['role' => 'user', 'content' => $prompt]
        ],
        'response_format' => ['type' => 'json_object']
    ];
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $openAiKey
    ];

    $http = postJsonWithCurl($endpoint, $headers, $payload, $timeoutSeconds);
    $rawResponse = (string) $http['raw_response'];
    $curlErrNo = (int) $http['curl_errno'];
    $curlError = (string) $http['curl_error'];
    $httpCode = (int) $http['http_code'];

    if ($curlErrNo !== 0) {
        return ['ok' => false, 'error' => 'Network/timeout error: ' . $curlError, 'http_code' => 0];
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        return ['ok' => false, 'error' => 'OpenAI API HTTP error ' . $httpCode . '. Raw: ' . substr((string) $rawResponse, 0, 500), 'http_code' => $httpCode];
    }

    $decoded = json_decode((string) $rawResponse, true);
    if (!is_array($decoded)) {
        return ['ok' => false, 'error' => 'Failed to parse OpenAI API response JSON.', 'http_code' => $httpCode];
    }

    $text = $decoded['choices'][0]['message']['content'] ?? '';
    if ($text === '') {
        return ['ok' => false, 'error' => 'OpenAI response did not contain message content.', 'http_code' => $httpCode];
    }

    return ['ok' => true, 'content' => stripCodeFence($text), 'raw' => $decoded, 'provider_used' => 'openai'];
}

/**
 * Make AI API call using cURL for Gemini or OpenAI.
 */
function callAiApi($prompt) {
    $provider = strtolower(AI_PROVIDER);
    $timeoutSeconds = 40;

    if ($provider === 'gemini') {
        $geminiResult = callGeminiWithFallback($prompt, $timeoutSeconds);
        if ($geminiResult['ok']) {
            return $geminiResult;
        }

        // Automatic fallback to OpenAI when Gemini hits quota limit.
        if (((int) ($geminiResult['http_code'] ?? 0) === 429) && hasUsableKey(AI_OPENAI_API_KEY)) {
            $openAiResult = callOpenAiApi($prompt, $timeoutSeconds);
            if ($openAiResult['ok']) {
                $openAiResult['warning'] = 'Gemini quota exceeded, response generated using OpenAI fallback.';
                return $openAiResult;
            }
            return $openAiResult;
        }

        return $geminiResult;
    } elseif ($provider === 'openai') {
        return callOpenAiApi($prompt, $timeoutSeconds);
    } else {
        return ['ok' => false, 'error' => 'Unsupported AI_PROVIDER. Use gemini or openai.'];
    }
}

$topic = trim($_POST['topic'] ?? '');
$classLevel = trim($_POST['class_level'] ?? '');
$quizData = null;
$errorMsg = '';
$infoMsg = '';
$rawJsonOutput = '';
$bloomsSummary = [
    'Remember' => 0,
    'Understand' => 0,
    'Apply' => 0,
    'Analyze' => 0,
    'Evaluate' => 0,
    'Create' => 0
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($topic === '' || $classLevel === '') {
        $errorMsg = 'Please provide both Topic and Class Level.';
    } else {
        $prompt = buildQuizPrompt($topic, $classLevel);
        $aiResult = callAiApi($prompt);

        if (!$aiResult['ok']) {
            $errorMsg = $aiResult['error'];
        } else {
            if (!empty($aiResult['warning'])) {
                $infoMsg = (string) $aiResult['warning'];
            }
            $rawJsonOutput = (string) $aiResult['content'];
            $quizData = json_decode($rawJsonOutput, true);
            if (!is_array($quizData) || !isset($quizData['questions']) || !is_array($quizData['questions'])) {
                $errorMsg = 'AI returned invalid quiz JSON format. Raw output is shown below for debugging.';
            } else {
                foreach ($quizData['questions'] as $row) {
                    $level = trim((string) ($row['blooms_level'] ?? ''));
                    if (isset($bloomsSummary[$level])) {
                        $bloomsSummary[$level]++;
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
    <title>AI Lesson & Quiz Generator</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --brand: #2563eb;
            --brand-dark: #1e40af;
            --surface: #f1f5f9;
        }
        body { background: radial-gradient(circle at top left, #e2e8f0 0%, var(--surface) 45%, #eef2ff 100%); }
        .card-soft { border: none; border-radius: 16px; }
        .question-card { border-left: 4px solid #2563eb; }
        pre { white-space: pre-wrap; word-break: break-word; }
        .hero-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8fbff 100%);
            border: 1px solid #dbeafe;
            border-radius: 20px;
            padding: 18px 20px;
            box-shadow: 0 10px 30px rgba(37, 99, 235, 0.08);
        }
        .title-icon {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            border: 2px solid #93c5fd;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #eff6ff;
            color: var(--brand-dark);
            box-shadow: inset 0 0 0 3px #dbeafe;
        }
        .chart-wrap {
            border: 1px solid #dbeafe;
            border-radius: 14px;
            padding: 12px;
            background: #fff;
        }
        .result-kpi {
            border: 1px solid #dbeafe;
            background: #f8fbff;
            border-radius: 12px;
            padding: 10px 12px;
            min-width: 180px;
        }
    </style>
</head>
<body>
<div class="container py-4">
    <div class="hero-card mb-4 d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-3">
            <span class="title-icon"><i class="fas fa-chart-column"></i></span>
            <div>
                <h4 class="m-0 fw-bold text-primary">AI Lesson & Quiz Generator</h4>
                <small class="text-muted">Bloom-based quiz output with chart insights for teachers</small>
            </div>
        </div>
        <a href="teacher_dashboard.php" class="btn btn-outline-dark rounded-pill px-4">Back</a>
    </div>

    <div class="card card-soft shadow-sm mb-4">
        <div class="card-body p-4">
            <form method="POST" class="row g-3 align-items-end">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Topic</label>
                    <input type="text" name="topic" class="form-control" value="<?= htmlspecialchars($topic) ?>" placeholder="e.g. Photosynthesis" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Class Level</label>
                    <input type="text" name="class_level" class="form-control" value="<?= htmlspecialchars($classLevel) ?>" placeholder="e.g. Form 2" required>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-wand-magic-sparkles me-1"></i>Generate
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($errorMsg !== ''): ?>
        <div class="alert alert-danger shadow-sm">
            <strong>Unable to generate quiz:</strong> <?= htmlspecialchars($errorMsg) ?>
        </div>
    <?php endif; ?>

    <?php if ($infoMsg !== ''): ?>
        <div class="alert alert-warning shadow-sm">
            <?= htmlspecialchars($infoMsg) ?>
        </div>
    <?php endif; ?>

    <?php if (is_array($quizData) && isset($quizData['questions']) && is_array($quizData['questions'])): ?>
        <div class="card card-soft shadow-sm mb-4">
            <div class="card-body p-4">
                <div class="d-flex flex-wrap gap-2 justify-content-between align-items-start mb-3">
                    <div>
                        <h5 class="fw-bold mb-1"><?= htmlspecialchars($quizData['topic'] ?? $topic) ?></h5>
                        <div class="text-muted">Class Level: <?= htmlspecialchars($quizData['class_level'] ?? $classLevel) ?></div>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <div class="result-kpi">
                            <div class="small text-muted">Total Questions</div>
                            <div class="fw-bold text-primary"><?= number_format(count($quizData['questions'])) ?></div>
                        </div>
                        <div class="result-kpi">
                            <div class="small text-muted">Bloom Levels Used</div>
                            <div class="fw-bold text-success"><?= number_format(count(array_filter($bloomsSummary))) ?>/6</div>
                        </div>
                    </div>
                </div>

                <div class="chart-wrap mb-4">
                    <div class="small fw-bold text-secondary mb-2"><i class="fas fa-chart-bar me-1"></i>Bloom Taxonomy Distribution</div>
                    <canvas id="bloomsChart" height="90"></canvas>
                </div>

                <?php foreach ($quizData['questions'] as $q): ?>
                    <div class="card question-card mb-3">
                        <div class="card-body">
                            <div class="small text-primary fw-bold mb-1">Bloom Level: <?= htmlspecialchars($q['blooms_level'] ?? 'N/A') ?></div>
                            <div class="fw-semibold mb-2">
                                <?= (int)($q['number'] ?? 0) ?>. <?= htmlspecialchars($q['question'] ?? '') ?>
                            </div>
                            <ul class="mb-2">
                                <li>A. <?= htmlspecialchars($q['options']['A'] ?? '') ?></li>
                                <li>B. <?= htmlspecialchars($q['options']['B'] ?? '') ?></li>
                                <li>C. <?= htmlspecialchars($q['options']['C'] ?? '') ?></li>
                                <li>D. <?= htmlspecialchars($q['options']['D'] ?? '') ?></li>
                            </ul>
                            <div><strong>Correct Answer:</strong> <?= htmlspecialchars($q['correct_answer'] ?? '') ?></div>
                            <div class="text-muted small"><strong>Explanation:</strong> <?= htmlspecialchars($q['explanation'] ?? '') ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($rawJsonOutput !== ''): ?>
        <div class="card card-soft shadow-sm">
            <div class="card-header bg-white fw-bold">Raw JSON Output</div>
            <div class="card-body">
                <pre class="mb-0"><?= htmlspecialchars($rawJsonOutput) ?></pre>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php if (is_array($quizData) && isset($quizData['questions']) && is_array($quizData['questions'])): ?>
<script>
    (function () {
        const ctx = document.getElementById('bloomsChart');
        if (!ctx) return;

        const labels = <?= json_encode(array_keys($bloomsSummary)) ?>;
        const data = <?= json_encode(array_values($bloomsSummary)) ?>;

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Questions',
                    data: data,
                    backgroundColor: ['#1d4ed8', '#2563eb', '#3b82f6', '#60a5fa', '#0ea5e9', '#0284c7'],
                    borderRadius: 8,
                    maxBarThickness: 42
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0 }
                    }
                },
                plugins: {
                    legend: { display: false }
                }
            }
        });
    })();
</script>
<?php endif; ?>
</body>
</html>
