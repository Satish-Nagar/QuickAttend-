<?php
require_once '../includes/functions.php';
requireOM();
require_once '../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

header('Content-Type: application/json');

$action = $_POST['action'] ?? '';

function parseUploadedFile($file) {
    $rows = [];
    $headers = [];
    if ($file && $file['tmp_name']) {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext === 'csv') {
            $handle = fopen($file['tmp_name'], 'r');
            $headers = fgetcsv($handle);
            while (($row = fgetcsv($handle)) !== false) {
                $row_assoc = array_combine($headers, $row);
                $rows[] = $row_assoc;
            }
            fclose($handle);
        } elseif (in_array($ext, ['xls', 'xlsx'])) {
            $spreadsheet = IOFactory::load($file['tmp_name']);
            $sheet = $spreadsheet->getActiveSheet();
            $data = $sheet->toArray(null, true, true, true);
            $headers = array_map('trim', $data[1]);
            for ($i = 2; $i <= count($data); $i++) {
                $row = $data[$i];
                $row_assoc = array_combine($headers, $row);
                $rows[] = $row_assoc;
            }
        }
    }
    return [$headers, $rows];
}

if ($action === 'preview_file') {
    // Parse uploaded file
    list($headers, $rows) = parseUploadedFile($_FILES['scores_file'] ?? null);
    if (!$headers || !$rows) {
        echo json_encode(['success' => false, 'error' => 'Invalid or empty file.']);
        exit;
    }

    // Get section_id from POST or session/context
    $section_id = intval($_POST['section_id'] ?? 0);
    if (!$section_id) {
        echo json_encode(['success' => false, 'error' => 'Section not specified.']);
        exit;
    }

    // Fetch all roll numbers for this section
    $db = getDB();
    $students = $db->prepare("SELECT roll_no FROM students WHERE section_id = ?");
    $students->execute([$section_id]);
    $section_rolls = array_column($students->fetchAll(PDO::FETCH_ASSOC), 'roll_no');

    // Build a map from uploaded roll numbers to scores
    $uploaded_scores = [];
    foreach ($rows as $row) {
        $roll = trim($row['Roll No'] ?? $row['roll_no'] ?? $row['ROLL NO'] ?? '');
        $score = isset($row['Assignment Score']) ? $row['Assignment Score'] : (isset($row['score']) ? $row['score'] : null);
        if ($roll !== '' && $score !== null) {
            $uploaded_scores[$roll] = $score;
        }
    }

    // Build the output table
    $output = [];
    foreach ($section_rolls as $roll) {
        if (isset($uploaded_scores[$roll])) {
            $output[] = [
                'Roll No' => $roll,
                'Assignment Status' => 1,
                'Assignment Score' => $uploaded_scores[$roll]
            ];
        } else {
            $output[] = [
                'Roll No' => $roll,
                'Assignment Status' => 0,
                'Assignment Score' => 0
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'headers' => ['Roll No', 'Assignment Status', 'Assignment Score'],
        'rows' => $output
    ]);
    exit;
}

if ($action === 'export_file') {
    $headers = json_decode($_POST['headers'] ?? '[]', true);
    $rows = json_decode($_POST['rows'] ?? '[]', true);
    $subject = trim($_POST['subject_name'] ?? '');
    $faculty = trim($_POST['faculty_name'] ?? '');
    if (!$headers || !$rows) {
        echo json_encode(['success' => false, 'error' => 'Missing data for export.']);
        exit;
    }
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray([$subject, $faculty], null, 'A1');
    $sheet->fromArray($headers, null, 'A2');
    $i = 3;
    foreach ($rows as $row) {
        $row_data = [];
        foreach ($headers as $h) {
            $row_data[] = $row[$h];
        }
        $sheet->fromArray($row_data, null, 'A' . $i);
        $i++;
    }
    $filename = 'assignment_export_' . date('Ymd_His') . '.xlsx';
    $writer = new Xlsx($spreadsheet);
    ob_start();
    $writer->save('php://output');
    $excelData = ob_get_clean();
    $base64 = base64_encode($excelData);
    echo json_encode(['success' => true, 'file' => $base64, 'filename' => $filename]);
    exit;
}

if ($action === 'save_assignment') {
    $input = json_decode(file_get_contents('php://input'), true);
    $section_id = intval($input['section_id'] ?? 0);
    $data = $input['data'] ?? [];
    if (!$section_id || !is_array($data) || empty($data)) {
        echo json_encode(['success' => false, 'error' => 'Missing section or data.']);
        exit;
    }
    $db = getDB();
    $date = date('Y-m-d');
    try {
        foreach ($data as $row) {
            $roll_no = $row['roll_no'];
            $status = intval($row['status']);
            $score = intval($row['score']);
            // Upsert logic: update if exists, else insert
            $stmt = $db->prepare("SELECT id FROM assignments WHERE section_id = ? AND roll_no = ? AND date = ?");
            $stmt->execute([$section_id, $roll_no, $date]);
            $exists = $stmt->fetchColumn();
            if ($exists) {
                $stmt = $db->prepare("UPDATE assignments SET status = ?, score = ? WHERE id = ?");
                $stmt->execute([$status, $score, $exists]);
            } else {
                $stmt = $db->prepare("INSERT INTO assignments (section_id, roll_no, status, score, date) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$section_id, $roll_no, $status, $score, $date]);
            }
        }
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'error' => 'Invalid action.']); 