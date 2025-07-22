<?php
require_once '../includes/functions.php';
requireAdmin();
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

// Assignment filters
$assignment_colleges = $attendance_colleges; // same list
$assignment_college = isset($_GET['assignment_college']) ? $_GET['assignment_college'] : '';
$assignment_date = isset($_GET['assignment_date']) ? $_GET['assignment_date'] : '';
$assignment_dates = [];
if ($assignment_college) {
    $stmt = $db->prepare("SELECT DISTINCT DATE(a.due_date) FROM assignments a JOIN sections s ON a.section_id = s.id JOIN operation_managers om ON s.om_id = om.id WHERE om.college = ? ORDER BY DATE(a.due_date) DESC");
    $stmt->execute([$assignment_college]);
    $assignment_dates = $stmt->fetchAll(PDO::FETCH_COLUMN);
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
            // present/total += 0
        }
    }
}

// Assignment Data
$assignment_sections = [];
$assignment_submitted = 0;
$assignment_total = 0;
if ($assignment_college && $assignment_date) {
    $stmt = $db->prepare("SELECT s.name AS section_name, AVG(sc.score) AS avg_score, SUM(sc.status) AS submitted, COUNT(sc.id) AS total FROM assignment_scores sc JOIN assignments a ON sc.assignment_id = a.id JOIN sections s ON sc.section_id = s.id JOIN operation_managers om ON s.om_id = om.id WHERE om.college = ? AND DATE(a.due_date) = ? GROUP BY s.name");
    $stmt->execute([$assignment_college, $assignment_date]);
    $assignment_sections = $stmt->fetchAll();
    foreach ($assignment_sections as $row) {
        $assignment_submitted += $row['submitted'];
        $assignment_total += $row['total'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Smart Attendance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <div class="container mt-5">
        <h2>Analytics Dashboard</h2>
        <a href="dashboard.php" class="btn btn-secondary btn-sm mb-3">&larr; Back to Dashboard</a>
        <!-- Tab Slicer -->
        <ul class="nav nav-tabs mb-4" id="reportTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="attendance-tab" data-bs-toggle="tab" data-bs-target="#attendance" type="button" role="tab">Attendance Report</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="assignment-tab" data-bs-toggle="tab" data-bs-target="#assignment" type="button" role="tab">Assignment Report</button>
            </li>
        </ul>
        <div class="tab-content" id="reportTabContent">
            <!-- Attendance Tab -->
            <div class="tab-pane fade show active" id="attendance" role="tabpanel">
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
                            <div class="col-md-8 mb-3">
                                <div class="card">
                                    <div class="card-header">Attendance % by College (Today)</div>
                                    <div class="card-body">
                                        <canvas id="allCollegesBar"></canvas>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="card">
                                    <div class="card-header">Present vs Absent (Today)</div>
                                    <div class="card-body">
                                        <canvas id="allCollegesPie"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row mt-4 mb-3 justify-content-center">
                            <div class="col-auto">
                                <button class="btn btn-outline-primary" disabled>
                                    Overall Attendance: <?php echo $all_total > 0 ? round($all_present / $all_total * 100, 2) : 0; ?>%
                                </button>
                            </div>
                            <div class="col-auto">
                                <button class="btn btn-outline-info" disabled>
                                    Total Present: <?php echo $all_present; ?>
                                </button>
                            </div>
                            <div class="col-auto">
                                <button class="btn btn-outline-secondary" disabled>
                                    Total Absent: <?php echo $all_total - $all_present; ?>
                                </button>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">No attendance data for today.</div>
                    <?php endif; ?>
                <?php elseif ($attendance_college && $attendance_date): ?>
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
                <!-- Insights Buttons for Attendance -->
                <div class="row mt-4 mb-3 justify-content-center">
                    <div class="col-auto">
                        <button class="btn btn-outline-primary" disabled>
                            Overall Attendance: <?php echo $attendance_total > 0 ? round($attendance_present / $attendance_total * 100, 2) : 0; ?>%
                        </button>
                    </div>
                    <div class="col-auto">
                        <button class="btn btn-outline-success" disabled>
                            Best Section: <?php 
                                $best = null; $best_val = -1;
                                foreach ($attendance_sections as $row) {
                                    $pct = $row['total_count'] > 0 ? $row['present_count'] / $row['total_count'] * 100 : 0;
                                    if ($pct > $best_val) { $best_val = $pct; $best = $row['section_name']; }
                                }
                                echo $best ? htmlspecialchars($best) . ' (' . round($best_val, 2) . '%)' : 'N/A';
                            ?>
                        </button>
                    </div>
                    <div class="col-auto">
                        <button class="btn btn-outline-danger" disabled>
                            Lowest Section: <?php 
                                $low = null; $low_val = 101;
                                foreach ($attendance_sections as $row) {
                                    $pct = $row['total_count'] > 0 ? $row['present_count'] / $row['total_count'] * 100 : 0;
                                    if ($pct < $low_val) { $low_val = $pct; $low = $row['section_name']; }
                                }
                                echo $low ? htmlspecialchars($low) . ' (' . round($low_val, 2) . '%)' : 'N/A';
                            ?>
                        </button>
                    </div>
                    <div class="col-auto">
                        <button class="btn btn-outline-info" disabled>
                            Total Present: <?php echo $attendance_present; ?>
                        </button>
                    </div>
                    <div class="col-auto">
                        <button class="btn btn-outline-secondary" disabled>
                            Total Absent: <?php echo $attendance_total - $attendance_present; ?>
                        </button>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <!-- Assignment Tab -->
            <div class="tab-pane fade" id="assignment" role="tabpanel">
                <form class="row g-3 mb-4" method="get" id="assignment-filter-form">
                    <div class="col-md-4">
                        <label for="assignment_college" class="form-label">College</label>
                        <select name="assignment_college" id="assignment_college" class="form-select" required>
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
                        <select name="assignment_date" id="assignment_date" class="form-select" required <?php if (!$assignment_college) echo 'disabled'; ?>>
                            <option value="">Select Date</option>
                            <?php foreach ($assignment_dates as $d): ?>
                                <option value="<?php echo $d; ?>" <?php if ($assignment_date == $d) echo 'selected'; ?>><?php echo $d; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 align-self-end">
                        <button type="submit" class="btn btn-primary w-100">Show Report</button>
                    </div>
                </form>
                <?php if ($assignment_college && $assignment_date): ?>
                <div class="row mb-4">
                    <div class="col-md-8 mb-3">
                        <div class="card">
                            <div class="card-header">Section-wise Avg. Assignment Score</div>
                            <div class="card-body">
                                <canvas id="assignmentBar"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card">
                            <div class="card-header">Submitted vs Not Submitted</div>
                            <div class="card-body">
                                <canvas id="assignmentPie"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Insights Buttons for Assignment -->
                <div class="row mt-4 mb-3 justify-content-center">
                    <div class="col-auto">
                        <button class="btn btn-outline-primary" disabled>
                            Overall Avg. Score: <?php echo $assignment_total > 0 ? round(array_sum(array_column($assignment_sections, 'avg_score')) / $assignment_total, 2) : 0; ?>
                        </button>
                    </div>
                    <div class="col-auto">
                        <button class="btn btn-outline-success" disabled>
                            Best Section: <?php 
                                $best = null; $best_val = -1;
                                foreach ($assignment_sections as $row) {
                                    $pct = $row['total'] > 0 ? $row['avg_score'] : 0;
                                    if ($pct > $best_val) { $best_val = $pct; $best = $row['section_name']; }
                                }
                                echo $best ? htmlspecialchars($best) . ' (' . round($best_val, 2) . ')' : 'N/A';
                            ?>
                        </button>
                    </div>
                    <div class="col-auto">
                        <button class="btn btn-outline-danger" disabled>
                            Lowest Section: <?php 
                                $low = null; $low_val = 101;
                                foreach ($assignment_sections as $row) {
                                    $pct = $row['total'] > 0 ? $row['avg_score'] : 0;
                                    if ($pct < $low_val) { $low_val = $pct; $low = $row['section_name']; }
                                }
                                echo $low ? htmlspecialchars($low) . ' (' . round($low_val, 2) . ')' : 'N/A';
                            ?>
                        </button>
                    </div>
                    <div class="col-auto">
                        <button class="btn btn-outline-info" disabled>
                            Total Submitted: <?php echo $assignment_submitted; ?>
                        </button>
                    </div>
                    <div class="col-auto">
                        <button class="btn btn-outline-secondary" disabled>
                            Total Not Submitted: <?php echo $assignment_total - $assignment_submitted; ?>
                        </button>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script>
    // AJAX to update date options based on selected college for attendance
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
        // AJAX for assignment tab
        $('#assignment_college').on('change', function() {
            var college = $(this).val();
            if (college) {
                $.get('get_dates.php', {college: college}, function(data) {
                    var options = '<option value="">Select Date</option>';
                    $.each(JSON.parse(data), function(i, d) {
                        options += '<option value="'+d+'">'+d+'</option>';
                    });
                    $('#assignment_date').html(options).prop('disabled', false);
                });
            } else {
                $('#assignment_date').html('<option value="">Select Date</option>').prop('disabled', true);
            }
        });
    });
    // Attendance Bar and Pie for all colleges (today)
    <?php if ($show_all_colleges_today && count($all_colleges) > 0): ?>
    var allCollegesLabels = <?php echo json_encode(array_column($all_colleges, 'college')); ?>;
    var allCollegesData = <?php echo json_encode(array_map(function($row) { return $row['total'] > 0 ? round($row['present']/$row['total']*100,2) : 0; }, $all_colleges)); ?>;
    var present = <?php echo $all_present; ?>;
    var absent = <?php echo $all_total - $all_present; ?>;
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
    var ctxPie = document.getElementById('allCollegesPie').getContext('2d');
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
    <?php endif; ?>
    // Attendance Bar and Pie
    <?php if ($attendance_college && $attendance_date): ?>
    <!-- Debug: PHP arrays for chart -->
    <?php
    $labels = array_column($attendance_sections, 'section_name');
    $data = array_map(function($row) { return ($row['total_count'] ?? 0) > 0 ? round(($row['present_count'] / $row['total_count']) * 100, 2) : 0; }, $attendance_sections);
    ?>
    <!-- attendanceLabels: <?php echo json_encode($labels); ?> -->
    <!-- attendanceData: <?php echo json_encode($data); ?> -->
    <script>
    var attendanceLabels = <?php echo json_encode($labels); ?>;
    var attendanceData = <?php echo json_encode($data); ?>;
    console.log('attendanceLabels:', attendanceLabels);
    console.log('attendanceData:', attendanceData);
    if (attendanceLabels.length === 0) {
        document.getElementById('attendanceBar').parentNode.innerHTML = '<div class="alert alert-info">No sections found for this college.</div>';
    } else {
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
    }
    var present = <?php echo $attendance_present; ?>;
    var absent = <?php echo $attendance_total - $attendance_present; ?>;
    if (present + absent === 0) {
        document.getElementById('attendancePie').parentNode.innerHTML = '<div class="alert alert-info">No attendance data for this date.</div>';
    } else {
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
    }
    </script>
    <?php endif; ?>
    <!-- Optional: Table for verification -->
    <?php if ($attendance_college && $attendance_date): ?>
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
    // Assignment Bar and Pie
    <?php if ($assignment_college && $assignment_date): ?>
    var assignmentLabels = <?php echo json_encode(array_column($assignment_sections, 'section_name')); ?>;
    var assignmentData = <?php echo json_encode(array_map(function($row) { return round($row['avg_score'],2); }, $assignment_sections)); ?>;
    var submitted = <?php echo $assignment_submitted; ?>;
    var not_submitted = <?php echo $assignment_total - $assignment_submitted; ?>;
    var ctxBar2 = document.getElementById('assignmentBar').getContext('2d');
    new Chart(ctxBar2, {
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
    var ctxPie2 = document.getElementById('assignmentPie').getContext('2d');
    new Chart(ctxPie2, {
        type: 'pie',
        data: {
            labels: ['Submitted', 'Not Submitted'],
            datasets: [{
                data: [submitted, not_submitted],
                backgroundColor: ['#36a2eb', '#ff6384']
            }]
        },
        options: {responsive: true}
    });
    <?php endif; ?>
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 