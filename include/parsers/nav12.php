<?php

function parse_nav12($navfilelist, $datapath, $fout) {

    //----------- Initialize variables: -----------//
    $maxBuffer = 86400;  // Max number of elements array can hold
    $gpsBuffer = array();
    $pc = new stdClass();
    $nmea = new NMEA0183Message();
    $zda = new NMEA0183_ZDA();
    $gga = new NMEA0183_GGA();
    $ggaPrevious = new NMEA0183_GGA();
    $datetimeLastKnown = new DateTimeSimple();
    $dateBufferLast = new stdClass();  // Initially unknown date.

    $irec = 1;  // Record number (from start of file)
    $binx = 1;  // gga buffer index
    //----------- End Initialize Variables ---------//

    //echo "nav12 detected...\n";

    // Need to loop over all nav files in a cruise, in the order specified
    // by external control file.
    foreach ($navfilelist as $line) {

        // Hack for MGL1209 CNAV 3050:
        //      $doy = substr($line,-3);
        //if ($doy >= 154) {
        //    $day_offset = 7;
        //} else {
        //    $day_offset = 0;
        //}

        //      $line = trim( fgets($fin) );
        if ($line == "") break;
        $filename = $path . "/" . $line;
        //     echo "Reading " . $filename . "\n";

        // Check for presence of ZDA message:
        $fid = fopen($filename, 'r');
        $has_ZDA = false;
        // ***** Hack: Temporarily removed while loop for HLY 2005
        while (!feof($fid)) {
            $line = trim(fgets($fid));
            if (preg_match("/ZDA/", $line)) {
                $has_ZDA = true;
                break;
            }
        }
        fclose($fid);

        if ($has_ZDA) {

            //echo "Confirmed file contains ZDA...\n";

            $fid = fopen($filename, 'r');

            //----------- Get Date ----------//
            $datetimeLastKnown->init($fid);

            // Hack for MGL1209 CNAV 3050:
            //    if ($day_offset) {
            //if ($datetimeLastKnown->month == 5) {
            //  $datetimeLastKnown->day = $day_offset - (31 - $datetimeLastKnown->day);
            //  $datetimeLastKnown->month = $datetimeLastKnown->month + 1;
            //} else {
            //  $datetimeLastKnown->day = $datetimeLastKnown->day + $day_offset;
            //}
            //}

            rewind($fid);
            //------------ End Get Date -----------//

            $have_zda = false;

            //----------- Loop Over Contents of Single File ----------//
            while (!feof($fid)) {

                // Get NMEA message:
                $line = trim(fgets($fid));

                // Check that the line contains one (and only one) NMEA message.
                // On rare occasions, the real-time data stream that created the
                // raw navigation file may be interrupted, resulting in a partial
                // NMEA message followed by a complete NMEA message on the same line.
                // We try to catch the last complete NMEA message on the line if it
                // appears there may be more than one, as indicated by multiple '$'.
                if (substr_count($line, '$') > 1) {
                    $newline = strrchr($line, '$');
                    $line = $newline;
                }
                if (substr_count($line, '$') == 1) {

                    // Skip forward to beginning of NMEA message on line.
                    $newline = strstr($line, '$');
                    $line = $newline;
                    $nmea->init($line);

                    // Is checksum valid?  (We don't allow data without checksums to be processed.)
                    //if ($nmea->validCheckSum === true) {

                    //echo "Detected valid NMEA mesage...\n";

                    $NavRec = preg_split('/\,/', $nmea->data);
                    //echo "NavRec: " . $line . "\n";

                    // Do we have a GGA message?
                    if (preg_match('/^\$.{2}GGA$/', $NavRec[0]) && $have_zda) {

                        //echo "Found GGA.\n";
                        // Process NMEA message as a GGA message:
                        //$gga->init( $NavRec );

                        // Save GPS fix to buffer:
                        //$gpsBuffer[$binx]->gga = clone $gga;
                        $gpsBuffer[$binx]->gga = new NMEA0183_GGA();
                        $gpsBuffer[$binx]->gga->init($NavRec);

                        //echo "Encountered GGA: ", $gpsBuffer[$binx]->gga->year, "-", $gpsBuffer[$binx]->gga->month, "-", $gpsBuffer[$binx]->gga->day, "T", $gpsBuffer[$binx]->gga->hhmmss, "\n";

                        // Process buffer if it is full.
                        if ($binx < $maxBuffer) {
                            // Still room in buffer--keep reading file.
                            $binx++;
                        } else {
                            // Buffer full--process it before continuing with file read.

                            // Check to make sure we have read a ZDA message prior to filling
                            // the buffer:
                            if (!isset($zda->year)) {
                                echo "No ZDA message found prior to end of GPS buffer.\n";
                                echo "Maybe the buffer size is too small?\n";
                                exit(1);
                            }

                            // Initialize first GGA day based on last ZDA date/time stamp:
                            if ($gpsBuffer[1]->gga->hhmmss >= $zda->hhmmss) {  // GGA same day as ZDA
                                $gpsBuffer[1]->gga->year  = $zda->year;
                                $gpsBuffer[1]->gga->month = $zda->month;
                                $gpsBuffer[1]->gga->day   = $zda->day;
                            } else { // GGA belongs to next day
                                // Convert date to unix time, add 1 day,
                                // and convert back to date:
                                $dateUnix = strtotime( "+1 day", gmmktime(0, 0, 0, $zda->month, $zda->day, $zda->year) );
                                $dateString = gmdate( "Y-m-d", $dateUnix );
                                $dateArray = preg_split("/\-/", $dateString);

                                $gpsBuffer[1]->gga->year  = $dateArray[0];
                                $gpsBuffer[1]->gga->month = $dateArray[1];
                                $gpsBuffer[1]->gga->day   = $dateArray[2];
                            }  // end if

                            for ($inx=1; $inx<=$maxBuffer; $inx++) {

                                $delt = $gpsBuffer[$inx+1]->gga->hhmmss - $gpsBuffer[$inx]->gga->hhmmss;

                                //if ($gpsBuffer[$inx+1]->gga->hhmmss < $gpsBuffer[$inx]->gga->hhmmss) {
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

                        // Or do we have a valid ZDA date/time stamp?
                    } else if (preg_match('/^\$.{2}ZDA$/', $NavRec[0])
                        && valid_zda_message($NavRec)
                    ) {

                        // echo "Found ZDA.\n";
                        // Process NMEA message as a ZDA date/time stamp:
                        $zda->init($NavRec);

                        $have_zda = true;

                        // Hack for MGL1209 CNAV 3050:
                        //    if ($day_offset) {
                        //if ($zda->month == 5) {
                        //    $zda->day = $day_offset - (31 - $zda->day);
                        //    $zda->month = $zda->month + 1;
                        //  } else {
                        //    $zda->day = $zfda->day + $day_offset;
                        //  }
                        //    }

                        // When we encounter a ZDA date/time stamp, we process the GPS buffer,
                        // starting from the beginning of the buffer (the earliest GGA records
                        // in the buffer):
                        // (1) Assign dates to GGA (tricky when day advances within buffer
                        //     or when ZDA date/time is reported late.)
                        // (2) Print GPS buffer (all GGA messages dated)
                        // (3) Unset GPS buffer
                        //echo "Encountered ZDA: ", $zda->year, "-", $zda->month, "-", $zda->day, "T", $zda->hhmmss, "\n";
                        $inx = 1;
                        $inxMax = count($gpsBuffer);
                        while ( $inx<=$inxMax
                            && ($gpsBuffer[$inx]->gga->hhmmss <= $zda->hhmmss)
                        ) {
                            // GGA same day as ZDA
                            $gpsBuffer[$inx]->gga->year  = $zda->year;
                            $gpsBuffer[$inx]->gga->month = $zda->month;
                            $gpsBuffer[$inx]->gga->day   = $zda->day;
                            $inx++;
                        }
                        //echo "DEBUG: navcopy: inx=", $inx, ", inxMax=", $inxMax, "\n";
                        //echo "GGA: ", $gpsBuffer[$inx]->gga->hhmmss, ", ZDA: ", $zda->hhmmss, "\n";
                        //exit(1);
                        if ($inx > 1) {

                            $jnxMax = count($gpsBuffer);
                            for ($jnx=$inx; $jnx<=$jnxMax; $jnx++) {

                                if ($gpsBuffer[$jnx]->gga->hhmmss > $gpsBuffer[$jnx-1]->gga->hhmmss) {
                                    // Successive GGA records on same day
                                    $gpsBuffer[$jnx]->gga->year  = $gpsBuffer[$jnx-1]->gga->year;
                                    $gpsBuffer[$jnx]->gga->month = $gpsBuffer[$jnx-1]->gga->month;
                                    $gpsBuffer[$jnx]->gga->day   = $gpsBuffer[$jnx-1]->gga->day;
                                } else { // GGA day has advanced from one GGA to the next
                                    // Convert date to unix time, add 1 day,
                                    // and convert back to date:
                                    $dateUnix = strtotime( "+1 day", gmmktime(0, 0, 0, $gpsBuffer[$jnx-1]->gga->month, $gpsBuffer[$jnx-1]->gga->day, $gpsBuffer[$jnx-1]->gga->year) );
                                    $dateString = gmdate("Y-m-d", $dateUnix);
                                    $dateArray = preg_split("/\-/", $dateString);

                                    $gpsBuffer[$jnx]->gga->year  = $dateArray[0];
                                    $gpsBuffer[$jnx]->gga->month = $dateArray[1];
                                    $gpsBuffer[$jnx]->gga->day   = $dateArray[2];

                                }

                            } // end loop over remainder of buffer

                        } else { // GGA belongs to previous day

                            $jnxMax = count($gpsBuffer);
                            for ($jnx=$jnxMax; $jnx>=1; $jnx--) {

                                if ($gpsBuffer[$jnx]->gga->hhmmss <= $zda->hhmmss) { // GGA same day as ZDA
                                    $gpsBuffer[$jnx]->gga->year  = $zda->year;
                                    $gpsBuffer[$jnx]->gga->month = $zda->month;
                                    $gpsBuffer[$jnx]->gga->day   = $zda->day;
                                } else {

                                    // Convert date to unix time, subtract 1 day,
                                    // and convert back to date:
                                    $dateUnix = strtotime( "-1 day", gmmktime(0, 0, 0, $zda->month, $zda->day, $zda->year) );
                                    $dateString = gmdate("Y-m-d", $dateUnix);
                                    $dateArray = preg_split("/\-/", $dateString);

                                    $gpsBuffer[$jnx]->gga->year  = $dateArray[0];
                                    $gpsBuffer[$jnx]->gga->month = $dateArray[1];
                                    $gpsBuffer[$jnx]->gga->day   = $dateArray[2];

                                } // if current GGA time is greater than previous GGA time

                            }  // end loop over GPS buffer to produce dated-GGAs

                        } // end if ($inx > 1)

                        // Print buffer with dated-GGAs:
                        $linx = count($gpsBuffer);
                        for ($inx=1; $inx<=$linx; $inx++) {
                            printBuffer($fout, $gpsBuffer, $inx);
                        }
                        //exit(1);
                        // Hold onto last date/time:
                        $dateBufferLast->year   = $gpsBuffer[$linx]->gga->year;
                        $dateBufferLast->month  = $gpsBuffer[$linx]->gga->month;
                        $dateBufferLast->day    = $gpsBuffer[$linx]->gga->day;
                        $dateBufferLast->hhmmss = $gpsBuffer[$linx]->gga->hhmmss;

                        // Buffer has been printed.  Unset buffer and re-initialize
                        // GPS buffer index:
                        unset( $gpsBuffer );
                        $binx = 1;

                    } // end identify which NMEA message type

                    //} // end if valid checksum (or checksum not supplied)

                    $irec++;

                } // end if one and only one NMEA message in line

            } // end while (!feof($fid))
            //------------ End Loop Over Contents of Single File ----------//

        } else {

            // Added in case ZDA messages were not recorded but external timestamp exists:

            //echo "No ZDA message detected in file...\n";

            $lastline = '';
            $fid = fopen($filename, 'r');

            //----------- Loop Over Contents of Single File ----------//
            while (!feof($fid)) {

                // Get NMEA message:
                $line = trim(fgets($fid));
                if ($line=="") break;

                // Skip over non-data records.  Records start with 2-digit month [00-12]
                if (preg_match('/GGA/', $line)) {

                    $lines = preg_split('/\$/', $line);
                    // preg_split removes leading '$' from NMEA string.  Put it back:
                    $lines[1] = '$' . $lines[1];

                    // Check for duplicate GGA lines and skip them
                    if ($lines[1] == $lastline) {
                        continue;
                    }
                    $lastline = $lines[1];

                    $stringDateTime = preg_split("/\s+/", $lines[0]);
                    $datetimeRec = preg_split("/\:/", $stringDateTime[1]);
                    $pc->year  = $datetimeRec[0];
                    $doy       = $datetimeRec[1];

                    // Convert DOY to Month and Day:
                    $result = doy2mmdd($pc->year, $doy);
                    $pc->month = $result["month"];
                    $pc->day   = $result["day"];

                    $pc->hour   = $datetimeRec[2];
                    $pc->minute = $datetimeRec[3];
                    $pc->second = $datetimeRec[4];
                    $pc->hhmmss = 1e4*$pc->hour + 1e2*$pc->minute + $pc->second;

                    $nmea->init($lines[1]);

                    // Is checksum valid?  (We don't allow data without checksums to be processed.)
                    //if ($nmea->validCheckSum === true) {

                    $NavRec = preg_split('/\,/', $nmea->data);
                    //echo "NavRec: " . $line . "\n";

                    // Do we have a GGA message?
                    if (preg_match('/^\$.{2}GGA$/', $NavRec[0])) {

                        //echo "Found GGA.\n";
                        // Process NMEA message as a GGA message:
                        $gga->init($NavRec);

                        if (is_null($dateBufferLast)) {
                            $gga->year  = $pc->year;
                            $gga->month = $pc->month;
                            $gga->day   = $pc->day;
                            $dateBufferLast->year  = $gga->year;
                            $dateBufferLast->month = $gga->month;
                            $dateBufferLast->day   = $gga->day;
                        } else {
                            if ($gga->hhmmss <= $pc->hhmmss) { // Same day:
                                $gga->year  = $pc->year;
                                $gga->month = $pc->month;
                                $gga->day   = $pc->day;
                            } else { // Previous day:
                                $gga->year  = $dateBufferLast->year;
                                $gga->month = $dateBufferLast->month;
                                $gga->day  = $dateBufferLast->day;
                            }
                        }
                        $dateBufferLast->year  = $gga->year;
                        $dateBufferLast->month = $gga->month;
                        $dateBufferLast->day   = $gga->day;

                        // Print dated GGA message in standard format:
                        printDatedGGA($fout, $gga);

                    } // end if GGA

                    //} // end if nmea checksum valid

                } // end if data record

            } //end while (!feof($fid))

        }  // end if ZDA message

        fclose($fid);

    } // end foreach($navfilelist as $line)
    //------------ End Main Loop Over All Nav Files ------------//

    //--------- Might have unprocessed buffer at end of last file read -------
    if (isset($gpsBuffer) && count($gpsBuffer)>0) {

        //     echo "binx: " . $binx . "\n";
        // printf("%4d-%02d-%02dT%02d:%02d:%f\n", $zda->year, $zda->month, $zda->day,
        //        $zda->hh, $zda->mm, $zda->ss);

        //     echo "hhmmss: " . $zda->hhmmss . " " . $gpsBuffer[1]->gga->hhmmss . "\n";

        // Check to make sure we have read a ZDA message prior to filling
        // the buffer:
        if (!isset($zda->year)) {
            echo "No ZDA message found prior to end of GPS buffer.\n";
            echo "Maybe the buffer size is too small?\n";
            exit(1);
        }

        // Initialize first GGA day based on last ZDA date/time stamp:
        // This fails if GGA is before midnight and ZDA is after midnight.
        if ($gpsBuffer[1]->gga->hhmmss >= $zda->hhmmss) {  // GGA same day as ZDA
            $gpsBuffer[1]->gga->year  = $zda->year;
            $gpsBuffer[1]->gga->month = $zda->month;
            $gpsBuffer[1]->gga->day   = $zda->day;
        } else {
            if (($gpsBuffer[1]->gga->hhmmss - $dateBufferLast->hhmmss) >= 0) {
                // GGA is same day as end of previous buffer:
                $gpsBuffer[1]->gga->year  = $dateBufferLast->year;
                $gpsBuffer[1]->gga->month = $dateBufferLast->month;
                $gpsBuffer[1]->gga->day   = $dateBufferLast->day;
            } else { // GGA belongs to next day
                // Convert date to unix time, add 1 day,
                // and convert back to date:
                $dateUnix = strtotime( "+1 day", gmmktime(0, 0, 0, $zda->month, $zda->day, $zda->year) );
                $dateString = gmdate("Y-m-d", $dateUnix);
                $dateArray = preg_split("/\-/", $dateString);

                $gpsBuffer[1]->gga->year  = $dateArray[0];
                $gpsBuffer[1]->gga->month = $dateArray[1];
                $gpsBuffer[1]->gga->day   = $dateArray[2];
            } // end if GGA day same as end of previous buffer
        }  // end if

        for ($inx=1; $inx<$binx; $inx++) {

            if ($gpsBuffer[$inx+1]->gga->hhmmss < $gpsBuffer[$inx]->gga->hhmmss) {
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
