<?php
// handlers/direct_db.php
function getRemoteConnection() {
    static $conn = null;
    
    if ($conn === null) {
        try {
            $host = "153.92.15.81";
            $dbname = "u514031374_leir";
            $username = "u514031374_leir";
            $password = "leirP@55w0rd";
            $port = 3306;
            
            $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
            
            $conn = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
            ]);
            
        } catch (PDOException $e) {
            // Log error but don't expose details
            error_log("Remote DB Connection Error: " . $e->getMessage());
            return null;
        }
    }
    
    return $conn;
}

// Return connection for use in other handlers
return getRemoteConnection();
?>