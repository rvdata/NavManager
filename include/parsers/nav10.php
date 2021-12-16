<?php

function parse_nav10($navfilelist, $datapath, $fout) {

    // Need to loop over all nav files in a cruise, in the order specified
    // by external control file.
    foreach ($navfilelist as $line) {

        // $line = trim( fgets($fin) );
        if ($line == "") break;
        $filename = $datapath . "/" . $line;
        $fid = fopen($filename, 'r');

        //----------- Loop Over Contents of Single File ----------//
        while (!feof($fid)) {

            $line = trim(fgets($fid));

            if ($line!="") { // File sometimes has blank lines.  Skip them.

                $NavRec = preg_split("/\,/", $line);  // comma-separated values

                // Sometime in 2010, UW changed the date format from DD/MM/YYYY to DD-MM-YYYY.
                // Test whether there are slashes (/) or hyphens (-) in the date string and
                // read accordingly:

                $dateRec = preg_split("/\-|\//", $NavRec[0]);  // values separated by hyphen "-" or slash "/"
                $timeRec = preg_split("/\:/", $NavRec[1]);  // values separated by colon ":"

                // Check for no lat:
                if (empty($NavRec[2])) {

                    $lat = "NAN" ;
                    $lat_format = "%s";

                } else {  // if lat:

                    $lat = $NavRec[2];

                    // Determine the number of digits to the right of the decimal (lat):
                    $roz = preg_split("/\./", $lat);
                    $lat_nroz = strlen($roz[1]);

                    // Preserve the precision of the original decimal latitude:
                    $lat_format = "%." . $lat_nroz . "f";

                } // if no lat

                if (empty($NavRec[3])) {

                    $lon = "NAN" ;
                    $lon_format = "%s";

                } else {  // if lon:

                    $lon = $NavRec[3];

                    // Determine the number of digits to the right of the decimal (lon):
                    $roz = preg_split("/\./", $lon);
                    $lon_nroz = strlen($roz[1]);

                    // Preserve the precision of the original decimal longitude:
                    $lon_format = "%." . $lon_nroz . "f";

                } // if no lon

                // Decode the date and time and the time precision:
                $day   = $dateRec[0];
                $month = $dateRec[1];
                $year  = $dateRec[2];

                $hour   = $timeRec[0];
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

                $datestamp = sprintf( $time_format, $year, $month, $day, $hour, $minute, $second );

                // This format does not record GGA information.  Fill in with "NAN".
                $qual = "NAN";
                $nsat = "NAN";
                $hdop = "NAN";
                $alt  = "NAN";

                $print_format = "%s\t" . $lon_format . "\t" . $lat_format . "\t%s\t%s\t%s\t%s\n";

                fprintf( $fout, $print_format,
                         $datestamp, $lon, $lat,
                         $qual, $nsat, $hdop, $alt );

            } // end if ($line!="")

        } //end while (!feof($fid))

        fclose($fid);

    } // end foreach($navfilelist as $line)
    //------------ End Main Loop Over All Nav Files ------------//


}
?>
