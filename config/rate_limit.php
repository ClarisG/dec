<?php
// config/rate_limit.php
function getRateLimitInfo($user_id) {
    try {
        $conn = getDbConnection();
        
        // Count reports in the last hour
        $query = "SELECT COUNT(*) as count, MAX(created_at) as last_report 
                  FROM reports 
                  WHERE user_id = :user_id 
                  AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'count' => $result['count'] ?? 0,
            'last_report' => $result['last_report'] ?? null
        ];
    } catch (Exception $e) {
        error_log("Error getting rate limit info: " . $e->getMessage());
        return [
            'count' => 0,
            'last_report' => null
        ];
    }
}
?>