<?php

function parse_nav32($navfilelist, $datapath, $fout) {

    //----------- Initialize variables: -----------//
    $maxBuffer = 86400;  // Max number of elements array can hold
    $gpsBuffer = array();
    $dateBufferLast = new stdClass();
    $nmea = new NMEA0183Message();
    $rmc = new NMEA0183_RMC();
    $datetimeLastKnown = new DateTimeSimple();

    $irec = 1;  // Record number (from start of file)
    $binx = 1;  // gps buffer index
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
            echo "No RMC date stamp in file.\n";
            exit(1);
        }
        rewind($fid);
        //------------ End Get Date -----------//

        //----------- Loop Over Contents of Single File ----------//
        while (!feof($fid)) {

            // Get record:
            $line = trim(fgets($fid));


            // Skip forward to first NMEA message on line.
            $newline = strstr($line, '$');

            $nmea->init($newline);

            // Is checksum valid?  (We allow data without checksums to be processed.)
            if ((is_null($nmea->suppliedCheckSum)) || ($nmea->validCheckSum)) {

                $NavRec = preg_split('/\,/', $nmea->data);
                //echo "NavRec: " . $line . "\n";

                // Do we have an RMC message?
                if (preg_match('/^\$.{2}RMC$/', $NavRec[0])) {

                    //echo "Found RMC.\n";
                    // Process NMEA message as a RMC message:
                    $rmc->init($NavRec);

                    // Save GPS fix to buffer:
                    $gpsBuffer[$binx] = new stdClass();
                    $gpsBuffer[$binx]->rmc = clone $rmc;

                    // Process buffer if it is full.
                    if ($binx < $maxBuffer) {
                        // Still room in buffer--keep reading file.
                        $binx++;
                    } else {
                        // Buffer full--process it before continuing with file read.

                        // Print buffer with RMCs:
                        $linx = count($gpsBuffer);
                        for ($inx=1; $inx<=$linx; $inx++) {
                            printBufferRMC($fout, $gpsBuffer, $inx);
                        }

                        // Buffer has been printed.  Unset buffer and re-initialize
                        // GPS buffer index:
                        unset( $gpsBuffer );
                        $binx = 1;

                    } // end if $binx < $maxBuffer

                } // end identify which NMEA message type

            } // end if valid checksum (or checksum not supplied)

            $irec++;

        } // end while (!feof($fid))
        //------------ End Loop Over Contents of Single File ----------//

        fclose($fid);

    } // end foreach($navfilelist as $line)
    //------------ End Main Loop Over All Nav Files ------------//

    //--------- Might have unprocessed buffer at end of last file read -------
    if (isset($gpsBuffer) && count($gpsBuffer)>0) {

        //     echo "binx: " . $binx . "\n";
        // printf("%4d-%02d-%02dT%02d:%02d:%f\n", $rmc->year, $rmc->month, $rmc->day,
        //	    $rmc->hh, $rmc->mm, $rmc->ss);

        //     echo "hhmmss: " . $rmc->hhmmss . " " . $gpsBuffer[1]->rmc->hhmmss . "\n";

        // Check to make sure we have read a RMC message prior to filling
        // the buffer:
        if (!isset($rmc->year)) {
            echo "No RMC message found prior to end of GPS buffer.\n";
            echo "Maybe the buffer size is too small?\n";
            exit(1);
        }

        for ($inx=1; $inx<$binx; $inx++) {
            printBufferRMC($fout, $gpsBuffer, $inx);
        } // end for loop over GPS buffer

        // Buffer has been printed.  Unset buffer and re-initialize
        // GPS buffer index:
        unset($gpsBuffer);
        $binx = 1;

    } // end if (isset())

}

?>
