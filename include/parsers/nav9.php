<?php

function parse_nav9($navfilelist, $datapath, $fout) {

    // Need to loop over all nav files in a cruise, in the order specified
    // by external control file.
    foreach ($navfilelist as $line) {

        //      $line = trim( fgets($fin) );
        if ($line == "") break;
        $lineRec = preg_split("/[\s]+/", $line);
        $filename = $path . "/" . $lineRec[0];
        $fid = fopen($filename, 'r');

        //----------- Loop Over Contents of Single File ----------//
        while (!feof($fid)) {

            $line = trim(fgets($fid));
            if ($line=="") break;

            // Skip over comments in file (leading hash '#'):

            if ($line[0] != "#") {

                // Some times from older cruises may have single-digit hours and single-digit minutes
                // with a blank space where a zero is expected.  Assuming the time always starts and ends
                // at the same location in the string, replace leading blanks with zeros:
                if ($line[16] == " ") $line[16] = 0;
                if ($line[18] == " ") $line[18] = 0;

                // Add missing white-space before minus signs '-' in input record:
                $line = preg_replace("/-/", " -", $line);
                $NavRec = preg_split("/[\s]+/", $line);

                $day = $NavRec[0];
                $month = $NavRec[1];
                $year = $NavRec[2];
                $hhmm = intval($NavRec[3]);
                $hour = intval($hhmm/1e2);
                $minute = intval($hhmm - ($hour*1e2));
                $second = 0;  // Time is reported to nearest minute in satdata file.
                $lat_deg = floatval($NavRec[4]);
                $lat_min = floatval($NavRec[5]);
                $lat = ($lat_deg >= 0) ? ($lat_deg + ($lat_min/60)) : ($lat_deg - ($lat_min/60));
                // Determine the number of digits to the right of the decimal (lat):
                if (preg_match('/\./', $NavRec[5])) {
                    $roz = preg_split('/\./', $NavRec[5]) ;
                    $lat_nroz = strlen($roz[1]) + 2;
                } else {
                    $lat_nroz = 0;
                }
                $lon_deg = floatval($NavRec[6]);
                $lon_min = floatval($NavRec[7]);
                $lon = ($lon_deg >= 0) ? ($lon_deg + ($lon_min/60)) : ($lon_deg - ($lon_min/60));
                // Determine the number of digits to the right of the decimal (lon):
                if (preg_match('/\./', $NavRec[7])) {
                    $roz = preg_split('/\./', $NavRec[7]);
                    $lon_nroz = strlen($roz[1]) + 2;
                } else {
                    $lon_nroz = 0;
                }
                $time_format = "%4d-%02d-%02dT%02d:%02d:%02dZ";

                $datestamp = sprintf(
                    $time_format, $year, $month, $day, $hour, $minute, $second
                );

                // Preserve the precision of the original decimal longitude and latitude:
                $lon_format = "%." . $lon_nroz . "f";
                $lat_format = "%." . $lat_nroz . "f";

                // This format does not record GGA information.  Fill in with "NAN".
                $qual = "NAN";
                $nsat = "NAN";
                $hdop = "NAN";
                $alt  = "NAN";

                $print_format = "%s\t" . $lon_format . "\t" . $lat_format . "\t%s\t%s\t%s\t%s\n";

                fprintf(
                    $fout, $print_format,
                    $datestamp, $lon, $lat,
                    $qual, $nsat, $hdop, $alt
                );

            } // end if ($line[0] == "#")

        } //end while (!feof($fid))

        fclose($fid);

    } // end foreach($navfilelist as $line)
    //------------ End Main Loop Over All Nav Files ------------//

}

?>
