<?php
require_once '../includes/functions.php';
requireAdmin();
$db = getDB();
$college = $_GET['college'] ?? '';
$dates = [];
if ($college) {
    $stmt = $db->prepare("SELECT DISTINCT a.date FROM attendance a JOIN sections s ON a.section_id = s.id JOIN operation_managers om ON s.om_id = om.id WHERE om.college = ? ORDER BY a.date DESC");
    $stmt->execute([$college]);
    $dates = $stmt->fetchAll(PDO::FETCH_COLUMN);
}
echo json_encode($dates); 