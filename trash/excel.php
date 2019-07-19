<?php


header("Content-type: application/json; charset=utf-8"); 
require 'vendor/autoload.php';
if ( $xlsx = SimpleXLSX::parse('sss.xlsx') ) {
    echo json_encode( $xlsx->rows() );
} else {
    echo SimpleXLSX::parseError();
}
