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
        'l' => array('switch' => array('l', 'logfile'), 'type' => GETOPT_VAL),
        'j' => array('switch' => array('j', 'jsonfile'), 'type' => GETOPT_VAL),
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

if ($opts['j']) {
    $qajsonfile = trim($opts['j']);
    $fqajson = fopen($qajsonfile, 'w');
    if ($fqajson == null) {
        echo "navqa: Could not open json file for writing: "
            . $qajsonfile . "\n";
        exit(1);
    }
} else {
	$fqajson = null;
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

$debug = false;

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
    echo "\tLog file:                 ", ($opts['l']? $qalogfile: "none"), "\n";
}

// END DEBUG stuff

	$qaNavigationRaw = @navqa(
		$navBestResPreQC, $dateStringUTCStart, $dateStringUTCEnd,
		$speedHoriMax, $accelHoriMax, $gapThreshold, 
		$portLongitudeStart, $portLatitudeStart, 
		$portLongitudeEnd, $portLatitudeEnd,
		$fqalog
	);

    $output = '';
    $output = "Duration and range of values:\n" . $output;
    $output =  $output . "\tEpoch Interval [" . $qaNavigationRaw->duration_and_range_of_values->Epoch_Interval->uom . "]: " . $qaNavigationRaw->duration_and_range_of_values->Epoch_Interval->value . "\n";
    $output =  $output . "\tMaximum Altitude [" . $qaNavigationRaw->duration_and_range_of_values->Maximum_Altitude->uom . "]: " . $qaNavigationRaw->duration_and_range_of_values->Maximum_Altitude->value . "\n";
    $output =  $output . "\tMinimum Altitude [" . $qaNavigationRaw->duration_and_range_of_values->Minimum_Altitude->uom . "]: " . $qaNavigationRaw->duration_and_range_of_values->Minimum_Altitude->value . "\n";
    $output =  $output . "\tMaximum Horizontal Speed [" . $qaNavigationRaw->duration_and_range_of_values->Maximum_Horizontal_Speed->uom . "]: " . $qaNavigationRaw->duration_and_range_of_values->Maximum_Horizontal_Speed->value . "\n";
    $output =  $output . "\tMinimum Horizontal Speed [" . $qaNavigationRaw->duration_and_range_of_values->Minimum_Horizontal_Speed->uom . "]: " . $qaNavigationRaw->duration_and_range_of_values->Minimum_Horizontal_Speed->value . "\n";
    $output =  $output . "\tMaximum Horizontal Acceleration [" . $qaNavigationRaw->duration_and_range_of_values->Maximum_Horizontal_Acceleration->uom . "]: " . $qaNavigationRaw->duration_and_range_of_values->Maximum_Horizontal_Acceleration->value . "\n";
    $output =  $output . "\tMinimum Horizontal Acceleration [" . $qaNavigationRaw->duration_and_range_of_values->Minimum_Horizontal_Acceleration->uom . "]: " . $qaNavigationRaw->duration_and_range_of_values->Minimum_Horizontal_Acceleration->value . "\n";
    $output =  $output . "\tDistance from Port Start: " . $qaNavigationRaw->duration_and_range_of_values->distanceFromPortStart . "\n";
    $output =  $output . "\tDistance from Port End: " . $qaNavigationRaw->duration_and_range_of_values->distanceFromPortStart . "\n";
    $output =  $output . "\tFirst Epoch: " . $qaNavigationRaw->duration_and_range_of_values->First_Epoch . "\n";
    $output =  $output . "\tLast Epoch: " . $qaNavigationRaw->duration_and_range_of_values->Last_Epoch . "\n";
    $output =  $output . "\tPossible Number of Epochs with Observations: " . $qaNavigationRaw->duration_and_range_of_values->Possible_Number_of_Epochs_with_Observations . "\n";
    $output =  $output . "\tActual Number of Epochs with Observations: " . $qaNavigationRaw->duration_and_range_of_values->Actual_Number_of_Epochs_with_Observations . "\n";
    $output =  $output . "\tActual Countable Number of Epoch with Observations: " . $qaNavigationRaw->duration_and_range_of_values->Actual_Countable_Number_of_Epoch_with_Observations . "\n";
    $output =  $output . "\tAbsent Number of Epochs with Observations: " . $qaNavigationRaw->duration_and_range_of_values->Absent_Number_of_Epochs_with_Observations . "\n";
    $output =  $output . "\tFlagged Number of Epochs with Observations: " . $qaNavigationRaw->duration_and_range_of_values->Flagged_Number_of_Epochs_with_Observations . "\n";
    $output =  $output . "\tMaximum Number of Satellites: " . $qaNavigationRaw->duration_and_range_of_values->Maximum_Number_of_Satellites . "\n";
    $output =  $output . "\tMinimum Number of Satellites: " . $qaNavigationRaw->duration_and_range_of_values->Minimum_Number_of_Satellites . "\n";
    $output =  $output . "\tMaximum HDOP: " . $qaNavigationRaw->duration_and_range_of_values->Maximum_HDOP . "\n";
    $output =  $output . "\tMinimum HDOP: " . $qaNavigationRaw->duration_and_range_of_values->Minimum_HDOP . "\n";
    $output =  $output . "\n";
    $output =  $output . "Quality Assessment:\n";
    $output =  $output . "\tLongest Epoch Gap [" . $qaNavigationRaw->quality_assessment->longest_epoch_gap->uom . "]: " . $qaNavigationRaw->quality_assessment->longest_epoch_gap->value . "\n";
    $output =  $output . "\tNumber of Gaps Longer than Threshold: " . $qaNavigationRaw->quality_assessment->number_of_gaps_longer_than_threshold . "\n";
    $output =  $output . "\tNumber of Epochs Out of Sequence: " . $qaNavigationRaw->quality_assessment->number_of_epochs_out_of_sequence . "\n";
    $output =  $output . "\tNumber of Epochs with Bad GPS Quality Indicator: " . $qaNavigationRaw->quality_assessment->number_of_epochs_with_bad_gps_quality_indicator . "\n";
    $output =  $output . "\tNumber of Horizontal Speeds Exceeding Threshold: " . $qaNavigationRaw->quality_assessment->number_of_horizontal_speeds_exceeding_threshold . "\n";
    $output =  $output . "\tNumber of Horizontal Accelerations Exceeding Threshold: " . $qaNavigationRaw->quality_assessment->number_of_horizontal_accelerations_exceeding_threshold . "\n";
    $output =  $output . "\tPercent Completeness: " . $qaNavigationRaw->quality_assessment->percent_completeness . "\n";

	if ($fqalog != null) {    
        fwrite($fqalog, $output);
		fclose($fqalog);
	} else {
		echo $output;
	}

	if ($fqajson != null) {    
        fwrite($fqajson, json_encode($qaNavigationRaw));
		fclose($fqajson);
	}

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
	echo "\t-j or --jsonfile <jsonfile>\n\n";
	echo "\t\tSpecify a json file for the qa report.\n\n";
    echo "\t-h or --help\n\n";
    echo "\t\tShow this help message.\n\n";
    echo "\n";
    
} // end function usage()

?>
