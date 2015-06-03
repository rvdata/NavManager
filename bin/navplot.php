#!/usr/bin/env php
<?php 
/**
 * Control Point Navigation Plotter
 * Use Generic Mapping Tools (GMT) to create postscript
 * plot of cruise track with coastlines.
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
require INCLUDE_PATH . 'globals.inc.php';
require INCLUDE_PATH . 'getopts.php';
require INCLUDE_PATH . 'navbounds.inc.php';
date_default_timezone_set('UTC');

//------------ Function Definitions -----------//
/**
 * Display how to use this program on the command-line
 */
function usage() 
{
    echo "\n";
    echo "Program navplot.php Version 0.9 \"Shark Bait\" by Aaron Sweeney\n";
    echo "Rolling Deck To Repository (R2R): Control Point Navigation Plotter\n";
    echo "Purpose: Use Generic Mapping Tools (GMT) to create postscript\n";
    echo "         plot of cruise track with coastlines.\n";
    echo "\n";
    echo "Usage: navplot.php -i <infile> [-h]\n";
    echo "\n";
    echo "Required:\n";
    echo "\t-i or --infile <infile>\n";
    echo "\t\tInput r2rnav file to be plotted\n";
    echo "\n";
    echo "Options:\n";
    echo "\t-h or --help\t\tShow this help.\n";
    echo "\n";
    echo "Output file: <infile>_track.ps\n";
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
//------------ End Function Definitions ----------//

//------------ Begin Main Program -----------//

//----------- Main ------------//
$opts = getopts(
    array(
        'i' => array('switch' => array('i','infile'), 'type' => GETOPT_VAL),
        'h' => array('switch' => array('h','help'), 'type' => GETOPT_SWITCH),
    ), $argv
);

if (!$opts['i']) {
    $syntaxErr .=  "SYNTAX ERROR: Must specify a  r2rnav file to plot [-i]\n";
} else {
    $navControl = trim($opts['i']);
}

if ($opts['h']) {
    usage();
    exit(1);
}

if ($syntaxErr) {
    usage();
    echo $syntaxErr;
    exit(1);
}

// Output username, machine name, and current UTC date/time:
echo "Run by user ", exec('whoami'), " on ", php_uname('n'), " at ", 
    gmdate("Y-m-d\TH:i:s\Z"), "\n";
// Output OS specifics:
echo "OS: ", php_uname('a') ,"\n";
// Output PHP version:
echo "PHP version: ", PHP_VERSION, "\n";

$navPlot    = $navControl . "_track.ps";

//---------- Determine geographic boundaries of control point navigation ----------//
echo "Running navbounds() with:\n";
echo "\tInput file:              ",$navControl,"\n";

$bounds = navbounds($navControl);

if ($bounds === false) {
    exit(1);
}

echo "navbounds(): Done.\n";
echo "\n";
    
$northBoundLatitude = $bounds->northBoundLatitude;
$southBoundLatitude = $bounds->southBoundLatitude;
$westBoundLongitude = $bounds->westBoundLongitude;
$eastBoundLongitude = $bounds->eastBoundLongitude;

echo "northBoundLatitude: $northBoundLatitude\n";
echo "southBoundLatitude: $southBoundLatitude\n";
echo "westBoundLongitude: $westBoundLongitude\n";
echo "eastBoundLongitude: $eastBoundLongitude\n";

if (!(($westBoundLongitude>=0.0 && $eastBoundLongitude>=0.0) 
    || ($westBoundLongitude<=0.0 && $eastBoundLongitude<=0.0) 
    || ($westBoundLongitude<=0.0 && $eastBoundLongitude>=0.0)) 
) {
    // Transform [-180,180] longitudes to [0,360] for GMT plotting:
    while ($westBoundLongitude < 0.0) $westBoundLongitude += 360.0;
    while ($eastBoundLongitude < 0.0) $eastBoundLongitude += 360.0;
}


//------- SET VARIABLES -------
$width = "6i+";
$coastres = "f";  // Coast resolution: c=crude, l=low, i=intermediate, h=high, f=full
$rivertype = "a"; // Draw rivers: a=all rivers and canals
$lonMargin = 1;   // [degree]
$latMargin = 1;   // [degree]
$west  = $westBoundLongitude - $lonMargin;
$east  = $eastBoundLongitude + $lonMargin;
$south = $southBoundLatitude - $latMargin;
$north = $northBoundLatitude + $latMargin;

//$west = -58.5;
//$east = -57.3;
//$south = 33.35;
//$north = 34.25;

if ($north < 70) {
    $proj = "m";     // Transverse Mercator Projection
    $width = "6i+";  // longest axis is 6 inches
} else {
    $proj = "s";  // Polar Stereographic Projection
    $width = "0/90/3i/$south";  // Centered on North Pole, map radius = 3 inches to $south lat.
    $north = 90;
    $west = -180;
    $east = 180;
}

$xinfo = ($east - $west)/4 ;
if ($xinfo < 0) {
    $xinfo = -$xinfo;
}
if ($xinfo == 0) {
    $xinfo = 0.5;
}
$yinfo = ($north - $south)/4 ;
if ($yinfo == 0) {
    $yinfo = 0.5;
}

$fontsize = 8;  // [points]
$textangle = 45; // [degrees CCW from horizontal]
$fontno = 0;
$justify = "BL";

// Map scale bar settings:
$dtr = M_PI/180.0;  // degrees to radians
$km_per_degree = 111; // kilometers per arc-degree
$lat0 = $south + abs($north - $south)/10;
$slat = ($north - $south)/2 ;
$map_width = abs($east - $west)*cos($slat*$dtr)*$km_per_degree;  // map width [km]
echo "map width [km]: $map_width\n";
// Set length of map scale in tens of km:
if ($map_width < 1) {
    $length = 0.1;
} else {
    if ($map_width < 10) {
        $length = 1;
    } else {
        if ($map_width < 100) {
            $length = 10;
        } else {
            if ($map_width < 1000) {
                $length = 100;
            } else {
                $length = 1000;
            }
        }
    }
}
$lon0 = $west + $length/$km_per_degree;
//$length = ( $map_width > 1 ) ? intval($map_width)/10 : 0.1 ; // length of scale in [km]

//------- END SET VARIABLES ---

// Define nice priority level:
$niceness = 20;
$beNice = "nice -n $niceness ";

$cmd_str = "gmtset PAPER_MEDIA letter PLOT_DEGREE_FORMAT D ANNOT_FONT_SIZE_PRIMARY 8";
run_cmd("gmtset", $beNice . $cmd_str); 

#echo "pscoast -J${proj}${width} -R$west/$east/$south/$north -B${xinfo}a${yinfo}WSen -D$coastres -I$rivertype -A1000 -W2 -Yc -Xc -Lf$lon0/$lat0/$slat/$length+l -P -K -V > $navPlot\n";

$cmd_str = "pscoast -J${proj}${width} -R$west/$east/$south/$north -B${xinfo}a${yinfo}WSen -D$coastres -I$rivertype -A1000 -W2 -Yc -Xc -Lf$lon0/$lat0/$slat/$length+l -P -K -S132/112/255 -G85/107/47 -V> $navPlot";
run_cmd("pscoast", $beNice . $cmd_str);

#$cmd_str = "awk 'NR>3 {print $2, $3}' $navControl | psxy -J -R -Wred -Sc5p -O -K -V >> $navPlot";
#run_cmd("psxy (symbols)", $beNice . $cmd_str);

$cmd_str = "awk 'NR>3 {print $2, $3}' $navControl | psxy -J -R -W1.5p,red -O -K -V >> $navPlot";
run_cmd("psxy (line)", $beNice . $cmd_str);

#$cmd_str = "awk 'NR>3 && NR%10==0 {print $2, $3, " . $fontsize . ", " . $textangle . ", " . $fontno . ", \"" . $justify . "\", $1}' $navControl | pstext -J -R -Gred -O -V >> $navPlot";
#run_cmd("pstext", $beNice . $cmd_str);

// Successful execution:
exit(0);
?>
