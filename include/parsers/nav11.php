<?php

function parse_nav11($navfilelist, $datapath, $fout) {

    //----------- Initialize variables: -----------//
    $maxBuffer = 86400;  // Max number of elements array can hold
    $gpsBuffer = array();
    $pc = new stdClass();
    $dateBufferLast = new stdClass();  // Initially unknown date.
    $nmea = new NMEA0183Message();
    $gga = new NMEA0183_GGA();

    $irec = 1;  // Record number (from start of file)
    $binx = 1;  // gga buffer index
    //----------- End Initialize Variables ---------//

    // Need to loop over all nav files in a cruise, in the order specified
    // by external control file.
    foreach ($navfilelist as $line) {

        if ($line == "") break;
        $lineRec = preg_split("/[\s]+/", $line);
        $filename = $datapath . "/" . $lineRec[0];
        $fid = fopen($filename, 'r');

        //----------- Loop Over Contents of Single File ----------//
        while (!feof($fid)) {

            // Get NMEA message:
            $line = trim(fgets($fid));

            // Skip over non-data records.  Records start with 2-digit month [00-12]
            if (preg_match('/GGA/', $line)) {

                $NavRec = preg_split('/\s+/', $line);

                // Read external datetime stamp:
                $mm_dd_yyyy = preg_split("/\//", $NavRec[0]);
                $pc->month = $mm_dd_yyyy[0];
                $pc->day   = $mm_dd_yyyy[1];
                $pc->year  = $mm_dd_yyyy[2];

                $hh_mm_ss_ms = preg_split("/\:/", $NavRec[1]);
                $pc->hour = $hh_mm_ss_ms[0];
                $pc->minute = $hh_mm_ss_ms[1];
                $pc->second = $hh_mm_ss_ms[2] + $hh_mm_ss_ms[3];
                $pc->hhmmss = 1e4*$pc->hour + 1e2*$pc->minute + $pc->second;

                // Read GPS receiver data:
                $hhmmss = $NavRec[2];
                $lat_deg = $NavRec[3];
                $lat_min = $NavRec[4];
                if (preg_match('/\./', $NavRec[4])) {
                    $roz = preg_split('/\./', $NavRec[4]);
                    $lat_nroz = strlen($roz[1]);
                } else {
                    $lat_nroz = 0;
                }
                $lat_format = "%02d%0" . (3 + $lat_nroz) . "." . $lat_nroz . "f";
                $northsouth = $NavRec[5];
                $lon_deg = $NavRec[6];
                $lon_min = $NavRec[7];
                if (preg_match('/\./', $NavRec[7])) {
                    $roz = preg_split('/\./', $NavRec[7]);
                    $lon_nroz = strlen($roz[1]);
                } else {
                    $lon_nroz = 0;
                }
                $lon_format = "%03d%0" . (3 + $lon_nroz) . "." . $lon_nroz . "f";
                $eastwest = $NavRec[8];
                $alt = $NavRec[9];
                $geoid_height = $NavRec[10];
                $hdop = $NavRec[11];
                $nsat = $NavRec[12];
                $qual = $NavRec[13];
                $gga_tag = $NavRec[14];

                // Reassemble the GGA string from white-space-separated GPS receiver data:
                $nmeaString = $gga_tag . ',' . $hhmmss . ',' .
                    sprintf($lat_format, $lat_deg, $lat_min) . ',' . $northsouth . ',' .
                    sprintf($lon_format, $lon_deg, $lon_min) . ',' . $eastwest . ',' .
                    $qual . ',' . $nsat . ',' . $hdop . ',' . $alt . ',M' . $geoid_height . ',M' . "\n";

                //      echo $nmeaString;
                //exit(1);

                $nmea->init($nmeaString);

                // Is checksum valid?  (We allow data without checksums to be processed.)
                if ((is_null($nmea->suppliedCheckSum)) || ($nmea->validCheckSum)) {

                    $NavRec = preg_split('/\,/', $nmea->data);
                    //echo "NavRec: " . $line . "\n";

                    // Do we have a GGA message?
                    if (preg_match('/^\$.{2}GGA$/', $NavRec[0])) {

                        //echo "Found GGA.\n";
                        // Process NMEA message as a GGA message:
                        //$gga->init( $NavRec );

                        // Save GPS fix to buffer:
                        //$gpsBuffer[$binx]->gga = clone $gga;
                        $gpsBuffer[$binx] = new stdClass();
                        $gpsBuffer[$binx]->gga = new NMEA0183_GGA();
                        $gpsBuffer[$binx]->gga->init($NavRec);

                        // For the moment, assume GGA day and PC day are the same.  We will check this
                        // and make corrections to the day once the GPS buffer is full.

                        $delt = $pc->hhmmss - $gpsBuffer[$binx]->gga->hhmmss;

                        if ($pc->hhmmss < $gpsBuffer[$binx]->gga->hhmmss && $delt < -120000) {
                            // GPS belongs to previous day.
                            $dateUnix = strtotime("-1 day", gmmktime(0, 0, 0, $pc->month, $pc->day, $pc->year));
                            $dateString = gmdate("Y-m-d", $dateUnix);
                            $dateArray = preg_split("/\-/", $dateString);

                            $gpsBuffer[$binx]->gga->year  = $dateArray[0];
                            $gpsBuffer[$binx]->gga->month = $dateArray[1];
                            $gpsBuffer[$binx]->gga->day   = $dateArray[2];

                        } else { // GPS belongs to same day as PC  clock.

                            $gpsBuffer[$binx]->gga->year  = $pc->year;
                            $gpsBuffer[$binx]->gga->month = $pc->month;
                            $gpsBuffer[$binx]->gga->day   = $pc->day;

                        }

                        // Process buffer if it is full.
                        if ($binx < $maxBuffer) {
                            // Still room in buffer--keep reading file.
                            $binx++;
                        } else {
                            // Buffer full--process it before continuing with file read.

                            for ($inx=1; $inx<=$maxBuffer - 1; $inx++) {

                                $delt = $gpsBuffer[$inx+1]->gga->hhmmss - $gpsBuffer[$inx]->gga->hhmmss;

                                if ($gpsBuffer[$inx+1]->gga->hhmmss < $gpsBuffer[$inx]->gga->hhmmss && $delt < -120000) {
                                    // Date has advanced.  Convert date to unix time, add 1 day,
                                    // and convert back to date:
                                    $dateUnix = strtotime( "+1 day", gmmktime(0, 0, 0, $gpsBuffer[$inx]->gga->month, $gpsBuffer[$inx]->gga->day, $gpsBuffer[$inx]->gga->year) );
                                    $dateString = gmdate("Y-m-d", $dateUnix);
                                    $dateArray = preg_split("/\-/", $dateString);

                                    $gpsBuffer[$inx+1]->gga->year  = $dateArray[0];
                                    $gpsBuffer[$inx+1]->gga->month = $dateArray[1];
                                    $gpsBuffer[$inx+1]->gga->day   = $dateArray[2];

                                } else {  // Still the same day.

                                    $gpsBuffer[$inx+1]->gga->year  = $gpsBuffer[$inx]->gga->year;
                                    $gpsBuffer[$inx+1]->gga->month = $gpsBuffer[$inx]->gga->month;
                                    $gpsBuffer[$inx+1]->gga->day   = $gpsBuffer[$inx]->gga->day;

                                } // end if date has advanced.

                                // Print dated-GGA:
                                printBuffer( $fout, $gpsBuffer, $inx );

                            } // end for loop over GPS buffer

                            $linx = count($gpsBuffer);
                            // Hold onto last date/time:
                            $dateBufferLast->year   = $gpsBuffer[$linx]->gga->year;
                            $dateBufferLast->month  = $gpsBuffer[$linx]->gga->month;
                            $dateBufferLast->day    = $gpsBuffer[$linx]->gga->day;
                            $dateBufferLast->hhmmss = $gpsBuffer[$linx]->gga->hhmmss;

                            // Buffer has been printed.  Unset buffer and re-initialize
                            // GPS buffer index:
                            unset($gpsBuffer);
                            $binx = 1;

                        } // end if $binx < $maxBuffer

                    } // end identify which NMEA message type

                } // end if valid checksum (or checksum not supplied)

                $irec++;

            } // end if one and only one NMEA message in line

        } // end while (!feof($fid))
        //------------ End Loop Over Contents of Single File ----------//

        fclose($fid);

    } // end foreach($navfilelist as $line)
    //------------ End Main Loop Over All Nav Files ------------//

    //--------- Might have unprocessed buffer at end of last file read -------
    if (isset($gpsBuffer) && count($gpsBuffer)>0) {

        for ($inx=1; $inx < $binx - 1; $inx++) {

            $delt = $gpsBuffer[$inx+1]->gga->hhmmss - $gpsBuffer[$inx]->gga->hhmmss;

            if ($gpsBuffer[$inx+1]->gga->hhmmss < $gpsBuffer[$inx]->gga->hhmmss && $delt < -120000) {
                // Date has advanced.  Convert date to unix time, add 1 day,
                // and convert back to date:
                $dateUnix = strtotime( "+1 day", gmmktime(0, 0, 0, $gpsBuffer[$inx]->gga->month, $gpsBuffer[$inx]->gga->day, $gpsBuffer[$inx]->gga->year) );
                $dateString = gmdate("Y-m-d", $dateUnix);
                $dateArray = preg_split("/\-/", $dateString);

                $gpsBuffer[$inx+1]->gga->year  = $dateArray[0];
                $gpsBuffer[$inx+1]->gga->month = $dateArray[1];
                $gpsBuffer[$inx+1]->gga->day   = $dateArray[2];

            } else {  // Still the same day.

                $gpsBuffer[$inx+1]->gga->year  = $gpsBuffer[$inx]->gga->year;
                $gpsBuffer[$inx+1]->gga->month = $gpsBuffer[$inx]->gga->month;
                $gpsBuffer[$inx+1]->gga->day   = $gpsBuffer[$inx]->gga->day;

            } // end if date has advanced.

            // Print dated-GGA:
            printBuffer($fout, $gpsBuffer, $inx);

        } // end for loop over GPS buffer

        // Buffer has been printed.  Unset buffer and re-initialize
        // GPS buffer index:
        unset($gpsBuffer);
        $binx = 1;

    } // end if (isset())

}

?>
