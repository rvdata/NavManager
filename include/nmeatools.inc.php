<?php
/**
 * Define classes for NMEA messages
 *
 * PHP version 5
 *
 * @category R2R_Products
 * @package  R2R_Nav
 * @author   Aaron Sweeney <asweeney@ucsd.edu>
 * @license  http://opensource.org/licenses/GPL-3.0 GNU General Public License
 * @link     http://www.rvdata.us
 */

//----------- Class Definitions ----------//
/**
 * NMEA0183 Message
 *
 * @category R2R_Products
 * @package  R2R_Nav
 * @author   Aaron Sweeney <asweeney@ucsd.edu>
 * @license  http://opensource.org/licenses/GPL-3.0 GNU General Public License
 * @link     http://www.rvdata.us
 */
class NMEA0183Message
{
    public $data, $suppliedCheckSum, $validCheckSum;
    
    /**
     * Get first NMEA message (data, checksum)
     *
     * @param string $sentence File record
     */
    public function init($sentence) 
    {
        //    echo "line: " . $sentence . "\n";
        
        // Find location of checksum in line, if present, and separate from data:
        $cinx = strpos($sentence, '*');
        if ($cinx) {

            // Data with checksum removed.
            $this->data = substr($sentence, 0, $cinx);
            $this->suppliedCheckSum = strtolower(substr($sentence, $cinx + 1));
            
            // Compute checksum from bitwise exclusive-or of everything between,
            // and not including, the initial '$' and '*':
            $checkSum = 0;
            for ($inx = 1; $inx <=strlen($sentence); $inx++ ) {
                if ($sentence[$inx] == '*') {
                    break;
                }
                $checkSum ^= ord($sentence[$inx]);
            }
            $checkSum = dechex($checkSum);
            
            // If checksum is supplied, but not equal to the computed checksum, 
            // set boolean:
            if ($checkSum == $this->suppliedCheckSum) {
                $this->validCheckSum = true;  
            } else {
                $this->validCheckSum = false;
            }
            
        } else {  // Don't bother to compute checksum if not supplied:
            
            $this->data = $sentence;
            $this->suppliedCheckSum = null;
            $this->validCheckSum = false;
            
        }  // end if checksum supplied or not
        
    } // end function init()
    
} // end class NMEA0183Message


/**
 * NMEA0183 ZDA Message
 *
 * @category R2R_Products
 * @package  R2R_Nav
 * @author   Aaron Sweeney <asweeney@ucsd.edu>
 * @license  http://opensource.org/licenses/GPL-3.0 GNU General Public License
 * @link     http://www.rvdata.us
 */
class NMEA0183_ZDA
{
    public $time_stamp, $tim_nroz, $hhmmss;
    public $year, $month, $day;
    public $hh, $mm, $ss;
    public $tzhh, $tzmm;  // Time-zone hours and minutes

    /**
     * Get class properties from ZDA message array
     *
     * @param array $NavArray ZDA message array
     */
    public function init($NavArray) 
    {
        $this->hhmmss = floatval($NavArray[1]);
        
        if (preg_match('/\./', $NavArray[1])) {
            $roz = preg_split('/\./', $NavArray[1]);
            $tim_nroz = strlen($roz[1]);
        } else {
            $tim_nroz = 0;
        }
        $this->tim_nroz = $tim_nroz;
        
        $this->hh = intval($this->hhmmss/1e4);
        $this->mm = intval(($this->hhmmss - ($this->hh*1e4))/1e2);
        $this->ss = $this->hhmmss - ($this->hh*1e4) - ($this->mm*1e2);
        
        // Print exactly the same precision time stamp as in the recorded data.
        if ($this->tim_nroz == 0) {
            $time_format = "%02d:%02d:%02d";
        } else {
            $time_format = "%02d:%02d:%0" . ($this->tim_nroz + 3)
                . "." . $this->tim_nroz . "f";
        }
        //    $hhmmss = sprintf($time_format, $hh, $mm, $ss);
        
        $this->day   = $NavArray[2];
        $this->month = $NavArray[3];
        $this->year  = $NavArray[4];
        
        $this->tzhh  = $NavArray[5];
        if ($this->tzhh != null) {
            // Add sign, if not present (case 0).
            if (!preg_match('/^[\+\-]{1}/', $this->tzhh)) {
                $this->tzhh = '+' . $this->tzhh;
            }
        } else {
            $this->tzhh = '+00';  // No time-zone given, assume UTC
        }
        
        if (preg_match('/\*/', $NavArray[6])) {
            $last_field = preg_split('/\*/', $NavArray[6]);
            $this->tzmm = $last_field[0];
        } else {
            $this->tzmm = $NavArray[6];
        }
        
        // Need to make sure time is UTC.  Convert to UTC if it is not already.
        //    $hh = $hh + $tzhh;
        //$mm = $mm + $tzmm;
        $hhmmss = sprintf($time_format, $this->hh, $this->mm, $this->ss);

        // Strip +- sign, if present.
        // if (preg_match('/^[\+\-]{1}/', $this->tzmm)) { 
        //  $this->tzmm = trim($this->tzmm,"+-");
        // }
        
        //    $time_zone = sprintf("%3s:%2s", $this->tzhh, $this->tzmm);

        // Note: Time in ZDA message is UTC time.
        $iso8601_time = sprintf(
            "%4d-%02d-%02dT%sZ", 
            $this->year, $this->month, $this->day, $hhmmss
        );
        
        $this->time_stamp = $iso8601_time;
        
    }
} // end class NMEA0183_ZDA


