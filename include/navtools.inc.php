<?php
/**
 * Define functions helpful with navigation.  
 * Compute distance, speed, and acceleration
 *
 * PHP version 5
 *
 * @category R2R_Products
 * @package  R2R_Nav
 * @author   Aaron Sweeney <asweeney@ucsd.edu>
 * @license  http://opensource.org/licenses/GPL-3.0 GNU General Public License
 * @link     http://www.rvdata.us
 */

/**
 * Compute distance [m] on a spherical earth using Haversine's formula
 *
 * @param float $lonFrom Starting longitude [-180, 180)
 * @param float $latFrom Starting latitude [-90, 90]
 * @param float $lonTo   Ending longitude [-180, 180)
 * @param float $latTo   Ending latitude [-90, 90]
 *
 * @return float Returns distance [m]
 */
function haversine($lonFrom, $latFrom, $lonTo, $latTo) 
{
    $dtr = M_PI/180;     // degrees to radians
    $R_earth = 6371010;  // [m]
    
    $dellat = $dtr * ($latTo - $latFrom);
    $dellon = $dtr * ($lonTo - $lonFrom);
    // Angular distance ($delsig) computed with Haversine formula 
    // (spherical earth model):
    $dx = sqrt(
        pow(sin($dellat/2), 2) + cos($dtr*$latFrom) * 
        cos($dtr*$latTo) * pow(sin($dellon/2), 2)
    );
    $delsig = 2.0 * asin($dx);
    
    $distance = $R_earth * $delsig ;
    return $distance;
    
} // end function haversine()


/**
 * Compute distance [m] and direction angles (forward and reverse) 
 * on an ellipsoidal earth using Vincenty's algorithm
 *
 * WGS-84 ellipsoid is used.
 *
 * @param float $lon1 Starting longitude [-180, 180)
 * @param float $lat1 Starting latitude [-90, 90]
 * @param float $lon2 Ending longitude [-180, 180)
 * @param float $lat2 Ending latitude [-90, 90]
 *
 * @return array Returns distance [m], 
 *               forward azimuth [degrees CW from N], and 
 *               reverse azimuth [degrees CW from N]
 */
function vincenty($lon1, $lat1, $lon2, $lat2) 
{
    $dtr = M_PI/180;     // degrees to radians

    // WGS-84 parameters:
    $a = 6378137.0;  // ellipsoid major axis [m]
    //  $a = 6378137 - 21 * sin(lat);
    $b = 6356752.314; // ellipsoid minor axis [m]
    //  $b = 6356752.3142; 
    $f = 1/298.257223563; // ellipsoid flattening
    
    // Convert decimal degrees to radians:
    $p1_lat = $lat1*$dtr;
    $p2_lat = $lat2*$dtr;
    $p1_lon = $lon1*$dtr;
    $p2_lon = $lon2*$dtr;
    
    $L = $p2_lon - $p1_lon;
    
    $U1 = atan((1 - $f) * tan($p1_lat));
    $U2 = atan((1 - $f) * tan($p2_lat));
    
    $sinU1 = sin($U1);
    $cosU1 = cos($U1);
    $sinU2 = sin($U2);
    $cosU2 = cos($U2);
    
    $lambda = $L;
    $lambdaP = 2*M_PI;
    $iterLimit = 20;
    
    while (abs($lambda - $lambdaP) > 1e-12 && $iterLimit > 0) {
        $sinLambda = sin($lambda);
        $cosLambda = cos($lambda);
        $sinSigma = sqrt(
            ($cosU2*$sinLambda) * ($cosU2*$sinLambda) 
            + ($cosU1*$sinU2-$sinU1*$cosU2*$cosLambda) 
            * ($cosU1*$sinU2-$sinU1*$cosU2*$cosLambda)
        );
        
        if ($sinSigma == 0) {  // co-incident points
            return array(0, 0, 180);  // co-incident points
        }
        $cosSigma = $sinU1*$sinU2 + $cosU1*$cosU2*$cosLambda;
        $sigma = atan2($sinSigma, $cosSigma);
        $sinAlpha = $cosU1 * $cosU2 * $sinLambda / $sinSigma;
        $cosSqAlpha = 1 - $sinAlpha*$sinAlpha;
        $cos2SigmaM = $cosSigma - 2*$sinU1*$sinU2/$cosSqAlpha;
        if (is_nan($cos2SigmaM)) {
            $cos2SigmaM = 0;  // equatorial line: cosSqAlpha=0
        }
        $C = $f/16*$cosSqAlpha*(4+$f*(4-3*$cosSqAlpha));
        $lambdaP = $lambda;
        $lambda = $L + (1-$C) * $f * $sinAlpha * (
            $sigma + $C*$sinSigma*(
                $cos2SigmaM+$C*$cosSigma*(
                    -1+2*$cos2SigmaM*$cos2SigmaM
                )
            )
        );
    }
    
    //  if ($iterLimit == 0) {
    //      return "NAN";  // formula failed to converge
    //  }
    
    $uSq = $cosSqAlpha*($a*$a-$b*$b)/($b*$b);
    $A = 1 + $uSq/16384*(4096+$uSq*(-768+$uSq*(320-175*$uSq)));
    $B = $uSq/1024 * (256+$uSq*(-128+$uSq*(74-47*$uSq)));
    
    $deltaSigma = $B*$sinSigma*(
        $cos2SigmaM+$B/4*(
            $cosSigma*(-1+2*$cos2SigmaM*$cos2SigmaM)
            - $B/6*$cos2SigmaM*(-3+4*$sinSigma*$sinSigma)
            *(-3+4*$cos2SigmaM*$cos2SigmaM)
        )
    );
   
    $s = $b*$A*($sigma-$deltaSigma);
    
    // initial & final bearings [radians]
    $fwdAz = atan2($cosU2*$sinLambda, $cosU1*$sinU2-$sinU1*$cosU2*$cosLambda);
    $revAz = atan2($cosU1*$sinLambda, -$sinU1*$cosU2+$cosU1*$sinU2*$cosLambda);
    
    // Convert from radians to degrees;
    $fwdAz = $fwdAz/$dtr;
    $revAz = $revAz/$dtr;

    // 2013-01-21: ADS: Make sure angle is in range [0, 360).
    while ($fwdAz < 0.0) $fwdAz += 360.0;  // Bearing should be [0, 360)
    while ($revAz < 0.0) $revAz += 360.0;  // [degrees CW from N]

    return array($s, $fwdAz, $revAz);
    
} // end function vincenty()


