<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Software Agreement | Likindy Digital Solution</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cedarville+Cursive&display=swap" rel="stylesheet">
    
    <style>
        body { background-color: #f8fafc; font-family: 'Times New Roman', serif; line-height: 1.6; }
        .contract-paper {
            max-width: 850px;
            margin: 30px auto;
            background: white;
            padding: 60px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            border-top: 10px solid #1e293b;
        }
        .dev-signature {
            font-family: 'Cedarville Cursive', cursive;
            font-size: 2.2rem;
            color: #1e3a8a; /* Rangi ya bluu ya kalamu */
            margin-bottom: -10px;
            display: block;
        }
        .sig-line { border-bottom: 2px solid #333; width: 250px; margin-top: 5px; }
        .section-header { font-weight: bold; text-decoration: underline; text-transform: uppercase; margin-top: 20px; display: block; }
        
        @media print {
            .no-print { display: none !important; }
            .contract-paper { box-shadow: none; margin: 0; padding: 20px; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="text-end mt-3 no-print">
        <button onclick="window.print()" class="btn btn-dark"><i class="fas fa-print me-2"></i> Print Contract</button>
    </div>

    <div class="contract-paper">
        <div class="text-center mb-5">
            <h2 class="fw-bold">SOFTWARE PURCHASE AGREEMENT</h2>
            <p class="mb-0"><strong>LIKINDY DIGITAL SOLUTION</strong></p>
            <p class="small">Kijichi, Unguja, Zanzibar | +255 658 415 488</p>
        </div>

        <p>This Agreement is entered into on this <strong><?= date('d/m/Y') ?></strong> between:</p>
        <p><strong>DEVELOPER:</strong> Likindy Ismail (Likindy Digital Solution)</p>
        <p><strong>CLIENT:</strong> __________________________________________________</p>

        <span class="section-header">1. Scope of Work</span>
        <p>The Developer agrees to deliver a complete School Management System including Student, Staff, Finance, and SMS modules for a total cost of <strong>TZS 10,000,000</strong>.</p>

        <span class="section-header">2. Annual Maintenance</span>
        <p>The Client shall pay <strong>TZS 1,000,000</strong> annually for system maintenance, security updates, and technical support starting one year after installation.</p>

        <span class="section-header">3. Ownership</span>
        <p>The system source code remains the intellectual property of Likindy Digital Solution. The Client is granted a usage license for one institution only.</p>

        <div class="row mt-5 pt-4">
            <div class="col-6">
                <p class="fw-bold small mb-3">DEVELOPER SIGNATURE:</p>
                <span class="dev-signature">Likindy Ismail</span>
                <div class="sig-line"></div>
                <p class="mt-2 mb-0"><strong>Likindy Ismail</strong></p>
                <p class="small text-muted">Founder, Likindy Digital Solution</p>
            </div>
            
            <div class="col-6 text-end">
                <p class="fw-bold small mb-3 text-start ms-5 ps-4">CLIENT SIGNATURE:</p>
                <div style="height: 50px;"></div>
                <div class="sig-line ms-auto"></div>
                <p class="mt-2 mb-0">Authorized Representative</p>
                <p class="small text-muted">Date: ____/____/20___</p>
            </div>
        </div>

        <div class="mt-5 text-center border-top pt-3">
            <p class="small text-muted italic">"Designed and Developed by Likindy Digital Solution - Zanzibar"</p>
        </div>
    </div>
</div>

</body>
</html>