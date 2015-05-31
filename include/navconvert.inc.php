<?php
require_once 'flags.inc.php';
date_default_timezone_set('UTC');

function modulo($dividend, $divisor) 
{
    // $dividend and $divisor must be positive
    
    return ($dividend - $divisor*floor($dividend/$divisor));
    
} // end function modulo()


function navconvert($infile, $outfile) 
{
    // Purpose: Convert R2R Navigation Standard format into
    //          MB-System nav format 1: 
    //            Unix time  Longitude  Latitude
    //          where Longitude [-180,180] and Latitude [-90,90] are 
    //          decimal degrees
    //
    // Input: infile: Input file (NavBestRes)
    //        outfile: Output file (converted, with flagged positions removed)
    //
    // Returns true on successful completion.
    
    //----- Begin Initialize Variables -----// 
    $maxBuffer = 3600;
    
    $stats->timeNROD = 0; // Time least count: Number of digits to right of decimal
    
    //----- End Initialize Variables -----//
    
    if (!file_exists($infile)) {
        echo "navconvert(): Could not locate file: " . $infile . "\n";
        exit(1);
    }
    
    $fin = fopen($infile, "r");
    if ($fin == null) {
        echo "navconvert(): Could not open file: " . $infile . "\n";
        exit(1);
    }
    
    $fout = fopen($outfile, "w");
    if ($fout == null) {
        echo "navconvert(): Could not open file: " . $outfile . "\n";
        exit(1);
    }
    
    $tim = array();
    $lon = array();
    $lonNROD = array();
    $lat = array();
    $latNROD = array();
    $haveFirstEpoch = false;
    
    $irec = 1;  // Record number (from start of file)
    $binx = 1;  // Buffer index
    $jinx = 1;
    $inx = 1;
    $delt = 10000;
    $delt_old = 10000;
    while (!feof($fin)) {
        
        $line = trim(fgets($fin));
        if ($line != "") {
            
            // Skip flagged data records and header records:
            if ($line[0] != QCFLAG && !strstr($line, HEADER)) {
                
                $dataRec = preg_split('/\t/', $line);
                
                $tmpEpochRFC5424 = $dataRec[0];
                $tmpLon = $dataRec[1];
                $tmpLat = $dataRec[2];
                
                // Get quality information, if present:
                if (count($dataRec) >= 6) {
                    $tmpQual = $dataRec[3];
                    $tmpNsat = $dataRec[4];
                    $tmpHdop = $dataRec[5];
                }
                
                $result = sscanf(
                    $tmpEpochRFC5424, "%d-%d-%dT%d:%d:%sZ", 
                    $year, $month, $day, $hour, $minute, $second
                );
                $second = trim($second, "Z");
                
                // if (!(($hour==0) && ($minute==0) && ($second==0) 
                //    && ($tmpLon==0) && ($tmpLat==0))
                // ) {
                
                $epochFlags[$binx] = 0;
                
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
                    $stats->timeNROD = max($stats->timeNROD, strlen($roz[1]));
                } else {
                    $stats->timeNROD = 0;
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
                
                //echo $lonNROD[$binx] . ", " . $latNROD[$binx];
                //      exit(1);
                
                // PHP function gmmktime expects integer seconds.  Need to add 
                // fractions of second to integer second Unix time to preserve
                // original time precision.
                if ($stats->timeNROD > 0) {
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
                    $qual[$binx] = intval($tmpQual);
                    $nsat[$binx] = intval($tmpNsat);
                    if (preg_match('/\./', $tmpHdop)) {
                        $roz = preg_split('/\./', $tmpHdop);
                        $hdopNROD[$binx] = strlen($roz[1]);
                    } else {
                        $hdopNROD[$binx] = 0;
                    }
                    $hdop[$binx] = floatval($tmpHdop);
                }
                
                $lonFormat = "%." . $lonNROD[$binx] . "f";
                $latFormat = "%." . $latNROD[$binx] . "f";
                $secFracFormat = "%0" . $stats->timeNROD . "d";
                fprintf(
                    $fout, "%s\t%s\t%s\n", 
                    $tim[$binx], 
                    sprintf($lonFormat, $lon[$binx]),
                    sprintf($latFormat, $lat[$binx])
                );	  

                // Still room in buffer--keep reading file.
                if ($binx < $maxBuffer) { 
                    $binx++;
                } else {  // Buffer full--wrap around to beginning of buffer.
                    $binx = 1;
                }
                
                //}  // If not obviously bad.
                
                $irec++;
                
                //    if ($irec > 300) {
                //  break;
                // }
                
            } // if ($line[0] != QCFLAG && !strstr( $line, HEADER ))
            
        } // end if $line
        
    } // end while
    
    fclose($fin);
    
    //----- Begin Cleanup -----//
    unset($tim);
    unset($lon);
    unset($lonNROD);
    unset($lat);
    unset($latNROD);
    unset($qual);
    unset($nsat);
    unset($hdop);
    unset($hdopNROD);
    //unset($alt);
    fclose($fout);
    //----- End Cleanup -----//
    
    // Successful execution:
    return true;
    
} // end function navconvert()
?>
