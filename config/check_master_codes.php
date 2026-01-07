<?php
require_once 'config/database.php';

try {
    $conn = getDbConnection();
    
    // Check all personnel users
    $query = "SELECT id, username, email, role, master_code, is_master_code_used 
              FROM users 
              WHERE role IN ('secretary', 'captain', 'admin', 'tanod')";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    
    echo "<h2>Personnel Master Codes</h2>";
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>Master Code</th><th>Used?</th></tr>";
    
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['username'] . "</td>";
        echo "<td>" . $row['email'] . "</td>";
        echo "<td>" . $row['role'] . "</td>";
        echo "<td>" . ($row['master_code'] ? $row['master_code'] : 'NOT SET') . "</td>";
        echo "<td>" . ($row['is_master_code_used'] ? 'Yes' : 'No') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>