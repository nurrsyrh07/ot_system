<?php

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/vendor/autoload.php';

require_login();

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

// --------------------------------------------------
// Get parameters
// --------------------------------------------------

$year    = isset($_GET['y']) ? (int)$_GET['y'] : (int)date('Y');
$month   = isset($_GET['m']) ? (int)$_GET['m'] : (int)date('n');
$staffId = isset($_GET['staff_id']) ? (int)$_GET['staff_id'] : 0;

if ($month < 1 || $month > 12) {
    die('Invalid month.');
}

if ($staffId <= 0) {
    die('Please select a staff member.');
}

// --------------------------------------------------
// Month
// --------------------------------------------------

$firstOfMonth = mktime(0, 0, 0, $month, 1, $year);

$monthStart = date('Y-m-01', $firstOfMonth);
$monthEnd   = date('Y-m-t', $firstOfMonth);

$monthName = date('F Y', $firstOfMonth);

// --------------------------------------------------
// Get staff
// --------------------------------------------------

$staffStmt = $pdo->prepare(
    'SELECT id, name, staff_no
     FROM users
     WHERE id = :id
       AND role = "staff"
     LIMIT 1'
);

$staffStmt->execute([
    ':id' => $staffId
]);

$staff = $staffStmt->fetch();

if (!$staff) {
    die('Staff member not found.');
}

// --------------------------------------------------
// Get approved OT
// --------------------------------------------------

$stmt = $pdo->prepare(
    'SELECT
        r.ot_date,
        r.start_time,
        r.end_time,
        r.total_hours,
        r.reason
     FROM ot_requests r
     WHERE r.staff_id = :staff_id
       AND r.status = "approved"
       AND r.ot_date BETWEEN :start AND :end
     ORDER BY r.ot_date ASC, r.start_time ASC'
);

$stmt->execute([
    ':staff_id' => $staffId,
    ':start'    => $monthStart,
    ':end'      => $monthEnd
]);

$rows = $stmt->fetchAll();

// --------------------------------------------------
// Create Excel
// --------------------------------------------------

$spreadsheet = new Spreadsheet();

$sheet = $spreadsheet->getActiveSheet();

$sheet->setTitle('Monthly OT');

// --------------------------------------------------
// Title
// --------------------------------------------------

$sheet->mergeCells('A1:F1');

$sheet->setCellValue(
    'A1',
    'MONTHLY OVERTIME REPORT - ' . strtoupper($monthName)
);

$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);

$sheet->getStyle('A1')->getAlignment()
    ->setHorizontal(Alignment::HORIZONTAL_CENTER);

// --------------------------------------------------
// Staff information
// --------------------------------------------------

$sheet->setCellValue('A3', 'Staff Name');
$sheet->setCellValue('B3', $staff['name']);

$sheet->setCellValue('A4', 'Staff No');
$sheet->setCellValue('B4', $staff['staff_no']);

$sheet->setCellValue('A5', 'Month');
$sheet->setCellValue('B5', $monthName);

$sheet->getStyle('A3:A5')->getFont()->setBold(true);

// --------------------------------------------------
// Table header
// --------------------------------------------------

$headerRow = 7;

$headers = [
    'No.',
    'Date',
    'Works Day',
    'OT Time',
    'Remarks',
    'OT Hour'
];

foreach ($headers as $column => $header) {
    $sheet->setCellValue(
        chr(65 + $column) . $headerRow,
        $header
    );
}

$sheet->getStyle("A{$headerRow}:F{$headerRow}")
    ->getFont()
    ->setBold(true);

$sheet->getStyle("A{$headerRow}:F{$headerRow}")
    ->getAlignment()
    ->setHorizontal(Alignment::HORIZONTAL_CENTER);

// --------------------------------------------------
// Data
// --------------------------------------------------

$rowNumber = $headerRow + 1;
$monthlyTotal = 0;
$counter = 1;

foreach ($rows as $row) {

    $monthlyTotal += (float)$row['total_hours'];

    $sheet->setCellValue(
        "A{$rowNumber}",
        $counter
    );

    $sheet->setCellValue(
        "B{$rowNumber}",
        date('d/M/y', strtotime($row['ot_date']))
    );

    $sheet->setCellValue(
        "C{$rowNumber}",
        date('l', strtotime($row['ot_date']))
    );

    $sheet->setCellValue(
        "D{$rowNumber}",
        substr($row['start_time'], 0, 5)
        . ' - ' .
        substr($row['end_time'], 0, 5)
    );

    $sheet->setCellValue(
        "E{$rowNumber}",
        $row['reason']
    );

    $sheet->setCellValue(
        "F{$rowNumber}",
        (float)$row['total_hours']
    );

    $rowNumber++;
    $counter++;
}

// --------------------------------------------------
// Total
// --------------------------------------------------

$sheet->setCellValue(
    "E{$rowNumber}",
    'TOTAL OT HOURS'
);

$sheet->setCellValue(
    "F{$rowNumber}",
    $monthlyTotal
);

$sheet->getStyle("E{$rowNumber}:F{$rowNumber}")
    ->getFont()
    ->setBold(true);

// --------------------------------------------------
// Borders
// --------------------------------------------------

$lastRow = $rowNumber;

$sheet->getStyle("A{$headerRow}:F{$lastRow}")
    ->getBorders()
    ->getAllBorders()
    ->setBorderStyle(Border::BORDER_THIN);

// --------------------------------------------------
// Alignment
// --------------------------------------------------

$sheet->getStyle("A{$headerRow}:D{$lastRow}")
    ->getAlignment()
    ->setVertical(Alignment::VERTICAL_TOP);

$sheet->getStyle("A{$headerRow}:D{$lastRow}")
    ->getAlignment()
    ->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet->getStyle("F{$headerRow}:F{$lastRow}")
    ->getAlignment()
    ->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet->getStyle("E{$headerRow}:E{$lastRow}")
    ->getAlignment()
    ->setWrapText(true);

// --------------------------------------------------
// Column widths
// --------------------------------------------------

$sheet->getColumnDimension('A')->setWidth(8);
$sheet->getColumnDimension('B')->setWidth(15);
$sheet->getColumnDimension('C')->setWidth(15);
$sheet->getColumnDimension('D')->setWidth(20);
$sheet->getColumnDimension('E')->setWidth(45);
$sheet->getColumnDimension('F')->setWidth(12);

// --------------------------------------------------
// Download
// --------------------------------------------------

$filename =
    'Monthly_OT_' .
    preg_replace('/[^A-Za-z0-9_-]/', '_', $staff['name']) .
    '_' .
    date('Ym', $firstOfMonth) .
    '.xlsx';

header(
    'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
);

header(
    'Content-Disposition: attachment; filename="' . $filename . '"'
);

header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);

$writer->save('php://output');

exit;