#!/usr/bin/env php
<?php

define('INCLUDE_PATH', dirname(__FILE__) . '/../include/');
require INCLUDE_PATH . '/globals.inc.php';
require INCLUDE_PATH . '/getopts.php';
require INCLUDE_PATH . '/navdatalist.inc.php';
require INCLUDE_PATH . '/navqc.inc.php';


//-------------------------GET OPTS --------------------------------//

$opts = getopts(
    array(
        'i' => array('switch' => array('i', 'infile'), 'type' => GETOPT_VAL),
        'o' => array('switch' => array('o', 'outfile'), 'type' => GETOPT_VAL),
        'v' => array('switch' => array('v', 'max_speed'), 'type' => GETOPT_VAL),
        'a' => array('switch' => array('a', 'max_accel'), 'type' => GETOPT_VAL),
        'l' => array('switch' => array('l', 'log'), 'type' => GETOPT_VAL),
        'h' => array('switch' => array('h', 'help'), 'type' => GETOPT_SWITCH),
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
		echo "navqc(): Could not locate file: " . $navBestResPreQC . "\n";
		exit(1);
	}
}

if ($opts['o'] == null) {
    $syntaxErr .=  "SYNTAX ERROR: Must specify a destination for the QC'd r2rnav file [-o]\n";
} else {
	$navBestRes = trim($opts['o']);
}

if ($opts['l']) {
	$qclogfile = trim($opts['l']);
	$fqclog = fopen($qclogfile, 'w');
	if ($fqclog == null) {
		echo "navmanager: Could not open log file for writing: "
			. $qclogfile . "\n";
		exit(1);
	}   
} else {
	$fqclog = null;
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

if ($syntaxErr) {
    usage();
    echo $syntaxErr;
    exit(1);
}

//---------------------END GET OPTS --------------------------------//

// Get port start/end info from first/last line of file

		$if = fopen($navBestResPreQC, r);

		$headerPattern =  preg_quote(HEADER, '/');
		$firstLine = firstLine($if, $headerPattern);
		$dataRecFirst = preg_split('/\t/', $firstLine);
		$dateStringUTCStart = $dataRecFirst[0];

		$lastLine = lastLine($if, PHP_EOL);
		$dataRecLast = preg_split('/\t/', $lastLine);
		$dateStringUTCEnd = $dataRecLast[0];

		fclose($if);

// DEBUG stuff

$debug = 'TRUE';

if ($debug) {

	echo "Running navqc() with:\n";
	echo "\tInput file:              ", $navBestResPreQC, "\n";
	echo "\tStart:                   ", $dateStringUTCStart, "\n";
	echo "\tEnd:                     ", $dateStringUTCEnd, "\n";
	echo "\tSpeed threshold [m/s]:   ", $speedHoriMax, "\n";
	echo "\tAccel threshold [m/s^2]: ", $accelHoriMax, "\n";
	echo "\tOutput file:             ", $navBestRes, "\n";
	if ($fqclog != null) {
		echo "\tLog file:                ", $qclogfile, "\n";
	} else {
		echo "\tLog file:                none\n";
	}   
}

// END DEBUG stuff

//---------- Quality Control of navigation data ----------//

	navqc(
		$navBestResPreQC,
		$dateStringUTCStart,
		$dateStringUTCEnd,
		$speedHoriMax,
		$accelHoriMax,
		$navBestRes,
		$fqclog
	);  

	echo "navqc(): Done.\n";
	echo "\n";


/**
 * Display how to use this program on the command-line
 */
function usage() 
{
    echo "\n";
    echo "Program: navqc.php\nVersion: 0.9 \"Shark Bait\"\nAuthors: Aaron Sweeney, Chris Olson\n";
    echo "Rolling Deck To Repository (R2R): Navigation Manager\n";
    echo "Purpose: Flagg bad data from raw r2rnav standard files and\n";
	echo "	create a quality controlled full resolution nav product.\n";
    echo "\n";
    echo "Usage: navqc.php -i <infile> -o <outifle> [-l <logifle>] [-h]\n";
    echo "\n";

    echo "\n";
    echo "Program: navqa.php\nVersion: 0.9 \"Shark Bait\"\nAuthors: Aaron Sweeney, Chris Olson\n";
    echo "Rolling Deck To Repository (R2R): Navigation Manager\n";
    echo "Purpose: Quality assess navigation data in the\n";
    echo "  common r2rnav raw file format.\n";
    echo "\n";
    echo "Usage: navqc.php -i <infile> -o <outfile< [-v <speed_threshold>] [-a <acceleration_threshold>]\n";
    echo "\t[-g <gap_threshold>] [-l <logfile>] [-h]\n\n";
    echo "Required:\n";
    echo "\t-i <infile>\n";
    echo "\t\tThe r2rnav file to be quality assessed.\n\n";
    echo "\t-o <outfile>\n";
    echo "\t\tThe destination for the qualtiy controlled r2rnav product.\n\n";
    echo "Options:\n";
    echo "\t-v or --max_speed <speed_threshold>\n\n";
    echo "\t\tSpecify the maximum allowable velocity in m/s. Default: " . MAX_SPEED . "\n\n";
    echo "\t-a or --max_accel <acceleration_threshold>\n\n";
    echo "\t\tSpecify the maximum allowable acceleration in m/s^2. Default: " . MAX_ACCEL . "\n\n";
    echo "\t-l or --logfile <logfile>\n\n";
    echo "\t\tSpecify a logfile for the qa report.\n\n";
    echo "\t-h or --help\n\n";
    echo "\t\tShow this help message.\n\n";
    echo "\n";
    
} // end function usage()

?>
