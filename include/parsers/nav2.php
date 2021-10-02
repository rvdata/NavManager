<?php

function parse_nav2($navfilelist, $datapath, $fout) {
    //----------- Initialize variables: -----------//
		$pc = new stdClass();
    $maxBuffer = 86400;  // Max number of elements array can hold
    $gpsBuffer = array();
    $dateBufferLast = new stdClass();;  // Initially unknown date.
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
        $filename = $path . "/" . $lineRec[0];
        $fid = fopen($filename, 'r');

        //----------- Loop Over Contents of Single File ----------//
        while (!feof($fid)) {

            // Get NMEA message:
            $line = trim(fgets($fid));

            // Skip over non-data records.  Records start with 2-digit month [00-12]
            if (preg_match('/GGA/', $line)) {

                $lines = preg_split('/\,\$/', $line);
                // preg_split removes leading '$' from NMEA string.  Put it back:
                $lines[1] = '$' . $lines[1];

                $stringDateTime = preg_split("/\,/", $lines[0]);
                $mm_dd_yyyy = preg_split("/\//", $stringDateTime[0]);
                $pc->month = $mm_dd_yyyy[0];
                $pc->day   = $mm_dd_yyyy[1];
                $pc->year  = $mm_dd_yyyy[2];

                $hh_mm_ss = preg_split("/\:/", $stringDateTime[1]);
                $pc->hour = $hh_mm_ss[0];
                $pc->minute = $hh_mm_ss[1];
                $pc->second = $hh_mm_ss[2];
                $pc->hhmmss = 1e4*$pc->hour + 1e2*$pc->minute + $pc->second;

                $nmea->init($lines[1]);

                // Is checksum valid?  (We don't allow data without checksums to be processed.)
                if ($nmea->validCheckSum === true | $nmea->suppliedCheckSum === null) {
                    // Is checksum valid?  (We allow data without checksums to be processed.)
                    //if ((is_null($nmea->suppliedCheckSum)) || ($nmea->validCheckSum)) {

                    $NavRec = preg_split('/\,/', $nmea->data);
                    //echo "NavRec: " . $line . "\n";

                    // Do we have a valid GGA message?
                    //if (preg_match('/^\$.{2}GGA$/', $NavRec[0])) {
                    if (preg_match('/^\$.{2}GGA$/', $NavRec[0])
                        && valid_gga_message($NavRec)
                    ) {
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
                        $gpsBuffer[$binx]->gga->year  = $pc->year;
                        $gpsBuffer[$binx]->gga->month = $pc->month;
                        $gpsBuffer[$binx]->gga->day   = $pc->day;

                        // Process buffer if it is full.
                        if ($binx < $maxBuffer) {
                            // Still room in buffer--keep reading file.
                            $binx++;
                        } else {
                            // Buffer full--process it before continuing with file read.

                            for ($inx=1; $inx<=$maxBuffer - 1; $inx++) {

                                // HACK for PE13-30, PE14-05 (gaps > 1 day)
                                //$isSamePCDay = ($gpsBuffer[$inx+1]->gga->year == $gpsBuffer[$inx]->gga->year)
                                //    && ($gpsBuffer[$inx+1]->gga->month == $gpsBuffer[$inx]->gga->month)
                                //    && ($gpsBuffer[$inx+1]->gga->day == $gpsBuffer[$inx]->gga->day);

                                //if ($isSamePCDay) { // HACK for PE13-30, PE14-05 (gaps > 1 day)

                                    $delt = $gpsBuffer[$inx+1]->gga->hhmmss - $gpsBuffer[$inx]->gga->hhmmss;

										// Added for Falkor to skip duplicate records caused by rounding seconds CJO
										if ($delt == 0) {
											continue;
                                    } elseif ($gpsBuffer[$inx+1]->gga->hhmmss < $gpsBuffer[$inx]->gga->hhmmss && $delt < -100000) {
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

                                //} // end if same PC day

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

            // HACK for PE13-30, PE14-05 (gaps > 1 day)
            //$isSamePCDay = ($gpsBuffer[$inx+1]->gga->year == $gpsBuffer[$inx]->gga->year)
            //    && ($gpsBuffer[$inx+1]->gga->month == $gpsBuffer[$inx]->gga->month)
            //    && ($gpsBuffer[$inx+1]->gga->day == $gpsBuffer[$inx]->gga->day);

            //if ($isSamePCDay) {  // HACK for PE13-30, PE14-05 (gaps > 1 day)

                $delt = $gpsBuffer[$inx+1]->gga->hhmmss - $gpsBuffer[$inx]->gga->hhmmss;

					// Added for Falkor to skip duplicate records caused by rounding seconds CJO
					if ($delt == 0) {
						continue;
                } elseif ($gpsBuffer[$inx+1]->gga->hhmmss < $gpsBuffer[$inx]->gga->hhmmss && $delt < -100000) {
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

            //} // end if same PC day

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