/**
 * NMEA0183 "UNIXD" Message
 *
 * @category R2R_Products
 * @package  R2R_Nav
 * @author   Aaron Sweeney <asweeney@ucsd.edu>
 * @license  http://opensource.org/licenses/GPL-3.0 GNU General Public License
 * @link     http://www.rvdata.us
 */
class NMEA0183_UNIXD
{
    // The UNIXD decimal day begins with UTC January 1 = Day 0.  (For example,
    // UTC Jan 1, 12:00:00 = decimay day 0.5).  If a cruise continues beyond the 
    // end of the year, the day will be larger than 365.  Conversion of the 
    // decimal day to day-of-month will depend on whether or not it comes during 
    // a leap year. The year is not recorded in this format and must come from 
    // an external source.
    
    // Note: The $UNIXD message is NOT reported by the GPS receiver; it is
    // reported by the PC clock on the machine running UHDAS.
    
    public $decimal_day, $tim_nroz, $hhmmss;
    public $year, $month, $day;
    public $time_stamp;
    
    /**
     * Get class properties from UNIXD message array
     *
     * @param int   $baseyear 4-digit baseyear
     * @param array $NavArray UNIXD message array
     */
    public function init($baseyear, $NavArray) 
    {
        $this->decimal_day = floatval($NavArray[1]);
        
        if (preg_match('/\./', $NavArray[1])) {
            $roz = preg_split('/\./', $NavArray[1]);
            $dday_nroz = strlen($roz[1]);  // nroz for decimal day
        } else {
            $dday_nroz = 0;
        }
        // Convert decimal day least count to decimal seconds:
        $sec_prec = pow(10, -1*$dday_nroz) * 24.0 * 3600.0; 
        // Calculate the number of digits to keep to the right of the decimal point:
        $this->tim_nroz = get_least_count($sec_prec);   // nroz for decimal seconds
        
        //    echo $dday_nroz, " ", $sec_prec, " ", $this->tim_nroz, "\n";
        
        $doy = intval($this->decimal_day);
        
        $decimal_hh = ($this->decimal_day - $doy) * 24.0; // 24 hr/day
        $hh = floor($decimal_hh);  // integer hours
        $decimal_mm = ($decimal_hh - $hh) * 60.0; // 60 min/hr
        $mm = floor($decimal_mm);  // integer minutes
        $decimal_ss = ($decimal_mm - $mm) * 60.0; // 60 sec/min 
        $ss = $decimal_ss;         // decimal seconds
        
        if ($this->tim_nroz == 0) {
            $time_format = "%02d%02d%02d";
        } else {
            $time_format = "%02d%02d%0" . ($this->tim_nroz + 3)
                . "." . $this->tim_nroz . "f";
        }
        $this->hhmmss = sprintf($time_format, $hh, $mm, $ss);
        
        // Print exactly the same precision time stamp as in the recorded data.
        if ($this->tim_nroz == 0) {
            $time_format = "%02d:%02d:%02d";
        } else {
            $time_format = "%02d:%02d:%0" . ($this->tim_nroz + 3)
                . "." . $this->tim_nroz . "f";
        }
        //    $hhmmss = sprintf($time_format, $hh, $mm, $ss);
        
        // Cumulative number of days:
        $monthsLeap   = array(0, 31, 60, 91, 121, 152, 182,
                              213, 244, 274, 305, 335, 366);
        $monthsNormal = array(0, 31, 59, 90, 120, 151, 181,
                              212, 243, 273, 304, 334, 365);
        
        // Convert decimal day to month and day-of-month.  If decimal day > 364, 
        // increment baseyear.
        $guess = intval($doy*0.032);  // 1/(31 days) = min possible month number
        $more = 0;
        if ($baseyear%4 == 0) {    // Works till year 2100
            if (($doy+1 - $monthsLeap[$guess + 1]) > 0) $more = 1;
            $month = $guess + $more + 1;
            $day = $doy+1 - $monthsLeap[$guess + $more];
        } else {
            if (($doy+1 - $monthsNormal[$guess + 1]) > 0) $more = 1;
            $month = $guess + $more + 1;
            $day = $doy+1 - $monthsNormal[$guess + $more];
        }
        
        $this->day   = $day;
        $this->month = $month;
        $this->year  = $baseyear;
        
        //    echo $baseyear, "-", $month, "-", $day, " doy=", $doy, "\n";
        
        $hhmmss = sprintf($time_format, $hh, $mm, $ss);
        
        // Note: Time in UNIXD message is UTC time.
        $iso8601_time = sprintf(
            "%4d-%02d-%02dT%sZ", 
            $this->year, $this->month, $this->day, $hhmmss
        );
        
        $this->time_stamp = $iso8601_time;
        
        //    echo $this->time_stamp,"\n";
        
    }
} // end class NMEA0183_UNIXD


