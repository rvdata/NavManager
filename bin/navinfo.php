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
        'l' => array('switch' => array('l', 'logfile'), 'type' => GETOPT_VAL),
        'j' => array('switch' => array('j', 'jsonfile'), 'type' => GETOPT_VAL),
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

if ($opts['j']) {
    $qajsonfile = trim($opts['j']);
    $fqajson = fopen($qajsonfile, 'w');
    if ($fqajson == null) {
        echo "navinfo: Could not open json file for writing: "
            . $qajsonfile . "\n";
        exit(1);
    }
} else {
	$fqajson = null;
}

if ($syntaxErr != "") {
    usage();
    echo $syntaxErr;
    exit(1);
}


//---------------------END GET OPTS --------------------------------//


// Get port start/end info from first/last line of file


// DEBUG stuff

$debug = false;

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

    $startStop = (object) array('startTimestamp' => $dateStringUTCStart, 'endTimestamp' => $dateStringUTCStart, 'startLatLon' => array($portLatitudeStart,$portLongitudeStart), 'endLatLon' => array($portLatitudeEnd, $portLongitudeEnd));
    
    $outputObj = (object) array('startStop' => $startStop, 'boundingBox' => $bounds);

    $output = '';
    $output = $output . "Navigation Start/End Info:\n";
    $output = $output . "\tStart Date:\t" . $outputObj->startStop->startTimestamp . "\n"; 
    $output = $output . "\tEnd Date:\t" . $outputObj->startStop->endTimestamp . "\n"; 
    $output = $output . "\tStart Lat/Lon:\t[" . $outputObj->startStop->startLatLon[0] . "," . $outputObj->startStop->startLatLon[1] . "]\n"; 
    $output = $output . "\tEnd Lat/Lon:\t[" . $outputObj->startStop->endLatLon[0] . "," . $outputObj->startStop->endLatLon[1] . "]\n"; 
    $output = $output . "\n";
    $output = $output . "Navigation Bounding Box Info:\n";
    $output = $output . "\tMinimum Longitude:\t" . $outputObj->boundingBox->westBoundLongitude . "\n";
    $output = $output . "\tMaximum Longitude:\t" . $outputObj->boundingBox->eastBoundLongitude . "\n";
    $output = $output . "\tMinimum Latitude:\t" . $outputObj->boundingBox->southBoundLatitude . "\n";
    $output = $output . "\tMaximum Latitude:\t" . $outputObj->boundingBox->northBoundLatitude . "\n";
	
	if ($fqalog != null) {
		fwrite($fqalog, $output);
		fclose($fqalog);
	} else {
        echo $output;
	}

    if ($fqajson != null) {
		fwrite($fqajson, json_encode($outputObj));
		fclose($fqajson);
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
    echo "Usage: navinfo.php -i <infile> [-l <logfile>] [-j <jsonfile>] [-h]\n";
    echo "\n";
    
} // end function usage()

?>
