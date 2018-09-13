<?php
/**
 * Define function to sub-sample the R2R Navigation BestRes standard product
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
require_once 'globals.inc.php';
date_default_timezone_set('UTC');

/**
 * Find remainder
 * 
 * @param float $dividend dividend
 * @param float $divisor  divisor
 * 
 * @return float Return the remainder
 */
function modulo($dividend, $divisor) 
{
    // $dividend and $divisor must be positive
    
    return ($dividend - $divisor*floor($dividend/$divisor));
    
} // end function modulo()


/**
 * Sub-sample the R2R Navigation BestRes Standard Product
 *
 * @param string $infile   Input file (NavBestRes)
 * @param float  $interval Time interval between sub-sampled data 
 *                          records [s]
 * @param string $outfile  Output file (sub-sampled)
 *
 * @return bool  Returns true on successful completion.
 */
function navsample($infile, $interval, $outfile) 
{
    //----- Begin Initialize Variables -----// 
    $maxBuffer = 3600;
    
    //----- End Initialize Variables -------//
    
    if (!file_exists($infile)) {
        echo "navsample(): Could not locate file: " . $infile . "\n";
        exit(1);
    }
    
    if ($interval <= 0) {
        echo "navsample(): Interval cannot be equal to or less than zero.\n";
        exit(1);
    }
    
    $fin = fopen($infile, "r");
    if ($fin == null) {
        echo "navsample(): Could not open file: " . $infile . "\n";
        exit(1);
    }
    
    $fout = fopen($outfile, "w");
    if ($fout == null) {
        echo "navsample(): Could not open file: " . $outfile . "\n";
        exit(1);
    }
    
    //----- Begin write header records to output file -----//
    $date_created_1min = gmdate("Y-m-d\TH:i:s\Z");
    fprintf(
        $fout, 
        HEADER . " Datetime [UTC], Longitude [deg], Latitude [deg], " 
        . "Instantaneous Speed-over-ground [m/s], " 
        . "Instantaneous Course-over-ground [deg. clockwise from North]\n"
    );
    fprintf(
        $fout, 
        HEADER . " More detailed information may be found here: " 
        . "http://get.rvdata.us/format/100002/format-r2rnav.txt\n"
    );
    fprintf($fout, HEADER . " Creation date: %s\n", $date_created_1min);
    //----- End write header records to output file -----//    

    $tim = array();
    $time_nroz = array();
    $lon = array();
    $lonNROD = array();
    $lat = array();
    $latNROD = array();
    $nodetim = array();
    $qual = array();
    $nsat = array();
    $hdop = array();
    $alt = array();
    $sog = array();
    $cog = array();
    
    //  $irec = 1;  // Record number (from start of file)
    $binx = 1;  // Buffer index
    $ninx = 1;  // Node index
    //$inx = 1;
    //$delt = 10000;
    //$delt_old = 10000;
    while (!feof($fin)) {

        $line = trim(fgets($fin));
        if ($line != "") {
            
            // Skip flagged data records and header records:
            if ($line[0] != QCFLAG && !strstr($line, HEADER)) {
                
                $dataRec = preg_split("/".R2R_DELIMITER."/", $line);
                
                $tmpEpochRFC5424 = $dataRec[0];
                $tmpLon = $dataRec[1];
                $tmpLat = $dataRec[2];
                
                // Get quality information, if present:
                if (count($dataRec) >= 6) {
                    $tmpQual = $dataRec[3];
                    $tmpNsat = $dataRec[4];
                    $tmpHdop = $dataRec[5];
                    $tmpAlt  = $dataRec[6];
                }
                
                if (count($dataRec) >= 8) {
                    $tmpSog = $dataRec[7];
                    $tmpCog = $dataRec[8];
                }
                
                $result = sscanf(
                    $tmpEpochRFC5424, "%d-%d-%dT%d:%d:%sZ", 
                    $year, $month, $day, $hour, $minute, $second
                );
                $second = trim($second, "Z");
                
                // Determine time precision of current datetime:
                if (preg_match("/\./", $second)) {
                    $roz = preg_split('/\./', $second);
                    $time_nroz[$binx] = strlen($roz[1]);
                } else {
                    $time_nroz[$binx] = 0;
                }
                
                // Determine precision of current longitude:
                if (preg_match('/\./', $tmpLon)) {
                    $roz = preg_split('/\./', $tmpLon);
                    $lonNROD[$binx] = strlen($roz[1]);
                } else {
                    $lonNROD[$binx] = 0;
                }
                
                // Determine precision of current latitude:
                if (preg_match('/\./', $tmpLat)) {
                    $roz = preg_split('/\./', $tmpLat);
                    $latNROD[$binx] = strlen($roz[1]);
                } else {
                    $latNROD[$binx] = 0;
                }
                
                // PHP function gmmktime expects integer seconds.  Need to add
                // fractions of second to integer second Unix time to preserve
                // original time precision.
                if ($time_nroz[$binx] > 0) {
                    $secondFractionOf = floatval($second) - floor(floatval($second));
                    $tim[$binx] = gmmktime(
                        $hour, $minute, floor(floatval($second)), $month, $day, $year
                    ) + $secondFractionOf;
                } else {
                    $tim[$binx] = gmmktime(
                        $hour, $minute, floor(floatval($second)), $month, $day, $year
                    );
                }
                $lon[$binx] = floatval($tmpLon);
                $lat[$binx] = floatval($tmpLat);
                
                if (count($dataRec) >= 6) {
                    $qual[$binx] = ($tmpQual != "NAN") ? intval($tmpQual) : $tmpQual;
                    $nsat[$binx] = ($tmpNsat != "NAN") ? intval($tmpNsat) : $tmpNsat;
                    if (preg_match('/\./', $tmpHdop)) {
                        $roz = preg_split('/\./', $tmpHdop);
                        $hdopNROD[$binx] = strlen($roz[1]);
                    } else {
                        $hdopNROD[$binx] = 0;
                    }
                    $hdop[$binx] = ($tmpHdop != "NAN") 
                        ? floatval($tmpHdop) : $tmpHdop;
                    $alt[$binx] = $tmpAlt;
                }
                
                if (count($dataRec) >= 8) {
                    $sog[$binx] = $tmpSog;
                    $cog[$binx] = $tmpCog;
                }
                
                // Create temporary candidate node based on current time:
                $nodetmp = gmmktime($hour, $minute, 0, $month, $day, $year);
                
                // If candidate node is new, then save it.  If it's a repeat, 
                // ignore it.
                if ($ninx == 1) {
                    $nodetim[$ninx] = $nodetmp;
                } else {
                    if ($nodetmp > $nodetim[$ninx-1]) {
                        $nodetim[$ninx] = $nodetmp;
                    }
                }
                
                // Compare current time to node time:
                // Current time is an exact node match.
                if ($tim[$binx] == $nodetim[$ninx]) {
                    
                    // Print current time:
                    $lonFormat = "%." . $lonNROD[$binx] . "f";
                    $latFormat = "%." . $latNROD[$binx] . "f";
                    $secFracFormat = "%0" . $time_nroz[$binx] . "d";
                    if ($time_nroz[$binx] > 0) {
                        $epochRFC5424 = gmdate("Y-m-d\TH:i:s", $tim[$binx]) . "." .
                            sprintf(
                                $secFracFormat, round(
                                    pow(10, $time_nroz[$binx]) 
                                    * ($tim[$binx] - floor($tim[$binx]))
                                )
                            ) . "Z";
                    } else {
                        $epochRFC5424 = gmdate("Y-m-d\TH:i:s", $tim[$binx])
                            . "Z";            
                    }
                    if (isset($sog[$binx]) && isset($cog[$binx])) {
                        fprintf(
                            $fout, "%s%s%s%s%s%s%s%s%s\n",
                            $epochRFC5424, R2R_DELIMITER,
                            sprintf($lonFormat, $lon[$binx]), R2R_DELIMITER,
                            sprintf($latFormat, $lat[$binx]), R2R_DELIMITER,
                            $sog[$binx], R2R_DELIMITER,
                            $cog[$binx]
                        );
                    } else {
                        fprintf(
                            $fout, "%s%s%s%s%s\n",
                            $epochRFC5424, R2R_DELIMITER,
                            sprintf($lonFormat, $lon[$binx]), R2R_DELIMITER,
                            sprintf($latFormat, $lat[$binx])
                        );
                    }
                    $ninx++; // Advance the node index.
                    
                } else { // Not exact match - Determine if close enough
                    
                    // If node is bracketed between current and previous times:
                    if (($tim[$binx] > $nodetim[$ninx]) 
                        && ($tim[$binx-1] < $nodetim[$ninx])
                    ) {
                        
                        $delt1 = $tim[$binx] - $nodetim[$ninx];
                        $delt2 = $nodetim[$ninx] - $tim[$binx-1];
                        
                        if ($delt1 < $delt2) { // Current time is closer
                            
                            // Current time is within half the interval to the node.
                            if ($delt1 <= intval($interval/2) - 1) {
                                // Print current time:
                                $lonFormat = "%." . $lonNROD[$binx] . "f";
                                $latFormat = "%." . $latNROD[$binx] . "f";
                                $secFracFormat = "%0" . $time_nroz[$binx] . "d";
                                if ($time_nroz[$binx] > 0) {
                                    $epochRFC5424 
                                        = gmdate("Y-m-d\TH:i:s", $tim[$binx]) . "." 
                                            . sprintf(
                                                $secFracFormat, 
                                                round(
                                                    pow(10, $time_nroz[$binx]) 
                                                    * ($tim[$binx] 
                                                    - floor($tim[$binx]))
                                                )
                                            ) . "Z";
                                } else {
                                    $epochRFC5424 
                                        = gmdate("Y-m-d\TH:i:s", $tim[$binx])
                                            . "Z";            
                                }
                                if (isset($sog[$binx]) && isset($cog[$binx])) {
                                    fprintf(
                                        $fout, "%s%s%s%s%s%s%s%s%s\n",
                                        $epochRFC5424, R2R_DELIMITER,
                                        sprintf($lonFormat, $lon[$binx]), R2R_DELIMITER,
                                        sprintf($latFormat, $lat[$binx]), R2R_DELIMITER,
                                        $sog[$binx], R2R_DELIMITER,
                                        $cog[$binx]
                                    );
                                } else {
                                    fprintf(
                                        $fout, "%s%s%s%s%s\n",
                                        $epochRFC5424, R2R_DELIMITER,
                                        sprintf($lonFormat, $lon[$binx]), R2R_DELIMITER,
                                        sprintf($latFormat, $lat[$binx])
                                    );
                                }
                                $ninx++; // Advance the node index.
                            }
                            
                        } else { // Previous time is closer
                            
                            // Previous time is within half the interval to the node.
                            if ($delt2 <= intval($interval/2)) {
                                // Print previous time:
                                $lonFormat = "%." . $lonNROD[$binx-1] . "f";
                                $latFormat = "%." . $latNROD[$binx-1] . "f";
                                $secFracFormat = "%0" . $time_nroz[$binx-1] . "d";
                                if ($time_nroz[$binx-1] > 0) {
                                    $epochRFC5424 
                                        = gmdate("Y-m-d\TH:i:s", $tim[$binx-1]) . "."
                                            . sprintf(
                                                $secFracFormat, 
                                                round(
                                                    pow(10, $time_nroz[$binx-1]) 
                                                    * ($tim[$binx-1] 
                                                    - floor($tim[$binx-1]))
                                                )
                                            ) . "Z";
                                } else {
                                    $epochRFC5424 
                                        = gmdate("Y-m-d\TH:i:s", $tim[$binx-1])
                                            . "Z";            
                                }
                                if (isset($sog[$binx-1]) && isset($cog[$binx-1])) {
                                    fprintf(
                                        $fout, "%s%s%s%s%s%s%s%s%s\n",
                                        $epochRFC5424, R2R_DELIMITER,
                                        sprintf($lonFormat, $lon[$binx-1]), R2R_DELIMITER,
                                        sprintf($latFormat, $lat[$binx-1]), R2R_DELIMITER,
                                        $sog[$binx-1], R2R_DELIMITER,
                                        $cog[$binx-1]
                                    );
                                } else {
                                    fprintf(
                                        $fout, "%s%s%s%s%s\n",
                                        $epochRFC5424, R2R_DELIMITER,
                                        sprintf($lonFormat, $lon[$binx-1]), R2R_DELIMITER,
                                        sprintf($latFormat, $lat[$binx-1])
                                    );
                                }
                                $ninx++; // Advance the node index.
                            }
                            
                        } // end if current time is closer	    
                        
                    } // end if node is bracketed by current and previous times
                    
                } // end if exact match
                
                // Still room in buffer--keep reading file.
                if ($binx < $maxBuffer) {
                    $binx++;
                } else {  // Buffer full--wrap around to beginning of buffer.
                    $binx = 1;
                }
                
            } // end if ($line[0] != QCFLAG && !strstr( $line, HEADER ))
            
        } // end if $line
        
    } // end while
    
    fclose($fin);
    
    //----- Begin Cleanup -----//
    unset($tim);
    unset($time_nroz);
    unset($nodetim);
    unset($lon);
    unset($lonNROD);
    unset($lat);
    unset($latNROD);
    unset($qual);
    unset($nsat);
    unset($hdop);
    unset($hdopNROD);
    unset($alt);
    unset($sog);
    unset($cog);
    fclose($fout);
    //----- End Cleanup -----//
    
    // Successful execution:
    return true;
    
} // end function navsample()
?>
