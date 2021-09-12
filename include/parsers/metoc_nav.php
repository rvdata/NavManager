<?php

function parse_metoc_nav($navfilelist, $datapath, $fout) {

    $lat_deg_col_num = 0;
    $lat_min_col_num = 0;
    $lon_deg_col_num = 0;
    $lon_min_col_num = 0;
    $parameter_count = 0;

    // Need to loop over all nav files in a cruise, in the order specified
    // by external control file.
    foreach ($navfilelist as $line) {

        // $line = trim( fgets($fin) );
        if ($line == "") break;
        $filename = $path . "/" . $line;
        $fid = fopen($filename, 'r');

        $recnum = 1;
        //----------- Loop Over Contents of Single File ----------//
        while (!feof($fid)) {

            $line = trim(fgets($fid));

            if ($recnum == 2) {
                $parameters = preg_split("/,/", trim($line));

                $parameter_count = count($parameters);

                foreach ($parameters as $index => $parameter) {

                    $clean_parameter = trim($parameter, "\"");

                    if ($clean_parameter == 'Lat_deg') {
                        $lat_deg_col_num = $index;
                    } elseif ($clean_parameter == 'Lat_min') {
                        $lat_min_col_num = $index;
                    } elseif ($clean_parameter == 'Lon_deg') {
                        $lon_deg_col_num = $index;
                    } elseif ($clean_parameter == 'Lon_min') {
                        $lon_min_col_num = $index;
                    } elseif ($clean_parameter == 'Latitude') {
                        $lat_col_num = $index;
                    } elseif ($clean_parameter == 'Longitude') {
                        $lon_col_num = $index;
                    }
                }
                break;
            } else {
                $recnum++;
            }
        }

        rewind($fid);
        $recnum = 1;

        while (!feof($fid)) {

            $line = trim(fgets($fid));

                if ($line == "" || $recnum <= 4) {
                    $recnum++;
                    continue;
                } else {
                    $records = preg_split("/,/", $line);
                    $dateTimeRec = preg_split("/\s+/", trim($records[0], "\""));

                    $dateStr = strtotime($dateTimeRec[0]);

                    $year  = date('Y', $dateStr);
                    $month = date('m', $dateStr);
                    $day   = date('d', $dateStr);

                    $hhmmss = preg_split("/:/", $dateTimeRec[1]);

                    // Decode the time and the time precision:
                    $hour   = $hhmmss[0];
                    $minute = $hhmmss[1];
                    $second = $hhmmss[2];

                    if (preg_match("/\./", $second)) {
                        $roz = preg_split('/\./', $second);
                        $tim_nroz = strlen($roz[1]);
                    } else {
                        $tim_nroz = 0;
                    }

                    // Print exactly the same precision time stamp
                    // as in the recorded data.
                    if ($tim_nroz == 0) {
                        $time_format = "%4d-%02d-%02dT%02d:%02d:%02dZ";
                    } else {
                        $time_format = "%4d-%02d-%02dT%02d:%02d:%0"
                            . ($tim_nroz + 3) . "." . $tim_nroz . "fZ";
                    }

                    $datestamp = sprintf($time_format, $year, $month, $day, $hour, $minute, $second);

                    $lat = floatval(trim($records[$lat_col_num], "\""));
                    $lon = floatval(trim($records[$lon_col_num], "\""));

                    if ( 6 == 9 ) {

                    $lat_deg = floatval(trim($records[$lat_deg_col_num], "\""));
                    $lat_min = floatval(trim($records[$lat_min_col_num], "\""));
                    $lat = $lat_deg + ($lat_min/60);

                    if (preg_match('/\./', $lat_min)) {
                        $roz = preg_split('/\./', $lat_min);
                        $lat_nroz = strlen($roz[1]);
                    } else {
                        $lat_nroz = 0;
                    }
                    $lat_format = "%." . ($lat_nroz + 2) . "f";

                    $lon_deg = floatval(trim($records[$lon_deg_col_num], "\""));
                    $lon_min = floatval(trim($records[$lon_min_col_num], "\""));
                    $lon = $lon_deg + ($lon_min/60);


                    if (preg_match('/\./', $lon_min)) {
                        $roz = preg_split('/\./', $lon_min);
                        $lon_nroz = strlen($roz[1]);
                    } else {
                        $lon_nroz = 0;
                    }
                    $lon_format = "%." . ($lon_nroz + 2) . "f";

                    }

                    $lat_format = '%s';
                    $lon_format = '%s';

                    // This format does not record GGA information.  Fill in with "NAN".
                    $qual = "NAN";
                    $nsat = "NAN";
                    $hdop = "NAN";
                    $alt  = "NAN";

                    // Preserve the time, lat, and lon precisions:
                    $print_format = "%s\t" . $lon_format . "\t" . $lat_format . "\t%s\t%s\t%s\t%s\n";

                    fprintf(
                        $fout, $print_format,
                        $datestamp, $lon, $lat,
                        $qual, $nsat, $hdop, $alt
                    );

                }

            }


        fclose($fid);

    } // end foreach($navfilelist as $line)
}


?>
