<?php
require '../vendor/autoload.php';

$excel2 = PHPExcel_IOFactory::createReader('Excel2007');
$excel2 = $excel2->load('sheet_test.xlsx');
//$excel2->setLoadAllSheets();
$excel2->setActiveSheetIndex(0);
var_dump($excel2->getActiveSheet());
$excel2->getActiveSheet()->setCellValue('A1', 'Trí dep trai 20cm');

$objWriter = PHPExcel_IOFactory::createWriter($excel2, 'Excel2007');
$objWriter->save('sheet_test.xlsx');
