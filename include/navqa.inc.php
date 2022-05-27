<?php
/**
 * Define function to quality assess the navigation rawdata.
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

class Statistics 
{
    public $timeNROD, $numberOutOfSequence;
    public $epochInterval, $epochIntervalNROD, $longestEpochGap;
    public $maxNumEpochs, $actualNumEpochs, $numberOfEpochsFlagged; 
    public $numberOfLongGaps;
    public $numberOfLargeSpeeds, $numberOfLargeAccelerations, $numBadQualityGPS;
    public $maxHDOP, $minHDOP, $maxNumSatellites, $minNumSatellites; 
    public $maxAlt, $minAlt;
    public $speedHoriMax, $speedHoriMin, $accelHoriMax, $accelHoriMin;
    public $actualCountableNumEpochs;
}

class GeoBounds 
{
    public $westBoundLongitude, $eastBoundLongitude; 
    public $southBoundLatitude, $northBoundLatitude;
}

class MinMax 
{
    public $min, $max;
}

class DateTimeObject 
{
    public $year, $month, $day, $hour, $minute, $second;
}

/**
 * Compare two arrays of values=>occurences, combine them, 
 * and sort the result.
 * 
 * @param array $old Old array of values and occurences
 * @param array $new New array of values and occurences
 *
 * @return array Return combined, sorted array of values and occurences
 */
function add($old, $new) 
{   
    foreach ($new as $key => $value) {
        if (array_key_exists($key, $old)) {
            $old[$key] += $value;
        } else {
            $old[$key] = $value;
        }
    }

    arsort($old);
    
    return $old;   
}


/**
 * Find the mode value(s) for an array of values and occurences
 *
 * @param array $counts Array of values and occurences
 *
 * @return array|int Mode value(s)
 */
function array_mode($counts) 
{
    // Returns the mode value(s) for an array of values=>occurences:
    
    $modes = array_keys($counts, current($counts), true);
    
    // Only one modal value:
    if (count($modes) === 1) return $modes[0];
    
    // Multiple modal values:
    return $modes;
    
}


/**
 * Returns an array of values=>occurences, in descending 
 * order of occurences.  If old array of values=>occurences exists, 
 * combine this with the new result.
 *
 * @param array $set        The array of values to determine the mode.
 * @param array $old_counts Optional old array of values and occurences
 *
 * @return array
 */
function counter(array $set, $old_counts = null) 
{      
    $counts = @array_count_values($set);
    arsort($counts); // Sort counts in descending order
    // $counts: keys are modes, values are occurences
    
    if (!is_null($old_counts)) {
        $counts = add($old_counts, $counts);
    }
    
    return $counts;
} // end counter()


/**
 * Assess the quality of navigation rawdata
 *
 * Purpose: Determine the duration and range of values present in
 * navigation files and assess the quality of these data.
 *
 * @param string   $infile             Input filename for assessment
 * @param string   $dateStringUTCStart Start datetime (RFC-2524)
 * @param string   $dateStringUTCEnd   End datetime (RFC-2524)
 * @param float    $speedLimit         Maximum horizontal speed [m/s] 
 *                                      to test against
 * @param float    $accelLimit         Maximum horizontal acceleration
 *                                      [m/s^2] to test against
 * @param float    $gapThreshold       Data gaps lasting longer than 
 *                                      this threshold [s] will be 
 *                                      reported
 * @param float    $portLongitudeStart [-180, 180) [decimal degree]
 * @param float    $portLatitudeStart  [-90, 90] [decimal degree]
 * @param float    $portLongitudeEnd   [-180, 180) [decimal degreee]
 * @param float    $portLatitudeEnd    [-90, 90] [decimal degree]
 *
 * @param resource $flog               Optional log file pointer; if 
 *                                      not null, log file will be 
 *                                      written [default: null]
 * 
 * @return object Returns a quality assessment object.
 */
