<?php
require_once '../config/database.php';

header('Content-Type: application/json');

// Initialize database connection
try {
    $conn = getDbConnection();
} catch(PDOException $e) {
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// Get stats
$stats = [
    'total_users' => getTotalUsers($conn),
    'active_cases' => getActiveCases($conn),
    'system_health' => getSystemHealth($conn),
    'last_backup' => getLastBackup($conn)
];

echo json_encode($stats);

function getTotalUsers($conn) {
    try {
        $query = "SELECT COUNT(*) as count FROM users WHERE status = 'active'";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)$result['count'];
    } catch(Exception $e) {
        return 0;
    }
}

function getActiveCases($conn) {
    try {
        $query = "SELECT COUNT(*) as count FROM reports WHERE status IN ('pending', 'assigned', 'investigating')";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)$result['count'];
    } catch(Exception $e) {
        return 0;
    }
}

function getSystemHealth($conn) {
    try {
        $health_query = "SELECT 
            (SELECT COUNT(*) FROM users WHERE is_active = 1) as active_users,
            (SELECT COUNT(*) FROM reports WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)) as weekly_reports,
            (SELECT COUNT(*) FROM api_integrations WHERE status = 'active') as active_apis,
            (SELECT COUNT(*) FROM file_encryption_logs WHERE last_decrypted IS NOT NULL) as decrypted_files,
            (SELECT COUNT(*) FROM activity_logs WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)) as hourly_activity,
            (SELECT MAX(created_at) FROM activity_logs) as last_activity";
        
        $stmt = $conn->prepare($health_query);
        $stmt->execute();
        $health_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Calculate a health score (simplified example)
        $health_score = 95; // Default
        $active_users = $health_data['active_users'] ?? 0;
        $weekly_reports = $health_data['weekly_reports'] ?? 0;
        
        // You can add more sophisticated health calculations here
        if ($active_users > 0 && $weekly_reports > 0) {
            $health_score = min(100, 80 + ($weekly_reports / max($active_users, 1)) * 2);
        }
        
        return [
            'score' => round($health_score),
            'status' => $health_score >= 90 ? 'excellent' : ($health_score >= 70 ? 'good' : ($health_score >= 50 ? 'warning' : 'critical')),
            'metrics' => $health_data
        ];
    } catch(Exception $e) {
        return [
            'score' => 0,
            'status' => 'error',
            'metrics' => []
        ];
    }
}

function getLastBackup($conn) {
    try {
        // First check if backup_logs table exists
        $table_check = $conn->query("SHOW TABLES LIKE 'backup_logs'")->rowCount();
        
        if ($table_check > 0) {
            $query = "SELECT backup_time, file_size, status FROM backup_logs ORDER BY backup_time DESC LIMIT 1";
            $stmt = $conn->prepare($query);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                return [
                    'time' => $result['backup_time'],
                    'size' => $result['file_size'],
                    'status' => $result['status']
                ];
            }
        }
        
        // Fallback: check for backup files in the filesystem
        $backup_dir = '../backups/';
        if (is_dir($backup_dir)) {
            $files = glob($backup_dir . '*.sql');
            if (!empty($files)) {
                $latest_file = max($files);
                return [
                    'time' => date('Y-m-d H:i:s', filemtime($latest_file)),
                    'size' => filesize($latest_file),
                    'status' => 'file_found'
                ];
            }
        }
        
        return [
            'time' => 'Never',
            'size' => '0',
            'status' => 'no_backup'
        ];
    } catch(Exception $e) {
        return [
            'time' => 'Unknown',
            'size' => '0',
            'status' => 'error'
        ];
    }
}

// Close connection
if (isset($conn)) {
    $conn = null;
}
?>