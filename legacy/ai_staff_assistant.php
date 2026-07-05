<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$role = strtolower((string)($_SESSION['role'] ?? ''));
$allowedRoles = ['admin', 'teacher', 'class teacher', 'accountant'];
if (!in_array($role, $allowedRoles, true)) {
    header('Location: index.php?error=staff_only');
    exit();
}

$backUrl = 'teacher_dashboard.php';
if ($role === 'admin') {
    $backUrl = 'admin_dashboard.php';
} elseif ($role === 'accountant') {
    $backUrl = 'Accountant.php';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SYSTEM Staff AI Assistant</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            min-height: 100vh;
            background: radial-gradient(circle at 10% 10%, rgba(59,130,246,0.12) 0, transparent 35%),
                        radial-gradient(circle at 90% 8%, rgba(20,184,166,0.12) 0, transparent 30%),
                        #eef2f7;
        }
        .hero {
            max-width: 1000px;
            margin: 22px auto;
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid #dbe4f0;
            border-radius: 22px;
            box-shadow: 0 12px 35px rgba(15, 23, 42, 0.08);
            padding: 24px;
        }
        .guide-grid {
            display: grid;
            grid-template-columns: 1.3fr 1fr;
            gap: 16px;
            margin-top: 16px;
        }
        .guide-card {
            background: #ffffff;
            border: 1px solid #dbe4f0;
            border-radius: 16px;
            padding: 14px;
        }
        .guide-item {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 12px;
            margin-bottom: 10px;
            background: #f8fafc;
        }
        .guide-item:last-child { margin-bottom: 0; }
        .guide-item h6 {
            font-size: 14px;
            margin-bottom: 6px;
        }
        .guide-item ol {
            margin-bottom: 8px;
            padding-left: 18px;
            color: #334155;
            font-size: 13px;
        }
        .mini-chip {
            display: inline-block;
            border: 1px solid #cbd5e1;
            border-radius: 999px;
            padding: 2px 8px;
            font-size: 11px;
            color: #334155;
            background: #fff;
            margin: 2px 6px 0 0;
        }
        @media (max-width: 992px) {
            .guide-grid { grid-template-columns: 1fr; }
        }
        .floating-toggle {
            position: fixed;
            right: 22px;
            bottom: 22px;
            width: 62px;
            height: 62px;
            border-radius: 50%;
            border: none;
            background: linear-gradient(135deg, #1d4ed8, #2563eb);
            color: #fff;
            box-shadow: 0 14px 30px rgba(37, 99, 235, 0.35);
            z-index: 1100;
        }
        .chat-card {
            position: fixed;
            right: 22px;
            bottom: 96px;
            width: min(430px, calc(100vw - 20px));
            height: min(620px, calc(100vh - 140px));
            background: #fff;
            border: 1px solid #dbe4f0;
            border-radius: 18px;
            box-shadow: 0 16px 36px rgba(15, 23, 42, 0.18);
            overflow: hidden;
            display: none;
            z-index: 1099;
        }
        .chat-head {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #fff;
            padding: 12px 14px;
        }
        .chat-body {
            padding: 12px;
            height: calc(100% - 132px);
            overflow: auto;
            background: #f8fafc;
        }
        .chat-foot {
            border-top: 1px solid #e2e8f0;
            padding: 10px;
            background: #fff;
        }
        .bubble {
            max-width: 86%;
            border-radius: 14px;
            padding: 10px 12px;
            margin-bottom: 10px;
            border: 1px solid transparent;
            white-space: pre-wrap;
        }
        .b-user { margin-left: auto; background: #dbeafe; border-color: #93c5fd; }
        .b-ai { margin-right: auto; background: #fff; border-color: #dbe4f0; }
        .chip {
            display: inline-block;
            font-size: 11px;
            border: 1px solid #cbd5e1;
            border-radius: 999px;
            padding: 3px 8px;
            margin: 2px 4px 0 0;
            color: #334155;
            background: #fff;
        }
        .confidence-badge {
            display: inline-block;
            font-size: 10px;
            font-weight: 700;
            border-radius: 999px;
            padding: 2px 8px;
            margin-bottom: 6px;
            border: 1px solid #cbd5e1;
            background: #f8fafc;
            color: #334155;
        }
        .action-btn {
            border: 1px solid #bfdbfe;
            background: #eff6ff;
            color: #1d4ed8;
            border-radius: 999px;
            font-size: 11px;
            padding: 3px 9px;
            margin: 2px 6px 0 0;
            cursor: pointer;
        }
    </style>
</head>
<body>

<div class="hero">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="fw-bold mb-1"><i class="fas fa-robot text-primary me-2"></i>In-App AI Assistant (Feature 5)</h4>
            <p class="text-muted mb-0">Ask staff workflow questions, e.g. "How do I upgrade students to next class?"</p>
        </div>
        <a href="<?= htmlspecialchars($backUrl) ?>" class="btn btn-outline-dark rounded-pill px-4">Back</a>
    </div>

    <div class="alert alert-info mb-0">
        Click the floating assistant button at bottom-right to chat.
    </div>

    <div class="guide-grid">
        <div class="guide-card">
            <h5 class="fw-bold mb-3"><i class="fas fa-book-open text-primary me-2"></i>User Manual Guide</h5>

            <div class="guide-item">
                <h6 class="fw-bold mb-2">1) Upload Students</h6>
                <ol>
                    <li>Open students.php.</li>
                    <li>Go to import_students.php.</li>
                    <li>Download template from download_template.php.</li>
                    <li>Fill and upload your file, then verify in students.php.</li>
                </ol>
                <span class="mini-chip">students.php</span>
                <span class="mini-chip">import_students.php</span>
                <span class="mini-chip">download_template.php</span>
            </div>

            <div class="guide-item">
                <h6 class="fw-bold mb-2">2) Enter Results</h6>
                <ol>
                    <li>Open result.php and select level.</li>
                    <li>Use marks entry page for that level.</li>
                    <li>Select term/year correctly and save marks.</li>
                    <li>Verify output from broadsheet or sheet page.</li>
                </ol>
                <span class="mini-chip">result.php</span>
                <span class="mini-chip">marks_entry.php</span>
                <span class="mini-chip">marks_entry_olevel.php</span>
                <span class="mini-chip">marks_entry_alevel.php</span>
            </div>

            <div class="guide-item">
                <h6 class="fw-bold mb-2">3) Payments & Receipts</h6>
                <ol>
                    <li>Record payment in make_payment.php.</li>
                    <li>Review receipts in payment_list.php.</li>
                    <li>Use student_payment_lookup.php for one student statement.</li>
                </ol>
                <span class="mini-chip">make_payment.php</span>
                <span class="mini-chip">payment_list.php</span>
                <span class="mini-chip">student_payment_lookup.php</span>
            </div>
        </div>

        <div class="guide-card">
            <h6 class="fw-bold mb-2"><i class="fas fa-lightbulb text-warning me-2"></i>How To Use Chat Better</h6>
            <ul class="mb-3 text-muted" style="font-size: 14px; padding-left: 18px;">
                <li>Ask one task at a time.</li>
                <li>Mention page name if possible.</li>
                <li>Include class, term, and year for result questions.</li>
                <li>Use "upload students" for admissions/import workflow.</li>
            </ul>

            <h6 class="fw-bold mb-2">Quick Prompts</h6>
            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-sm btn-outline-primary rounded-pill quick-prompt" data-prompt="How do I upload students using template?">Upload students</button>
                <button type="button" class="btn btn-sm btn-outline-primary rounded-pill quick-prompt" data-prompt="How do I enter O-Level marks for Term 2?">Enter O-Level marks</button>
                <button type="button" class="btn btn-sm btn-outline-primary rounded-pill quick-prompt" data-prompt="How do I print one student payment statement?">Print payment statement</button>
            </div>
            <small class="d-block text-muted mt-3">Tip: click a quick prompt, then send it in chat.</small>
        </div>
    </div>
