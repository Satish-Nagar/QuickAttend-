<?php
require_once '../includes/functions.php';
requireAdmin();
$admin_id = $_SESSION['user_id'];
$db = getDB();
$stmt = $db->prepare("SELECT profile_picture FROM admins WHERE id = ?");
$stmt->execute([$admin_id]);
$admin = $stmt->fetch();
$profile_picture = $admin && $admin['profile_picture']
    ? '../uploads/' . htmlspecialchars($admin['profile_picture'])
    : 'https://via.placeholder.com/40x40.png?text=Admin';
require_once '../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$db = getDB();

// Attendance filters
$attendance_colleges = $db->query("SELECT DISTINCT college FROM operation_managers ORDER BY college")->fetchAll();
$attendance_college = isset($_GET['attendance_college']) ? $_GET['attendance_college'] : '';
$attendance_date = isset($_GET['attendance_date']) ? $_GET['attendance_date'] : '';
$attendance_dates = [];
if ($attendance_college) {
    $stmt = $db->prepare("SELECT DISTINCT a.date FROM attendance a JOIN sections s ON a.section_id = s.id JOIN operation_managers om ON s.om_id = om.id WHERE om.college = ? ORDER BY a.date DESC");
    $stmt->execute([$attendance_college]);
    $attendance_dates = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Attendance Data
$attendance_sections = [];
$attendance_present = 0;
$attendance_total = 0;
$show_all_colleges_today = false;
$all_colleges = [];
$all_present = 0;
$all_total = 0;
$today = date('Y-m-d');
if (!$attendance_college && !$attendance_date) {
    // Default: show all colleges for today
    $show_all_colleges_today = true;
    $stmt = $db->prepare("SELECT om.college, SUM(a.present_count) as present, SUM(a.total_count) as total FROM attendance a JOIN sections s ON a.section_id = s.id JOIN operation_managers om ON s.om_id = om.id WHERE a.date = ? GROUP BY om.college");
    $stmt->execute([$today]);
    $all_colleges = $stmt->fetchAll();
    $all_present = array_sum(array_column($all_colleges, 'present'));
    $all_total = array_sum(array_column($all_colleges, 'total'));
} elseif ($attendance_college && $attendance_date) {
    // Get all sections for the selected college
    $stmt = $db->prepare("SELECT s.id, s.name FROM sections s JOIN operation_managers om ON s.om_id = om.id WHERE om.college = ? ORDER BY s.name");
    $stmt->execute([$attendance_college]);
    $all_sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Get attendance for each section (LEFT JOIN to include sections with no attendance)
    $stmt = $db->prepare("SELECT s.id as section_id, s.name as section_name, a.present_count, a.total_count FROM sections s JOIN operation_managers om ON s.om_id = om.id LEFT JOIN attendance a ON a.section_id = s.id AND a.date = ? WHERE om.college = ? ORDER BY s.name");
    $stmt->execute([$attendance_date, $attendance_college]);
    $attendance_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Build a map of section_id to attendance
    $attendance_map = [];
    foreach ($attendance_raw as $row) {
        $attendance_map[$row['section_id']] = $row;
    }
    // Ensure every section is present in the final array
    $attendance_sections = [];
    foreach ($all_sections as $section) {
        $sid = $section['id'];
        if (isset($attendance_map[$sid])) {
            $attendance_sections[] = $attendance_map[$sid];
            $attendance_present += (int)($attendance_map[$sid]['present_count'] ?? 0);
            $attendance_total += (int)($attendance_map[$sid]['total_count'] ?? 0);
        } else {
            $attendance_sections[] = [
                'section_id' => $sid,
                'section_name' => $section['name'],
                'present_count' => 0,
                'total_count' => 0
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
    <title>Attendance Report - Smart Attendance System</title>
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
            max-width: 1100px;
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
                        <h2>Attendance Report</h2>
                        <a href="dashboard.php" class="btn btn-secondary btn-sm mb-3">&larr; Back to Dashboard</a>
                        <!-- Summary Cards -->
                        <?php
                        $overall_pct = $attendance_total > 0 ? round($attendance_present / $attendance_total * 100, 2) : 0;
                        $best_section = null; $best_val = -1;
                        $low_section = null; $low_val = 101;
                        foreach ($attendance_sections as $row) {
                            $pct = ($row['total_count'] ?? 0) > 0 ? ($row['present_count'] / $row['total_count']) * 100 : 0;
                            if ($pct > $best_val) { $best_val = $pct; $best_section = $row['section_name']; }
                            if ($pct < $low_val) { $low_val = $pct; $low_section = $row['section_name']; }
                        }
                        ?>
                        <div class="row mb-4">
                            <div class="col-md-2">
                                <div class="card text-bg-primary mb-3" data-bs-toggle="tooltip" title="Overall attendance percentage for the selected context.">
                                    <div class="card-body text-center">
                                        <h6 class="card-title">Overall Attendance</h6>
                                        <p class="card-text fs-4"><?php echo $overall_pct; ?>%</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="card text-bg-success mb-3" data-bs-toggle="tooltip" title="Total number of students present.">
                                    <div class="card-body text-center">
                                        <h6 class="card-title">Total Present</h6>
                                        <p class="card-text fs-4"><?php echo $attendance_present; ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="card text-bg-danger mb-3" data-bs-toggle="tooltip" title="Total number of students absent.">
                                    <div class="card-body text-center">
                                        <h6 class="card-title">Total Absent</h6>
                                        <p class="card-text fs-4"><?php echo $attendance_total - $attendance_present; ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card text-bg-info mb-3" data-bs-toggle="tooltip" title="Section with the highest attendance %.">
                                    <div class="card-body text-center">
                                        <h6 class="card-title">Best Section</h6>
                                        <p class="card-text fs-5"><?php echo $best_section ? htmlspecialchars($best_section) . ' (' . round($best_val, 2) . '%)' : 'N/A'; ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card text-bg-warning mb-3" data-bs-toggle="tooltip" title="Section with the lowest attendance %.">
                                    <div class="card-body text-center">
                                        <h6 class="card-title">Lowest Section</h6>
                                        <p class="card-text fs-5"><?php echo $low_section ? htmlspecialchars($low_section) . ' (' . round($low_val, 2) . '%)' : 'N/A'; ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <form class="row g-3 mb-4" method="get" id="attendance-filter-form">
                            <div class="col-md-4">
                                <label for="attendance_college" class="form-label">College</label>
                                <select name="attendance_college" id="attendance_college" class="form-select">
                                    <option value="">Select College</option>
                                    <?php foreach ($attendance_colleges as $c): ?>
                                        <option value="<?php echo htmlspecialchars($c['college']); ?>" <?php if ($attendance_college == $c['college']) echo 'selected'; ?>>
                                            <?php echo htmlspecialchars($c['college']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="attendance_date" class="form-label">Date</label>
                                <select name="attendance_date" id="attendance_date" class="form-select" <?php if (!$attendance_college) echo 'disabled'; ?>>
                                    <option value="">Select Date</option>
                                    <?php if ($attendance_college): ?>
                                        <?php foreach ($attendance_dates as $d): ?>
                                            <option value="<?php echo $d; ?>" <?php if ($attendance_date == $d) echo 'selected'; ?>><?php echo $d; ?></option>
                                        <?php endforeach; ?>
                                        <?php if (empty($attendance_dates)): ?>
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
                                            <div class="card-header">Attendance % by College (Today)</div>
                                            <div class="card-body">
                                                <canvas id="allCollegesBar"></canvas>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <script>
                                var allCollegesLabels = <?php echo json_encode(array_column($all_colleges, 'college')); ?>;
                                var allCollegesData = <?php echo json_encode(array_map(function($row) { return $row['total'] > 0 ? round($row['present']/$row['total']*100,2) : 0; }, $all_colleges)); ?>;
                                var ctxBar = document.getElementById('allCollegesBar').getContext('2d');
                                new Chart(ctxBar, {
                                    type: 'bar',
                                    data: {
                                        labels: allCollegesLabels,
                                        datasets: [{
                                            label: 'Attendance %',
                                            data: allCollegesData,
                                            backgroundColor: 'rgba(54, 162, 235, 0.7)'
                                        }]
                                    },
                                    options: {responsive: true, plugins: {legend: {display: false}}}
                                });
                                </script>
                            <?php else: ?>
                                <div class="alert alert-info">No attendance data for today.</div>
                            <?php endif; ?>
                        <?php elseif ($attendance_college && $attendance_date): ?>
                            <!-- Add pie chart below bar graph for present vs absent -->
                            <div class="row mb-4">
                                <div class="col-md-8 mb-3">
                                    <div class="card">
                                        <div class="card-header">Section-wise Attendance %</div>
                                        <div class="card-body">
                                            <canvas id="attendanceBar"></canvas>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="card">
                                        <div class="card-header">Present vs Absent</div>
                                        <div class="card-body">
                                            <canvas id="attendancePie"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <script>
                            var attendanceLabels = <?php echo json_encode(array_column($attendance_sections, 'section_name')); ?>;
                            var attendanceData = <?php echo json_encode(array_map(function($row) { return ($row['total_count'] ?? 0) > 0 ? round(($row['present_count'] / $row['total_count']) * 100, 2) : 0; }, $attendance_sections)); ?>;
                            var ctxBar = document.getElementById('attendanceBar').getContext('2d');
                            new Chart(ctxBar, {
                                type: 'bar',
                                data: {
                                    labels: attendanceLabels,
                                    datasets: [{
                                        label: 'Attendance %',
                                        data: attendanceData,
                                        backgroundColor: 'rgba(54, 162, 235, 0.7)'
                                    }]
                                },
                                options: {responsive: true, plugins: {legend: {display: false}}}
                            });
                            var present = <?php echo $attendance_present; ?>;
                            var absent = <?php echo $attendance_total - $attendance_present; ?>;
                            var ctxPie = document.getElementById('attendancePie').getContext('2d');
                            new Chart(ctxPie, {
                                type: 'pie',
                                data: {
                                    labels: ['Present', 'Absent'],
                                    datasets: [{
                                        data: [present, absent],
                                        backgroundColor: ['#36a2eb', '#ff6384']
                                    }]
                                },
                                options: {responsive: true}
                            });
                            </script>
                            <div class="card mt-4 mb-4">
                                <div class="card-header">Section Attendance Details</div>
                                <div class="card-body table-responsive">
                                    <table class="table table-bordered table-striped">
                                        <thead><tr><th>Section</th><th>Present</th><th>Total</th><th>Attendance %</th></tr></thead>
                                        <tbody>
                                        <?php foreach ($attendance_sections as $row): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($row['section_name']) ?></td>
                                                <td><?= (int)($row['present_count'] ?? 0) ?></td>
                                                <td><?= (int)($row['total_count'] ?? 0) ?></td>
                                                <td><?= ($row['total_count'] ?? 0) > 0 ? round(($row['present_count'] / $row['total_count']) * 100, 2) : 0 ?>%</td>
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
        $('#attendance_college').on('change', function() {
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
                    $('#attendance_date').html(options).prop('disabled', false);
                });
            } else {
                $('#attendance_date').html('<option value="">Select Date</option>').prop('disabled', true);
            }
        });
    });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
      return new bootstrap.Tooltip(tooltipTriggerEl)
    });
    </script>
</body>
</html> 