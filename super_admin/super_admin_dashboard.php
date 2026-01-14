<?php
require_once '../config/session.php';
require_once '../config/database.php';

// Check if user is Super Admin
if ($_SESSION['role'] !== 'super_admin') {
    header('Location: ../login.php');
    exit();
}

$page_title = "Super Admin Dashboard";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - LEIR System</title>
    <link rel="stylesheet" href="../styles/auth.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <div class="dashboard-container">
        <!-- Header -->
        <header class="dashboard-header">
            <div class="header-left">
                <h1>Super Admin Dashboard</h1>
                <p class="subtitle">System-wide oversight & unrestricted access</p>
            </div>
            <div class="header-right">
                <div class="user-info">
                    <span class="welcome">Welcome, <?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?> (Super Admin)</span>
                    <a href="../logout.php" class="logout-btn">Logout</a>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <div class="dashboard-main">
            <!-- Sidebar -->
            <aside class="dashboard-sidebar">
                <nav class="sidebar-nav">
                    <h3>Global Modules</h3>
                    <ul>
                        <li><a href="#" class="nav-link active" data-module="global_config">🌐 System Configuration</a></li>
                        <li><a href="#" class="nav-link" data-module="user_management">👥 User Management</a></li>
                        <li><a href="#" class="nav-link" data-module="audit_dashboard">📊 Audit & Compliance</a></li>
                        <li><a href="#" class="nav-link" data-module="incident_override">🔄 Classification Override</a></li>
                        <li><a href="#" class="nav-link" data-module="evidence_log">🔐 Evidence Master Log</a></li>
                        <li><a href="#" class="nav-link" data-module="patrol_override">🚔 Patrol & Duty Control</a></li>
                        <li><a href="#" class="nav-link" data-module="kpi_superview">📈 Executive KPI View</a></li>
                        <li><a href="#" class="nav-link" data-module="api_control">🔗 API Integration Control</a></li>
                        <li><a href="#" class="nav-link" data-module="mediation_oversight">⚖️ Mediation Oversight</a></li>
                        <li><a href="#" class="nav-link" data-module="super_notifications">📢 Super Notifications</a></li>
                        <li><a href="#" class="nav-link" data-module="system_health">💊 System Health</a></li>
                    </ul>
                    
                    <h3>Quick Access</h3>
                    <ul>
                        <li><a href="#" class="nav-link" data-module="reports_all">📋 All Reports</a></li>
                        <li><a href="#" class="nav-link" data-module="users_all">👤 All Users</a></li>
                        <li><a href="#" class="nav-link" data-module="announcements_all">📢 All Announcements</a></li>
                        <li><a href="#" class="nav-link" data-module="activity_logs">📝 Activity Logs</a></li>
                    </ul>
                </nav>
            </aside>

            <!-- Content Area -->
            <main class="dashboard-content">
                <div class="content-header">
                    <h2 id="module-title">System Configuration</h2>
                    <div class="content-actions">
                        <button class="btn-refresh" id="refresh-btn">🔄 Refresh</button>
                        <button class="btn-export" id="export-btn">📤 Export</button>
                    </div>
                </div>
                
                <div class="module-container" id="module-content">
                    <!-- Module content will be loaded here via AJAX -->
                    <div class="welcome-message">
                        <h3>Super Admin Dashboard</h3>
                        <p>You have unrestricted access to all system modules and data.</p>
                        <div class="stats-grid">
                            <div class="stat-card">
                                <h4>Total Users</h4>
                                <span id="total-users">Loading...</span>
                            </div>
                            <div class="stat-card">
                                <h4>Active Cases</h4>
                                <span id="active-cases">Loading...</span>
                            </div>
                            <div class="stat-card">
                                <h4>System Health</h4>
                                <span id="system-health">Loading...</span>
                            </div>
                            <div class="stat-card">
                                <h4>Last Backup</h4>
                                <span id="last-backup">Loading...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- JavaScript -->
    <script>
        $(document).ready(function() {
            // Load default module
            loadModule('global_config');
            
            // Navigation click handler
            $('.nav-link').click(function(e) {
                e.preventDefault();
                $('.nav-link').removeClass('active');
                $(this).addClass('active');
                
                var module = $(this).data('module');
                loadModule(module);
            });
            
            // Refresh button
            $('#refresh-btn').click(function() {
                var currentModule = $('.nav-link.active').data('module');
                loadModule(currentModule);
            });
            
            // Load dashboard stats
            loadDashboardStats();
            
            // Auto-refresh every 30 seconds
            setInterval(loadDashboardStats, 30000);
        });
        
        function loadModule(module) {
            $('#module-content').html('<div class="loading">Loading module...</div>');
            
            $.ajax({
                url: '../ajax/load_module.php',
                type: 'POST',
                data: {
                    module: module,
                    user_type: 'super_admin'
                },
                success: function(response) {
                    $('#module-content').html(response);
                    updateModuleTitle(module);
                },
                error: function() {
                    $('#module-content').html('<div class="error">Error loading module. Please try again.</div>');
                }
            });
        }
        
        function updateModuleTitle(module) {
            var titles = {
                'global_config': 'System Configuration',
                'user_management': 'User Management',
                'audit_dashboard': 'Audit & Compliance',
                'incident_override': 'Incident Classification Override',
                'evidence_log': 'Evidence Master Log',
                'patrol_override': 'Patrol & Duty Control',
                'kpi_superview': 'Executive KPI View',
                'api_control': 'API Integration Control',
                'mediation_oversight': 'Mediation Oversight',
                'super_notifications': 'Super Notifications',
                'system_health': 'System Health',
                'reports_all': 'All Reports',
                'users_all': 'All Users',
                'announcements_all': 'All Announcements',
                'activity_logs': 'Activity Logs'
            };
            
            $('#module-title').text(titles[module] || module.replace('_', ' '));
        }
        
        function loadDashboardStats() {
            $.ajax({
                url: '../ajax/get_superadmin_stats.php',
                type: 'GET',
                success: function(response) {
                    var stats = JSON.parse(response);
                    $('#total-users').text(stats.total_users);
                    $('#active-cases').text(stats.active_cases);
                    $('#system-health').text(stats.system_health);
                    $('#last-backup').text(stats.last_backup);
                }
            });
        }
    </script>
</body>
</html>