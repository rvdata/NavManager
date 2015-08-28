#!/usr/bin/env php 
<?php
/**
 * Generate the navigation processing configuration file(s)
 *
 * PHP version 5
 *
 * @category R2R_Products
 * @package  R2R_Nav
 * @author   Aaron Sweeney <asweeney@ucsd.edu>
 * @license  http://opensource.org/licenses/GPL-3.0 GNU General Public License
 * @link     http://www.rvdata.us
 */
define('INCLUDE_PATH', dirname(__FILE__) . '/../include/');
require INCLUDE_PATH . 'getopts.php';
require INCLUDE_PATH . 'getmeta.inc.php';
date_default_timezone_set('UTC');

//------------ Function Definitions -----------//
/**
 * Display how to use this program on the command-line
 */
function usage()
{
    echo "\n";
    echo "Program navconfig.php Version 0.9 \"Shark Bait\" by Aaron Sweeney\n";
    echo "Rolling Deck To Repository (R2R): Navigation Processing Configuration\n";
    echo "Purpose: Generate navigation processing configuration files.\n";
    echo "\n";
    echo "Usage: navconfig.php -c <cruiseid> [-h]\n";
    echo "\n";
    echo "Required:\n";
    echo "\t-c or --cruiseid <cruiseid>\n";
    echo "\t\tCruise ID\n";
    echo "\n";
    echo "Options:\n";
    echo "\n";
    echo "\t-h or --help\t\tShow this help.\n";
    echo "\n";
    echo "Example:\n";
    echo "\tnavconfig.php -c MV0901\n";
    echo "\n";
    
} // end function usage()
//------------ End Function Definitions ----------//

//------------ Begin Main Program -----------//

//----------- Main ------------//
$opts = getopts(
    array(
        'c' => array('switch' => array('c', 'cruiseid'), 'type' => GETOPT_VAL),
        'm' => array('switch' => array('m', 'meta'), 'type' => GETOPT_VAL),
        'h' => array('switch' => array('h', 'help'), 'type' => GETOPT_SWITCH),
    ), $argv
);

if ($opts['h'] || $opts['c'] == null ) {
    usage();
    exit(0);
}

$cruiseid = trim($opts['c']);

// Output username, machine name, and current UTC date/time:
echo "Run by user ", exec('whoami'), " on ", php_uname('n'), " at ", 
    gmdate("Y-m-d\TH:i:s\Z"), "\n";
// Output OS specifics:
echo "OS: ", php_uname('a') ,"\n";
// Output PHP version:
echo "PHP version: ", phpversion(), "\n";

//---------- Get processing parameters ----------//

// Configuration file does not exist.  Create one for each GNSS
// on the vessel, including the fileset used as the primary 
// navigation source (nav_source = 1).
$cfgfilelist = @make_cfg($cruiseid);  

echo "navconfig: Created the following configuration files: \n";

foreach ($cfgfilelist as $cfgfile) {
    echo $cfgfile,"\n";
}

//---------- Output the UTC date/time when this process finished ----------//
echo "Finished at ", gmdate("Y-m-d\TH:i:s\Z"), "\n";

// End of successful execution:
exit(0);
//------------ End Main Program -----------//
?>
