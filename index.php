<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smile Republic Dental Clinic - Professional Dental Care</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <nav class="navbar">
                <a href="#" class="logo">
                    <i class="fas fa-tooth"></i>
                    Smile Republic
                </a>
                <ul class="nav-links">
                    <li><a href="#home">Home</a></li>
                    <li><a href="#services">Services</a></li>
                    <li><a href="#features">Features</a></li>
                    <li><a href="#contact">Contact</a></li>
                    <li><a href="login.php" class="btn btn-primary btn-sm">Staff Login</a></li>
                </ul>
                <!-- Mobile menu button -->
                <button class="mobile-menu-btn" id="mobileMenuBtn" style="display: none; background: none; border: none; color: var(--gray-600); font-size: 1.5rem; cursor: pointer;">
                    <i class="fas fa-bars"></i>
                </button>
            </nav>
        </div>
    </header>

    <!-- Hero Section -->
    <section id="home" class="hero">
        <div class="container">
            <h1>Your Smile, Our Priority</h1>
            <p>Experience exceptional dental care with our state-of-the-art clinic management system. Professional, efficient, and patient-focused.</p>
            <div style="margin-top: 2rem;">
                <a href="#services" class="btn btn-primary btn-lg" style="margin-right: 1rem;">Our Services</a>
                <a href="login.php" class="btn btn-secondary btn-lg">Staff Portal</a>
            </div>
        </div>
    </section>

    <!-- Services Section -->
    <section id="services" class="features">
        <div class="container">
            <div class="text-center">
                <h2>Our Dental Services</h2>
                <p>Comprehensive dental care for all your oral health needs</p>
            </div>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-teeth"></i>
                    </div>
                    <h3>Preventive Care</h3>
                    <p>Regular cleanings, checkups, and preventive treatments to maintain optimal oral health and prevent dental issues.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-tooth"></i>
                    </div>
                    <h3>Restorative Dentistry</h3>
                    <p>Fillings, crowns, bridges, and other treatments to restore damaged teeth and improve function.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-smile"></i>
                    </div>
                    <h3>Cosmetic Dentistry</h3>
                    <p>Teeth whitening, veneers, and other cosmetic procedures to enhance your smile's appearance.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-cut"></i>
                    </div>
                    <h3>Oral Surgery</h3>
                    <p>Tooth extractions, dental implants, and other surgical procedures performed with precision and care.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-grip-lines"></i>
                    </div>
                    <h3>Orthodontics</h3>
                    <p>Braces, aligners, and other orthodontic treatments to straighten teeth and improve bite alignment.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-stethoscope"></i>
                    </div>
                    <h3>Endodontics</h3>
                    <p>Root canal therapy and other endodontic treatments to save and preserve natural teeth.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" style="background: var(--light-color); padding: 5rem 0;">
        <div class="container">
            <div class="text-center mb-4">
                <h2>Why Choose Smile Republic?</h2>
                <p>Advanced technology meets compassionate care</p>
            </div>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <h3>Easy Scheduling</h3>
                    <p>Our efficient appointment system ensures you get the care you need when you need it, with minimal wait times.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-user-md"></i>
                    </div>
                    <h3>Expert Dentists</h3>
                    <p>Our team of experienced dental professionals provides the highest quality care using the latest techniques.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h3>Safe & Secure</h3>
                    <p>We maintain the highest standards of safety and privacy for all our patients and their medical information.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <h3>Flexible Hours</h3>
                    <p>Extended hours and multiple scheduling options to accommodate your busy lifestyle and schedule.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-heart"></i>
                    </div>
                    <h3>Patient-Centered</h3>
                    <p>Your comfort and satisfaction are our top priorities. We listen to your concerns and customize treatment plans.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-laptop-medical"></i>
                    </div>
                    <h3>Modern Technology</h3>
                    <p>State-of-the-art equipment and digital systems ensure precise diagnoses and effective treatments.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact" style="background: var(--white); padding: 5rem 0;">
        <div class="container">
            <div class="text-center mb-4">
                <h2>Contact Us</h2>
                <p>Get in touch to schedule your appointment or learn more about our services</p>
            </div>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem;">
                <div class="card">
                    <div class="card-body text-center">
                        <div class="feature-icon" style="margin-bottom: 1rem;">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <h4>Visit Us</h4>
                        <p>123 Dental Street<br>Health City, HC 12345</p>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body text-center">
                        <div class="feature-icon" style="margin-bottom: 1rem;">
                            <i class="fas fa-phone"></i>
                        </div>
                        <h4>Call Us</h4>
                        <p>(555) 123-SMILE<br>Available Mon-Fri 8AM-6PM</p>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body text-center">
                        <div class="feature-icon" style="margin-bottom: 1rem;">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <h4>Email Us</h4>
                        <p>info@smilerepublic.com<br>We'll respond within 24 hours</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer style="background: var(--dark-color); color: var(--white); padding: 2rem 0; text-align: center;">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center" style="flex-wrap: wrap; gap: 1rem;">
                <div>
                    <div class="logo" style="margin-bottom: 0.5rem;">
                        <i class="fas fa-tooth"></i>
                        Smile Republic Dental Clinic
                    </div>
                    <p style="margin: 0; opacity: 0.8;">Professional dental care you can trust</p>
                </div>
                <div>
                    <a href="login.php" class="btn btn-primary">Staff Login</a>
                </div>
            </div>
            <hr style="margin: 2rem 0; border-color: #4A5568;">
            <p style="margin: 0; opacity: 0.8;">
                &copy; <?php echo date('Y'); ?> Smile Republic Dental Clinic. All rights reserved. | 
                <a href="#" style="color: var(--accent-color);">Privacy Policy</a> | 
                <a href="#" style="color: var(--accent-color);">Terms of Service</a>
            </p>
        </div>
    </footer>

    <!-- Scripts -->
    <script>
        // Smooth scrolling for navigation links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Add scroll effect to header
        window.addEventListener('scroll', function() {
            const header = document.querySelector('.header');
            if (window.scrollY > 100) {
                header.style.boxShadow = '0 4px 20px rgba(0, 0, 0, 0.1)';
            } else {
                header.style.boxShadow = '0 4px 6px -1px rgba(0, 0, 0, 0.1)';
            }
        });

        // Animate feature cards on scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.animation = 'fadeInUp 0.6s ease forwards';
                }
            });
        }, observerOptions);

        document.querySelectorAll('.feature-card').forEach(card => {
            observer.observe(card);
        });

        // Add CSS animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes fadeInUp {
                from {
                    opacity: 0;
                    transform: translateY(30px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
            .feature-card {
                opacity: 0;
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>
