<?php
require_once '../includes/functions.php';

echo "<h2>Testing Default Admin Login</h2>";

$test_username = 'admin';
$test_password = 'password';

try {
    $db = getDB();
    if (!$db) {
        echo "<p style='color: red;'>❌ Database connection failed!</p>";
        exit;
    }
    
    $stmt = $db->prepare("SELECT id, username, password FROM admins WHERE username = ?");
    $stmt->execute([$test_username]);
    $admin = $stmt->fetch();
    
    if ($admin) {
        echo "<p style='color: green;'>✅ Found admin user: {$admin['username']}</p>";
        
        if (verifyPassword($test_password, $admin['password'])) {
            echo "<p style='color: green;'>✅ Password verification successful!</p>";
            echo "<p><strong>Default Admin Credentials:</strong></p>";
            echo "<ul>";
            echo "<li><strong>Username:</strong> admin</li>";
            echo "<li><strong>Password:</strong> password</li>";
            echo "<li><strong>Email:</strong> admin@attendance.com</li>";
            echo "</ul>";
        } else {
            echo "<p style='color: red;'>❌ Password verification failed!</p>";
            echo "<p>Hash in database: " . substr($admin['password'], 0, 20) . "...</p>";
        }
    } else {
        echo "<p style='color: red;'>❌ Admin user not found!</p>";
        echo "<p>Please make sure the database has been set up with the default admin account.</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}

echo "<br><a href='login.php'>Go to Login Page</a>";
?> 