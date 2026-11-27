<?php
session_start();
require_once 'db.php';

// Include autoload
$autoload_path = __DIR__ . '/vendor/autoload.php';
if (!file_exists($autoload_path)) {
    die("ERROR: PhpSpreadsheet belum terinstall. Jalankan: composer require phpoffice/phpspreadsheet");
}

require $autoload_path;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

// ================= CEK LOGIN =================
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// ================= AMBIL DATA =================
$insiden_id = null;
$insiden    = [];
$tindakan   = [];

/* ===== MODE POST (DARI SUMMARY) ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tindakan_data'])) {
    $tindakan = json_decode($_POST['tindakan_data'], true);
    $insiden_post = json_decode($_POST['insiden_data'], true);

    if (!isset($insiden_post['id'])) {
        die("ID insiden tidak ditemukan di data POST");
    }
    $insiden_id = intval($insiden_post['id']);
}
/* ===== MODE GET (DARI DETAIL) ===== */
elseif (isset($_GET['id'])) {
    $insiden_id = intval($_GET['id']);
} else {
    die("Tidak ada data untuk diexport");
}

// Validasi ID
if (!$insiden_id || $insiden_id <= 0) {
    die("ID tidak valid: " . $insiden_id);
}

// ================= AMBIL DATA DARI DATABASE =================
// Ambil data insiden
$stmt = $mysqli->prepare("SELECT * FROM insiden WHERE id = ?");
$stmt->bind_param("i", $insiden_id);
$stmt->execute();
$result = $stmt->get_result();
$insiden = $result->fetch_assoc();
$stmt->close();

if (!$insiden) {
    die("Data insiden tidak ditemukan di database untuk ID: " . $insiden_id);
}

// ===== AMBIL DATA TINDAKAN (JIKA BELUM ADA DARI POST) =====
if (empty($tindakan)) {
    $tindakan = [];
    $q = $mysqli->prepare("SELECT * FROM tindakan_perbaikan WHERE insiden_id = ?");
    $q->bind_param("i", $insiden_id);
    $q->execute();
    $result = $q->get_result();
    while ($r = $result->fetch_assoc()) {
        $tindakan[] = $r;
    }
    $q->close();
}

// ================= BUAT SPREADSHEET =================
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Incident Tracking');

// ================= TAMBAH LOGO =================
$logo_path = __DIR__ . '/logo_pt.jpg';
if (file_exists($logo_path)) {
    try {
        $drawing = new Drawing();
        $drawing->setName('Logo');
        $drawing->setDescription('Logo');
        $drawing->setPath($logo_path);
        $drawing->setHeight(60);
        $drawing->setCoordinates('A1');
        $drawing->setWorksheet($sheet);
        
        // Merge cell untuk logo
        $sheet->mergeCells('A1:C4');
        
        // Set alignment untuk merged cells
        $sheet->getStyle('A1:C4')->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
            
    } catch (Exception $e) {
        // Jika error, skip logo
        error_log("Error loading logo: " . $e->getMessage());
    }
}

// ================= HEADER =================
$sheet->mergeCells('D1:J2');
$sheet->setCellValue('D1', 'INCIDENT / ACCIDENT TRACKING & FOLLOW-UP STATUS RECORD');

// Style untuk header utama
$sheet->getStyle('D1:J2')->applyFromArray([
    'font' => [
        'size' => 14,
        'bold' => true,
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER,
    ]
]);

$sheet->mergeCells('D3:J3');
$sheet->setCellValue('D3', 'PT RADIANT UTAMA INTERINSCO, TBK');

// Style untuk subtitle
$sheet->getStyle('D3:J3')->applyFromArray([
    'font' => [
        'size' => 12,
        'bold' => true,
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER,
    ]
]);

// ================= HEADER TABEL =================
$headers = [
    'No', 'Incident Number', 'Category', 'Date', 
    'Project', 'Branch', 'Recommendation', 
    'Corrective Action', 'PIC', 'Status'
];

$row = 6; // Mulai dari row 6

// Set header tabel
foreach ($headers as $colIndex => $header) {
    $colLetter = chr(65 + $colIndex); // A=65, B=66, dst
    $cellAddress = $colLetter . $row;
    
    $sheet->setCellValue($cellAddress, $header);
    
    // Style untuk header
    $sheet->getStyle($cellAddress)->applyFromArray([
        'font' => [
            'bold' => true,
            'color' => ['argb' => 'FF000000'],
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['argb' => 'FF9DC3E6'],
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER,
            'wrapText' => true,
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['argb' => 'FF000000'],
            ],
        ]
    ]);
}

