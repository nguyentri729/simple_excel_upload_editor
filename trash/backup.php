<html>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.1.1/jquery.min.js"></script>

<script src="http://cdn.bossanova.uk/js/jquery.jexcel.js"></script>
<link rel="stylesheet" href="http://cdn.bossanova.uk/css/jquery.jexcel.css" type="text/css" />
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.13.5/xlsx.full.min.js"></script>
<script type="text/javascript" src="//unpkg.com/xlsx/dist/shim.min.js"></script>
<script type="text/javascript" src="//unpkg.com/blob.js@1.0.1/Blob.js"></script>
<script type="text/javascript" src="//unpkg.com/file-saver@1.3.3/FileSaver.js"></script>
<script>
function doit(type, fn, dl) {
    var elt = document.getElementById('my');
    var wb = XLSX.utils.table_to_book(elt, {sheet:"Sheet JS"});
    return dl ?
        XLSX.write(wb, {bookType:type, bookSST:true, type: 'base64'}) :
        XLSX.writeFile(wb, fn || ('cac.' + (type || 'xlsx')));
}
</script>
<div id="my"></div>

<pre><b>Export it!</b></pre>
<table id="xport">
<tr><td><pre>XLSX Excel 2007+ XML</pre></td><td>
    <p id="xportxlsx" class="xport"><input type="submit" value="Export to XLSX!" onclick="doit('xlsx');"></p>
    <p id="xlsxbtn" class="btn">Flash required for actually downloading the generated file.</p>
</td></tr>
<tr><td><pre>XLSB Excel 2007+ Binary</pre></td><td>
    <p id="xportxlsb" class="xport"><input type="submit" value="Export to XLSB!" onclick="doit('xlsb');"></p>
    <p id="xlsbbtn" class="btn">Flash required for actually downloading the generated file.</p>
</td></tr>
<tr><td><pre>XLS Excel 97-2004 Binary</pre></td><td>
    <p id="xportbiff8" class="xport"><input type="submit" value="Export to XLS!"  onclick="doit('biff8', 'test.xls');"></p>
    <p id="biff8btn" class="btn">Flash required for actually downloading the generated file.</p>
</td></tr>
<tr><td><pre>ODS</pre></td><td>
    <p id="xportods" class="xport"><input type="submit" value="Export to ODS!"  onclick="doit('ods');"></p>
    <p id="odsbtn" class="btn">Flash required for actually downloading the generated file.</p>
</td></tr>
<tr><td><pre>Flat ODS</pre></td><td>
    <p id="xportfods" class="xport"><input type="submit" value="Export to FODS!"  onclick="doit('fods', 'test.fods');"></p>
    <p id="fodsbtn" class="btn">Flash required for actually downloading the generated file.</p>
</td></tr>
</table>
<pre><b>Powered by the <a href="//sheetjs.com/opensource">community version of js-xlsx</a></b></pre>
<script type="text/javascript">
function tableau(pid, iid, fmt, ofile) {
    if(typeof Downloadify !== 'undefined') Downloadify.create(pid,{
            swf: 'downloadify.swf',
            downloadImage: 'download.png',
            width: 100,
            height: 30,
            filename: ofile, data: function() { return doit(fmt, ofile, true); },
            transparent: false,
            append: false,
            dataType: 'base64',
            onComplete: function(){ alert('Your File Has Been Saved!'); },
            onCancel: function(){ alert('You have cancelled the saving of this file.'); },
            onError: function(){ alert('You must put something in the File Contents or there will be nothing to save!'); }
    }); else document.getElementById(pid).innerHTML = "";
}
tableau('biff8btn', 'xportbiff8', 'biff8', 'test.xls');
tableau('odsbtn',   'xportods',   'ods',   'test.ods');
tableau('fodsbtn',  'xportfods',  'fods',  'test.fods');
tableau('xlsbbtn',  'xportxlsb',  'xlsb',  'test.xlsb');
tableau('xlsxbtn',  'xportxlsx',  'xlsx',  'test.xlsx');

</script>
<script>

            $.ajax({
                async: false,
                method: 'GET',
                url: "excel.php",
            }).done(function(a) {
            
                $('#my').jexcel({ data:a});

            }).fail(function(){
               alert('error');
            });

</script>
</html>