/**
 * NMEA0183 GGA Message
 *
 * @category R2R_Products
 * @package  R2R_Nav
 * @author   Aaron Sweeney <asweeney@ucsd.edu>
 * @license  http://opensource.org/licenses/GPL-3.0 GNU General Public License
 * @link     http://www.rvdata.us
 */
class NMEA0183_GGA
{
    // GGA = GPS fix data:
    
    public $year, $month, $day, $hour, $minute, $second, $hhmmss;
    public $lat, $lon;
    public $gpsQuality, $numberOfSatellites, $horizontalDilution;
    public $antennaAltitude, $antennaAltitudeUnit;
    public $geoidUndulation, $geoidUndulationUnit;
    public $ageOfDifferentialData, $differentialRefId;
    public $tim_nroz, $lat_nroz, $lon_nroz, $alt_nroz;
    
    /**
     * Get class properties from GGAA message array
     *
     * @param array $NavArray GGA message array
     */
    public function init($NavArray) 
    {
        $this->hhmmss = floatval($NavArray[1]);
        
        if (preg_match('/\./', $NavArray[1])) {
            $roz = preg_split('/\./', $NavArray[1]);
            $tim_nroz = strlen($roz[1]);
        } else {
            $tim_nroz = 0;
        }
        $this->tim_nroz = $tim_nroz;
        
        // We should have no more than 6 characters to the left of the 
        // decimal point (hhmmss).
        // This test was added to catch hhmmss errors appearing in SAV-09-04.
        //if (strlen($roz[0]) > 6) {
        //  $this->hhmmss = floatval( substr($roz[0],-6) . '.' . $roz[1] );
        //}
        
        $this->year   = null;
        $this->month  = null;
        $this->day    = null;
        $this->hour   = intval($this->hhmmss/1e4);
        $this->minute = intval(($this->hhmmss - ($this->hour*1e4))/1e2);
        $this->second = $this->hhmmss - ($this->hour*1e4) - ($this->minute*1e2);
        
        $lat_nmea0183 = floatval($NavArray[2]);
        
        $lat_deg = intval($lat_nmea0183/100);
        $lat_min = $lat_nmea0183 - ($lat_deg*100);
        $lat = $lat_deg + ($lat_min/60);
        if (preg_match('/\./', $NavArray[2])) {
            $roz = preg_split('/\./', $NavArray[2]);
            $lat_nroz = strlen($roz[1]);
        } else {
            $lat_nroz = 0;
        }
        $northsouth = $NavArray[3];
        
        if ($northsouth == 'S') {
            $lat = -$lat;
        }
        $lat_format = "%." . ($lat_nroz + 2) . "f";
        $lat_prec = sprintf($lat_format, $lat);
        //    echo $lat_prec . " " . $lat_nroz . "\n";
        $this->lat = $lat_prec;
        $this->lat_nroz = $lat_nroz;
        
        $lon_nmea0183 = floatval($NavArray[4]);
        
        $lon_deg = intval($lon_nmea0183/100);
        $lon_min = $lon_nmea0183 - ($lon_deg*100);
        $lon = $lon_deg + ($lon_min/60);
        if (preg_match('/\./', $NavArray[4])) {
            $roz = preg_split('/\./', $NavArray[4]);
            $lon_nroz = strlen($roz[1]);
        } else {
            $lon_nroz = 0;
        }
        
        $eastwest = $NavArray[5];
        
        if ($eastwest == 'W') {
            $lon = -$lon;
        }
        $lon_format = "%." . ($lon_nroz + 2) . "f";
        $lon_prec = sprintf($lon_format, $lon);
        //    echo $lon_prec . " " . $lon_nroz . "\n";
        $this->lon = $lon_prec;
        $this->lon_nroz = $lon_nroz;
        
        //    break;
        
        $this->gpsQuality = intval($NavArray[6]);    
        //    echo $this->gpsQuality . "\n";
        
        $this->numberOfSatellites = intval($NavArray[7]);
        //echo $this->numberOfSatellites . "\n";
        //    $maxNSat = max($maxNSat, $numberOfSatellites);
        //    $minNSat = min($minNSat, $numberOfSatellites);
        
        $this->horizontalDilution = $NavArray[8];
        //echo $this->horizontalDilution . "\n";
        //    $maxHDOP = max($maxHDOP, floatval($horizontalDilution));
        //    $minHDOP = min($minHDOP, floatval($horizontalDilution));
        
        $this->antennaAltitude = floatval($NavArray[9]);
        //echo $this->antennaAltitude . "\n";
        //    $maxAlt = max($maxAlt, $antennaAltitude);
        //    $minAlt = min($minAlt, $antennaAltitude);
        
        if (preg_match('/\./', $NavArray[9])) {
            $roz = preg_split('/\./', $NavArray[9]);
            $alt_nroz = strlen($roz[1]);
        } else {
            $alt_nroz = 0;
        }
        //echo $alt_nroz . "\n";
        $this->alt_nroz = $alt_nroz;
        
        $this->antennaAltitudeUnit = $NavArray[10];
        //echo $this->antennaAltitudeUnit . "\n";
        
        $this->geoidUndulation = floatval($NavArray[11]);
        //echo $this->geoidUndulation . "\n";
        
        if (preg_match('/\./', $NavArray[10])) {
            $roz = preg_split('/\./', $NavArray[10]);
            $geoid_nroz = strlen($roz[1]);
        } else {
            $geoid_nroz = 0;
        }  
        //echo $geoid_nroz . "\n";
        $this->geoid_nroz = $geoid_nroz;
        
        $this->geoidUndulationUnit = $NavArray[12];
        //echo $this->geoidUndulationUnit . "\n";
        
        // Initialize the following values (some streams don't provide these):
        $this->ageOfDifferentialData = null;
        $this->differentialRefId = null;
        
        if (count($NavArray) >= 14) {
            
            $this->ageOfDifferentialData = floatval($NavArray[13]);
            //echo $this->ageOfDifferentialData . "\n";
            
            if (count($NavArray) >= 15) {
                
                $this->differentialRefId = intval($NavArray[14]);
                //echo $this->differentialRefId . "\n";
                
            } // if 15 elements
            
        } // if 14 elements
        
    }
    
} // end class NMEA0183_GGA


