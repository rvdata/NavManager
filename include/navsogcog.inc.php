<?php
/**
 * Define function to calculate speed-over-ground (SOG) and
 * course-over-ground (COG) and add them to the R2R Navigation BestRes
 * standard product.
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
 * Calculate speed over ground (SOG) and course over ground (COG)
 * 
 * Add SOG and COG last columns of the R2R quality controlled 
 * navigation file.
 * 
 * @param string $infile  Input file for SOG and COG
 * 
 * @param string $outfile Filename for quality controlled result
 * @param string $flog    Optional log file pointer; if not null, log file will be 
 *                         written [default: null]
 *                 
 * @return bool  Returns true on successful completion.
 */
function navsogcog($infile, $outfile, $flog = null) 
{     
    if (!file_exists($infile)) {
        echo "navsogcog(): Could not locate file: " . $infile . "\n";
        exit(1);
    }
    
    // Not currently used:
    if ($flog != null) {
        $verbose = true;
    } else {
        $verbose = false;
    }
    
    $fin = fopen($infile, "r");
    if ($fin == null) {
        echo "navsogcog(): Could not open file: " . $infile . "\n";
        exit(1);
    }
    
    $fout = fopen($outfile, "w");
    if ($fout == null) {
        echo "navsogcog(): Could not open file: " . $outfile . "\n";
        exit(1);
    }

    //----- Read header records and print them back out -----//
    for ($inx = 1; $inx < 4; $inx++) {
        $line = trim(fgets($fin));
        if (strstr($line, HEADER)) {
            fprintf($fout, "%s\n", $line);
        }
    }

    //----- Begin Initialize Variables -----//
    $maxBuffer = 86400;  // Max number of elements array can hold  
    
    $datagram = array();
    $epochOK = array();
    $utim = array("current" => null, "next" => null);
    $lon = array("current" => null, "next" => null);
    $lat = array("current" => null, "next" => null);
    //----- End Initialize Variables -----//
    
    $binx = 0;  // Buffer index
    while (!feof($fin)) {
        
        if ($binx < $maxBuffer) {  // Still room in buffer--keep reading file.
            
            if ($verbose) {
                fprintf($flog, "Filling datagram[%d]...\n", $binx);
            }
            
            $line = trim(fgets($fin));
            if ($line != "") {
                
                // Load new datagram:
                $datagram[$binx] = $line;
                
                // Check for flagged data:
                if ($line[0]==QCFLAG) {
                    $epochOK[$binx] = false;
                } else {
                    $epochOK[$binx] = true;
                    $last_known_good_position_in_buffer = $binx;
                } // end if data flagged
                
                $binx++;
                
            } // end if ($line!="")
            
        } else {  // Buffer full--process it before continuing with file read.
            
            if ($verbose) {
                fprintf($flog, "Buffer full...\n");
            }
            
            // Loop over buffer to calculate SOG and COG, where appropriate:
            $inx = 0;
            $i_current = 0; // Index to current datagram to save SOG and COG.
            $inxMax = count($datagram);
            while ($inx < $inxMax) {
                
                if ($epochOK[$inx]) {  // Use only unflagged data.
                    
                    $line = $datagram[$inx];
                    $dataRec = preg_split('/\t/', $line);
                    
                    $tmpEpochRFC5424 = $dataRec[0];
                    $tmpLon = $dataRec[1];
                    $tmpLat = $dataRec[2];
                    
                    $result = sscanf(
                        $tmpEpochRFC5424, "%d-%d-%dT%d:%d:%sZ", 
                        $year, $month, $day, $hour, $minute, $second
                    );
                    $second = trim($second, "Z");
                    
                    // If there is no current position yet, save as current position:
                    if (is_null($utim["current"])) {
                        
                        // PHP function gmmktime expects integer seconds.  
                        // Need to add fractions of second to integer second 
                        // Unix time to preserve original time precision.
                        $secondFractionOf = floatval($second) 
                            - floor(floatval($second));
                        $utim["current"] = gmmktime(
                            $hour, $minute, 
                            floor(floatval($second)), $month, $day, $year
                        ) + $secondFractionOf;
                        $lon["current"] = floatval($tmpLon);
                        $lat["current"] = floatval($tmpLat);
                        $i_current = $inx;
                        
                        if ($verbose) {
                            fprintf(
                                $flog, "This should only print once.  %d\n",
                                $i_current
                            );
                        }
                        
                    } else {  // Otherwise, save as next position:
                        
                        // PHP function gmmktime expects integer seconds.  
                        // Need to add fractions of second to integer second 
                        // Unix time to preserve original time precision.
                        $secondFractionOf = floatval($second) 
                            - floor(floatval($second));
                        $utim["next"] = gmmktime(
                            $hour, $minute, 
                            floor(floatval($second)), $month, $day, $year
                        ) + $secondFractionOf;
                        $lon["next"] = floatval($tmpLon);
                        $lat["next"] = floatval($tmpLat);
                        
                    } // end if no current position
                    
                    // If there is a next position, calculate SOG and COG:
                    if (!is_null($utim["next"])) {
                        
                        // Calculate time interval between current and next position:
                        $delt = $utim["next"] - $utim["current"];
                        
                        // Calculate instantaneous speed over ground (SOG)
						// print point if change in time is zero
                        if ($delt == 0) {
                            printf(
                                "%d\t%d\t%d\t%s\t%s\t%0.6f\t%0.6f\t%0.6f\t%0.6f\n",
                                $inx, $i_current, 
                                $last_known_good_position_in_buffer,
                                $utim["next"], $utim["current"],
                                $lon["current"], $lat["current"], 
                                $lon["next"], $lat["next"]
                            );
							$sog = 0;
                        } else {
							$sog = $distance / $delt; // [m/s]
						}
                        
                        // Calculate instantaneous course over ground (COG):
                        list($distance, $forward_azimuth, $reverse_azimuth) 
                            = vincenty(
                                $lon["current"], $lat["current"], 
                                $lon["next"], $lat["next"]
                            );

                        $cog = $forward_azimuth;  // [decimal degrees, CW from North]
                        
                        if ($verbose) {
                            $format_verbose
                                = "%d\t%d\t%d\t%d\t%s\t%0.6f\t%0.6f"
                                . "\t%0.6f\t%0.6f\t%0.3f\t%0.3f\t%0.2f\t%0.3f\n";
                            fprintf(
                                $flog, $format_verbose,
                                $i_current, $binx, $inx, 
                                $last_known_good_position_in_buffer,
                                $datagram[$i_current], 
                                $lon["current"], $lat["current"],
                                $lon["next"], $lat["next"],
                                $distance, $delt, $sog, $cog
                            );
                        }
                        
                        // Append SOG and COG to current datagram:
                        $datagram[$i_current] = sprintf(
                            "%s\t%0.2f\t%0.3f", $datagram[$i_current], $sog, $cog
                        );
                        
                        // Copy next position to current position:
                        $utim["current"] = $utim["next"];
                        $lat["current"] = $lat["next"];
                        $lon["current"] = $lon["next"];
                        $utim["next"] = null;
                        $lat["next"] = null;
                        $lon["next"] = null;
                        $i_current = $inx;
                        
                    } // end if there is a next position
                    
                }  // end if ($epochOK[$inx])
                
                $inx++;
                
            } // end loop over buffer
            
            // Print buffer to file, up to but not including last known good 
            // position:
            for ($jnx=0; $jnx<$last_known_good_position_in_buffer; $jnx++) {
                fprintf($fout, "%s\n", $datagram[$jnx]);
            }
            
            // Save last known good position through the end of the buffer:
            $datagram = array_slice($datagram, $last_known_good_position_in_buffer);
            $epochOK = array_slice($epochOK, $last_known_good_position_in_buffer);
            $binx = count($datagram);
            
            // Reached end of buffer, reinitialize current and next positions:
            $utim["current"] = null;
            $lon["current"] = null;
            $lat["current"] = null;
            $utim["next"] = null;
            $lon["next"] = null;
            $lat["next"] = null;
            
            if ($verbose) {
                fprintf($flog, "binx: %d\n", $binx);
                for ($i=0; $i<$binx; $i++) {
                    fprintf($flog, "datagram[%d]: %s\n", $i, $datagram[$i]);
                }
            }
            
        } // end if ($binx < $maxBuffer)
        
    } // end while(!$feof($fin))
    
    fclose($fin);
    
    //----- Might have unprocessed buffer at end of file read -----//
    if ($binx < $maxBuffer) {
        
        // Loop over buffer to calculate SOG and COG, where approriate:
        $inx = 0;
        $i_current = 0; // Index of current datagram to write SOG and COG.
        $inxMax = $binx;
        while ($inx < $inxMax) {
            
            if ($epochOK[$inx]) {  // Use only unflagged data.
                
                $line = $datagram[$inx];
                $dataRec = preg_split('/\t/', $line);
                
                $tmpEpochRFC5424 = $dataRec[0];
                $tmpLon = $dataRec[1];
                $tmpLat = $dataRec[2];
                
                $result = sscanf(
                    $tmpEpochRFC5424, "%d-%d-%dT%d:%d:%sZ", 
                    $year, $month, $day, $hour, $minute, $second
                );
                $second = trim($second, "Z");
                
                // If there is no current position yet, save as current position:
                if (is_null($utim["current"])) {
                    
                    // PHP function gmmktime expects integer seconds.  Need to 
                    // add fractions of second to integer second Unix time to 
                    // preserve original time precision.
                    $secondFractionOf = floatval($second) - floor(floatval($second));
                    $utim["current"] = gmmktime(
                        $hour, $minute, floor(floatval($second)), $month, $day, $year
                    ) + $secondFractionOf;
                    $lon["current"] = floatval($tmpLon);
                    $lat["current"] = floatval($tmpLat);
                    $i_current = $inx;
                    
                } else {  // Otherwise, save as next position:
                    
                    // PHP function gmmktime expects integer seconds.  Need to 
                    // add fractions of second to integer second Unix time to 
                    // preserve original time precision.
                    $secondFractionOf = floatval($second) - floor(floatval($second));
                    $utim["next"] = gmmktime(
                        $hour, $minute, floor(floatval($second)), $month, $day, $year
                    ) + $secondFractionOf;
                    $lon["next"] = floatval($tmpLon);
                    $lat["next"] = floatval($tmpLat);
                    
                } // end if no current position
                
                // If there is a next position, calculate SOG and COG:
                if (!is_null($utim["next"])) {
                    
                    // Calculate time interval between current and next position:
                    $delt = $utim["next"] - $utim["current"];
                    
                    if ($delt == 0) {
                        printf(
                            "%d\t%d\t%d\t%s\t%s\t%0.6f\t%0.6f\t%0.6f\t%0.6f\n",
                            $inx, $i_current, $last_known_good_position_in_buffer,
                            $utim["next"], $utim["current"],
                            $lon["current"], $lat["current"], 
                            $lon["next"], $lat["next"]
                        );
                    }
                    
                    // Calculate instantaneous speed over ground (SOG) and course
                    // over ground (COG):
                    list($distance, $forward_azimuth, $reverse_azimuth)
                        = vincenty(
                            $lon["current"], $lat["current"], 
                            $lon["next"], $lat["next"]
                        );
                    
                    $sog = $distance / $delt;
                    $cog = $forward_azimuth;
                    
                    if ($verbose) {
                        $format_verbose 
                            = "%d\t%d\t%d\t%s\t%0.6f\t%0.6f"
                            . "\t%0.6f\t%0.6f\t%0.3f\t%0.3f\t%0.2f\t%0.3f\n";
                        fprintf(
                            $flog, $format_verbose,
                            $i_current, $inx, $last_known_good_position_in_buffer,
                            $datagram[$i_current], 
                            $lon["current"], $lat["current"],
                            $lon["next"], $lat["next"],
                            $distance, $delt, $sog, $cog
                        );
                    }
                    
                    // Append SOG and COG to current datagram:
                    $datagram[$i_current] = sprintf(
                        "%s\t%0.2f\t%0.3f", $datagram[$i_current], $sog, $cog
                    );
                    
                    // Copy next position to current position:
                    $utim["current"] = $utim["next"];
                    $lat["current"] = $lat["next"];
                    $lon["current"] = $lon["next"];
                    $utim["next"] = null;
                    $lat["next"] = null;
                    $lon["next"] = null;
                    $i_current = $inx;
                    
                } // end if there is a next position
                
            }  // end if ($epochOK[$inx])
            
            $inx++;
            
        } // end loop over buffer
        
        // Repeat last known SOG and COG into the datagram with the last known
        // good position:
        $datagram[$last_known_good_position_in_buffer]
            = sprintf(
                "%s\t%0.2f\t%0.3f", 
                $datagram[$last_known_good_position_in_buffer], $sog, $cog
            );
        
        // Print buffer to file:
        for ($inx=0; $inx<$inxMax; $inx++) {
            fprintf($fout, "%s\n", $datagram[$inx]);
        }
        
    } // end if ($binx < $maxBuffer)
    
    //----- Begin Cleanup -----//
    unset($datagram);
    unset($epochOK);
    unset($utim);
    unset($lon);
    unset($lat);
    fclose($fout);
    //----- End Cleanup -----//
    
    // Successful execution:
    return true;
    
} // end function navsogcog()
?>
