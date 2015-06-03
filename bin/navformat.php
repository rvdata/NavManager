#!/usr/bin/env php
<?php
/**
 * List the supported input navigation rawdata formats
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
define('DOC_PATH', dirname(__FILE__) . '/../doc/');
require INCLUDE_PATH . 'getopts.php';
date_default_timezone_set('UTC');

//------------ Function Definitions -----------//
/**
 * Display how to use this program on the command-line
 */
function usage() 
{
    echo "\n";
    echo "Program navformat.php Version 0.9 \"Shark Bait\" by Aaron Sweeney\n";
    echo "Rolling Deck To Repository (R2R): List of Supported Input Navigation "
        . "Formats\n";
    echo "\n";
    echo "Purpose: Display list of input navigation formats that R2R tries to\n"; 
    echo "accommodate.\n";
    echo "\n";
    echo "Usage: navformat.php -f <format> [-h]\n";
    echo "\n";
    echo "Required:\n";
    echo "\t-f or --format <format>\n";
    echo "\t\tFormat specifier.  (See list of supported formats below.)\n";
    echo "\n";
    echo "Options:\n";
    echo "\t-h or --help\t\tShow this help.\n";
    echo "\n";
    echo "Input format specifier:\n";
    echo "\tnav1\traw NMEA: ZDA + GGA\n";
    echo "\tnav2\tDAS: NOAA Shipboard Computer System (SCS): external clock + GGA\n";
    echo "\tnav3\tDAS: NOAA Shipboard Computer System (SCS): external clock + "
        . "GLL only (pending)\n";
    echo "\tnav4\tDAS: WHOI Calliope (Atlantis, Knorr)\n";
    echo "\tnav5\tDAS: WHOI Calliope (Oceanus)\n";
    echo "\tnav6\tDAS: UDel Surface Mapping System (SMS)\n";
    echo "\tnav7\tDAS: UH-specific (KOK) (pending)\n";
    echo "\tnav8\tDAS: UH-specific (KM) [Applanix POS/MV-320 V4]\n";
    echo "\tnav9\tDAS: SIO-specific: satdata\n";
    echo "\tnav10\tDAS: UW-specific\n";
    echo "\tnav11\tDAS: UMiami-specific [Applanix POS/MV-320]\n";
    echo "\tnav12\tDAS: device id + external clock + GGA and ZDA\n";
    echo "\tnav13\tDAS: LUMCON Multiple Instrument Data Aquisition System (MIDAS)\n";
    echo "\tnav14\tDAS: MLML Underway Data Aquisition System (UDAS)\n";
    echo "\tnav15\tDAS: OSU csv\n";
    echo "\n";
    echo "\tuhdas\t\tDAS: University of Hawaii Data Acquisition System (for ADCP)\n";
    echo "\t\t\t[raw NMEA: UNIXD + GGA]\n";
    echo "\tall\t\tDisplay all supported input navigation formats.\n";
    echo "\n";
    echo "\tr2rnav\t\tShow R2R navigation standard products format.\n";
    echo "\n";
    echo "Output written to STDOUT.\n";
    
} // end function usage()
//------------ End Function Definitions ----------//

//------------ Begin Main Program -----------//

//----------- Main ------------//
$opts = getopts(
    array(
        'f' => array('switch' => array('f', 'format'), 'type' => GETOPT_VAL),
        'h' => array('switch' => array('h', 'help'), 'type' => GETOPT_SWITCH),
    ), $argv
);

if ($opts['h'] || $opts['f'] == null) {
    usage();
    exit(0);
}

$inputFormatSpec = trim($opts['F']); // e.g. "nav1"

//------------ Begin Main Program ----------//

$docpath = DOC_PATH . 'fileformat/';

if (is_dir("$docpath")) {
  
    switch ($inputFormatSpec) {	

    case 'all':
        for ($inx = 1; $inx<=15; $inx++) {
            $filename = "format-nav" . $inx . ".txt";
            if (file_exists($docpath . "/" . $filename)) {
                readfile($docpath . "/" . $filename);
                echo "\n";
                echo "----------------------------------------------\n";
            }
        }
        $filename = "format-r2rnav.txt";
        if (file_exists($docpath . "/" . $filename)) {
            readfile($docpath . "/" . $filename);
            echo "\n";
            echo "----------------------------------------------\n";
        }
        break;
        
    case "uhdas":
        $filename = "format-uhdas.txt";
        if (file_exists($docpath . "/" . $filename)) {
            readfile($docpath . "/" . $filename);
            echo "\n";
        }
        break;
        
    default:
        $filename = "format-" . $inputFormatSpec . ".txt";
        if (file_exists($docpath . "/" . $filename)) {
            readfile($docpath . "/" . $filename);
            echo "\n";
        } else {
            echo "Unrecognized format.\n";
        }
        break;
        
    } // end switch ($inputFormatSpec)
    
} else {
    
    echo "Documentation directory not found: $docpath\n";
    exit(1);
    
} // end if is_dir($docpath)

// Successful execution:
exit(0);
//------------ End Main Program -----------//
?>
