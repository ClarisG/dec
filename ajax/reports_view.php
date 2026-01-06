<?php
// reports_view.php
session_start();
require_once './config/database.php';

class ReportViewer {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function getReportById($report_id) {
        try {
            $sql = "SELECT 
                r.*,
                u.first_name AS reporter_first_name,
                u.last_name AS reporter_last_name,
                u.contact_number AS reporter_contact,
                rt.type_name AS report_type_name,
                rt.jurisdiction AS jurisdiction,
                rt.severity_level AS severity,
                a.first_name AS assigned_first_name,
                a.last_name AS assigned_last_name,
                s.first_name AS submitted_first_name,
                s.last_name AS submitted_last_name,
                GROUP_CONCAT(fel.original_name) AS evidence_files
            FROM reports r
            LEFT JOIN users u ON r.user_id = u.id
            LEFT JOIN report_types rt ON r.report_type_id = rt.id
            LEFT JOIN users a ON r.assigned_to = a.id
            LEFT JOIN users s ON r.submitted_by = s.id
            LEFT JOIN file_encryption_logs fel ON r.id = fel.report_id
            WHERE r.id = ?
            GROUP BY r.id";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$report_id]);
            $report = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$report) {
                throw new Exception("Report not found");
            }
            
            // Get status history
            $report['status_history'] = $this->getStatusHistory($report_id);
            
            // Get messages
            $report['messages'] = $this->getReportMessages($report_id);
            
            // Get AI classification if exists
            $report['ai_classification'] = $this->getAIClassification($report_id);
            
            return $report;
            
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            throw new Exception("Unable to fetch report details: " . $e->getMessage());
        }
    }
    
    private function getStatusHistory($report_id) {
        $sql = "SELECT 
            rsh.*,
            u.first_name,
            u.last_name
        FROM report_status_history rsh
        LEFT JOIN users u ON rsh.updated_by = u.id
        WHERE rsh.report_id = ?
        ORDER BY rsh.created_at DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$report_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function getReportMessages($report_id) {
        $sql = "SELECT 
            m.*,
            s.first_name AS sender_first_name,
            s.last_name AS sender_last_name,
            r.first_name AS receiver_first_name,
            r.last_name AS receiver_last_name
        FROM messages m
        LEFT JOIN users s ON m.sender_id = s.id
        LEFT JOIN users r ON m.receiver_id = r.id
        WHERE m.report_id = ?
        ORDER BY m.created_at ASC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$report_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function getAIClassification($report_id) {
        $sql = "SELECT * FROM ai_classification_logs 
                WHERE report_id = ? 
                ORDER BY created_at DESC 
                LIMIT 1";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$report_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function getUserReports($user_id, $status = null, $limit = 50) {
        try {
            $params = [$user_id];
            $sql = "SELECT 
                r.*,
                rt.type_name,
                rt.jurisdiction,
                u.first_name AS assigned_first_name,
                u.last_name AS assigned_last_name,
                (SELECT status FROM report_status_history 
                 WHERE report_id = r.id 
                 ORDER BY created_at DESC LIMIT 1) AS current_status
            FROM reports r
            LEFT JOIN report_types rt ON r.report_type_id = rt.id
            LEFT JOIN users u ON r.assigned_to = u.id
            WHERE r.user_id = ?";
            
            if ($status) {
                $sql .= " AND r.status = ?";
                $params[] = $status;
            }
            
            $sql .= " ORDER BY r.created_at DESC LIMIT ?";
            $params[] = $limit;
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            return [];
        }
    }
    
    public function updateReportStatus($report_id, $status, $user_id, $notes = null) {
        try {
            $this->pdo->beginTransaction();
            
            // Update main report
            $sql = "UPDATE reports 
                    SET status = ?, updated_at = NOW() 
                    WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$status, $report_id]);
            
            // Add to status history
            $sql = "INSERT INTO report_status_history 
                    (report_id, status, updated_by, notes, created_at) 
                    VALUES (?, ?, ?, ?, NOW())";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$report_id, $status, $user_id, $notes]);
            
            // Log activity
            $this->logActivity($user_id, 'update_report_status', 
                "Updated report #$report_id status to $status", $report_id);
            
            $this->pdo->commit();
            return true;
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Error updating report status: " . $e->getMessage());
            return false;
        }
    }
    
    private function logActivity($user_id, $action, $description, $affected_id = null) {
        $sql = "INSERT INTO activity_logs 
                (user_id, action, description, affected_id, affected_type, ip_address, created_at) 
                VALUES (?, ?, ?, ?, 'report', ?, NOW())";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $user_id, 
            $action, 
            $description, 
            $affected_id,
            $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
        ]);
    }
}

// Usage in your page:
try {
    $db = new PDO(
        "mysql:host=localhost:3307;dbname=leir_db;charset=utf8mb4",
        "your_username",
        "your_password",
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    $reportViewer = new ReportViewer($db);
    
    // Check if viewing specific report
    if (isset($_GET['id'])) {
        $report_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
        
        if ($report_id) {
            $report = $reportViewer->getReportById($report_id);
            
            // Display report
            echo "<h1>Report #" . htmlspecialchars($report['report_number']) . "</h1>";
            echo "<p><strong>Title:</strong> " . htmlspecialchars($report['title']) . "</p>";
            echo "<p><strong>Type:</strong> " . htmlspecialchars($report['report_type_name']) . "</p>";
            echo "<p><strong>Status:</strong> " . htmlspecialchars($report['status']) . "</p>";
            echo "<p><strong>Description:</strong><br>" . nl2br(htmlspecialchars($report['description'])) . "</p>";
            
            // Display status history
            if (!empty($report['status_history'])) {
                echo "<h3>Status History</h3>";
                foreach ($report['status_history'] as $history) {
                    echo "<p>" . date('Y-m-d H:i', strtotime($history['created_at'])) . " - ";
                    echo htmlspecialchars($history['status']) . " by ";
                    echo htmlspecialchars($history['first_name'] . ' ' . $history['last_name']) . "</p>";
                    if ($history['notes']) {
                        echo "<p>Notes: " . htmlspecialchars($history['notes']) . "</p>";
                    }
                }
            }
            
        } else {
            echo "Invalid report ID";
        }
    } 
    // List user's reports
    else if (isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
        $status = $_GET['status'] ?? null;
        
        $reports = $reportViewer->getUserReports($user_id, $status);
        
        if (empty($reports)) {
            echo "<p>No reports found.</p>";
        } else {
            echo "<table border='1' style='width:100%; border-collapse: collapse;'>";
            echo "<tr>
                    <th>Report #</th>
                    <th>Title</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Actions</th>
                  </tr>";
            
            foreach ($reports as $report) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($report['report_number']) . "</td>";
                echo "<td>" . htmlspecialchars($report['title']) . "</td>";
                echo "<td>" . htmlspecialchars($report['type_name']) . "</td>";
                echo "<td>" . htmlspecialchars($report['status']) . "</td>";
                echo "<td>" . date('Y-m-d', strtotime($report['created_at'])) . "</td>";
                echo "<td><a href='?id=" . $report['id'] . "'>View</a></td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    } else {
        echo "Please login to view reports.";
    }
    
} catch (PDOException $e) {
    echo "Database connection error: " . $e->getMessage();
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>