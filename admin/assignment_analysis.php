<?php
require_once '../includes/functions.php';
requireAdmin();
require_once '../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$db = getDB();
// Filters
$section_id = intval($_GET['section_id'] ?? 0);
$assignment_id = intval($_GET['assignment_id'] ?? 0);
$date = $_GET['date'] ?? '';

// Fetch sections
$sections = $db->query("SELECT id, name FROM sections ORDER BY name")->fetchAll();
// Fetch assignments for filter
$assignments = $section_id ? $db->prepare("SELECT id, title, due_date FROM assignments WHERE section_id = ? ORDER BY due_date DESC") : null;
if ($assignments) { $assignments->execute([$section_id]); $assignments = $assignments->fetchAll(); }
else $assignments = [];

// Build query
$query = "SELECT a.*, s.name AS section_name, ass.title FROM assignment_scores a JOIN sections s ON a.section_id = s.id JOIN assignments ass ON a.assignment_id = ass.id WHERE 1";
$params = [];
if ($section_id) { $query .= " AND a.section_id = ?"; $params[] = $section_id; }
if ($assignment_id) { $query .= " AND a.assignment_id = ?"; $params[] = $assignment_id; }
if ($date) { $query .= " AND DATE(ass.due_date) = ?"; $params[] = $date; }
$query .= " ORDER BY ass.due_date DESC, a.roll_no";
$stmt = $db->prepare($query);
$stmt->execute($params);
$records = $stmt->fetchAll();

// Stats
$total = count($records);
$submitted = array_sum(array_column($records, 'status'));
$avg = $total ? round(array_sum(array_column($records, 'score')) / $total, 2) : 0;
$high = $records ? max(array_column($records, 'score')) : 0;
$low = $records ? min(array_column($records, 'score')) : 0;
$trend = [];
foreach ($records as $r) {
    $trend[$r['title']][] = $r['score'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assignment Analysis - Smart Attendance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<div class="container mt-5">
    <h2>Assignment Analysis</h2>
    <a href="dashboard.php" class="btn btn-secondary btn-sm mb-3">&larr; Back to Dashboard</a>
    <form class="row g-3 mb-4" method="get">
        <div class="col-md-3">
            <label class="form-label">Section</label>
            <select name="section_id" class="form-select" onchange="this.form.submit()">
                <option value="0">All Sections</option>
                <?php foreach ($sections as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= $section_id == $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Assignment</label>
            <select name="assignment_id" class="form-select">
                <option value="0">All Assignments</option>
                <?php foreach ($assignments as $a): ?>
                    <option value="<?= $a['id'] ?>" <?= $assignment_id == $a['id'] ? 'selected' : '' ?>><?= htmlspecialchars($a['title']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Due Date</label>
            <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($date) ?>">
        </div>
        <div class="col-md-2 align-self-end">
            <button type="submit" class="btn btn-primary">Filter</button>
        </div>
        <div class="col-md-1 align-self-end">
            <a href="export_assignment.php?assignment_id=<?= $assignment_id ?>&section_id=<?= $section_id ?>" class="btn btn-success">Export</a>
        </div>
    </form>
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-bg-primary mb-3"><div class="card-body"><h5 class="card-title">Total Records</h5><p class="card-text fs-4"><?= $total ?></p></div></div>
        </div>
        <div class="col-md-3">
            <div class="card text-bg-success mb-3"><div class="card-body"><h5 class="card-title">% Submitted</h5><p class="card-text fs-4"><?= $total ? round($submitted/$total*100,2) : 0 ?>%</p></div></div>
        </div>
        <div class="col-md-2">
            <div class="card text-bg-info mb-3"><div class="card-body"><h5 class="card-title">Average</h5><p class="card-text fs-4"><?= $avg ?></p></div></div>
        </div>
        <div class="col-md-2">
            <div class="card text-bg-warning mb-3"><div class="card-body"><h5 class="card-title">High</h5><p class="card-text fs-4"><?= $high ?></p></div></div>
        </div>
        <div class="col-md-2">
            <div class="card text-bg-danger mb-3"><div class="card-body"><h5 class="card-title">Low</h5><p class="card-text fs-4"><?= $low ?></p></div></div>
        </div>
    </div>
    <div class="card mb-4">
        <div class="card-header">Assignment Trend</div>
        <div class="card-body">
            <canvas id="trendChart"></canvas>
        </div>
    </div>
    <div class="card">
        <div class="card-header">Assignment Records</div>
        <div class="card-body table-responsive">
            <table class="table table-bordered table-striped">
                <thead><tr><th>Roll No</th><th>Section</th><th>Assignment</th><th>Status</th><th>Score</th></tr></thead>
                <tbody>
                <?php foreach ($records as $r): ?>
                    <tr>
                        <td><?= htmlspecialchars($r['roll_no']) ?></td>
                        <td><?= htmlspecialchars($r['section_name']) ?></td>
                        <td><?= htmlspecialchars($r['title']) ?></td>
                        <td><?= $r['status'] ? 'Submitted' : 'Not Submitted' ?></td>
                        <td><?= $r['score'] ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script>
const ctx = document.getElementById('trendChart').getContext('2d');
const trendData = <?php echo json_encode(array_map('array_sum', $trend)); ?>;
const trendLabels = <?php echo json_encode(array_keys($trend)); ?>;
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: trendLabels,
        datasets: [{
            label: 'Total Score',
            data: Object.values(trendData),
            backgroundColor: 'rgba(54, 162, 235, 0.7)'
        }]
    },
    options: {responsive: true, plugins: {legend: {display: false}}}
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 