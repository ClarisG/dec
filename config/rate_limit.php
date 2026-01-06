<?php
// config/rate_limit.php - Rate limiting functions

function checkRateLimit($user_id) {
    $conn = getDbConnection();
    $hour_ago = date('Y-m-d H:i:s', strtotime('-1 hour'));
    
    $query = "SELECT COUNT(*) as count 
              FROM reports 
              WHERE user_id = :user_id 
              AND created_at > :hour_ago";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([':user_id' => $user_id, ':hour_ago' => $hour_ago]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Limit to 5 reports per hour
    if ($result['count'] >= 5) {
        return false;
    }
    return true;
}

function getRateLimitInfo($user_id) {
    $conn = getDbConnection();
    $hour_ago = date('Y-m-d H:i:s', strtotime('-1 hour'));
    
    $query = "SELECT COUNT(*) as count, 
              MAX(created_at) as last_report 
              FROM reports 
              WHERE user_id = :user_id 
              AND created_at > :hour_ago";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([':user_id' => $user_id, ':hour_ago' => $hour_ago]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
?>