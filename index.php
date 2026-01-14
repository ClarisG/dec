<?php
// index.php - Updated Landing Page
session_start();

// Set base URL for absolute paths
$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
$current_path = dirname($_SERVER['PHP_SELF']);
if ($current_path != '/') {
    $base_url .= $current_path;
}

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'];
    switch($role) {
        case 'citizen': header("Location: citizen_dash/dashboard.php"); exit;
        case 'tanod': header("Location: tanod_dash/dashboard.php"); exit;
        case 'secretary': header("Location: secretary_dash/dashboard.php"); exit;
        case 'captain': header("Location: captain_dash/dashboard.php"); exit;
        case 'admin': header("Location: admin_dash/dashboard.php"); exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LEIR</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" type="image/png" href="images/10213.png">
    <style>
        :root {
            --primary-blue: #1a4f8c;
            --secondary-blue: #2a6bb0;
            --accent-green: #28a745;
            --accent-orange: #fd7e14;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            overflow-x: hidden;
        }
        
        /* Custom animations */
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        
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
        
        .animate-float {
            animation: float 3s ease-in-out infinite;
        }
        
        .animate-fadeInUp {
            animation: fadeInUp 0.8s ease-out;
        }
        
        /* Loading Screen Animations */
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        
        /* Glass effect */
        .glass-effect {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        /* Progress bar animation */
        .progress-bar {
            height: 6px;
            background: linear-gradient(90deg, #1a4f8c, #2a6bb0);
            border-radius: 3px;
            position: relative;
            overflow: hidden;
        }
        
        .progress-bar::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            height: 100%;
            width: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
            animation: shimmer 2s infinite;
        }
        
        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }
        
        /* Hover effects */
        .hover-lift {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .hover-lift:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
        }
        
        /* Map container */
        .map-container {
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        
        /* Timeline steps */
        .timeline-step {
            position: relative;
        }
        
        .timeline-step::before {
            content: '';
            position: absolute;
            width: 2px;
            height: 100%;
            background: #e5e7eb;
            left: 24px;
            top: 40px;
        }
        
        .timeline-step:last-child::before {
            display: none;
        }
        
        /* Touch feedback */
        .touch-feedback:active {
            transform: scale(0.98);
            transition: transform 0.1s;
        }
        
        /* Logo Animation */
        .logo-float {
            animation: float 4s ease-in-out infinite;
        }
        
        /* Footer specific styles */
        .footer-divider {
            color: rgba(255, 255, 255, 0.5);
            padding: 0 8px;
        }
        
        .footer-link {
            transition: color 0.2s ease;
        }
        
        .footer-link:hover {
            color: white;
        }
        
        /* Custom Link Styling */
        .nav-link {
            position: relative;
            padding: 5px 0;
        }
        
        .nav-link::after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: 0;
            left: 0;
            background-color: var(--secondary-blue);
            transition: width 0.3s ease;
        }
        
        .nav-link:hover::after {
            width: 100%;
        }
        
        /* Active link */
        .nav-link.active {
            color: var(--primary-blue);
            font-weight: 600;
        }
        
        .nav-link.active::after {
            width: 100%;
        }

        /* Loading Screen Styles */
        #leir-loading-screen {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 99999;
            transition: opacity 0.8s ease-out;
        }

        .loading-content {
            text-align: center;
            position: relative;
        }

        .loading-logo-container {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 2rem;
        }

        .loading-logo-wrapper {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 120px;
            height: 120px;
        }

        .loading-logo-image {
            width: 100%;
            height: 100%;
            object-fit: contain;
            filter: brightness(1) contrast(1);
        }

        .loading-dots {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            margin-top: 1.5rem;
        }

        .loading-dot {
            width: 0.75rem;
            height: 0.75rem;
            background: linear-gradient(to right, #1a4f8c, #2a6bb0);
            border-radius: 50%;
            animation: bounce 1.2s ease-in-out infinite;
        }

        .loading-dot:nth-child(2) {
            animation-delay: 0.2s;
        }

        .loading-dot:nth-child(3) {
            animation-delay: 0.4s;
        }
        
        /* RESPONSIVE IMPROVEMENTS */
        
        /* Extra Small Devices (Phones, less than 576px) */
        @media (max-width: 575.98px) {
            .hero-title {
                font-size: 1.75rem !important;
                line-height: 1.3 !important;
            }
            
            .section-title {
                font-size: 1.5rem !important;
            }
            
            .section-padding {
                padding-top: 2rem !important;
                padding-bottom: 2rem !important;
            }
            
            .nav-logo-container h1 {
                font-size: 0.875rem !important;
            }
            
            .map-container iframe {
                height: 250px !important;
            }
            
            .feature-card {
                padding: 1rem !important;
            }
            
            .footer-links {
                flex-direction: column !important;
                gap: 0.5rem !important;
            }
            
            .footer-divider {
                display: none !important;
            }
        }
        
        /* Small Devices (Phones, 576px and up) */
        @media (min-width: 576px) and (max-width: 767.98px) {
            .hero-title {
                font-size: 2rem !important;
            }
            
            .section-title {
                font-size: 1.75rem !important;
            }
            
            .nav-logo-container h1 {
                font-size: 1rem !important;
            }
        }
        
        /* Medium Devices (Tablets, 768px and up) */
        @media (min-width: 768px) and (max-width: 991.98px) {
            .hero-title {
                font-size: 2.5rem !important;
            }
            
            .section-title {
                font-size: 2rem !important;
            }
            
            .feature-grid {
                grid-template-columns: repeat(2, 1fr) !important;
            }
            
            .nav-links-tablet {
                gap: 1rem !important;
            }
            
            .nav-button-tablet {
                padding-left: 0.75rem !important;
                padding-right: 0.75rem !important;
                font-size: 0.875rem !important;
            }
        }
        
        /* Large Devices (Desktops, 992px and up) */
        @media (min-width: 992px) and (max-width: 1199.98px) {
            .hero-title {
                font-size: 3rem !important;
            }
            
            .container {
                max-width: 960px !important;
            }
        }
        
        /* Extra Large Devices (Large desktops, 1200px and up) */
        @media (min-width: 1200px) {
            .container {
                max-width: 1140px !important;
            }
        }
        
        /* Mobile-specific optimizations */
        @media (max-width: 767.98px) {
            .mobile-full-width {
                width: 100% !important;
            }
            
            .mobile-text-center {
                text-align: center !important;
            }
            
            .mobile-stack {
                flex-direction: column !important;
            }
            
            .mobile-gap-4 {
                gap: 1rem !important;
            }
            
            .mobile-mb-4 {
                margin-bottom: 1rem !important;
            }
            
            .mobile-p-4 {
                padding: 1rem !important;
            }
            
            .mobile-hidden {
                display: none !important;
            }
            
            .mobile-block {
                display: block !important;
            }
            
            /* Improved mobile timeline */
            .mobile-timeline-step {
                position: relative;
                padding-left: 3rem;
                margin-bottom: 2rem;
            }
            
            .mobile-timeline-step:last-child {
                margin-bottom: 0;
            }
            
            .mobile-timeline-step::before {
                content: '';
                position: absolute;
                left: 1.5rem;
                top: 0;
                bottom: -2rem;
                width: 2px;
                background: #e5e7eb;
            }
            
            .mobile-timeline-step:last-child::before {
                display: none;
            }
            
            .mobile-timeline-icon {
                position: absolute;
                left: 0;
                top: 0;
                width: 3rem;
                height: 3rem;
                border-radius: 50%;
                background: white;
                display: flex;
                align-items: center;
                justify-content: center;
                border: 2px solid #e5e7eb;
                z-index: 2;
            }
            
            /* Better mobile touch targets */
            .mobile-touch-target {
                min-height: 44px;
                min-width: 44px;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            /* Mobile safe areas */
            .mobile-safe-top {
                padding-top: env(safe-area-inset-top) !important;
            }
            
            .mobile-safe-bottom {
                padding-bottom: env(safe-area-inset-bottom) !important;
            }
        }
        
        /* Tablet-specific optimizations */
        @media (min-width: 768px) and (max-width: 1024px) {
            .tablet-full-width {
                width: 100% !important;
            }
            
            .tablet-text-center {
                text-align: center !important;
            }
            
            .tablet-gap-6 {
                gap: 1.5rem !important;
            }
            
            .tablet-p-6 {
                padding: 1.5rem !important;
            }
            
            .tablet-mb-6 {
                margin-bottom: 1.5rem !important;
            }
            
            /* Tablet timeline adjustments */
            .tablet-timeline-container {
                padding-left: 2rem;
                padding-right: 2rem;
            }
            
            .tablet-timeline-step {
                padding: 1.5rem;
            }
        }
        
        /* Desktop-specific optimizations */
        @media (min-width: 1025px) {
            .desktop-only {
                display: block !important;
            }
            
            .mobile-only {
                display: none !important;
            }
            
            /* Smooth desktop hover effects */
            .desktop-hover-effect {
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            }
            
            .desktop-hover-effect:hover {
                transform: translateY(-4px);
                box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            }
            
            /* Desktop header logo positioning */
            .desktop-logo-container {
                margin-right: 2rem !important;
            }
        }
        
        /* Print styles */
        @media print {
            .no-print {
                display: none !important;
            }
            
            .print-only {
                display: block !important;
            }
            
            body {
                font-size: 12pt !important;
                line-height: 1.5 !important;
                color: black !important;
                background: white !important;
            }
            
            .hero-section, .feature-section, .timeline-section {
                break-inside: avoid;
                page-break-inside: avoid;
            }
        }
        
        /* High-resolution displays */
        @media (-webkit-min-device-pixel-ratio: 2), (min-resolution: 192dpi) {
            .high-res-image {
                background-image: url('images/logo@2x.png');
                background-size: contain;
            }
        }
        
        /* Dark mode support */
        @media (prefers-color-scheme: dark) {
            .dark-mode-support {
                background-color: #1a202c;
                color: #e2e8f0;
            }
            
            .dark-mode-support .bg-gray-50 {
                background-color: #2d3748 !important;
            }
            
            .dark-mode-support .text-gray-800 {
                color: #e2e8f0 !important;
            }
            
            .dark-mode-support .text-gray-600 {
                color: #a0aec0 !important;
            }
        }
        
        /* Reduced motion preferences */
        @media (prefers-reduced-motion: reduce) {
            * {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }
    </style>
</head>
<body class="bg-gray-50 dark-mode-support">
    <!-- Loading Screen -->
    <div id="leir-loading-screen">
        <div class="loading-content">
            <div class="loading-logo-container">
                <div class="loading-logo-wrapper">
                    <img src="images/10213.png" alt="LEIR Logo" class="loading-logo-image">
                </div>
            </div>
            <div class="loading-dots">
                <div class="loading-dot"></div>
                <div class="loading-dot"></div>
                <div class="loading-dot"></div>
            </div>
        </div>
    </div>

    <!-- Header with Navigation -->
    <header class="sticky top-0 z-50 bg-white shadow-md mobile-safe-top">
        <div class="container mx-auto px-4">
            <nav class="flex justify-between items-center py-4">
                <!-- Logo and Title Container - Updated for desktop spacing -->
                <div class="flex items-center space-x-1 desktop-logo-container">
                    <img src="images/10213.png"
                    alt="Law Enforcement Logo"
                    class="w-12 h-14 sm:w-14 sm:h-16"
                    />
                    <div class="mobile-hidden sm:block">
                        <h1 class="text-lg sm:text-xl md:text-1x1 font-bold text-blue-800">Law Enforcement and Incident Report</h1>
                    </div>
                    <div class="sm:hidden">
                        <h1 class="text-base font-bold text-blue-800">Law Enforcement and Incident Report</h1>
                    </div>
                </div>
                    
                <!-- Desktop Navigation - Updated spacing -->
                <div class="hidden md:flex items-center space-x-6 lg:space-x-8 xl:space-x-5 nav-links-tablet">
                    <a href="#home" class="nav-link text-blue-800 font-medium text-sm lg:text-base xl:text-lg mobile-touch-target">Home</a>
                    <a href="#about" class="nav-link text-gray-700 font-medium text-sm lg:text-base xl:text-lg mobile-touch-target">About Us</a>
                    <a href="#how-it-works" class="nav-link text-gray-700 font-medium text-sm lg:text-base xl:text-lg mobile-touch-target">How It Works</a>
                    <a href="#contact" class="nav-link text-gray-700 font-medium text-sm lg:text-base xl:text-lg mobile-touch-target">Contact</a>
                    
                    <div class="flex items-center space-x-4 lg:space-x-6 xl:space-x-8 ml-4 lg:ml-8 xl:ml-12">
                        <a href="login.php" 
                           class="bg-gradient-to-r from-blue-600 to-blue-800 hover:from-blue-700 hover:to-blue-900 text-white px-4 py-2 lg:px-5 lg:py-2 xl:px-6 xl:py-2 rounded-full font-medium transition-all duration-300 shadow-md hover:shadow-lg text-sm lg:text-base xl:text-base desktop-hover-effect mobile-touch-target nav-button-tablet"
                           id="desktop-login-btn">
                            Login
                        </a>
                        <a href="register.php" 
                           class="bg-gradient-to-r from-blue-600 to-blue-800 hover:from-blue-700 hover:to-blue-900 text-white px-4 py-2 lg:px-5 lg:py-2 xl:px-6 xl:py-2 rounded-full font-medium transition-all duration-300 shadow-md hover:shadow-lg text-sm lg:text-base xl:text-base desktop-hover-effect mobile-touch-target nav-button-tablet"
                           id="desktop-register-btn">
                            Register
                        </a>
                    </div>
                </div>
                    
                <!-- Mobile Menu Button -->
                <button id="mobileMenuBtn" class="md:hidden text-blue-800 text-2xl mobile-touch-target" aria-label="Toggle menu">
                    <i class="fas fa-bars"></i>
                </button>
            </nav>
        </div>
            
        <!-- Mobile Menu -->
        <div id="mobileMenu" class="md:hidden bg-white shadow-lg hidden">
            <div class="container mx-auto px-4 py-6 space-y-3">
                <a href="#home" class="block text-blue-800 font-medium py-3 text-lg mobile-touch-target">Home</a>
                <a href="#about" class="block text-gray-700 font-medium py-3 text-lg mobile-touch-target">About Us</a>
                <a href="#how-it-works" class="block text-gray-700 font-medium py-3 text-lg mobile-touch-target">How It Works</a>
                <a href="#contact" class="block text-gray-700 font-medium py-3 text-lg mobile-touch-target">Contact</a>
                
                <div class="pt-4 border-t space-y-3 mobile-p-4">
                    <a href="login.php" 
                       class="block text-center border border-blue-600 text-blue-600 hover:bg-blue-50 font-medium py-3 rounded-lg text-lg mobile-full-width mobile-touch-target"
                       id="mobile-login-btn">
                        Login
                    </a>
                    <a href="register.php" 
                       class="block text-center bg-gradient-to-r from-blue-600 to-blue-800 hover:from-blue-700 hover:to-blue-900 text-white py-3 rounded-lg font-medium transition-all duration-300 text-lg mobile-full-width mobile-touch-target"
                       id="mobile-register-btn">
                        Register
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section id="home" class="relative bg-gradient-to-br from-blue-900 via-blue-800 to-blue-700 text-white overflow-hidden section-padding">
        <!-- Background Elements -->
        <div class="absolute inset-0 overflow-hidden">
            <div class="absolute -top-40 -right-40 w-80 h-80 bg-blue-500 rounded-full opacity-10"></div>
            <div class="absolute -bottom-40 -left-40 w-80 h-80 bg-blue-400 rounded-full opacity-10"></div>
            <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-96 h-96 bg-blue-300 rounded-full opacity-5"></div>
        </div>
        
        <div class="container mx-auto px-4 py-12 sm:py-16 md:py-20 lg:py-24 xl:py-32 relative z-10">
            <div class="max-w-4xl mx-auto text-center animate-fadeInUp mobile-text-center">                
                <h1 class="hero-title text-2xl sm:text-3xl md:text-4xl lg:text-5xl xl:text-6xl font-bold leading-tight mb-4 md:mb-6">
                    <span class="block sm:inline">Your Barangay, Digitized.</span><br>
                    <span class="text-green-200 block sm:inline">Secure Reporting,</span><br>
                    <span class="block sm:inline">Real-Time Resolution.</span>
                </h1>
                
                <p class="text-base sm:text-lg md:text-xl text-blue-100 mb-6 md:mb-8 max-w-3xl mx-auto px-2 sm:px-4">
                    Modernizing the Katarungang Pambarangay process for a safer, more transparent community. Fully compliant with RA 7160 standards for digital governance.
                </p>
                
            </div>
        </div>
        
        <!-- Wave separator -->
        <div class="absolute bottom-0 left-0 right-0">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 100" preserveAspectRatio="none" class="w-full h-16 sm:h-20 md:h-24">
                <path fill="#fff" fill-opacity="1" d="M0,80L48,75C96,70,192,60,288,55C384,50,480,50,576,55C672,60,768,70,864,75C960,80,1056,80,1152,75C1248,70,1344,60,1392,55L1440,50L1440,100L1392,100C1344,100,1248,100,1152,100C1056,100,960,100,864,100C768,100,672,100,576,100C480,100,384,100,288,100C192,100,96,100,48,100L0,100Z"></path>
            </svg>
        </div>
    </section>

    <!-- Why Choose LEIR Section -->
    <section id="about" class="py-8 sm:py-12 md:py-16 lg:py-20 bg-white section-padding">
        <div class="container mx-auto px-4 sm:px-6">
            <div class="text-center mb-8 sm:mb-10 md:mb-12 lg:mb-16 mobile-mb-4">
                <h2 class="section-title text-2xl sm:text-3xl md:text-4xl font-bold text-black-800 mb-3 md:mb-4 mobile-text-center">Why Choose LEIR?</h2>
                <p class="text-base sm:text-lg md:text-xl text-gray-600 max-w-3xl mx-auto px-2 sm:px-4 mobile-text-center">
                    Designed to differentiate from traditional reporting, our system offers intelligent routing and enhanced security protocols.
                </p>
            </div>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6 md:gap-8 feature-grid">
                <!-- Feature 1 -->
                <div class="bg-gray-50 rounded-2xl p-4 sm:p-6 md:p-8 hover-lift animate-fadeInUp desktop-hover-effect feature-card">
                    <div class="w-12 h-12 sm:w-14 sm:h-14 md:w-16 md:h-16 bg-blue-100 rounded-xl flex items-center justify-center mb-4 md:mb-6 mx-auto sm:mx-0">
                        <i class="fas fa-brain text-blue-600 text-lg sm:text-xl md:text-2xl"></i>
                    </div>
                    <h3 class="text-lg sm:text-xl md:text-2xl font-bold text-gray-800 mb-3 md:mb-4 mobile-text-center sm:text-left">Smart Classification</h3>
                    <p class="text-gray-600 text-sm sm:text-base md:text-base mobile-text-center sm:text-left">
                        Automatically routes reports to Police or Barangay jurisdiction based on legal criteria and keyword analysis.
                    </p>
                </div>
                
                <!-- Feature 2 -->
                <div class="bg-gray-50 rounded-2xl p-4 sm:p-6 md:p-8 hover-lift animate-fadeInUp desktop-hover-effect feature-card" style="animation-delay: 0.2s;">
                    <div class="w-12 h-12 sm:w-14 sm:h-14 md:w-16 md:h-16 bg-green-100 rounded-xl flex items-center justify-center mb-4 md:mb-6 mx-auto sm:mx-0">
                        <i class="fas fa-map-marker-alt text-green-600 text-lg sm:text-xl md:text-2xl"></i>
                    </div>
                    <h3 class="text-lg sm:text-xl md:text-2xl font-bold text-gray-800 mb-3 md:mb-4 mobile-text-center sm:text-left">Live Tracking</h3>
                    <p class="text-gray-600 text-sm sm:text-base md:text-base mobile-text-center sm:text-left">
                        Track your report status from 'Submitted' to 'Resolution' in real-time with transparent milestone updates.
                    </p>
                </div>
                
                <!-- Feature 3 -->
                <div class="bg-gray-50 rounded-2xl p-4 sm:p-6 md:p-8 hover-lift animate-fadeInUp desktop-hover-effect feature-card sm:col-span-2 lg:col-span-1" style="animation-delay: 0.4s;">
                    <div class="w-12 h-12 sm:w-14 sm:h-14 md:w-16 md:h-16 bg-purple-100 rounded-xl flex items-center justify-center mb-4 md:mb-6 mx-auto sm:mx-0">
                        <i class="fas fa-user-shield text-purple-600 text-lg sm:text-xl md:text-2xl"></i>
                    </div>
                    <h3 class="text-lg sm:text-xl md:text-2xl font-bold text-gray-800 mb-3 md:mb-4 mobile-text-center sm:text-left">PIN-Protected Privacy</h3>
                    <p class="text-gray-600 text-sm sm:text-base md:text-base mobile-text-center sm:text-left">
                        End-to-end encryption using a personal 4-digit PIN. Only you control who views your sensitive data.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works Section -->
    <section id="how-it-works" class="py-8 sm:py-12 md:py-16 lg:py-20 bg-gradient-to-br from-blue-50 to-gray-100 section-padding">
        <div class="container mx-auto px-4 sm:px-6">
            <div class="text-center mb-8 sm:mb-10 md:mb-12 lg:mb-16 mobile-mb-4">
                <h2 class="section-title text-2xl sm:text-3xl md:text-4xl font-bold text-gray-800 mb-3 md:mb-4 mobile-text-center">Simple, Secure, & Fast</h2>
                <p class="text-base sm:text-lg md:text-xl text-gray-600 max-w-3xl mx-auto px-2 sm:px-4 mobile-text-center">
                    Our streamlined process ensures your concerns are addressed efficiently and securely.
                </p>
            </div>

            <!-- Desktop Timeline -->
            <div class="hidden md:block max-w-6xl xl:max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 tablet-timeline-container">
                <div class="flex justify-center items-start relative pt-8 lg:pt-10">
                    <div id="main-flow-line" class="absolute top-20 lg:top-24 w-2/3 h-0.5 bg-gray-300 transition-colors duration-300"></div>
                    
                    <div id="line-segment-1-2" class="absolute top-20 lg:top-24 w-1/3 h-0.5 bg-transparent left-1/2 -translate-x-full transition-colors duration-300"></div>
                    <div id="line-segment-2-3" class="absolute top-20 lg:top-24 w-1/3 h-0.5 bg-transparent left-1/2 transition-colors duration-300"></div>

                    <div class="flex flex-col items-center w-1/3 group cursor-pointer tablet-timeline-step" 
                         onmouseover="document.getElementById('main-flow-line').classList.replace('bg-gray-300', 'bg-green-600'); document.getElementById('line-segment-1-2').classList.replace('bg-transparent', 'bg-green-600')" 
                         onmouseout="document.getElementById('main-flow-line').classList.replace('bg-green-600', 'bg-gray-300'); document.getElementById('line-segment-1-2').classList.replace('bg-green-600', 'bg-transparent')">
                        
                        <div class="relative flex flex-col items-center">
                            <div class="w-16 h-16 sm:w-20 sm:h-20 md:w-20 md:h-20 lg:w-24 lg:h-24 rounded-full border-2 border-gray-300 bg-white flex items-center justify-center mb-4 md:mb-6 shadow-sm transition-all duration-300
                                        group-hover:border-green-600 desktop-hover-effect">
                                <i class="fas fa-mobile-alt text-xl sm:text-2xl md:text-2xl lg:text-3xl text-gray-800 transition-colors duration-300 
                                          group-hover:text-green-600"></i>
                            </div>
                            <div class="w-8 h-8 sm:w-10 sm:h-10 md:w-10 md:h-10 lg:w-12 lg:h-12 rounded-full border-2 border-gray-300 bg-white flex items-center justify-center absolute -top-3 sm:-top-4 md:-top-4 lg:-top-5 transition-all duration-300 
                                        group-hover:border-green-600 group-hover:shadow-lg desktop-hover-effect">
                                <span class="text-xs sm:text-sm font-bold text-gray-800 transition-colors duration-300 
                                             group-hover:text-green-600">01</span>
                            </div>
                        </div>

                        <h3 class="text-lg sm:text-xl font-bold text-gray-900 mb-2 transition-colors duration-300 group-hover:text-green-600">Report</h3>
                        <p class="text-gray-600 max-w-xs text-sm sm:text-base lg:text-base mobile-text-center">
                            Submit securely via the Mobile App using your unique 4-digit PIN to encrypt evidence.
                        </p>
                    </div>

                    <div class="flex flex-col items-center w-1/3 group cursor-pointer tablet-timeline-step" 
                         onmouseover="document.getElementById('main-flow-line').classList.replace('bg-gray-300', 'bg-green-600'); document.getElementById('line-segment-1-2').classList.replace('bg-transparent', 'bg-green-600'); document.getElementById('line-segment-2-3').classList.replace('bg-transparent', 'bg-green-600')" 
                         onmouseout="document.getElementById('main-flow-line').classList.replace('bg-green-600', 'bg-gray-300'); document.getElementById('line-segment-1-2').classList.replace('bg-green-600', 'bg-transparent'); document.getElementById('line-segment-2-3').classList.replace('bg-green-600', 'bg-transparent')">
                        
                        <div class="relative flex flex-col items-center">
                            <div class="w-16 h-16 sm:w-20 sm:h-20 md:w-20 md:h-20 lg:w-24 lg:h-24 rounded-full border-2 border-gray-300 bg-white flex items-center justify-center mb-4 md:mb-6 shadow-sm transition-all duration-300
                                        group-hover:border-green-600 desktop-hover-effect">
                                <i class="fas fa-search text-xl sm:text-2xl md:text-2xl lg:text-3xl text-gray-800 transition-colors duration-300 
                                          group-hover:text-green-600"></i>
                            </div>
                            <div class="w-8 h-8 sm:w-10 sm:h-10 md:w-10 md:h-10 lg:w-12 lg:h-12 rounded-full border-2 border-gray-300 bg-white flex items-center justify-center absolute -top-3 sm:-top-4 md:-top-4 lg:-top-5 transition-all duration-300
                                        group-hover:border-green-600 group-hover:shadow-lg desktop-hover-effect">
                                <span class="text-xs sm:text-sm font-bold text-gray-800 transition-colors duration-300 
                                             group-hover:text-green-600">02</span>
                            </div>
                        </div>

                        <h3 class="text-lg sm:text-xl font-bold text-gray-900 mb-2 transition-colors duration-300 group-hover:text-green-600">Track</h3>
                        <p class="text-gray-600 max-w-xs text-sm sm:text-base lg:text-base mobile-text-center">
                            Receive real-time updates as Tanod or Officials verify and process your case.
                        </p>
                    </div>
                    
                    <div class="flex flex-col items-center w-1/3 group cursor-pointer tablet-timeline-step"
                         onmouseover="document.getElementById('main-flow-line').classList.replace('bg-gray-300', 'bg-green-600'); document.getElementById('line-segment-2-3').classList.replace('bg-transparent', 'bg-green-600')" 
                         onmouseout="document.getElementById('main-flow-line').classList.replace('bg-green-600', 'bg-gray-300'); document.getElementById('line-segment-2-3').classList.replace('bg-green-600', 'bg-transparent')">
                        
                        <div class="relative flex flex-col items-center">
                            <div class="w-16 h-16 sm:w-20 sm:h-20 md:w-20 md:h-20 lg:w-24 lg:h-24 rounded-full border-2 border-gray-300 bg-white flex items-center justify-center mb-4 md:mb-6 shadow-sm transition-all duration-300
                                        group-hover:border-green-600 desktop-hover-effect">
                                <i class="fas fa-gavel text-xl sm:text-2xl md:text-2xl lg:text-3xl text-gray-800 transition-colors duration-300 
                                          group-hover:text-green-600"></i>
                            </div>
                            <div class="w-8 h-8 sm:w-10 sm:h-10 md:w-10 md:h-10 lg:w-12 lg:h-12 rounded-full border-2 border-gray-300 bg-white flex items-center justify-center absolute -top-3 sm:-top-4 md:-top-4 lg:-top-5 transition-all duration-300
                                        group-hover:border-green-600 group-hover:shadow-lg desktop-hover-effect">
                                <span class="text-xs sm:text-sm font-bold text-gray-800 transition-colors duration-300 
                                             group-hover:text-green-600">03</span>
                            </div>
                        </div>

                        <h3 class="text-lg sm:text-xl font-bold text-gray-900 mb-2 transition-colors duration-300 group-hover:text-green-600">Resolve</h3>
                        <p class="text-gray-600 max-w-xs text-sm sm:text-base lg:text-base mobile-text-center">
                            Receive official resolution or referral updates digitally via the secure dashboard.
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Mobile Timeline -->
            <div class="md:hidden">
                <div class="max-w-md mx-auto">
                    <div class="mobile-timeline-step">
                        <div class="mobile-timeline-icon">
                            <i class="fas fa-mobile-alt text-blue-600 text-lg"></i>
                        </div>
                        <div class="ml-4">
                            <div class="w-8 h-8 rounded-full border-2 border-blue-600 bg-white flex items-center justify-center mb-2">
                                <span class="text-xs font-bold text-blue-600">01</span>
                            </div>
                            <h3 class="text-lg font-bold text-gray-900 mb-2">Report</h3>
                            <p class="text-gray-600 text-sm">
                                Submit securely via the Mobile App using your unique 4-digit PIN to encrypt evidence.
                            </p>
                        </div>
                    </div>
                    
                    <div class="mobile-timeline-step">
                        <div class="mobile-timeline-icon">
                            <i class="fas fa-search text-green-600 text-lg"></i>
                        </div>
                        <div class="ml-4">
                            <div class="w-8 h-8 rounded-full border-2 border-green-600 bg-white flex items-center justify-center mb-2">
                                <span class="text-xs font-bold text-green-600">02</span>
                            </div>
                            <h3 class="text-lg font-bold text-gray-900 mb-2">Track</h3>
                            <p class="text-gray-600 text-sm">
                                Receive real-time updates as Tanod or Officials verify and process your case.
                            </p>
                        </div>
                    </div>
                    
                    <div class="mobile-timeline-step">
                        <div class="mobile-timeline-icon">
                            <i class="fas fa-gavel text-purple-600 text-lg"></i>
                        </div>
                        <div class="ml-4">
                            <div class="w-8 h-8 rounded-full border-2 border-purple-600 bg-white flex items-center justify-center mb-2">
                                <span class="text-xs font-bold text-purple-600">03</span>
                            </div>
                            <h3 class="text-lg font-bold text-gray-900 mb-2">Resolve</h3>
                            <p class="text-gray-600 text-sm">
                                Receive official resolution or referral updates digitally via the secure dashboard.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Commitment Section -->
    <section class="py-8 sm:py-12 md:py-16 lg:py-20 bg-gradient-to-r from-blue-900 to-blue-800 text-white section-padding">
        <div class="container mx-auto px-4 sm:px-6">
            <div class="max-w-4xl mx-auto">
                <div class="text-center mb-6 sm:mb-8 md:mb-10 lg:mb-12">
                    <h2 class="text-2xl sm:text-3xl md:text-4xl font-bold mb-3 sm:mb-4 md:mb-5 lg:mb-6 mobile-text-center">
                        Commitment to 
                        <span class="text-green-200">Justice & Efficiency</span>
                    </h2>
                    <p class="text-base sm:text-lg md:text-xl text-blue-100 mb-4 sm:mb-5 md:mb-6 lg:mb-8 px-2 sm:px-4 mobile-text-center">
                        Our system ensures strict adherence to the <strong>Katarungang Pambarangay Law (RA 7160)</strong>. 
                        We monitor the 15-day resolution timeline to ensure swift justice and prevent case backlogs.
                    </p>
                </div>
                
                <blockquote class="border-l-4 border-blue-300 pl-3 sm:pl-4 md:pl-5 lg:pl-6 py-3 sm:py-4 my-4 sm:my-5 md:my-6 lg:my-8 text-base sm:text-lg md:text-xl italic bg-white/10 rounded-r-2xl p-4 sm:p-5 md:p-6 lg:p-8">
                    <i class="fas fa-quote-left text-blue-300 text-xl sm:text-2xl md:text-2xl lg:text-3xl mb-2 sm:mb-3 md:mb-3 lg:mb-4 block"></i>
                    "Providing a secure, trackable pipeline for all community concerns, bridging the gap between citizens and local governance."
                </blockquote>
                
                <!-- Compliance Badges -->
                <div class="flex flex-col sm:flex-row justify-center items-center gap-3 sm:gap-4 md:gap-5 lg:gap-8 mt-6 sm:mt-7 md:mt-8 lg:mt-12">
                    <div class="flex items-center space-x-2 sm:space-x-3 md:space-x-4 bg-white/10 backdrop-blur-sm p-3 sm:p-4 md:p-5 lg:p-6 rounded-2xl border border-white/20 w-full sm:w-auto tablet-p-6">
                        <div class="w-10 h-10 sm:w-12 sm:h-12 md:w-14 md:h-14 lg:w-16 lg:h-16 bg-white/20 rounded-xl flex items-center justify-center">
                            <i class="fas fa-gavel text-lg sm:text-xl md:text-xl lg:text-2xl"></i>
                        </div>
                        <div>
                            <p class="font-bold text-sm sm:text-base md:text-base lg:text-lg">Data Privacy Act</p>
                        </div>
                    </div>
                    
                    <div class="flex items-center space-x-2 sm:space-x-3 md:space-x-4 bg-white/10 backdrop-blur-sm p-3 sm:p-4 md:p-5 lg:p-6 rounded-2xl border border-white/20 w-full sm:w-auto tablet-p-6">
                        <div class="w-10 h-10 sm:w-12 sm:h-12 md:w-14 md:h-14 lg:w-16 lg:h-16 bg-white/20 rounded-xl flex items-center justify-center">
                            <i class="fas fa-balance-scale text-lg sm:text-xl md:text-xl lg:text-2xl"></i>
                        </div>
                        <div>
                            <p class="font-bold text-sm sm:text-base md:text-base lg:text-lg">Justice and Law</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact" class="py-8 sm:py-12 md:py-16 lg:py-20 bg-white section-padding">
        <div class="container mx-auto px-4 sm:px-6">
            <div class="text-center mb-8 sm:mb-10 md:mb-12 lg:mb-16 mobile-mb-4">
                <h2 class="section-title text-2xl sm:text-3xl md:text-4xl font-bold text-gray-800 mb-3 md:mb-4 mobile-text-center">Contact Us</h2>
                <p class="text-base sm:text-lg md:text-xl text-gray-600 max-w-3xl mx-auto px-2 sm:px-4 mobile-text-center">
                    Reach out to us for assistance or visit our Barangay Hall for in-person support
                </p>
            </div>
            
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 sm:gap-8 md:gap-10 lg:gap-12 contact-grid">
                <!-- Contact Information -->
                <div>
                    <div class="space-y-4 sm:space-y-5 md:space-y-6 lg:space-y-8">
                        <!-- Emergency Hotline -->
                        <div class="bg-blue-50 rounded-2xl p-3 sm:p-4 md:p-5 lg:p-6 hover-lift desktop-hover-effect">
                            <div class="flex items-start space-x-2 sm:space-x-3 md:space-x-4">
                                <div class="w-8 h-8 sm:w-10 sm:h-10 md:w-12 md:h-12 bg-red-100 rounded-xl flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-phone-alt text-red-600 text-base sm:text-lg md:text-xl"></i>
                                </div>
                                <div>
                                    <h3 class="text-base sm:text-lg md:text-xl font-bold text-gray-800 mb-1 md:mb-2">Emergency Hotline</h3>
                                    <div class="text-xl sm:text-2xl md:text-3xl font-bold text-red-600 mb-1">911</div>
                                    <p class="text-gray-600 text-sm sm:text-base md:text-base">(02) 8-123-4567</p>
                                    <p class="text-xs sm:text-sm md:text-sm text-gray-500 mt-1 md:mt-2">24/7 Emergency Response</p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Barangay Address -->
                        <div class="bg-blue-50 rounded-2xl p-3 sm:p-4 md:p-5 lg:p-6 hover-lift desktop-hover-effect">
                            <div class="flex items-start space-x-2 sm:space-x-3 md:space-x-4">
                                <div class="w-8 h-8 sm:w-10 sm:h-10 md:w-12 md:h-12 bg-blue-100 rounded-xl flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-map-marker-alt text-blue-600 text-base sm:text-lg md:text-xl"></i>
                                </div>
                                <div>
                                    <h3 class="text-base sm:text-lg md:text-xl font-bold text-gray-800 mb-1 md:mb-2">Visit Barangay Hall</h3>
                                    <p class="text-gray-600 text-sm sm:text-base md:text-base mb-1 md:mb-2">Official Barangay Address,</p>
                                    <p class="text-gray-800 font-medium text-sm sm:text-base md:text-base">City Hall Compound, LGU-4 District</p>
                                    <p class="text-xs sm:text-sm md:text-sm text-gray-500 mt-1 md:mt-2">Open Monday to Friday, 8:00 AM - 5:00 PM</p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Email Support -->
                        <div class="bg-green-50 rounded-2xl p-3 sm:p-4 md:p-5 lg:p-6 hover-lift desktop-hover-effect">
                            <div class="flex items-start space-x-2 sm:space-x-3 md:space-x-4">
                                <div class="w-8 h-8 sm:w-10 sm:h-10 md:w-12 md:h-12 bg-green-100 rounded-xl flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-envelope text-green-600 text-base sm:text-lg md:text-xl"></i>
                                </div>
                                <div>
                                    <h3 class="text-base sm:text-lg md:text-xl font-bold text-gray-800 mb-1 md:mb-2">Email Support</h3>
                                    <p class="text-gray-800 font-medium text-sm sm:text-base md:text-lg break-words">lgu4lawenforcement@gmail.com</p>
                                    <p class="text-xs sm:text-sm md:text-sm text-gray-500 mt-1 md:mt-2">Response within 24 hours</p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Login CTA -->
                        <div class="bg-gradient-to-r from-blue-600 to-blue-800 rounded-2xl p-4 sm:p-5 md:p-6 lg:p-8 text-white text-center">
                            <h3 class="text-lg sm:text-xl md:text-2xl font-bold mb-2 sm:mb-3 md:mb-4">Barangay Governance Portal</h3>
                            <p class="text-blue-100 text-sm sm:text-base md:text-base mb-3 sm:mb-4 md:mb-5 lg:mb-6">Access the official portal for barangay officials and staff</p>
                            <a href="login.php" 
                               class="inline-flex items-center justify-center bg-white text-blue-800 hover:bg-blue-50 px-3 py-2 sm:px-4 sm:py-2 md:px-5 md:py-3 lg:px-6 lg:py-3 rounded-full font-semibold transition-all duration-300 shadow-md hover:shadow-lg text-sm sm:text-base md:text-base desktop-hover-effect mobile-touch-target"
                               id="portal-login-btn">
                                <i class="fas fa-sign-in-alt mr-2"></i>
                                Login to Portal
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Google Map -->
                <div>
                    <div class="lg:sticky lg:top-24">
                        <h3 class="text-lg sm:text-xl md:text-2xl font-bold text-gray-800 mb-3 sm:mb-4 md:mb-5 lg:mb-6">Our Location</h3>
                        <div class="map-container hover-lift desktop-hover-effect">
                            <iframe 
                                src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d736.1184015785971!2d121.0871720045833!3d14.69712755789391!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3397ba0d1e186d73%3A0x575e861aa5cfcd55!2sBarangay%20Commonwealth%20Barangay%20Hall!5e1!3m2!1sen!2sph!4v1765034719204!5m2!1sen!2sph" width="600" height="450" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade">                             width="100%" 

                            </iframe>
                        </div>
                        
                        <!-- Map Instructions -->
                        <div class="mt-3 sm:mt-4 md:mt-5 lg:mt-6 bg-gray-50 rounded-xl p-3 sm:p-4 md:p-5 lg:p-6 hover-lift desktop-hover-effect">
                            <h4 class="font-bold text-gray-800 mb-2 sm:mb-3 md:mb-3 lg:mb-4">Getting Here</h4>
                            <ul class="space-y-1 sm:space-y-2 md:space-y-2 lg:space-y-2 text-gray-600 text-sm sm:text-base md:text-base">
                                <li class="flex items-start">
                                    <i class="fas fa-bus text-blue-500 mr-2 sm:mr-3 mt-0.5 sm:mt-1 text-sm sm:text-base"></i>
                                    <span class="text-xs sm:text-sm md:text-base">Public transport: Jeepneys and buses stop at City Hall Compound</span>
                                </li>
                                <li class="flex items-start">
                                    <i class="fas fa-car text-green-500 mr-2 sm:mr-3 mt-0.5 sm:mt-1 text-sm sm:text-base"></i>
                                    <span class="text-xs sm:text-sm md:text-base">Parking available at the east wing parking area</span>
                                </li>
                                <li class="flex items-start">
                                    <i class="fas fa-wheelchair text-purple-500 mr-2 sm:mr-3 mt-0.5 sm:mt-1 text-sm sm:text-base"></i>
                                    <span class="text-xs sm:text-sm md:text-base">Wheelchair accessible entrance at the main gate</span>
                                </li>
                                <li class="flex items-start">
                                    <i class="fas fa-clock text-orange-500 mr-2 sm:mr-3 mt-0.5 sm:mt-1 text-sm sm:text-base"></i>
                                    <span class="text-xs sm:text-sm md:text-base">Office Hours: Mon-Fri, 8:00 AM - 5:00 PM</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-900 text-gray-300 py-4 sm:py-5 md:py-6 mobile-safe-bottom">
        <div class="container mx-auto px-4">
            <div class="flex flex-col md:flex-row justify-between items-center footer-content mobile-stack mobile-gap-4">
                <!-- Copyright Text -->
                <div class="mb-2 sm:mb-3 md:mb-0 mobile-text-center">
                    <span class="text-xs sm:text-sm md:text-sm">
                        © <?php echo date('Y'); ?> LGU-4 Incident Reporting System. All rights reserved.
                    </span>
                </div>
                
                <!-- Links Section -->
                <div class="flex items-center footer-links mobile-stack mobile-gap-4">
                    <div class="flex items-center space-x-2 sm:space-x-3 md:space-x-4">
                        <a href="#" class="footer-link text-xs sm:text-sm md:text-sm text-gray-400 hover:text-white mobile-touch-target">
                            Privacy Policy
                        </a>
                        <span class="footer-divider hidden sm:inline">|</span>
                        <a href="#" class="footer-link text-xs sm:text-sm md:text-sm text-gray-400 hover:text-white mobile-touch-target">
                            Terms of Service
                        </a>
                        <span class="footer-divider hidden sm:inline">|</span>
                        <a href="#" class="footer-link text-xs sm:text-sm md:text-sm text-gray-400 hover:text-white flex items-center mobile-touch-target">
                            <i class="fas fa-external-link-alt mr-1 text-xs"></i>
                            <span class="hidden sm:inline">Official Links</span>
                            <span class="sm:hidden">Gov Links</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <!-- Floating Action Button for Mobile -->
    <div class="md:hidden fixed bottom-6 right-6 z-40 mobile-safe-bottom">
        <a href="register.php" 
           class="bg-gradient-to-r from-blue-600 to-blue-800 text-white w-12 h-12 rounded-full flex items-center justify-center shadow-lg hover:shadow-xl transition-all duration-300 hover-lift mobile-touch-target"
           id="fab-register-btn"
           aria-label="Register">
            <i class="fas fa-plus text-lg"></i>
        </a>
    </div>

    <!-- Scripts -->
    <script>
        // Store base URL from PHP
        const baseUrl = '<?php echo $base_url; ?>';
        
        // Loading screen functionality
        document.addEventListener('DOMContentLoaded', function() {
            const loadingScreen = document.getElementById('leir-loading-screen');
            
            // Hide loading screen after 0.5 seconds (500ms)
            setTimeout(function() {
                if (loadingScreen) {
                    loadingScreen.style.opacity = '0';
                    setTimeout(function() {
                        loadingScreen.style.display = 'none';
                        document.body.classList.remove('overflow-hidden');
                    }, 300);
                }
            }, 500);
            
            // Prevent body scroll during loading
            document.body.classList.add('overflow-hidden');
        });
        
        // Mobile menu toggle with improved accessibility
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        if (mobileMenuBtn) {
            mobileMenuBtn.addEventListener('click', function() {
                const mobileMenu = document.getElementById('mobileMenu');
                const isExpanded = mobileMenu.classList.contains('hidden') ? 'true' : 'false';
                
                mobileMenu.classList.toggle('hidden');
                
                // Change icon and aria-expanded attribute
                const icon = this.querySelector('i');
                if (icon.classList.contains('fa-bars')) {
                    icon.classList.remove('fa-bars');
                    icon.classList.add('fa-times');
                    this.setAttribute('aria-expanded', 'true');
                    document.body.classList.add('overflow-hidden');
                } else {
                    icon.classList.remove('fa-times');
                    icon.classList.add('fa-bars');
                    this.setAttribute('aria-expanded', 'false');
                    document.body.classList.remove('overflow-hidden');
                }
            });
        }
        
        // Close mobile menu when clicking a link or outside
        document.querySelectorAll('#mobileMenu a').forEach(link => {
            link.addEventListener('click', function() {
                closeMobileMenu();
            });
        });
        
        // Close mobile menu when clicking outside
        document.addEventListener('click', function(event) {
            const mobileMenu = document.getElementById('mobileMenu');
            const mobileMenuBtn = document.getElementById('mobileMenuBtn');
            
            if (mobileMenu && !mobileMenu.classList.contains('hidden') && 
                !mobileMenu.contains(event.target) && 
                !mobileMenuBtn.contains(event.target)) {
                closeMobileMenu();
            }
        });
        
        // Function to close mobile menu
        function closeMobileMenu() {
            const mobileMenu = document.getElementById('mobileMenu');
            const mobileMenuBtn = document.getElementById('mobileMenuBtn');
            
            if (mobileMenu && mobileMenuBtn) {
                mobileMenu.classList.add('hidden');
                const icon = mobileMenuBtn.querySelector('i');
                icon.classList.remove('fa-times');
                icon.classList.add('fa-bars');
                mobileMenuBtn.setAttribute('aria-expanded', 'false');
                document.body.classList.remove('overflow-hidden');
            }
        }
        
        // Fix for login links
        function fixLoginLinks() {
            const loginLinks = [
                'desktop-login-btn',
                'mobile-login-btn',
                'portal-login-btn',
                'desktop-register-btn',
                'mobile-register-btn',
                'hero-register-btn',
                'fab-register-btn'
            ];
            
            loginLinks.forEach(id => {
                const link = document.getElementById(id);
                if (link) {
                    link.addEventListener('click', function(e) {
                        e.stopPropagation();
                        
                        // Add click feedback for mobile
                        if (window.innerWidth < 768) {
                            this.style.transform = 'scale(0.95)';
                            setTimeout(() => {
                                this.style.transform = '';
                            }, 150);
                        }
                        
                        setTimeout(() => {
                            if (window.location.href.indexOf(this.getAttribute('href')) === -1) {
                                window.location.href = this.getAttribute('href');
                            }
                        }, 10);
                    });
                }
            });
        }
        
        // Smooth scrolling for anchor links with offset for fixed header
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                const href = this.getAttribute('href');
                if (href !== '#' && href !== '#!') {
                    e.preventDefault();
                    const targetId = href;
                    if (targetId === '#home') {
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    } else {
                        const targetElement = document.querySelector(targetId);
                        if (targetElement) {
                            const headerHeight = document.querySelector('header').offsetHeight;
                            const targetPosition = targetElement.offsetTop - headerHeight - 20;
                            window.scrollTo({ 
                                top: targetPosition, 
                                behavior: 'smooth' 
                            });
                            
                            // Close mobile menu if open
                            closeMobileMenu();
                        }
                    }
                }
            });
        });
        
        // Animate elements on scroll with Intersection Observer
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };
        
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animate-fadeInUp');
                    observer.unobserve(entry.target);
                }
            });
        }, observerOptions);
        
        // Observe elements for animation
        document.querySelectorAll('.hover-lift, .feature-card, .timeline-step').forEach(el => {
            observer.observe(el);
        });
        
        // Initialize on DOMContentLoaded
        document.addEventListener('DOMContentLoaded', function() {
            // Add touch feedback for mobile
            document.querySelectorAll('.mobile-touch-target').forEach(element => {
                element.addEventListener('touchstart', function() {
                    this.style.opacity = '0.8';
                });
                
                element.addEventListener('touchend', function() {
                    this.style.opacity = '';
                });
            });
            
            // Fix login links
            fixLoginLinks();
            
            // Update current year in footer
            const yearElement = document.querySelector('footer span');
            if (yearElement) {
                const currentYear = new Date().getFullYear();
                yearElement.innerHTML = yearElement.innerHTML.replace('<?php echo date("Y"); ?>', currentYear);
            }
            
            // Add scroll-based header background
            window.addEventListener('scroll', function() {
                const header = document.querySelector('header');
                if (window.scrollY > 50) {
                    header.classList.add('shadow-lg', 'bg-white/95', 'backdrop-blur-sm');
                } else {
                    header.classList.remove('shadow-lg', 'bg-white/95', 'backdrop-blur-sm');
                }
            });
            
            // Check for reduced motion preference
            const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
            if (reducedMotion.matches) {
                document.querySelectorAll('*').forEach(el => {
                    el.style.animationDuration = '0.01ms';
                    el.style.transitionDuration = '0.01ms';
                });
            }
            
            // Handle resize events
            let resizeTimer;
            window.addEventListener('resize', function() {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(function() {
                    // Close mobile menu on resize to desktop
                    if (window.innerWidth >= 768) {
                        closeMobileMenu();
                    }
                }, 250);
            });
        });
        
        // Handle navigation for external pages
        document.addEventListener('click', function(e) {
            const target = e.target.closest('a');
            if (target) {
                const href = target.getAttribute('href');
                // Check if it's an external page (not anchor link)
                if (href && !href.startsWith('#') && !href.startsWith('javascript:')) {
                    // Allow normal navigation
                    return true;
                }
            }
        });
        
        // Add keyboard navigation support
        document.addEventListener('keydown', function(e) {
            // Close mobile menu on Escape key
            if (e.key === 'Escape') {
                closeMobileMenu();
            }
            
            // Tab key navigation - ensure focus stays within modal when open
            const mobileMenu = document.getElementById('mobileMenu');
            if (e.key === 'Tab' && mobileMenu && !mobileMenu.classList.contains('hidden')) {
                const focusableElements = mobileMenu.querySelectorAll('a, button, [tabindex]:not([tabindex="-1"])');
                const firstElement = focusableElements[0];
                const lastElement = focusableElements[focusableElements.length - 1];
                
                if (e.shiftKey) {
                    if (document.activeElement === firstElement) {
                        lastElement.focus();
                        e.preventDefault();
                    }
                } else {
                    if (document.activeElement === lastElement) {
                        firstElement.focus();
                        e.preventDefault();
                    }
                }
            }
        });
    </script>
</body>
</html>