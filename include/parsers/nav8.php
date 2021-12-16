<?php

    function parse_nav8($navfilelist, $datapath, $fout) {

        // Need to loop over all nav files in a cruise, in the order specified
        // by external control file.
        foreach ($navfilelist as $line) {

            // $line = trim( fgets($fin) );
            if ($line == "") break;
            $lineRec = preg_split("/[\s]+/", $line);
            $filename = $datapath . "/" . $lineRec[0];
            $fid = fopen($filename, 'r');

            //----------- Loop Over Contents of Single File ----------//
            while (!feof($fid)) {

                $line = trim(fgets($fid));
                if ($line=="") break;

                $NavRec = preg_split("/\s+/", $line);

                $year = $NavRec[0];
                $doy = $NavRec[1];
                $hour = $NavRec[2];
                $minute = $NavRec[3];
                $second = $NavRec[4];
                $millisecond = $NavRec[5];
                $lat = $NavRec[7];
                $lon = $NavRec[8];
                $hdop = $NavRec[9];
                $nsat = $NavRec[12];
                $qual = $NavRec[13];

                // Convert DOY to Month and Day:
                $result = doy2mmdd($year, $doy);
                $month = $result["month"];
                $day = $result["day"];

                $time_format = "%4d-%02d-%02dT%02d:%02d:%02d.%03dZ";

                // Determine the number of digits to the right of the decimal (lon):
                $roz = preg_split("/\./", $lon);
                $lon_nroz = strlen($roz[1]);

                // Determine the number of digits to the right of the decimal (lat):
                $roz = preg_split("/\./", $lat);
                $lat_nroz = strlen($roz[1]);

                // Preserve the precision of the original decimal longitude and latitude:
                $lon_format = "%." . $lon_nroz . "f";
                $lat_format = "%." . $lat_nroz . "f";

                // Format for quality info:
                $qual_format = "%s\t%s\t%s";

                // This format does not record altitude.  Fill in with "NAN".
                $alt  = "NAN";

                $datestamp = sprintf(
                    $time_format, $year, $month, $day, $hour, $minute,
                    $second, $millisecond
                );

                $print_format = "%s\t" . $lon_format . "\t" . $lat_format . "\t" .
                    $qual_format . "\t%s\n";

                fprintf(
                    $fout, $print_format,
                    $datestamp, $lon, $lat,
                    $qual, $nsat, $hdop, $alt
                );

            } //end while (!feof($fid))

            fclose($fid);

        } // end foreach($navfilelist as $line)
        //------------ End Main Loop Over All Nav Files ------------//

}

?>
