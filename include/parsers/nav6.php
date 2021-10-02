<?php

function parse_nav6($navfilelist, $datapath, $fout) {

    // Need to loop over all nav files in a cruise, in the order specified
    // by external control file.
    foreach ($navfilelist as $line) {

        // $line = trim( fgets($fin) );
        if ($line == "") break;
        $filename = $path . "/" . $line;
        $fid = fopen($filename, 'r');

        // Initialize variables:
        $lastknown_northsouth = 'N';
        $lastknown_eastwest = 'W';

        //----------- Loop Over Contents of Single File ----------//
        while (!feof($fid)) {

            //$line = trim( fgets($fid) );
            //if ($line=="") break;
            $line = stream_get_line($fid, 1024, "\r\n");  // Windows line endings.
            $line = preg_replace("/\n/", "", $line);      // Remove line breaks from records.

            $NavRec = preg_split("/\,/", $line);  // comma-separated values

            if (count($NavRec) > 30) {  // Skip incomplete records.

                $dateRec = preg_split("/\//", $NavRec[1]);  // values separated by slash "/"
                $timeRec = preg_split("/\:/", $NavRec[2]);  // values separated by colon ":"

                // Check for "-99" or incomplete lat:
                if ($NavRec[5] == "-99" || $NavRec[6] == "-99" || strlen($NavRec[5]) < 9) {

                    $lat = "NAN" ;
                    $lat_format = "%s";

                } else {

                    // Decode the latitude and its precision:
                    $lat_nmea0183 = floatval($NavRec[5]);      // DDMM.MMMM

                    $lat_deg = intval($lat_nmea0183/100);
                    $lat_min = $lat_nmea0183 - ($lat_deg*100);
                    $lat = $lat_deg + ($lat_min/60);
                    if (preg_match('/\./', $NavRec[5])) {
                        $roz = preg_split('/\./', $NavRec[5]);
                        $lat_nroz = strlen($roz[1]);
                    } else {
                        $lat_nroz = 0;
                    }
                    $northsouth = (!empty($NavRec[6])) ? $NavRec[6] : $lastknown_northsouth;
                    $lastknown_northsouth = $northsouth;

                    if ($northsouth == 'S') {
                        $lat = -$lat;
                    }
                    $lat_format = "%." . ($lat_nroz + 2) . "f";

                } // end if lat "-99" or incomplete lat

                // Check for "-99" or incomplete lon:
                if ($NavRec[8] == "-99" || $NavRec[9] == "-99" || strlen($NavRec[8]) < 10) {

                    $lon = "NAN";
                    $lon_format = "%s";

                } else {

                    // Decode the longitude and its precision:
                    $lon_nmea0183 = floatval($NavRec[8]);   // DDDMM.MMMM

                    $lon_deg = intval($lon_nmea0183/100);
                    $lon_min = $lon_nmea0183 - ($lon_deg*100);
                    $lon = $lon_deg + ($lon_min/60);
                    if (preg_match('/\./', $NavRec[8])) {
                        $roz = preg_split('/\./', $NavRec[8]);
                        $lon_nroz = strlen($roz[1]);
                    } else {
                        $lon_nroz = 0;
                    }
                    $eastwest = (!empty($NavRec[9])) ? $NavRec[9] : $lastknown_eastwest;
                    $lastknown_eastwest = $eastwest;

                    if ($eastwest == 'W') {
                        $lon = -$lon;
                    }
                    $lon_format = "%." . ($lon_nroz + 2) . "f";

                } // end if lon "-99"

                // Decode the date and time and the time precision:
                $month = $dateRec[0];
                $day = $dateRec[1];
                $year = $dateRec[2];

                $hour = $timeRec[0];
                $minute = $timeRec[1];
                $second = $timeRec[2];

                if (preg_match("/\./", $second)) {
                    $roz = preg_split('/\./', $second);
                    $tim_nroz = strlen($roz[1]);
                } else {
                    $tim_nroz = 0;
                }

                // Print exactly the same precision time stamp as in the recorded data.
                if ($tim_nroz == 0) {
                    $time_format = "%4d-%02d-%02dT%02d:%02d:%02dZ";
                } else {
                    $time_format = "%4d-%02d-%02dT%02d:%02d:%0" . ($tim_nroz + 3) . "." . $tim_nroz . "fZ";
                }

                $datestamp = sprintf($time_format, $year, $month, $day, $hour, $minute, $second);

                // This format does not record GGA information.  Fill in with "NAN".
                $qual = "NAN";
                $nsat = "NAN";
                $hdop = "NAN";
                $alt  = "NAN";

                $print_format = "%s\t" . $lon_format . "\t" . $lat_format . "\t%s\t%s\t%s\t%s\n";

                // Don't print record if both lat and lon are empty:
                if ($lat != "NAN" && $lon != "NAN") {

                    fprintf(
                        $fout, $print_format,
                        $datestamp, $lon, $lat,
                        $qual, $nsat, $hdop, $alt
                    );

                } // if lat and lon both empty

            } // end if count($NavRec) >

        } //end while (!feof($fid))

        fclose($fid);

    } // end foreach($navfilelist as $line)
    //------------ End Main Loop Over All Nav Files ------------//

}
?>
