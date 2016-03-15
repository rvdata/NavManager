<?php
/**
 * Define function to quality control (flag) navigation rawdata and
 * create R2R Navigation BestRes standard product
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
require_once 'navtools.inc.php';
date_default_timezone_set('UTC');

/**
 * Find the median value in an unsorted array
 *
 * @param array $arr Unsorted array
 *
 * @return float Return median value
 */
function array_median($arr) 
{
    sort($arr);
    $count = count($arr); // total numbers in array
    // find the middle value, or the lowest middle value
    $middleval = floor(($count-1)/2);

    if ($count % 2) { // odd number, middle is the median
        $median = $arr[$middleval];
    } else { // even number, calculate avg of 2 medians
        $low = $arr[$middleval];
        $high = $arr[$middleval+1];
        $median = (($low+$high)/2);
    }
    
    return $median;    
}


/**
 * Quality control navigation data in R2R standard format by flagging
 * bad records.
 * 
 * @param string $infile             Input file for quality control
 * @param string $dateStringUTCStart Start datetime (RFC-2524)
 * @param string $dateStringUTCEnd   End datetime (RFC-2524)
 * @param float  $speedLimit         Maximum horizontal speed [m/s] to test against
 * @param float  $accelLimit         Maximum horizontal acceleration [m/s^2] to 
 *                                    test against
 * @param string $outfile            Optional filename for quality controlled result
 * @param string $flog               Optional log file pointer; if not null, log 
 *                                    file will be written [default: null]         
 * @return bool Returns true on successful completion.
 */
