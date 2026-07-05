<?php
session_start();
include('db_config.php');

$students = [];
if (isset($_GET['load_list'])) {
    $class = mysqli_real_escape_string($conn, $_GET['class']);
    $stream = mysqli_real_escape_string($conn, $_GET['stream']);
    $subject_id = mysqli_real_escape_string($conn, $_GET['subject']);
    $year = mysqli_real_escape_string($conn, $_GET['year']);
    $exam = mysqli_real_escape_string($conn, $_GET['exam_type']);

    $sql = "SELECT s.id, s.fullname, s.gender, 
                   m.monthly_mark, m.m2_mark, m.paper1_mark, m.paper2_mark, 
                   m.monthly_base, m.exam_base
            FROM students s 
            LEFT JOIN olevel_marks m ON s.id = m.student_id 
            AND m.subject_id = '$subject_id' 
            AND m.exam_type = '$exam'
            AND m.academic_year = '$year'
            WHERE s.class_name = '$class' AND s.stream = '$stream' 
            ORDER BY s.fullname ASC";
            
    $res = $conn->query($sql);
    if($res) { 
        while($row = $res->fetch_assoc()) { 
            $students[] = $row; 
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Advanced O-Level Entry | Likindy Digital Solution</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary-green: #059669; --dark-green: #064e3b; }
        body { background: #f8fafc; font-family: 'Inter', sans-serif; }
        .card { border: none; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .mark-input { width: 70px; text-align: center; font-weight: bold; border: 2px solid #e2e8f0; border-radius: 8px; transition: 0.3s; }
        .mark-input:focus { border-color: var(--primary-green); outline: none; background: #ecfdf5; box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.1); }
        .header-gradient { background: linear-gradient(135deg, var(--dark-green), var(--primary-green)); color: white; padding: 30px; border-radius: 15px; margin-bottom: 25px; }
        .total-preview { font-size: 0.85rem; color: #64748b; font-weight: 600; display: block; }
        .total-final { font-size: 1.1rem; font-weight: 900; color: #dc2626; }
        .grade-badge { padding: 6px 12px; border-radius: 8px; font-weight: 800; color: white; min-width: 45px; display: inline-block; text-center; }
        .g-A { background: #059669; } .g-B { background: #10b981; } .g-C { background: #f59e0b; } .g-D { background: #f97316; } .g-F { background: #ef4444; }
        .base-label { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; color: #64748b; }
        footer { background: white; padding: 20px 0; border-top: 1px solid #e2e8f0; margin-top: 50px; }
    </style>
</head>
<body onload="checkExamLogic()">

<div class="container-fluid py-4 px-4">
    <div class="header-gradient d-flex justify-content-between align-items-center shadow">
        <div>
            <h2 class="mb-1"><i class="fas fa-graduation-cap"></i> O-Level Results Portal</h2>
            <p class="mb-0 opacity-75">Secondary School Marks Management System</p>
        </div>
        <div class="d-flex gap-2">
            <a href="olevel_view_results.php" class="btn btn-warning fw-bold px-4 rounded-pill shadow-sm"><i class="fas fa-eye me-2"></i> VIEW SUMMARY CLASS LIST</a>
            <a href="olevel_result.php" class="btn btn-light fw-bold px-4 rounded-pill shadow-sm"><i class="fas fa-arrow-left me-2"></i> BACK TO DASHBOARD</a>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body p-4">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label fw-bold small">SUBJECT</label>
                    <select name="subject" id="sub_id" class="form-select border-2" required onchange="checkExamLogic()">
                        <option value="">-- Select Subject --</option>
                        <?php 
                        $sub_q = $conn->query("SELECT * FROM olevel_subjects");
                        while($s = $sub_q->fetch_assoc()){
                            $sel = (@$_GET['subject'] == $s['id']) ? 'selected' : '';
                            echo "<option value='{$s['id']}' data-p2='{$s['has_paper2']}' data-name='{$s['subject_name']}' $sel>{$s['subject_name']}</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold small">CLASS</label>
                    <select name="class" id="class_name" class="form-select border-2">
                        <?php foreach(['Form 1','Form 2','Form 3','Form 4'] as $c) echo "<option value='$c' ".(@$_GET['class']==$c?'selected':'').">$c</option>"; ?>
                    </select>
                </div>
                <div class="col-md-1">
                    <label class="form-label fw-bold small">STREAM</label>
                    <select name="stream" id="stream_id" class="form-select border-2">
                        <?php foreach(range('A','M') as $l) echo "<option value='$l' ".(@$_GET['stream']==$l?'selected':'').">$l</option>"; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold small">EXAM TYPE</label>
                    <select name="exam_type" id="exam_type" class="form-select border-2" onchange="checkExamLogic()">
                        <option value="Term 1" <?= (@$_GET['exam_type']=='Term 1'?'selected':'') ?>>Term 1</option>
                        <option value="Term 2" <?= (@$_GET['exam_type']=='Term 2'?'selected':'') ?>>Term 2</option>
                        <option value="Terminal" <?= (@$_GET['exam_type']=='Terminal'?'selected':'') ?>>Terminal (100%)</option>
                        <option value="Annual" <?= (@$_GET['exam_type']=='Annual'?'selected':'') ?>>Annual (100%)</option>
                        <option value="Special" <?= (@$_GET['exam_type']=='Special'?'selected':'') ?>>Special (M1+M2+Exam)</option>
                        <option value="Mock" <?= (@$_GET['exam_type']=='Mock'?'selected':'') ?>>Mock Exam</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold small">YEAR</label>
                    <select name="year" id="academic_year" class="form-select border-2">
                        <?php for($y=2015;$y<=2036;$y++){ $v="$y/".($y+1); echo "<option value='$v' ".(@$_GET['year']==$v?'selected':'').">$v</option>"; } ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" name="load_list" class="btn btn-success w-100 fw-bold py-2 shadow-sm">LOAD LIST</button>
                </div>
            </form>
        </div>
    </div>

    <?php if(!empty($students)): ?>
    <form action="save_olevel_marks.php" method="POST">
        <input type="hidden" name="subject_id" value="<?= $_GET['subject'] ?>">
        <input type="hidden" name="class_name" value="<?= $_GET['class'] ?>">
        <input type="hidden" name="stream" value="<?= $_GET['stream'] ?>">
        <input type="hidden" name="year" value="<?= $_GET['year'] ?>">
        <input type="hidden" name="exam_type" value="<?= $_GET['exam_type'] ?>">

        <div id="base_config_area" class="card border-start border-success border-4 mb-4" style="display:none;">
            <div class="card-body d-flex align-items-center py-2">
                <div class="me-4 border-end pe-4">
                    <span class="base-label d-block">Monthly Weight (%)</span>
                    <input type="number" name="m_base" id="m_base" class="form-control form-control-sm w-100 fw-bold border-2" value="<?= $students[0]['monthly_base'] ?? 40 ?>" oninput="updateBases()">
                </div>
                <div>
                    <span class="base-label d-block">Exam Weight (%)</span>
                    <span id="e_base_display" class="h4 fw-bold text-success"><?= $students[0]['exam_base'] ?? 60 ?></span>%
                    <input type="hidden" name="e_base" id="e_base_hidden" value="<?= $students[0]['exam_base'] ?? 60 ?>">
                </div>
            </div>
        </div>

        <div class="card p-0 overflow-hidden">
            <table class="table table-hover align-middle mb-0" id="marksTable">
                <thead class="table-light border-bottom">
                    <tr class="small text-uppercase fw-bold">
                        <th class="ps-4">#</th>
                        <th>Student Name</th>
                        <th class="text-center m1-col">Monthly</th>
                        <th class="text-center m1-total-col">M. Total (100%)</th>
                        <th class="text-center m2-col" style="display:none;">Monthly 2</th>
                        <th class="text-center p1-col">Exam (P1)</th>
                        <th class="text-center p1-total-col">E. Total (100%)</th>
                        <th class="text-center p2-col" style="display:none;">Paper 2</th>
                        <th class="text-center">Final Total</th>
                        <th class="text-center">Grade</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($students as $k => $std): ?>
                    <tr class="student-row">
                        <td class="ps-4 text-muted"><?= $k+1 ?></td>
                        <td>
                            <div class="fw-bold text-dark"><?= $std['fullname'] ?></div>
                            <small class="text-muted text-uppercase"><?= $std['gender'] ?></small>
                            <input type="hidden" name="std_id[]" value="<?= $std['id'] ?>">
                        </td>
                        <td class="text-center m1-col">
                            <input type="number" name="m1[]" class="form-control mark-input m1-box mx-auto" step="0.1" value="<?= $std['monthly_mark'] ?>" oninput="calculate(this)">
                        </td>
                        <td class="text-center m1-total-col">
                            <span class="total-preview m1-converted">0.0</span>
                        </td>
                        <td class="text-center m2-col" style="display:none;">
                            <input type="number" name="m2[]" class="form-control mark-input m2-box mx-auto" step="0.1" value="<?= $std['m2_mark'] ?>" oninput="calculate(this)">
                        </td>
                        <td class="text-center p1-col">
                            <input type="number" name="p1[]" class="form-control mark-input p1-box mx-auto" step="0.1" value="<?= $std['paper1_mark'] ?>" oninput="calculate(this)">
                        </td>
                        <td class="text-center p1-total-col">
                            <span class="total-preview p1-converted">0.0</span>
                        </td>
                        <td class="text-center p2-col" style="display:none;">
                            <input type="number" name="p2[]" class="form-control mark-input p2-box mx-auto" step="0.1" value="<?= $std['paper2_mark'] ?>" oninput="calculate(this)">
                        </td>
                        <td class="text-center">
                            <span class="total-final">0.0</span>
                        </td>
                        <td class="text-center">
                            <span class="grade-badge g-F">F</span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="card-footer bg-white border-top-0 p-4 text-end">
                <button type="submit" class="btn btn-success btn-lg px-5 rounded-pill fw-bold shadow"><i class="fas fa-save me-2"></i> SAVE ALL RESULTS</button>
            </div>
        </div>
    </form>
    <?php endif; ?>
</div>

<footer class="text-center shadow-lg">
    <div class="container">
        <p class="mb-0 fw-bold text-muted">© <?= date('Y') ?> School Management System</p>
        <p class="text-success small fw-bold">Design & Engineered by <span class="text-dark">Sir Likindy</span></p>
    </div>
</footer>

<script>
function updateBases() {
    let m = parseFloat(document.getElementById('m_base').value) || 0;
    if(m > 100) m = 100;
    let e = 100 - m;
    document.getElementById('e_base_display').innerText = e;
    document.getElementById('e_base_hidden').value = e;
    calculateAll();
}

function checkExamLogic() {
    const exam = document.getElementById('exam_type').value;
    const sub = document.getElementById('sub_id');
    const hasP2 = sub.options[sub.selectedIndex]?.getAttribute('data-p2') === '1';
    
    document.getElementById('base_config_area').style.display = (exam.includes('Term ')) ? 'block' : 'none';
    
    // Manage column rules for 100% face-value modes (Terminal & Annual)
    document.querySelectorAll('.m1-col, .m1-total-col').forEach(c => c.style.display = (exam === 'Terminal' || exam === 'Annual' || exam === 'Mock') ? 'none' : '');
    document.querySelectorAll('.p1-total-col').forEach(c => c.style.display = (exam.includes('Term ') || exam === 'Special') ? '' : 'none');
    document.querySelectorAll('.m2-col').forEach(c => c.style.display = (exam === 'Special') ? '' : 'none');
    document.querySelectorAll('.p2-col').forEach(c => c.style.display = (exam === 'Mock' && hasP2) ? '' : 'none');
    
    calculateAll();
}

function calculate(input) {
    const row = input.closest('tr');
    const m1 = parseFloat(row.querySelector('.m1-box').value) || 0;
    const m2 = parseFloat(row.querySelector('.m2-box').value) || 0;
    const p1 = parseFloat(row.querySelector('.p1-box').value) || 0;
    const p2 = parseFloat(row.querySelector('.p2-box').value) || 0;
    
    const m1_conv = row.querySelector('.m1-converted');
    const p1_conv = row.querySelector('.p1-converted');
    const totalDisp = row.querySelector('.total-final');
    const gradeBadge = row.querySelector('.grade-badge');
    
    const exam = document.getElementById('exam_type').value;
    const sub = document.getElementById('sub_id');
    const subOption = sub.options[sub.selectedIndex];
    const subName = subOption ? subOption.getAttribute('data-name').toLowerCase() : "";
    
    let finalTotal = 0;

    if (exam === 'Terminal' || exam === 'Annual') {
        // Direct absolute evaluation out of 100
        finalTotal = p1; 
    }
    else if (exam.includes('Term ')) {
        let m_base = parseFloat(document.getElementById('m_base').value) || 40;
        let e_base = 100 - m_base;
        if(m1_conv) m1_conv.innerText = ((m1 / m_base) * 100).toFixed(1);
        if(p1_conv) p1_conv.innerText = ((p1 / e_base) * 100).toFixed(1);
        finalTotal = m1 + p1; 
    } 
    else if (exam === 'Special') {
        if(m1_conv) m1_conv.innerText = ((m1 / 20) * 100).toFixed(1);
        if(p1_conv) p1_conv.innerText = ((p1 / 60) * 100).toFixed(1);
        finalTotal = m1 + m2 + p1; 
    }
    else if (exam === 'Mock') {
        if (subName.includes('bio') || subName.includes('phy') || subName.includes('chem')) {
            finalTotal = (p1 + p2) / 1.5;
        } else if (subName.includes('edk')) {
            finalTotal = (p1 + p2) / 2;
        } else {
            finalTotal = p1;
        }
    }

    if(finalTotal > 100) finalTotal = 100;
    totalDisp.innerText = finalTotal.toFixed(1);

    gradeBadge.className = 'grade-badge';
    if(finalTotal >= 80) { gradeBadge.innerText = 'A'; gradeBadge.classList.add('g-A'); }
    else if(finalTotal >= 70) { gradeBadge.innerText = 'B'; gradeBadge.classList.add('g-B'); }
    else if(finalTotal >= 60) { gradeBadge.innerText = 'C'; gradeBadge.classList.add('g-C'); }
    else if(finalTotal >= 50) { gradeBadge.innerText = 'D'; gradeBadge.classList.add('g-D'); }
    else { gradeBadge.innerText = 'F'; gradeBadge.classList.add('g-F'); }
}

function calculateAll() {
    document.querySelectorAll('.student-row').forEach(row => {
        const input = row.querySelector('.p1-box');
        if(input) calculate(input);
    });
}
</script>
</body>
</html>