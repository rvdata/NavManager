#!/usr/bin/env php
<?php

define('INCLUDE_PATH', dirname(__FILE__) . '/../include/');
require INCLUDE_PATH . '/globals.inc.php';
require INCLUDE_PATH . '/getopts.php';
require INCLUDE_PATH . '/navdatalist.inc.php';
require INCLUDE_PATH . '/navbounds.inc.php';
require INCLUDE_PATH . '/navcopy.inc.php';
require INCLUDE_PATH . '/xmltools.inc.php';

ini_set('memory_limit','1024M');

//-------------------------GET OPTS --------------------------------//

$opts = getopts(
    array(
        'd' => array('switch' => array('d', 'data_directory'), 'type' => GETOPT_VAL),
        'o' => array('switch' => array('o', 'outfile'), 'type' => GETOPT_VAL),
        'f' => array('switch' => array('f', 'format'), 'type' => GETOPT_VAL),
        't' => array('switch' => array('t', 'time_range'), 'type' => GETOPT_VAL),
        'h' => array('switch' => array('h', 'help'), 'type' => GETOPT_SWITCH),
    ), $argv
);

// Help or missing required arguments
if ($opts['h']) {
    usage();
    exit(0);
}

$syntaxErr = "";

if ($opts['d'] == null) {
	$syntaxErr .=  "SYNTAX ERROR: Must specify a data directory [-d]\n";
} else {
	$pathNavigationRaw = trim($opts['d']);
}

if ($opts['f'] == null) {
	$syntaxErr .=  "SYNTAX ERROR: Must specify a data format [-f]\n";
} else {
	$r2rnav_file_format = trim($opts['f']);
}

if ($opts['o'] == null) {
	$syntaxErr .=  "SYNTAX ERROR: Must specify a destination for the r2rnav product [-o]\n";
} else {
	$navBestResPreQC = trim($opts['o']);
}

if ($opts['t']) {
	$datesInput = preg_split('/\//', trim($opts['t']));
	$dateStringUTCStart = $datesInput[0];
	$dateStringUTCEnd = $datesInput[1];
} else {
	$dateStringUTCStart = GPS_START_DATE;
	$dateStringUTCEnd = GPS_END_DATE;
}

if ($syntaxErr != "") {
	usage();
	echo $syntaxErr;
	exit(1);
}


	//----- Create time ordered list of data files within directory -----//

        echo "Running navdatalist() with:\n";
        echo "\tR2R_fileformat_id: ", $r2rnav_file_format, "\n";
        echo "\tData path:         ", $pathNavigationRaw, "\n";
		if ($opts['t']) {
			echo "\tStart:             ", $dateStringUTCStart, "\n";
			echo "\tEnd:               ", $dateStringUTCEnd, "\n";
		}

        list($filelistNavigationRaw, $report)
            = @navdatalist(
                $r2rnav_file_format,
                $dateStringUTCStart,
                $dateStringUTCEnd,
                $pathNavigationRaw
            );

        if ($report) {
            // Report on (1) Gaps between parseable files that last longer
            // than 12 hours, (2) Files that could not be parsed, and/or 
            // (3) Files that could be parsed, but do not fall within the
            // cruise start/end dates.
            foreach ($report as $statement) {
                echo $statement,"\n";
            }
        }

        echo "navdatalist(): Done.\n";
        echo "\n";

		if(!$filelistNavigationRaw) {
			exit(1);
		}

	//----- Convert primary raw navigation data into R2R standard format -----//

	echo "Running navcopy() with:\n";
	echo "\tR2R_fileformat_id: ", $r2rnav_file_format, "\n";
	echo "\tData path:         ", $pathNavigationRaw, "\n";
	echo "\tData files:\n";
	foreach ($filelistNavigationRaw as $fileRaw) {
		echo "\t\t", $fileRaw, "\n";
	}   
	echo "\tOutput file:       ", $navBestResPreQC, "\n";

	navcopy(
		$r2rnav_file_format, 
		$pathNavigationRaw, 
		$filelistNavigationRaw, 
		$navBestResPreQC
	);  

	echo "navcopy(): Done.\n";
	echo "\n";


/**
* Display how to use this program on the command-line
*/
function usage() 
{
    echo "\n";
    echo "Program: navcopy.php\nVersion: 0.9 \"Shark Bait\"\nAuthors: Aaron Sweeney, Chris Olson\n";
    echo "Rolling Deck To Repository (R2R): Navigation Manager\n";
    echo "Purpose: Convert raw nav data to the r2rnav standard format.\n";
    echo "\n";
    echo "Usage: navcopy.php -d <data_diretory> -f <format> -o <outifle> [-t <time range>] [-h]\n\n";
	echo "Required:\n";
	echo "\t-d <directory>\n";
	echo "\t\tPath to directory containing raw navigation data.\n";
	echo "\t-f <format>\n";
	echo "\t\tThe format of raw navigation files. (see navformat.php)\n";
	echo "\t-o <outfile>\n";
	echo "\t\tThe destination filename for the raw r2rnav file.\n\n";
	echo "Options\n";
	echo "\t-t <start_time/end_time>\n";
	echo "\t\tThe time interval which to sample at.\n";
	echo "\t\tFormat: [yyyy]-[mm]-[dd]T[hh]:[mm]:[ss]Z/[yyyy]-[mm]-[dd]T[hh]:[mm]:[ss]Z\n";
	echo "\t\tExample: -t 2014-03-01T00:00:00Z/2014-03-12T00:00:00Z\n";
	echo "\t-h or --help\n";
	echo "\t\tShow this help message.\n";

    echo "\n";
    
} // end function usage()

/**
 * Run a command in the linux shell.
 *
 * @param string $cmd_name The name of the command to run (for display only)
 * @param string $cmd_str  The actual command string to run (incl. command name)
 * 
 * @return string|int Returns result of command, if present and successful, else 0.
 */
function run_cmd($cmd_name, $cmd_str) 
{
    echo "$cmd_name: $cmd_str\n";
    exec($cmd_str, $output, $return_val);
    if ($return_val != 0) {
        foreach ($output as $line) {
            echo $line,"\n";
        }   
        echo "$cmd_name: Failed.\n";
        unset($output);
        echo "Stopped at ", gmdate("Y-m-d\TH:i:s\Z"), "\n";
        exit(1);
    } else {
        echo "$cmd_name: Done.\n";
        if (!empty($output)) {
            return $output[0];
        } else {
            return 0;
        }   
    }   
    
} // end function run_cmd();

?>