/**
 * NMEA0183 RMC Message
 *
 * @category R2R_Products
 * @package  R2R_Nav
 * @author   Aaron Sweeney <asweeney@ucsd.edu>
 * @license  http://opensource.org/licenses/GPL-3.0 GNU General Public License
 * @link     http://www.rvdata.us
 */
class NMEA0183_RMC
{
    // RMC = Recommended Minimum sentence C:
    
    public $year, $month, $day, $hh, $mm, $ss, $hhmmss, $ddmmyy;
    public $lat, $lon;
    public $status, $speedOverGround, $trackDegTrue;
    public $magneticVariation, $magneticVariationDirection;
    public $modeIndicator;
    public $tim_nroz, $lat_nroz, $lon_nroz, $alt_nroz;
    
    /**
     * Get class properties from RMC message array
     *
     * @param array $NavArray RMC message array
     */
    public function init($NavArray) 
    {
        $this->hhmmss = floatval($NavArray[1]);
        //echo $this->hhmmss,"\n";
        
        if (preg_match('/\./', $NavArray[1])) {
            $roz = preg_split('/\./', $NavArray[1]);
            $tim_nroz = strlen($roz[1]);
        } else {
            $tim_nroz = 0;
        }
        $this->tim_nroz = $tim_nroz;
        
        $this->hh = intval($this->hhmmss/1e4);
        $this->mm = intval(($this->hhmmss - ($this->hh*1e4))/1e2);
        $this->ss = $this->hhmmss - ($this->hh*1e4) - ($this->mm*1e2);
        
        $this->status = $NavArray[2];  // A = Active, V = Void
        
        $lat_nmea0183 = floatval($NavArray[3]);
        
        $lat_deg = intval($lat_nmea0183/100);
        $lat_min = $lat_nmea0183 - ($lat_deg*100);
        $lat = $lat_deg + ($lat_min/60);
        if (preg_match('/\./', $NavArray[3])) {
            $roz = preg_split('/\./', $NavArray[3]);
            $lat_nroz = strlen($roz[1]);
        } else {
            $lat_nroz = 0;
        }
        $northsouth = $NavArray[4];
        
        if ($northsouth == 'S') {
            $lat = -$lat;
        }
        $lat_format = "%." . ($lat_nroz + 2) . "f";
        $lat_prec = sprintf($lat_format, $lat);
        //    echo $lat_prec . " " . $lat_nroz . "\n";
        $this->lat = $lat_prec;
        $this->lat_nroz = $lat_nroz;
        
        $lon_nmea0183 = floatval($NavArray[5]);
        
        $lon_deg = intval($lon_nmea0183/100);
        $lon_min = $lon_nmea0183 - ($lon_deg*100);
        $lon = $lon_deg + ($lon_min/60);
        if (preg_match('/\./', $NavArray[5])) {
            $roz = preg_split('/\./', $NavArray[5]);
            $lon_nroz = strlen($roz[1]);
        } else {
            $lon_nroz = 0;
        }
        
        $eastwest = $NavArray[6];
        
        if ($eastwest == 'W') {
            $lon = -$lon;
        }
        $lon_format = "%." . ($lon_nroz + 2) . "f";
        $lon_prec = sprintf($lon_format, $lon);
        //    echo $lon_prec . " " . $lon_nroz . "\n";
        $this->lon = $lon_prec;
        $this->lon_nroz = $lon_nroz;
        
        //    break;
        
        $this->speedOverGround = $NavArray[7];   // [knots]
        //    echo $this->speedOverGround,"\n";
        
        $this->trackDegTrue = $NavArray[8];
        //echo $this->trackDegTrue,"\n";
        
        $this->ddmmyy = intval($NavArray[9]);
        //    echo $this->ddmmyy,"\n";
        
        $this->day   = intval($this->ddmmyy/1e4);
        $this->month = intval(($this->ddmmyy - ($this->day*1e4))/1e2);
        $this->year  = 2000 + intval(
            $this->ddmmyy - ($this->day*1e4) - ($this->month*1e2)
        );
        
        $this->magneticVariation = $NavArray[10];
        //echo $this->magneticVariation,"\n";
        
        $this->magneticVariationDirection = $NavArray[11];
        //echo $this->magneticVariationDirection,"\n";
        
        if (count($NavArray)>13) {
            
            // Mode Indicator added in NMEA v2.3:
            // A = Autonomous (Status = Active)
            // D = Differential (Status = Active)
            // E = Estimated (Status = Void)
            // N = Not valid (Status = Void)
            // S = Simulator (Status = Void)
            $this->modeIndicator = $NavArray[12];
            //echo $this->modeIndicator,"\n";
            
        }
        
    }
    
} // end class NMEA0183_RMC