</div>

<button class="floating-toggle" id="assistantToggle" title="Open AI Assistant">
    <i class="fas fa-comments fa-lg"></i>
</button>

<div class="chat-card" id="chatCard">
    <div class="chat-head d-flex justify-content-between align-items-center">
        <div>
            <div class="fw-bold">SYSTEM Assistant</div>
            <small class="opacity-75">Staff help and system guidance</small>
        </div>
        <button class="btn btn-sm btn-light rounded-pill" id="closeChat"><i class="fas fa-xmark"></i></button>
    </div>

    <div class="chat-body" id="chatBody">
        <div class="bubble b-ai">Habari <?= htmlspecialchars((string)($_SESSION['fullname'] ?? 'Staff')) ?>. Niulize chochote kuhusu kutumia mfumo, nitakupa hatua kwa hatua.</div>
    </div>

    <div class="chat-foot">
        <form id="chatForm" class="d-flex gap-2">
            <input type="text" id="chatInput" class="form-control" placeholder="Ask: How do I save marks for Term 2?" required>
            <button class="btn btn-primary rounded-pill px-3" id="sendBtn"><i class="fas fa-paper-plane"></i></button>
        </form>
        <small class="text-muted">Assistant can suggest relevant pages in the system.</small>
    </div>
</div>

