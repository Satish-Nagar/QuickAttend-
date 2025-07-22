<?php
require_once '../config/database.php';

echo "<h2>Database Connection Test</h2>";

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    if ($conn) {
        echo "<p style='color: green;'>✅ Database connection successful!</p>";
        
        // Check if admins table exists
        $stmt = $conn->query("SHOW TABLES LIKE 'admins'");
        if ($stmt->rowCount() > 0) {
            echo "<p style='color: green;'>✅ Admins table exists!</p>";
            
            // Check admin accounts
            $stmt = $conn->query("SELECT id, username, email FROM admins");
            $admins = $stmt->fetchAll();
            
            if (count($admins) > 0) {
                echo "<p style='color: green;'>✅ Found " . count($admins) . " admin account(s):</p>";
                echo "<ul>";
                foreach ($admins as $admin) {
                    echo "<li>ID: {$admin['id']}, Username: {$admin['username']}, Email: {$admin['email']}</li>";
                }
                echo "</ul>";
            } else {
                echo "<p style='color: orange;'>⚠️ No admin accounts found in the database.</p>";
                echo "<p>You can create an admin account using the registration page.</p>";
            }
        } else {
            echo "<p style='color: red;'>❌ Admins table does not exist!</p>";
            echo "<p>Please run the database setup script: <code>database/attendance_system.sql</code></p>";
        }
    } else {
        echo "<p style='color: red;'>❌ Database connection failed!</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}

echo "<br><a href='login.php'>Back to Login</a>";
?> 