/**
 * NMEA0183 GLL Message
 *
 * @category R2R_Products
 * @package  R2R_Nav
 * @author   Aaron Sweeney <asweeney@ucsd.edu>
 * @license  http://opensource.org/licenses/GPL-3.0 GNU General Public License
 * @link     http://www.rvdata.us
 */
class NMEA0183_GLL
{
    // GLL = Geographic Position, Latitude/Longitude and Time
    
    public $year, $month, $day, $hour, $minute, $second, $hhmmss;
    public $lat, $lon;  
    public $status;
    public $tim_nroz, $lat_nroz, $lon_nroz;
    
    /**
     * Get class properties from GLL message array
     *
     * @param array $NavArray GLL message array
     */
    public function init($NavArray) 
    {
        $lat_nmea0183 = floatval($NavArray[1]);
        
        $lat_deg = intval($lat_nmea0183/100);
        $lat_min = $lat_nmea0183 - ($lat_deg*100);
        $lat = $lat_deg + ($lat_min/60);
        if (preg_match('/\./', $NavArray[1])) {
            $roz = preg_split('/\./', $NavArray[1]);
            $lat_nroz = strlen($roz[1]);
        } else {
            $lat_nroz = 0;
        }
        $northsouth = $NavArray[2];
        
        if ($northsouth == 'S') {
            $lat = -$lat;
        }
        $lat_format = "%." . ($lat_nroz + 2) . "f";
        $lat_prec = sprintf($lat_format, $lat);
        //    echo $lat_prec . " " . $lat_nroz . "\n";
        $this->lat = $lat_prec;
        $this->lat_nroz = $lat_nroz;
        
        $lon_nmea0183 = floatval($NavArray[3]);
        
        $lon_deg = intval($lon_nmea0183/100);
        $lon_min = $lon_nmea0183 - ($lon_deg*100);
        $lon = $lon_deg + ($lon_min/60);
        if (preg_match('/\./', $NavArray[3])) {
            $roz = preg_split('/\./', $NavArray[3]);
            $lon_nroz = strlen($roz[1]);
        } else {
            $lon_nroz = 0;
        }
        
        $eastwest = $NavArray[4];
        
        if ($eastwest == 'W') {
            $lon = -$lon;
        }
        $lon_format = "%." . ($lon_nroz + 2) . "f";
        $lon_prec = sprintf($lon_format, $lon);
        //    echo $lon_prec . " " . $lon_nroz . "\n";
        $this->lon = $lon_prec;
        $this->lon_nroz = $lon_nroz;
        
        if (count($NavArray) > 5) {
            
            $this->hhmmss = floatval($NavArray[5]);
            
            if (preg_match('/\./', $NavArray[5])) {
                $roz = preg_split('/\./', $NavArray[5]);
                $tim_nroz = strlen($roz[1]);
            } else {
                $tim_nroz = 0;
            }
            $this->tim_nroz = $tim_nroz;
            
            $this->year   = null;
            $this->month  = null;
            $this->day    = null;
            $this->hour   = intval($this->hhmmss/1e4);
            $this->minute = intval(($this->hhmmss - ($this->hour*1e4))/1e2);
            $this->second = $this->hhmmss - ($this->hour*1e4) - ($this->minute*1e2);
            
        } else {
            
            $this->hhmmss = null;
            $this->year   = null;
            $this->month  = null;
            $this->day    = null;
            $this->hour   = null;
            $this->minute = null;
            $this->second = null;
            
        } 
        
        if (count($NavArray) > 6) {
            $this->status = $NavArray[6];  // A = Active, V = Void
        } else {
            $this->status = null;
        }
        
    } // end function init()
    
} // end class NMEA0183_GLL


