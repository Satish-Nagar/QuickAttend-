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
$binary_output = '';
$attendance_stats = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $present_rolls_input = sanitizeInput($_POST['present_rolls']);
    $date = getCurrentDate();
    
    if (empty($present_rolls_input)) {
        $error = 'Please enter the roll numbers of present students';
    } else {
        // Parse present roll numbers (comma-separated)
        $present_rolls = array_map('trim', explode(',', $present_rolls_input));
        $present_rolls = array_filter($present_rolls); // Remove empty values
        
        // Validate roll numbers (should be 3 digits)
        foreach ($present_rolls as $roll) {
            if (!preg_match('/^\d{3}$/', $roll)) {
                $error = 'Roll numbers should be exactly 3 digits';
                break;
            }
        }
        
        if (!$error) {
            // Generate binary string
            $binary_output = generateBinaryString($present_rolls, $students);
            // Convert to row-wise (each value on a new line)
            $binary_output = str_replace(',', "\n", $binary_output);
            $attendance_stats = getAttendanceStats(str_replace("\n", ',', $binary_output));
            
            // Check if attendance already exists for today
            $stmt = $db->prepare("SELECT id FROM attendance WHERE section_id = ? AND date = ?");
            $stmt->execute([$section_id, $date]);
            
            if ($stmt->fetch()) {
                $error = 'Attendance for today has already been marked. You can view it in the attendance history.';
            } else {
                // Save attendance record
                try {
                    $stmt = $db->prepare("INSERT INTO attendance (section_id, date, binary_string, present_count, total_count) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$section_id, $date, $binary_output, $attendance_stats['present'], $attendance_stats['total']]);
                    
                    $success = 'Attendance marked successfully!';
                    
                } catch (Exception $e) {
                    $error = 'Error saving attendance: ' . $e->getMessage();
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
    <title>Mark Attendance - <?php echo htmlspecialchars($section['name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .navbar {
            background: linear-gradient(45deg, #28a745, #20c997);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .sidebar {
            background: white;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
            min-height: calc(100vh - 76px);
        }
        .sidebar .nav-link {
            color: #333;
            padding: 12px 20px;
            border-radius: 8px;
            margin: 5px 10px;
            transition: all 0.3s ease;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: linear-gradient(45deg, #28a745, #20c997);
            color: white;
        }
        .main-content {
            padding: 30px;
        }
        .attendance-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        .form-control {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 12px 15px;
            transition: all 0.3s ease;
        }
        .form-control:focus {
            border-color: #28a745;
            box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
        }
        .btn-submit {
            background: linear-gradient(45deg, #28a745, #20c997);
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }
        .binary-output {
            background: #f8f9fa;
            border: 2px solid #28a745;
            border-radius: 10px;
            padding: 15px;
            font-family: 'Courier New', monospace;
            font-size: 1.1rem;
            word-break: break-all;
        }
        .student-list {
            max-height: 400px;
            overflow-y: auto;
        }
        .student-item {
            padding: 8px 12px;
            border-bottom: 1px solid #e9ecef;
        }
        .student-item:last-child {
            border-bottom: none;
        }
        .roll-suffix {
            background: #e9ecef;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.9rem;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
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
                        <li><a class="dropdown-item" href="profile.php">
                            <i class="fas fa-user"></i> Profile
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
           

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
                <div class="main-content">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="attendance-card">
                                <h3 class="mb-4">
                                    <i class="fas fa-check"></i> Mark Attendance - <?php echo htmlspecialchars($section['name']); ?>
                                </h3>
                                
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
                                    <div class="mb-4">
                                        <label for="present_rolls" class="form-label">
                                            Enter Last 3 Digits of Present Students' Roll Numbers *
                                        </label>
                                        <input type="text" class="form-control" id="present_rolls" name="present_rolls" 
                                               value="<?php echo isset($_POST['present_rolls']) ? htmlspecialchars($_POST['present_rolls']) : ''; ?>" 
                                               placeholder="e.g., 123, 125, 141" required>
                                        <div class="form-text">
                                            Enter the last 3 digits of roll numbers separated by commas
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between">
                                        <a href="dashboard.php" class="btn btn-secondary">
                                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                                        </a>
                                        <button type="submit" class="btn btn-success btn-submit">
                                            <i class="fas fa-check"></i> Mark Attendance
                                        </button>
                                    </div>
                                </form>
                                
                                <?php if ($binary_output): ?>
                                    <div class="mt-4">
                                        <h5><i class="fas fa-clipboard"></i> Generated Binary Output</h5>
                                        <div class="binary-output" id="binaryOutput">
                                            <?php echo $binary_output; ?>
                                        </div>
                                        <div class="mt-3">
                                            <button class="btn btn-primary" onclick="copyToClipboard(event)">
                                                <i class="fas fa-copy"></i> Copy to Clipboard
                                            </button>
                                            <?php if ($attendance_stats): ?>
                                                <div class="mt-2">
                                                    <strong>Statistics:</strong> 
                                                    <?php echo $attendance_stats['present']; ?> present out of 
                                                    <?php echo $attendance_stats['total']; ?> students 
                                                    (<?php echo $attendance_stats['percentage']; ?>%)
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="attendance-card">
                                <h5 class="mb-3">
                                    <i class="fas fa-users"></i> Student List
                                </h5>
                                <div class="student-list">
                                    <?php foreach ($students as $index => $student): ?>
                                        <div class="student-item">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <strong><?php echo htmlspecialchars($student['name']); ?></strong>
                                                    <br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($student['roll_no']); ?></small>
                                                </div>
                                                <span class="roll-suffix">
                                                    <?php echo substr($student['roll_no'], -3); ?>
                                                </span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="mt-3 text-center">
                                    <small class="text-muted">
                                        Total Students: <?php echo count($students); ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="text-center mt-5 mb-3 text-muted small">
        &lt;GoG&gt; Smart Attendance Tracker Presented By Satish Nagar
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
function copyToClipboard(event) {
    const binaryOutput = document.getElementById('binaryOutput');
    if (!binaryOutput || !binaryOutput.textContent.trim()) {
        alert('Nothing to copy!');
        return;
    }
    const text = binaryOutput.textContent;
    navigator.clipboard.writeText(text).then(function() {
        // Show success message
        const button = event.target;
        const originalText = button.innerHTML;
        button.innerHTML = '<i class="fas fa-check"></i> Copied!';
        button.classList.remove('btn-primary');
        button.classList.add('btn-success');
        setTimeout(function() {
            button.innerHTML = originalText;
            button.classList.remove('btn-success');
            button.classList.add('btn-primary');
        }, 2000);
    }).catch(function(err) {
        console.error('Could not copy text: ', err);
        alert('Failed to copy to clipboard');
    });
}
</script>
    <script src="assignment_modal.js"></script>
</body>
</html> 