/**
 * Calculate horizontal component of speed [m/s]
 *
 * @param float $timFrom Start datetime, Unix seconds
 * @param float $lonFrom Start longitude [-180, 180)
 * @param float $latFrom Start latitiude [-90, 90]
 * @param float $timTo   End datetime, Unix seconds
 * @param float $lonTo   End longitude [-180, 180)
 * @param float $latTo   End latitude [-90, 90]
 *
 * @return float Returns horizonal component of speed [m/s]
 */
function calcSpeedHori($timFrom, $lonFrom, $latFrom, $timTo, $lonTo, $latTo) 
{  
    // Time increment:
    $delt = $timTo - $timFrom;
    
    if ($lonFrom == "NAN" || $latFrom == "NAN" 
        || $lonTo == "NAN" || $lonTo == "NAN"
    ) {
        $speedHori = "NAN";
        return $speedHori;
    }
    
    if ($delt != 0) {  // Check for divide-by-zero condition.
        
        // Distance on earth's surface:
        //if ($delt < 10) { // haversine is good as long as delt is not too large
        //  $distance = haversine($lonFrom, $latFrom, $lonTo, $latTo);
        //} else {
        list($distance, $fwdAz, $revAz) 
            = vincenty($lonFrom, $latFrom, $lonTo, $latTo);
        //}
        
        $speedHori = $distance / $delt;
        
    } else {
        
        $speedHori = "NAN";
        
    }
    
    return $speedHori;
    
} // end function calcSpeedHori()


/**
 * Calculate horizontal component of acceleration [m/s^2]
 *
 * @param float $timFrom Start datetime, Unix seconds
 * @param float $velFrom Start horizontal speed [m/s]
 * @param float $timTo   End datetime, Unix seconds
 * @param float $velTo   End horizontal speed [m/s]
 *
 * @return float Returns horizonal component of acceleration [m/s^2]
 */
function calcAccelHori($timFrom, $velFrom, $timTo, $velTo) 
{  
    $delvel = $velTo - $velFrom;
    $delt = $timTo - $timFrom;
    
    if ($velFrom == "NAN" || $velTo == "NAN") {
        $accelHori = "NAN";
        return $accelHori;
    }
    
    if ($delt != 0) {  // Check for divide-by-zero condition.
        
        $accelHori = $delvel / $delt;
        
    } else {
        
        $accelHori = "NAN";
        
    }
    
    return $accelHori;
    
} // end function calcAccelHori()


