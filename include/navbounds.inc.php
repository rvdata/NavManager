<?php
/**
 * Define function to determine the geographic WESN extent of the given
 * input file in R2R navigation standard product format
 *
 * PHP version 5
 *
 * @category R2R_Products
 * @package  R2R_Nav
 * @author   Aaron Sweeney <asweeney@ucsd.edu>
 * @license  http://opensource.org/licenses/GPL-3.0 GNU General Public License
 * @link     http://www.rvdata.us
 */
require_once 'flags.inc.php';

class GeographicExtent
{
    public $westBoundLongitude, $eastBoundLongitude;
    public $southBoundLatitude, $northBoundLatitude;
}

/**
 * Determine the geographic WESN extent of the given input file
 *
 * Apapted from Generic Mapping Tools program minmax.c, Version 4.1.  
 * For reference see:
 * 
 *  Wessel, P., W. H. F. Smith, R. Scharroo, J. F. Luis, and F. Wobbe, 
 *   Generic Mapping Tools: Improved version released, EOS Trans. AGU, 
 *   94, 409-410, 2013.
 *  Wessel, P. and W. H. F. Smith, New, improved version of the Generic 
 *   Mapping Tools released, EOS Trans. AGU, 79, 579, 1998.  
 *  Wessel, P. and W. H. F. Smith, New version of the Generic Mapping 
 *   Tools released, EOS Trans. AGU, 76, 329, 1995.
 *  Wessel, P. and W. H. F. Smith, Free software helps map and display 
 *   data, EOS Trans. AGU, 72, 441, 1991.
 *
 * @param string $infile Input filename (R2R navigation standard format)
 *
 * @return object Returns a GeographicExtent object
 */ 
function navbounds($infile)
{
    $quad = array(false, false, false, false);
    
    $lonmin = +1e9;
    $lonmax = -1e9;
    $latmin = +1e9;
    $latmax = -1e9;
    
    $xmin1 =  360.0;
    $xmin2 =  360.0;
    $xmax1 = -360.0;
    $xmax2 = -360.0;
    
    if (!($fin = @fopen($infile, 'r'))) {
        printf("navbounds.php: Cannot open file %s\n", $infile);
        return false;
    }
    
    while (!feof($fin)) {
        
        $line = trim(fgets($fin));
        if ($line != "") {
            
            // Skip flagged data records and header records:
            if ($line[0] != QCFLAG && !strstr($line, HEADER)) {
                
                $dataRec = preg_split('/\t/', $line);
                
                $lon = (float) $dataRec[1];  // Longitude [-180,180] decimal degrees
                $lat = (float) $dataRec[2];  // Latitude [-90,90] decimal degrees

                if ($lon === 0.0 && $lat === 0.0) {
                    continue;
                }
                
                // Decode all fields and update minmax arrays:
                // Start off with everything in 0-360 range
                while ($lon < 0.0) $lon += 360.0;
                $xmin1 = min($lon, $xmin1);
                $xmax1 = max($lon, $xmax1);
                $quad_no = (int) floor($lon/90.0);   // Yields quadrants 0-3
                if ($quad_no == 4) $quad_no = 0;	 // When in[i] == 360.0 
                $quad[$quad_no] = true;
                while ($lon > 180.0) $lon -= 360.0;  // Switch to [-180,180] range
                $xmin2 = min($lon, $xmin2);
                $xmax2 = max($lon, $xmax2);
                
                if ($lat < $latmin) $latmin = $lat;
                if ($lat > $latmax) $latmax = $lat;
                
            } // end if ($line[0] != QCFLAG && !strstr($line, HEADER))
            
        } // end if ($line != "")
        
    } // end loop over file
    fclose($fin);

    // How many quadrants had data?
    $n_quad = (int)($quad[0] + $quad[1] + $quad[2] + $quad[3]);
    // Longitudes on either side of Greenwich only, must use [-180,180] notation
    if ($quad[0] && $quad[3]) {	 
        $lonmin = $xmin2;
        $lonmax = $xmax2;
        // Longitudes on either side of the date line, must user 0/360 notation
    } elseif ($quad[1] && $quad[2]) {  
        $lonmin = $xmin1;
        $lonmax = $xmax1;
        // Funny quadrant gap, pick shortest longitude extent
    } elseif ($n_quad == 2 && (($quad[0] && $quad[2]) || ($quad[1] && $quad[3]))) {  
        if (($xmax1 - $xmin1) < ($xmax2 - $xmin2)) { // [0,360] more compact
            $lonmin = $xmin1;
            $lonmax = $xmax1;
        } else {  // [-180,180] more compact 
            $lonmin = $xmin2;
            $lonmax = $xmax2;
        }
    } else {  // Either will do, use default settings
        $lonmin = $xmin1;
        $lonmax = $xmax1;
    }
    if ($lonmin > $lonmax) $lonmin -= 360.0;
    if ($lonmin < 0.0 && $lonmax < 0.0) {
        $lonmin += 360.0; 
        $lonmax += 360.0;
    }
    
    // Convert longitude bounds back into [-180,180], if not already:
    while ($lonmin > 180.0) $lonmin -= 360.0;
    while ($lonmax > 180.0) $lonmax -= 360.0;
    
    $bounds = new GeographicExtent();
    $bounds->westBoundLongitude = $lonmin;
    $bounds->eastBoundLongitude = $lonmax;
    $bounds->southBoundLatitude = $latmin;
    $bounds->northBoundLatitude = $latmax;
    
    return $bounds;
    
}  
?>
