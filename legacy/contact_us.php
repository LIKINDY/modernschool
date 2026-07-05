<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Developer | Likindy Digital Solution</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f1f5f9; font-family: 'Inter', sans-serif; }
        .contact-sidebar { background: linear-gradient(135deg, #1e293b 0%, #334155 100%); color: white; border-radius: 20px; padding: 40px; }
        .contact-form-card { background: white; border-radius: 20px; padding: 40px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); border: none; }
        .skill-badge { background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); padding: 5px 12px; border-radius: 8px; font-size: 0.8rem; margin-right: 5px; margin-bottom: 5px; display: inline-block; }
        .social-btn { width: 45px; height: 45px; display: inline-flex; align-items: center; justify-content: center; border-radius: 50%; background: #25d366; color: white; text-decoration: none; margin-right: 10px; transition: 0.3s; }
        .social-btn:hover { transform: scale(1.1); color: white; }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="row g-4">
        <div class="col-lg-5">
            <div class="contact-sidebar shadow-lg">
                <div class="mb-4">
                    <h2 class="fw-bold">Likindy Ismail</h2>
                    <p class="text-info fw-bold">Founder & Lead Developer</p>
                    <p class="small opacity-75">Likindy Digital Solution</p>
                </div>

                <hr class="opacity-25">

                <div class="mb-4">
                    <h6 class="fw-bold mb-3"><i class="fas fa-code me-2"></i>Expertise & Services</h6>
                    <div class="mb-3">
                        <span class="skill-badge">Python</span>
                        <span class="skill-badge">PHP</span>
                        <span class="skill-badge">Java</span>
                        <span class="skill-badge">Dart</span>
                        <span class="skill-badge">HTML/CSS</span>
                    </div>
                    <p class="small">I specialize in building all types of websites, advanced school management systems, and communication platforms. I also offer professional tutoring in <strong>Computer Science</strong> and <strong>Programming Languages</strong>.</p>
                </div>

                <div class="mb-4">
                    <h6 class="fw-bold mb-3"><i class="fas fa-map-marker-alt me-2"></i>Location</h6>
                    <p class="small">Kijichi, Unguja, Zanzibar - Tanzania</p>
                </div>

                <div class="mb-5">
                    <h6 class="fw-bold mb-3"><i class="fas fa-headset me-2"></i>Direct Contact</h6>
                    <div class="mb-2 small"><i class="fas fa-envelope me-2"></i> likindyismail@gmail.com</div>
                    <div class="mb-2 small"><i class="fas fa-phone me-2"></i> +255 658 415 488</div>
                    <div class="mb-2 small"><i class="fas fa-phone me-2"></i> +255 625 415 484</div>
                </div>

                <div>
                    <h6 class="fw-bold mb-3">Chat with me on WhatsApp:</h6>
                    <a href="https://wa.me/255658415488" class="social-btn shadow" target="_blank"><i class="fab fa-whatsapp"></i></a>
                    <a href="https://wa.me/255625415484" class="social-btn shadow" target="_blank" style="background: #128C7E;"><i class="fab fa-whatsapp"></i></a>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="contact-form-card shadow-sm h-100">
                <h3 class="fw-bold text-dark mb-4">Inquiry / System Support</h3>
                <p class="text-muted mb-4">Need a new website or technical assistance? Send a message directly to Likindy Digital Solution.</p>
                
                <form action="#" method="POST">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold">FULL NAME</label>
                            <input type="text" class="form-control bg-light border-0 py-3" placeholder="Enter your name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold">EMAIL ADDRESS</label>
                            <input type="email" class="form-control bg-light border-0 py-3" placeholder="Enter your email" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">SERVICE REQUIRED</label>
                        <select class="form-select bg-light border-0 py-3">
                            <option value="Website Development">Website Development</option>
                            <option value="System Maintenance">System Maintenance / Support</option>
                            <option value="Programming Classes">Programming / CS Tuition</option>
                            <option value="Other Inquiry">Other Inquiry</option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="form-label small fw-bold">YOUR MESSAGE</label>
                        <textarea class="form-control bg-light border-0" rows="5" placeholder="Explain your requirement here..." required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary px-5 py-3 fw-bold rounded-pill shadow">
                        SEND INQUIRY <i class="fas fa-paper-plane ms-2"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>