/**
 *  Test the given datetime against the start and end datetimes of the
 *  cruise.  
 *
 *  A valid datetime is one that falls within the cruise 
 *  dates plus-or-minus one month (30 days).  
 *  Format: YYYY-MM-DDYHH:mm:ss.sssZ
 *
 * @param string $tmpEpochRFC5424    Data record datetime
 * @param string $dateStringUTCStart Cruise start datetime
 * @param string $dateStringUTCEnd   Cruise end datetime
 *
 * @return bool Returns true if valid datetime, else false
 */
function valid_datetime($tmpEpochRFC5424, $dateStringUTCStart, $dateStringUTCEnd) 
{
    // Check expected character length of datetime components:
    $re = "/^\d{4}\-\d{2}\-\d{2}T\d{2}\:\d{2}:\d{2}(\.\d*)?Z$/";
    if (!preg_match($re, $tmpEpochRFC5424)) {
        return false;
    }
    
    $windowStart = strtotime("-30 days", strtotime($dateStringUTCStart));
    $windowEnd   = strtotime("+30 days", strtotime($dateStringUTCEnd));
    
    if (strtotime($tmpEpochRFC5424) >= $windowStart 
        && strtotime($tmpEpochRFC5424) <= $windowEnd
    ) {
        return true;
    } else {
        return false;
    }
    
} // end function valid_datetime()


/**
 * Determine whether given longitude is valid.  (-180 <= lon < 180)
 *
 * @param float $lon Given longitude value
 *
 * @return bool Returns true if valid longitude, else false
 */
function valid_longitude($lon) 
{
    if ($lon == "NAN") {
        return false;
    }
    
    if ($lon >= -180.0 && $lon < 180.0) {
        return true;
    } else {
        return false;
    }
    
} // end function valid_longitude()


/**
 * Determine whether given latitude is valid.  (-90 <= lon <= 90)
 *
 * @param float $lat Given latitude value
 *
 * @return bool Returns true if valid latitude, else false
 */
function valid_latitude($lat) 
{
    if ($lat == "NAN") {
        return false;
    }
    
    if ($lat >= -90.0 && $lat <= 90.0) {
        return true;
    } else {
        return false;
    }
    
} // end function valid_latitude()


/**
 * Determine whether given GGA fix is valid
 *
 * @param int   $qual NMEA GGA quality indicator
 * @param int   $nsat NMEA GGA number of satellites
 * @param float $hdop NMEA GGA horizontal dilution of precision
 *
 * @return bool Returns true if valid GGA fix, else false
 */
function valid_gga_fix($qual, $nsat, $hdop) 
{
    // Assume GGA is valid until proven otherwise:
    $is_valid = true;
    
    // Exclude invalid fix (0), dead-reckoning mode (6), manual mode (7), 
    // and simulator mode (8):
    if ($qual < 1 || $qual > 5) {
        $is_valid = false;
    }
    
    // No fix:
    if ($nsat == 0 || $hdop == 0) {
        $is_valid = false;
    }
    
    // HDOP is unreasonably large:
    if ($hdop > 50) {
        $is_valid = false;
    }
    
    // The number of satellites should be 24 or less (we use 50 in case
    // the system grows):
    if ($nsat > 50) {
        $is_valid = false;
    } 
    
    // We need 4 or more satellites to determine position.
    if ($nsat < 4) {
        $is_valid = false;
    }
    
    return $is_valid;
    
} // end function valid_gga_fix()


/**
 * Determine whether given altitude [m] is valid
 *
 * @param float $alt NMEA GGA altitude [m]
 *
 * @return bool Returns true if valid altitude, else false
 */
function valid_altitude($alt) 
{
    // Not all operators record altitude, so allow NANs to pass:
    if ($alt === "NAN") {
        return true;
    }
    
    // Assume altitude is valid until proven otherwise:
    $is_valid = true;
    
    // If recorded by operators, then altitude of vessel should be reasonable.
    if (abs($alt) >= 500) {
        $is_valid = false;
    } else {
        $is_valid = true;
    }
    
    return $is_valid;
    
} // end function valid_altitude()
?>
