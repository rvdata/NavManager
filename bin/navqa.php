#!/usr/bin/env php
<?php

define('INCLUDE_PATH', dirname(__FILE__) . '/../include/');
require INCLUDE_PATH . '/getopts.php';
require INCLUDE_PATH . '/navdatalist.inc.php';
require INCLUDE_PATH . '/navqa.inc.php';


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


if ($opts['i'] == null) {
    $syntaxErr .=  "SYNTAX ERROR: Must specify a full res r2rnav file [-i]\n";
} else {
	$navBestResPreQC = trim($opts['i']);
	if (!file_exists($navBestResPreQC)) {
		echo "navqa(): Could not locate file: " . $navBestResPreQC . "\n";
		exit(1);
	}
}


if ($opts['l']) {
    $qalogfile = trim($opts['l']);
    $fqalog = fopen($qalogfile, 'w');
    if ($fqalog == null) {
        echo "navqa: Could not open log file for writing: "
            . $qalogfile . "\n";
        exit(1);
    }
} else {
	$fqalog = null;
}

if ($syntaxErr) {
    usage();
    echo $syntaxErr;
    exit(1);
}


//---------------------END GET OPTS --------------------------------//

$speedHoriMax = 8.7;
$accelHoriMax = 1;
$gapThreshold = 300;

// Get port start/end info from first/last line of file

		$if = fopen($navBestResPreQC, r);

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

// DEBUG stuff

$debug = 'TRUE';

if ($debug) {
	echo "\n";
	echo "Running navqa() with:\n";
	echo "\tInput file:               ", $navBestResPreQC, "\n";
	echo "\tStart:                    ", $dateStringUTCStart, "\n";
	echo "\tEnd:                      ", $dateStringUTCEnd, "\n";
	echo "\tSpeed threshold [m/s]:    ", $speedHoriMax, "\n";
	echo "\tAccel threshold [m/s^2]:  ", $accelHoriMax, "\n";
	echo "\tGap threshold [s]:        ", $gapThreshold, "\n";
	echo "\tDeparture Port Longitude: ", $portLongitudeStart, "\n";
	echo "\tDeparture Port Latitude:  ", $portLatitudeStart, "\n";
	echo "\tArrival Port Longitude:   ", $portLongitudeEnd, "\n";
	echo "\tArrival Port Latitude:    ", $portLatitudeEnd, "\n";
	if ($fqalog != null) {
		echo "\tLog file:                 ", $qalogfile, "\n";
	} else {
		echo "\tLog file:                none\n";
	}   
}

// END DEBUG stuff

	$qaNavigationRaw = navqa(
		$navBestResPreQC, $dateStringUTCStart, $dateStringUTCEnd,
		$speedHoriMax, $accelHoriMax, $gapThreshold, 
		$portLongitudeStart, $portLatitudeStart, 
		$portLongitudeEnd, $portLatitudeEnd,
		$fqalog
	);

	var_dump($qaNavigationRaw);


#var_dump($qaNavigationRaw);
/**
 * Display how to use this program on the command-line
 */
function usage() 
{
    echo "\n";
    echo "Program: navqa.php\nVersion: 0.9 \"Shark Bait\"\nAuthors: Aaron Sweeney, Chris Olson\n";
    echo "Rolling Deck To Repository (R2R): Navigation Manager\n";
    echo "Purpose: Quality assess navigation data in the\n";
	echo "	common r2rnav raw file format.\n";
    echo "\n";
    echo "Usage: navqa.php -i <infile> [-l <logfile>] [-h]\n";
    echo "\n";
    
} // end function usage()

?>