/**
 * NMEA0183 VTG Message
 *
 * @category R2R_Products
 * @package  R2R_Nav
 * @author   Aaron Sweeney <asweeney@ucsd.edu>
 * @license  http://opensource.org/licenses/GPL-3.0 GNU General Public License
 * @link     http://www.rvdata.us
 */
class NMEA0183_VTG
{
    // VTG = Actual track made good and speed over ground:
    
    public $trackDegTrue, $trackDegMag, $speedKnots, $speedKph;
    
    /**
     * Get class properties from VTG message array
     *
     * @param array $NavArray VTG message array
     */
    public function init($NavArray) 
    {
        
        $this->trackDegTrue = $NavArray[1];
        $this->trackDegMag  = $NavArray[3];
        $this->speedKnots   = $NavArray[5];
        $this->speedKph     = $NavArray[7];
        
    }
    
} // end class NMEA0183_VTG


/**
 * Latitude and longitude pair
 *
 * @category R2R_Products
 * @package  R2R_Nav
 * @author   Aaron Sweeney <asweeney@ucsd.edu>
 * @license  http://opensource.org/licenses/GPL-3.0 GNU General Public License
 * @link     http://www.rvdata.us
 */
class LatLon
{
    public $lat, $lon;
    
    /**
     * Initialize lat/lon
     *
     * @param float $lat Latitude (decimal degrees) [-90, 90]
     * @param float $lon Longitude (decimal degrees) [-180, 180)
     */
    public function init($lat, $lon) 
    {
        $this->lat = $lat;
        $this->lon = $lon;
    }
} // end class LatLon


/**
 * Simple Date Time
 *
 * @category R2R_Products
 * @package  R2R_Nav
 * @author   Aaron Sweeney <asweeney@ucsd.edu>
 * @license  http://opensource.org/licenses/GPL-3.0 GNU General Public License
 * @link     http://www.rvdata.us
 */
class DateTimeSimple
{
    public $year, $month, $day, $hh, $mm, $ss;
    
    /**
     * Get first data datetime from file
     *
     * @param resource &$filePointer File pointer
     * @param int      $baseyear     Optional baseyear
     */
    public function init(&$filePointer, $baseyear = 0)
    {
        $nmea = new NMEA0183Message();
        $zda = new NMEA0183_ZDA();
        $unixd = new NMEA0183_UNIXD();
        $rmc = new NMEA0183_RMC();
        
        while (!feof($filePointer)) {
            
            $line = trim(fgets($filePointer));
            // Skip forward to beginning of NMEA message on line.
            $newline = strstr($line, '$'); 
            $line = $newline;
            $nmea->init($line);
            $NavRec = preg_split('/\,/', $nmea->data);
            if (preg_match('/^\$.{2}ZDA$/', $NavRec[0])) { 
                $zda->init($NavRec);
                $this->year  = $zda->year;
                $this->month = $zda->month;
                $this->day   = $zda->day;
                $this->hh    = $zda->hh;
                $this->mm    = $zda->mm;
                $this->ss    = $zda->ss;
                //	$date_format = "%4d-%02d-%02dT%02d:%02d:%02dZ";
                //	echo "zda: ", sprintf(
                //      $date_format, $zda->year, $zda->month, $zda->day,
                //      $zda->hh, $zda->mm, $zda->ss
                //  ), "\n";
				if ($zda->year != "" && $zda->month != ""&& $zda->day != "" && $zda->hh != "0" && $zda->mm != "0" && $zda->ss != "0" || $zda->year != "1999" && $zda->month != "11" && $zda->day != "30") {
					break;
				}
            } else {
                if (preg_match('/^\$UNIXD$/', $NavRec[0])) {
                    $unixd->init($baseyear, $NavRec);
                    $this->year  = $unixd->year;
                    $this->month = $unixd->month;
                    $this->day   = $unixd->day;
                    $this->hh    = $unixd->hh;
                    $this->mm    = $unixd->mm;
                    $this->ss    = $unixd->ss;
					if ($zda->year != "" && $zda->month != ""&& $zda->day != "" && $zda->hh != "0" && $zda->mm != "0" && $zda->ss != "0" || $zda->year != "1999" && $zda->month != "11" && $zda->day != "30") {
						break;
					}
                } else {
                    if (preg_match('/^\$.{2}RMC$/', $NavRec[0])) {
                        $rmc->init($NavRec);
                        $this->year  = $rmc->year;
                        $this->month = $rmc->month;
                        $this->day   = $rmc->day;
                        $this->hh    = $rmc->hh;
                        $this->mm    = $rmc->mm;
                        $this->ss    = $rmc->ss;
                        // $date_format = "%4d-%02d-%02dT%02d:%02d:%02dZ";
                        // echo "rmc: ", sprintf(
                        //     $date_format, $rmc->year, $rmc->month, $rmc->day, 
                        //     $rmc->hh, $rmc->mm, $rmc->ss
                        // ),"\n";   
						if ($zda->year != "" && $zda->month != ""&& $zda->day != "" && $zda->hh != "0" && $zda->mm != "0" && $zda->ss != "0" || $zda->year != "1999" && $zda->month != "11" && $zda->day != "30") {
							break;
						}
                    } // end if RMC
                } // end if UNIXD
            } // end if ZDA
            
        } // end while (!feof($fid))
        
    }  // end function init()
    
