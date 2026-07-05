<?php
session_start();

// Enable error reporting for development debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// CONFIGURATION: Add your Gemini API Key here
define('GEMINI_API_KEY', 'YOUR_GEMINI_API_KEY_HERE');

// 1. BACKEND AI OCR PROCESSING ENTRYPOINT (Handles Camera Image Uploads)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['ocr_image'])) {
    header('Content-Type: application/json');

    if (empty(GEMINI_API_KEY) || GEMINI_API_KEY === 'YOUR_GEMINI_API_KEY_HERE') {
        echo json_encode(['success' => false, 'error' => 'Gemini API Key is not configured in the script.']);
        exit();
    }

    $file_tmp = $_FILES['ocr_image']['tmp_name'];
    $file_type = $_FILES['ocr_image']['type'];

    if (!file_exists($file_tmp)) {
        echo json_encode(['success' => false, 'error' => 'Uploaded image file not found on server storage.']);
        exit();
    }

    // Convert image binary data to Base64 encoding
    $imageData = base64_encode(file_get_contents($file_tmp));

    // Formulate a structured prompt instructing the vision model exactly how to parse the handwriting
    $prompt = "Analyze this image of a handwritten student marks list. Extract every single student's name and their corresponding marks. " .
              "Output each student on a brand new line formatted exactly like this: 'Name Mark' (e.g., 'Asma Machano Juma 67'). " .
              "Do not include table gridlines, serial numbers, headers (like S/N, Jina, Marks), or any conversational introductory text. " .
              "Just return the names and their scores line by line.";

    // Structure the JSON payload for the Gemini API call
    $payload = [
        "contents" => [
            [
                "parts" => [
                    ["text" => $prompt],
                    [
                        "inlineData" => [
                            "mimeType" => $file_type,
                            "data" => $imageData
                        ]
                    ]
                ]
            ]
        ]
    ];

    // Initialize cURL targeting the Gemini 2.5 Flash Vision Endpoint
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . GEMINI_API_KEY;
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    $response = curl_exec($ch);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        echo json_encode(['success' => false, 'error' => 'cURL Error: ' . $curl_error]);
        exit();
    }

    $resultData = json_decode($response, true);
    
    // Parse response content safely out of the nested Gemini architecture
    if (isset($resultData['candidates'][0]['content']['parts'][0]['text'])) {
        $extractedText = $resultData['candidates'][0]['content']['parts'][0]['text'];
        echo json_encode(['success' => true, 'text' => $extractedText]);
    } else {
        echo json_encode(['success' => false, 'error' => 'AI could not read the layout. Check if payload structure matches API standards.', 'raw' => $resultData]);
    }
    exit();
}

include('db_config.php');

if (!isset($_SESSION['user_id'])) { 
    header("Location: index.php"); 
    exit(); 
}

// Capture Context Filters
$level       = $_GET['level'] ?? '';
$class       = $_GET['class'] ?? '';
$stream      = $_GET['stream'] ?? '';
$subject_id  = $_GET['subject'] ?? '';
$year        = $_GET['year'] ?? '';
$exam        = $_GET['exam_type'] ?? '';

// Fetch students for fuzzy matching
$students_db = [];
if (!empty($class) && !empty($stream)) {
    $st_query = $conn->query("SELECT id, fullname FROM students WHERE class_name = '$class' AND stream = '$stream' ORDER BY fullname ASC");
    if ($st_query) {
        while($row = $st_query->fetch_assoc()) { 
            $students_db[] = $row; 
        }
    }
}

// Fuzzy Matching Logic (PHP Side for CSV)
function getBestStudentMatch($rawName, $students) {
    $bestMatch = null;
    $highestScore = 0;
    foreach ($students as $student) {
        similar_text(strtoupper(trim($rawName)), strtoupper(trim($student['fullname'])), $percent);
        if ($percent > $highestScore) {
            $highestScore = $percent;
            $bestMatch = $student;
        }
    }
    return ($highestScore >= 75) ? $bestMatch : null;
}

$processed_data = [];
$excel_error = "";

