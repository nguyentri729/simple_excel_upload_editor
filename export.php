<?php 


class XLSXWriterBuffererWriter
{
    protected $fd=null;
    protected $buffer='';
    protected $check_utf8=false;
    
    public function __construct($filename, $fd_fopen_flags='w', $check_utf8=false)
    {
        $this->check_utf8 = $check_utf8;
        $this->fd = fopen($filename, $fd_fopen_flags);
        if ($this->fd===false) {
            XLSXWriter::log("Unable to open $filename for writing.");
        }
    }
    
    public function write($string)
    {
        $this->buffer.=$string;
        if (isset($this->buffer[8191])) {
            $this->purge();
        }
    }
    
    protected function purge()
    {
        if ($this->fd) {
            if ($this->check_utf8 && !self::isValidUTF8($this->buffer)) {
                XLSXWriter::log("Error, invalid UTF8 encoding detected.");
                $this->check_utf8 = false;
            }
            fwrite($this->fd, $this->buffer);
            $this->buffer='';
        }
    }
    
    public function close()
    {
        $this->purge();
        if ($this->fd) {
            fclose($this->fd);
            $this->fd=null;
        }
    }
    
    public function __destruct()
    {
        $this->close();
    }
    
    public function ftell()
    {
        if ($this->fd) {
            $this->purge();
            return ftell($this->fd);
        }
        return -1;
    }
    
    public function fseek($pos)
    {
        if ($this->fd) {
            $this->purge();
            return fseek($this->fd, $pos);
        }
        return -1;
    }
    
    protected static function isValidUTF8($string)
    {
        if (function_exists('mb_check_encoding'))
        {
            return mb_check_encoding($string, 'UTF-8') ? true : false;
        }
        return preg_match("//u", $string) ? true : false;
    }
}
class XLSXWriter
{
	//http://www.ecma-international.org/publications/standards/Ecma-376.htm
	//http://officeopenxml.com/SSstyles.php
	//------------------------------------------------------------------
	//http://office.microsoft.com/en-us/excel-help/excel-specifications-and-limits-HP010073849.aspx
	const EXCEL_2007_MAX_ROW=1048576;
	const EXCEL_2007_MAX_COL=16384;
	//------------------------------------------------------------------
	protected $title;
	protected $subject;
	protected $author;
	protected $company;
	protected $description;
	protected $keywords = [];
	
	protected $currentSheet;
	protected $sheets = [];
	protected $tempFiles = [];
	protected $cellStyles = [];
	protected $numberFormats = [];

	public function __construct()
	{
		if(!ini_get('date.timezone'))
		{
			//using date functions can kick out warning if this isn't set
			date_default_timezone_set('UTC');
		}
		$this->addCellStyle('GENERAL', null);
		$this->addCellStyle('GENERAL', null);
		$this->addCellStyle('GENERAL', null);
		$this->addCellStyle('GENERAL', null);
	}

	public function setTitle($title = '') 
	{ 
	    $this->title = $title; 
	}
	
	public function setSubject($subject = '') 
	{ 
	    $this->subject = $subject; 
	}
	
	public function setAuthor($author = '') 
	{ 
	    $this->author = $author; 
	}
	
	public function setCompany($company = '') 
	{ 
	    $this->company = $company; 
	}
	
	public function setKeywords($keywords = '') 
	{ 
	    $this->keywords = $keywords; 
	}
	
	public function setDescription($description = '') 
	{ 
	    $this->description = $description; 
	}
	
	public function setTempDir($tempdir = '') 
	{ 
	    $this->tempdir = $tempdir; 
	}
	
	public function __destruct()
	{
		if (!empty($this->tempFiles)) {
			foreach($this->tempFiles as $tempFile) {
				@unlink($tempFile);
			}
		}
	}

	protected function tempFilename()
	{
		$tempdir = !empty($this->tempdir) ? $this->tempdir : sys_get_temp_dir();
		$filename = tempnam($tempdir, "xlsx_writer_");
		$this->tempFiles[] = $filename;
		return $filename;
	}

	public function writeToStdOut()
	{
		$tempFile = $this->tempFilename();
		self::writeToFile($tempFile);
		readfile($tempFile);
	}

	public function writeToString()
	{
		$tempFile = $this->tempFilename();
		self::writeToFile($tempFile);
		$string = file_get_contents($tempFile);
		return $string;
	}

	/**
	 * @param string $filename
	 */
	public function writeToFile($filename)
	{
		foreach($this->sheets as $sheetName => $sheet) {
			self::finalizeSheet($sheetName);//making sure all footers have been written
		}

		if ( file_exists( $filename ) ) {
			if ( is_writable( $filename ) ) {
				@unlink( $filename ); //if the zip already exists, remove it
			} else {
				self::log( "Error in " . __CLASS__ . "::" . __FUNCTION__ . ", file is not writeable." );
				return;
			}
		}
		$zip = new \ZipArchive();
		if (empty($this->sheets)) { 
		    self::log("Error in ".__CLASS__."::".__FUNCTION__.", no worksheets defined."); 
		    return; 
		}
		if (!$zip->open($filename, \ZipArchive::CREATE)) {
		    self::log("Error in ".__CLASS__."::".__FUNCTION__.", unable to create zip."); 
		    return; 
		}

		$zip->addEmptyDir("docProps/");
		$zip->addFromString("docProps/app.xml" , self::buildAppXML() );
		$zip->addFromString("docProps/core.xml", self::buildCoreXML());

		$zip->addEmptyDir("_rels/");
		$zip->addFromString("_rels/.rels", self::buildRelationshipsXML());

		$zip->addEmptyDir("xl/worksheets/");
		foreach($this->sheets as $sheet) {
			$zip->addFile($sheet->filename, "xl/worksheets/".$sheet->xmlname );
		}
		$zip->addFromString("xl/workbook.xml"         , self::buildWorkbookXML() );
		$zip->addFile($this->writeStylesXML(), "xl/styles.xml" );  //$zip->addFromString("xl/styles.xml"           , self::buildStylesXML() );
		$zip->addFromString("[Content_Types].xml"     , self::buildContentTypesXML() );

		$zip->addEmptyDir("xl/_rels/");
		$zip->addFromString("xl/_rels/workbook.xml.rels", self::buildWorkbookRelsXML() );
		$zip->close();
	}

