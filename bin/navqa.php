#!/usr/bin/env php
<?php

define('INCLUDE_PATH', dirname(__FILE__) . '/../include/');
require INCLUDE_PATH . '/globals.inc.php';
require INCLUDE_PATH . '/getopts.php';
require INCLUDE_PATH . '/navdatalist.inc.php';
require INCLUDE_PATH . '/navqa.inc.php';


//-------------------------GET OPTS --------------------------------//

$opts = getopts(
    array(
        'i' => array('switch' => array('i', 'infile'), 'type' => GETOPT_VAL),
        'v' => array('switch' => array('v', 'max_speed'), 'type' => GETOPT_VAL),
        'a' => array('switch' => array('a', 'max_accel'), 'type' => GETOPT_VAL),
        'g' => array('switch' => array('g', 'max_gap'), 'type' => GETOPT_VAL),
        'l' => array('switch' => array('l', 'log'), 'type' => GETOPT_VAL),
        'h' => array('switch' => array('h', 'help'), 'type' => GETOPT_SWITCH),
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

if ($opts['v']) {
	$speedHoriMax = trim($opts['v']);
} else {
	$speedHoriMax = MAX_SPEED;
}

if ($opts['a']) {
    $accelHoriMax = trim($opts['a']);
} else {
    $accelHoriMax = MAX_ACCEL;
}

if ($opts['g']) {
    $gapThreshold = trim($opts['g']);
} else {
    $gapThreshold = MAX_GAP;
}

if ($syntaxErr != "") {
    usage();
    echo $syntaxErr;
    exit(1);
}


//---------------------END GET OPTS --------------------------------//

// Get port start/end info from first/last line of file

		$if = fopen($navBestResPreQC, 'r');

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

	$qaNavigationRaw = @navqa(
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
    echo "Usage: navqa.php -i <infile> [-v <speed_threshold>] [-a <acceleration_threshold>]\n";
	echo "\t[-g <gap_threshold>] [-l <logfile>] [-h]\n\n";
	echo "Required:\n";
	echo "\t-i <infile>\n\n";
	echo "\t\tThe r2rnav file to be quality assessed.\n\n";
	echo "Options:\n";
	echo "\t-v or --max_speed <speed_threshold>\n\n";
	echo "\t\tSpecify the maximum allowable velocity in m/s. Default: " . MAX_SPEED . "\n\n";
	echo "\t-a or --max_accel <acceleration_threshold>\n\n";
	echo "\t\tSpecify the maximum allowable acceleration in m/s^2. Default: " . MAX_ACCEL . "\n\n";
	echo "\t-g or --max_gap <gap_threshold>\n\n";
	echo "\t\tSpecify the maximum allowable time gap in data in seconds. Default: " . MAX_GAP . "\n\n";
	echo "\t-l or --logfile <logfile>\n\n";
	echo "\t\tSpecify a logfile for the qa report.\n\n";
    echo "\t-h or --help\n\n";
    echo "\t\tShow this help message.\n\n";
    echo "\n";
    
} // end function usage()

?>
