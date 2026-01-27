<?php
// config/rate_limit.php - Rate limiting functions

function check_rate_limit($key, $limit = 5, $timeframe = 3600) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $cache_key = "rate_limit_{$key}_{$ip}";
    
    if (!isset($_SESSION[$cache_key])) {
        $_SESSION[$cache_key] = [
            'count' => 1,
            'time' => time()
        ];
        return true;
    }
    
    $data = $_SESSION[$cache_key];
    
    if (time() - $data['time'] > $timeframe) {
        $_SESSION[$cache_key] = [
            'count' => 1,
            'time' => time()
        ];
        return true;
    }
    
    if ($data['count'] >= $limit) {
        return false;
    }
    
    $_SESSION[$cache_key]['count']++;
    return true;
}