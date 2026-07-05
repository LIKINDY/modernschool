<?php
session_start();
include('db_config.php');
include('activity_logger.php');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: index.php");
    exit();
}

$school = $conn->query("SELECT logo FROM school_info LIMIT 1")->fetch_assoc();
$favicon = 'https://cdn-icons-png.flaticon.com/512/3059/3059518.png';
if (!empty($school['logo']) && file_exists('uploads/logo/' . $school['logo'])) {
    $favicon = 'uploads/logo/' . $school['logo'];
}

$students_res = $conn->query("SELECT fullname, phone FROM students WHERE phone IS NOT NULL AND phone != '' ORDER BY fullname ASC");
$staff_res = $conn->query("SELECT fullname, phone FROM accountants WHERE phone IS NOT NULL AND phone != '' ORDER BY fullname ASC");

$students = [];
while ($row = $students_res->fetch_assoc()) {
    $students[] = $row;
}

$staff = [];
while ($row = $staff_res->fetch_assoc()) {
    $staff[] = $row;
}

$total_parents = count($students);
$total_staff = count($staff);

$parentPhones = [];
foreach ($students as $s) {
    $phone = preg_replace('/\s+/', '', (string) ($s['phone'] ?? ''));
    if ($phone !== '') {
        $parentPhones[] = $phone;
    }
}

$staffPhones = [];
foreach ($staff as $f) {
    $phone = preg_replace('/\s+/', '', (string) ($f['phone'] ?? ''));
    if ($phone !== '') {
        $staffPhones[] = $phone;
    }
}

$feedbackType = '';
$feedbackMessage = '';
$phoneSmsLink = '';

