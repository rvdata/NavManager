<?php

function parse_nav1($navfilelist, $datapath, $fout) {

    //----------- Initialize variables: -----------//
    $maxBuffer = 86400;  // Max number of elements array can hold
    $gpsBuffer = array();
		$dateBufferLast = new stdClass();
    $nmea = new NMEA0183Message();
    $zda = new NMEA0183_ZDA();
    $gga = new NMEA0183_GGA();
    $ggaPrevious = new NMEA0183_GGA();
    $datetimeLastKnown = new DateTimeSimple();

    $irec = 1;  // Record number (from start of file)
    $binx = 1;  // gga buffer index
    //----------- End Initialize Variables ---------//

    // Need to loop over all nav files in a cruise, in the order specified
    // by external control file.
    foreach ($navfilelist as $line) {

        //      $line = trim( fgets($fin) );
        if ($line == "") break;
        $filename = $datapath . "/" . $line;
        //     echo "Reading " . $filename . "\n";
        $fid = fopen($filename, 'r');

        //----------- Get Date ----------//
        $datetimeLastKnown->init($fid);
        if (is_null($datetimeLastKnown)) {
            echo "No ZDA date stamp in file.\n";
            exit(1);
        }
        rewind($fid);
        //------------ End Get Date -----------//

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

                $nmea->init($line);

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

                        if ($dateBufferLast->hhmmss == $gpsBuffer[$binx]->gga->hhmmss) {
                            unset($gpsBuffer[$binx]);
                            continue;
                        }

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
                                $dateUnix = strtotime("+1 day", gmmktime(0, 0, 0, $zda->month, $zda->day, $zda->year));
                                $dateString = gmdate("Y-m-d", $dateUnix);
                                $dateArray = preg_split("/\-/", $dateString);

                                $gpsBuffer[1]->gga->year  = $dateArray[0];
                                $gpsBuffer[1]->gga->month = $dateArray[1];
                                $gpsBuffer[1]->gga->day   = $dateArray[2];
                            }  // end if

                            for ($inx=1; $inx<=$maxBuffer; $inx++) {

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

                        // Or do we have a ZDA date/time stamp?
                    } else if (preg_match('/^\$.{2}ZDA$/', $NavRec[0])) {

                        // echo "Found ZDA.\n";
                        // Process NMEA message as a ZDA date/time stamp:
                        $zda->init($NavRec);

                        // When we encounter a ZDA date/time stamp, we process the GPS buffer,
                        // starting from the beginning of the buffer (the earliest GGA records
                        // in the buffer):
                        // (1) Assign dates to GGA (tricky when day advances within buffer
                        //     or when ZDA date/time is reported late.)
                        // (2) Print GPS buffer (all GGA messages dated)
                        // (3) Unset GPS buffer
                        $inx = 1;
                        $inxMax = count($gpsBuffer);
                        while ($inx <= $inxMax
                               && ($gpsBuffer[$inx]->gga->hhmmss <= $zda->hhmmss)
                        ) {
                            // GGA same day as ZDA
                            $gpsBuffer[$inx]->gga->year  = $zda->year;
                            $gpsBuffer[$inx]->gga->month = $zda->month;
                            $gpsBuffer[$inx]->gga->day   = $zda->day;
                            $inx++;
                        }
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
                                    $dateUnix = strtotime("+1 day", gmmktime(0, 0, 0, $gpsBuffer[$jnx-1]->gga->month, $gpsBuffer[$jnx-1]->gga->day, $gpsBuffer[$jnx-1]->gga->year));
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
                                    $dateUnix = strtotime("-1 day", gmmktime(0, 0, 0, $zda->month, $zda->day, $zda->year));
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

                        // Hold onto last date/time:
                        $dateBufferLast->year   = $gpsBuffer[$linx]->gga->year;
                        $dateBufferLast->month  = $gpsBuffer[$linx]->gga->month;
                        $dateBufferLast->day    = $gpsBuffer[$linx]->gga->day;
                        $dateBufferLast->hhmmss = $gpsBuffer[$linx]->gga->hhmmss;

                        // Buffer has been printed.  Unset buffer and re-initialize
                        // GPS buffer index:
                        unset($gpsBuffer);
                        $binx = 1;

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
    if (isset($gpsBuffer) && count($gpsBuffer) > 0) {

        //     echo "binx: " . $binx . "\n";
        // printf("%4d-%02d-%02dT%02d:%02d:%f\n", $zda->year, $zda->month, $zda->day,
        //	    $zda->hh, $zda->mm, $zda->ss);

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
                $dateUnix = strtotime("+1 day", gmmktime(0, 0, 0, $zda->month, $zda->day, $zda->year));
                $dateString = gmdate("Y-m-d", $dateUnix);
                $dateArray = preg_split("/\-/", $dateString);

                $gpsBuffer[1]->gga->year  = $dateArray[0];
                $gpsBuffer[1]->gga->month = $dateArray[1];
                $gpsBuffer[1]->gga->day   = $dateArray[2];
            } // end if GGA day same as end of previous buffer
        }  // end if

        for ($inx=1; $inx < $binx - 1; $inx++) {

            if ($gpsBuffer[$inx+1]->gga->hhmmss < $gpsBuffer[$inx]->gga->hhmmss) {
                // Date has advanced.  Convert date to unix time, add 1 day,
                // and convert back to date:
                $dateUnix = strtotime("+1 day", gmmktime(0, 0, 0, $gpsBuffer[$inx]->gga->month, $gpsBuffer[$inx]->gga->day, $gpsBuffer[$inx]->gga->year));
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


} // end function navcopy()
?>
