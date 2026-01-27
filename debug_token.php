<?php
// debug_token.php
require_once 'config/database.php';

// Get token from URL or manually set
$test_token = isset($_GET['token']) ? $_GET['token'] : (isset($_GET['manual']) ? $_GET['manual'] : '');

echo "<h1>Token Debug</h1>";
echo "<p>Use: debug_token.php?token=TOKEN_FROM_EMAIL or debug_token.php?manual=MANUAL_TOKEN</p>";
echo "<hr>";

if (!empty($test_token)) {
    echo "Token: " . htmlspecialchars($test_token) . "<br>";
    echo "Token length: " . strlen($test_token) . "<br>";
    echo "URL decoded: " . urldecode($test_token) . "<br>";
    echo "Raw URL decoded: " . rawurldecode($test_token) . "<br>";
    
    // Check if it's a valid hex token
    if (preg_match('/^[a-f0-9]{64}$/i', $test_token)) {
        echo "<p style='color: green;'>Token format is valid (64 hex characters)</p>";
    } else {
        echo "<p style='color: red;'>Token format is INVALID</p>";
    }
    
    echo "<hr>";
    
    try {
        $conn = getDbConnection();
        
        // Check if token exists
        $query = "SELECT id, email, verification_token, verification_expiry, email_verified, 
                         LENGTH(verification_token) as token_length,
                         created_at
                  FROM users 
                  WHERE verification_token = :token";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':token', $test_token);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            echo "<h2 style='color: green;'>Token FOUND in database!</h2>";
            echo "User ID: " . $user['id'] . "<br>";
            echo "Email: " . $user['email'] . "<br>";
            echo "Stored token: " . $user['verification_token'] . "<br>";
            echo "Stored token length: " . $user['token_length'] . "<br>";
            echo "Expiry: " . $user['verification_expiry'] . "<br>";
            echo "Email verified: " . $user['email_verified'] . "<br>";
            echo "Created: " . $user['created_at'] . "<br>";
            
            // Check expiry
            $expiry = new DateTime($user['verification_expiry']);
            $now = new DateTime();
            echo "Current time: " . $now->format('Y-m-d H:i:s') . "<br>";
            echo "Link expires: " . $expiry->format('Y-m-d H:i:s') . "<br>";
            
            if ($expiry > $now) {
                echo "<p style='color: green;'>Token is still valid!</p>";
            } else {
                echo "<p style='color: red;'>Token has EXPIRED!</p>";
            }
        } else {
            echo "<h2 style='color: red;'>Token NOT FOUND in database</h2>";
            
            // Check similar tokens (first 20 chars)
            $partial = substr($test_token, 0, 20);
            $similar_query = "SELECT id, email, verification_token, 
                                     LENGTH(verification_token) as token_length
                             FROM users 
                             WHERE verification_token LIKE :partial 
                             ORDER BY created_at DESC LIMIT 5";
            $similar_stmt = $conn->prepare($similar_query);
            $similar_stmt->bindValue(':partial', $partial . '%');
            $similar_stmt->execute();
            
            if ($similar_stmt->rowCount() > 0) {
                echo "<h3>Similar tokens found (matching first 20 chars):</h3>";
                while ($row = $similar_stmt->fetch(PDO::FETCH_ASSOC)) {
                    echo "ID: " . $row['id'] . " - Email: " . $row['email'] . 
                         " - Token: " . $row['verification_token'] . 
                         " (Length: " . $row['token_length'] . ")<br>";
                }
            } else {
                echo "<p>No similar tokens found.</p>";
            }
        }
    } catch(PDOException $e) {
        echo "Database error: " . $e->getMessage();
    }
} else {
    echo "<p>No token provided. Add ?token=TOKEN to URL.</p>";
}

// Show recent registrations for debugging
echo "<hr><h2>Recent Registrations (for debugging):</h2>";
try {
    $conn = getDbConnection();
    $recent_query = "SELECT id, email, verification_token, verification_expiry, email_verified, created_at 
                    FROM users 
                    WHERE verification_token IS NOT NULL 
                    ORDER BY created_at DESC LIMIT 10";
    $recent_stmt = $conn->query($recent_query);
    
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Email</th><th>Token (first 20 chars)</th><th>Expiry</th><th>Verified</th><th>Created</th></tr>";
    while ($row = $recent_stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['email'] . "</td>";
        echo "<td>" . substr($row['verification_token'], 0, 20) . "...</td>";
        echo "<td>" . $row['verification_expiry'] . "</td>";
        echo "<td>" . $row['email_verified'] . "</td>";
        echo "<td>" . $row['created_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>