if (isset($_POST['send_broadcast'])) {
    $target = $_POST['target_group'] ?? 'Parents';
    $deliveryMode = $_POST['delivery_mode'] ?? 'api';
    $messageRaw = trim($_POST['message'] ?? '');
    $message = mysqli_real_escape_string($conn, $messageRaw);
    $whatsappGroupLink = trim($_POST['whatsapp_group_link'] ?? '');

    $apiProvider = trim($_POST['api_provider'] ?? '');
    $apiEndpoint = trim($_POST['api_endpoint'] ?? '');
    $apiSenderId = trim($_POST['api_sender_id'] ?? '');
    $apiKey = trim($_POST['api_key'] ?? '');

    $recipientCount = $target === 'Staff' ? $total_staff : $total_parents;
    $selectedPhones = $target === 'Staff' ? $staffPhones : $parentPhones;

    if ($messageRaw === '') {
        $feedbackType = 'danger';
        $feedbackMessage = 'Message cannot be empty.';
    } elseif ($recipientCount < 1) {
        $feedbackType = 'warning';
        $feedbackMessage = 'Hakuna wapokeaji kwenye kundi ulilochagua.';
    } else {
        if ($deliveryMode === 'api') {
            if ($apiEndpoint === '' || $apiKey === '') {
                $feedbackType = 'warning';
                $feedbackMessage = 'API mode selected. Jaza API Endpoint na API Key kwanza, kisha integration itafanya kazi ya kutuma.';
            } else {
                $feedbackType = 'info';
                $feedbackMessage = 'API imewekwa. Hapa ni integration point tayari kwa kuunganisha provider wa SMS.';
            }
        } elseif ($deliveryMode === 'free') {
            $feedbackType = 'warning';
            $feedbackMessage = 'Free public SMS gateways si za kuaminika kwa production. Tumia API provider au Phone SMS mode.';
        } else {
            $numbers = implode(',', $selectedPhones);
            $phoneSmsLink = 'sms:' . rawurlencode($numbers) . '?body=' . rawurlencode($messageRaw);
            $feedbackType = 'success';
            $feedbackMessage = 'Phone SMS mode ready. Bonyeza "Send Using My Phone" kufungua app ya SMS kwenye simu.';
        }

        log_system_activity($conn, [
            'user_id' => $_SESSION['user_id'] ?? null,
            'fullname' => $_SESSION['fullname'] ?? null,
            'username' => $_SESSION['username'] ?? null,
            'role' => $_SESSION['role'] ?? 'admin',
            'activity_type' => 'sms_broadcast',
            'activity' => 'SMS broadcast prepared for ' . $target . ' via ' . strtoupper($deliveryMode),
            'status' => 'success',
            'metadata' => [
                'target_group' => $target,
                'delivery_mode' => $deliveryMode,
                'recipient_count' => $recipientCount,
                'message_length' => strlen($message),
                'api_provider' => $apiProvider,
                'api_endpoint_set' => $apiEndpoint !== '',
                'api_sender_id' => $apiSenderId,
                'api_key_set' => $apiKey !== '',
                'whatsapp_link' => $whatsappGroupLink
            ]
        ]);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart SMS Center | Likindy Digital</title>
    <link rel="icon" type="image/png" href="<?= htmlspecialchars($favicon) ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --ink: #0f172a;
            --muted: #64748b;
            --brand-1: #0ea5e9;
            --brand-2: #14b8a6;
            --brand-3: #f59e0b;
            --surface: #ffffff;
            --line: #e2e8f0;
            --bg: #eaf4ff;
        }

        body {
            margin: 0;
            font-family: 'Manrope', sans-serif;
            color: var(--ink);
            background:
                radial-gradient(circle at 0% 0%, rgba(20, 184, 166, 0.15) 0%, rgba(20, 184, 166, 0) 38%),
                radial-gradient(circle at 100% 10%, rgba(245, 158, 11, 0.18) 0%, rgba(245, 158, 11, 0) 35%),
                linear-gradient(180deg, #f8fbff 0%, var(--bg) 100%);
        }

        .shell-card {
            border: 1px solid rgba(255,255,255,0.65);
            background: rgba(255,255,255,0.84);
            border-radius: 22px;
            box-shadow: 0 20px 40px rgba(15, 23, 42, 0.08);
            backdrop-filter: blur(8px);
        }

        .hero-ribbon {
            background: linear-gradient(120deg, #0ea5e9 0%, #14b8a6 45%, #f59e0b 100%);
            border-radius: 18px;
            color: #fff;
            position: relative;
            overflow: hidden;
        }

        .hero-ribbon::before {
            content: "";
            position: absolute;
            width: 260px;
            height: 260px;
            right: -90px;
            top: -120px;
            border-radius: 50%;
            background: rgba(255,255,255,0.17);
        }

        .hero-icon {
            width: 66px;
            height: 66px;
            border-radius: 18px;
            background: rgba(255,255,255,0.2);
            display: grid;
            place-items: center;
            font-size: 26px;
        }

        .field-label {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: var(--muted);
            font-weight: 700;
            margin-bottom: 6px;
        }

        .form-control,
        .form-select {
            border-radius: 14px;
            border: 1.5px solid var(--line);
            padding: 12px 14px;
            box-shadow: none;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #22c55e;
            box-shadow: 0 0 0 4px rgba(34, 197, 94, 0.13);
        }

        .target-box {
            border: 1.5px solid #dbeafe;
            background: #f8fbff;
            border-radius: 16px;
            padding: 14px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .target-box:hover {
            transform: translateY(-2px);
            border-color: #7dd3fc;
        }

        .btn-check:checked + .target-box {
            border-color: #0ea5e9;
            background: #e0f2fe;
            box-shadow: 0 12px 24px rgba(14, 165, 233, 0.15);
        }

        .delivery-card {
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 12px;
            background: #fff;
        }

        .send-btn {
            border: none;
            border-radius: 14px;
            padding: 14px;
            font-weight: 800;
            letter-spacing: 0.4px;
            background: linear-gradient(120deg, #0284c7, #0d9488);
            color: #fff;
        }

        .send-btn:hover { filter: brightness(1.05); }

        .list-box {
            max-height: 370px;
            overflow: auto;
            border-radius: 16px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 10px;
        }

        .list-box::-webkit-scrollbar { width: 7px; }
        .list-box::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 20px; }

        .badge-role {
            background: #e0f2fe;
            color: #075985;
            border-radius: 12px;
            padding: 4px 8px;
            font-size: 11px;
            font-weight: 700;
        }

        .phone-helper {
            border: 1px dashed #14b8a6;
            background: #f0fdfa;
            border-radius: 14px;
            padding: 12px;
        }

        @media (max-width: 992px) {
            .left-col { order: 1; }
            .right-col { order: 2; }
        }
    </style>
</head>
<body>

<div class="container py-4 py-lg-5">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <a href="admin_dashboard.php" class="btn btn-light border rounded-pill px-4 shadow-sm">
            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
        </a>
        <div class="d-flex gap-2">
            <a href="system_logs.php" class="btn btn-outline-dark rounded-pill px-4"><i class="fas fa-list-check me-2"></i>System Logs</a>
            <button onclick="window.print()" class="btn btn-outline-secondary rounded-pill px-4"><i class="fas fa-print me-2"></i>Print</button>
        </div>
    </div>

    <div class="hero-ribbon p-4 p-lg-5 mb-4">
        <div class="d-flex align-items-center gap-3 position-relative" style="z-index:1;">
            <div class="hero-icon"><i class="fas fa-bullhorn"></i></div>
            <div>
                <h3 class="mb-1 fw-bold">Smart Messaging Hub <span class="ms-1">📨</span></h3>
                <p class="mb-0 opacity-75">SMS API integration ready, Phone SMS fallback ready, WhatsApp group quick-link ready.</p>
            </div>
        </div>
    </div>

    <?php if ($feedbackMessage !== ''): ?>
        <div class="alert alert-<?= htmlspecialchars($feedbackType) ?> rounded-4 shadow-sm border-0">
            <i class="fas fa-circle-info me-2"></i><?= htmlspecialchars($feedbackMessage) ?>
        </div>
    <?php endif; ?>

    <?php if ($phoneSmsLink !== ''): ?>
        <div class="phone-helper mb-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <div class="fw-bold"><i class="fas fa-mobile-screen-button me-2"></i>Phone SMS ready</div>
                    <small class="text-muted">Ukifungua link hii kwenye simu, itajaza ujumbe tayari kwenye app ya SMS.</small>
                </div>
                <a href="<?= htmlspecialchars($phoneSmsLink) ?>" class="btn btn-success rounded-pill px-4">
                    <i class="fas fa-paper-plane me-2"></i>Send Using My Phone
                </a>
            </div>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-xl-7 left-col">
            <div class="shell-card p-4 p-lg-5">
                <div class="d-flex align-items-center justify-content-between mb-4">
                    <h5 class="fw-bold mb-0"><i class="fas fa-pen-to-square me-2 text-primary"></i>Create Broadcast</h5>
                    <span class="badge text-bg-light border px-3 py-2 rounded-pill">Modern UI</span>
                </div>

                <form method="POST" id="broadcastForm">
                    <div class="mb-4">
                        <div class="field-label">1. Audience</div>
                        <div class="row g-3">
                            <div class="col-6">
                                <input type="radio" class="btn-check" name="target_group" id="targetParents" value="Parents" checked>
                                <label class="target-box w-100" for="targetParents">
                                    <div class="d-flex align-items-center gap-2 mb-2"><i class="fas fa-people-roof text-primary"></i><strong>Parents</strong></div>
                                    <small class="text-muted"><?= number_format($total_parents) ?> recipients</small>
                                </label>
                            </div>
                            <div class="col-6">
                                <input type="radio" class="btn-check" name="target_group" id="targetStaff" value="Staff">
                                <label class="target-box w-100" for="targetStaff">
                                    <div class="d-flex align-items-center gap-2 mb-2"><i class="fas fa-user-tie text-success"></i><strong>Staff</strong></div>
                                    <small class="text-muted"><?= number_format($total_staff) ?> recipients</small>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <div class="field-label">2. Delivery Mode</div>
                        <div class="row g-2">
                            <div class="col-md-4">
                                <label class="delivery-card w-100">
                                    <input class="form-check-input me-2" type="radio" name="delivery_mode" value="api" checked onclick="toggleApiFields()">
                                    <i class="fas fa-plug text-info me-1"></i> SMS API
                                </label>
                            </div>
                            <div class="col-md-4">
                                <label class="delivery-card w-100">
                                    <input class="form-check-input me-2" type="radio" name="delivery_mode" value="free" onclick="toggleApiFields()">
                                    <i class="fas fa-gift text-warning me-1"></i> Free Trial
                                </label>
                            </div>
                            <div class="col-md-4">
                                <label class="delivery-card w-100">
                                    <input class="form-check-input me-2" type="radio" name="delivery_mode" value="phone" onclick="toggleApiFields()">
                                    <i class="fas fa-mobile-alt text-success me-1"></i> My Phone SMS
                                </label>
                            </div>
                        </div>
                        <small class="text-muted d-block mt-2">Note: Hakuna free SMS IP ya kuaminika kwa production, ndio maana API na Phone mode zipo kama options salama.</small>
                    </div>

                    <div id="apiConfigBox" class="mb-4">
                        <div class="field-label">3. API Integration Settings</div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <input type="text" class="form-control" name="api_provider" placeholder="Provider (Twilio, Africa's Talking, n.k.)">
                            </div>
                            <div class="col-md-6">
                                <input type="text" class="form-control" name="api_sender_id" placeholder="Sender ID (Optional)">
                            </div>
                            <div class="col-12">
                                <input type="url" class="form-control" name="api_endpoint" placeholder="API Endpoint URL">
                            </div>
                            <div class="col-12">
                                <input type="text" class="form-control" name="api_key" placeholder="API Key / Token (integration placeholder)">
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <div class="field-label">4. WhatsApp Group Link</div>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fab fa-whatsapp text-success"></i></span>
                            <input type="url" name="whatsapp_group_link" id="whatsappLink" class="form-control" placeholder="https://chat.whatsapp.com/...">
                            <a href="#" class="btn btn-outline-success" id="openWhatsappBtn" target="_blank">Open</a>
                        </div>
                        <small class="text-muted">Hii ni quick link tu kwa sasa kama ulivyotaka.</small>
                    </div>

                    <div class="mb-4">
                        <div class="field-label">5. Message</div>
                        <textarea id="msg_box" name="message" class="form-control" rows="6" maxlength="1000" placeholder="Andika ujumbe wako hapa..." required></textarea>
                        <div class="d-flex justify-content-between align-items-center mt-2">
                            <small id="charCount" class="text-muted fw-semibold">0 / 1000 Characters</small>
                            <small class="text-muted"><i class="fas fa-shield-heart me-1"></i>Confirmation required before send</small>
                        </div>
                    </div>

                    <button type="button" onclick="openConfirmModal()" class="send-btn w-100">
                        <i class="fas fa-paper-plane me-2"></i>Review & Confirm Send
                    </button>

                    <button type="submit" name="send_broadcast" id="realSubmitBtn" class="d-none">Submit</button>
                </form>
            </div>
        </div>

        <div class="col-xl-5 right-col">
            <div class="shell-card p-4 h-100">
                <h5 class="fw-bold mb-3"><i class="fas fa-address-book text-primary me-2"></i>Live Directory</h5>
                <ul class="nav nav-pills mb-3" role="tablist">
                    <li class="nav-item w-50" role="presentation">
                        <button class="nav-link active w-100" data-bs-toggle="tab" data-bs-target="#parentsPane" type="button">
                            <i class="fas fa-users me-1"></i> Parents
                        </button>
                    </li>
                    <li class="nav-item w-50" role="presentation">
                        <button class="nav-link w-100" data-bs-toggle="tab" data-bs-target="#staffPane" type="button">
                            <i class="fas fa-user-shield me-1"></i> Staff
                        </button>
                    </li>
                </ul>

                <div class="tab-content">
                    <div class="tab-pane fade show active" id="parentsPane">
                        <div class="list-box">
                            <table class="table table-sm align-middle mb-0">
                                <tbody>
                                <?php if ($total_parents > 0): ?>
                                    <?php foreach ($students as $s): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-semibold small"><?= htmlspecialchars($s['fullname']) ?></div>
                                                <span class="badge-role">Parent</span>
                                            </td>
                                            <td class="text-end small text-primary fw-bold"><?= htmlspecialchars($s['phone']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td class="text-center py-4 text-muted">Hakuna data ya wazazi.</td></tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="staffPane">
                        <div class="list-box">
                            <table class="table table-sm align-middle mb-0">
                                <tbody>
                                <?php if ($total_staff > 0): ?>
                                    <?php foreach ($staff as $f): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-semibold small"><?= htmlspecialchars($f['fullname']) ?></div>
                                                <span class="badge-role">Staff</span>
                                            </td>
                                            <td class="text-end small text-success fw-bold"><?= htmlspecialchars($f['phone']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td class="text-center py-4 text-muted">Hakuna data ya staff.</td></tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="mt-3 pt-3 border-top">
                    <small class="text-muted d-block"><i class="fas fa-lightbulb me-1 text-warning"></i>Tip: Kwa simu, chagua My Phone SMS mode ili utume bila kulipia gateway.</small>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="confirmSendModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="fas fa-circle-check text-success me-2"></i>Confirm Broadcast</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2"><strong>Target:</strong> <span id="confirmTarget">-</span></p>
                <p class="mb-2"><strong>Mode:</strong> <span id="confirmMode">-</span></p>
                <p class="mb-2"><strong>Recipients:</strong> <span id="confirmRecipients">0</span></p>
                <p class="mb-3"><strong>WhatsApp Link:</strong> <span id="confirmWhatsapp">Not set</span></p>
                <div class="p-3 bg-light rounded-3 small" id="confirmMessagePreview"></div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary rounded-pill px-4" onclick="submitBroadcast()">
                    <i class="fas fa-paper-plane me-2"></i>Yes, Send
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const parentCount = <?= (int) $total_parents ?>;
    const staffCount = <?= (int) $total_staff ?>;

    const form = document.getElementById('broadcastForm');
    const textarea = document.getElementById('msg_box');
    const countDisplay = document.getElementById('charCount');
    const apiConfigBox = document.getElementById('apiConfigBox');
    const whatsappInput = document.getElementById('whatsappLink');
    const openWhatsappBtn = document.getElementById('openWhatsappBtn');
    const modal = new bootstrap.Modal(document.getElementById('confirmSendModal'));

    function currentMode() {
        const checked = document.querySelector('input[name="delivery_mode"]:checked');
        return checked ? checked.value : 'api';
    }

    function currentTarget() {
        const checked = document.querySelector('input[name="target_group"]:checked');
        return checked ? checked.value : 'Parents';
    }

    function toggleApiFields() {
        apiConfigBox.style.display = currentMode() === 'api' ? 'block' : 'none';
    }

    textarea.addEventListener('input', () => {
        const len = textarea.value.length;
        countDisplay.textContent = `${len} / 1000 Characters`;
        countDisplay.classList.toggle('text-danger', len > 160);
    });

    whatsappInput.addEventListener('input', () => {
        const val = whatsappInput.value.trim();
        openWhatsappBtn.href = val !== '' ? val : '#';
    });

    function openConfirmModal() {
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        const target = currentTarget();
        const mode = currentMode();
        const recipients = target === 'Staff' ? staffCount : parentCount;
        const text = textarea.value.trim();
        const waLink = whatsappInput.value.trim();

        document.getElementById('confirmTarget').textContent = target;
        document.getElementById('confirmMode').textContent = mode.toUpperCase();
        document.getElementById('confirmRecipients').textContent = recipients;
        document.getElementById('confirmWhatsapp').textContent = waLink !== '' ? waLink : 'Not set';
        document.getElementById('confirmMessagePreview').textContent = text;

        modal.show();
    }

    function submitBroadcast() {
        document.getElementById('realSubmitBtn').click();
    }

    toggleApiFields();
</script>
</body>
</html>