    /**
     * Get last data datetime from file
     *
     * @param resource &$filePointer File pointer
     * @param int      $baseyear     Optional baseyear
     */
    public function last(&$filePointer, $baseyear = 0) 
    {
        $nmea = new NMEA0183Message();
        $zda = new NMEA0183_ZDA();
        $unixd = new NMEA0183_UNIXD();
        $rmc = new NMEA0183_RMC();
        
        $line = '';
        // Read file backwards line-by-line until ZDA, UNIXD, or RMC message is
        // found.
        for ($x_pos=0; fseek($filePointer, $x_pos, SEEK_END) !== -1; $x_pos--) {
            
            $char = fgetc($filePointer);
            
            if ($char === "\n") {
                
                if ($line != '') {
                    
                    // Skip forward to beginning of NMEA message on line.
                    $newline = strstr(trim($line), '$'); 
                    $line = $newline;
                    $nmea->init($line);
                    $NavRec = preg_split('/\,/', $nmea->data);
                    if (preg_match('/^\$.{2}ZDA$/', $NavRec[0]) 
                        && count($NavRec) >= 6
                    ) { 
                        $zda->init($NavRec);
                        $this->year  = $zda->year;
                        $this->month = $zda->month;
                        $this->day   = $zda->day;
                        $this->hh    = $zda->hh;
                        $this->mm    = $zda->mm;
                        $this->ss    = $zda->ss;
                        // $date_format = "%4d-%02d-%02dT%02d:%02d:%02dZ";
                        // echo "zda: ",sprintf(
                        //     $date_format, $zda->year, $zda->month, $zda->day, 
                        //     $zda->hh, $zda->mm, $zda->ss
                        // ),"\n";   
						if ($zda->year != "" && $zda->month != ""&& $zda->day != "" && $zda->hh != "0" && $zda->mm != "0" && $zda->ss != "0") {
							break;
						}
				
                    } else {
                        if (preg_match('/^\$UNIXD$/', $NavRec[0])) {
                            $unixd->init($baseyear, $NavRec);
                            $this->year  = $unixd->year;
                            $this->month = $unixd->month;
                            $this->day   = $unixd->day;
                            $this->hh    = $unixd->hh;
                            $this->mm    = $unixd->mm;
                            $this->ss    = $unixd->ss;

							if ($zda->year != "" && $zda->month != ""&& $zda->day != "" && $zda->hh != "0" && $zda->mm != "0" && $zda->ss != "0") {
								break;
							}

                        } else {
                            if (preg_match('/^\$.{2}RMC$/', $NavRec[0])) {
                                $rmc->init($NavRec);
                                $this->year  = $rmc->year;
                                $this->month = $rmc->month;
                                $this->day   = $rmc->day;
                                $this->hh    = $rmc->hh;
                                $this->mm    = $rmc->mm;
                                $this->ss    = $rmc->ss;
                                // $date_format = "%4d-%02d-%02dT%02d:%02d:%02dZ";
                                // echo "rmc: ",sprintf(
                                // $date_format, $rmc->year, $rmc->month, $rmc->day,
                                // $rmc->hh, $rmc->mm, $rmc->ss
                                // ), "\n";   
								if ($zda->year != "" && $zda->month != ""&& $zda->day != "" && $zda->hh != "0" && $zda->mm != "0" && $zda->ss != "0") {
									break;
								}
                            } // end if RMC
                        } // end if UNIXD
                    } // end if ZDA
                    
                    // Reset $line:
                    $line = $char;
                    
                } // end if ($line != '')
                
            } // end if ($char === "\n")
            
            // Add character to line:
            $line = $char . $line;
            
        } // end for(): read file backwards line-by-line
        
    }  // end function last()
    
} // end class DateTimeSimple
//------------ End Class Definitions ------------//