// ================= ISI DATA =================
$row = 7;
$no = 1;

if (!empty($tindakan)) {
    foreach ($tindakan as $t) {
        // Set data untuk setiap kolom
        $sheet->setCellValue('A' . $row, $no++);
        $sheet->setCellValue('B' . $row, $insiden['insiden_number'] ?? '');
        $sheet->setCellValue('C' . $row, $insiden['insiden_type'] ?? '');
        $sheet->setCellValue('D' . $row, !empty($insiden['tanggal']) ? date('d/m/Y', strtotime($insiden['tanggal'])) : '');
        $sheet->setCellValue('E' . $row, $insiden['lokasi'] ?? '');
        $sheet->setCellValue('F' . $row, $insiden['departemen_insiden'] ?? '');
        $sheet->setCellValue('G' . $row, $t['rekomendasi'] ?? '');
        $sheet->setCellValue('H' . $row, $t['deskripsi'] ?? '');
        $sheet->setCellValue('I' . $row, $t['penanggungjawab'] ?? '');
        $sheet->setCellValue('J' . $row, $t['status'] ?? '');
        
        // Style untuk data
        $sheet->getStyle('A' . $row . ':J' . $row)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => 'FF000000'],
                ],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_TOP,
                'wrapText' => true,
            ]
        ]);
        
        // Center untuk kolom tertentu
        $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('D' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('J' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        // Set row height untuk menampung wrapped text
        $sheet->getRowDimension($row)->setRowHeight(-1);
        
        $row++;
    }
} else {
    // Jika tidak ada data tindakan
    $sheet->mergeCells('A' . $row . ':J' . $row);
    $sheet->setCellValue('A' . $row, 'Tidak ada data tindakan perbaikan');
    $sheet->getStyle('A' . $row)->applyFromArray([
        'font' => [
            'bold' => true,
            'italic' => true,
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER,
        ]
    ]);
    $row++;
}

// ================= SET LEBAR KOLOM =================
$sheet->getColumnDimension('A')->setWidth(5);    // No
$sheet->getColumnDimension('B')->setWidth(15);   // Incident Number
$sheet->getColumnDimension('C')->setWidth(12);   // Category
$sheet->getColumnDimension('D')->setWidth(10);   // Date
$sheet->getColumnDimension('E')->setWidth(20);   // Project
$sheet->getColumnDimension('F')->setWidth(15);   // Branch
$sheet->getColumnDimension('G')->setWidth(30);   // Recommendation
$sheet->getColumnDimension('H')->setWidth(30);   // Corrective Action
$sheet->getColumnDimension('I')->setWidth(15);   // PIC
$sheet->getColumnDimension('J')->setWidth(10);   // Status

// ================= FOOTER SIGNATURE =================
$row += 2; // Tambah 2 baris kosong

// Tanggal
$sheet->mergeCells('G' . $row . ':J' . $row);
$sheet->setCellValue('G' . $row, 'Jakarta, ' . date('d F Y'));
$sheet->getStyle('G' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$row++;

// "Prepared by,"
$sheet->mergeCells('G' . $row . ':J' . $row);
$sheet->setCellValue('G' . $row, 'Prepared by,');
$sheet->getStyle('G' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$row += 3; // Kosong 3 baris

// Garis tanda tangan
$sheet->mergeCells('G' . $row . ':J' . $row);
$sheet->setCellValue('G' . $row, '__________________________');
$sheet->getStyle('G' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$row++;

// "Investigator"
$sheet->mergeCells('G' . $row . ':J' . $row);
$sheet->setCellValue('G' . $row, 'Investigator');
$sheet->getStyle('G' . $row)->applyFromArray([
    'font' => [
        'bold' => true,
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
    ]
]);

// ================= ORIENTASI DAN MARGIN =================
$sheet->getPageSetup()
    ->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)
    ->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4);

$sheet->getPageMargins()
    ->setTop(0.5)
    ->setRight(0.5)
    ->setBottom(0.5)
    ->setLeft(0.5);

// ================= OUTPUT =================
$writer = new Xlsx($spreadsheet);

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="INCIDENT_TRACKING_' . date('Ymd_His') . '.xlsx"');
header('Cache-Control: max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$writer->save('php://output');
exit;