<script>
const toggleBtn = document.getElementById('assistantToggle');
const chatCard = document.getElementById('chatCard');
const closeChat = document.getElementById('closeChat');
const chatBody = document.getElementById('chatBody');
const chatForm = document.getElementById('chatForm');
const chatInput = document.getElementById('chatInput');
const sendBtn = document.getElementById('sendBtn');
const promptButtons = document.querySelectorAll('.quick-prompt');

function addBubble(text, type = 'ai', chips = [], meta = {}) {
    const div = document.createElement('div');
    div.className = 'bubble ' + (type === 'user' ? 'b-user' : 'b-ai');

    if (type === 'ai' && meta && meta.confidence) {
        const badge = document.createElement('div');
        badge.className = 'confidence-badge';
        badge.textContent = 'Confidence: ' + meta.confidence;
        div.appendChild(badge);
    }

    const textNode = document.createElement('div');
    textNode.textContent = text;
    div.appendChild(textNode);
    chatBody.appendChild(div);

    if (Array.isArray(chips) && chips.length > 0) {
        const wrap = document.createElement('div');
        wrap.style.marginBottom = '8px';
        chips.forEach((c) => {
            const s = document.createElement('span');
            s.className = 'chip';
            s.textContent = c;
            wrap.appendChild(s);
        });
        chatBody.appendChild(wrap);
    }

    if (type === 'ai' && meta && Array.isArray(meta.actions) && meta.actions.length > 0) {
        const actionWrap = document.createElement('div');
        actionWrap.style.marginBottom = '8px';

        meta.actions.forEach((a) => {
            const page = (a && a.page) ? String(a.page) : '';
            if (!page) return;

            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'action-btn';
            btn.textContent = (a && a.label) ? String(a.label) : ('Open ' + page);
            btn.addEventListener('click', () => {
                window.location.href = page;
            });
            actionWrap.appendChild(btn);
        });

        if (actionWrap.childElementCount > 0) {
            chatBody.appendChild(actionWrap);
        }
    }

    chatBody.scrollTop = chatBody.scrollHeight;
}

function setLoading(isLoading) {
    chatInput.disabled = isLoading;
    sendBtn.disabled = isLoading;
}

toggleBtn.addEventListener('click', () => {
    chatCard.style.display = 'block';
    chatInput.focus();
});

closeChat.addEventListener('click', () => {
    chatCard.style.display = 'none';
});

promptButtons.forEach((btn) => {
    btn.addEventListener('click', () => {
        const text = btn.getAttribute('data-prompt') || '';
        if (text) {
            chatInput.value = text;
            chatCard.style.display = 'block';
            chatInput.focus();
        }
    });
});

chatForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const q = chatInput.value.trim();
    if (!q) return;

    addBubble(q, 'user');
    chatInput.value = '';
    setLoading(true);

    try {
        const res = await fetch('ai_staff_assistant_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                question: q,
                page_context: window.location.pathname.split('/').pop()
            })
        });
        const data = await res.json();
        if (!data.ok) {
            addBubble('Samahani, imeshindikana sasa hivi: ' + (data.error || 'Unknown error'));
        } else {
            addBubble(
                data.answer || 'Nimekuelewa. Tafadhali eleza kwa detail zaidi.',
                'ai',
                data.suggested_pages || [],
                {
                    confidence: data.confidence || '',
                    actions: data.suggested_actions || []
                }
            );
        }
    } catch (err) {
        addBubble('Network error: Tafadhali jaribu tena baada ya muda mfupi.');
    } finally {
        setLoading(false);
        chatInput.focus();
    }
});
</script>

</body>
</html>
