<?php
require 'vendor/autoload.php';
$inputFileType1 = 'Excel2007';
$inputFileName1 = 'uploads/sheet.xlsx';
$inputFileType2 = 'Excel2007';
$inputFileName2 = 'uploads/sheet1.xlsx';
$outputFileType = 'Excel2007';
$outputFileName = 'outputData.xlsx';

// Load the first workbook (an xlsx file)
$objPHPExcelReader1 = PHPExcel_IOFactory::createReader($inputFileType1);
$objPHPExcel1 = $objPHPExcelReader1->load($inputFileName1);

// Load the second workbook (an xls file)
$objPHPExcelReader2 = PHPExcel_IOFactory::createReader($inputFileType2);
$objPHPExcel2 = $objPHPExcelReader2->load($inputFileName2);

// Merge the second workbook into the first
var_dump($objPHPExcel2);
exit();
$objPHPExcel2->getActiveSheet()->setTitle('Sheet2');
$objPHPExcel1->addExternalSheet($objPHPExcel2->getActiveSheet());

// Save the merged workbook under a new name (could save under the original name)
// as an xls file
/*$objPHPExcelWriter = PHPExcel_IOFactory::createWriter($objPHPExcel1,$outputFileType);
$objPHPExcelWriter->save($outputFileName);*/