function navqa(
    $infile, $dateStringUTCStart, $dateStringUTCEnd, $speedLimit, $accelLimit, 
    $gapThreshold, $portLongitudeStart, $portLatitudeStart, 
    $portLongitudeEnd, $portLatitudeEnd, $flog = null
) {   
    //----- Begin Initialize Variables -----//
    $maxBuffer = 3600;  // Max number of elements array can hold
    
	$duration_and_range_of_values = new stdClass();
	$duration_and_range_of_values->Epoch_Interval = new stdClass();
	$duration_and_range_of_values->Maximum_Altitude = new stdClass();
	$duration_and_range_of_values->Minimum_Altitude = new stdClass();
	$duration_and_range_of_values->Minimum_Horizontal_Speed = new stdClass();
	$duration_and_range_of_values->Maximum_Horizontal_Speed = new stdClass();
	$duration_and_range_of_values->Minimum_Horizontal_Acceleration = new stdClass();
	$duration_and_range_of_values->Maximum_Horizontal_Acceleration = new stdClass();
	$quality_assessment = new stdClass();
	 $quality_assessment->longest_epoch_gap = new stdClass();
	$qac = new stdClass();

    $stats = new Statistics;
    
    $stats->timeNROD = 0; // Time least count: Number of digits to right of decimal
    $stats->numberOutOfSequence = 0;  // number of records out of sequence
    $stats->epochInterval = 31449600; // [1 yr in seconds]
    $stats->epochIntervalNROD = 3;
    $stats->longestEpochGap = 0;
    $stats->maxNumEpochs = 0;
    $stats->actualNumEpochs = 0;
    $stats->actualCountableNumEpochs = 1;
    $stats->numberOfEpochsFlagged = 0;
    $stats->numberOfLongGaps = 0;
    $stats->numberOfLargeSpeeds = 0;
    $stats->numberOfLargeAccelerations = 0;
    
    // NMEA GGA-specific quality information:
    $stats->numBadQualityGPS = 0;
    $stats->maxHDOP = 0;
    $stats->minHDOP = 10000;
    $stats->maxNumSatellites = 0;
    $stats->minNumSatellites = 10000;
    $stats->maxAlt = -10000;
    $stats->minAlt =  10000;
    
    $stats->speedHoriMax = -10000;
    $stats->speedHoriMin =  10000;
    $stats->accelHoriMax = -10000;
    $stats->accelHoriMin =  10000;
    
    $numQualityNAN = 0;
    //----- End Initialize Variables -----//
    
    if (!file_exists($infile)) {
        echo "navqa(): Could not locate file: " . $infile . "\n";
        exit(1);
    }
    
    if ($speedLimit <= 0) {
        echo "navqa(): Speed limit cannot be equal to or less than zero.\n";
        exit(1);
    }
    
    if ($accelLimit <= 0) {
        echo "navqa:() Acceleration limit cannot be equal to or less than zero.\n";
        exit(1);
    }
    
    if ($gapThreshold <= 0) {
        echo "navqa(): Gap threshold cannot be equal to or less than zero.\n";
        exit(1);
    }
    
    if ($flog != null) {
        $verbose = true;
    } else {
        $verbose = false;
    }
    
    $fin = fopen($infile, "r");
    if ($fin == null) {
        echo "navqa(): Could not open file: " . $infile . "\n";
        exit(1);
    }
    
    $utim = array();
    $last_good_utim = 0;
    $lon = array();
    $lonNROD = array();
    $lat = array();
    $latNROD = array();
    $haveFirstEpoch = false;
    $firstEpoch = array();
    $lastEpoch = array();
    $delt = array();
    $deltNROD = array();
    $counts = null;
    $countsNROD = null;
    
    //----- Determine the time interval between data records -----//
    //----- ("epoch interval") -----//
    // Read datetimes in files once to determine the modal time increment, 
    // then read the files again to do quality assessment:
    $irec = 1;  // Record number (from start of file)
    $binx = 1;  // Buffer index
    while (!feof($fin)) {
        
        $line = trim(fgets($fin));
        if ($line != "") {
            
            // Skip flagged data records and header records:
            if ($line[0] != QCFLAG && !strstr($line, HEADER)) {
                
                $dataRec = preg_split('/\t/', $line);
                
                $tmpEpochRFC5424 = $dataRec[0];
                $tmpLon = $dataRec[1];
                $tmpLat = $dataRec[2];
                
                // Skip data records with invalid datetimes or NAN positions:
                if (valid_datetime($tmpEpochRFC5424, $dateStringUTCStart, $dateStringUTCEnd) 
                    && $tmpLon !== "NAN" && $tmpLat !== "NAN"
                ) {
                    
                    $result = sscanf(
                        $tmpEpochRFC5424, "%d-%d-%dT%d:%d:%sZ", 
                        $year, $month, $day, $hour, $minute, $second
                    );
                    $second = trim($second, "Z");
                    
                    if (preg_match('/\./', $second)) {
                        $roz = preg_split('/\./', $second);
                        $timeNROD = strlen($roz[1]);
                        $stats->timeNROD = max($stats->timeNROD, $timeNROD);
                    } else {
                        $timeNROD = 0;
                    }
                    
                    // PHP function gmmktime expects integer seconds.  Need to
                    // add fractions of second to integer second Unix time to 
                    // preserve original time precision.
                    $secondFractionOf = floatval($second) - floor(floatval($second));
                    $utim[$binx] = gmmktime(
                        $hour, $minute, floor(floatval($second)), $month, $day, $year
                    ) + $secondFractionOf;
                    
                    if ($binx > 1) {
                        
                        // Check for sequential times:
                        // 2592000 sec = 3600 * 24 * 30 = 1 month
                        if ($utim[$binx] > $utim[$binx-1] 
                            && (($utim[$binx] - $utim[$binx-1]) <= 2592000)
                        ) {
                            
                            // Accumulate array of time increments.  Will later use 
                            // modal time increment as the reporting interval.  Note:
                            // This algorithms fails if the reporting interval is 
                            // faster than 1 millisec (i.e. > 1 kHz).
                            // [integer millisec]
                            $delt[] = intval(1e3 * ($utim[$binx] - $utim[$binx-1]));
                            $deltNROD[] = $timeNROD;
                            
                        }
                        
                    } // if ($binx > 1)
                    
                    // Still room in buffer--keep reading file.
                    if ($binx < $maxBuffer) {
                        $binx++;
                    } else {  
                        // Buffer full--process it before continuing with file read.
                        
                        // Check to make sure $delt is defined.  On rare occasions
                        // the clock time may be stuck for more than $maxBuffer 
                        // records (e.g. MGL1211).
                        if (!empty($delt)) {
                            
                            // Determine the modal epoch interval:
                            $counts = counter($delt, $counts);
                            //	  print_r($counts);
                            unset($delt);
                            
                            // Determine the modal time precision (NROD):
                            $countsNROD = counter($deltNROD, $countsNROD);
                            //          print_r($counts);
                            unset($deltNROD);
                            
                        } // end if (!$empty($delt))
                        
                        // Save the last two records into the beginning of the 
                        // buffer and remove the rest:
                        $tmp1 = $utim[$maxBuffer-1];
                        $tmp2 = $utim[$maxBuffer];      
                        unset($utim);
                        $utim[1] = $tmp1;
                        $utim[2] = $tmp2;
                        
                        // Since first two elements are loaded into buffer, 
                        // buffer index should now point to yet-to-be-loaded 
                        // 3rd element:
                        $binx = 3;
                        
                    }  // end if buffer full
                    
                } // end if (valid_datetime( $tmpEpochRFC5424, 
                  //          $dateStringUTCStart, $dateStringUTCEnd ))
                
            } // end if ($line[0] != QCFLAG && !strstr( $line, HEADER ))
            
        } // end if $line
        
    } // end while
    
    unset($utim);
    
    // Start over at start of file:
    rewind($fin);
    
    //  if (!empty($delt)) {
    // May have unprocessed buffer at end of file:
    $counts = counter($delt, $counts);
    $mode = array_mode($counts);
    
    echo "navqa(): Interval [s] -> Number of Occurrences: \n";
    foreach ($counts as $interval=>$frequency) {
        echo "navqa(): ", $interval/1e3, " s -> ", $frequency, "\n";
    }
    
    //  }
    
    // Determine the modal epoch interval:
    if ($mode) {
        if (count($mode) === 1) {
            $stats->epochInterval = $mode / 1e3;
        } else {
            $stats->epochInterval = $mode[0] / 1e3;
        }
    } else {
        $stats->epochInterval = 50000;
    }
    unset($delt);
    
    // May have unprocessed buffer at end of file:
    $countsNROD = counter($deltNROD, $countsNROD);
    $modeNROD = array_mode($countsNROD);
    
    // Determine the modal time precision:
    if ($modeNROD) {
        if (count($modeNROD) === 1) {
            $stats->epochIntervalNROD = $modeNROD;
        } else {
            $stats->epochIntervalNROD = $modeNROD[0];
        }
    } else {
        $stats->epochIntervalNROD = 0;
    }
    unset($deltNROD);
    
    //  echo "epoch time precision [sec]: ", $stats->epochIntervalNROD, "\n";
    //exit(1);
    
    // Read the file again to determine the number of countable epochs:
    $delt = array();
    $irec = 1;  // Record number (from start of file)
    $binx = 1;  // Buffer index
    while (!feof($fin)) {
        
        $line = trim(fgets($fin));
        if ($line != "") {
            
            // Skip flagged data records and header records:
            if ($line[0] != QCFLAG && !strstr($line, HEADER)) {
                
                $dataRec = preg_split('/\t/', $line);
                
                $tmpEpochRFC5424 = $dataRec[0];
                $tmpLon = $dataRec[1];
                $tmpLat = $dataRec[2];
                
                // Skip data records with invalid datetimes or NAN positions:
                if (valid_datetime($tmpEpochRFC5424, $dateStringUTCStart, $dateStringUTCEnd) 
                    && $tmpLon !== "NAN" && $tmpLat !== "NAN"
                ) {
                    
                    $result = sscanf(
                        $tmpEpochRFC5424, "%d-%d-%dT%d:%d:%sZ", 
                        $year, $month, $day, $hour, $minute, $second
                    );
                    $second = trim($second, "Z");
                    
                    if (preg_match('/\./', $second)) {
                        $roz = preg_split('/\./', $second);
                        $stats->timeNROD = max($stats->timeNROD, strlen($roz[1]));
                    }
                    
                    // PHP function gmmktime expects integer seconds.  Need to
                    // add fractions of second to integer second Unix time to 
                    // preserve original time precision.
                    $secondFractionOf = floatval($second) - floor(floatval($second));
                    $utim[$binx] = gmmktime(
                        $hour, $minute, floor(floatval($second)), $month, $day, $year
                    ) + $secondFractionOf;
                    
                    if ($binx > 1) {
                        
                        // Check for sequential times:
                        // 2592000 sec = 3600 * 24 * 30 = 1 month
                        if ($utim[$binx] > $utim[$binx-1] 
                            && (($utim[$binx] - $utim[$binx-1]) <= 2592000)
                        ) {
                            
                            // Accumulate array of time increments.  Will later use
                            // modal time increment as the reporting interval.  Note:
                            // This algorithms fails if the reporting interval is 
                            // faster than 1 millisec (i.e. > 1 kHz).
                            // [integer millisec]
                            $delt[] = intval(1e3 * ($utim[$binx] - $utim[$binx-1]));
                            
                        }
                        
                    } // if ($binx > 1)

                    // Still room in buffer--keep reading file.
                    if ($binx < $maxBuffer) {
                        $binx++;
                    } else {
                        // Buffer full--process it before continuing with file read.
                        
                        // Determine the number of countable epochs toward 
                        // % completeness:
                        $inx = 0;
                        $mnx = count($delt);
                        while ($inx < $mnx) {
                            
                            if (intval($delt[$inx])/1e3 >= $stats->epochInterval) {
                                $stats->actualCountableNumEpochs++;
                                $inx++;
                            } else {
                                
                                $bigd = $delt[$inx];
                                while (intval($bigd)/1e3 < $stats->epochInterval 
                                    && $inx < $mnx
                                ) {
                                    $inx++;
                                    $bigd += $delt[$inx];
                                }
                                if (intval($bigd)/1e3 >= $stats->epochInterval) {
                                    $stats->actualCountableNumEpochs++;
                                }
                                $inx++;
                                
                            }
                            
                        } // end while ($inx < $mnx)
                        
                        // echo "actual countable num epochs: ", 
                        //     $stats->actualCountableNumEpochs,"\n";
                        unset($delt);
                        
                        // Save the last two records into the beginning of the 
                        // buffer and remove the rest:
                        $tmp1 = $utim[$maxBuffer-1];
                        $tmp2 = $utim[$maxBuffer];      
                        unset($utim);
                        $utim[1] = $tmp1;
                        $utim[2] = $tmp2;
                        
                        // Since first two elements are loaded into buffer, 
                        // buffer index should now point to yet-to-be-loaded 3rd
                        // element:
                        $binx = 3;
                        
                    }  // end if buffer full
                    
                } // end if (valid_datetime( $tmpEpochRFC5424, 
                  //         $dateStringUTCStart, $dateStringUTCEnd ))
                
            } // end if ($line[0] != QCFLAG && !strstr( $line, HEADER ))
            
        } // end if $line
        
    } // end while
    
    // May have unprocessed buffer at end of loop:
    // Determine the number of countable epochs toward % completeness:
    $inx = 0;
    $mnx = count($delt);
    while ($inx < $mnx) {
        
        if (intval($delt[$inx]) >= $stats->epochInterval) {
            $stats->actualCountableNumEpochs++;
            $inx++;
        } else {
            
            $bigd = $delt[$inx];
            while (intval($bigd) < $stats->epochInterval && $inx < $mnx) {
                $inx++;
                $bigd += $delt[$inx];
            }
            if (intval($bigd) >= $stats->epochInterval) {
                $stats->actualCountableNumEpochs++;
            }
            $inx++;
            
        }
        
    } // end while ($inx < $mnx)
    
    unset($delt);
    
    //  echo "epoch interval [sec]: ", $stats->epochInterval, "\n";
    //echo "actual countable num epochs: ", $stats->actualCountableNumEpochs, "\n";
    //exit(1);
    
    // Cleanup:
    unset($utim);
    
    // Start over at start of file:
    rewind($fin);
    
    //----- Read file to do quality assessment -----//
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
                
                // Get NMEA quality information, if present:
                if (count($dataRec) == 6) {
                    $tmpQual = $dataRec[3];
                    $tmpNsat = $dataRec[4];
                    $tmpHdop = $dataRec[5];
                } else {
                    if (count($dataRec) >= 7) {
                        $tmpQual = $dataRec[3];
                        $tmpNsat = $dataRec[4];
                        $tmpHdop = $dataRec[5];
                        $tmpAlt  = $dataRec[6];
                    }
                }
                
                $result = sscanf(
                    $tmpEpochRFC5424, "%d-%d-%dT%d:%d:%sZ", 
                    $year, $month, $day, $hour, $minute, $second
                );
                $second = trim($second, "Z");
                
                if (preg_match('/\./', $second)) {
                    $roz = preg_split('/\./', $second);
                    $timeNROD = strlen($roz[1]);
                    $stats->timeNROD = max($stats->timeNROD, $timeNROD);
                } else {
                    $timeNROD = 0;
                }
                
                $not_junk = (!(($hour==0) && ($minute==0) && ($second==0) 
                    && ($tmpLon==0) && ($tmpLat==0)));
                
                if (valid_datetime($tmpEpochRFC5424, $dateStringUTCStart, $dateStringUTCEnd) 
                    && $not_junk
                ) {
                    if ($haveFirstEpoch == false) { 
                        // Save the first epoch with valid datetime:
                        $firstEpoch["year"] = $year;
                        $firstEpoch["month"] = $month;
                        $firstEpoch["day"] = $day;
                        $firstEpoch["hour"] = $hour;
                        $firstEpoch["minute"] = $minute;
                        $firstEpoch["second"] = floatval($second);
                        $firstEpoch["lat"] = ($tmpLat != "NAN") 
                            ? floatval($tmpLat) : $tmpLat ;
                        $firstEpoch["lon"] = ($tmpLon != "NAN") 
                            ? floatval($tmpLon) : $tmpLon ;
                        $firstEpoch["timeNROD"] = $timeNROD;
                        $haveFirstEpoch = true;
                        // Initialize last epoch:
                        $lastEpoch["year"] = $year;
                        $lastEpoch["month"] = $month;
                        $lastEpoch["day"] = $day;
                        $lastEpoch["hour"] = $hour;
                        $lastEpoch["minute"] = $minute;
                        $lastEpoch["second"] = floatval($second);
                        $lastEpoch["lat"] = ($tmpLat != "NAN") 
                            ? floatval($tmpLat) : $tmpLat ;
                        $lastEpoch["lon"] = ($tmpLon != "NAN") 
                            ? floatval($tmpLon) : $tmpLon ;
                        $lastEpoch["timeNROD"] = $timeNROD;
                    } else { 
                        // Update the last epoch with the current valid datetime:
                        $lastEpochRFC5424 = sprintf(
                            "%d-%d-%dT%d:%d:%sZ",
                            $lastEpoch["year"], $lastEpoch["month"], 
                            $lastEpoch["day"], 
                            $lastEpoch["hour"], $lastEpoch["minute"], 
                            $lastEpoch["second"]
                        );
                        if (strtotime($tmpEpochRFC5424) > strtotime($lastEpochRFC5424)) {
                            $lastEpoch["year"] = $year;
                            $lastEpoch["month"] = $month;
                            $lastEpoch["day"] = $day;
                            $lastEpoch["hour"] = $hour;
                            $lastEpoch["minute"] = $minute;
                            $lastEpoch["second"] = floatval($second);
                            $lastEpoch["lat"] = ($tmpLat != "NAN") 
                                ? floatval($tmpLat) : $tmpLat ;
                            $lastEpoch["lon"] = ($tmpLon != "NAN") 
                                ? floatval($tmpLon) : $tmpLon ;
                            $lastEpoch["timeNROD"] = $timeNROD;
                        } // end if current time is greater than last saved 
                          // epoch time  
                    } // end if $haveFirstEpoch
                } // end if valid_datetime()
                
                if (preg_match('/\./', $tmpLon)) {
                    $roz = preg_split('/\./', $tmpLon);
                    $lonNROD[$binx] = strlen($roz[1]);
                    $lonFormat = "%." . $lonNROD[$binx] . "f";
                }
                
                if (preg_match('/\./', $tmpLat)) {
                    $roz = preg_split('/\./', $tmpLat);
                    $latNROD[$binx] = strlen($roz[1]);
                    $latFormat = "%." . $latNROD[$binx] . "f";
                }      
                
                //echo $lonNROD[$binx] . ", " . $latNROD[$binx];
                //      exit(1);
                
                // PHP function gmmktime expects integer seconds.  Need to add
                // fractions of second to integer second Unix time to preserve 
                // original time precision.
                $secondFractionOf = floatval($second) - floor(floatval($second));
                $utim[$binx] = gmmktime(
                    $hour, $minute, floor(floatval($second)), $month, $day, $year
                ) + $secondFractionOf;
                $lon[$binx] = ($tmpLon != "NAN") ? floatval($tmpLon) : $tmpLon;
                $lat[$binx] = ($tmpLat != "NAN") ? floatval($tmpLat) : $tmpLat;
                
                if (count($dataRec) >= 6) {
                    $qual[$binx] = ($tmpQual != "NAN") ? intval($tmpQual) : $tmpQual;
                    $nsat[$binx] = ($tmpNsat != "NAN") ? intval($tmpNsat) : $tmpNsat;
                    if (preg_match('/\./', $tmpHdop)) {
                        $roz = preg_split('/\./', $tmpHdop);
                        $hdopNROD[$binx] = strlen($roz[1]);
                    }
                    $hdop[$binx] = ($tmpHdop != "NAN") 
                        ? floatval($tmpHdop) : $tmpHdop; 
                    if (count($dataRec) >= 7) {
                        $alt[$binx] = ($tmpAlt != "NAN") 
                            ? floatval($tmpAlt) : $tmpAlt;
                        if ($alt[$binx] != "NAN") {
                            $stats->maxAlt = max($stats->maxAlt, $alt[$binx]);
                            $stats->minAlt = min($stats->minAlt, $alt[$binx]);	
                        }
                    }
                } else {  // if no NMEA quality information present:
                    $qual[$binx] = "NAN";
                    $nset[$binx] = "NAN";
                    $hdop[$binx] = "NAN";
                    $alt[$binx]  = "NAN";
                } // end if NMEA quality information present
                
                if (count($dataRec) >= 6) {
                  
                    // GPS Quality Indicator: 1=valid, 2=DGPS, 4=RTK(int), 5=RTK(float)
                    // everything else is bad.
                    if ($qual[$binx]!=1 && $qual[$binx]!=2 && $qual[$binx] !=4 && $qual[$binx] !=5) {
                        $stats->numBadQualityGPS++;
                    }
                    if ($qual[$binx] == "NAN") {
                        $numQualityNAN++;
                    }
                    
                } // end if NMEA quality information present
                
                if ($hdop[$binx] != "NAN") {
                    $stats->maxHDOP = max($stats->maxHDOP, $hdop[$binx]);
                    $stats->minHDOP = min($stats->minHDOP, $hdop[$binx]);
                }
                if ($nsat[$binx] != "NAN") {
                    $stats->maxNumSatellites = max(
                        $stats->maxNumSatellites, $nsat[$binx]
                    );
                    $stats->minNumSatellites = min(
                        $stats->minNumSatellites, $nsat[$binx]
                    );
                }
                
                $not_junk = (!(($hour==0) && ($minute==0) && ($second==0) 
                    && ($tmpLon==0) && ($tmpLat==0)));
                if (!$not_junk) {
                    $binx--;
                }
                
                // Initialize last_good_utim for determining if records are 
                // out of sequence:
                if ($binx == 1) {
                    $last_good_utim = $utim[$binx];
                }
                
                if ($binx > 1) {
                    
                    // Check for sequential times:
                    // 2592000 sec = 3600 * 24 * 30 = 1 month
                    if ($utim[$binx] > $last_good_utim 
                        && (($utim[$binx] - $last_good_utim) <= 2592000)
                    ) {
                        
                        // If NAN position, count it towards longest gap:
                        if ($lon[$binx] !== "NAN" && $lat[$binx] !== "NAN") {
                            
                            // Get time increment [sec]:
                            $tinc = $utim[$binx] - $last_good_utim;
                            
                            $stats->longestEpochGap = max(
                                $stats->longestEpochGap, $tinc
                            );
                            if ($tinc >= $gapThreshold) {
                                $stats->numberOfLongGaps++;	 
                            }
                            
                            $last_good_utim = $utim[$binx];
                            
                            // Check for sequential times:
                            //if ($utim[$binx] > $utim[$binx-1]) {
                            
                            // Get time increment [sec]:
                            //$tinc = $utim[$binx] - $utim[$binx-1];
                            
                            //$stats->longestEpochGap = max( 
                            //   $stats->longestEpochGap, $tinc 
                            //);
                            //if ($tinc >= $gapThreshold) {
                            //  $stats->numberOfLongGaps++;	 
                            // }
                            
                            if ($verbose) {
                                if ($tinc >= $gapThreshold) {
                                    $secFracFormat = "%0" . $stats->timeNROD . "d";
                                    if ($stats->timeNROD > 0) {
                                        $epoch1RFC5424 = gmdate(
                                            "Y-m-d\TH:i:s", $utim[$binx-1]
                                        ) . "." 
                                            . sprintf(
                                                $secFracFormat, 
                                                round(
                                                    pow(10, $stats->timeNROD) 
                                                    * ($utim[$binx-1] 
                                                    - floor($utim[$binx-1]))
                                                )
                                            ) . "Z";
                                    } else {
                                        $epoch1RFC5424 = gmdate(
                                            "Y-m-d\TH:i:s", $utim[$binx-1]
                                        ) . "Z";
                                    }
                                    $secFracFormat = "%0" . $stats->timeNROD . "d";
                                    if ($stats->timeNROD > 0) {
                                        $epoch2RFC5424 = gmdate(
                                            "Y-m-d\TH:i:s", $utim[$binx]
                                        ) . "." 
                                            . sprintf(
                                                $secFracFormat, 
                                                round(
                                                    pow(10, $stats->timeNROD) 
                                                    * ($utim[$binx] 
                                                    - floor($utim[$binx]))
                                                )
                                            ) . "Z";
                                    } else {
                                        $epoch2RFC5424 = gmdate(
                                            "Y-m-d\TH:i:s", $utim[$binx]
                                        ) . "Z";
                                    }
                                    fprintf(
                                        $flog, "Data gap between: %s and "
                                        . "%s, Duration [sec]: %s\n", $epoch1RFC5424,
                                        $epoch2RFC5424, 
                                        sprintf($secFracFormat, $tinc)
                                    );
                                } // end if ($tinc >= $gapThreshold)
                            } // end if ($verbose)
                            
                            // Count only sequential records that are recorded no
                            // faster than the reported epoch interval as actual
                            // records:
                            //	    if (intval($tinc) >= $stats->epochInterval) {
                            //  $stats->actualCountableNumEpochs++;
                            //}  
                            $stats->actualNumEpochs++;
                            
                        } // end if not NAN position
                        
                    } else { // Non-sequential times in file.
                        
                        if ($verbose) {
                            fprintf($flog, "Record out of sequence:\n");
                            $secFormat = "%." . $stats->timeNROD . "f";
                            $lonFormat = "%." . $lonNROD[$binx-1] . "f";
                            $latFormat = "%." . $latNROD[$binx-1] . "f";
                            $secFracFormat = "%0" . $stats->timeNROD . "d";
                            if ($stats->timeNROD > 0) {
                                $epochRFC5424 = gmdate(
                                    "Y-m-d\TH:i:s", $utim[$binx-1]
                                ) . "." 
                                    . sprintf(
                                        $secFracFormat, 
                                        round(
                                            pow(10, $stats->timeNROD) 
                                            * ($utim[$binx-1] 
                                            - floor($utim[$binx-1]))
                                        )
                                    ) . "Z";
                            } else {
                                $epochRFC5424 = gmdate(
                                    "Y-m-d\TH:i:s", $utim[$binx-1]
                                ) . "Z";
                            }
                            fprintf(
                                $flog, "Previous record: %s\t%s\t%s\n", 
                                $epochRFC5424, 
                                sprintf($lonFormat, $lon[$binx-1]), 
                                sprintf($latFormat, $lat[$binx-1])
                            ); 
                            $secFormat = "%." . $stats->timeNROD . "f";
                            $lonFormat = "%." . $lonNROD[$binx] . "f";
                            $latFormat = "%." . $latNROD[$binx] . "f";
                            $secFracFormat = "%0" . $stats->timeNROD . "d";
                            if ($stats->timeNROD > 0) {
                                $epochRFC5424 = gmdate(
                                    "Y-m-d\TH:i:s", $utim[$binx]
                                ) . "." 
                                    . sprintf(
                                        $secFracFormat, 
                                        round(
                                            pow(10, $stats->timeNROD) 
                                            * ($utim[$binx] - floor($utim[$binx]))
                                        )
                                    ) . "Z";
                            } else {
                                $epochRFC5424 = gmdate("Y-m-d\TH:i:s", $utim[$binx])
                                    . "Z";
                            }
                            fprintf(
                                $flog, "Current record:  %s\t%s\t%s\n", 
                                $epochRFC5424, 
                                sprintf($lonFormat, $lon[$binx]), 
                                sprintf($latFormat, $lat[$binx])
                            ); 
                        }  // if verbose
                        
                        $stats->numberOutOfSequence++;
                        
                    }
                    
                } // if ($binx > 1)
                
                // Still room in buffer--keep reading file.
                if ($binx < $maxBuffer) {
                    $binx++;
                } else {
                    // Buffer full--process it before continuing with file read.
                    
                    $inxMax = count($utim);
                    for ($inx=1; $inx<$inxMax; $inx++) {
                        $speedHori[$inx] = calcSpeedHori(
                            $utim[$inx], $lon[$inx], $lat[$inx], 
                            $utim[$inx+1], $lon[$inx+1], $lat[$inx+1]
                        );
                        $tvel[$inx] = 0.5 * ( $utim[$inx+1] + $utim[$inx] );
                        if ($speedHori[$inx] != "NAN") {
                            $stats->speedHoriMax = max(
                                $stats->speedHoriMax, $speedHori[$inx]
                            );
                            $stats->speedHoriMin = min(
                                $stats->speedHoriMin, $speedHori[$inx]
                            );
                        } else {
                            $speedReasonable[$inx] = false;
                        }
                        
                        if ($speedHori[$inx] > $speedLimit) {
                            $speedReasonable[$inx] = false;
                            $stats->numberOfLargeSpeeds++;
                            if ($verbose) {
                                $secFormat = "%." . $stats->timeNROD . "f";
                                fprintf(
                                    $flog, "Excessive Speed: %s m/s, "
                                    . "Time increment: %s sec\n", 
                                    sprintf("%.2f", $speedHori[$inx]), 
                                    sprintf(
                                        $secFormat, ($utim[$inx+1] - $utim[$inx])
                                    )
                                );
                                $lonFormat = "%." . $lonNROD[$inx] . "f";
                                $latFormat = "%." . $latNROD[$inx] . "f";
                                $secFracFormat = "%0" . $stats->timeNROD . "d";
                                $qualFormat = "%s\t%s\t%s";
                                if ($stats->timeNROD > 0) {
                                    $epochRFC5424 = gmdate(
                                        "Y-m-d\TH:i:s", $utim[$inx]
                                    ) . "." 
                                        . sprintf(
                                            $secFracFormat, 
                                            round(
                                                pow(10, $stats->timeNROD) 
                                                * ($utim[$inx] - floor($utim[$inx]))
                                            )
                                        ) . "Z";	      
                                } else {
                                    $epochRFC5424 = gmdate(
                                        "Y-m-d\TH:i:s", $utim[$inx]
                                    ) . "Z";
                                }
                                fprintf(
                                    $flog, "%s\t%s\t%s\t%s\n", $epochRFC5424, 
                                    sprintf($lonFormat, $lon[$inx]), 
                                    sprintf($latFormat, $lat[$inx]), 
                                    sprintf(
                                        $qualFormat, $qual[$inx], 
                                        $nsat[$inx], $hdop[$inx]
                                    )
                                );
                                if ($stats->timeNROD > 0) {
                                    $epochRFC5424 = gmdate(
                                        "Y-m-d\TH:i:s", $utim[$inx+1]
                                    ) . "." 
                                        . sprintf(
                                            $secFracFormat, 
                                            round(
                                                pow(10, $stats->timeNROD) 
                                                * ($utim[$inx+1] 
                                                - floor($utim[$inx+1]))
                                            )
                                        ) . "Z";
                                } else {
                                    $epochRFC5424 = gmdate(
                                        "Y-m-d\TH:i:s", $utim[$inx+1]
                                    ) . "Z";
                                }
                                fprintf(
                                    $flog, "%s\t%s\t%s\t%s\n", $epochRFC5424, 
                                    sprintf($lonFormat, $lon[$inx+1]), 
                                    sprintf($latFormat, $lat[$inx+1]),
                                    sprintf(
                                        $qualFormat, $qual[$inx+1], 
                                        $nsat[$inx+1], $hdop[$inx+1]
                                    )
                                );
                            }  // if ($verbose)
                        }
                    } // end loop over utim
                    
                    
                    
                    //	if ($verbose) {
                    //  echo "Number of Records Read, so far: "
                    //      . ($irec-1) . "\n";
                    //  echo "Max Horizontal Speed, so far: "
                    //      . $stats->speedHoriMax . "\n";
                    //  echo "Min Horizontal Speed, so far: "
                    //      . $stats->speedHoriMin . "\n";
                    //}	
                    
                    $inxMax = count($speedHori);
                    for ($inx=1; $inx<$inxMax; $inx++) {
                        $accelHori[$inx] = calcAccelHori(
                            $tvel[$inx], $speedHori[$inx], 
                            $tvel[$inx+1], $speedHori[$inx+1]
                        );
                        $tacc[$inx] = 0.5 * ( $tvel[$inx+1] + $tvel[$inx] );
                        if ($accelHori[$inx] != "NAN") {
                            $stats->accelHoriMax = max(
                                $stats->accelHoriMax, $accelHori[$inx]
                            );
                            $stats->accelHoriMin = min(
                                $stats->accelHoriMin, $accelHori[$inx]
                            );
                        } else {
                            $accelReasonable[$inx] = false;
                        }
                        
                        if (abs($accelHori[$inx]) > $accelLimit) {
                            $accelReasonable[$inx] = false;
                            $stats->numberOfLargeAccelerations++;
                            if ($verbose) {
                                $secFormat = "%." . $stats->timeNROD . "f";
                                fprintf(
                                    $flog, "Excessive Accel: %s m/s^2, "
                                    . "Time increment: %s sec\n", 
                                    sprintf("%.3f", $accelHori[$inx]), 
                                    sprintf(
                                        $secFormat, ($tvel[$inx+1] - $tvel[$inx])
                                    )
                                );
                                $secFracFormat = "%0" . $stats->timeNROD . "d";
                                if ($stats->timeNROD > 0) {
                                    $epochRFC5424 = gmdate(
                                        "Y-m-d\TH:i:s", $tvel[$inx]
                                    ) . "." 
                                        . sprintf(
                                            $secFracFormat, 
                                            round(
                                                pow(10, $stats->timeNROD) 
                                                * ($tvel[$inx] - floor($tvel[$inx]))
                                            )
                                        ) . "Z";
                                } else {
                                    $epochRFC5424 = gmdate(
                                        "Y-m-d\TH:i:s", $tvel[$inx]
                                    ) . "Z";
                                }
                                fprintf(
                                    $flog, "%s\t%s m/s\n", $epochRFC5424, 
                                    sprintf("%.2f", $speedHori[$inx])
                                );
                                if ($stats->timeNROD > 0) {
                                    $epochRFC5424 = gmdate(
                                        "Y-m-d\TH:i:s", $tvel[$inx+1]
                                    ) . "."
                                        . sprintf(
                                            $secFracFormat, 
                                            round(
                                                pow(10, $stats->timeNROD) 
                                                * ($tvel[$inx+1] 
                                                - floor($tvel[$inx+1]))
                                            )
                                        ) . "Z";
                                } else {
                                    $epochRFC5424 = gmdate(
                                        "Y-m-d\TH:i:s", $tvel[$inx+1]
                                    ) . "Z";
                                }
                                fprintf(
                                    $flog, "%s\t%s m/s\n", $epochRFC5424, 
                                    sprintf("%.2f", $speedHori[$inx+1])
                                );
                            }
                        } // if excessive accel
                        
                    } // end loop over speedHori[]
                    
                    //	if ($verbose) {
                    //  echo "Max Horizontal Accel, so far: "
                    //      . $stats->accelHoriMax . "\n";
                    //  echo "Min Horizontal Accel, so far: "
                    //      . $stats->accelHoriMin . "\n";
                    //}	
                    
                    //      break;
                    
                    // Save the last two records into the beginning of the
                    // buffer and remove the rest:
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
                    
                    // Speeds and accelerations will be computed once the
                    // buffer is full, 
                    // so empty them entirely now:
                    unset($speedHori);
                    unset($accelHori);
                    unset($tvel);
                    unset($tacc);
                    
                    // Since first two elements are loaded into buffer, 
                    // buffer index should now point to yet-to-be-loaded 3rd
                    // element:
                    $binx = 3;
                    
                }  // end if buffer full
                
                //}  // If not obviously bad.
                
                $irec++;
                
            } else { // Record is either a header or is flagged.
                
                // Count up flagged records:
                if ($line[0] == QCFLAG) {	
                    $stats->numberOfEpochsFlagged++;
                }
                
            } // end if ($line[0] != QCFLAG && !strstr( $line, HEADER )) {
            
        } // end if $line
        
    } // end while
    
    fclose($fin);
    
    //--------- Might have unprocessed buffer at end of file read -------//
    if (isset($utim)) {
        
        $inxMax = count($utim);
        for ($inx=1; $inx<$inxMax; $inx++) {
            $speedHori[$inx] = calcSpeedHori(
                $utim[$inx], $lon[$inx], $lat[$inx], 
                $utim[$inx+1], $lon[$inx+1], $lat[$inx+1]
            );
            $tvel[$inx] = 0.5 * ( $utim[$inx+1] + $utim[$inx] );
            if ($speedHori[$inx] != "NAN") {
                $stats->speedHoriMax = max($stats->speedHoriMax, $speedHori[$inx]);
                $stats->speedHoriMin = min($stats->speedHoriMin, $speedHori[$inx]);
            } else {
                $speedReasonable[$inx] = false;
            }
            if ($speedHori[$inx] > $speedLimit) {
                $speedReasonable[$inx] = false;
                $stats->numberOfLargeSpeeds++;
                if ($verbose) {
                    $secFormat = "%." . $stats->timeNROD . "f";
                    fprintf(
                        $flog, "Excessive Speed: %s m/s, "
                        . "Time increment: %s sec\n", 
                        sprintf("%.2f", $speedHori[$inx]),
                        sprintf($secFormat, ($utim[$inx+1] - $utim[$inx]))
                    );
                    $lonFormat = "%." . $lonNROD[$inx] . "f";
                    $latFormat = "%." . $latNROD[$inx] . "f";
                    $secFracFormat = "%0" . $stats->timeNROD . "d";
                    $qualFormat = "%s\t%s\t%s";
                    if ($stats->timeNROD > 0) {
                        $epochRFC5424 = gmdate("Y-m-d\TH:i:s", $utim[$inx]) . "." 
                            . sprintf(
                                $secFracFormat, 
                                round(
                                    pow(10, $stats->timeNROD) 
                                    * ($utim[$inx] - floor($utim[$inx]))
                                )
                            ) . "Z";
                    } else {
                        $epochRFC5424 = gmdate("Y-m-d\TH:i:s", $utim[$inx]) . "Z";
                    }
                    fprintf(
                        $flog, "%s\t%s\t%s\t%s\n", $epochRFC5424,
                        sprintf($lonFormat, $lon[$inx]), 
                        sprintf($latFormat, $lat[$inx]), 
                        sprintf($qualFormat, $qual[$inx], $nsat[$inx], $hdop[$inx])
                    );
                    if ($stats->timeNROD > 0) {
                        $epochRFC5424 = gmdate("Y-m-d\TH:i:s", $utim[$inx+1]) . "."
                            . sprintf(
                                $secFracFormat, 
                                round(
                                    pow(10, $stats->timeNROD) 
                                    * ($utim[$inx+1] - floor($utim[$inx+1]))
                                )
                            ) . "Z";
                    } else {
                        $epochRFC5424 = gmdate("Y-m-d\TH:i:s", $utim[$inx+1]) . "Z";
                    }
                    fprintf(
                        $flog, "%s\t%s\t%s\t%s\n", $epochRFC5424, 
                        sprintf($lonFormat, $lon[$inx+1]), 
                        sprintf($latFormat, $lat[$inx+1]),
                        sprintf(
                            $qualFormat, $qual[$inx+1], 
                            $nsat[$inx+1], $hdop[$inx+1]
                        )
                    );
                } // if ($verbose)
            } // end if speedHori > speedLimit
        } // end loop over utim[]
        
        //  if ($verbose) {
        //    echo "Total number of records read: " . ($irec-1) . "\n";
        //    echo "Final buffer length: " . count($utim) . "\n";
        //    echo "Max Horizontal Speed, so far: " . $stats->speedHoriMax . "\n";
        //    echo "Min Horizontal Speed, so far: " . $stats->speedHoriMin . "\n";
        //  }
        
        $inxMax = count($speedHori);
        for ($inx=1; $inx<$inxMax; $inx++) {
            $accelHori[$inx] = calcAccelHori(
                $tvel[$inx], $speedHori[$inx], 
                $tvel[$inx+1], $speedHori[$inx+1]
            );
            $tacc[$inx] = 0.5 * ( $tvel[$inx+1] + $tvel[$inx] );
            if ($accelHori[$inx] != "NAN") {
                $stats->accelHoriMax = max($stats->accelHoriMax, $accelHori[$inx]);
                $stats->accelHoriMin = min($stats->accelHoriMin, $accelHori[$inx]);
            } else {
                $accelReasonable[$inx] = false;
            }
            
            if (abs($accelHori[$inx]) > $accelLimit) {
                $accelReasonable[$inx] = false;
                $stats->numberOfLargeAccelerations++;
                if ($verbose) {
                    $secFormat = "%." . $stats->timeNROD . "f";
                    fprintf(
                        $flog, "Excessive Accel: %s m/s^2, Time increment: %s sec\n",
                        sprintf("%.3f", $accelHori[$inx]),
                        sprintf($secFormat, ($tvel[$inx+1] - $tvel[$inx]))
                    );
                    $secFracFormat = "%0" . $stats->timeNROD . "d";
                    if ($stats->timeNROD > 0) {
                        $epochRFC5424 = gmdate("Y-m-d\TH:i:s", $tvel[$inx]) . "."
                            . sprintf(
                                $secFracFormat, 
                                round(
                                    pow(10, $stats->timeNROD) 
                                    * ($tvel[$inx] - floor($tvel[$inx]))
                                )
                            ) . "Z";
                    } else {
                        $epochRFC5424 = gmdate("Y-m-d\TH:i:s", $tvel[$inx]) . "Z";
                    }
                    fprintf(
                        $flog, "%s\t%s m/s\n", $epochRFC5424, 
                        sprintf("%.2f", $speedHori[$inx])
                    );
                    if ($stats->timeNROD > 0) {
                        $epochRFC5424 = gmdate("Y-m-d\TH:i:s", $tvel[$inx+1]) . "."
                            . sprintf(
                                $secFracFormat, 
                                round(
                                    pow(10, $stats->timeNROD) 
                                    * ($tvel[$inx+1] - floor($tvel[$inx+1]))
                                )
                            ) . "Z";
                    } else {
                        $epochRFC5424 = gmdate("Y-m-d\TH:i:s", $tvel[$inx+1]) . "Z";
                    }
                    fprintf(
                        $flog, "%s\t%s m/s\n", $epochRFC5424, 
                        sprintf("%.2f", $speedHori[$inx+1])
                    );
                }
            } // if excessive accel
            
        } // end loop over speedHori
        
        //  if ($verbose) {
        //  echo "Max Horizontal Accel, so far: " . $stats->accelHoriMax . "\n";
        //  echo "Min Horizontal Accel, so far: " . $stats->accelHoriMin . "\n";
        // }
        
    } // if isset($utim)
    
    // Determine distance between port start and location when data collection began:
    list($distanceStartCollection, $fwdAz, $revAz) = vincenty(
        $portLongitudeStart, $portLatitudeStart,
        $firstEpoch["lon"], $firstEpoch["lat"]
    );
    
    // Determine distance between port end and location when data collection ended:
    list($distanceEndCollection, $fwdAz, $revAz) = vincenty(
        $portLongitudeEnd, $portLatitudeEnd,
        $lastEpoch["lon"], $lastEpoch["lat"]
    );
    
    $duration_and_range_of_values->distanceFromPortStart = $distanceStartCollection;
    $duration_and_range_of_values->distanceFromPortEnd   = $distanceEndCollection; 
    
    // PHP function gmmktime expects integer seconds.  Need to add fractions of
    // second to integer second Unix time to preserve original time precision.
    $secondFractionOf = $lastEpoch["second"] - floor($lastEpoch["second"]);
    $unixLastEpoch = gmmktime(
        $lastEpoch["hour"], $lastEpoch["minute"], 
        floor($lastEpoch["second"]), $lastEpoch["month"], 
        $lastEpoch["day"], $lastEpoch["year"]
    ) + $secondFractionOf;
    $secondFractionOf = $firstEpoch["second"] - floor($firstEpoch["second"]);
    $unixFirstEpoch = gmmktime(
        $firstEpoch["hour"], $firstEpoch["minute"], 
        floor($firstEpoch["second"]), $firstEpoch["month"], 
        $firstEpoch["day"], $firstEpoch["year"]
    ) + $secondFractionOf;
    
    $stats->maxNumEpochs = floor(
        1 + ($unixLastEpoch - $unixFirstEpoch) / $stats->epochInterval
    );
    
    //---------- Begin Report Statistics ----------//
    $secFormat = "%." . $stats->epochIntervalNROD . "f";
    $secFracFormat = "%0" . $stats->epochIntervalNROD . "d";
    
    //echo "navqa(): secFormat: ", $secFormat, "\n";
    
    // Create report objects:
    if ($firstEpoch["timeNROD"] > 0) {
        $secFracFormatFirst = "%0" . $firstEpoch["timeNROD"] . "d";
        $First_Epoch = sprintf(
            "%s.%sZ", gmdate("Y-m-d\TH:i:s", $unixFirstEpoch),
            sprintf(
                $secFracFormatFirst, 
                round(
                    pow(10, $firstEpoch["timeNROD"]) 
                    * ($unixFirstEpoch - floor($unixFirstEpoch))
                )
            )
        );
    } else {
        $First_Epoch = sprintf("%sZ", gmdate("Y-m-d\TH:i:s", $unixFirstEpoch));
    }
    if ($lastEpoch["timeNROD"] > 0) {
        $secFracFormatLast = "%0" . $lastEpoch["timeNROD"] . "d";
        $Last_Epoch = sprintf(
            "%s.%sZ", gmdate("Y-m-d\TH:i:s", $unixLastEpoch),
            sprintf(
                $secFracFormatLast, 
                round(
                    pow(10, $lastEpoch["timeNROD"]) 
                    * ($unixLastEpoch - floor($unixLastEpoch))
                )
            )
        );
    } else {
        $Last_Epoch = sprintf("%sZ", gmdate("Y-m-d\TH:i:s", $unixLastEpoch));
    }
    $duration_and_range_of_values->First_Epoch = $First_Epoch;
    $duration_and_range_of_values->Last_Epoch  = $Last_Epoch;
    
    $duration_and_range_of_values->Epoch_Interval->uom = "s";
    $duration_and_range_of_values->Epoch_Interval->value 
        = sprintf($secFormat, $stats->epochInterval);
    
    $duration_and_range_of_values->Possible_Number_of_Epochs_with_Observations 
        = $stats->maxNumEpochs;
    $duration_and_range_of_values->Actual_Number_of_Epochs_with_Observations 
        = $stats->actualNumEpochs;
    $duration_and_range_of_values->Actual_Countable_Number_of_Epoch_with_Observations
        = $stats->actualCountableNumEpochs;
    $duration_and_range_of_values->Absent_Number_of_Epochs_with_Observations
        = $stats->maxNumEpochs - $stats->actualNumEpochs 
        - $stats->numberOfEpochsFlagged;
    $duration_and_range_of_values->Flagged_Number_of_Epochs_with_Observations
        = $stats->numberOfEpochsFlagged;
    
    if ($stats->maxNumSatellites != 0) {
        $duration_and_range_of_values->Maximum_Number_of_Satellites
            = $stats->maxNumSatellites;
    } else {
        $duration_and_range_of_values->Maximum_Number_of_Satellites = null; 
    }
    if ($stats->minNumSatellites != 10000) {
        $duration_and_range_of_values->Minimum_Number_of_Satellites
            = $stats->minNumSatellites;
    } else {
        $duration_and_range_of_values->Minimum_Number_of_Satellites = null;
    }
    
    if ($stats->maxHDOP != 0) {
        $duration_and_range_of_values->Maximum_HDOP = $stats->maxHDOP;
    } else {
        $duration_and_range_of_values->Maximum_HDOP = null;
    }
    if ($stats->minHDOP != 10000) {
        $duration_and_range_of_values->Minimum_HDOP = $stats->minHDOP;
    } else {
        $duration_and_range_of_values->Minimum_HDOP = null;
    }
    
    if ($stats->maxAlt != -10000) {
        $duration_and_range_of_values->Maximum_Altitude->uom = "m";
        $duration_and_range_of_values->Maximum_Altitude->value = $stats->maxAlt;
    } else {
        $duration_and_range_of_values->Maximum_Altitude->uom = "m";
        $duration_and_range_of_values->Maximum_Altitude->value = null;
    }
    if ($stats->minAlt != 10000) {
        $duration_and_range_of_values->Minimum_Altitude->uom = "m";
        $duration_and_range_of_values->Minimum_Altitude->value = $stats->minAlt;
    } else {
        $duration_and_range_of_values->Minimum_Altitude->uom = "m";
        $duration_and_range_of_values->Minimum_Altitude->value = null;
    }
    
    $duration_and_range_of_values->Maximum_Horizontal_Speed->uom = "m/s";
    if ($stats->speedHoriMax != "-10000") {
        $duration_and_range_of_values->Maximum_Horizontal_Speed->value 
            = sprintf("%.2f", $stats->speedHoriMax);
    } else {
        $duration_and_range_of_values->Maximum_Horizontal_Speed->value = null;
    }
    
    $duration_and_range_of_values->Minimum_Horizontal_Speed->uom = "m/s";
    if ($stats->speedHoriMin != "10000") {
        $duration_and_range_of_values->Minimum_Horizontal_Speed->value
            = sprintf("%.2f", $stats->speedHoriMin);
    } else {
        $duration_and_range_of_values->Minimum_Horizontal_Speed->value = null;
    }
    
    $duration_and_range_of_values->Maximum_Horizontal_Acceleration->uom = "m/s^2";
    if ($stats->accelHoriMax != "-10000") {
        $duration_and_range_of_values->Maximum_Horizontal_Acceleration->value
            = sprintf("%.3f", $stats->accelHoriMax);
    } else {
        $duration_and_range_of_values->Maximum_Horizontal_Acceleration->value = null;
    }
    
    $duration_and_range_of_values->Minimum_Horizontal_Acceleration->uom = "m/s^2";
    if ($stats->accelHoriMin != "10000") {
        $duration_and_range_of_values->Minimum_Horizontal_Acceleration->value
            = sprintf("%.3f", $stats->accelHoriMin);
    } else {
        $duration_and_range_of_values->Minimum_Horizontal_Acceleration->value = null;
    }
    
    $quality_assessment->longest_epoch_gap->uom = "s";
    $quality_assessment->longest_epoch_gap->value
        = sprintf($secFormat, $stats->longestEpochGap);
    
    $quality_assessment->number_of_gaps_longer_than_threshold 
        = $stats->numberOfLongGaps;
    $quality_assessment->number_of_epochs_out_of_sequence 
        = $stats->numberOutOfSequence;
    if ($numQualityNAN != $stats->numBadQualityGPS) {
        $quality_assessment->number_of_epochs_with_bad_gps_quality_indicator
            = $stats->numBadQualityGPS;
    } else {
        $quality_assessment->number_of_epochs_with_bad_gps_quality_indicator = null;
    }
    $quality_assessment->number_of_horizontal_speeds_exceeding_threshold
        = $stats->numberOfLargeSpeeds;
    $quality_assessment->number_of_horizontal_accelerations_exceeding_threshold
        = $stats->numberOfLargeAccelerations;
    $quality_assessment->percent_completeness = sprintf(
        "%0.2f", 100.0 * $stats->actualCountableNumEpochs / $stats->maxNumEpochs
    );
    
    $qac->duration_and_range_of_values = $duration_and_range_of_values;
    $qac->quality_assessment = $quality_assessment;
    
    //  print_r($qac);
    //---------- End Report Statistics ----------//
    
    //---------- Begin Cleanup ----------//
    unset($utim);
    unset($lon);
    unset($lonNROD);
    unset($lat);
    unset($latNROD);
    unset($speedHori);
    unset($speedReasonable);
    unset($tvel);
    unset($accelHori);
    unset($accelReasonable);
    unset($tacc);
    unset($qual);
    unset($nsat);
    unset($hdop);
    unset($hdopNROD);
    if (isset($alt)) {
        unset($alt);
    }
    //---------- End Cleanup ------------//
    
    return $qac;

} // end function navqa()
?>
