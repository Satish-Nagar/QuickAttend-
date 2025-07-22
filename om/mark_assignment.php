<?php
require_once '../includes/functions.php';
requireOM();

$om_id = $_SESSION['user_id'];
$section_id = isset($_GET['section_id']) ? (int)$_GET['section_id'] : 0;

if (!$section_id) {
    setFlashMessage('error', 'Section ID is required');
    redirect('dashboard.php');
}

$db = getDB();

// Verify section belongs to this OM
$stmt = $db->prepare("SELECT * FROM sections WHERE id = ? AND om_id = ?");
$stmt->execute([$section_id, $om_id]);
$section = $stmt->fetch();

if (!$section) {
    setFlashMessage('error', 'Section not found or access denied');
    redirect('dashboard.php');
}

// Get students in this section
$stmt = $db->prepare("SELECT * FROM students WHERE section_id = ? ORDER BY name");
$stmt->execute([$section_id]);
$students = $stmt->fetchAll();

if (empty($students)) {
    setFlashMessage('error', 'No students found in this section');
    redirect('dashboard.php');
}

$success = '';
$error = '';
$preview_data = [];
$summary = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $assignment_name = sanitizeInput($_POST['assignment_name'] ?? '');
    $assignment_date = sanitizeInput($_POST['assignment_date'] ?? '');
    $input_scores = trim($_POST['input_scores'] ?? '');

    if (!$assignment_name || !$assignment_date) {
        $error = 'Assignment name and date are required.';
    } elseif (empty($input_scores)) {
        $error = 'Please enter roll number and score pairs.';
    } else {
        // Parse input: expected format "ROLL<TAB or SPACE or COMMA>SCORE" per line
        $lines = preg_split('/\r?\n/', $input_scores);
        $score_map = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;
            // Split by tab, then by comma, then by space
            if (strpos($line, "\t") !== false) {
                $parts = explode("\t", $line);
            } elseif (strpos($line, ",") !== false) {
                $parts = explode(",", $line);
            } else {
                $parts = preg_split('/\s+/', $line);
            }
            $parts = array_map(function($v) { return strtoupper(trim($v)); }, $parts);
            if (count($parts) == 2 && strlen($parts[0]) > 0 && is_numeric($parts[1])) {
                $score_map[$parts[0]] = floatval($parts[1]);
            }
        }
        // Prepare preview and DB data
        $attempted = 0;
        $total_score = 0;
        $preview_data = [];
        foreach ($students as $student) {
            $roll = strtoupper(trim($student['roll_no']));
            $score = isset($score_map[$roll]) ? $score_map[$roll] : 0;
            $status = isset($score_map[$roll]) ? 1 : 0;
            if ($status) {
                $attempted++;
                $total_score += $score;
            }
            $preview_data[] = [
                'id' => $student['id'],
                'roll_no' => $student['roll_no'],
                'name' => $student['name'],
                'score' => $score,
                'status' => $status
            ];
        }
        $summary = [
            'attempted' => $attempted,
            'total' => count($students),
            'average' => $attempted ? round($total_score / $attempted, 2) : 0
        ];

        // Save to DB if requested
        if (isset($_POST['save_assignment'])) {
            // Check for duplicate
            $stmt = $db->prepare("SELECT id FROM assignments WHERE section_id = ? AND assignment_name = ? AND assignment_date = ? LIMIT 1");
            $stmt->execute([$section_id, $assignment_name, $assignment_date]);
            if ($stmt->fetch()) {
                $error = 'Assignment for this section, name, and date already exists.';
            } else {
                try {
                    $db->beginTransaction();
                    foreach ($preview_data as $row) {
                        $stmt = $db->prepare("INSERT INTO assignments (section_id, assignment_name, assignment_date, student_id, score, status) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$section_id, $assignment_name, $assignment_date, $row['id'], $row['score'], $row['status']]);
                    }
                    $db->commit();
                    $success = 'Assignment scores saved successfully!';
                } catch (Exception $e) {
                    $db->rollBack();
                    $error = 'Error saving assignment: ' . $e->getMessage();
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mark Assignment - <?php echo htmlspecialchars($section['name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .main-content { padding: 30px; }
        .assignment-card { background: white; border-radius: 15px; padding: 30px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .form-control { border-radius: 10px; }
        .btn-submit { background: linear-gradient(45deg, #007bff, #00c6ff); border: none; border-radius: 10px; padding: 12px 30px; font-weight: 600; }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.3); }
        .preview-table { background: #fff; border-radius: 10px; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-clipboard-check"></i> Smart Attendance System
            </a>
            <div class="navbar-nav ms-auto">
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['om_name']); ?>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-3 col-lg-2 px-0">
               
            </div>
            <div class="col-md-9 col-lg-10">
                <div class="main-content">
                    <div class="assignment-card">
                        <h3 class="mb-4"><i class="fas fa-book"></i> Mark Assignment - <?php echo htmlspecialchars($section['name']); ?></h3>
                        <?php if ($success): ?>
                            <div class="alert alert-success" role="alert">
                                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($error): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                            </div>
                        <?php endif; ?>
                        <form method="POST" action="">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="assignment_name" class="form-label">Assignment Name *</label>
                                    <input type="text" class="form-control" id="assignment_name" name="assignment_name" value="<?php echo htmlspecialchars($_POST['assignment_name'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="assignment_date" class="form-label">Assignment Date *</label>
                                    <input type="date" class="form-control" id="assignment_date" name="assignment_date" value="<?php echo htmlspecialchars($_POST['assignment_date'] ?? date('Y-m-d')); ?>" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="input_scores" class="form-label">Enter Roll No and Score Pairs *</label>
                                <textarea class="form-control" id="input_scores" name="input_scores" rows="4" placeholder="e.g. 0128CS221026\t20\n0128CS221027\t21\n0128CS221028\t22" required><?php echo htmlspecialchars($_POST['input_scores'] ?? ''); ?></textarea>
                                <div class="form-text">Paste roll number and score pairs from Excel or Google Sheets (tab, space, or comma separated per line).</div>
                            </div>
                            <div class="d-flex justify-content-between">
                                <a href="dashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
                                <button type="submit" name="save_assignment" class="btn btn-primary btn-submit"><i class="fas fa-save"></i> Save Assignment</button>
                            </div>
                        </form>
                        <?php if (!empty($preview_data)): ?>
                            <div class="mt-4">
                                <h5><i class="fas fa-eye"></i> Preview</h5>
                                <div class="table-responsive preview-table">
                                    <table class="table table-bordered table-hover">
                                        <thead>
                                            <tr>
                                                <th>Roll No</th>
                                                <th>Name</th>
                                                <th>Score</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($preview_data as $row): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($row['roll_no']); ?></td>
                                                    <td><?php echo htmlspecialchars($row['name']); ?></td>
                                                    <td><?php echo $row['score']; ?></td>
                                                    <td><?php echo $row['status']; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="mt-3">
                                    <strong>Summary:</strong> <?php echo $summary['attempted']; ?> attempted out of <?php echo $summary['total']; ?> students. Average Score: <?php echo $summary['average']; ?>
                                </div>
                                <div class="mt-3">
                                    <button class="btn btn-info me-2" onclick="copyScores()"><i class="fas fa-copy"></i> Copy Scores</button>
                                    <button class="btn btn-info" onclick="copyStatuses()"><i class="fas fa-copy"></i> Copy Statuses</button>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <footer class="text-center mt-5 mb-3 text-muted small">&lt;GoG&gt; Smart Attendance Tracker Presented By Satish Nagar</footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function copyScores() {
        var scores = [];
        <?php if (!empty($preview_data)): ?>
            <?php foreach ($preview_data as $row): ?>
                scores.push('<?php echo $row['score']; ?>');
            <?php endforeach; ?>
        <?php endif; ?>
        if (scores.length) {
            navigator.clipboard.writeText(scores.join("\n")).then(function() {
                alert('Scores copied!');
            });
        }
    }
    function copyStatuses() {
        var statuses = [];
        <?php if (!empty($preview_data)): ?>
            <?php foreach ($preview_data as $row): ?>
                statuses.push('<?php echo $row['status']; ?>');
            <?php endforeach; ?>
        <?php endif; ?>
        if (statuses.length) {
            navigator.clipboard.writeText(statuses.join("\n")).then(function() {
                alert('Statuses copied!');
            });
        }
    }
    </script>
</body>
</html> 