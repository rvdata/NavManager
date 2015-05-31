#!/usr/bin/env php
<?php

define('INCLUDE_PATH', dirname(__FILE__) . '/../include/');
require INCLUDE_PATH . '/getopts.php';
require INCLUDE_PATH . '/navsogcog.inc.php';


//-------------------------GET OPTS --------------------------------//

$opts = getopts(
    array(
        'i' => array('switch' => array('i', 'infile'), 'type' => GETOPT_VAL),
        'o' => array('switch' => array('o', 'outfile'), 'type' => GETOPT_VAL),
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
	$navBestRes = trim($opts['i']);
	if (!file_exists($navBestRes)) {
		echo "navqc(): Could not locate file: " . $navBestRes . "\n";
		exit(1);
	}
}

#if ($opts['o'] == null) {
#    $syntaxErr .=  "SYNTAX ERROR: Must specify a destination for the full res product [-o]\n";
#    exit(1);
#} else {
#    $navBestRes = trim($opts['o']);
#}

if ($opts['l']) {
	$dplogfile = trim($opts['l']);
	$fdplog = fopen($dplogfile, 'w');
	if ($fdplog == null) {
		echo "navmanager: Could not open log file for writing: "
			. $dplogfile . "\n";
		exit(1);
	}   
} else {
	$fdplog = null;
}

$navBestResSOGCOG = "navSOGCOG.tmp.r2rnav";

if ($syntaxErr) {
    usage();
    echo $syntaxErr;
    exit(1);
}

//---------------------END GET OPTS --------------------------------//

		//----- Calculate instantaneous Speed-Over-Ground (SOG) and -----// 
		//----- Course-Over-Ground (COG) from QC'd navigation data  -----//
		echo "Running navsogcog() with:\n";
		echo "\tInput file:              ", $navBestRes, "\n";
		echo "\tOutput file (temporary): ", $navBestResSOGCOG, "\n";
		if ($fdplog != null) {
			echo "\tLog file:                ", $dplogfile, "\n";
		} else {
			echo "\tLog file:                none\n";
		}   

		navsogcog($navBestRes, $navBestResSOGCOG, $fdplog);

		// Rename results file with SOG and COG to QC'd product file:
		if (!@unlink($navBestRes)) {
			echo "Unable to overwrite file ", $navBestRes,
				"--likely to be a permissions problem.\n";
		} else {
			if (!@rename($navBestResSOGCOG, $navBestRes)) {
				echo "File ", $navBestResSOGCOG, 
					" could not be moved while overwritting--",
					"likely a permissions problem.\n";
			} else {
				echo "File ", $navBestResSOGCOG,
					" successfully overwritten to file ", $navBestRes, ".\n";
			}   
		}   

		echo "navsogcog(): Done.\n";
		echo "\n";

/**
 * Display how to use this program on the command-line
 */
function usage() 
{
    echo "\n";
    echo "Program: navsogcog.php\nVersion: 0.9 \"Shark Bait\"\nAuthors: Aaron Sweeney, Chris Olson\n";
    echo "Rolling Deck To Repository (R2R): Navigation Manager\n";
    echo "Purpose: Insert speed over ground (SOG) and course over ground (COG)\n";
	echo "	values into full resolution r2rnav files.\n";
    echo "\n";
    echo "Usage: navsogcog.php -i <infile> -o <outifle> [-l <logifle>] [-h]\n";
    echo "\n";
    
} // end function usage()

?>