// CSV processing logic
if (isset($_POST['upload_excel']) && isset($_FILES['excel_file'])) {
    $file_tmp = $_FILES['excel_file']['tmp_name'];
    $file_name = $_FILES['excel_file']['name'];
    $ext = pathinfo($file_name, PATHINFO_EXTENSION);
    
    if (strtolower($ext) === 'csv') {
        if (($handle = fopen($file_tmp, "r")) !== FALSE) {
            $index = 0;
            while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
                if ($index == 0 || empty($row[0])) { 
                    $index++; 
                    continue; 
                }
                
                $rawName = $row[0]; 
                $rawMark = $row[1] ?? 0; 
                
                $match = getBestStudentMatch($rawName, $students_db);
                if ($match) {
                    $processed_data[] = [
                        'id' => $match['id'], 
                        'name' => $match['fullname'], 
                        'mark' => $rawMark
                    ];
                }
                $index++;
            }
            fclose($handle);
        } else {
            $excel_error = "Failed to open the CSV file.";
        }
    } else {
        $excel_error = "Please save your Excel file as .csv (Comma delimited) first.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Scanner | Likindy Digital Solution</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            --accent-color: #3b82f6;
            --success-color: #10b981;
            --body-bg: #f8fafc;
        }

        body { 
            background-color: var(--body-bg); 
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: #334155;
        }

        .glass-header {
            background: var(--primary-gradient);
            color: white;
            border-radius: 24px;
            padding: 2.5rem;
            box-shadow: 0 20px 40px rgba(15, 23, 42, 0.15);
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .glass-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 300px;
            height: 300px;
            background: rgba(59, 130, 246, 0.2);
            border-radius: 50%;
            filter: blur(50px);
        }

        .custom-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
            transition: all 0.3s ease;
        }

        .custom-card:hover {
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.06);
        }

        .nav-pills .nav-link {
            color: #64748b;
            font-weight: 600;
            padding: 0.6rem 1rem;
            border-radius: 12px;
            transition: all 0.2s ease;
            border: 1px solid transparent;
        }

        .nav-pills .nav-link.active {
            background-color: #eff6ff;
            color: var(--accent-color);
            border: 1px solid #bfdbfe;
        }

        .form-select, .form-control {
            border-radius: 12px;
            padding: 0.65rem 1rem;
            border: 1px solid #cbd5e1;
            font-size: 0.95rem;
        }

        .form-select:focus, .form-control:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
        }

        .file-upload-wrapper {
            position: relative;
            border: 2px dashed #cbd5e1;
            border-radius: 16px;
            padding: 2rem 1rem;
            text-align: center;
            background: #f8fafc;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .file-upload-wrapper:hover {
            background: #f1f5f9;
            border-color: var(--accent-color);
        }

        .file-upload-wrapper input[type="file"] {
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            opacity: 0;
            cursor: pointer;
        }

        .preview-img { 
            max-width: 100%; 
            max-height: 220px;
            object-fit: cover;
            border-radius: 14px; 
            display: none; 
            margin-top: 15px; 
            box-shadow: 0 8px 16px rgba(0,0,0,0.08);
        }

        .table {
            margin-bottom: 0;
        }

        .table th {
            font-weight: 600;
            color: #475569;
            background-color: #f8fafc !important;
            border-bottom: 2px solid #e2e8f0;
            padding: 1rem;
        }

        .table td {
            padding: 1rem;
            vertical-align: middle;
            border-bottom: 1px solid #f1f5f9;
        }

        .mark-input {
            width: 100px;
            text-align: center;
            font-weight: 700;
            color: #1e293b;
            border-radius: 10px;
            border: 1px solid #cbd5e1;
        }

        .filter-badge {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(4px);
            padding: 0.5rem 1rem;
            border-radius: 10px;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .loading {
            display: none;
            background: #fff;
            padding: 2rem;
            border-radius: 166px;
            border: 1px solid #e2e8f0;
        }

        .pulse-text {
            animation: pulse 1.5s infinite;
            font-weight: 600;
            color: var(--accent-color);
        }

        @keyframes pulse {
            0% { opacity: 0.6; }
            50% { opacity: 1; }
            100% { opacity: 0.6; }
        }
    </style>
</head>
<body>

<div class="container py-4">

    <?php if(!empty($excel_error)): ?>
        <div class="alert alert-danger border-0 shadow-sm rounded-4 p-3 mb-4 d-flex align-items-center">
            <i class="fas fa-exclamation-circle fa-lg me-3"></i>
            <div><strong>Upload Error:</strong> <?= $excel_error ?></div>
        </div>
    <?php endif; ?>

    <?php if(empty($class) || empty($stream)): ?>
        <div class="alert alert-warning border-0 shadow-sm rounded-4 p-3 mb-4 d-flex align-items-center">
            <i class="fas fa-info-circle fa-lg me-3"></i>
            <div>
                <strong>System Notice:</strong> It looks like you opened this page directly without selecting a class. Please ensure you navigate from the filters page to load the correct student list.
            </div>
        </div>
    <?php endif; ?>

    <div class="glass-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-4">
        <div>
            <span class="text-uppercase tracking-wider text-info small fw-bold"><i class="fas fa-bolt me-1"></i> AI-Powered Grade Intake</span>
            <h2 class="fw-bold text-white mt-1 mb-3 mb-md-0"><i class="fas fa-qrcode me-2"></i> Smart Marks Entry</h2>
            
            <div class="d-flex flex-wrap gap-2 mt-2">
                <?php if(!empty($class)): ?>
                    <span class="filter-badge"><i class="fas fa-graduation-cap"></i> <?= htmlspecialchars($class) ?></span>
                <?php endif; ?>
                <?php if(!empty($stream)): ?>
                    <span class="filter-badge"><i class="fas fa-users"></i> Stream: <?= htmlspecialchars($stream) ?></span>
                <?php endif; ?>
                <?php if(!empty($exam)): ?>
                    <span class="filter-badge"><i class="fas fa-file-invoice"></i> <?= htmlspecialchars($exam) ?></span>
                <?php endif; ?>
                <?php if(!empty($year)): ?>
                    <span class="filter-badge"><i class="fas fa-calendar-alt"></i> <?= htmlspecialchars($year) ?></span>
                <?php endif; ?>
            </div>
        </div>
        <div>
            <a href="javascript:history.back()" class="btn btn-light rounded-pill px-4 fw-600 btn-lg shadow-sm text-dark">
                <i class="fas fa-arrow-left me-2"></i> Go Back
            </a>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card custom-card p-4 mb-4">
                <h5 class="fw-bold mb-3 text-dark d-flex align-items-center">
                    <span class="p-2 bg-primary bg-opacity-10 text-primary rounded-3 me-2" style="width:36px; height:36px; display:inline-flex; align-items:center; justify-content:center;">1</span>
                    Mark Criteria
                </h5>
                
                <label class="text-muted small fw-bold mb-2">Grading System (Logic)</label>
                <select id="logic_mode" class="form-select mb-4">
                    <option value="raw">Marks are already at 60% (Raw)</option>
                    <option value="convert">Convert from 100% to 60%</option>
                </select>

                <h5 class="fw-bold mb-3 text-dark d-flex align-items-center">
                    <span class="p-2 bg-primary bg-opacity-10 text-primary rounded-3 me-2" style="width:36px; height:36px; display:inline-flex; align-items:center; justify-content:center;">2</span>
                    Data Input Method
                </h5>
                
                <div class="nav nav-pills mb-4 p-1 bg-light rounded-3" id="pills-tab" role="tablist">
                    <button class="nav-link active w-50" data-bs-toggle="pill" data-bs-target="#pills-camera" type="button"><i class="fas fa-camera me-2"></i>Camera</button>
                    <button class="nav-link w-50" data-bs-toggle="pill" data-bs-target="#pills-excel" type="button"><i class="fas fa-file-csv me-2"></i>Excel / CSV</button>
                </div>

                <div class="tab-content">
                    <div class="tab-pane fade show active" id="pills-camera">
                        <div class="file-upload-wrapper">
                            <i class="fas fa-cloud-upload-alt fa-3x text-primary mb-2"></i>
                            <p class="fw-bold mb-1 text-dark">Take a photo or choose a document</p>
                            <p class="text-muted small mb-0">Supports JPG, PNG, WebP formats</p>
                            <input type="file" id="camera_input" accept="image/*" capture="environment">
                        </div>
                        <div class="text-center">
                            <img id="image_preview" class="preview-img img-fluid">
                        </div>
                        <button type="button" onclick="startOCR()" class="btn btn-primary w-100 mt-4 py-3 fw-bold rounded-3 shadow-sm">
                            <i class="fas fa-expand me-2"></i> START SCANNING DOCUMENT
                        </button>
                    </div>
                    
                    <div class="tab-pane fade" id="pills-excel">
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="upload_excel" value="1">
                            <div class="file-upload-wrapper">
                                <i class="fas fa-file-csv fa-3x text-success mb-2"></i>
                                <p class="fw-bold mb-1 text-dark">Upload CSV File</p>
                                <p class="text-muted small mb-0">Ensure it is saved in .csv format</p>
                                <input type="file" name="excel_file" accept=".csv" required onchange="this.form.submit()">
                            </div>
                            <span class="text-muted small d-block mt-2 text-center"><i class="fas fa-info-circle me-1"></i>Once selected, the file will automatically upload and process.</span>
                        </form>
                    </div>
                </div>

                <div id="loading" class="loading text-center mt-4">
                    <div class="spinner-border text-primary mb-3" style="width: 3rem; height: 3rem;" role="status"></div>
                    <p class="mb-0 pulse-text"><i class="fas fa-brain me-1"></i> Gemini AI reading handwriting... Please wait</p>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <form action="save_olevel_marks.php" method="POST"> 
                <input type="hidden" name="subject_id" value="<?= htmlspecialchars($subject_id) ?>">
                <input type="hidden" name="class_name" value="<?= htmlspecialchars($class) ?>">
                <input type="hidden" name="stream" value="<?= htmlspecialchars($stream) ?>">
                <input type="hidden" name="year" value="<?= htmlspecialchars($year) ?>">
                <input type="hidden" name="exam_type" value="<?= htmlspecialchars($exam) ?>">
                
                <div class="card custom-card overflow-hidden">
                    <div class="card-header bg-white p-3 border-0 d-flex justify-content-between align-items-center border-bottom">
                        <h5 class="fw-bold mb-0 text-dark"><i class="fas fa-check-double text-success me-2"></i>Review & Confirm Marks</h5>
                        <span class="badge bg-light text-dark border px-3 py-2 rounded-pill fw-600">Final Step</span>
                    </div>
                    
                    <div class="table-responsive" style="max-height: 520px; overflow-y: auto;">
                        <table class="table align-middle" id="resultTable">
                            <thead>
                                <tr>
                                    <th>Student Found (Database)</th>
                                    <th width="160" class="text-center">Updated Mark</th>
                                </tr>
                            </thead>
                            <tbody id="preview_body">
                                <?php if(!empty($processed_data)): ?>
                                    <?php foreach($processed_data as $data): ?>
                                        <tr>
                                            <td>
                                                <input type="hidden" name="std_id[]" value="<?= $data['id'] ?>">
                                                <div class="d-flex align-items-center">
                                                    <div class="p-2 bg-success bg-opacity-10 text-success rounded-circle me-3 d-none d-sm-block" style="width:36px; height:36px; display:flex; align-items:center; justify-content:center;">
                                                        <i class="fas fa-user-check"></i>
                                                    </div>
                                                    <div>
                                                        <strong class="text-dark d-block"><?= htmlspecialchars($data['name']) ?></strong>
                                                        <span class="text-muted small"><i class="fas fa-file-import me-1"></i>From CSV file</span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <input type="number" step="0.1" name="p1[]" class="form-control mark-input mx-auto" value="<?= htmlspecialchars($data['mark']) ?>">
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="2" class="text-center py-5 text-muted">
                                            <div class="py-4">
                                                <i class="fas fa-camera-retro fa-3x mb-3 text-light-emphasis"></i>
                                                <p class="mb-0 fw-500">No marks processed yet.</p>
                                                <small>Use the Camera on the left or upload a CSV to see results here.</small>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="card-footer bg-light border-0 text-end p-3 border-top">
                        <button type="submit" class="btn btn-success px-5 py-3 fw-bold rounded-pill shadow-sm">
                            <i class="fas fa-save me-2"></i> SAVE RESULTS TO DATABASE
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Pass student list from PHP to a JavaScript array
const studentsDb = <?php echo json_encode($students_db); ?>;

// JavaScript Fuzzy Matching Logic
function findBestMatch(name) {
    let bestMatch = null;
    let highestScore = 0;
    studentsDb.forEach(s => {
        let score = similarity(name.toUpperCase(), s.fullname.toUpperCase());
        if(score > highestScore) {
            highestScore = score;
            bestMatch = s;
        }
    });
    return (highestScore > 0.72) ? bestMatch : null;
}

function similarity(s1, s2) {
    var longer = s1, shorter = s2;
    if (s1.length < s2.length) { longer = s2; shorter = s1; }
    var longerLength = longer.length;
    if (longerLength == 0) { return 1.0; }
    return (longerLength - editDistance(longer, shorter)) / parseFloat(longerLength);
}

function editDistance(s1, s2) {
    s1 = s1.toLowerCase(); s2 = s2.toLowerCase();
    var costs = new Array();
    for (var i = 0; i <= s1.length; i++) {
        var lastValue = i;
        for (var j = 0; j <= s2.length; j++) {
            if (i == 0) costs[j] = j;
            else {
                if (j > 0) {
                    var newValue = costs[j - 1];
                    if (s1.charAt(i - 1) != s2.charAt(j - 1))
                        newValue = Math.min(Math.min(newValue, lastValue), costs[j]) + 1;
                    costs[j - 1] = lastValue;
                    var lastValue = newValue;
                }
            }
        }
        if (i > 0) costs[s2.length] = lastValue;
    }
    return costs[s2.length];
}

// Asynchronously route the image directly to our Gemini AI backend handler
async function startOCR() {
    const fileInput = document.getElementById('camera_input');
    if (fileInput.files.length === 0) {
        alert("Please take a photo or select a document file first.");
        return;
    }

    document.getElementById('loading').style.display = 'block';
    const image = fileInput.files[0];
    
    // Package image inside a FormData multi-part container
    const formData = new FormData();
    formData.append('ocr_image', image);

    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            processScannedText(result.text);
        } else {
            alert("AI OCR Processing Error: " + result.error);
        }
    } catch (err) {
        alert("Connection Error: Could not reach the scanning server. Check network performance.");
    }
    document.getElementById('loading').style.display = 'none';
}

