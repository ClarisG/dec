<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../config/functions.php';

// Check if user is logged in and is a tanod
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'tanod') {
    header('Location: ../login.php');
    exit();
}

// Get tanod data
$tanod_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$tanod_id]);
$tanod = $stmt->fetch();

// Get duty status
$stmt = $pdo->prepare("SELECT status FROM tanod_status WHERE user_id = ?");
$stmt->execute([$tanod_id]);
$duty_status = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tanod Dashboard - Barangay Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .sidebar {
            transition: all 0.3s ease;
        }
        .module-card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .module-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        .status-on-duty {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }
        .status-off-duty {
            background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);
        }
        .active-module {
            border-left: 4px solid #3b82f6;
            background-color: #f8fafc;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Navbar -->
    <nav class="bg-white shadow-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <div class="flex-shrink-0 flex items-center">
                        <i class="fas fa-shield-alt text-blue-600 text-2xl"></i>
                        <span class="ml-2 text-xl font-bold text-gray-800">Tanod Dashboard</span>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="relative">
                        <div class="flex items-center space-x-3">
                            <div class="text-right">
                                <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($tanod['first_name'] . ' ' . $tanod['last_name']); ?></p>
                                <p class="text-xs text-gray-500">Tanod</p>
                            </div>
                            <div class="h-10 w-10 rounded-full bg-blue-100 flex items-center justify-center">
                                <i class="fas fa-user text-blue-600"></i>
                            </div>
                        </div>
                    </div>
                    <a href="../logout.php" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="flex">
        <!-- Sidebar -->
        <div class="sidebar w-64 bg-gray-900 text-white h-screen sticky top-0">
            <div class="p-4">
                <div class="mb-8">
                    <h2 class="text-lg font-semibold text-gray-300">Navigation</h2>
                </div>
                <nav>
                    <ul class="space-y-2">
                        <li>
                            <a href="#" onclick="loadModule('duty')" class="flex items-center p-3 rounded-lg hover:bg-gray-800 transition module-link" data-module="duty">
                                <i class="fas fa-calendar-alt mr-3"></i>
                                <span>Duty & Patrol</span>
                            </a>
                        </li>
                        <li>
                            <a href="#" onclick="loadModule('incident')" class="flex items-center p-3 rounded-lg hover:bg-gray-800 transition module-link" data-module="incident">
                                <i class="fas fa-clipboard-list mr-3"></i>
                                <span>Incident Logging</span>
                            </a>
                        </li>
                        <li>
                            <a href="#" onclick="loadModule('vetting')" class="flex items-center p-3 rounded-lg hover:bg-gray-800 transition module-link" data-module="vetting">
                                <i class="fas fa-check-circle mr-3"></i>
                                <span>Report Vetting</span>
                            </a>
                        </li>
                        <li>
                            <a href="#" onclick="loadModule('evidence')" class="flex items-center p-3 rounded-lg hover:bg-gray-800 transition module-link" data-module="evidence">
                                <i class="fas fa-box mr-3"></i>
                                <span>Evidence Handover</span>
                            </a>
                        </li>
                        <li>
                            <a href="#" onclick="loadModule('profile')" class="flex items-center p-3 rounded-lg hover:bg-gray-800 transition module-link" data-module="profile">
                                <i class="fas fa-user-cog mr-3"></i>
                                <span>Profile & Status</span>
                            </a>
                        </li>
                    </ul>
                </nav>
                
                <!-- Duty Status -->
                <div class="mt-8 p-4 bg-gray-800 rounded-lg">
                    <h3 class="text-sm font-semibold text-gray-300 mb-2">Current Status</h3>
                    <div id="current-status" class="<?php echo ($duty_status && $duty_status['status'] == 'On-Duty') ? 'status-on-duty' : 'status-off-duty'; ?> p-3 rounded-lg text-center">
                        <p class="text-lg font-bold text-white">
                            <?php echo ($duty_status && $duty_status['status']) ? htmlspecialchars($duty_status['status']) : 'Off-Duty'; ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="flex-1 p-6">
            <div id="module-content">
                <!-- Default dashboard view -->
                <div class="mb-8">
                    <h1 class="text-3xl font-bold text-gray-800">Welcome, Tanod <?php echo htmlspecialchars($tanod['first_name']); ?>!</h1>
                    <p class="text-gray-600 mt-2">Select a module from the sidebar to get started.</p>
                </div>

                <!-- Module Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div class="module-card bg-white rounded-xl shadow-md p-6 cursor-pointer" onclick="loadModule('duty')">
                        <div class="flex items-center mb-4">
                            <div class="h-12 w-12 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                                <i class="fas fa-calendar-alt text-blue-600 text-xl"></i>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-800">My Duty & Patrol Schedule</h3>
                        </div>
                        <p class="text-gray-600 text-sm">View assigned shifts and designated routes. Clock in/out using real-time tracker.</p>
                        <div class="mt-4">
                            <span class="inline-block bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded">Critical: Shift times, Patrol routes</span>
                        </div>
                    </div>

                    <div class="module-card bg-white rounded-xl shadow-md p-6 cursor-pointer" onclick="loadModule('incident')">
                        <div class="flex items-center mb-4">
                            <div class="h-12 w-12 bg-green-100 rounded-lg flex items-center justify-center mr-4">
                                <i class="fas fa-clipboard-list text-green-600 text-xl"></i>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-800">Incident Logging & Submission</h3>
                        </div>
                        <p class="text-gray-600 text-sm">Quick field form for incidents. GPS location recording and evidence upload.</p>
                        <div class="mt-4">
                            <span class="inline-block bg-green-100 text-green-800 text-xs px-2 py-1 rounded">Critical: Incident details, GPS, Evidence</span>
                        </div>
                    </div>

                    <div class="module-card bg-white rounded-xl shadow-md p-6 cursor-pointer" onclick="loadModule('vetting')">
                        <div class="flex items-center mb-4">
                            <div class="h-12 w-12 bg-purple-100 rounded-lg flex items-center justify-center mr-4">
                                <i class="fas fa-check-circle text-purple-600 text-xl"></i>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-800">Report Vetting Queue</h3>
                        </div>
                        <p class="text-gray-600 text-sm">Review citizen reports for field verification. Submit vetting recommendations.</p>
                        <div class="mt-4">
                            <span class="inline-block bg-purple-100 text-purple-800 text-xs px-2 py-1 rounded">Critical: Report details, Verification notes</span>
                        </div>
                    </div>

                    <div class="module-card bg-white rounded-xl shadow-md p-6 cursor-pointer" onclick="loadModule('evidence')">
                        <div class="flex items-center mb-4">
                            <div class="h-12 w-12 bg-yellow-100 rounded-lg flex items-center justify-center mr-4">
                                <i class="fas fa-box text-yellow-600 text-xl"></i>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-800">Evidence Handover Log</h3>
                        </div>
                        <p class="text-gray-600 text-sm">Formal log for transferring physical evidence. Maintain chain of custody.</p>
                        <div class="mt-4">
                            <span class="inline-block bg-yellow-100 text-yellow-800 text-xs px-2 py-1 rounded">Critical: Item description, Handover details</span>
                        </div>
                    </div>

                    <div class="module-card bg-white rounded-xl shadow-md p-6 cursor-pointer" onclick="loadModule('profile')">
                        <div class="flex items-center mb-4">
                            <div class="h-12 w-12 bg-red-100 rounded-lg flex items-center justify-center mr-4">
                                <i class="fas fa-user-cog text-red-600 text-xl"></i>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-800">Profile & Status</h3>
                        </div>
                        <p class="text-gray-600 text-sm">Manage contact information and duty status. Auto-set by schedule or admin.</p>
                        <div class="mt-4">
                            <span class="inline-block bg-red-100 text-red-800 text-xs px-2 py-1 rounded">Critical: Contact details, Duty status</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script>
        function loadModule(module) {
            // Update active sidebar link
            document.querySelectorAll('.module-link').forEach(link => {
                link.classList.remove('active-module');
                link.classList.add('hover:bg-gray-800');
            });
            document.querySelector(`[data-module="${module}"]`).classList.add('active-module');
            document.querySelector(`[data-module="${module}"]`).classList.remove('hover:bg-gray-800');

            // Show loading
            document.getElementById('module-content').innerHTML = `
                <div class="flex justify-center items-center h-64">
                    <div class="text-center">
                        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto"></div>
                        <p class="mt-4 text-gray-600">Loading module...</p>
                    </div>
                </div>
            `;

            // Load module content
            fetch(`modules/${module}.php`)
                .then(response => response.text())
                .then(html => {
                    document.getElementById('module-content').innerHTML = html;
                })
                .catch(error => {
                    document.getElementById('module-content').innerHTML = `
                        <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                            <p class="text-red-600">Error loading module. Please try again.</p>
                        </div>
                    `;
                });
        }

        // Handle clock in/out
        function toggleDuty() {
            fetch('../ajax/toggle_duty.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const statusDiv = document.getElementById('current-status');
                        if (data.status === 'On-Duty') {
                            statusDiv.className = 'status-on-duty p-3 rounded-lg text-center';
                        } else {
                            statusDiv.className = 'status-off-duty p-3 rounded-lg text-center';
                        }
                        statusDiv.innerHTML = `<p class="text-lg font-bold text-white">${data.status}</p>`;
                        
                        // Show notification
                        showNotification(data.message, 'success');
                    }
                });
        }

        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 p-4 rounded-lg shadow-lg z-50 ${
                type === 'success' ? 'bg-green-100 text-green-800 border border-green-200' :
                type === 'error' ? 'bg-red-100 text-red-800 border border-red-200' :
                'bg-blue-100 text-blue-800 border border-blue-200'
            }`;
            notification.innerHTML = `
                <div class="flex items-center">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : 'info-circle'} mr-2"></i>
                    <span>${message}</span>
                </div>
            `;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.remove();
            }, 3000);
        }
    </script>
</body>
</html>