	protected function initializeSheet($sheetName, $colWidths = [], $autoFilter = false, $freezeRows = false, $freezeColumns = false )
	{
		//if already initialized
		if ($this->currentSheet == $sheetName || isset($this->sheets[$sheetName]))
			return;

		$sheetFileName = $this->tempFilename();
		$sheetXmlName= 'sheet' . (count($this->sheets) + 1).".xml";
		$this->sheets[$sheetName] = (object) [
			'filename' => $sheetFileName,
			'sheetname' => $sheetName,
		    'xmlname' => $sheetXmlName,
			'rowCount' => 0,
			'fileWriter' => new XLSXWriterBuffererWriter($sheetFileName),
			'columns' => [],
			'mergeCells' => [],
			'maxCellTagStart' => 0,
			'maxCellTagEnd' => 0,
			'autoFilter' => $autoFilter,
			'freezeRows' => $freezeRows,
			'freezeColumns' => $freezeColumns,
			'finalized' => false,
		];
		$sheet = &$this->sheets[$sheetName];
		$tabselected = count($this->sheets) == 1 ? 'true' : 'false';//only first sheet is selected
		$maxCell = XLSXWriter::xlsCell(self::EXCEL_2007_MAX_ROW, self::EXCEL_2007_MAX_COL);//XFE1048577
		$sheet->fileWriter->write('<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n");
		$sheet->fileWriter->write('<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">');
		$sheet->fileWriter->write(  '<sheetPr filterMode="false">');
		$sheet->fileWriter->write(    '<pageSetUpPr fitToPage="false"/>');
		$sheet->fileWriter->write(  '</sheetPr>');
		$sheet->maxCellTagStart = $sheet->fileWriter->ftell();
		$sheet->fileWriter->write('<dimension ref="A1:' . $maxCell . '"/>');
		$sheet->maxCellTagEnd = $sheet->fileWriter->ftell();
		$sheet->fileWriter->write(  '<sheetViews>');
		$sheet->fileWriter->write(    '<sheetView colorId="64" defaultGridColor="true" rightToLeft="false" showFormulas="false" showGridLines="true" showOutlineSymbols="true" showRowColHeaders="true" showZeros="true" tabSelected="' . $tabselected . '" topLeftCell="A1" view="normal" windowProtection="false" workbookViewId="0" zoomScale="100" zoomScaleNormal="100" zoomScalePageLayoutView="100">');
		if ($sheet->freezeRows && $sheet->freezeColumns) {
			$sheet->fileWriter->write(      '<pane ySplit="'.$sheet->freezeRows.'" xSplit="'.$sheet->freezeColumns.'" topLeftCell="'.self::xlsCell($sheet->freezeRows, $sheet->freezeColumns).'" activePane="bottomRight" state="frozen"/>');
			$sheet->fileWriter->write(      '<selection activeCell="'.self::xlsCell($sheet->freezeRows, 0).'" activeCellId="0" pane="topRight" sqref="'.self::xlsCell($sheet->freezeRows, 0).'"/>');
			$sheet->fileWriter->write(      '<selection activeCell="'.self::xlsCell(0, $sheet->freezeColumns).'" activeCellId="0" pane="bottomLeft" sqref="'.self::xlsCell(0, $sheet->freezeColumns).'"/>');
			$sheet->fileWriter->write(      '<selection activeCell="'.self::xlsCell($sheet->freezeRows, $sheet->freezeColumns).'" activeCellId="0" pane="bottomRight" sqref="'.self::xlsCell($sheet->freezeRows, $sheet->freezeColumns).'"/>');
		}
		elseif ($sheet->freezeRows) {
			$sheet->fileWriter->write(      '<pane ySplit="'.$sheet->freezeRows.'" topLeftCell="'.self::xlsCell($sheet->freezeRows, 0).'" activePane="bottomLeft" state="frozen"/>');
			$sheet->fileWriter->write(      '<selection activeCell="'.self::xlsCell($sheet->freezeRows, 0).'" activeCellId="0" pane="bottomLeft" sqref="'.self::xlsCell($sheet->freezeRows, 0).'"/>');
		}
		elseif ($sheet->freezeColumns) {
			$sheet->fileWriter->write(      '<pane xSplit="'.$sheet->freezeColumns.'" topLeftCell="'.self::xlsCell(0, $sheet->freezeColumns).'" activePane="topRight" state="frozen"/>');
			$sheet->fileWriter->write(      '<selection activeCell="'.self::xlsCell(0, $sheet->freezeColumns).'" activeCellId="0" pane="topRight" sqref="'.self::xlsCell(0, $sheet->freezeColumns).'"/>');
		}
		else { // not frozen
			$sheet->fileWriter->write(      '<selection activeCell="A1" activeCellId="0" pane="topLeft" sqref="A1"/>');
		}
		$sheet->fileWriter->write(    '</sheetView>');
		$sheet->fileWriter->write(  '</sheetViews>');
		$sheet->fileWriter->write(  '<cols>');
		$i=0;
		if (!empty($colWidths)) {
			foreach($colWidths as $columnWidth) {
				$sheet->fileWriter->write(  '<col collapsed="false" hidden="false" max="'.($i+1).'" min="'.($i+1).'" style="0" customWidth="true" width="'.floatval($columnWidth).'"/>');
				$i++;
			}
		}
		$sheet->fileWriter->write(  '<col collapsed="false" hidden="false" max="1024" min="'.($i+1).'" style="0" customWidth="false" width="11.5"/>');
		$sheet->fileWriter->write(  '</cols>');
		$sheet->fileWriter->write(  '<sheetData>');
	}

	private function addCellStyle($numberFormat, $cellStyleString)
	{
		$numberFormatIdx = self::addToListGetIndex($this->numberFormats, $numberFormat);
		$lookupString = $numberFormatIdx.";".$cellStyleString;
		$cellStyleIdx = self::addToListGetIndex($this->cellStyles, $lookupString);
		return $cellStyleIdx;
	}

	private function initializeColumnTypes($headerTypes)
	{
		$columnTypes = [];
		foreach($headerTypes as $v)
		{
			$numberFormat = self::numberFormatStandardized($v);
			$numberFormatType = self::determineNumberFormatType($numberFormat);
			$cellStyleIdx = $this->addCellStyle($numberFormat, null);
			$columnTypes[] = [
			    'numberFormat' => $numberFormat,//contains excel format like 'YYYY-MM-DD HH:MM:SS'
				'numberFormatType' => $numberFormatType, //contains friendly format like 'datetime'
				'defaultCellStyle' => $cellStyleIdx,
			];
		}
		return $columnTypes;
	}

	public function writeSheetHeader($sheetName, array $headerTypes, $colOptions = null)
	{
	    if (empty($sheetName) || empty($headerTypes) || !empty($this->sheets[$sheetName])) {
			return;
	    }

		$suppressRow = isset($colOptions['suppress_row']) ? intval($colOptions['suppress_row']) : false;
		if (is_bool($colOptions)) {
			self::log( "Warning! passing $suppressRow=false|true to writeSheetHeader() is deprecated, this will be removed in a future version." );
			$suppressRow = intval($colOptions);
		}
        $style = &$colOptions;

		$colWidths = isset($colOptions['widths']) ? (array) $colOptions['widths'] : [];
		$autoFilter = isset($colOptions['autoFilter']) ? intval($colOptions['autoFilter']) : false;
		$freezeRows = isset($colOptions['freezeRows']) ? intval($colOptions['freezeRows']) : false;
		$freezeColumns = isset($colOptions['freezeColumns']) ? intval($colOptions['freezeColumns']) : false;
		self::initializeSheet($sheetName, $colWidths, $autoFilter, $freezeRows, $freezeColumns);
		$sheet = &$this->sheets[$sheetName];
		$sheet->columns = $this->initializeColumnTypes($headerTypes);
		if (!$suppressRow) {
			$headerRow = array_keys($headerTypes);      

			$sheet->fileWriter->write('<row collapsed="false" customFormat="false" customHeight="false" hidden="false" ht="12.1" outlineLevel="0" r="' . (1) . '">');
			foreach ($headerRow as $c => $v) {
				$cellStyleIdx = empty($style) ? $sheet->columns[$c]['defaultCellStyle'] : $this->addCellStyle( 'GENERAL', json_encode(isset($style[0]) ? $style[$c] : $style) );
				$this->writeCell($sheet->fileWriter, 0, $c, $v, 'n_string', $cellStyleIdx);
			}
			$sheet->fileWriter->write('</row>');
			$sheet->rowCount++;
		}
		$this->currentSheet = $sheetName;
	}

	public function writeSheetRow($sheetName, array $row, $rowOptions=null)
	{
		if (empty($sheetName))
			return;

		self::initializeSheet($sheetName);
		$sheet = &$this->sheets[$sheetName];
		if (count($sheet->columns) < count($row)) {
			$defaultColumnTypes = $this->initializeColumnTypes( array_fill(0, count($row), 'GENERAL') );//will map to n_auto
			$sheet->columns = array_merge((array) $sheet->columns, $defaultColumnTypes);
		}
		
		if (!empty($rowOptions))
		{
			$ht = isset($rowOptions['height']) ? floatval($rowOptions['height']) : 12.1;
			$customHt = isset($rowOptions['height']) ? true : false;
			$hidden = isset($rowOptions['hidden']) ? (bool)($rowOptions['hidden']) : false;
			$collapsed = isset($rowOptions['collapsed']) ? (bool)($rowOptions['collapsed']) : false;
			$sheet->fileWriter->write('<row collapsed="'.($collapsed).'" customFormat="false" customHeight="'.($customHt).'" hidden="'.($hidden).'" ht="'.($ht).'" outlineLevel="0" r="' . ($sheet->rowCount + 1) . '">');
		} else {
			$sheet->fileWriter->write('<row collapsed="false" customFormat="false" customHeight="false" hidden="false" ht="12.1" outlineLevel="0" r="' . ($sheet->rowCount + 1) . '">');
		}

		$style = &$rowOptions;
		$c=0;
		foreach ($row as $v) {
			$numberFormat = $sheet->columns[$c]['numberFormat'];
			$numberFormatType = $sheet->columns[$c]['numberFormatType'];
			$cellStyleIdx = empty($style) ? $sheet->columns[$c]['defaultCellStyle'] : $this->addCellStyle( $numberFormat, json_encode(isset($style[0]) ? $style[$c] : $style) );
			$this->writeCell($sheet->fileWriter, $sheet->rowCount, $c, $v, $numberFormatType, $cellStyleIdx);
			$c++;
		}
		$sheet->fileWriter->write('</row>');
		$sheet->rowCount++;
		$this->currentSheet = $sheetName;
	}

	public function countSheetRows($sheetName = '')
	{
		$sheetName = $sheetName ?: $this->currentSheet;
		return array_key_exists($sheetName, $this->sheets) ? $this->sheets[$sheetName]->rowCount : 0;
	}

	protected function finalizeSheet($sheetName)
	{
	    if (empty($sheetName) || $this->sheets[$sheetName]->finalized) {
			return;
	    }

		$sheet = &$this->sheets[$sheetName];

		$sheet->fileWriter->write(    '</sheetData>');

		if (!empty($sheet->mergeCells)) {
			$sheet->fileWriter->write(    '<mergeCells>');
			foreach ($sheet->mergeCells as $range) {
				$sheet->fileWriter->write(        '<mergeCell ref="' . $range . '"/>');
			}
			$sheet->fileWriter->write(    '</mergeCells>');
		}

		$maxCell = self::xlsCell($sheet->rowCount - 1, count($sheet->columns) - 1);

		if ($sheet->autoFilter) {
			$sheet->fileWriter->write(    '<autoFilter ref="A1:' . $maxCell . '"/>');			
		}

		$sheet->fileWriter->write(    '<printOptions headings="false" gridLines="false" gridLinesSet="true" horizontalCentered="false" verticalCentered="false"/>');
		$sheet->fileWriter->write(    '<pageMargins left="0.5" right="0.5" top="1.0" bottom="1.0" header="0.5" footer="0.5"/>');
		$sheet->fileWriter->write(    '<pageSetup blackAndWhite="false" cellComments="none" copies="1" draft="false" firstPageNumber="1" fitToHeight="1" fitToWidth="1" horizontalDpi="300" orientation="portrait" pageOrder="downThenOver" paperSize="1" scale="100" useFirstPageNumber="true" usePrinterDefaults="false" verticalDpi="300"/>');
		$sheet->fileWriter->write(    '<headerFooter differentFirst="false" differentOddEven="false">');
		$sheet->fileWriter->write(        '<oddHeader>&amp;C&amp;&quot;Times New Roman,Regular&quot;&amp;12&amp;A</oddHeader>');
		$sheet->fileWriter->write(        '<oddFooter>&amp;C&amp;&quot;Times New Roman,Regular&quot;&amp;12Page &amp;P</oddFooter>');
		$sheet->fileWriter->write(    '</headerFooter>');
		$sheet->fileWriter->write('</worksheet>');

		$max_cell_tag = '<dimension ref="A1:' . $maxCell . '"/>';
		$padding_length = $sheet->maxCellTagEnd - $sheet->maxCellTagStart - strlen($max_cell_tag);
		$sheet->fileWriter->fseek($sheet->maxCellTagStart);
		$sheet->fileWriter->write($max_cell_tag.str_repeat(" ", $padding_length));
		$sheet->fileWriter->close();
		$sheet->finalized = true;
	}

	public function markMergedCell($sheetName, $startCellRow, $startCellColumn, $endCellRow, $endCellColumn)
	{
		if (empty($sheetName) || $this->sheets[$sheetName]->finalized)
			return;

		self::initializeSheet($sheetName);
		$sheet = &$this->sheets[$sheetName];

		$startCell = self::xlsCell($startCellRow, $startCellColumn);
		$endCell = self::xlsCell($endCellRow, $endCellColumn);
		$sheet->mergeCells[] = $startCell . ":" . $endCell;
	}

	public function writeSheet(array $data, $sheetName='', array $headerTypes = [])
	{
		$sheetName = empty($sheetName) ? 'Sheet1' : $sheetName;
		$data = empty($data) ? [['']] : $data;
		if (!empty($headerTypes))
		{
			$this->writeSheetHeader($sheetName, $headerTypes);
		}
		foreach($data as $row)
		{
			$this->writeSheetRow($sheetName, $row);
		}
		$this->finalizeSheet($sheetName);
	}

	/**
	 * One cell output
	 * 
	 * @param XLSXWriterBuffererWriter $file
	 * @param integer $rowNumber
	 * @param integer $columnNumber
	 * @param mixed $value
	 * @param string $numFormatType
	 * @param integer $cellStyleIdx
	 */
	protected function writeCell(XLSXWriterBuffererWriter &$file, $rowNumber, $columnNumber, $value, $numFormatType, $cellStyleIdx)
	{
		$cellName = self::xlsCell($rowNumber, $columnNumber);

		if (is_object($value) && $value instanceof \Carbon\Carbon) {
		    $file->write('<c r="'.$cellName.'" s="'.$cellStyleIdx.'" t="n"><v>'.self::convertDateTime($value->format('Y-m-d H:i:s')).'</v></c>');
		} elseif (is_object($value) && $value instanceof \DateTime) {
		    $file->write('<c r="'.$cellName.'" s="'.$cellStyleIdx.'" t="n"><v>'.self::convertDateTime($value->format('Y-m-d H:i:s')).'</v></c>');
		} elseif (!is_scalar($value) || $value==='') { 
		    //objects, array, empty
	        $file->write('<c r="'.$cellName.'" s="'.$cellStyleIdx.'"/>');
	    } elseif (is_string($value) && $value{0}=='='){
			$file->write('<c r="'.$cellName.'" s="'.$cellStyleIdx.'" t="s"><f>'.self::xmlspecialchars($value).'</f></c>');
		} elseif ($numFormatType=='n_date') {
			$file->write('<c r="'.$cellName.'" s="'.$cellStyleIdx.'" t="n"><v>'.intval(self::convertDateTime($value)).'</v></c>');
		} elseif ($numFormatType=='n_datetime') {
			$file->write('<c r="'.$cellName.'" s="'.$cellStyleIdx.'" t="n"><v>'.self::convertDateTime($value).'</v></c>');
		} elseif ($numFormatType=='n_numeric') {
			$file->write('<c r="'.$cellName.'" s="'.$cellStyleIdx.'" t="n"><v>'.self::xmlspecialchars($value).'</v></c>');//int,float,currency
		} elseif ($numFormatType=='n_string') {
			$file->write('<c r="'.$cellName.'" s="'.$cellStyleIdx.'" t="inlineStr"><is><t>'.self::xmlspecialchars($value).'</t></is></c>');
		} elseif ($numFormatType=='n_auto' || 1) { //auto-detect unknown column types
			if (!is_string($value) || $value=='0' || ($value[0]!='0' && ctype_digit($value)) || preg_match("/^\-?(0|[1-9][0-9]*)(\.[0-9]+)?$/", $value)){
				$file->write('<c r="'.$cellName.'" s="'.$cellStyleIdx.'" t="n"><v>'.self::xmlspecialchars($value).'</v></c>');//int,float,currency
			} else { //implied: ($cell_format=='string')
				$file->write('<c r="'.$cellName.'" s="'.$cellStyleIdx.'" t="inlineStr"><is><t>'.self::xmlspecialchars($value).'</t></is></c>');
			}
		}
	}

	protected function styleFontIndexes()
	{
		static $borderAllowed = [
		    'left', 
		    'right', 
		    'top', 
    		'bottom'
		];
		static $borderStyleAllowed = [
		    'thin','medium','thick','dashDot',
		    'dashDotDot','dashed','dotted',
		    'double','hair','mediumDashDot',
		    'mediumDashDotDot','mediumDashed','slantDashDot'
		];
		static $horizontalAllowed = ['general','left','right','justify','center'];
		static $verticalAllowed = ['bottom','center','distributed','top'];
		$defaultFont = ['size'=>'10','name'=>'Arial','family'=>'2'];
		$fills = ['',''];//2 placeholders for static xml later
		$fonts = ['','','',''];//4 placeholders for static xml later
		$borders = [''];//1 placeholder for static xml later
		$styleIndexes = [];
		foreach($this->cellStyles as $i=>$cell_style_string)
		{
			$semiColonPos = strpos($cell_style_string,";");
			$numberFormatIdx = substr($cell_style_string, 0, $semiColonPos);
			$styleJsonString = substr($cell_style_string, $semiColonPos+1);
			$style = @json_decode($styleJsonString, true);

			$styleIndexes[$i] = [
			    'num_fmt_idx'=>$numberFormatIdx //initialize entry
			];
			if (isset($style['border']) && is_string($style['border'])) //border is a comma delimited str
			{
			    $borderValue = [];
				$borderValue['side'] = array_intersect(explode(",", $style['border']), $borderAllowed);
				if (isset($style['border-style']) && in_array($style['border-style'],$borderStyleAllowed))
				{
					$borderValue['style'] = $style['border-style'];
				}
				if (isset($style['border-color']) && is_string($style['border-color']) && $style['border-color'][0]=='#')
				{
					$v = substr($style['border-color'],1,6);
					$v = strlen($v)==3 ? $v[0].$v[0].$v[1].$v[1].$v[2].$v[2] : $v;// expand cf0 => ccff00
					$borderValue['color'] = "FF".strtoupper($v);
				}
				$styleIndexes[$i]['border_idx'] = self::addToListGetIndex($borders, json_encode($borderValue));
			}
			if (isset($style['fill']) && is_string($style['fill']) && $style['fill'][0]=='#')
			{
				$v = substr($style['fill'],1,6);
				$v = strlen($v)==3 ? $v[0].$v[0].$v[1].$v[1].$v[2].$v[2] : $v;// expand cf0 => ccff00
				$styleIndexes[$i]['fill_idx'] = self::addToListGetIndex($fills, "FF".strtoupper($v) );
			}
			if (isset($style['halign']) && in_array($style['halign'],$horizontalAllowed))
			{
				$styleIndexes[$i]['alignment'] = true;
				$styleIndexes[$i]['halign'] = $style['halign'];
			}
			if (isset($style['valign']) && in_array($style['valign'],$verticalAllowed))
			{
				$styleIndexes[$i]['alignment'] = true;
				$styleIndexes[$i]['valign'] = $style['valign'];
			}
			if (isset($style['wrap_text']))
			{
				$styleIndexes[$i]['alignment'] = true;
				$styleIndexes[$i]['wrap_text'] = (bool)$style['wrap_text'];
			}

			$font = $defaultFont;
			if (isset($style['font-size']))
			{
				$font['size'] = floatval($style['font-size']);//floatval to allow "10.5" etc
			}
			if (isset($style['font']) && is_string($style['font']))
			{
				if ($style['font']=='Comic Sans MS') { $font['family']=4; }
				if ($style['font']=='Times New Roman') { $font['family']=1; }
				if ($style['font']=='Courier New') { $font['family']=3; }
				$font['name'] = strval($style['font']);
			}
			if (isset($style['font-style']) && is_string($style['font-style']))
			{
				if (strpos($style['font-style'], 'bold')!==false) { $font['bold'] = true; }
				if (strpos($style['font-style'], 'italic')!==false) { $font['italic'] = true; }
				if (strpos($style['font-style'], 'strike')!==false) { $font['strike'] = true; }
				if (strpos($style['font-style'], 'underline')!==false) { $font['underline'] = true; }
			}
			if (isset($style['color']) && is_string($style['color']) && $style['color'][0]=='#' )
			{
				$v = substr($style['color'],1,6);
				$v = strlen($v)==3 ? $v[0].$v[0].$v[1].$v[1].$v[2].$v[2] : $v;// expand cf0 => ccff00
				$font['color'] = "FF".strtoupper($v);
			}
			if ($font!=$defaultFont)
			{
				$styleIndexes[$i]['font_idx'] = self::addToListGetIndex($fonts, json_encode($font) );
			}
		}
		return [
		    'fills' => $fills,
		    'fonts' => $fonts,
		    'borders' => $borders,
		    'styles' => $styleIndexes
		];
	}

	protected function writeStylesXML()
	{
		$r = self::styleFontIndexes();
		$fills = $r['fills'];
		$fonts = $r['fonts'];
		$borders = $r['borders'];
		$styleIndexes = $r['styles'];

		$temporaryFilename = $this->tempFilename();
		$file = new XLSXWriterBuffererWriter($temporaryFilename);
		$file->write('<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n");
		$file->write('<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">');
		$file->write('<numFmts count="'.count($this->numberFormats).'">');
		foreach($this->numberFormats as $i=>$v) {
			$file->write('<numFmt numFmtId="'.(164+$i).'" formatCode="'.self::xmlspecialchars($v).'" />');
		}
		//$file->write(		'<numFmt formatCode="GENERAL" numFmtId="164"/>');
		//$file->write(		'<numFmt formatCode="[$$-1009]#,##0.00;[RED]\-[$$-1009]#,##0.00" numFmtId="165"/>');
		//$file->write(		'<numFmt formatCode="YYYY-MM-DD\ HH:MM:SS" numFmtId="166"/>');
		//$file->write(		'<numFmt formatCode="YYYY-MM-DD" numFmtId="167"/>');
		$file->write('</numFmts>');

		$file->write('<fonts count="'.(count($fonts)).'">');
		$file->write(		'<font><name val="Arial"/><charset val="1"/><family val="2"/><sz val="10"/></font>');
		$file->write(		'<font><name val="Arial"/><family val="0"/><sz val="10"/></font>');
		$file->write(		'<font><name val="Arial"/><family val="0"/><sz val="10"/></font>');
		$file->write(		'<font><name val="Arial"/><family val="0"/><sz val="10"/></font>');

		foreach($fonts as $font) {
			if (!empty($font)) { //fonts have 4 empty placeholders in array to offset the 4 static xml entries above
				$f = json_decode($font,true);
				$file->write('<font>');
				$file->write(	'<name val="'.htmlspecialchars($f['name']).'"/><charset val="1"/><family val="'.intval($f['family']).'"/>');
				$file->write(	'<sz val="'.intval($f['size']).'"/>');
				if (!empty($f['color'])) { $file->write('<color rgb="'.strval($f['color']).'"/>'); }
				if (!empty($f['bold'])) { $file->write('<b val="true"/>'); }
				if (!empty($f['italic'])) { $file->write('<i val="true"/>'); }
				if (!empty($f['underline'])) { $file->write('<u val="single"/>'); }
				if (!empty($f['strike'])) { $file->write('<strike val="true"/>'); }
				$file->write('</font>');
			}
		}
		$file->write('</fonts>');

		$file->write('<fills count="'.(count($fills)).'">');
		$file->write(	'<fill><patternFill patternType="none"/></fill>');
		$file->write(	'<fill><patternFill patternType="gray125"/></fill>');
		foreach($fills as $fill) {
			if (!empty($fill)) { //fills have 2 empty placeholders in array to offset the 2 static xml entries above
				$file->write('<fill><patternFill patternType="solid"><fgColor rgb="'.strval($fill).'"/><bgColor indexed="64"/></patternFill></fill>');
			}
		}
		$file->write('</fills>');

		$file->write('<borders count="'.(count($borders)).'">');
        $file->write(    '<border diagonalDown="false" diagonalUp="false"><left/><right/><top/><bottom/><diagonal/></border>');
		foreach($borders as $border) {
			if (!empty($border)) { //fonts have an empty placeholder in the array to offset the static xml entry above
				$pieces = json_decode($border,true);
				$border_style = !empty($pieces['style']) ? $pieces['style'] : 'hair';
				$border_color = !empty($pieces['color']) ? '<color rgb="'.strval($pieces['color']).'"/>' : '';
				$file->write('<border diagonalDown="false" diagonalUp="false">');
				foreach (['left', 'right', 'top', 'bottom'] as $side)
				{
                    $show_side = in_array($side,$pieces['side']) ? true : false;
					$file->write($show_side ? "<$side style=\"$border_style\">$border_color</$side>" : "<$side/>");
				}
				$file->write(  '<diagonal/>');
				$file->write('</border>');
			}
		}
		$file->write('</borders>');

		$file->write('<cellStyleXfs count="20">');
		$file->write(		'<xf applyAlignment="true" applyBorder="true" applyFont="true" applyProtection="true" borderId="0" fillId="0" fontId="0" numFmtId="164">');
		$file->write(		'<alignment horizontal="general" indent="0" shrinkToFit="false" textRotation="0" vertical="bottom" wrapText="false"/>');
		$file->write(		'<protection hidden="false" locked="true"/>');
		$file->write(		'</xf>');
		$file->write(		'<xf applyAlignment="false" applyBorder="false" applyFont="true" applyProtection="false" borderId="0" fillId="0" fontId="1" numFmtId="0"/>');
		$file->write(		'<xf applyAlignment="false" applyBorder="false" applyFont="true" applyProtection="false" borderId="0" fillId="0" fontId="1" numFmtId="0"/>');
		$file->write(		'<xf applyAlignment="false" applyBorder="false" applyFont="true" applyProtection="false" borderId="0" fillId="0" fontId="2" numFmtId="0"/>');
		$file->write(		'<xf applyAlignment="false" applyBorder="false" applyFont="true" applyProtection="false" borderId="0" fillId="0" fontId="2" numFmtId="0"/>');
		$file->write(		'<xf applyAlignment="false" applyBorder="false" applyFont="true" applyProtection="false" borderId="0" fillId="0" fontId="0" numFmtId="0"/>');
		$file->write(		'<xf applyAlignment="false" applyBorder="false" applyFont="true" applyProtection="false" borderId="0" fillId="0" fontId="0" numFmtId="0"/>');
		$file->write(		'<xf applyAlignment="false" applyBorder="false" applyFont="true" applyProtection="false" borderId="0" fillId="0" fontId="0" numFmtId="0"/>');
		$file->write(		'<xf applyAlignment="false" applyBorder="false" applyFont="true" applyProtection="false" borderId="0" fillId="0" fontId="0" numFmtId="0"/>');
		$file->write(		'<xf applyAlignment="false" applyBorder="false" applyFont="true" applyProtection="false" borderId="0" fillId="0" fontId="0" numFmtId="0"/>');
		$file->write(		'<xf applyAlignment="false" applyBorder="false" applyFont="true" applyProtection="false" borderId="0" fillId="0" fontId="0" numFmtId="0"/>');
		$file->write(		'<xf applyAlignment="false" applyBorder="false" applyFont="true" applyProtection="false" borderId="0" fillId="0" fontId="0" numFmtId="0"/>');
		$file->write(		'<xf applyAlignment="false" applyBorder="false" applyFont="true" applyProtection="false" borderId="0" fillId="0" fontId="0" numFmtId="0"/>');
		$file->write(		'<xf applyAlignment="false" applyBorder="false" applyFont="true" applyProtection="false" borderId="0" fillId="0" fontId="0" numFmtId="0"/>');
		$file->write(		'<xf applyAlignment="false" applyBorder="false" applyFont="true" applyProtection="false" borderId="0" fillId="0" fontId="0" numFmtId="0"/>');
		$file->write(		'<xf applyAlignment="false" applyBorder="false" applyFont="true" applyProtection="false" borderId="0" fillId="0" fontId="1" numFmtId="43"/>');
		$file->write(		'<xf applyAlignment="false" applyBorder="false" applyFont="true" applyProtection="false" borderId="0" fillId="0" fontId="1" numFmtId="41"/>');
		$file->write(		'<xf applyAlignment="false" applyBorder="false" applyFont="true" applyProtection="false" borderId="0" fillId="0" fontId="1" numFmtId="44"/>');
		$file->write(		'<xf applyAlignment="false" applyBorder="false" applyFont="true" applyProtection="false" borderId="0" fillId="0" fontId="1" numFmtId="42"/>');
		$file->write(		'<xf applyAlignment="false" applyBorder="false" applyFont="true" applyProtection="false" borderId="0" fillId="0" fontId="1" numFmtId="9"/>');
		$file->write('</cellStyleXfs>');

		$file->write('<cellXfs count="'.(count($styleIndexes)).'">');
		//$file->write(		'<xf applyAlignment="false" applyBorder="false" applyFont="false" applyProtection="false" borderId="0" fillId="0" fontId="0" numFmtId="164" xfId="0"/>');
		//$file->write(		'<xf applyAlignment="false" applyBorder="false" applyFont="false" applyProtection="false" borderId="0" fillId="0" fontId="0" numFmtId="165" xfId="0"/>');
		//$file->write(		'<xf applyAlignment="false" applyBorder="false" applyFont="false" applyProtection="false" borderId="0" fillId="0" fontId="0" numFmtId="166" xfId="0"/>');
		//$file->write(		'<xf applyAlignment="false" applyBorder="false" applyFont="false" applyProtection="false" borderId="0" fillId="0" fontId="0" numFmtId="167" xfId="0"/>');
		foreach($styleIndexes as $v)
		{
			$applyAlignment = isset($v['alignment']) ? 'true' : 'false';
			$wrapText = !empty($v['wrap_text']) ? 'true' : 'false';
			$horizAlignment = isset($v['halign']) ? $v['halign'] : 'general';
			$vertAlignment = isset($v['valign']) ? $v['valign'] : 'bottom';
			$applyBorder = isset($v['border_idx']) ? 'true' : 'false';
			$applyFont = 'true';
			$borderIdx = isset($v['border_idx']) ? intval($v['border_idx']) : 0;
			$fillIdx = isset($v['fill_idx']) ? intval($v['fill_idx']) : 0;
			$fontIdx = isset($v['font_idx']) ? intval($v['font_idx']) : 0;
			//$file->write('<xf applyAlignment="'.$applyAlignment.'" applyBorder="'.$applyBorder.'" applyFont="'.$applyFont.'" applyProtection="false" borderId="'.($borderIdx).'" fillId="'.($fillIdx).'" fontId="'.($fontIdx).'" numFmtId="'.(164+$v['num_fmt_idx']).'" xfId="0"/>');
			$file->write('<xf applyAlignment="'.$applyAlignment.'" applyBorder="'.$applyBorder.'" applyFont="'.$applyFont.'" applyProtection="false" borderId="'.($borderIdx).'" fillId="'.($fillIdx).'" fontId="'.($fontIdx).'" numFmtId="'.(164+$v['num_fmt_idx']).'" xfId="0">');
			$file->write('	<alignment horizontal="'.$horizAlignment.'" vertical="'.$vertAlignment.'" textRotation="0" wrapText="'.$wrapText.'" indent="0" shrinkToFit="false"/>');
			$file->write('	<protection locked="true" hidden="false"/>');
			$file->write('</xf>');
		}
		$file->write('</cellXfs>');
		$file->write(	'<cellStyles count="6">');
		$file->write(		'<cellStyle builtinId="0" customBuiltin="false" name="Normal" xfId="0"/>');
		$file->write(		'<cellStyle builtinId="3" customBuiltin="false" name="Comma" xfId="15"/>');
		$file->write(		'<cellStyle builtinId="6" customBuiltin="false" name="Comma [0]" xfId="16"/>');
		$file->write(		'<cellStyle builtinId="4" customBuiltin="false" name="Currency" xfId="17"/>');
		$file->write(		'<cellStyle builtinId="7" customBuiltin="false" name="Currency [0]" xfId="18"/>');
		$file->write(		'<cellStyle builtinId="5" customBuiltin="false" name="Percent" xfId="19"/>');
		$file->write(	'</cellStyles>');
		$file->write('</styleSheet>');
		$file->close();
		return $temporaryFilename;
	}

	protected function buildAppXML()
	{
		$resultXml="";
		$resultXml.='<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n";
		$resultXml.='<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">';
		$resultXml.='<TotalTime>0</TotalTime>';
		$resultXml.='<Company>'.self::xmlspecialchars($this->company).'</Company>';
		$resultXml.='</Properties>';
		return $resultXml;
	}

	protected function buildCoreXML()
	{
		$resultXml="";
		$resultXml.='<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n";
		$resultXml.='<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">';
		$resultXml.='<dcterms:created xsi:type="dcterms:W3CDTF">'.date("Y-m-d\TH:i:s.00\Z").'</dcterms:created>';//$date_time = '2014-10-25T15:54:37.00Z';
		$resultXml.='<dc:title>'.self::xmlspecialchars($this->title).'</dc:title>';
		$resultXml.='<dc:subject>'.self::xmlspecialchars($this->subject).'</dc:subject>';
		$resultXml.='<dc:creator>'.self::xmlspecialchars($this->author).'</dc:creator>';
		if (!empty($this->keywords)) {
			$resultXml.='<cp:keywords>'.self::xmlspecialchars(implode (", ", (array) $this->keywords)).'</cp:keywords>';
		}		
		$resultXml.='<dc:description>'.self::xmlspecialchars($this->description).'</dc:description>';		
		$resultXml.='<cp:revision>0</cp:revision>';
		$resultXml.='</cp:coreProperties>';
		return $resultXml;
	}

	protected function buildRelationshipsXML()
	{
		$resultXml="";
		$resultXml.='<?xml version="1.0" encoding="UTF-8"?>'."\n";
		$resultXml.='<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
		$resultXml.='<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>';
		$resultXml.='<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>';
		$resultXml.='<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>';
		$resultXml.="\n";
		$resultXml.='</Relationships>';
		return $resultXml;
	}

	protected function buildWorkbookXML()
	{
		$i=0;
		$resultXml="";
		$resultXml.='<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n";
		$resultXml.='<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
		$resultXml.='<fileVersion appName="Calc"/><workbookPr backupFile="false" showObjects="all" date1904="false"/><workbookProtection/>';
		$resultXml.='<bookViews><workbookView activeTab="0" firstSheet="0" showHorizontalScroll="true" showSheetTabs="true" showVerticalScroll="true" tabRatio="212" windowHeight="8192" windowWidth="16384" xWindow="0" yWindow="0"/></bookViews>';
		$resultXml.='<sheets>';
		foreach($this->sheets as $sheet) {
			$sheetname = self::sanitizeSheetname($sheet->sheetname);
			$resultXml.='<sheet name="'.self::xmlspecialchars($sheetname).'" sheetId="'.($i+1).'" state="visible" r:id="rId'.($i+2).'"/>';
			$i++;
		}
		$resultXml.='</sheets>';
		$resultXml.='<definedNames>';
		foreach($this->sheets as $sheet) {
		    if ($sheet->autoFilter) {
		        $sheetname = self::sanitizeSheetname($sheet->sheetname);
		        $resultXml.='<definedName name="_xlnm._FilterDatabase" localSheetId="0" hidden="1">\''.self::xmlspecialchars($sheetname).'\'!$A$1:' . self::xlsCell($sheet->rowCount - 1, count($sheet->columns) - 1, true) . '</definedName>';
		        $i++;
		    }
		}
		$resultXml.='</definedNames>';
		$resultXml.='<calcPr iterateCount="100" refMode="A1" iterate="false" iterateDelta="0.001"/></workbook>';
		return $resultXml;
	}

	protected function buildWorkbookRelsXML()
	{
		$i=0;
		$resultXml="";
		$resultXml.='<?xml version="1.0" encoding="UTF-8"?>'."\n";
		$resultXml.='<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
		$resultXml.='<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
		foreach($this->sheets as $sheet) {
			$resultXml.='<Relationship Id="rId'.($i+2).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/'.($sheet->xmlname).'"/>';
			$i++;
		}
		$resultXml.="\n";
		$resultXml.='</Relationships>';
		return $resultXml;
	}

	protected function buildContentTypesXML()
	{
		$resultXml="";
		$resultXml.='<?xml version="1.0" encoding="UTF-8"?>'."\n";
		$resultXml.='<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">';
		$resultXml.='<Override PartName="/_rels/.rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>';
		$resultXml.='<Override PartName="/xl/_rels/workbook.xml.rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>';
		foreach($this->sheets as $sheet) {
			$resultXml.='<Override PartName="/xl/worksheets/'.($sheet->xmlname).'" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
		}
		$resultXml.='<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>';
		$resultXml.='<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
		$resultXml.='<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>';
		$resultXml.='<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>';
		$resultXml.="\n";
		$resultXml.='</Types>';
		return $resultXml;
	}

	//------------------------------------------------------------------
	/*
	 * @param $row_number int, zero based
	 * @param $column_number int, zero based
	 * @param $absolute bool
	 * @return Cell label/coordinates, ex: A1, C3, AA42 (or if $absolute==true: $A$1, $C$3, $AA$42)
	 * */
	public static function xlsCell($rowNumber, $columnNumber, $absolute=false)
	{
		$n = $columnNumber;
		for($r = ""; $n >= 0; $n = intval($n / 26) - 1) {
			$r = chr($n%26 + 0x41) . $r;
		}
		if ($absolute) {
			return '$' . $r . '$' . ($rowNumber+1);			
		}
		return $r . ($rowNumber+1);
	}
	
	/**
	 * 
	 * @param string $msg
	 */
	public static function log(string $msg)
	{
		file_put_contents("php://stderr", date("Y-m-d H:i:s:").rtrim(is_array($msg) ? json_encode($msg) : $msg)."\n");
	}
	
	public static function sanitizeFilename($filename) //http://msdn.microsoft.com/en-us/library/aa365247%28VS.85%29.aspx
	{
		$nonprinting = array_map('chr', range(0,31));
		$invalidChars = ['<', '>', '?', '"', ':', '|', '\\', '/', '*', '&'];
		$allInvalids = array_merge($nonprinting,$invalidChars);
		return str_replace($allInvalids, "", $filename);
	}
	
	public static function sanitizeSheetname($sheetname) 
	{
		static $badchars  = '\\/?*:[]';
		static $goodchars = '        ';
		$sheetname = strtr($sheetname, $badchars, $goodchars);
		$sheetname = mb_substr($sheetname, 0, 31);
		$sheetname = trim(trim(trim($sheetname),"'"));//trim before and after trimming single quotes
		return !empty($sheetname) ? $sheetname : 'Sheet'.((rand()%900)+100);
	}
	
	public static function xmlspecialchars($val)
	{
		//note, badchars does not include \t\n\r (\x09\x0a\x0d)
		static $badchars = "\x00\x01\x02\x03\x04\x05\x06\x07\x08\x0b\x0c\x0e\x0f\x10\x11\x12\x13\x14\x15\x16\x17\x18\x19\x1a\x1b\x1c\x1d\x1e\x1f\x7f";
		static $goodchars = "                              ";
		return strtr(htmlspecialchars($val, ENT_QUOTES | ENT_XML1), $badchars, $goodchars);//strtr appears to be faster than str_replace
	}
	
	public static function arrayFirstKey(array $arr)
	{
		reset($arr);
		$first_key = key($arr);
		return $first_key;
	}

	/**
	 * 
	 * @param string $numFormat
	 * @return string
	 */
	private static function determineNumberFormatType($numFormat)
	{
		$numFormat = preg_replace("/\[(Black|Blue|Cyan|Green|Magenta|Red|White|Yellow)\]/i", "", $numFormat);
		if ($numFormat=='GENERAL') return 'n_auto';
		if ($numFormat=='@') return 'n_string';
		if ($numFormat=='0') return 'n_numeric';
		if (preg_match('/[H]{1,2}:[M]{1,2}(?![^"]*+")/i', $numFormat)) return 'n_datetime';
		if (preg_match('/[M]{1,2}:[S]{1,2}(?![^"]*+")/i', $numFormat)) return 'n_datetime';
		if (preg_match('/[Y]{2,4}(?![^"]*+")/i', $numFormat)) return 'n_date';
		if (preg_match('/[D]{1,2}(?![^"]*+")/i', $numFormat)) return 'n_date';
		if (preg_match('/[M]{1,2}(?![^"]*+")/i', $numFormat)) return 'n_date';
		if (preg_match('/$(?![^"]*+")/', $numFormat)) return 'n_numeric';
		if (preg_match('/%(?![^"]*+")/', $numFormat)) return 'n_numeric';
		if (preg_match('/0(?![^"]*+")/', $numFormat)) return 'n_numeric';
		return 'n_auto';
	}
	
	/**
	 * 
	 * @param string $numFormat
	 * @return string
	 */
	private static function numberFormatStandardized($numFormat)
	{
		if ($numFormat=='money') { $numFormat='dollar'; }
		if ($numFormat=='number') { $numFormat='integer'; }

		if      ($numFormat=='string')   $numFormat='@';
		else if ($numFormat=='integer')  $numFormat='0';
		else if ($numFormat=='date')     $numFormat='YYYY-MM-DD';
		else if ($numFormat=='datetime') $numFormat='YYYY-MM-DD HH:MM:SS';
		else if ($numFormat=='price')    $numFormat='#,##0.00';
		else if ($numFormat=='dollar')   $numFormat='[$$-1009]#,##0.00;[RED]-[$$-1009]#,##0.00';
		else if ($numFormat=='euro')     $numFormat='#,##0.00 [$€-407];[RED]-#,##0.00 [$€-407]';
		$ignore_until='';
		$escaped = '';
		for($i=0,$ix=strlen($numFormat); $i<$ix; $i++)
		{
			$c = $numFormat[$i];
			if ($ignore_until=='' && $c=='[')
				$ignore_until=']';
			else if ($ignore_until=='' && $c=='"')
				$ignore_until='"';
			else if ($ignore_until==$c)
				$ignore_until='';
			if ($ignore_until=='' && ($c==' ' || $c=='-'  || $c=='('  || $c==')') && ($i==0 || $numFormat[$i-1]!='_'))
				$escaped.= "\\".$c;
			else
				$escaped.= $c;
		}
		return $escaped;
	}
	
	/**
	 * 
	 * @param array $haystack
	 * @param mixed $needle
	 * @return mixed
	 */
	public static function addToListGetIndex(&$haystack, $needle)
	{
		$existingIdx = array_search($needle, $haystack, true);
		if ($existingIdx===false)
		{
			$existingIdx = count($haystack);
			$haystack[] = $needle;
		}
		return $existingIdx;
	}

	/**
	 * 
	 * @param string $dateInput
	 * @return number
	 */
	public static function convertDateTime($dateInput) //thanks to Excel::Writer::XLSX::Worksheet.pm (perl)
	{
	    
		$days    = 0;    # Number of days since epoch
		$seconds = 0;    # Time expressed as fraction of 24h hours in seconds
		$year = $month = $day = 0;
		$hour = $min = $sec = 0;

		$dateTime = $dateInput;
		$matches = null;
		if (preg_match("/(\d{4})\-(\d{2})\-(\d{2})/", $dateTime, $matches))
		{
			list(, $year, $month, $day) = $matches;
		}
		$matches = null;
		if (preg_match("/(\d+):(\d{2}):(\d{2})/", $dateTime, $matches))
		{
		    list(, $hour, $min, $sec) = $matches;
			$seconds = ( $hour * 60 * 60 + $min * 60 + $sec ) / ( 24 * 60 * 60 );
		}

		//using 1900 as epoch, not 1904, ignoring 1904 special case

		# Special cases for Excel.
		if ("$year-$month-$day"=='1899-12-31')  return $seconds      ;    # Excel 1900 epoch
		if ("$year-$month-$day"=='1900-01-00')  return $seconds      ;    # Excel 1900 epoch
		if ("$year-$month-$day"=='1900-02-29')  return 60 + $seconds ;    # Excel false leapday

		# We calculate the date by calculating the number of days since the epoch
		# and adjust for the number of leap days. We calculate the number of leap
		# days by normalising the year in relation to the epoch. Thus the year 2000
		# becomes 100 for 4 and 100 year leapdays and 400 for 400 year leapdays.
		$epoch  = 1900;
		$offset = 0;
		$norm   = 300;
		$range  = $year - $epoch;

		# Set month days and check for leap year.
		$leap = (($year % 400 == 0) || (($year % 4 == 0) && ($year % 100)) ) ? 1 : 0;
		$mdays = array( 31, ($leap ? 29 : 28), 31, 30, 31, 30, 31, 31, 30, 31, 30, 31 );

		# Some boundary checks
		if($year < $epoch || $year > 9999) return 0;
		if($month < 1     || $month > 12)  return 0;
		if($day < 1       || $day > $mdays[ $month - 1 ]) return 0;

		# Accumulate the number of days since the epoch.
		$days = $day;    # Add days for current month
		$days += array_sum( array_slice($mdays, 0, $month-1 ) );    # Add days for past months
		$days += $range * 365;                      # Add days for past years
		$days += intval( ( $range ) / 4 );             # Add leapdays
		$days -= intval( ( $range + $offset ) / 100 ); # Subtract 100 year leapdays
		$days += intval( ( $range + $offset + $norm ) / 400 );  # Add 400 year leapdays
		$days -= $leap;                                      # Already counted above

		# Adjust for Excel erroneously treating 1900 as a leap year.
		if ($days > 59) { $days++;}

		return $days + $seconds;
	}
}
?>

<?php

if(isset($_POST['export'])){
	$url = 'uploads/'.$_POST['name'];
	$writer = new XLSXWriter();
	
	$writer->writeSheet($_POST['export']);
	$writer->writeToFile($url);
	echo $url;
}

