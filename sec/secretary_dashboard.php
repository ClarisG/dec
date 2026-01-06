<?php
session_start();

// Check if user is logged in and is secretary
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'secretary') {
    header("Location: login.php");
    exit;
}

// Include database configuration
require_once './config/database.php';

// Get secretary information
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];

// Database connection
try {
    $conn = getDbConnection();
} catch(PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Handle module switching
$module = isset($_GET['module']) ? $_GET['module'] : 'dashboard';
$valid_modules = ['dashboard', 'case', 'compliance', 'documents', 'referral', 'profile'];
if (!in_array($module, $valid_modules)) {
    $module = 'dashboard';
}

// Handle actions based on module
if ($module == 'case' && isset($_POST['assign_case'])) {
    // Handle case assignment
    $case_id = $_POST['case_id'];
    $lupon_member = $_POST['lupon_member'];
    
    try {
        $stmt = $conn->prepare("UPDATE cases SET assigned_lupon = :lupon, status = 'processing', assigned_at = NOW() WHERE id = :id");
        $stmt->bindParam(':lupon', $lupon_member);
        $stmt->bindParam(':id', $case_id);
        $stmt->execute();
        
        $success_message = "Case #$case_id assigned to $lupon_member successfully!";
    } catch(PDOException $e) {
        $error_message = "Failed to assign case: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secretary Dashboard - Barangay Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            font-family: 'Inter', sans-serif;
        }
        
        :root {
            --primary-blue: #e3f2fd;
            --secondary-blue: #bbdefb;
            --accent-blue: #2196f3;
            --dark-blue: #0d47a1;
            --light-blue: #f5fbff;
        }
        
        body {
            background: linear-gradient(135deg, #f5fbff 0%, #e3f2fd 100%);
            min-height: 100vh;
        }
        
        .glass-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .module-card {
            transition: all 0.3s ease;
            border-left: 4px solid var(--accent-blue);
        }
        
        .module-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(33, 150, 243, 0.1);
            border-left-color: var(--dark-blue);
        }
        
        .stat-card {
            background: linear-gradient(135deg, #ffffff 0%, #f0f9ff 100%);
            border: 1px solid #e0f2fe;
        }
        
        .urgent {
            border-left: 4px solid #ef4444;
            background: linear-gradient(135deg, #fef2f2 0%, #fecaca 100%);
        }
        
        .warning {
            border-left: 4px solid #f59e0b;
            background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
        }
        
        .success {
            border-left: 4px solid #10b981;
            background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
        }
        
        .sidebar {
            background: linear-gradient(180deg, #1e3a8a 0%, #0d47a1 100%);
            box-shadow: 4px 0 15px rgba(0, 0, 0, 0.1);
        }
        
        .sidebar-link {
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
        }
        
        .sidebar-link:hover {
            background: rgba(255, 255, 255, 0.1);
            border-left-color: #60a5fa;
        }
        
        .sidebar-link.active {
            background: rgba(255, 255, 255, 0.15);
            border-left-color: #3b82f6;
        }
        
        .case-table tr {
            border-bottom: 1px solid #e5e7eb;
        }
        
        .case-table tr:hover {
            background-color: #f8fafc;
        }
        
        .badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .badge-pending {
            background-color: #fef3c7;
            color: #92400e;
        }
        
        .badge-processing {
            background-color: #dbeafe;
            color: #1e40af;
        }
        
        .badge-resolved {
            background-color: #d1fae5;
            color: #065f46;
        }
        
        .badge-referred {
            background-color: #f3e8ff;
            color: #5b21b6;
        }
        
        .badge-vawc {
            background-color: #fee2e2;
            color: #991b1b;
        }
        
        .badge-minor {
            background-color: #fef3c7;
            color: #92400e;
        }
        
        .animate-pulse {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
    </style>
</head>
<body class="min-h-screen">
    <div class="flex">
        <!-- Sidebar -->
        <div class="sidebar w-64 min-h-screen fixed left-0 top-0 z-40">
            <div class="p-6">
                <div class="flex items-center space-x-3 mb-8">
                    <div class="w-10 h-10 bg-white rounded-lg flex items-center justify-center">
                        <i class="fas fa-landmark text-blue-600 text-xl"></i>
                    </div>
                    <div>
                        <h1 class="text-white font-bold text-lg">Barangay</h1>
                        <p class="text-blue-200 text-sm">Management System</p>
                    </div>
                </div>
                
                <div class="mb-8">
                    <div class="flex items-center space-x-3 p-3 bg-white/10 rounded-lg">
                        <div class="w-10 h-10 bg-blue-500 rounded-full flex items-center justify-center text-white font-bold">
                            <?php echo strtoupper(substr($_SESSION['first_name'], 0, 1)); ?>
                        </div>
                        <div>
                            <p class="text-white font-medium"><?php echo htmlspecialchars($user_name); ?></p>
                            <p class="text-blue-200 text-sm">Secretary</p>
                        </div>
                    </div>
                </div>
                
                <nav class="space-y-2">
                    <a href="?module=dashboard" class="sidebar-link block p-3 text-white rounded-lg <?php echo $module == 'dashboard' ? 'active' : ''; ?>">
                        <i class="fas fa-tachometer-alt mr-3"></i>
                        Dashboard Overview
                    </a>
                    <a href="?module=case" class="sidebar-link block p-3 text-white rounded-lg <?php echo $module == 'case' ? 'active' : ''; ?>">
                        <i class="fas fa-gavel mr-3"></i>
                        Case-Blotter Management
                    </a>
                    <a href="?module=compliance" class="sidebar-link block p-3 text-white rounded-lg <?php echo $module == 'compliance' ? 'active' : ''; ?>">
                        <i class="fas fa-clock mr-3"></i>
                        Compliance Monitoring
                    </a>
                    <a href="?module=documents" class="sidebar-link block p-3 text-white rounded-lg <?php echo $module == 'documents' ? 'active' : ''; ?>">
                        <i class="fas fa-file-pdf mr-3"></i>
                        Document Generation
                    </a>
                    <a href="?module=referral" class="sidebar-link block p-3 text-white rounded-lg <?php echo $module == 'referral' ? 'active' : ''; ?>">
                        <i class="fas fa-exchange-alt mr-3"></i>
                        External Referral Desk
                    </a>
                    <a href="?module=profile" class="sidebar-link block p-3 text-white rounded-lg <?php echo $module == 'profile' ? 'active' : ''; ?>">
                        <i class="fas fa-user-cog mr-3"></i>
                        Profile Account
                    </a>
                </nav>
                
                <div class="mt-8 pt-8 border-t border-blue-400/30">
                    <a href="logout.php" class="flex items-center p-3 text-blue-200 hover:text-white hover:bg-white/10 rounded-lg transition">
                        <i class="fas fa-sign-out-alt mr-3"></i>
                        Logout
                    </a>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="ml-64 flex-1 p-8">
            <!-- Header -->
            <div class="flex justify-between items-center mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800">
                        <?php 
                        $titles = [
                            'dashboard' => 'Secretary Dashboard',
                            'case' => 'Case & Blotter Management',
                            'compliance' => 'Compliance Monitoring',
                            'documents' => 'Document Generation',
                            'referral' => 'External Referral Desk',
                            'profile' => 'Profile Account'
                        ];
                        echo $titles[$module];
                        ?>
                    </h1>
                    <p class="text-gray-600 mt-2">
                        <?php 
                        $subtitles = [
                            'dashboard' => 'Overview of all secretary functions and quick actions',
                            'case' => 'Manage cases, assign blotter numbers, and track case progress',
                            'compliance' => 'Monitor RA 7160 deadlines and compliance requirements',
                            'documents' => 'Generate legal documents and export secure PDFs',
                            'referral' => 'Handle VAWC, minor cases, and external agency referrals',
                            'profile' => 'Manage your account information and activity log'
                        ];
                        echo $subtitles[$module];
                        ?>
                    </p>
                </div>
                
                <div class="flex items-center space-x-4">
                    <div class="relative">
                        <i class="fas fa-bell text-gray-600 text-xl"></i>
                        <span class="absolute -top-1 -right-1 w-3 h-3 bg-red-500 rounded-full animate-pulse"></span>
                    </div>
                    <div class="text-right">
                        <p class="font-medium text-gray-800"><?php echo date('l, F j, Y'); ?></p>
                        <p class="text-sm text-gray-600"><?php echo date('h:i A'); ?></p>
                    </div>
                </div>
            </div>

            <!-- Alerts -->
            <?php if (isset($success_message)): ?>
            <div class="mb-6 p-4 bg-green-50 border-l-4 border-green-500 rounded-lg">
                <div class="flex items-center">
                    <i class="fas fa-check-circle text-green-500 mr-3"></i>
                    <p class="text-green-700"><?php echo htmlspecialchars($success_message); ?></p>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
            <div class="mb-6 p-4 bg-red-50 border-l-4 border-red-500 rounded-lg">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
                    <p class="text-red-700"><?php echo htmlspecialchars($error_message); ?></p>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($module == 'dashboard'): ?>
            <!-- Dashboard Overview -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Left Column -->
                <div class="space-y-8">
                    <!-- Quick Stats -->
                    <div class="glass-card rounded-xl p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-6 flex items-center">
                            <i class="fas fa-chart-bar mr-3 text-blue-600"></i>
                            Quick Statistics
                        </h2>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div class="stat-card rounded-lg p-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm text-gray-600">Pending Cases</p>
                                        <p class="text-2xl font-bold text-gray-800">12</p>
                                    </div>
                                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                                        <i class="fas fa-clock text-blue-600 text-xl"></i>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="stat-card rounded-lg p-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm text-gray-600">Approaching Deadline</p>
                                        <p class="text-2xl font-bold text-gray-800">5</p>
                                    </div>
                                    <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center">
                                        <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="stat-card rounded-lg p-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm text-gray-600">Documents Generated</p>
                                        <p class="text-2xl font-bold text-gray-800">47</p>
                                    </div>
                                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                                        <i class="fas fa-file-pdf text-green-600 text-xl"></i>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="stat-card rounded-lg p-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm text-gray-600">External Referrals</p>
                                        <p class="text-2xl font-bold text-gray-800">8</p>
                                    </div>
                                    <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                                        <i class="fas fa-exchange-alt text-purple-600 text-xl"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Quick Actions -->
                    <div class="glass-card rounded-xl p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-6 flex items-center">
                            <i class="fas fa-bolt mr-3 text-blue-600"></i>
                            Quick Actions
                        </h2>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <a href="?module=case" class="module-card bg-white rounded-lg p-4 shadow-sm hover:shadow-md">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                                        <i class="fas fa-gavel text-blue-600"></i>
                                    </div>
                                    <div>
                                        <p class="font-medium text-gray-800">Assign Case</p>
                                        <p class="text-sm text-gray-600">New blotter assignment</p>
                                    </div>
                                </div>
                            </a>
                            
                            <a href="?module=documents" class="module-card bg-white rounded-lg p-4 shadow-sm hover:shadow-md">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mr-3">
                                        <i class="fas fa-file-contract text-green-600"></i>
                                    </div>
                                    <div>
                                        <p class="font-medium text-gray-800">Generate Document</p>
                                        <p class="text-sm text-gray-600">Legal paperwork</p>
                                    </div>
                                </div>
                            </a>
                            
                            <a href="?module=referral" class="module-card bg-white rounded-lg p-4 shadow-sm hover:shadow-md">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center mr-3">
                                        <i class="fas fa-shield-alt text-red-600"></i>
                                    </div>
                                    <div>
                                        <p class="font-medium text-gray-800">VAWC Referral</p>
                                        <p class="text-sm text-gray-600">Confidential protocol</p>
                                    </div>
                                </div>
                            </a>
                            
                            <a href="?module=compliance" class="module-card bg-white rounded-lg p-4 shadow-sm hover:shadow-md">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center mr-3">
                                        <i class="fas fa-clock text-orange-600"></i>
                                    </div>
                                    <div>
                                        <p class="font-medium text-gray-800">Check Deadlines</p>
                                        <p class="text-sm text-gray-600">RA 7160 compliance</p>
                                    </div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Right Column -->
                <div class="space-y-8">
                    <!-- Modules Overview -->
                    <div class="glass-card rounded-xl p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-6 flex items-center">
                            <i class="fas fa-cogs mr-3 text-blue-600"></i>
                            Secretary Functions
                        </h2>
                        
                        <div class="space-y-4">
                            <div class="module-card bg-white rounded-lg p-5">
                                <div class="flex items-start">
                                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                                        <i class="fas fa-gavel text-blue-600 text-lg"></i>
                                    </div>
                                    <div class="flex-1">
                                        <h3 class="font-bold text-gray-800 mb-1">Case-Blotter Management</h3>
                                        <p class="text-gray-600 text-sm mb-2">Issues formal blotter numbers, assigns cases to Lupon members, handles internal barangay matters</p>
                                        <div class="flex flex-wrap gap-2">
                                            <span class="badge badge-pending">Official Blotter Numbers</span>
                                            <span class="badge badge-processing">Assigned Lupon Members</span>
                                            <span class="badge badge-resolved">Case Notes</span>
                                        </div>
                                    </div>
                                    <a href="?module=case" class="text-blue-600 hover:text-blue-800">
                                        <i class="fas fa-arrow-right"></i>
                                    </a>
                                </div>
                            </div>
                            
                            <div class="module-card bg-white rounded-lg p-5">
                                <div class="flex items-start">
                                    <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center mr-4">
                                        <i class="fas fa-clock text-orange-600 text-lg"></i>
                                    </div>
                                    <div class="flex-1">
                                        <h3 class="font-bold text-gray-800 mb-1">Compliance Monitoring</h3>
                                        <p class="text-gray-600 text-sm mb-2">Color-coded alerts for 3-day filing and 15-day resolution deadlines (RA 7160)</p>
                                        <div class="flex flex-wrap gap-2">
                                            <span class="px-3 py-1 bg-red-100 text-red-800 rounded-full text-xs font-medium">Urgent: ≤1 day</span>
                                            <span class="px-3 py-1 bg-yellow-100 text-yellow-800 rounded-full text-xs font-medium">Warning: 2-3 days</span>
                                            <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-xs font-medium">On Track: >3 days</span>
                                        </div>
                                    </div>
                                    <a href="?module=compliance" class="text-blue-600 hover:text-blue-800">
                                        <i class="fas fa-arrow-right"></i>
                                    </a>
                                </div>
                            </div>
                            
                            <div class="module-card bg-white rounded-lg p-5">
                                <div class="flex items-start">
                                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mr-4">
                                        <i class="fas fa-file-pdf text-green-600 text-lg"></i>
                                    </div>
                                    <div class="flex-1">
                                        <h3 class="font-bold text-gray-800 mb-1">Document Generation</h3>
                                        <p class="text-gray-600 text-sm mb-2">Automated creation of legal paperwork with secure PDF export</p>
                                        <div class="flex flex-wrap gap-2">
                                            <span class="badge badge-pending">Subpoena</span>
                                            <span class="badge badge-processing">Hearing Notices</span>
                                            <span class="badge badge-resolved">Certificates</span>
                                            <span class="badge badge-referred">Resolutions</span>
                                        </div>
                                    </div>
                                    <a href="?module=documents" class="text-blue-600 hover:text-blue-800">
                                        <i class="fas fa-arrow-right"></i>
                                    </a>
                                </div>
                            </div>
                            
                            <div class="module-card bg-white rounded-lg p-5 urgent">
                                <div class="flex items-start">
                                    <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center mr-4">
                                        <i class="fas fa-shield-alt text-red-600 text-lg"></i>
                                    </div>
                                    <div class="flex-1">
                                        <h3 class="font-bold text-gray-800 mb-1">External Referral Desk</h3>
                                        <p class="text-gray-600 text-sm mb-2">Confidential protocols for VAWC/minor cases, digital handover to PNP/City Hall</p>
                                        <div class="flex flex-wrap gap-2">
                                            <span class="badge badge-vawc">VAWC Protocol</span>
                                            <span class="badge badge-minor">Minor Cases</span>
                                            <span class="badge badge-referred">Digital Handover</span>
                                        </div>
                                    </div>
                                    <a href="?module=referral" class="text-blue-600 hover:text-blue-800">
                                        <i class="fas fa-arrow-right"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php elseif ($module == 'case'): ?>
            <!-- Case-Blotter Management Module -->
            <div class="space-y-8">
                <!-- Header Section -->
                <div class="glass-card rounded-xl p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-2xl font-bold text-gray-800 flex items-center">
                            <i class="fas fa-gavel mr-3 text-blue-600"></i>
                            Case & Blotter Management
                        </h2>
                        <button class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center">
                            <i class="fas fa-plus mr-2"></i> New Blotter Entry
                        </button>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                        <div class="bg-blue-50 p-4 rounded-lg">
                            <div class="flex items-center">
                                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                                    <i class="fas fa-hashtag text-blue-600 text-xl"></i>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-600">Official Blotter Numbers</p>
                                    <p class="text-xl font-bold text-gray-800">BLT-2024-001 to 045</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-green-50 p-4 rounded-lg">
                            <div class="flex items-center">
                                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mr-4">
                                    <i class="fas fa-users text-green-600 text-xl"></i>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-600">Assigned Lupon Members</p>
                                    <p class="text-xl font-bold text-gray-800">12 Active</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-purple-50 p-4 rounded-lg">
                            <div class="flex items-center">
                                <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mr-4">
                                    <i class="fas fa-sticky-note text-purple-600 text-xl"></i>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-600">Formal Case Notes</p>
                                    <p class="text-xl font-bold text-gray-800">156 Entries</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Cases Table -->
                <div class="glass-card rounded-xl p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-xl font-bold text-gray-800">Pending Cases for Assignment</h3>
                        <div class="flex space-x-2">
                            <button class="px-4 py-2 bg-blue-100 text-blue-700 rounded-lg">All</button>
                            <button class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg">Barangay Matters</button>
                            <button class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg">Criminal</button>
                            <button class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg">Civil</button>
                        </div>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="w-full case-table">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="py-3 px-4 text-left text-gray-600 font-semibold">Case ID</th>
                                    <th class="py-3 px-4 text-left text-gray-600 font-semibold">Date Filed</th>
                                    <th class="py-3 px-4 text-left text-gray-600 font-semibold">Complainant</th>
                                    <th class="py-3 px-4 text-left text-gray-600 font-semibold">Category</th>
                                    <th class="py-3 px-4 text-left text-gray-600 font-semibold">Status</th>
                                    <th class="py-3 px-4 text-left text-gray-600 font-semibold">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="py-3 px-4">
                                        <span class="font-medium text-blue-600">#2024-045</span>
                                        <p class="text-xs text-gray-500">Needs blotter number</p>
                                    </td>
                                    <td class="py-3 px-4">Mar 15, 2024</td>
                                    <td class="py-3 px-4">Juan Dela Cruz</td>
                                    <td class="py-3 px-4">
                                        <span class="badge badge-pending">Barangay Matter</span>
                                    </td>
                                    <td class="py-3 px-4">
                                        <span class="px-3 py-1 bg-yellow-100 text-yellow-800 rounded-full text-xs font-medium">Pending</span>
                                    </td>
                                    <td class="py-3 px-4">
                                        <button onclick="openAssignmentModal(2024045)" class="px-3 py-1 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700">
                                            Assign
                                        </button>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="py-3 px-4">
                                        <span class="font-medium text-blue-600">#2024-044</span>
                                        <p class="text-xs text-gray-500">BLT-2024-044</p>
                                    </td>
                                    <td class="py-3 px-4">Mar 14, 2024</td>
                                    <td class="py-3 px-4">Maria Santos</td>
                                    <td class="py-3 px-4">
                                        <span class="badge badge-processing">Boundary Dispute</span>
                                    </td>
                                    <td class="py-3 px-4">
                                        <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-xs font-medium">Assigned</span>
                                    </td>
                                    <td class="py-3 px-4">
                                        <button class="px-3 py-1 bg-gray-100 text-gray-700 rounded-lg text-sm hover:bg-gray-200">
                                            View Details
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <?php elseif ($module == 'compliance'): ?>
            <!-- Compliance Monitoring Module -->
            <div class="space-y-8">
                <div class="glass-card rounded-xl p-6">
                    <h2 class="text-2xl font-bold text-gray-800 mb-6 flex items-center">
                        <i class="fas fa-clock mr-3 text-blue-600"></i>
                        Compliance Monitoring Dashboard
                    </h2>
                    
                    <div class="mb-8">
                        <div class="bg-blue-50 border-l-4 border-blue-500 p-4 rounded-lg">
                            <div class="flex items-center">
                                <i class="fas fa-info-circle text-blue-500 text-xl mr-3"></i>
                                <div>
                                    <h4 class="font-bold text-blue-800">RA 7160 Compliance Monitoring</h4>
                                    <p class="text-blue-700">Monitoring 3-day filing deadline and 15-day resolution deadline as per Local Government Code</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                        <div class="urgent rounded-lg p-6">
                            <div class="flex items-center">
                                <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mr-4">
                                    <i class="fas fa-exclamation-triangle text-red-600 text-2xl"></i>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-600">Overdue Cases</p>
                                    <p class="text-3xl font-bold text-gray-800">3</p>
                                    <p class="text-sm text-red-600">Past 15-day deadline</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="warning rounded-lg p-6">
                            <div class="flex items-center">
                                <div class="w-16 h-16 bg-yellow-100 rounded-full flex items-center justify-center mr-4">
                                    <i class="fas fa-clock text-yellow-600 text-2xl"></i>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-600">Approaching Deadline</p>
                                    <p class="text-3xl font-bold text-gray-800">5</p>
                                    <p class="text-sm text-yellow-600">Within 3 days</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="success rounded-lg p-6">
                            <div class="flex items-center">
                                <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mr-4">
                                    <i class="fas fa-check-circle text-green-600 text-2xl"></i>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-600">On Track</p>
                                    <p class="text-3xl font-bold text-gray-800">24</p>
                                    <p class="text-sm text-green-600">Within timeline</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php elseif ($module == 'documents'): ?>
            <!-- Document Generation Module -->
            <div class="space-y-8">
                <div class="glass-card rounded-xl p-6">
                    <h2 class="text-2xl font-bold text-gray-800 mb-6 flex items-center">
                        <i class="fas fa-file-pdf mr-3 text-blue-600"></i>
                        Document Generation Center
                    </h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div class="module-card bg-white rounded-xl p-6">
                            <div class="w-16 h-16 bg-blue-100 rounded-xl flex items-center justify-center mb-4">
                                <i class="fas fa-file-contract text-blue-600 text-2xl"></i>
                            </div>
                            <h3 class="font-bold text-gray-800 mb-2">Subpoena/Summons</h3>
                            <p class="text-gray-600 text-sm mb-4">Official notice to appear for barangay hearing</p>
                            <button class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2 rounded-lg">
                                Generate Document
                            </button>
                        </div>
                        
                        <div class="module-card bg-white rounded-xl p-6">
                            <div class="w-16 h-16 bg-green-100 rounded-xl flex items-center justify-center mb-4">
                                <i class="fas fa-calendar-alt text-green-600 text-2xl"></i>
                            </div>
                            <h3 class="font-bold text-gray-800 mb-2">Notice of Hearing</h3>
                            <p class="text-gray-600 text-sm mb-4">Schedule and notify about barangay hearings</p>
                            <button class="w-full bg-green-600 hover:bg-green-700 text-white py-2 rounded-lg">
                                Generate Document
                            </button>
                        </div>
                        
                        <div class="module-card bg-white rounded-xl p-6">
                            <div class="w-16 h-16 bg-purple-100 rounded-xl flex items-center justify-center mb-4">
                                <i class="fas fa-certificate text-purple-600 text-2xl"></i>
                            </div>
                            <h3 class="font-bold text-gray-800 mb-2">Certificate to File Action</h3>
                            <p class="text-gray-600 text-sm mb-4">Authorization for court filing after barangay proceedings</p>
                            <button class="w-full bg-purple-600 hover:bg-purple-700 text-white py-2 rounded-lg">
                                Generate Document
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <?php elseif ($module == 'referral'): ?>
            <!-- External Referral Desk Module -->
            <div class="space-y-8">
                <div class="glass-card rounded-xl p-6">
                    <h2 class="text-2xl font-bold text-gray-800 mb-6 flex items-center">
                        <i class="fas fa-exchange-alt mr-3 text-blue-600"></i>
                        External Referral Desk
                    </h2>
                    
                    <div class="urgent rounded-xl p-6 mb-8">
                        <div class="flex items-center mb-4">
                            <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center mr-4">
                                <i class="fas fa-lock text-red-600 text-xl"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-gray-800">CONFIDENTIAL PROTOCOL</h3>
                                <p class="text-gray-600">VAWC & Minor Cases require strict confidentiality and immediate action</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="bg-white rounded-xl p-6 border border-red-200">
                            <div class="flex items-center mb-4">
                                <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center mr-4">
                                    <i class="fas fa-female text-red-600 text-xl"></i>
                                </div>
                                <div>
                                    <h3 class="font-bold text-gray-800">VAWC Cases (RA 9262)</h3>
                                    <p class="text-sm text-gray-600">Violence Against Women and Children</p>
                                </div>
                            </div>
                            <ul class="space-y-2 mb-6">
                                <li class="flex items-center text-sm text-gray-600">
                                    <i class="fas fa-check-circle text-green-500 mr-2"></i>
                                    Immediate referral to PNP Women's Desk
                                </li>
                                <li class="flex items-center text-sm text-gray-600">
                                    <i class="fas fa-check-circle text-green-500 mr-2"></i>
                                    Confidentiality is MANDATORY
                                </li>
                                <li class="flex items-center text-sm text-gray-600">
                                    <i class="fas fa-check-circle text-green-500 mr-2"></i>
                                    Generate Protection Order request
                                </li>
                            </ul>
                            <button class="w-full bg-red-600 hover:bg-red-700 text-white py-3 rounded-lg font-medium">
                                Initiate VAWC Referral
                            </button>
                        </div>
                        
                        <div class="bg-white rounded-xl p-6 border border-orange-200">
                            <div class="flex items-center mb-4">
                                <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center mr-4">
                                    <i class="fas fa-child text-orange-600 text-xl"></i>
                                </div>
                                <div>
                                    <h3 class="font-bold text-gray-800">Minor Cases</h3>
                                    <p class="text-sm text-gray-600">Cases involving children/minors</p>
                                </div>
                            </div>
                            <ul class="space-y-2 mb-6">
                                <li class="flex items-center text-sm text-gray-600">
                                    <i class="fas fa-check-circle text-green-500 mr-2"></i>
                                    Refer to DSWD or PNP WCPD
                                </li>
                                <li class="flex items-center text-sm text-gray-600">
                                    <i class="fas fa-check-circle text-green-500 mr-2"></i>
                                    Notify Municipal Social Welfare Office
                                </li>
                                <li class="flex items-center text-sm text-gray-600">
                                    <i class="fas fa-check-circle text-green-500 mr-2"></i>
                                    Document all communications
                                </li>
                            </ul>
                            <button class="w-full bg-orange-600 hover:bg-orange-700 text-white py-3 rounded-lg font-medium">
                                Initiate Minor Case Referral
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <?php elseif ($module == 'profile'): ?>
            <!-- Profile Account Module -->
            <div class="space-y-8">
                <div class="glass-card rounded-xl p-6">
                    <h2 class="text-2xl font-bold text-gray-800 mb-6 flex items-center">
                        <i class="fas fa-user-cog mr-3 text-blue-600"></i>
                        Profile Account Management
                    </h2>
                    
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                        <!-- Profile Information -->
                        <div class="lg:col-span-2">
                            <div class="bg-white rounded-xl p-6 mb-6">
                                <h3 class="text-lg font-bold text-gray-800 mb-4">Personal Information</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm text-gray-600 mb-1">First Name</label>
                                        <input type="text" value="<?php echo htmlspecialchars($_SESSION['first_name'] ?? ''); ?>" 
                                               class="w-full p-3 bg-gray-50 border border-gray-200 rounded-lg" readonly>
                                    </div>
                                    <div>
                                        <label class="block text-sm text-gray-600 mb-1">Last Name</label>
                                        <input type="text" value="<?php echo htmlspecialchars($_SESSION['last_name'] ?? ''); ?>" 
                                               class="w-full p-3 bg-gray-50 border border-gray-200 rounded-lg" readonly>
                                    </div>
                                    <div>
                                        <label class="block text-sm text-gray-600 mb-1">Email Address</label>
                                        <input type="email" value="<?php echo htmlspecialchars($_SESSION['email'] ?? 'secretary@barangay.gov.ph'); ?>" 
                                               class="w-full p-3 bg-gray-50 border border-gray-200 rounded-lg">
                                    </div>
                                    <div>
                                        <label class="block text-sm text-gray-600 mb-1">Contact Number</label>
                                        <input type="tel" value="<?php echo htmlspecialchars($_SESSION['contact_number'] ?? '+63 912 345 6789'); ?>" 
                                               class="w-full p-3 bg-gray-50 border border-gray-200 rounded-lg">
                                    </div>
                                </div>
                                
                                <div class="mt-6">
                                    <label class="block text-sm text-gray-600 mb-1">Address</label>
                                    <textarea class="w-full p-3 bg-gray-50 border border-gray-200 rounded-lg" rows="2">Barangay Hall, Municipal Hall Compound, Municipality</textarea>
                                </div>
                                
                                <div class="mt-6 flex justify-end">
                                    <button class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                        Save Changes
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Account Status -->
                        <div>
                            <div class="bg-white rounded-xl p-6 mb-6">
                                <h3 class="text-lg font-bold text-gray-800 mb-4">Account Status</h3>
                                <div class="space-y-4">
                                    <div>
                                        <p class="text-sm text-gray-600">Role</p>
                                        <p class="font-medium text-gray-800">Barangay Secretary</p>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-600">Employee ID</p>
                                        <p class="font-medium text-gray-800">SEC-<?php echo str_pad($user_id, 4, '0', STR_PAD_LEFT); ?></p>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-600">Account Status</p>
                                        <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-xs font-medium">Active</span>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-600">Last Login</p>
                                        <p class="font-medium text-gray-800">Today, 10:30 AM</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="bg-blue-50 rounded-xl p-6">
                                <h3 class="text-lg font-bold text-gray-800 mb-4">Quick Actions</h3>
                                <div class="space-y-3">
                                    <button class="w-full p-3 bg-white border border-gray-200 rounded-lg text-left hover:bg-gray-50">
                                        <i class="fas fa-key mr-2 text-blue-600"></i>
                                        Change Password
                                    </button>
                                    <button class="w-full p-3 bg-white border border-gray-200 rounded-lg text-left hover:bg-gray-50">
                                        <i class="fas fa-bell mr-2 text-blue-600"></i>
                                        Notification Settings
                                    </button>
                                    <button class="w-full p-3 bg-white border border-gray-200 rounded-lg text-left hover:bg-gray-50">
                                        <i class="fas fa-download mr-2 text-blue-600"></i>
                                        Export Activity Log
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Assignment Modal -->
    <div id="assignmentModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-2xl max-w-md w-full p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold text-gray-800">Assign Case to Lupon Member</h3>
                <button onclick="closeAssignmentModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form method="POST" action="">
                <input type="hidden" name="case_id" id="modalCaseId">
                <input type="hidden" name="assign_case" value="1">
                
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Select Lupon Member</label>
                    <select name="lupon_member" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Choose Lupon Member</option>
                        <option value="Juan Dela Cruz">Juan Dela Cruz - Lupon Chairman</option>
                        <option value="Maria Santos">Maria Santos - Lupon Member</option>
                        <option value="Pedro Reyes">Pedro Reyes - Lupon Member</option>
                        <option value="Ana Lim">Ana Lim - Lupon Member</option>
                        <option value="Carlos Torres">Carlos Torres - Lupon Member</option>
                    </select>
                </div>
                
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Blotter Number</label>
                    <input type="text" value="BLT-2024-<?php echo date('md') . '-' . rand(100, 999); ?>" 
                           class="w-full p-3 bg-gray-50 border border-gray-300 rounded-lg" readonly>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeAssignmentModal()" 
                            class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        Assign Case
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Assignment Modal Functions
        function openAssignmentModal(caseId) {
            document.getElementById('modalCaseId').value = caseId;
            document.getElementById('assignmentModal').classList.remove('hidden');
            document.getElementById('assignmentModal').classList.add('flex');
        }
        
        function closeAssignmentModal() {
            document.getElementById('assignmentModal').classList.add('hidden');
            document.getElementById('assignmentModal').classList.remove('flex');
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('assignmentModal');
            if (event.target == modal) {
                closeAssignmentModal();
            }
        }
        
        // Auto-refresh for compliance monitoring
        if (window.location.search.includes('module=compliance')) {
            setInterval(() => {
                // In real implementation, this would fetch updated data
                console.log('Refreshing compliance data...');
            }, 30000); // 30 seconds
        }
    </script>
</body>
</html>