//------------ Begin Function Definitions ----------//
/**
 * Determine the number of days in a given month
 *
 * @param int $year  Given 4-digit year
 * @param int $month Given 2-digit month
 *
 * @return int
 */
function daysInMonth($year, $month) 
{
    if ($month == 1) return 31;
    if ($month == 2) {
        if ((($year%4) == 0) && ((($year%100) != 0) 
            || ((($year%100) == 0) && (($year%400) == 0)))
        ) return 29;
        return 28;
    }
    if ($month == 3) return 31;
    if ($month == 4) return 30;
    if ($month == 5) return 31;
    if ($month == 6) return 30;
    if ($month == 7) return 31;
    if ($month == 8) return 31;
    if ($month == 9) return 30;
    if ($month == 10) return 31;
    if ($month == 11) return 30;
    if ($month == 12) return 31;
    return 0;
    
} // end function daysInMonth()


/**
 * Convert Julian day-of-year to month and day-of-month
 *
 * @param int $year Given 4-digit year
 * @param int $doy  Given Julian day-of-year
 *
 * @return array Associative array
 */
function doy2mmdd($year, $doy) 
{
    $quit = false;
    $inx = 1;
    
    while ((!$quit) && ($inx < 12)) {
        if (daysInMonth($year, $inx) < $doy) {
            $doy -= daysInMonth($year, $inx);
            $inx++;
        } else {
            $quit = true;
        }
    }
    $month = $inx;
    $day = $doy;
    
    return array("month" => $month, "day" => $day);
    
} // end function doy2mmdd()


/**
 * Determine if file contains NMEA GGA record(s)
 *
 * @param resource $fid File pointer
 *
 * @return bool Returns true if the file contains NMEA GGA record, else false
 */
function has_GGA($fid) 
{
    // 
    
    $has_GGA = false;
    
    while (!feof($fid)) {
        
        $line = trim(fgets($fid));
        
        if (preg_match('/GGA/', $line)) {
            $has_GGA = true;
            break;
        }
        
    } // end loop over file
    
    rewind($fid);  // Move file pointer back to start of file.
    
    return $has_GGA;
    
} // end function has_GGA()


/**
 * Validate NMEA GGA message
 *
 * @param array $NavArray Array of GGA information
 *
 * @return bool Returns true if valid NMEA GGA record, else false
 */
function valid_gga_message($NavArray) 
{
    // Test for length and valid characters in datetime:
    $re = "/^\d{6}(\.\d*)?$/";
    if (!preg_match($re, $NavArray[1])) {
        return false;
    }
    
    // Test for length and valid characters in lat:
    $re = "/^\d{4}(\.\d*)?$/";
    if (!preg_match($re, $NavArray[2])) {
        return false;
    }
    
    // Test for valid North/South direction:
    $re = "/^(N|S)$/";
    if (!preg_match($re, $NavArray[3])) {
        return false;
    }
    
    // Test for length and valid characters in lon:
    $re = "/^\d{5}(\.\d*)?$/";
    if (!preg_match($re, $NavArray[4])) {
        return false;
    }
    
    // Test for valid West/East direction:
    $re = "/^(W|E)$/";
    if (!preg_match($re, $NavArray[5])) {
        return false;
    }
    
    // Test for single digit for quality indicator:
    $re = "/^\d{1}$/";
    if (!preg_match($re, $NavArray[6])) {
        return false;
    }
    
    // Test for single or double digit(s) for number of satellites:
    //  $re = "/^\d{1,2}$/";
    //  if (!preg_match( $re, $NavArray[7] )) {
    //    return false;
    //  }
    
    // If no tests fail, return true:
    return true;
    
} // end function valid_gga_message()


/**
 * Validate NMEA ZDA message
 *
 * @param array $NavArray Array of ZDA information
 *
 * @return bool Returns true if valid NMEA ZDA record, else false
 */
function valid_zda_message($NavArray) 
{
    // Test for length and valid characters in datetime:
    $re = "/^\d{6}(\.\d*)?$/";
    if (!preg_match($re, $NavArray[1])) {
        return false;
    }
    
    // Test for length and valid characters in day:
    $re = "/^\d{2}$/";
    if (!preg_match($re, $NavArray[2])) {
        return false;
    }
    
    // Test for length and valid characters in month:
    $re = "/^\d{2}$/";
    if (!preg_match($re, $NavArray[3])) {
        return false;
    } 
    
    // Test for length and valid characters in year:
    $re = "/^\d{4}$/";
    if (!preg_match($re, $NavArray[4])) {
        return false;
    } 
    
    // If no tests fail, return true:
    return true;
    
} // end function valid_zda_message()

//------------ End Function Definitions ----------//
?>