function navqc(
    $infile, $dateStringUTCStart, $dateStringUTCEnd, 
    $speedLimit, $accelLimit, $outfile, $flog = null
) {  
    //--------- Begin Initialize Variables ----------//
    $maxBuffer = 3600;  // Max number of elements array can hold
    $maxIter = 3;  // Max num of iterations to loop through buffer,
                   // flagging positions
    //--------- End Initialize Variables ------------//

    
    if (!file_exists($infile)) {
        echo "navqc(): Could not locate file: " . $infile . "\n";
        exit(1);
    }
    
    if ($speedLimit <= 0) {
        echo "navqc(): Speed limit cannot be equal to or less than zero.\n";
        exit(1);
    }
    
    if ($accelLimit <= 0) {
        echo "navqc(): Acceleration limit cannot be equal to or less than zero.\n";
        exit(1);
    }
    
    if ($flog != null) {
        $verbose = true;
    } else {
        $verbose = false;
    }
    
    $fin = fopen($infile, "r");
    if ($fin == null) {
        echo "navqc(): Could not open file: " . $infile . "\n";
        exit(1);
    }
    
    $fout = fopen($outfile, "w");
    if ($fout == null) {
        echo "navqc(): Could not open file: " . $outfile . "\n";
        exit(1);
    }

    //----- Begin write header records to output file -----//
    $date_created_bestres = gmdate("Y-m-d\TH:i:s\Z");
    fprintf(
        $fout, 
        HEADER . " Datetime [UTC], Longitude [deg], Latitude [deg], " 
        . "GPS quality indicator, Number of GPS satellites, " 
        . "Horizontal dilution of precision, " 
        . "GPS antenna height above/below mean sea level [m], " 
        . "Instantaneous Speed-over-ground [m/s], " 
        . "Instantaneous Course-over-ground [deg. clockwise from North]\n"
    );
    fprintf(
        $fout, 
        HEADER . " More detailed information may be found here: " 
        . "http://get.rvdata.us/format/100002/format-r2rnav.txt\n"
    );
    fprintf($fout, HEADER . " Creation date: %s\n", $date_created_bestres);
    //----- End write header records to output file -----//    

    $timeNROD = array(); // Time least count: Number of digits to right of decimal
    $utim = array();
    $last_good_utim = 0;
    $last_good_index = 1;
    $lon = array();
    $lonNROD = array();
    $lat = array();
    $latNROD = array();
    $epochOK = array();
    $haveFirstEpoch = false;
	$firstEpoch = new stdClass();
    
    $median_speed = 0;  // Initial median speed is zero.
    $min_speed_threshold = 0.25; // [m/s]
    
    $irec = 1;  // Record number (from start of file)
    $binx = 1;  // Buffer index
    while (!feof($fin)) {
        
        $line = trim(fgets($fin));
        if ($line != "") {
            
            // Skip flagged data records and header records
            if ($line[0] != QCFLAG && !strstr($line, HEADER)) {
                
                $dataRec = preg_split('/\t/', $line);
                
                $tmpEpochRFC5424 = $dataRec[0];
                $tmpLon = $dataRec[1];
                $tmpLat = $dataRec[2];
                
                // Get quality information, if present:
                if (count($dataRec) == 6) {
                    $tmpQual = $dataRec[3];
                    $tmpNsat = $dataRec[4];
                    $tmpHdop = $dataRec[5];
                } else {
                    if (count($dataRec) == 7) {
                        $tmpQual = $dataRec[3];
                        $tmpNsat = $dataRec[4];
                        $tmpHdop = $dataRec[5];
                        $tmpAlt  = $dataRec[6];
                    }
                }
                
                // Test date record datetime against cruise dates.  If not valid, 
                // flag record.
                if (valid_datetime($tmpEpochRFC5424, $dateStringUTCStart, $dateStringUTCEnd)) {
                    $epochOK[$binx] = true;
                } else {
                    $epochOK[$binx] = false;
                }
                
                $result = sscanf(
                    $tmpEpochRFC5424, "%d-%d-%dT%d:%d:%sZ", 
                    $year, $month, $day, $hour, $minute, $second
                );
                $second = trim($second, "Z");
                
                // Skip over epoch if hh:mm:ss = 00:00:00 and lon = 0 and lat = 0.
                if (!(($hour==0) && ($minute==0) && ($second==0) 
                    && ($tmpLon==0) && ($tmpLat==0)) && $epochOK[$binx]
                ) {
                    
                    $epochOK[$binx] = true;  // OK, until otherwise tested.
                    
                    if ($haveFirstEpoch == false) {
                        $firstEpoch->year = $year;
                        $firstEpoch->month = $month;
                        $firstEpoch->day = $day;
                        $firstEpoch->hour = $hour;
                        $firstEpoch->minute = $minute;
                        $firstEpoch->second = floatval($second);
                        $haveFirstEpoch = true;
                    }
                    
                    if (preg_match('/\./', $second)) {
                        $roz = preg_split('/\./', $second);
                        $timeNROD[$binx] = strlen($roz[1]);
                    }
                    
                    if (preg_match('/\./', $tmpLon)) {
                        $roz = preg_split('/\./', $tmpLon);
                        $lonNROD[$binx] = strlen($roz[1]);
                    } else {
                        $lonNROD[$binx] = 0;
                    }
                    
                    if (preg_match('/\./', $tmpLat)) {
                        $roz = preg_split('/\./', $tmpLat);
                        $latNROD[$binx] = strlen($roz[1]);
                    } else {
                        $latNROD[$binx] = 0;
                    }
                    
                    // PHP function gmmktime expects integer seconds.  Need to 
                    // add fractions of second to integer second Unix time to 
                    // preserve original time precision.
                    $secondFractionOf = floatval($second) - floor(floatval($second));
                    $utim[$binx] = gmmktime(
                        $hour, $minute, floor(floatval($second)), $month, $day, $year
                    ) + $secondFractionOf;
                    if ($tmpLon != "NAN") {
                        $lon[$binx] = floatval($tmpLon);
                    } else {
                        $lon[$binx] = $tmpLon;
                    }
                    if ($tmpLat != "NAN") {
                        $lat[$binx] = floatval($tmpLat);
                    } else {
                        $lat[$binx] = $tmpLat;
                    }
                    
                    if (!valid_longitude($lon[$binx])) {
                        $epochOK[$binx] = false;
                    }
                    if (!valid_latitude($lat[$binx])) {
                        $epochOK[$binx] = false;
                    }
                    
                    // If the position uncertainty is greater than 0.11 m then 
                    // we have to inflate the acceleration limit from 1 m/s^2.  
                    // The accelerations are noisier if the positions are more 
                    // uncertain.
                    if ($binx == 1) {
                        $dtr = 180/M_PI;  // degrees to radians
                        $R_earth = 6378000;  // Average radius of earth [m]
                        $mpdLat = 2*M_PI*$R_earth/360 ;  // [meters per degree of latitude]
                        $mpdLon = (2*M_PI*$R_earth/360) * cos($lat[$binx]/$dtr) ;  // [meters per degree of longitude]
                        $delLatMeters = 1.0/pow(10, intval($latNROD[$binx])) * $mpdLat;
                        $delLonMeters = 1.0/pow(10, intval($lonNROD[$binx])) * $mpdLon;
                        $maxPositionDeltaMeters = max($delLatMeters, $delLonMeters);
                        // If $latNROD = 6, then $delLatMeters = 0.11 m.
                        // If $latNROD = 5, then $delLatMeters = 1.11 m.
                        // Need this code for Endeavor only because its GPS 
                        // records at lower spatial res than other vessels 
                        // (10^-5 degrees vs 10^-6 degrees).
                        if ($maxPositionDeltaMeters >= 1.11) {
                            $accelLimit = 3*$maxPositionDeltaMeters;
                        }
                    }
                    
                    // NMEA quality information
                    if (count($dataRec) >= 6) {
                        $qual[$binx] = ($tmpQual != "NAN") 
                            ? intval($tmpQual) : $tmpQual;
                        $nsat[$binx] = ($tmpNsat != "NAN") 
                            ? intval($tmpNsat) : $tmpNsat;
                        if (preg_match('/\./', $tmpHdop)) {
                            $roz = preg_split('/\./', $tmpHdop);
                            $hdopNROD[$binx] = strlen($roz[1]);
                        } else {
                            $hdopNROD[$binx] = 0;
                        }
                        $hdop[$binx] = ($tmpHdop != "NAN") 
                            ? floatval($tmpHdop) : $tmpHdop;
                        if (count($dataRec) == 7) {
                            $alt[$binx] = $tmpAlt;
                        }
                    } else { // if NMEA quality information not present:
                        $qual[$binx] = "NAN";
                        $hdop[$binx] = "NAN";
                        $nsat[$binx] = "NAN";
                        $alt[$binx]  = "NAN";
                    } // if NMEA quality information present
                    
                    // Check to see whether or not record contains NMEA GGA
                    // quality indicator:
                    if (!is_null($qual[$binx]) && $qual[$binx] !== "NAN" 
                        && $nsat[$binx] !== "NAN"
                    ) {
                        
                        // Throw away no fix, dead reckoning, no satellites, or 
                        // no hdop.
                        // Also, throw away if hdop>50, or if qual=1, but nsat<4.
                        // Also, throw away if altitude is "huge."
                        //if ($qual[$binx]==0 || $qual[$binx]==6 || $nsat[$binx]==0 
                        // || $hdop[$binx]==0 || $hdop[$binx]>50 || ($qual[$binx]==1 
                        // && $nsat[$binx]<4)) {
                        //if ($qual[$binx]==0 || $qual[$binx]==6 || $nsat[$binx]==0 
                        // || $hdop[$binx]==0 || $hdop[$binx]>50 || ($qual[$binx]==1 
                        // && $nsat[$binx]<4) || $alt[$binx]>=500 
                        // || $alt[$binx]<=-500) {
                        if (!valid_gga_fix($qual[$binx], $nsat[$binx], $hdop[$binx]) 
                            || !valid_altitude($alt[$binx])
                        ) {
                            
                            $epochOK[$binx] = false;
                            //echo "navqc(): Bad GGA: ", $line, "\n";
                            
                            //---------- BEGIN VERBOSE ------------//
                            if ($verbose) {
                                
                                $secFormat = "%." . $timeNROD[$binx] . "f";
                                $lonFormat = ($lon[$binx] != "NAN") 
                                    ? ("%." . $lonNROD[$binx] . "f") : "%s";
                                $latFormat = ($lat[$binx] != "NAN") 
                                    ? ("%." . $latNROD[$binx] . "f") : "%s";
                                $secFracFormat = "%0" . $timeNROD[$binx] . "d";
                                $qualFormat = "%s\t%s\t%." . $hdopNROD[$binx] . "f";
                                if ($timeNROD[$binx] > 0) {
                                    $epochRFC5424 = gmdate(
                                        "Y-m-d\TH:i:s", $utim[$binx]) . "." .
                                        sprintf($secFracFormat, round(pow(10, $timeNROD[$binx]) * 
                                        ($utim[$binx] - floor($utim[$binx])))
                                        ) . "Z";
                                } else {
                                    $epochRFC5424 = gmdate("Y-m-d\TH:i:s", $utim[$binx])
                                        . "Z";
                                }
                                if (isset($alt)) {
                                    fprintf($flog, "Bad fix: %s\t%s\t%s\t%s\t%s\n", $epochRFC5424, 
                                            sprintf($lonFormat, $lon[$binx]),
                                            sprintf($latFormat, $lat[$binx]), 
                                            sprintf($qualFormat, $qual[$binx], $nsat[$binx], $hdop[$binx]),
                                            sprintf("%s", $alt[$binx])); 
                                } else {
                                    if (!is_null($qual[$binx] && $qual[$binx] != "NAN")) {
                                        fprintf($flog, "Bad fix: %s\t%s\t%s\t%s\n", $epochRFC5424,
                                                sprintf($lonFormat, $lon[$binx]),
                                                sprintf($latFormat, $lat[$binx]),
                                                sprintf($qualFormat, $qual[$binx], $nsat[$binx], $hdop[$binx]));
                                    } else {
                                        fprintf($flog, "Bad fix: %s\t%s\t%s\n", $epochRFC5424,
                                                sprintf($lonFormat, $lon[$binx]),
                                                sprintf($latFormat, $lat[$binx]) );
                                    }
                                } // if alt set
                                
                            } // end if verbose
                            //---------- END VERBOSE ------------//
                            
                        } // if no fix
                        
                    } // if NMEA quality information present	  
                    
                    // Initialize last_good_utim for determining if records 
                    // are out of sequence:
                    if ($last_good_utim == 0 && $epochOK[$binx]) {
                        $last_good_utim = $utim[$binx];
                        $last_good_index = $binx;
                    }
                    //if ($binx == 1) {
                    //   if ($epochOK[$binx]) {
                    //     $last_good_utim = $utim[$binx];
                    //   }
                    // }
                    
                    //if ($binx > 1) {
                    if ($binx > $last_good_index) { 
                        
                        // Check for sequential times in file:
                        // 2592000 sec = 3600 * 24 * 30 = 1 month
                        if ($utim[$binx] > $last_good_utim 
                            && (($utim[$binx] - $last_good_utim) <= 2592000)
                        ) {
                            
                            $last_good_utim = $utim[$binx];
                            
                        } else { // Non-sequential times in file.
                            
                            // Flag non-sequential record.
                            $epochOK[$binx] = false;
                            
                            //---------- BEGIN VERBOSE ------------//	      
                            if ($verbose) {
                                
                                fprintf($flog, "Record out of sequence:\n");
                                $secFormat = "%." . $timeNROD[$binx] . "f";
                                $lonFormat = ($lon[$binx-1] != "NAN") ? ("%." . $lonNROD[$binx-1] . "f") : "%s";
                                $latFormat = ($lat[$binx-1] != "NAN") ? ("%." . $latNROD[$binx-1] . "f") : "%s";
                                $secFracFormat = "%0" . $timeNROD[$binx] . "d";
                                if ($timeNROD[$binx] > 0) {
                                    $epochRFC5424 = gmdate("Y-m-d\TH:i:s", $utim[$binx-1]) . "." .
                                        sprintf($secFracFormat, round(pow(10, $timeNROD[$binx]) * 
                                        ($utim[$binx-1] - floor($utim[$binx-1])))) . "Z";
                                } else {
                                    $epochRFC5424 = gmdate("Y-m-d\TH:i:s", $utim[$binx-1]) . "Z";
                                }
                                fprintf($flog, "Previous record: %s\t%s\t%s\n", $epochRFC5424, 
                                sprintf($lonFormat, $lon[$binx-1]), 
                                sprintf($latFormat, $lat[$binx-1])); 
                                $secFormat = "%." . $timeNROD[$binx] . "f";
                                $lonFormat = ($lon[$binx] != "NAN") ? ("%." . $lonNROD[$binx] . "f") : "%s";
                                $latFormat = ($lat[$binx] != "NAN") ? ("%." . $latNROD[$binx] . "f") : "%s";
                                $secFracFormat = "%0" . $timeNROD[$binx] . "d";
                                if ($timeNROD[$binx] > 0) {
                                    $epochRFC5424 = gmdate("Y-m-d\TH:i:s", $utim[$binx]) . "." .
                                        sprintf($secFracFormat, round(pow(10, $timeNROD[$binx]) * 
                                        ($utim[$binx] - floor($utim[$binx])))) . "Z";
                                } else {
                                    $epochRFC5424 = gmdate("Y-m-d\TH:i:s", $utim[$binx]) . "Z";
                                }
                                fprintf($flog, "Current record: %s\t%s\t%s\n", $epochRFC5424, 
                                sprintf($lonFormat, $lon[$binx]), 
                                sprintf($latFormat, $lat[$binx])); 
                                
                            }  // end if verbose
                            //---------- END VERBOSE ------------//
                            
                        }  // end if non-sequential times
                        
                    } // if bad epoch
                    
                    if ($binx < $maxBuffer) {  
                        // Still room in buffer--keep reading file.
                        $binx++;
                    } else {  
                        // Buffer full--process it before continuing with file read.
                        
                        $iter = 1;
                        $speedReasonableAll = false;
                        $accelReasonableAll = false;
                        
                        //---------- BEGIN VERBOSE ------------//
                        if ($verbose) {
                            
                            if ($timeNROD[$inx] > 0) {
                                $epochRFC5424 = gmdate("Y-m-d\TH:i:s", $utim[1]) . "." .
                                    sprintf($secFracFormat, round(pow(10, $timeNROD[$inx]) * ($utim[$inx] - floor($utim[$inx])))) . "Z";	      
                            } else {
                                $epochRFC5424 = gmdate("Y-m-d\TH:i:s", $utim[1]) . "Z";
                            }
                            fprintf( $flog, "Start time of buffer: %s\n", $epochRFC5424);
                            $endx = count($utim) - 1;
                            if ($timeNROD[$inx] > 0) {
                                $epochRFC5424 = gmdate("Y-m-d\TH:i:s", $utim[$endx]) . "." .
                                    sprintf($secFracFormat, round(pow(10, $timeNROD[$inx]) * ($utim[$inx] - floor($utim[$inx])))) . "Z";	      
                            } else {
                                $epochRFC5424 = gmdate("Y-m-d\TH:i:s", $utim[$endx]) . "Z";
                            }
                            fprintf($flog, "End time of buffer: %s\n", $epochRFC5424); 
                            
                        }  // end if verbose
                        //---------- END VERBOSE ------------//
                        
                        // The following test for duplicate positions including a running median speed was
                        // developed to handle acqsys filesets produced by TN where no NMEA quality information is
                        // recorded and the computer clock is uncalibrated.  Added 2013-07-25.
                        $debug = true;
                        if ($debug) {
                            
                            //---------- BEGIN TEST BLOCK FOR CATCHING DUPLICATE POSITIONS WHILE UNDERWAY ----------//
                            // Loop over the buffer and compare current speed to the median of the 
                            // last 10 speeds.  If the current speed is zero but the median is not,
                            // flag the next position.  The intention is to catch 
                            // accelerations are reasonable.
                            //$median_speed = 0;  // Initial median speed is zero.
                            $vnx = 0;  
                            $inx = 1;
                            $inxMax = count($utim);
                            $jnxMax = $inxMax;
                            
                            while ($inx < $inxMax) {
                                
                                if ($epochOK[$inx]) {  // Skip flagged positions
                                    // Get index of next position that is ok:
                                    
                                    if ($vnx >= 11) {
                                        $median_speed = array_median(array_slice($speedHori, $vnx-11, 10));
                                    }
                                    
                                    $jnx = $inx + 1;
                                    while ($jnx < $jnxMax) {
                                        
                                        if ($epochOK[$jnx]) {
                                            
                                            if ($lon[$inx] == $lon[$jnx] 
                                                && $lat[$inx] == $lat[$jnx] 
                                                && $median_speed > $min_speed_threshold
                                            ) {
                                                $epochOK[$jnx] = false;
                                                $jnx++; 
                                            } else {
                                                break;
                                            }
                                            
                                        } else {
                                            
                                            $jnx++;
                                            
                                        } // end if next position ok
                                        
                                    } // end loop to find next ok position
                                    
                                    $speedHori[$vnx] = calcSpeedHori(
                                        $utim[$inx], $lon[$inx], $lat[$inx], 
                                        $utim[$jnx], $lon[$jnx], $lat[$jnx]
                                    );
                                    $tvel[$vnx] = 0.5 * ($utim[$jnx] + $utim[$inx]);
                                    
                                    $vnx++;
                                    
                                } // end if position ok
                                
                                $inx++;
                                
                            } // end loop over buffer
                            //---------- END TEST BLOCK FOR CATCHING DUPLICATE POSITIONS WHILE UNDERWAY ----------//
                            
                        } // end debug
                        
                        while ((!$speedReasonableAll || !$accelReasonableAll) 
                            && $iter < $maxIter
                        ) {
                            
                            //	  echo "Iteration: " . $iter . "\n";
                            
                            // Re-initialize speed and accel checks:
                            $speedReasonableAll = true;
                            $accelReasonableAll = true;
                            unset($speedHori);
                            unset($tvel);
                            unset($accelHori);
                            unset($tacc);
                            unset($delt);
                            unset($speedReasonable);
                            unset($accelReasonable);
                            
                            $vnx = 1;  // speed index
                            $inxMax = count($utim);
                            $jnxMax = $inxMax;
                            // May need to turn both for loops into while loops:
                            for ($inx=1; $inx<$inxMax; $inx++) {
                                
				// Flipped check - Webb Pinner
                                if (!$epochOK[$inx]) {  // Skip flagged positions
                                    // Get index of next position that is ok:

                                    for ($jnx=$inx+1; $jnx<$jnxMax;$jnx++) {
                                        if ($epochOK[$jnx]) {
                                            break;
                                        }
                                    }
                                    
                                    $speedHori[$vnx] = calcSpeedHori(
                                        $utim[$inx], $lon[$inx], $lat[$inx], 
                                        $utim[$jnx], $lon[$jnx], $lat[$jnx]
                                    );
                                    $tvel[$vnx] = 0.5 * ($utim[$jnx] + $utim[$inx]);
                                    
                                    // Check for two successive speeds that are 
                                    // too fast.  If so, flag the 
                                    // middle position.
                                    //		  if ($speedHori[$vnx] > $speedLimit) {
                                    //  if (isset($speedHori[$vnx-1])) {
                                    //    if ($speedHori[$vnx-1] > $speedLimit) {
                                    //		$epochOK[$inx] = false;
                                    //    }
                                    //  }
                                    // }
                                    
                                    if ($speedReasonableAll) {
                                        $speedReasonableAll = false;
                                    }
                                    $speedReasonable[$vnx] = false;
                                    
                                    //---------- BEGIN VERBOSE ------------//
                                    if ($verbose) {
                                        
                                        $secFormat = "%." . $timeNROD[$inx] . "f";
/* Commented out by Webb Pinner
                                        fprintf($flog, "Excessive Speed: %s m/s, Time increment: %s sec\n",
                                                sprintf("%.2f", $speedHori[$vnx]), 
                                                sprintf($secFormat, ($utim[$jnx] - $utim[$inx])));
*/ //Commented out by Webb Pinner
                                        $lonFormat = ($lon[$inx] != "NAN") ? ("%." . $lonNROD[$inx] . "f") : "%s";
                                        $latFormat = ($lat[$inx] != "NAN") ? ("%." . $latNROD[$inx] . "f") : "%s";
                                        $secFracFormat = "%0" . $timeNROD[$inx] . "d";
                                        $qualFormat = "%s\t%s\t%." . $hdopNROD[$inx] . "f";
                                        if ($timeNROD[$inx] > 0) {
                                            $epochRFC5424 = gmdate("Y-m-d\TH:i:s", $utim[$inx]) . "." .
                                                sprintf($secFracFormat, round(pow(10, $timeNROD[$inx]) * ($utim[$inx] - floor($utim[$inx])))) . "Z";	      
                                        } else {
                                            $epochRFC5424 = gmdate("Y-m-d\TH:i:s", $utim[$inx]) . "Z";
                                        }
                                        if (isset($alt)) {
                                            fprintf($flog, "%s\t%s\t%s\t%s\t%s\n", $epochRFC5424,
                                                    sprintf($lonFormat, $lon[$inx]), 
                                                    sprintf($latFormat, $lat[$inx]), 
                                                    sprintf($qualFormat, $qual[$inx], $nsat[$inx], $hdop[$inx]),
                                                    sprintf("%s", $alt[$inx]));
                                        } else {
                                            if (!is_null($qual[$inx])) {
                                                fprintf($flog, "%s\t%s\t%s\t%s\n", $epochRFC5424,
                                                        sprintf($lonFormat, $lon[$inx]),
                                                        sprintf($latFormat, $lat[$inx]), 
                                                        sprintf($qualFormat, $qual[$inx], $nsat[$inx], $hdop[$inx]));
                                            } else {
                                                fprintf($flog, "%s\t%s\t%s\n", $epochRFC5424,
                                                        sprintf($lonFormat, $lon[$inx]),
                                                        sprintf($latFormat, $lat[$inx])); 
                                            }
                                        }
                                        if ($timeNROD[$jnx] > 0) {
                                            $epochRFC5424 = gmdate("Y-m-d\TH:i:s", $utim[$jnx]) . "." .
                                                sprintf($secFracFormat, round(pow(10, $timeNROD[$jnx]) * ($utim[$jnx] - floor($utim[$jnx])))) . "Z";
                                        } else {
                                            $epochRFC5424 = gmdate("Y-m-d\TH:i:s", $utim[$jnx]) . "Z";
                                        }
                                        if (isset($alt)) {
                                            fprintf($flog, "%s\t%s\t%s\t%s\t%s\n", $epochRFC5424, 
                                                    sprintf($lonFormat, $lon[$jnx]),
                                                    sprintf($latFormat, $lat[$jnx]), 
                                                    sprintf($qualFormat, $qual[$jnx], $nsat[$jnx], $hdop[$jnx]),
                                                    sprintf("%s", $alt[$jnx]));
                                        } else {
                                            if (!is_null($qual[$jnx])) {
                                                fprintf($flog, "%s\t%s\t%s\t%s\n", $epochRFC5424,
                                                        sprintf($lonFormat, $lon[$jnx]),
                                                        sprintf($latFormat, $lat[$jnx]), 
                                                        sprintf($qualFormat, $qual[$jnx], $nsat[$jnx], $hdop[$jnx]));
                                            } else {
                                                fprintf($flog, "%s\t%s\t%s\n", $epochRFC5424,
                                                        sprintf($lonFormat, $lon[$jnx]),
                                                        sprintf($latFormat, $lat[$jnx]));
                                            }
                                        }

                                    } // end if verbose
                                    //---------- END VERBOSE ------------//

                                    // Insert code here to test if two successive speeds are unreasonable.

                                    //} // if excessive speed

                                    $vnx++;

                                } // end if epochOK

                            }  // end loop over utim

                            // If two successive speeds are unreasonable, then the middle position
                            // is flagged.
                            //	      $vnxMax = count($speedHori);
                            //  $jnxMax = count($utim);
                            //  for ($vnx=2; $vnx<$vnxMax; $vnx++) {
                            //		if ($speedHori[$vnx] > $speedLimit && $speedHori[$vnx-1] > $speedLimit) {
                            //	  for ($jnx=1; $jnx<=$jnxMax; $jnx++) {
                            //	    if ( ($utim[$jnx] > $tvel[$vnx-1]) && ($utim[$jnx] < $tvel[$vnx]) ) {
                            //	      $epochOK[$jnx] = false;
                            //      // Recompute speedHori[$vnx-1]:
                            //      $speedHori[$vnx-1] = calcSpeedHori( $utim[$inx], $lon[$inx], $lat[$inx], 
                            //					  $utim[$jnx], $lon[$jnx], $lat[$jnx] );
                            //      $tvel[$vnx] = 0.5 * ( $utim[$jnx] + $utim[$inx] );
                            //      array_splice( $speedHori, $vnx-1, 1 );
                            //      array_splice( $tvel, $vnx-1, 1 );
                            //    }
                            //	  }
                            //	}
                            //}
                            // Recompute speeds
                            
                            
                            $inxMax = count($speedHori);
                            $jnxMax = count($utim);
                            for ($inx=1; $inx<$inxMax; $inx++) {
                                $accelHori[$inx] = calcAccelHori(
                                    $tvel[$inx], $speedHori[$inx], 
                                    $tvel[$inx+1], $speedHori[$inx+1]
                                );
                                $tacc[$inx] = 0.5 * ( $tvel[$inx+1] + $tvel[$inx] );
                                
                                if (abs($accelHori[$inx]) > $accelLimit) {
                                    if ($accelReasonableAll) {
                                        $accelReasonableAll = false;
                                    }
                                    $accelReasonable[$inx] = false;
                                    
                                    //---------- BEGIN VERBOSE ------------//
                                    if ($verbose) {
                                        
                                        $secFormat = "%." . $timeNROD[$inx] . "f";
                                        fprintf($flog, "Excessive Accel: %s m/s^2, Time increment: %s sec\n",
                                                sprintf("%.3f", $accelHori[$inx]), 
                                                sprintf($secFormat, ($tvel[$inx+1] - $tvel[$inx])));
                                        $secFracFormat = "%0" . $timeNROD[$inx] . "d";
                                        if ($timeNROD[$inx] > 0) {
                                            $epochRFC5424 = gmdate("Y-m-d\TH:i:s", $tvel[$inx]) . "." .
                                                sprintf($secFracFormat, round(pow(10, $timeNROD[$inx]) * ($tvel[$inx] - floor($tvel[$inx])))) . "Z";	      
                                        } else {
                                            $epochRFC5424 = gmdate("Y-m-d\TH:i:s", $tvel[$inx]) . "Z";
                                        }
                                        fprintf($flog, "%s\t%s m/s\n", $epochRFC5424, sprintf("%.2f", $speedHori[$inx]));
                                        if ($timeNROD[$inx] > 0) {
                                            $epochRFC5424 = gmdate("Y-m-d\TH:i:s", $tvel[$inx+1]) . "." .
                                                sprintf($secFracFormat, round(pow(10, $timeNROD[$inx]) * ($tvel[$inx+1] - floor($tvel[$inx+1])))) . "Z";
                                        } else {
                                            $epochRFC5424 = gmdate("Y-m-d\TH:i:s", $tvel[$inx+1]) . "Z";
                                        }
                                        fprintf($flog, "%s\t%s m/s\n", $epochRFC5424, 
                                                sprintf("%.2f", $speedHori[$inx+1]));
                                        
                                    } // end if verbose
                                    //---------- END VERBOSE ------------//
                                    
                                    // Find closest position in time to time of excessive accel:
                                    for ($jnx=1; $jnx<=$jnxMax; $jnx++) {
                                        $delt[$jnx] = $tacc[$inx] - $utim[$jnx];
                                        //echo "delt [s]: " . $delt[$jnx] . 
                                        //  " tacc: " . gmdate( "Y-m-d\TH:i:s", $tacc[$inx] ) . "Z" . 
                                        //  " accelHori [m/s^2]: " . $accelHori[$inx] . 
                                        //  " utim: " . gmdate( "Y-m-d\TH:i:s", $utim[$jnx] ) . "Z" . 
                                        //  " lat:  " . $lat[$jnx] .
                                        //  " lon:  " . $lon[$jnx] . "\n";
                                        if ($jnx>1) {
                                            if (abs($delt[$jnx]) > abs($delt[$jnx-1])) {
                                                $epochOK[$jnx-1] = false;
                                                break;
                                            }
                                        }
                                    } // end loop over positions to find position of excessive accel
                                    
                                } // if excessive accel
                                
                            }  // end loop over speedHori
                            
                            $iter++;
                            
                        } // end loop over buffer
                        
                        //echo "Number of iterations: " . $iter . "\n";
                        //	exit(1);
                        
                        // Loop over the buffer and write out position records that pass all QC tests.
                        // Since the last two epochs in the buffer will be moved to the beginning of
                        // the buffer, don't write them out.
                        $inxMax = count($utim)-2;
                        for ($inx=1; $inx<=$inxMax; $inx++) {
                            $secFormat = "%." . $timeNROD[$inx] . "f";
                            $lonFormat = ($lon[$inx] != "NAN") 
                                ? ("%." . $lonNROD[$inx] . "f") : "%s";
                            $latFormat = ($lat[$inx] != "NAN") 
                                ? ("%." . $latNROD[$inx] . "f") : "%s";
                            $secFracFormat = "%0" . $timeNROD[$inx] . "d";
                            if ($timeNROD[$inx] > 0) {
                                $epochRFC5424 = gmdate("Y-m-d\TH:i:s", $utim[$inx]) . "." .
                                    sprintf($secFracFormat, round(pow(10, $timeNROD[$inx]) * ($utim[$inx] - floor($utim[$inx])))) . "Z";	      
                            } else {
                                $epochRFC5424 = gmdate("Y-m-d\TH:i:s", $utim[$inx]) . "Z";
                            }
                            if ($epochOK[$inx]) { // Print good epoch w/o flag:
                                if (isset($alt)) {
                                    fprintf($fout, "%s\t%s\t%s\t%s\t%s\t%s\t%s\n", 
                                            $epochRFC5424, 
                                            sprintf($lonFormat, $lon[$inx]), 
                                            sprintf($latFormat, $lat[$inx]), 
                                            $qual[$inx], $nsat[$inx], $hdop[$inx],
                                            $alt[$inx]);
                                } else {
                                    if (!is_null($qual[$inx])) {
                                        fprintf($fout, "%s\t%s\t%s\t%s\t%s\t%s\n", 
                                                $epochRFC5424, 
                                                sprintf($lonFormat, $lon[$inx]), 
                                                sprintf($latFormat, $lat[$inx]), 
                                                $qual[$inx], $nsat[$inx], $hdop[$inx]);		
                                    } else {
                                        fprintf($fout, "%s\t%s\t%s\n", 
                                                $epochRFC5424, 
                                                sprintf($lonFormat, $lon[$inx]), 
                                                sprintf($latFormat, $lat[$inx]));
                                    }
                                } // if alt set
                            } else { // Flag bad epoch with leading QCFLAG:
                                if (isset($alt)) {
                                    fprintf($fout, "%s\t%s\t%s\t%s\t%s\t%s\t%s\n", 
                                            QCFLAG . $epochRFC5424, 
                                            sprintf($lonFormat, $lon[$inx]), 
                                            sprintf($latFormat, $lat[$inx]), 
                                            $qual[$inx], $nsat[$inx], $hdop[$inx],
                                            $alt[$inx] );
                                } else {
                                    if (!is_null($qual[$inx])) {
                                        fprintf($fout, "%s\t%s\t%s\t%s\t%s\t%s\n", 
                                                QCFLAG . $epochRFC5424, 
                                                sprintf($lonFormat, $lon[$inx]), 
                                                sprintf($latFormat, $lat[$inx]), 
                                                $qual[$inx], $nsat[$inx], $hdop[$inx]);		
                                    } else {
                                        fprintf($fout, "%s\t%s\t%s\n", 
                                                QCFLAG . $epochRFC5424, 
                                                sprintf($lonFormat, $lon[$inx]), 
                                                sprintf($latFormat, $lat[$inx]));
                                    }
                                } // if alt set
                            } // if epochOK
                        } // end loop over positions
                        
                        // Save the last two records into the beginning of the 
                        // buffer and remove the rest:
                        $tmp1 = $timeNROD[$maxBuffer-1];
                        $tmp2 = $timeNROD[$maxBuffer];
                        unset($timeNROD);
                        $timeNROD[1] = $tmp1;
                        $timeNROD[2] = $tmp2;
                        
                        $tmp1 = $utim[$maxBuffer-1];
                        $tmp2 = $utim[$maxBuffer];      
                        unset($utim);
                        $utim[1] = $tmp1;
                        $utim[2] = $tmp2;
                        
                        $tmp1 = $lon[$maxBuffer-1];
                        $tmp2 = $lon[$maxBuffer];      
                        unset($lon);
                        $lon[1] = $tmp1;
                        $lon[2] = $tmp2;
                        
                        $tmp1 = $lonNROD[$maxBuffer-1];
                        $tmp2 = $lonNROD[$maxBuffer];      
                        unset($lonNROD);
                        $lonNROD[1] = $tmp1;
                        $lonNROD[2] = $tmp2;
                        
                        $tmp1 = $lat[$maxBuffer-1];
                        $tmp2 = $lat[$maxBuffer];      
                        unset($lat);
                        $lat[1] = $tmp1;
                        $lat[2] = $tmp2;
                        
                        $tmp1 = $latNROD[$maxBuffer-1];
                        $tmp2 = $latNROD[$maxBuffer];      
                        unset($latNROD);
                        $latNROD[1] = $tmp1;
                        $latNROD[2] = $tmp2;
                        
                        $tmp1 = $epochOK[$maxBuffer-1];
                        $tmp2 = $epochOK[$maxBuffer];
                        unset($epochOK);
                        $epochOK[1] = $tmp1;
                        $epochOK[2] = $tmp2;
                        
                        $tmp1 = $qual[$maxBuffer-1];
                        $tmp2 = $qual[$maxBuffer];
                        unset($qual);
                        $qual[1] = $tmp1;
                        $qual[2] = $tmp2;
                        
                        $tmp1 = $nsat[$maxBuffer-1];
                        $tmp2 = $nsat[$maxBuffer];
                        unset($nsat);
                        $nsat[1] = $tmp1;
                        $nsat[2] = $tmp2;
                        
                        $tmp1 = $hdop[$maxBuffer-1];
                        $tmp2 = $hdop[$maxBuffer];
                        unset($hdop);
                        $hdop[1] = $tmp1;
                        $hdop[2] = $tmp2;
                        
                        $tmp1 = $hdopNROD[$maxBuffer-1];
                        $tmp2 = $hdopNROD[$maxBuffer];
                        unset($hdopNROD);
                        $hdopNROD[1] = $tmp1;
                        $hdopNROD[2] = $tmp2;
                        
                        if (isset($alt)) {
                            $tmp1 = $alt[$maxBuffer-1];
                            $tmp2 = $alt[$maxBuffer];
                            unset($alt);
                            $alt[1] = $tmp1;
                            $alt[2] = $tmp2;
                        }
                        
                        // Speeds and accelerations will be computed once 
                        // the buffer is full, 
                        // so empty them entirely now:
                        unset($speedHori);
                        unset($accelHori);
                        unset($tvel);
                        unset($tacc);
                        unset($delt);
                        
                        // Since first two elements are loaded into buffer, 
                        // buffer index should now point to yet-to-be-loaded 
                        // 3rd element:
                        $binx = 3;
                        
                    } // end if buffer full
                    
                }  // If not obviously bad.
                
                $irec++;
                
            } // end if ($line[0] != QCFLAG && !strstr( $line, HEADER ))
            
        } // end if $line
        
    } // end while
    
    fclose($fin);
    
    //echo "Last pass.";
    //exit(1);
    
    //--------- Might have unprocessed buffer at end of file read -------//
    if (isset($utim)) {
        
        // The following test for duplicate positions including a running 
        // median speed was
        // developed to handle acqsys filesets produced by TN where no NMEA 
        // quality information is
        // recorded and the computer clock is uncalibrated.  Added 2013-07-25.
        $debug = true;
        if ($debug) {
            
            //---------- BEGIN TEST BLOCK FOR CATCHING DUPLICATE POSITIONS WHILE UNDERWAY ----------//
            // Loop over the buffer and compare current speed to the median of the 
            // last 10 speeds.  If the current speed is zero but the median is not,
            // flag the next position.  The intention is to catch 
            // accelerations are reasonable.
            //$median_speed = 0;  // Initial median speed is zero.
            $vnx = 0;
            $inx = 1;
            $inxMax = count($utim);
            $jnxMax = $inxMax;
            
            while ($inx < $inxMax) {
                
                if ($epochOK[$inx]) {  // Skip flagged positions
                    // Get index of next position that is ok:
                    
                    if ($vnx >= 11) {
                        $median_speed = array_median(array_slice($speedHori, $vnx-11, 10));
                    }
                    
                    $jnx = $inx + 1;
                    while ($jnx < $jnxMax) {
                        
                        if ($epochOK[$jnx]) {
                            
                            if ($lon[$inx] == $lon[$jnx] 
                                && $lat[$inx] == $lat[$jnx] 
                                && $median_speed > $min_speed_threshold
                            ) {
                                $epochOK[$jnx] = false;
                                $jnx++;
                            } else {
                                break;
                            }
                            
                        } else {
                            
                            $jnx++;
                            
                        } // end if next position ok
                        
                    } // end loop to find next ok position
                    
                    $speedHori[$vnx] = calcSpeedHori(
                        $utim[$inx], $lon[$inx], $lat[$inx],
                        $utim[$jnx], $lon[$jnx], $lat[$jnx]
                    );
                    $tvel[$vnx] = 0.5 * ( $utim[$jnx] + $utim[$inx] );
                    
                    $vnx++;
                    
                } // end if position ok
                
                $inx++;
                
            } // end loop over buffer
            //---------- END TEST BLOCK FOR CATCHING DUPLICATE POSITIONS WHILE UNDERWAY ----------//
            
        } // end debug
        
        
        $iter = 1;
        $speedReasonableAll = false;
        $accelReasonableAll = false;
        
        // Loop over the buffer, flagging positions, until all velocities and 
        // accelerations are reasonable.  This may take multiple passes.
        while ((!$speedReasonableAll || !$accelReasonableAll) && $iter < $maxIter) {
            
            //    echo "Iteration: " . $iter . "\n";
            
            // Re-initialize speed and accel checks:
            $speedReasonableAll = true;
            $accelReasonableAll = true;
            unset($speedHori);
            unset($tvel);
            unset($accelHori);
            unset($tacc);
            unset($delt);
            unset($speedReasonable);
            unset($accelReasonable);
            
            $vnx = 1; // speed index
            $inxMax = count($utim);
            $jnxMax = $inxMax;
            for ($inx=1; $inx<$inxMax; $inx++) {
                if ($epochOK[$inx]) {  // Skip flagged positions
                    // Get index of next position that is ok:
                    for ($jnx=$inx+1; $jnx<$jnxMax;$jnx++) {
                        if ($epochOK[$jnx]) {
                            break;
                        }
                    }
                    $speedHori[$vnx] = calcSpeedHori(
                        $utim[$inx], $lon[$inx], $lat[$inx], 
                        $utim[$jnx], $lon[$jnx], $lat[$jnx]
                    );
                    $tvel[$vnx] = 0.5 * ( $utim[$jnx] + $utim[$inx] );

                    if ($speedHori[$vnx] > $speedLimit) {
                        if ($speedReasonableAll) {
                            $speedReasonableAll = false;
                        }
                        $speedReasonable[$vnx] = false;
                        
                        //---------- BEGIN VERBOSE ------------//
                        if ($verbose) {
                            
                            $secFormat = "%." . $timeNROD[$inx] . "f";
                            fprintf($flog, "Excessive Speed: %s m/s, Time increment: %s sec\n", 
                                    sprintf("%.2f", $speedHori[$vnx]),  
                                    sprintf($secFormat, ($utim[$jnx] - $utim[$inx])) );
                            $lonFormat = ($lon[$inx] != "NAN") ? ("%." . $lonNROD[$inx] . "f") : "%s";
                            $latFormat = ($lat[$inx] != "NAN") ? ("%." . $latNROD[$inx] . "f") :"%s";
                            $secFracFormat = "%0" . $timeNROD[$inx] . "d";
                            if ($timeNROD[$inx] > 0) {
                                $epochRFC5424 = gmdate("Y-m-d\TH:i:s", $utim[$inx]) . "." .
                                    sprintf($secFracFormat, round(pow(10, $timeNROD[$inx]) * ($utim[$inx] - floor($utim[$inx])))) . "Z";
                            } else {
                                $epochRFC5424 = gmdate("Y-m-d\TH:i:s", $utim[$inx]) . "Z";
                            }
                            fprintf($flog, "%s\t%s\t%s\n", $epochRFC5424, 
                                    sprintf($lonFormat, $lon[$inx]), 
                                    sprintf($latFormat, $lat[$inx]));
                            if ($timeNROD[$jnx] > 0) {
                                $epochRFC5424 = gmdate("Y-m-d\TH:i:s", $utim[$jnx]) . "." .
                                    sprintf($secFracFormat, round(pow(10, $timeNROD[$jnx]) * ($utim[$jnx] - floor($utim[$jnx])))) . "Z";
                            } else {
                                $epochRFC5424 = gmdate("Y-m-d\TH:i:s", $utim[$jnx]) . "Z";
                            }
                            fprintf($flog, "%s\t%s\t%s\n", $epochRFC5424, 
                                    sprintf($lonFormat, $lon[$jnx]), 
                                    sprintf($latFormat, $lat[$jnx]));
                            
                        } // end if verbose
                        //---------- END VERBOSE ------------//
                        
                    } // if excessive speed
                    $vnx++;
                } // end if epochOK
            }  // end loop over utim
            
            $inxMax = count($speedHori);
            for ($inx=1; $inx<$inxMax; $inx++) {
                $accelHori[$inx] = calcAccelHori(
                    $tvel[$inx], $speedHori[$inx], 
                    $tvel[$inx+1], $speedHori[$inx+1]
                );
                $tacc[$inx] = 0.5 * ( $tvel[$inx+1] + $tvel[$inx] );
                
                if (abs($accelHori[$inx]) > $accelLimit) {
                    if ($accelReasonableAll) {
                        $accelReasonableAll = false;
                    }
                    $accelReasonable[$inx] = false;
                    
                    //---------- BEGIN VERBOSE ------------//
                    if ($verbose) {
                        
                        $secFormat = "%." . $timeNROD[$inx] . "f";
                        fprintf($flog, "Excessive Accel: %s m/s^2, Time increment: %s sec\n", 
                                sprintf("%.3f", $accelHori[$inx]), 
                                sprintf($secFormat, ($tvel[$inx+1] - $tvel[$inx])));
                        $secFracFormat = "%0" . $timeNROD[$inx] . "d";
                        if ($timeNROD[$inx] > 0) {
                            $epochRFC5424 = gmdate("Y-m-d\TH:i:s", $tvel[$inx]) . "." .
                                sprintf($secFracFormat, round(pow(10, $timeNROD[$inx]) * ($tvel[$inx] - floor($tvel[$inx])))) . "Z";	      
                        } else {
                            $epochRFC5424 = gmdate("Y-m-d\TH:i:s", $tvel[$inx]) . "Z";
                        }
                        fprintf($flog, "%s\t%s m/s\n", $epochRFC5424, 
                                sprintf("%.2f", $speedHori[$inx]));
                        if ($timeNROD[$inx] > 0) {
                            $epochRFC5424 = gmdate("Y-m-d\TH:i:s", $tvel[$inx+1]) . "." .
                                sprintf($secFracFormat, round(pow(10, $timeNROD[$inx]) * ($tvel[$inx+1] - floor($tvel[$inx+1])))) . "Z";
                        } else {
                            $epochRFC5424 = gmdate("Y-m-d\TH:i:s", $tvel[$inx+1]) . "Z";
                        }
                        fprintf($flog, "%s\t%s m/s\n", $epochRFC5424, 
                                sprintf("%.2f", $speedHori[$inx+1]));
                    } // end if verbose
                    //---------- END VERBOSE ------------//
                    
                    // Find closest position in time to time of excessive accel:
                    $jnxMax = count($utim);
                    for ($jnx=1; $jnx<=$jnxMax; $jnx++) {
                        $delt[$jnx] = $tacc[$inx] - $utim[$jnx];
                        //echo "delt [s]: " . $delt[$jnx] . 
                        //  " tacc: " . gmdate( "Y-m-d\TH:i:s", $tacc[$inx] ) . "Z" . 
                        //  " accelHori [m/s^2]: " . $accelHori[$inx] . 
                        //  " utim: " . gmdate( "Y-m-d\TH:i:s", $utim[$jnx] ) . "Z" . 
                        //  " lat:  " . $lat[$jnx] .
                        //  " lon:  " . $lon[$jnx] . "\n";
                        if ($jnx>1) {
                            if (abs($delt[$jnx]) > abs($delt[$jnx-1])) {
                                $epochOK[$jnx-1] = false;
                                break;
                            }
                        }
                    } // end loop over positions to find position of excessive accel
                    
                } // if excessive accel
                
            }  // end loop over speedHori
            
            $iter++;
            
        } // end loop over buffer
        
        //echo "Number of iterations: " . $iter . "\n";
        //	exit(1);
        
        // Loop over the buffer and write out position records that pass all QC tests.
        $inxMax = count($utim);
        for ($inx=1; $inx<=$inxMax; $inx++) {
            $secFormat = "%." . $timeNROD[$inx] . "f";
            $lonFormat = ($lon[$inx] != "NAN") 
                ? ("%." . $lonNROD[$inx] . "f") : "%s";
            $latFormat = ($lat[$inx] != "NAN") 
                ? ("%." . $latNROD[$inx] . "f") : "%s";
            $secFracFormat = "%0" . $timeNROD[$inx] . "d";
            if ($timeNROD[$inx] > 0) {
                $epochRFC5424 = gmdate("Y-m-d\TH:i:s", $utim[$inx]) . "." .
                    sprintf($secFracFormat, round(pow(10, $timeNROD[$inx]) * ($utim[$inx] - floor($utim[$inx])))) . "Z";	      
            } else {
                $epochRFC5424 = gmdate("Y-m-d\TH:i:s", $utim[$inx]) . "Z";      
            }
            if ($epochOK[$inx]) { // Print good epoch w/o flag:
                if (isset($alt)) {
                    fprintf($fout, "%s\t%s\t%s\t%s\t%s\t%s\t%s\n", 
                            $epochRFC5424, 
                            sprintf($lonFormat, $lon[$inx]), 
                            sprintf($latFormat, $lat[$inx]), 
                            $qual[$inx], $nsat[$inx], $hdop[$inx],
                            $alt[$inx]);
                } else {
                    if (!is_null($qual[$inx])) {
                        fprintf($fout, "%s\t%s\t%s\t%s\t%s\t%s\n", 
                                $epochRFC5424, 
                                sprintf($lonFormat, $lon[$inx]), 
                                sprintf($latFormat, $lat[$inx]), 
                                $qual[$inx], $nsat[$inx], $hdop[$inx]);
                    } else {
                        fprintf($fout, "%s\t%s\t%s\n", 
                                $epochRFC5424, 
                                sprintf($lonFormat, $lon[$inx]), 
                                sprintf($latFormat, $lat[$inx])); 
                    }
                } // if alt set
            } else { // Flag bad epoch with leading QCFLAG:
                if (isset($alt)) {
                    fprintf($fout, "%s\t%s\t%s\t%s\t%s\t%s\t%s\n", 
                            QCFLAG . $epochRFC5424, 
                            sprintf($lonFormat, $lon[$inx]), 
                            sprintf($latFormat, $lat[$inx]), 
                            $qual[$inx], $nsat[$inx], $hdop[$inx],
                            $alt[$inx]);
                } else {
                    if (!is_null($qual[$inx])) {
                        fprintf($fout, "%s\t%s\t%s\t%s\t%s\t%s\n", 
                                QCFLAG . $epochRFC5424, 
                                sprintf($lonFormat, $lon[$inx]), 
                                sprintf($latFormat, $lat[$inx]), 
                                $qual[$inx], $nsat[$inx], $hdop[$inx]);
                    } else {
                        fprintf($fout, "%s\t%s\t%s\n", 
                                QCFLAG . $epochRFC5424, 
                                sprintf($lonFormat, $lon[$inx]), 
                                sprintf($latFormat, $lat[$inx]));
                    }
                } // if alt set
            } // end if epochOK
        } // end loop over positions
        
    } // if isset($utim)
    
    //---------- Begin Cleanup ----------//
    unset($timeNROD);
    unset($utim);
    unset($lon);
    unset($lonNROD);
    unset($lat);
    unset($latNROD);
    unset($epochOK);
    unset($speedHori);
    unset($speedReasonable);
    unset($tvel);
    unset($accelHori);
    unset($accelReasonable);
    unset($tacc);
    unset($delt);
    unset($qual);
    unset($nsat);
    unset($hdop);
    unset($hdopNROD);
    if (isset($alt)) {
        unset($alt);
    }
    fclose($fout);
    //---------- End Cleanup ------------//
    
    // Successful execution:
    return true;
    
} // end function navqc()
?>
