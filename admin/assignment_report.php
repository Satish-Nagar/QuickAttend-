<?php
require_once '../includes/functions.php';
requireAdmin();
require_once '../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$admin_id = $_SESSION['user_id'];
$db = getDB();
$stmt = $db->prepare("SELECT profile_picture FROM admins WHERE id = ?");
$stmt->execute([$admin_id]);
$admin = $stmt->fetch();
$profile_picture = $admin && $admin['profile_picture']
    ? '../uploads/' . htmlspecialchars($admin['profile_picture'])
    : 'https://via.placeholder.com/40x40.png?text=Admin';

// Assignment filters
$assignment_colleges = $db->query("SELECT DISTINCT college FROM operation_managers ORDER BY college")->fetchAll();
$assignment_college = isset($_GET['assignment_college']) ? $_GET['assignment_college'] : '';
$assignment_date = isset($_GET['assignment_date']) ? $_GET['assignment_date'] : '';
$assignment_dates = [];
if ($assignment_college) {
    $stmt = $db->prepare("SELECT DISTINCT DATE(a.created_at) FROM assignments a JOIN sections s ON a.section_id = s.id JOIN operation_managers om ON s.om_id = om.id WHERE om.college = ? ORDER BY DATE(a.created_at) DESC");
    $stmt->execute([$assignment_college]);
    $assignment_dates = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Assignment Data
$assignment_sections = [];
$assignment_submitted = 0;
$assignment_total = 0;
$show_all_colleges_today = false;
$all_colleges = [];
$all_submitted = 0;
$all_total = 0;
$today = date('Y-m-d');
if (!$assignment_college && !$assignment_date) {
    // Default: show all colleges for today
    $show_all_colleges_today = true;
    $stmt = $db->prepare("SELECT om.college, SUM(a.status) as submitted, COUNT(a.id) as total, AVG(a.score) as avg_score FROM assignments a JOIN sections s ON a.section_id = s.id JOIN operation_managers om ON s.om_id = om.id WHERE DATE(a.created_at) = ? GROUP BY om.college");
    $stmt->execute([$today]);
    $all_colleges = $stmt->fetchAll();
    $all_submitted = array_sum(array_column($all_colleges, 'submitted'));
    $all_total = array_sum(array_column($all_colleges, 'total'));
} elseif ($assignment_college && $assignment_date) {
    // Get all sections for the selected college
    $stmt = $db->prepare("SELECT s.id, s.name FROM sections s JOIN operation_managers om ON s.om_id = om.id WHERE om.college = ? ORDER BY s.name");
    $stmt->execute([$assignment_college]);
    $all_sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Get assignment scores for each section (LEFT JOIN to include sections with no assignments)
    $stmt = $db->prepare("SELECT s.id as section_id, s.name as section_name, AVG(a.score) as avg_score, COUNT(a.id) as total FROM sections s JOIN operation_managers om ON s.om_id = om.id LEFT JOIN assignments a ON a.section_id = s.id AND DATE(a.created_at) = ? WHERE om.college = ? GROUP BY s.id, s.name ORDER BY s.name");
    $stmt->execute([$assignment_date, $assignment_college]);
    $assignment_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Build a map of section_id to assignment
    $assignment_map = [];
    foreach ($assignment_raw as $row) {
        $assignment_map[$row['section_id']] = $row;
    }
    // Ensure every section is present in the final array
    $assignment_sections = [];
    foreach ($all_sections as $section) {
        $sid = $section['id'];
        if (isset($assignment_map[$sid])) {
            $assignment_sections[] = $assignment_map[$sid];
            $assignment_submitted += (int)($assignment_map[$sid]['submitted'] ?? 0);
            $assignment_total += (int)($assignment_map[$sid]['total'] ?? 0);
        } else {
            $assignment_sections[] = [
                'section_id' => $sid,
                'section_name' => $section['name'],
                'avg_score' => 0,
                'submitted' => 0,
                'total' => 0
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assignment Report - Smart Attendance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .navbar {
            background: linear-gradient(45deg, #667eea, #764ba2);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .sidebar {
            background: white;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            min-height: 100vh;
            padding-top: 30px;
        }
        .sidebar .nav-link {
            color: #333 !important;
            padding: 12px 20px;
            border-radius: 8px;
            margin: 5px 10px;
            transition: all 0.3s;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.08rem;
        }
        .sidebar .nav-link.active, .sidebar .nav-link:hover {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: #fff !important;
        }
        .sidebar .nav-link i {
            font-size: 1.2rem;
            margin-right: 8px;
        }
        .main-content {
            padding: 40px 30px 30px 30px;
            min-height: 100vh;
        }
        .report-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(102,126,234,0.08);
            padding: 32px 24px;
            margin-top: 24px;
            max-width: 900px;
            margin-left: auto;
            margin-right: auto;
        }
        @media (max-width: 991.98px) {
            .sidebar { min-height: auto; }
            .main-content { padding: 20px 5px; }
            .report-card { padding: 18px 8px; }
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-clipboard-check"></i> Smart Attendance System
            </a>
            <div class="navbar-nav ms-auto">
                <div class="nav-item dropdown d-flex align-items-center">
                    <img src="<?php echo $profile_picture; ?>" alt="Profile" class="rounded-circle me-2" style="width:36px;height:36px;object-fit:cover;">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user-shield"></i> Admin
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
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 px-0">
                <div class="sidebar">
                    <nav class="nav flex-column">
                        <a class="nav-link<?php if ($current_page == 'dashboard.php') echo ' active'; ?>" href="dashboard.php">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                        <a class="nav-link<?php if ($current_page == 'register_om.php') echo ' active'; ?>" href="register_om.php">
                            <i class="fas fa-user-plus"></i> Register OM
                        </a>
                        <a class="nav-link<?php if ($current_page == 'bulk_register.php') echo ' active'; ?>" href="bulk_register.php">
                            <i class="fas fa-upload"></i> Bulk Register
                        </a>
                        <a class="nav-link<?php if ($current_page == 'manage_oms.php') echo ' active'; ?>" href="manage_oms.php">
                            <i class="fas fa-users-cog"></i> Manage OMs
                        </a>
                        <a class="nav-link<?php if ($current_page == 'attendance_report.php') echo ' active'; ?>" href="attendance_report.php">
                            <i class="fas fa-chart-bar"></i> Attendance Report
                        </a>
                        <a class="nav-link<?php if ($current_page == 'assignment_report.php') echo ' active'; ?>" href="assignment_report.php">
                            <i class="fas fa-chart-bar"></i> Assignment Report
                        </a>
                        <a class="nav-link<?php if ($current_page == 'settings.php') echo ' active'; ?>" href="settings.php">
                            <i class="fas fa-cog"></i> Settings
                        </a>
                    </nav>
                </div>
            </div>
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
                <div class="main-content">
                    <div class="report-card">
                        <h2 class="mb-4">Assignment Report</h2>
                        <a href="dashboard.php" class="btn btn-secondary btn-sm mb-3">&larr; Back to Dashboard</a>
                        <form class="row g-3 mb-4" method="get" id="assignment-filter-form">
                            <div class="col-md-4">
                                <label for="assignment_college" class="form-label">College</label>
                                <select name="assignment_college" id="assignment_college" class="form-select">
                                    <option value="">Select College</option>
                                    <?php foreach ($assignment_colleges as $c): ?>
                                        <option value="<?php echo htmlspecialchars($c['college']); ?>" <?php if ($assignment_college == $c['college']) echo 'selected'; ?>>
                                            <?php echo htmlspecialchars($c['college']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="assignment_date" class="form-label">Date</label>
                                <select name="assignment_date" id="assignment_date" class="form-select" <?php if (!$assignment_college) echo 'disabled'; ?>>
                                    <option value="">Select Date</option>
                                    <?php if ($assignment_college): ?>
                                        <?php foreach ($assignment_dates as $d): ?>
                                            <option value="<?php echo $d; ?>" <?php if ($assignment_date == $d) echo 'selected'; ?>><?php echo $d; ?></option>
                                        <?php endforeach; ?>
                                        <?php if (empty($assignment_dates)): ?>
                                            <option value="">No data available</option>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="col-md-4 align-self-end">
                                <button type="submit" class="btn btn-primary w-100">Show Report</button>
                            </div>
                        </form>
                        <?php if ($show_all_colleges_today): ?>
                            <?php if (count($all_colleges) > 0): ?>
                                <div class="row mb-4">
                                    <div class="col-md-12 mb-3">
                                        <div class="card">
                                            <div class="card-header">Avg. Assignment Score by College (Today)</div>
                                            <div class="card-body">
                                                <canvas id="allCollegesBar"></canvas>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <script>
                                var allCollegesLabels = <?php echo json_encode(array_column($all_colleges, 'college')); ?>;
                                var allCollegesData = <?php echo json_encode(array_map(function($row) { return round($row['avg_score'],2); }, $all_colleges)); ?>;
                                var ctxBar = document.getElementById('allCollegesBar').getContext('2d');
                                new Chart(ctxBar, {
                                    type: 'bar',
                                    data: {
                                        labels: allCollegesLabels,
                                        datasets: [{
                                            label: 'Avg. Assignment Score',
                                            data: allCollegesData,
                                            backgroundColor: 'rgba(255, 206, 86, 0.7)'
                                        }]
                                    },
                                    options: {responsive: true, plugins: {legend: {display: false}}}
                                });
                                </script>
                            <?php else: ?>
                                <div class="alert alert-info">No assignment data for today.</div>
                            <?php endif; ?>
                        <?php elseif ($assignment_college && $assignment_date): ?>
                            <div class="row mb-4">
                                <div class="col-md-12 mb-3">
                                    <div class="card">
                                        <div class="card-header">Section-wise Avg. Assignment Score</div>
                                        <div class="card-body">
                                            <canvas id="assignmentBar"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <script>
                            var assignmentLabels = <?php echo json_encode(array_column($assignment_sections, 'section_name')); ?>;
                            var assignmentData = <?php echo json_encode(array_map(function($row) { return round($row['avg_score'],2); }, $assignment_sections)); ?>;
                            var ctxBar = document.getElementById('assignmentBar').getContext('2d');
                            new Chart(ctxBar, {
                                type: 'bar',
                                data: {
                                    labels: assignmentLabels,
                                    datasets: [{
                                        label: 'Avg. Assignment Score',
                                        data: assignmentData,
                                        backgroundColor: 'rgba(255, 206, 86, 0.7)'
                                    }]
                                },
                                options: {responsive: true, plugins: {legend: {display: false}}}
                            });
                            </script>
                            <div class="card mt-4 mb-4">
                                <div class="card-header">Section Assignment Details</div>
                                <div class="card-body table-responsive">
                                    <table class="table table-bordered table-striped">
                                        <thead><tr><th>Section</th><th>Avg. Score</th><th>Submitted</th><th>Total</th></tr></thead>
                                        <tbody>
                                        <?php foreach ($assignment_sections as $row): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($row['section_name']) ?></td>
                                                <td><?= round($row['avg_score'],2) ?></td>
                                                <td><?= (int)($row['submitted'] ?? 0) ?></td>
                                                <td><?= (int)($row['total'] ?? 0) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
    $(function() {
        $('#assignment_college').on('change', function() {
            var college = $(this).val();
            if (college) {
                $.get('get_dates.php', {college: college}, function(data) {
                    var options = '<option value="">Select Date</option>';
                    var dates = JSON.parse(data);
                    if (dates.length === 0) {
                        options += '<option value="">No data available</option>';
                    } else {
                        $.each(dates, function(i, d) {
                            options += '<option value="'+d+'">'+d+'</option>';
                        });
                    }
                    $('#assignment_date').html(options).prop('disabled', false);
                });
            } else {
                $('#assignment_date').html('<option value="">Select Date</option>').prop('disabled', true);
            }
        });
    });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 