function processScannedText(text) {
    const lines = text.split('\n');
    const tbody = document.getElementById('preview_body');
    const logic = document.getElementById('logic_mode').value;
    tbody.innerHTML = '';
    let matchesFound = 0;

    lines.forEach(line => {
        // Matches pattern: Any clean string of name text followed by a numeric mark
        const match = line.match(/([A-Za-z\s\.\-]+)\s+(\d+)/);
        if (match) {
            let rawName = match[1].trim();
            let rawScore = parseFloat(match[2]);
            
            if(logic === 'convert') {
                rawScore = Math.round((rawScore / 100) * 60);
            }

            const student = findBestMatch(rawName);
            if(student) {
                matchesFound++;
                const row = `
                    <tr>
                        <td>
                            <input type="hidden" name="std_id[]" value="${student.id}">
                            <div class="d-flex align-items-center">
                                <div class="p-2 bg-primary bg-opacity-10 text-primary rounded-circle me-3 d-none d-sm-block" style="width:36px; height:36px; display:flex; align-items:center; justify-content:center;">
                                    <i class="fas fa-user"></i>
                                </div>
                                <div>
                                    <strong class="text-dark d-block">${student.fullname}</strong>
                                    <span class="text-muted small"><i class="fas fa-eye me-1"></i>Detected as: "${rawName}"</span>
                                </div>
                            </div>
                        </td>
                        <td>
                            <input type="number" step="0.1" name="p1[]" class="form-control mark-input mx-auto" value="${rawScore}">
                        </td>
                    </tr>`;
                tbody.innerHTML += row;
            }
        }
    });

    if(matchesFound === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="2" class="text-center py-5 text-danger">
                    <i class="fas fa-unlink fa-2x mb-2"></i>
                    <p class="mb-0 fw-bold">No matching student matches found in our system records.</p>
                    <small class="text-muted">Ensure the sheet has proper light exposure or execute corrections via Excel CSV upload option.</small>
                </td>
            </tr>`;
    }
}

// Show image preview as soon as the teacher takes a photo
document.getElementById('camera_input').onchange = function (evt) {
    const tgt = evt.target || window.event.srcElement, files = tgt.files;
    if (FileReader && files && files.length) {
        const fr = new FileReader();
        fr.onload = function () {
            document.getElementById('image_preview').src = fr.result;
            document.getElementById('image_preview').style.display = 'block';
        }
        fr.readAsDataURL(files[0]);
    }
}
</script>
</body>
</html>