<?php
/**
 * Ultimate Premium NGO Landing Page
 * Donatix inspired structure with original codebase
 */
define('APP_ROOT', __DIR__);

// Load config and security for CSRF token
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/core/Security.php';
session_start();
$csrfToken = Security::generateCSRF();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Arohan Foundation | Building a Kinder Society</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/landing.css">
</head>
<body>

    <?php include 'includes/navbar.php'; ?>

    <!-- 1. Hero Section -->
    <header id="home" class="hero-section parallax-scene">
        <div class="hero-bg-texture" data-depth="0.05"></div>
        <div class="hero-glow-blob top-blob" data-depth="0.1"></div>
        <div class="hero-glow-blob bottom-blob" data-depth="-0.1"></div>
        <div class="hero-particles"></div>
        <!-- Slider Navigation Dots -->
        <div class="hero-slider-nav">
            <button class="hero-dot active" aria-label="Slide 1"></button>
            <button class="hero-dot" aria-label="Slide 2"></button>
            <button class="hero-dot" aria-label="Slide 3"></button>
            <button class="hero-dot" aria-label="Slide 4"></button>
        </div>

        <div class="container hero-container">
            <div class="hero-content" data-animate="fade-up">
                <!-- Decorative Crosses -->
                <div class="hero-crosses" data-depth="-0.15">
                    <i class="fas fa-plus"></i><i class="fas fa-plus"></i><i class="fas fa-plus"></i><i class="fas fa-plus"></i>
                    <i class="fas fa-plus"></i><i class="fas fa-plus"></i><i class="fas fa-plus"></i><i class="fas fa-plus"></i>
                    <i class="fas fa-plus"></i><i class="fas fa-plus"></i><i class="fas fa-plus"></i><i class="fas fa-plus"></i>
                </div>
                
                <div class="hero-subtitle-box">
                    <div class="hs-line"></div>
                    <span class="hs-text">Care • Heal • Empower • Uplift</span>
                </div>
                
                <h1 class="hero-title">Building a kinder<br>society. <span style="color: var(--secondary);">Together.</span></h1>
                
                <p class="hero-desc">Welcome to the Arohan Foundation. We are dedicated to creating sustainable solutions and providing direct, transparent support to communities in need across the globe.</p>
                
                <a href="#about" class="btn-premium">Discover Now <i class="fas fa-arrow-up-right-from-square"></i></a>
            </div>
            
            <div class="hero-visual" data-animate="zoom-in">
                <div class="hero-mask-wrapper" data-depth="0.05">
                    <!-- Black & white organic image -->
                    <img src="assets/images/hero/poor-girl.png" alt="Child in need">
                    <div class="hero-mask-stroke"></div>
                </div>
                
                <!-- Premium Floating UI Cards -->
                <div class="premium-glass-card pc-1" data-depth="0.15">
                    <div class="pc-icon green"><i class="fas fa-hand-holding-heart"></i></div>
                    <div class="pc-content">
                        <h4><span class="counter" data-target="2.5">0</span>M</h4>
                        <p>Funds Raised</p>
                    </div>
                </div>
                
                <div class="premium-glass-card pc-2" data-depth="-0.12">
                    <div class="pc-icon orange"><i class="fas fa-users"></i></div>
                    <div class="pc-content">
                        <h4><span class="counter" data-target="1200">0</span>+</h4>
                        <p>Volunteers</p>
                    </div>
                </div>
                
                <div class="premium-glass-card pc-3" data-depth="0.18">
                    <div class="pc-icon blue"><i class="fas fa-bullhorn"></i></div>
                    <div class="pc-content">
                        <h4><span class="counter" data-target="150">0</span>+</h4>
                        <p>Campaigns</p>
                    </div>
                </div>
                
                <div class="premium-glass-card pc-4" data-depth="-0.08">
                    <div class="pc-icon purple"><i class="fas fa-shield-alt"></i></div>
                    <div class="pc-content">
                        <h4><span class="counter" data-target="95">0</span>%</h4>
                        <p>Transparency</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Brush Divider -->
        <div class="hero-bottom-brush">
            <svg viewBox="0 0 1440 60" preserveAspectRatio="none" fill="#FFFFFF" xmlns="http://www.w3.org/2000/svg">
                <!-- A rough painterly brush stroke vector approximation -->
                <path d="M0,60 L1440,60 L1440,20 C1380,35 1320,10 1260,25 C1200,40 1140,15 1080,30 C1020,45 960,20 900,35 C840,50 780,25 720,40 C660,55 600,30 540,45 C480,60 420,35 360,50 C300,65 240,40 180,55 C120,70 60,45 0,60 Z"/>
            </svg>
        </div>
    </header>

    <!-- 2. Impact Stats Section -->
    <section class="impact-section">
        <div class="container">
            <div class="impact-grid">
                <div class="impact-card" data-animate="fade-up" data-depth="0.05">
                    <div class="impact-icon"><i class="fas fa-hand-holding-usd"></i></div>
                    <h3 class="counter" data-target="2500000">0</h3>
                    <p>Total Donations</p>
                </div>
                <div class="impact-card" data-animate="fade-up" data-depth="-0.03" style="transition-delay: 0.1s;">
                    <div class="impact-icon"><i class="fas fa-project-diagram"></i></div>
                    <h3 class="counter" data-target="156">0</h3>
                    <p>Completed Causes</p>
                </div>
                <div class="impact-card" data-animate="fade-up" data-depth="0.04" style="transition-delay: 0.2s;">
                    <div class="impact-icon"><i class="fas fa-users"></i></div>
                    <h3 class="counter" data-target="8500">0</h3>
                    <p>Active Volunteers</p>
                </div>
                <div class="impact-card" data-animate="fade-up" data-depth="-0.05" style="transition-delay: 0.3s;">
                    <div class="impact-icon"><i class="fas fa-smile"></i></div>
                    <h3 class="counter" data-target="120000">0</h3>
                    <p>Lives Impacted</p>
                </div>
            </div>
        </div>
    </section>

    <!-- 3. About Us Section -->
    <section id="about" class="about-section section-padding">
        <div class="container about-grid">
            <div class="about-images" data-animate="fade-right" data-depth="0.05">
                <div class="img-wrap main">
                    <img src="assets/images/volunteers/volunteer_team.png"
                         alt="Volunteer Work"
                         loading="lazy"
                         style="width:100%; height:100%; object-fit:cover; border-radius:16px; display:block;">
                </div>
                <div class="img-wrap sub">
                    <img src="assets/images/volunteers/community_help.png"
                         alt="Community Help"
                         loading="lazy"
                         style="width:100%; height:100%; object-fit:cover; border-radius:12px; display:block;">
                </div>
            </div>
            
            <div class="about-text" data-animate="fade-left" data-depth="0.02">
                <span class="section-subtitle">Who We Are</span>
                <h2 class="section-title">Driving positive change across the globe.</h2>
                <p class="section-desc">We are a non-profit organization dedicated to creating sustainable solutions for communities in need. Our transparent approach ensures every contribution makes a direct impact.</p>
                
                <div class="about-features">
                    <div class="feature-row">
                        <div class="fr-icon"><i class="fas fa-bullseye"></i></div>
                        <div class="fr-text">
                            <h4>Targeted Relief</h4>
                            <p>We identify communities with the most urgent needs through rigorous data analysis and on-ground partnerships.</p>
                        </div>
                    </div>
                    <div class="feature-row">
                        <div class="fr-icon orange"><i class="fas fa-search-dollar"></i></div>
                        <div class="fr-text">
                            <h4>Complete Transparency</h4>
                            <p>Track exactly where your donations go. We bypass middlemen to deliver resources directly to those in need.</p>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- 4. Why Choose Us -->
    <section class="features-section section-padding">
        <!-- Top Divider -->
        <div class="shape-divider top">
            <svg viewBox="0 0 1200 120" preserveAspectRatio="none">
                <path d="M321.39,56.44c58-10.79,114.16-30.13,172-41.86,82.39-16.72,168.19-17.73,250.45-.39C823.78,31,906.67,72,985.66,92.83c70.05,18.48,146.53,26.09,214.34,3V120H0V95.8C59.71,118,130.85,121.71,192.5,108,236.49,98.3,278.43,76.5,321.39,56.44Z" fill="#FFFFFF"></path>
            </svg>
        </div>
        
        <div class="container">
            <div class="section-header text-center" data-animate="fade-up">
                <span class="section-subtitle">Core Values</span>
                <h2 class="section-title">Trust built on Technology</h2>
                <p class="section-desc mx-auto">We combine modern financial infrastructure with on-ground charity work.</p>
            </div>
            
            <div class="features-grid">
                <div class="feature-box" data-animate="fade-up">
                    <div class="fb-icon"><i class="fas fa-shield-alt"></i></div>
                    <h4>Bank-level Security</h4>
                    <p>Your transactions are encrypted and secured by industry-leading financial infrastructure providers.</p>
                </div>
                <div class="feature-box" data-animate="fade-up" style="transition-delay: 0.1s;">
                    <div class="fb-icon"><i class="fas fa-bolt"></i></div>
                    <h4>Instant Deployment</h4>
                    <p>Funds are routed globally in milliseconds, bypassing traditional slow banking systems.</p>
                </div>
                <div class="feature-box" data-animate="fade-up" style="transition-delay: 0.2s;">
                    <div class="fb-icon"><i class="fas fa-globe"></i></div>
                    <h4>Global Network</h4>
                    <p>Partnered with 200+ localized NGOs to ensure cultural and regional efficiency on the ground.</p>
                </div>
            </div>
        </div>
        
        <!-- Bottom Divider -->
        <div class="shape-divider bottom">
            <svg viewBox="0 0 1200 120" preserveAspectRatio="none">
                <path d="M321.39,56.44c58-10.79,114.16-30.13,172-41.86,82.39-16.72,168.19-17.73,250.45-.39C823.78,31,906.67,72,985.66,92.83c70.05,18.48,146.53,26.09,214.34,3V120H0V95.8C59.71,118,130.85,121.71,192.5,108,236.49,98.3,278.43,76.5,321.39,56.44Z" fill="#F9FAFB"></path>
            </svg>
        </div>
    </section>

    <!-- 5. Featured Campaigns -->
    <section id="campaigns" class="campaigns-section section-padding bg-light">
        <div class="container">
            <div class="section-header text-center" data-animate="fade-up">
                <span class="section-subtitle">Active Fundraising Campaigns</span>
                <h2 class="section-title">Help us reach our goals</h2>
            </div>

            <div class="campaigns-grid">
                <!-- Card 1 -->
                <div class="campaign-card" data-animate="fade-up" data-depth="0.03">
                    <div class="card-media">
                        <img src="https://images.unsplash.com/photo-1509062522246-3755977927d7?auto=format&fit=crop&w=600&q=80" alt="Education">
                        <div class="card-badge">Education</div>
                        <div class="card-overlay"></div>
                    </div>
                    <div class="card-body">
                        <h3 class="card-title"><a href="javascript:void(0)">Provide Education for Rural Children</a></h3>
                        <p class="card-desc">Help us build a new school and provide essential learning materials for 500 children.</p>
                        
                        <div class="fund-stats">
                            <span class="raised">Raised: ₹45,000</span>
                            <span class="goal">Goal: ₹60,000</span>
                        </div>
                        <div class="fund-bar-bg">
                            <div class="fund-bar-fill" data-percent="75"></div>
                        </div>
                        
                        <div class="card-footer">
                            <div class="days-left"><i class="fas fa-clock"></i> 12 Days Left</div>
                            <a href="javascript:void(0)" class="btn-link">Donate <i class="fas fa-arrow-right"></i></a>
                        </div>
                    </div>
                </div>

                <!-- Card 2 -->
                <div class="campaign-card" data-animate="fade-up" data-depth="-0.02" style="transition-delay: 0.1s;">
                    <div class="card-media">
                        <img src="assets/images/campaigns/water-initiative.jpeg" alt="Water">
                        <div class="card-badge green">Clean Water</div>
                        <div class="card-overlay"></div>
                    </div>
                    <div class="card-body">
                        <h3 class="card-title"><a href="javascript:void(0)">Clean Water Initiative in Africa</a></h3>
                        <p class="card-desc">Building sustainable solar-powered water wells to serve isolated communities.</p>
                        
                        <div class="fund-stats">
                            <span class="raised">Raised: ₹85,000</span>
                            <span class="goal">Goal: ₹100,000</span>
                        </div>
                        <div class="fund-bar-bg">
                            <div class="fund-bar-fill" data-percent="85"></div>
                        </div>
                        
                        <div class="card-footer">
                            <div class="days-left"><i class="fas fa-clock"></i> 5 Days Left</div>
                            <a href="javascript:void(0)" class="btn-link">Donate <i class="fas fa-arrow-right"></i></a>
                        </div>
                    </div>
                </div>

                <!-- Card 3 -->
                <div class="campaign-card" data-animate="fade-up" data-depth="0.04" style="transition-delay: 0.2s;">
                    <div class="card-media">
                        <img src="https://images.unsplash.com/photo-1469571486292-0ba58a3f068b?auto=format&fit=crop&w=600&q=80" alt="Health">
                        <div class="card-badge">Healthcare</div>
                        <div class="card-overlay"></div>
                    </div>
                    <div class="card-body">
                        <h3 class="card-title"><a href="javascript:void(0)">Emergency Medical Relief Fund</a></h3>
                        <p class="card-desc">Providing critical medical supplies and mobile clinics in disaster-struck zones.</p>
                        
                        <div class="fund-stats">
                            <span class="raised">Raised: ₹20,000</span>
                            <span class="goal">Goal: ₹50,000</span>
                        </div>
                        <div class="fund-bar-bg">
                            <div class="fund-bar-fill" data-percent="40"></div>
                        </div>
                        
                        <div class="card-footer">
                            <div class="days-left"><i class="fas fa-clock"></i> 20 Days Left</div>
                            <a href="javascript:void(0)" class="btn-link">Donate <i class="fas fa-arrow-right"></i></a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="text-center mt-5" style="margin-top: 60px;">
                <a href="#campaigns" class="btn-premium btn-lg">View All Campaigns</a>
            </div>
        </div>
    </section>

    <!-- 6. Events Section -->
    <section id="events" class="events-section section-padding">
        <div class="container">
            <div class="section-header text-center" data-animate="fade-up">
                <span class="section-subtitle">Upcoming Events</span>
                <h2 class="section-title">Join us on the ground</h2>
            </div>
            
            <div class="events-wrapper">
                <div class="event-row" data-animate="fade-up">
                    <div class="event-date">
                        <span class="d">15</span>
                        <span class="m">Oct</span>
                    </div>
                    <div class="event-details">
                        <h3>Global Reforestation Drive</h3>
                        <div class="event-meta">
                            <span><i class="fas fa-map-marker-alt"></i> Amazon Rainforest</span>
                            <span><i class="fas fa-users"></i> 50/100 Volunteers Needed</span>
                        </div>
                        <p>Join our annual tree planting initiative to combat deforestation and restore natural habitats.</p>
                    </div>
                    <a href="register.php" class="btn btn-outline">Register</a>
                </div>
                
                <div class="event-row" data-animate="fade-up" style="transition-delay: 0.1s;">
                    <div class="event-date" style="background: rgba(245,158,11,0.1); color: var(--secondary); box-shadow: inset 0 0 0 2px var(--secondary);">
                        <span class="d">22</span>
                        <span class="m">Nov</span>
                    </div>
                    <div class="event-details">
                        <h3>Food Distribution Camp</h3>
                        <div class="event-meta">
                            <span><i class="fas fa-map-marker-alt"></i> Community Center, Sector 4</span>
                            <span><i class="fas fa-users"></i> 10/30 Volunteers Needed</span>
                        </div>
                        <p>Help us distribute nutritional food packets to local families facing food insecurity.</p>
                    </div>
                    <a href="register.php" class="btn btn-outline">Register</a>
                </div>
            </div>
        </div>
    </section>


    <!-- 8. Partners -->
    <section class="partners-section">
        <div class="partner-track">
            <div class="partner-logo"><h2>UNICEF</h2></div>
            <div class="partner-logo"><h2>WHO</h2></div>
            <div class="partner-logo"><h2>OXFAM</h2></div>
            <div class="partner-logo"><h2>RED CROSS</h2></div>
            <div class="partner-logo"><h2>GREENPEACE</h2></div>
            <div class="partner-logo"><h2>SAVE CHILDREN</h2></div>
        </div>
    </section>

    <!-- 9. FAQ -->
    <section class="faq-section section-padding">
        <div class="container">
            <div class="section-header text-center" data-animate="fade-up">
                <span class="section-subtitle">FAQ</span>
                <h2 class="section-title">Common Questions</h2>
            </div>
            
            <div class="faq-container" data-animate="fade-up">
                <div class="faq-card active">
                    <button class="faq-header">Are my donations tax deductible? <span class="faq-icon"><i class="fas fa-chevron-down"></i></span></button>
                    <div class="faq-body">
                        <p>Yes, all donations made through our platform are eligible for tax deductions under section 80G. You will receive an automated, cryptographically signed receipt in your email immediately after your transaction.</p>
                    </div>
                </div>
                <div class="faq-card">
                    <button class="faq-header">How do I track my donation's impact? <span class="faq-icon"><i class="fas fa-chevron-down"></i></span></button>
                    <div class="faq-body">
                        <p>Upon donating, you'll gain access to a personalized dashboard where you can track the allocation of your funds, complete with milestone updates and field reports.</p>
                    </div>
                </div>
                <div class="faq-card">
                    <button class="faq-header">What is your platform fee structure? <span class="faq-icon"><i class="fas fa-chevron-down"></i></span></button>
                    <div class="faq-body">
                        <p>We operate on a 0% platform fee model for donors. 100% of your donation goes directly to the cause. Our operational costs are covered separately by private philanthropic investors.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>


    <!-- 11. Contact -->
    <section id="contact" class="contact-section section-padding">
        <div class="container">
            <div class="section-header text-center" data-animate="fade-up">
                <span class="section-subtitle">Contact Us</span>
                <h2 class="section-title">Let's start a conversation</h2>
            </div>
            
            <div class="contact-grid" data-animate="fade-up">
                <div class="contact-info">
                    <h3>Get in touch</h3>
                    <p>We'd love to hear from you. Our friendly team is always here to chat.</p>
                    
                    <div class="c-info-item">
                        <div class="icon"><i class="fas fa-map-marker-alt"></i></div>
                        <div>
                            <h5>Headquarters</h5>
                            <p>123 Innovation Drive, San Francisco, CA 94105</p>
                        </div>
                    </div>
                    <div class="c-info-item">
                        <div class="icon"><i class="fas fa-envelope"></i></div>
                        <div>
                            <h5>Email Us</h5>
                            <p>hello@Arohan Foundation.org</p>
                        </div>
                    </div>
                    <div class="c-info-item">
                        <div class="icon"><i class="fas fa-phone-alt"></i></div>
                        <div>
                            <h5>Call Us</h5>
                            <p>+1 (555) 123-4567</p>
                        </div>
                    </div>
                </div>
                
                <div class="contact-form">
                    <form id="contactForm">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                        <div id="contactFormStatus" style="display:none; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 500;"></div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>First Name</label>
                                <input type="text" name="first_name" class="form-control" placeholder="John" required>
                            </div>
                            <div class="form-group">
                                <label>Last Name</label>
                                <input type="text" name="last_name" class="form-control" placeholder="Doe" required>
                            </div>
                        </div>
                        <div class="form-group" style="margin-bottom: 24px;">
                            <label>Email Address</label>
                            <input type="email" name="email" class="form-control" placeholder="john@example.com" required>
                        </div>
                        <div class="form-group" style="margin-bottom: 24px;">
                            <label>Message</label>
                            <textarea name="message" class="form-control" rows="4" placeholder="How can we help?" minlength="20" maxlength="2000" required></textarea>
                        </div>
                        <button type="submit" id="contactSubmitBtn" class="btn btn-premium w-100">Send Message</button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- 12. Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-col brand-col">
                    <div class="f-logo" style="display: flex; align-items: center; gap: 12px; font-family: var(--font-heading); font-size: 28px; font-weight: 900; color: white; margin-bottom: 24px;">
                        <img src="assets/images/logo/arohan-logo.jpeg" alt="Arohan Foundation Logo" style="height: 60px; width: auto; object-fit: contain; border-radius: 8px; background: transparent;">
                    </div>
                    <p class="f-desc">Building the future of transparent philanthropy. Empowering communities worldwide through direct action.</p>
                    <div class="social-icons">
                        <a href="https://facebook.com/arohan"><i class="fab fa-facebook-f"></i></a>
                        <a href="https://twitter.com/arohan"><i class="fab fa-twitter"></i></a>
                        <a href="https://instagram.com/arohan"><i class="fab fa-instagram"></i></a>
                        <a href="https://linkedin.com/company/arohan"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>
                <div class="footer-col">
                    <h4>Platform</h4>
                    <div class="f-links">
                        <a href="javascript:void(0)">Explore Campaigns</a>
                        <a href="javascript:void(0)">Upcoming Events</a>
                        <a href="javascript:void(0)">Transparency Ledger</a>
                        <a href="javascript:void(0)">Success Stories</a>
                    </div>
                </div>
                <div class="footer-col">
                    <h4>Company</h4>
                    <div class="f-links">
                        <a href="javascript:void(0)">About Us</a>
                        <a href="javascript:void(0)">Careers</a>
                        <a href="javascript:void(0)">Press & Media</a>
                        <a href="javascript:void(0)">Contact Support</a>
                    </div>
                </div>
                <div class="footer-col">
                    <h4>Legal</h4>
                    <div class="f-links">
                        <a href="privacy.php">Privacy Policy</a>
                        <a href="terms.php">Terms of Service</a>
                        <a href="javascript:void(0)">Cookie Policy</a>
                        <a href="javascript:void(0)">Donor Guidelines</a>
                    </div>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> Arohan Foundation Inc. All rights reserved.</p>
                <div class="footer-bottom-links">
                    <a href="privacy.php">Privacy</a>
                    <a href="terms.php">Terms</a>
                </div>
            </div>
        </div>
    </footer>

    <script src="assets/js/landing.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const contactForm = document.getElementById('contactForm');
        const contactStatus = document.getElementById('contactFormStatus');
        const submitBtn = document.getElementById('contactSubmitBtn');

        if (contactForm) {
            contactForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // UI Loading state
                submitBtn.disabled = true;
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
                contactStatus.style.display = 'none';
                
                const formData = new FormData(this);

                fetch('api/submit_contact.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                    
                    contactStatus.style.display = 'block';
                    if(data.status === 'success') {
                        contactStatus.style.backgroundColor = 'rgba(16, 185, 129, 0.1)';
                        contactStatus.style.color = '#059669';
                        contactStatus.innerHTML = '<i class="fas fa-check-circle"></i> ' + data.message;
                        contactForm.reset();
                    } else {
                        contactStatus.style.backgroundColor = 'rgba(239, 68, 68, 0.1)';
                        contactStatus.style.color = '#DC2626';
                        contactStatus.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + data.message;
                    }
                })
                .catch(error => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                    contactStatus.style.display = 'block';
                    contactStatus.style.backgroundColor = 'rgba(239, 68, 68, 0.1)';
                    contactStatus.style.color = '#DC2626';
                    contactStatus.innerHTML = '<i class="fas fa-exclamation-circle"></i> An error occurred. Please try again.';
                    console.error('Error:', error);
                });
            });
        }
    });
    </script>
</body>
</html>




