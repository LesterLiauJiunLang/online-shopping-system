<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
/* ========================
   Include Dependencies
======================== */
require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/models/UserModel.php';
require_once __DIR__ . '/../app/models/OrderModel.php';
require_once __DIR__ . '/../app/models/ProductModel.php';
require_once __DIR__ . '/../lib/base.php';

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']) && isset($_SESSION['logged_in']);
$userName = $_SESSION['user_name'] ?? '';
$userRole = $_SESSION['role'] ?? 'guest';

// Include auth functions if logged in
if ($isLoggedIn) {
    require_once '../lib/auth.php';
    auth();
    $userRole = $_user['role'] ?? $userRole;
}

// Set page title
$pageTitle = "About GadgetMart";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - Tech Accessories Store</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <style>
        :root {
            --primary-color: #5A4BFF;
            --secondary-color: #7B6FFF;
            --accent-color: #00D4AA;
            --light-color: #f8f9ff;
            --dark-color: #1a1a2e;
        }
        
        body {
            background-color: #ffffff;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #333;
            line-height: 1.6;
        }
        
        .navbar {
            background: white;
            box-shadow: 0 4px 20px rgba(90, 75, 255, 0.1);
            padding: 18px 0;
            transition: all 0.3s ease;
        }
        
        .navbar.scrolled {
            padding: 12px 0;
            box-shadow: 0 4px 30px rgba(90, 75, 255, 0.15);
        }
        
        .navbar-brand {
            font-weight: 800;
            color: var(--primary-color) !important;
            font-size: 2rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .nav-link {
            font-weight: 600;
            padding: 10px 20px !important;
            margin: 0 5px;
            border-radius: 8px;
            transition: all 0.3s ease;
            color: #555 !important;
        }
        
        .nav-link:hover {
            background-color: rgba(90, 75, 255, 0.1);
            color: var(--primary-color) !important;
            transform: translateY(-2px);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border: none;
            padding: 14px 32px;
            border-radius: 10px;
            font-weight: 700;
            transition: all 0.3s ease;
            box-shadow: 0 5px 20px rgba(90, 75, 255, 0.3);
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, var(--secondary-color), var(--primary-color));
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(90, 75, 255, 0.4);
        }
        
        .btn-outline-primary {
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
            font-weight: 700;
            padding: 12px 28px;
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        
        .btn-outline-primary:hover {
            background: var(--primary-color);
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(90, 75, 255, 0.2);
        }
        
        .hero-section {
            background: linear-gradient(135deg, #f8f9ff 0%, #ffffff 100%);
            padding: 100px 0 80px;
            position: relative;
            overflow: hidden;
        }
        
        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 60%;
            height: 100%;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 800"><path fill="%235a4bff" fill-opacity="0.03" d="M400,0C179,0,0,179,0,400s179,400,400,400s400-179,400-400S621,0,400,0z"/></svg>') no-repeat;
            background-size: contain;
            background-position: right center;
        }
        
        .hero-title {
            font-weight: 800;
            font-size: 3.5rem;
            margin-bottom: 25px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            line-height: 1.2;
        }
        
        .hero-subtitle {
            font-size: 1.2rem;
            color: #666;
            margin-bottom: 35px;
            max-width: 600px;
        }
        
        .section-title {
            font-weight: 800;
            font-size: 2.5rem;
            margin-bottom: 40px;
            text-align: center;
            color: var(--dark-color);
            position: relative;
        }
        
        .section-title::after {
            content: '';
            position: absolute;
            bottom: -15px;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 4px;
            background: linear-gradient(to right, var(--primary-color), var(--accent-color));
            border-radius: 2px;
        }
        
        .feature-card {
            background: white;
            border-radius: 16px;
            padding: 40px 30px;
            box-shadow: 0 10px 40px rgba(90, 75, 255, 0.08);
            transition: all 0.4s ease;
            height: 100%;
            border: 1px solid #f0f0ff;
            text-align: center;
        }
        
        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 50px rgba(90, 75, 255, 0.15);
        }
        
        .feature-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, rgba(90, 75, 255, 0.1), rgba(123, 111, 255, 0.2));
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: var(--primary-color);
            margin: 0 auto 25px;
            transition: all 0.3s ease;
        }
        
        .feature-card:hover .feature-icon {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            transform: scale(1.1);
        }
        
        .founder-card {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(90, 75, 255, 0.08);
            transition: all 0.4s ease;
            height: 100%;
            border: 1px solid #f0f0ff;
            text-align: center;
        }
        
        .founder-avatar {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            font-weight: 700;
            margin: 0 auto 20px;
            box-shadow: 0 10px 30px rgba(90, 75, 255, 0.2);
        }
        
        .cta-section {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 80px 0;
            border-radius: 30px;
            margin: 80px 0;
            position: relative;
            overflow: hidden;
        }
        
        .cta-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" preserveAspectRatio="none"><path d="M0,0 L100,0 L100,100 Z" fill="rgba(255,255,255,0.1)"/></svg>');
            background-size: cover;
        }
        
        .cta-title {
            font-weight: 800;
            font-size: 2.5rem;
            margin-bottom: 20px;
        }
        
        .footer {
            background: var(--dark-color);
            color: white;
            padding: 70px 0 30px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.1rem;
            box-shadow: 0 4px 15px rgba(90, 75, 255, 0.3);
        }
        
        .about-image {
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(90, 75, 255, 0.15);
            transition: all 0.4s ease;
        }
        
        .about-image:hover {
            transform: scale(1.02);
        }
        
        .highlight-text {
            color: var(--primary-color);
            font-weight: 700;
        }
        
        .tech-icon {
            font-size: 3rem;
            color: var(--primary-color);
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .hero-title {
                font-size: 2.5rem;
            }
            
            .section-title {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-light fixed-top">
        <div class="container">
            <!-- Logo -->
            <a class="navbar-brand" href="#">
                <i class="fas fa-keyboard"></i>GadgetMart
            </a>
            
            <!-- Mobile Toggle -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <!-- Navigation Links -->
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="#home">
                            <i class="fas fa-home me-1"></i> Home
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#about">
                            <i class="fas fa-info-circle me-1"></i> About
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#products">
                            <i class="fas fa-mouse me-1"></i> Products
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#contact">
                            <i class="fas fa-envelope me-1"></i> Contact
                        </a>
                    </li>
                    
                    <?php if ($isLoggedIn): ?>
                        <!-- User is logged in - Show User Menu -->
                        <li class="nav-item dropdown ms-3">
                            <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="userDropdown" 
                               data-bs-toggle="dropdown" aria-expanded="false">
                                <div class="user-avatar me-2">
                                    <?php echo strtoupper(substr($userName, 0, 1)); ?>
                                </div>
                                <span class="fw-600"><?php echo htmlspecialchars($userName); ?></span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><h6 class="dropdown-header">Welcome back!</h6></li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item" href="dashboard.php">
                                        <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="profile.php">
                                        <i class="fas fa-user-circle me-2"></i> My Profile
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="logout.php">
                                        <i class="fas fa-sign-out-alt me-2"></i> Logout
                                    </a>
                                </li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <!-- User is NOT logged in - Show Login/Shop -->
                        <li class="nav-item ms-3">
                            <a href="login.php" class="btn btn-primary">
                                <i class="fas fa-shopping-cart me-2"></i> Shop Now
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section id="home" class="hero-section pt-5">
        <div class="container position-relative">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h1 class="hero-title">Welcome to GadgetMart</h1>
                    <p class="hero-subtitle">
                        Your go-to store for quality tech accessories. Founded in 2023, we're passionate about 
                        bringing you the best keyboards, mice, and gaming gear to enhance your setup.
                    </p>
                    <div class="d-flex flex-wrap gap-4">
                        <a href="#products" class="btn btn-primary btn-lg px-5 py-3">
                            <i class="fas fa-rocket me-2"></i> Explore Products
                        </a>
                        <a href="login.php" class="btn btn-outline-primary btn-lg px-5 py-3">
                            <i class="fas fa-shopping-bag me-2"></i> Start Shopping
                        </a>
                    </div>
                </div>
                <div class="col-lg-6 text-center">
                    <img src="https://cdn-icons-png.flaticon.com/512/3067/3067256.png" 
                         alt="GadgetMart" class="img-fluid" style="max-height: 350px;">
                </div>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="py-5" style="background: #f8f9ff;">
        <div class="container py-5">
            <h2 class="section-title">Our Story</h2>
            
            <div class="row align-items-center mb-5">
                <div class="col-lg-6 mb-4 mb-lg-0">
                    <div class="text-center">
                        <div class="tech-icon">
                            <i class="fas fa-laptop-code"></i>
                        </div>
                        <h3 class="mb-4">Born from Passion</h3>
                        <p class="lead">
                            GadgetMart started in 2023 when three tech enthusiasts decided to turn their 
                            passion for gaming and productivity gear into a business.
                        </p>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card feature-card">
                        <div class="card-body">
                            <h3 class="mb-4">The Beginning</h3>
                            <p class="mb-4">
                                We launched GadgetMart in early 2023 with a simple mission: to provide 
                                high-quality, affordable tech accessories to gamers, programmers, and 
                                remote workers.
                            </p>
                            <p class="mb-4">
                                What started as a small online store has grown into a community of 
                                <span class="highlight-text">thousands of satisfied customers</span> who trust 
                                us for their essential tech gear.
                            </p>
                            <div class="row mt-4">
                                <div class="col-6 text-center">
                                    <div class="feature-icon mb-3" style="width: 60px; height: 60px;">
                                        <i class="fas fa-calendar-alt"></i>
                                    </div>
                                    <h4>Founded</h4>
                                    <p class="mb-0">2023</p>
                                </div>
                                <div class="col-6 text-center">
                                    <div class="feature-icon mb-3" style="width: 60px; height: 60px;">
                                        <i class="fas fa-users"></i>
                                    </div>
                                    <h4>Customers</h4>
                                    <p class="mb-0">5,000+</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Founders Section -->
            <h3 class="text-center mb-5" style="font-weight: 700; font-size: 2rem;">Meet Our Founders</h3>
            
            <div class="row g-4">
                <div class="col-lg-4 col-md-6">
                    <div class="founder-card">
                        <div class="founder-avatar">
                            LL
                        </div>
                        <h4 class="mb-2">Lester Liau</h4>
                        <p class="text-muted mb-3">Co-Founder & CEO</p>
                        <p class="mb-0">
                            Tech enthusiast with a background in e-commerce and software development. 
                            Oversees operations and product strategy.
                        </p>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <div class="founder-card">
                        <div class="founder-avatar">
                            NK
                        </div>
                        <h4 class="mb-2">Natalie Koa</h4>
                        <p class="text-muted mb-3">Co-Founder & Marketing Director</p>
                        <p class="mb-0">
                            Marketing specialist with experience in tech branding. Manages customer 
                            relationships and brand development.
                        </p>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <div class="founder-card">
                        <div class="founder-avatar">
                            CG
                        </div>
                        <h4 class="mb-2">Chee Guan</h4>
                        <p class="text-muted mb-3">Co-Founder & Product Manager</p>
                        <p class="mb-0">
                            Hardware expert who tests and selects every product. Ensures quality and 
                            stays updated with the latest tech trends.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Products Section -->
    <section id="products" class="py-5">
        <div class="container py-5">
            <h2 class="section-title">What We Sell</h2>
            <p class="text-center mb-5 lead" style="max-width: 800px; margin: 0 auto;">
                Premium tech accessories for gamers, programmers, and remote workers
            </p>
            
            <div class="row g-4">
                <div class="col-lg-3 col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-keyboard"></i>
                        </div>
                        <h3 class="h4 mb-3">Keyboards</h3>
                        <p class="mb-0">
                            Mechanical, membrane, and wireless keyboards for every need and budget.
                        </p>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-mouse"></i>
                        </div>
                        <h3 class="h4 mb-3">Mice & Mousepads</h3>
                        <p class="mb-0">
                            Gaming mice, ergonomic office mice, and high-quality mousepads.
                        </p>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-headphones"></i>
                        </div>
                        <h3 class="h4 mb-3">Audio Gear</h3>
                        <p class="mb-0">
                            Headsets, gaming headphones, and streaming microphones.
                        </p>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-gamepad"></i>
                        </div>
                        <h3 class="h4 mb-3">Gaming Accessories</h3>
                        <p class="mb-0">
                            Controllers, gaming chairs, monitor stands, and cable management.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section">
        <div class="container position-relative">
            <div class="row text-center">
                <div class="col-lg-8 mx-auto">
                    <h2 class="cta-title">Ready to Upgrade Your Setup?</h2>
                    <p class="lead mb-5" style="opacity: 0.9;">
                        Join thousands of tech enthusiasts who trust GadgetMart for their essential gear. 
                        Create an account today and get 10% off your first order!
                    </p>
                    <a href="login.php" class="btn btn-light btn-lg px-5 py-3" style="font-size: 1.1rem;">
                        <i class="fas fa-shopping-bag me-2"></i> Shop Tech Accessories
                    </a>
                    <p class="mt-4 small">
                        First-time customer? <a href="login.php" class="text-white fw-bold">Sign up in 30 seconds</a>
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact" class="py-5">
        <div class="container py-5">
            <h2 class="section-title">Get In Touch</h2>
            
            <div class="row">
                <div class="col-lg-8 mx-auto">
                    <div class="card border-0 shadow-lg">
                        <div class="card-body p-5">
                            <div class="row text-center mb-4">
                                <div class="col-md-6 mb-4">
                                    <div class="feature-icon mb-3" style="width: 60px; height: 60px; margin: 0 auto;">
                                        <i class="fas fa-envelope"></i>
                                    </div>
                                    <h4>Email Us</h4>
                                    <p class="mb-0">support@techhub.com</p>
                                </div>
                                <div class="col-md-6 mb-4">
                                    <div class="feature-icon mb-3" style="width: 60px; height: 60px; margin: 0 auto;">
                                        <i class="fas fa-phone"></i>
                                    </div>
                                    <h4>Call Us</h4>
                                    <p class="mb-0">(555) TECH-HUB</p>
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <h4 class="mb-4 text-center">Send us a message</h4>
                                <form>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <input type="text" class="form-control form-control-lg" placeholder="Your Name" required>
                                        </div>
                                        <div class="col-md-6">
                                            <input type="email" class="form-control form-control-lg" placeholder="Your Email" required>
                                        </div>
                                        <div class="col-12">
                                            <textarea class="form-control form-control-lg" rows="4" placeholder="Your Message" required></textarea>
                                        </div>
                                        <div class="col-12 text-center">
                                            <button type="submit" class="btn btn-primary btn-lg px-5">
                                                Send Message
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 mb-4">
                    <h3 class="mb-4">
                        <i class="fas fa-keyboard me-2"></i>GadgetMart
                    </h3>
                    <p>Your trusted source for quality tech accessories since 2023. Founded by Lester Liau, Natalie Koa, and Chee Guan.</p>
                    <div class="d-flex gap-3 mt-4">
                        <a href="#" class="text-light"><i class="fab fa-facebook fa-lg"></i></a>
                        <a href="#" class="text-light"><i class="fab fa-twitter fa-lg"></i></a>
                        <a href="#" class="text-light"><i class="fab fa-instagram fa-lg"></i></a>
                        <a href="#" class="text-light"><i class="fab fa-discord fa-lg"></i></a>
                    </div>
                </div>
                <div class="col-lg-2 col-6 mb-4">
                    <h5 class="mb-3">Shop</h5>
                    <ul class="list-unstyled">
                        <li><a href="#products" class="text-light text-decoration-none">Keyboards</a></li>
                        <li><a href="#products" class="text-light text-decoration-none">Mice</a></li>
                        <li><a href="#products" class="text-light text-decoration-none">Audio</a></li>
                        <li><a href="#products" class="text-light text-decoration-none">Gaming Gear</a></li>
                    </ul>
                </div>
                <div class="col-lg-2 col-6 mb-4">
                    <h5 class="mb-3">Support</h5>
                    <ul class="list-unstyled">
                        <li><a href="#faq" class="text-light text-decoration-none">FAQ</a></li>
                        <li><a href="#shipping" class="text-light text-decoration-none">Shipping</a></li>
                        <li><a href="#returns" class="text-light text-decoration-none">Returns</a></li>
                        <li><a href="#contact" class="text-light text-decoration-none">Contact</a></li>
                    </ul>
                </div>
                <div class="col-lg-4 mb-4">
                    <h5 class="mb-3">Join Our Community</h5>
                    <p>Subscribe for product launches, tech tips, and exclusive discounts.</p>
                    <div class="input-group">
                        <input type="email" class="form-control" placeholder="Enter your email">
                        <button class="btn btn-primary" type="button">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </div>
                </div>
            </div>
            <hr class="mt-4 mb-4" style="opacity: 0.2;">
            <div class="text-center">
                <p class="mb-0">&copy; 2023-<?php echo date('Y'); ?> GadgetMart. All rights reserved.</p>
                <p class="mb-0 small mt-2" style="opacity: 0.7;">Founded by Lester Liau, Natalie Koa, and Chee Guan</p>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script>
        // Navbar scroll effect
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });
        
        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const targetId = this.getAttribute('href');
                if (targetId === '#') return;
                
                const targetElement = document.querySelector(targetId);
                if (targetElement) {
                    window.scrollTo({
                        top: targetElement.offsetTop - 80,
                        behavior: 'smooth'
                    });
                }
            });
        });
        
        // Simple form submission
        document.querySelector('form')?.addEventListener('submit', function(e) {
            e.preventDefault();
            alert('Thank you for your message! We\'ll get back to you soon.');
            this.reset();
        });
    </script>
</body>
</html>