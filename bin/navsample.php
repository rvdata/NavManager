#!/usr/bin/env php
<?php

define('INCLUDE_PATH', dirname(__FILE__) . '/../include/');
require INCLUDE_PATH . '/getopts.php';
require INCLUDE_PATH . '/navsample.inc.php';


//-------------------------GET OPTS --------------------------------//

$opts = getopts(
    array(
        'i' => array('switch' => array('i', 'infile'), 'type' => GETOPT_VAL),
        'o' => array('switch' => array('o', 'outfile'), 'type' => GETOPT_VAL),
        't' => array('switch' => array('t', 'time_interval'), 'type' => GETOPT_VAL),
        'c' => array('switch' => array('c', 'control'), 'type' => GETOPT_SWITCH),
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
	$navBestRes = trim($opts['i']);
	if (!file_exists($navBestRes)) {
		echo "navsample(): Could not locate file: " . $navBestRes . "\n";
		exit(1);
	}
}

if ($opts['o'] == null) {
	$syntaxErr .=  "SYNTAX ERROR: Must specify a destination for the sample product [-o]\n";
} else {
	$navSampled = trim($opts['o']);
}

// One or the other - time interval downsampled or control
if ($opts['t'] == null && $opts['c'] == null || $opts['t'] && $opts['c']) {
	$syntaxErr .=  "SYNTAX ERROR: Must specify either [-c] or [-t]\n";
}

if ($syntaxErr != "") {
	usage();
	echo $syntaxErr;
	exit(1);
}


	//---------- Subsample navigation at timeInterval ------------//

if ($timeInterval = trim($opts['t'])) {

	echo "Running navsample() with:\n";
	echo "\tInput file:          ", $navBestRes, "\n";
	echo "\tOutput file:         ", $navSampled, "\n";
	echo "\tSample interval [s]: ", $timeInterval, "\n";

	@navsample($navBestRes, $timeInterval, $navSampled);

	echo "navsample(): Done.\n";
	echo "\n";
}

	//---------- END Subsample navigation timeInterval ----------//

if ($opts['c']) {
	//----- Create abstracted navigation file, suitable for mapping -----//

	$cmd_str = "java -classpath " . INCLUDE_PATH . "/NavControl navsimplifier $navBestRes $navSampled"; 
	run_cmd("navsimplifier", $cmd_str);

}

	//----- END Create abstracted navigation file, suitable for mapping -----//

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
