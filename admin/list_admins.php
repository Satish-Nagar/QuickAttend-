<?php
require_once '../config/database.php';

$database = new Database();
$conn = $database->getConnection();

if (!$conn) {
    echo "<p style='color:red;'>Database connection failed.</p>";
    exit;
}

$stmt = $conn->query("SELECT id, username, email, created_at FROM admins");
$admins = $stmt->fetchAll();

if (count($admins) === 0) {
    echo "<p>No admins found in the database.</p>";
} else {
    echo "<h2>Admins in Database</h2>";
    echo "<table border='1' cellpadding='5'><tr><th>ID</th><th>Username</th><th>Email</th><th>Created At</th></tr>";
    foreach ($admins as $admin) {
        echo "<tr>";
        echo "<td>{$admin['id']}</td>";
        echo "<td>" . htmlspecialchars($admin['username']) . "</td>";
        echo "<td>" . htmlspecialchars($admin['email']) . "</td>";
        echo "<td>{$admin['created_at']}</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "<br><a href='login.php'>Back to Login</a>"; 