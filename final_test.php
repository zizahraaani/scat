<?php
require __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;

if (ob_get_length()) ob_end_clean();

$phpWord = new PhpWord();
$section = $phpWord->addSection();
$section->addText("INI TES TERAKHIR");

header("Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document");
header("Content-Disposition: attachment; filename=FINAL_TEST.docx");
header("Cache-Control: max-age=0");

$writer = IOFactory::createWriter($phpWord, 'Word2007');
$writer->save("php://output");
exit;
