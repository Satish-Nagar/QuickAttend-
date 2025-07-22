<?php
require_once '../includes/functions.php';
requireOM();

$db = getDB();
$om_id = $_SESSION['user_id'];
$section_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch section and check ownership
$stmt = $db->prepare("SELECT * FROM sections WHERE id = ? AND om_id = ?");
$stmt->execute([$section_id, $om_id]);
$section = $stmt->fetch();
if (!$section) {
    echo '<div class="alert alert-danger m-5">Section not found or access denied.</div>';
    exit;
}

// Fetch students in this section
$stmt = $db->prepare("SELECT * FROM students WHERE section_id = ? ORDER BY name");
$stmt->execute([$section_id]);
$students = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Section - Smart Attendance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <h2>Section: <?php echo htmlspecialchars($section['name']); ?></h2>
    <a href="sections.php" class="btn btn-secondary btn-sm mb-3">&larr; Back to Sections</a>
    <div class="card mb-4">
        <div class="card-header">Section Details</div>
        <div class="card-body">
            <p><strong>Description:</strong> <?php echo htmlspecialchars($section['description']); ?></p>
            <p><strong>Created At:</strong> <?php echo htmlspecialchars($section['created_at']); ?></p>
        </div>
    </div>
    <div class="card">
        <div class="card-header">Students in this Section</div>
        <div class="card-body">
            <?php if (empty($students)): ?>
                <p>No students found in this section.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Roll Number</th>
                                <th>Contact</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($students as $student): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($student['name']); ?></td>
                                <td><?php echo htmlspecialchars($student['roll_no']); ?></td>
                                <td><?php echo htmlspecialchars($student['contact']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html> 