#!/usr/bin/env php
<?php

define('INCLUDE_PATH', dirname(__FILE__) . '/../include/');
require INCLUDE_PATH . '/getopts.php';
require INCLUDE_PATH . '/navdatalist.inc.php';
require INCLUDE_PATH . '/navbounds.inc.php';
require INCLUDE_PATH . '/navcopy.inc.php';
require INCLUDE_PATH . '/xmltools.inc.php';


//-------------------------GET OPTS --------------------------------//

$opts = getopts(
    array(
        'd' => array('switch' => array('d', 'data_directory'), 'type' => GETOPT_VAL),
        'o' => array('switch' => array('o', 'outfile'), 'type' => GETOPT_VAL),
        'f' => array('switch' => array('f', 'format'), 'type' => GETOPT_VAL),
        'h' => array('switch' => array('h', 'help'), 'type' => GETOPT_SWITCH),
    ), $argv
);

// Help or missing required arguments
if ($opts['h']) {
    usage();
    exit(0);
}

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

if ($syntaxErr) {
	usage();
	echo $syntaxErr;
	exit(1);
}


	//----- Create time ordered list of data files within directory -----//

	$dateStringUTCStart = NULL;
	$dateStringUTCEnd = NULL;

        echo "Running navdatalist() with:\n";
        echo "\tR2R_fileformat_id: ", $r2rnav_file_format, "\n";
		if (!$dateStringUTCStart) {
			$dateStringUTCStart = '1994-03-01T00:00:00Z';
		} else {
			echo "\tStart:             ", $dateStringUTCStart, "\n";
		}
		if (!$dateStringUTCEnd) {
			$dateStringUTCEnd = '2024-12-31T00:00:00Z';
		} else {
			echo "\tEnd:               ", $dateStringUTCEnd, "\n";
		}
        echo "\tData path:         ", $pathNavigationRaw, "\n";

        list($filelistNavigationRaw, $report)
            = navdatalist(
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

        // navdatalist() returns false if no data between cruise dates:
        if (!$filelistNavigationRaw) {
            $metadataNavigationRaw = new stdClass();
            $metadataNavigationRaw->creation_date = gmdate("Y-m-d\TH:i:s\Z");
            $metadataNavigationRaw->cruiseid = $cruiseid;
            $metadataNavigationRaw->vessel = $info->vessel;
            $metadataNavigationRaw->device = $info->device;
            $metadataNavigationRaw->fileset_inventory = $filelistNavigationRaw;
            $metadataNavigationRaw->processing_parameters
                = $cfg->processing_parameters;
            $metadataNavigationRaw->duration_and_range_of_values = null;
            $metadataNavigationRaw->quality_assessment = null;
            xml_write_special(
                $metadataNavigationRaw,
                $navRawQuality,
                $navRawQualityTemplate,
                $navRawQualityPreliminary
            );
            echo "Navigation raw data fileset metadata written to: ",
                $navRawQuality, "\n";
            exit(1);
        }

        echo "navdatalist(): Done.\n";
        echo "\n";

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
    echo "Program: navqc.php\nVersion: 0.9 \"Shark Bait\"\nAuthors: Aaron Sweeney, Chris Olson\n";
    echo "Rolling Deck To Repository (R2R): Navigation Manager\n";
    echo "Purpose: Downsample an r2rnav navigation file.\n";
    echo "\n";
    echo "Usage: navsample.php -i <infile> -o <outifle> [-c | -t <sample interval>] [-l <logifle>] [-h]\n\n";
	echo "\t-i <infile>\t The full resolutions r2rnav file to sample from.\n";
	echo "\t-o <outfile>\t The destination filename for the sampled product.\n";
	echo "\t-t <time>\t The time interval which to sample at.\n";
	echo "\t-c\t\tProduce a control file, with only enough navigation points.\n";
	echo "\t\t\tto plot a trackline on a map.\n";
	echo "\t-h\t\tShow this help message.\n";

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
