<?php
require '../vendor/autoload.php';

$excel2 = PHPExcel_IOFactory::createReader('Excel2007');
$excel2 = $excel2->load('dentkey.xlsx');
$excel2->setLoadAllSheets();
$excel2->setActiveSheetIndex(0);
$excel2->getActiveSheet()->setCellValue('C6', '4')           

->setCellValue('C7', '5')         

  ->setCellValue('C8', '6')       

    ->setCellValue('C9', '7');
$excel2->setActiveSheetIndex(1);
$excel2->getActiveSheet()->setCellValue('A7', '4')

->setCellValue('C7', '5');


$objWriter = PHPExcel_IOFactory::createWriter($excel2, 'Excel2007');
$objWriter->save('dentkey1.xlsx');
