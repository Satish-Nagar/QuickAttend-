<?php
require_once '../includes/functions.php';
requireOM();
require_once '../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$assignment_id = intval($_GET['assignment_id'] ?? 0);
$section_id = intval($_GET['section_id'] ?? 0);
if (!$assignment_id || !$section_id) die('Missing params');

$db = getDB();
$stmt = $db->prepare("SELECT roll_no, status, score FROM assignment_scores WHERE assignment_id = ? AND section_id = ?");
$stmt->execute([$assignment_id, $section_id]);
$rows = $stmt->fetchAll();

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->fromArray(['Roll No', 'Assignment Status', 'Assignment Score'], null, 'A1');
$i = 2;
foreach ($rows as $row) {
    $sheet->setCellValue("A$i", $row['roll_no']);
    $sheet->setCellValue("B$i", $row['status']);
    $sheet->setCellValue("C$i", $row['score']);
    $i++;
}
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="assignment_scores.xlsx"');
header('Cache-Control: max-age=0');
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit; 