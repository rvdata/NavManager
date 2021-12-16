<?php

function parse_nav3($navfilelist, $datapath, $fout) {
    //----------- Initialize variables: -----------//
    $dateBufferLast = new stdClass();  // Initially unknown date.
    $pc = new stdClass();
    $nmea = new NMEA0183Message();
    $gll = new NMEA0183_GLL();
    $gga = new NMEA0183_GGA();

    // Note: Because the GLL messages are partial (do not include GPS receiver clock), we are
    // forced to use the external clock provided at the beginning of the line for both GLL and GGA
    // messages.  So the GPS receiver clock in the GGA string is ignored, unlike the usual case.

    // Need to loop over all nav files in a cruise, in the order specified
    // by external control file.
    foreach ($navfilelist as $line) {

        //     $line = trim( fgets($fin) );
        if ($line == "") break;
        $lineRec = preg_split("/[\s]+/", $line);
        $filename = $datapath . "/" . $lineRec[0];
        $fid = fopen($filename, 'r');

        //----------- Loop Over Contents of Single File ----------//
        while (!feof($fid)) {

            // Get NMEA message:
            $line = trim(fgets($fid));
            if ($line=="") break;

            // Skip over non-data records.  Records start with 2-digit month [00-12]
            if (preg_match('/GGA/', $line) || preg_match('/GLL/', $line)) {

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
                $pc->second = floatval($hh_mm_ss[2]);
                $pc->hhmmss = 1e4*$pc->hour + 1e2*$pc->minute + $pc->second;

                $nmea->init($lines[1]);

                // Is checksum valid?  (We allow data without checksums to be processed.)
                if ((is_null($nmea->suppliedCheckSum)) || ($nmea->validCheckSum)) {

                    $NavRec = preg_split('/\,/', $nmea->data);
                    //echo "NavRec: " . $line . "\n";

                    // Do we have a GGA message?
                    if (preg_match('/^\$.{2}GGA$/', $NavRec[0])) {

                        //echo "Found GGA.\n";
                        // Process NMEA message as a GGA message:
                        $gga->init($NavRec);

                        $gga->year   = $pc->year;
                        $gga->month  = $pc->month;
                        $gga->day    = $pc->day;
                        $gga->hour   = $pc->hour;
                        $gga->minute = $pc->minute;
                        $gga->second = $pc->second;
                        $gga->hhmmss = $pc->hhmmss;
                        if (preg_match('/\./', $hh_mm_ss[2])) {
                            $roz = preg_split('/\./', $hh_mm_ss[2]);
                            $gga->tim_nroz = strlen($roz[1]);
                        } else {
                            $gga->tim_nroz = 0;
                        }

                        // Print dated GGA message in standard format:
                        printDatedGGA($fout, $gga);

                    } else { // end if GGA

                        // Do we have a GLL message?
                        if (preg_match('/^\$.{2}GLL$/', $NavRec[0])) {

                            //echo "Found GLL.\n";
                            // Process NMEA message as a GLL message:
                            $gll->init($NavRec);

                            $gga->year      = $pc->year;
                            $gga->month     = $pc->month;
                            $gga->day       = $pc->day;
                            $gga->hour      = $pc->hour;
                            $gga->minute    = $pc->minute;
                            $gga->second    = $pc->second;
                            $gga->hhmmss    = $pc->hhmmss;
                            if (preg_match('/\./', $hh_mm_ss[2])) {
                                $roz = preg_split('/\./', $hh_mm_ss[2]);
                                $gga->tim_nroz = strlen($roz[1]);
                            } else {
                                $gga->tim_nroz = 0;
                            }
                            $gga->lat       = $gll->lat;
                            $gga->lon       = $gll->lon;
                            switch ($gll->status) {
                            case "A":
                                $gga->gpsQuality = 1;
                                break;
                            case "V":
                                $gga->gpsQuality = 0;
                                break;
                            default:
                                $gga->gpsQuality = "NAN";
                                break;
                            }
                            $gga->numberOfSatellites = "NAN";
                            $gga->horizontalDilution = "NAN";
                            $gga->antennaAltitude    = "NAN";
                            $gga->lat_nroz  = $gll->lat_nroz;
                            $gga->lon_nroz  = $gll->lon_nroz;

                            // Print dated GGA message in standard format:
                            printDatedGGA($fout, $gga);

                        } // end if GLL

                    } // end if GGA

                } // end if nmea checksum valid

            } // end if data record

        } //end while (!feof($fid))

        fclose($fid);

    } // end foreach($navfilelist as $line)
        //------------ End Main Loop Over All Nav Files ------------//

}

?>
