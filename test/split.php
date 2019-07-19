<?php
require '../vendor/autoload.php';

$fileType = 'Excel2007';
$inputFileName = 'baocao.xlsx';

$objPHPExcelReader = PHPExcel_IOFactory::createReader($fileType);
$objPHPExcel = $objPHPExcelReader->load($inputFileName);

$sheetIndex = 0;
$sheetCount = $objPHPExcel->getSheetCount();
while ($sheetIndex < $sheetCount) {
    ++$sheetIndex;
    $workSheet = $objPHPExcel->getSheet(0);

    $newObjPHPExcel = new PHPExcel();
    $newObjPHPExcel->removeSheetByIndex(0);
    $newObjPHPExcel->addExternalSheet($workSheet);

    $objPHPExcelWriter = PHPExcel_IOFactory::createWriter($newObjPHPExcel,$fileType);
    $outputFileTemp = explode('.',$inputFileName);
    $outputFileName = $outputFileTemp[0].$sheetIndex.'.'.$outputFileTemp[1];
    $objPHPExcelWriter->save($outputFileName);
}
