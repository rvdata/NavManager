#!/usr/bin/env php
<?php

define('INCLUDE_PATH', dirname(__FILE__) . '/../include/');
require INCLUDE_PATH . '/flags.inc.php';
require INCLUDE_PATH . '/getopts.php';
require INCLUDE_PATH . '/navbounds.inc.php';
require INCLUDE_PATH . '/navdatalist.inc.php';


//-------------------------GET OPTS --------------------------------//

$opts = getopts(
    array(
        'i' => array('switch' => array('i', 'infile'), 'type' => GETOPT_VAL),
        'h' => array('switch' => array('h', 'help'), 'type' => GETOPT_SWITCH),
        'l' => array('switch' => array('l', 'log'), 'type' => GETOPT_VAL),
    ), $argv
);

// Help or missing required arguments
if ($opts['h']) {
    usage();
    exit(1);
}

$syntaxErr = "";

if ($opts['i'] == null) {
    $syntaxErr .=  "SYNTAX ERROR: Must specify a full res r2rnav file [-i]\n";
} else {
	$navInfoFile = trim($opts['i']);
	if (!file_exists($navInfoFile)) {
		echo "navinfo(): Could not locate file: " . $navInfoFile . "\n";
		exit(1);
	}
}

if ($opts['l']) {
    $qalogfile = trim($opts['l']);
    $fqalog = fopen($qalogfile, 'w');
    if ($fqalog == null) {
        echo "navinfo: Could not open log file for writing: "
            . $qalogfile . "\n";
        exit(1);
    }
} else {
	$fqalog = null;
}

if ($syntaxErr != "") {
    usage();
    echo $syntaxErr;
    exit(1);
}


//---------------------END GET OPTS --------------------------------//


// Get port start/end info from first/last line of file


// DEBUG stuff

$debug = '';

if ($debug) {
	echo "\n";
	echo "Running navbounds() with:\n";
	echo "\tInput file:               ", $navInfoFile, "\n";
}

// END DEBUG stuff

// Get port start/end info from first/last line of file

	$if = fopen($navInfoFile, 'r'); 

	$headerPattern =  preg_quote(HEADER, '/');
	$firstLine = firstLine($if, $headerPattern);
	$dataRecFirst = preg_split('/\t/', $firstLine);
	$dateStringUTCStart = $dataRecFirst[0];
	$portLongitudeStart = $dataRecFirst[1];
	$portLatitudeStart = $dataRecFirst[2];

	$lastLine = lastLine($if, PHP_EOL);
	$dataRecLast = preg_split('/\t/', $lastLine);
	$dateStringUTCEnd = $dataRecLast[0];
	$portLongitudeEnd = $dataRecLast[1];
	$portLatitudeEnd = $dataRecLast[2];

	fclose($if);

	$bounds = @navbounds($navInfoFile);

    $output = '';
    $output = $output . "Navigation Start/End Info:\n";
    $output = $output . "\tStart Date:\t" . $dateStringUTCStart . "\n"; 
    $output = $output . "\tEnd Date:\t" . $dateStringUTCEnd . "\n"; 
    $output = $output . "\tStart Lat/Lon:\t[" . $portLatitudeStart . "," . $portLongitudeStart . "]\n"; 
    $output = $output . "\tEnd Lat/Lon:\t[" . $portLatitudeEnd . "," . $portLongitudeEnd . "]\n"; 
    $output = $output . "\n";
    $output = $output . "Navigation Bounding Box Info:\n";
    $output = $output . "\tMinimum Longitude:\t" . $bounds->westBoundLongitude . "\n";
    $output = $output . "\tMaximum Longitude:\t" . $bounds->eastBoundLongitude . "\n";
    $output = $output . "\tMinimum Latitude:\t" . $bounds->southBoundLatitude . "\n";
    $output = $output . "\tMaximum Latitude:\t" . $bounds->northBoundLatitude . "\n";
	
	if ($fqalog != null) {
		fwrite($fqalog, $output);
		fclose($fqalog);
	} else {
        echo $output;
	}

#var_dump($qaNavigationRaw);
/**
 * Display how to use this program on the command-line
 */
function usage() 
{
    echo "\n";
    echo "Program: navinfo.php\nVersion: 0.9 \"Shark Bait\"\nAuthors: Aaron Sweeney, Chris Olson\n";
    echo "Rolling Deck To Repository (R2R): Navigation Manager\n";
    echo "Purpose: Get basic info on an r2rnav file.\n";
    echo "\n";
    echo "Usage: navinfo.php -i <infile> [-l <logfile>] [-h]\n";
    echo "\n";
    
} // end function usage()

?>
