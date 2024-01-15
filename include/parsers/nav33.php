<?php

define('LOCAL_DEBUG', FALSE);
function get_date_time_from_string_nav33($line)
{

if(LOCAL_DEBUG) print "\n --- #### DEBUG: START: get_date_time_from_string_nav33\n";
// comma-separated values
$NavRec = preg_split("/\,/", $line);
##print_r($NavRec);

// [0] =  2023-04-04T00:00:00.916471Z]
$dateRec = preg_split("/T/", $NavRec[0]);
##print_r($dateRec);

 // [0] =  2023-03-22
 $dateRec2 = preg_split("/-/", $dateRec[0]);
 ##print_r($dateRec2);
 
 
// values separated by colon ":"
$timeRec = preg_split("/\:/", $dateRec[1]);
$second = $timeRec[2];

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


// Decode the date and time and the time precision:
$ret_array[0]= $time_format;
$ret_array[1]= $dateRec2[0];
$ret_array[2]= $dateRec2[1];
$ret_array[3]= $dateRec2[2];
$ret_array[4]= $timeRec[0];
$ret_array[5]= $timeRec[1];
$ret_array[6]= $timeRec[2];

if(LOCAL_DEBUG) {
    print "DEBUG: INPUT:  $line\n";
    print "DEBUG: TIME FORMAT:  $ret_array[0]\n";
    print "DEBUG: YEAR: $ret_array[1] :: MONTH: $ret_array[2] : DAY: $ret_array[3]\n";
    print "DEBUG: hour: $ret_array[4] :: minute: $ret_array[5] : second: $ret_array[6]\n";
    print " --- #### DEBUG: END: get_date_time_from_string_nav33\n\n";
}
return $ret_array;
}

function make_list_nav33($handle, $inputFormatSpec, $dateStringUTCStart, $dateStringUTCEnd, $path)
{

    if(LOCAL_DEBUG)print "make_list_nav33() \n";
    $inx = 0;
    $jnx = 0;
    while (false !== ($file = readdir($handle))) {

        if ($file != "." && $file != "..") {

            if(LOCAL_DEBUG)print "##################################\n START: \n";
            $filename = $file;
            $fid = fopen($path . "/" . $filename, 'r');

            //----------- Loop Over Contents of Single File ----------//
            while (!feof($fid)) {

                // Skip the header 
                $dump = trim(fgets($fid));
                // Get message:
                $line = trim(fgets($fid));
                
                ### 2023-04-04T00:00:00.916471Z,$GPGGA,000000.839,2030.86487,N,04538.19146,W,5,32,0.6,0.54,M,,,4,1015*19
                $date_time_out= get_date_time_from_string_nav33($line);
               
                $dateStringUTCStartFile = sprintf(
                    $date_time_out[0], $date_time_out[1], $date_time_out[2], $date_time_out[3],
                    $date_time_out[4], $date_time_out[5], $date_time_out[6]
                );
                break;

            } // end loop over file

            // Grab last complete data record from file:
            rewind($fid);
            $line = trim(lastLine($fid, "\n"));
            fclose($fid);

            if(LOCAL_DEBUG)print "DEBUG: last line: $line\n";
            $date_time_out_last= get_date_time_from_string_nav33($line);
            if(LOCAL_DEBUG)print_r($date_time_out_last);
            
             $dateStringUTCEndFile = sprintf(
                $date_time_out_last[0], $date_time_out_last[1], $date_time_out_last[2], $date_time_out_last[3],
                $date_time_out_last[4], $date_time_out_last[5], $date_time_out_last[6]
            );

            if(LOCAL_DEBUG) { 
                print "DEBUG: dateStringUTCStart = $dateStringUTCStart\n";
                print "DEBUG: dateStringUTCStartFile = $dateStringUTCStartFile\n";
                print "DEBUG: dateStringUTCEnd = $dateStringUTCEnd\n";
                print "DEBUG: dateStringUTCEndFile = $dateStringUTCEndFile\n";
                print "DEBUG: dateStringUTCEndFile:  (strtotime($dateStringUTCEndFile) :: dateStringUTCStart: strtotime($dateStringUTCStart)\n";

            }
            // Check that the start/end date/times in each file overlap
            // the start/end date/times entered on the command line:

            if (!( (strtotime($dateStringUTCEndFile) < strtotime($dateStringUTCStart) )
                || (strtotime($dateStringUTCStartFile) > strtotime($dateStringUTCEnd) ) )
            ) {

                $table[$inx]["start"] = strtotime($dateStringUTCStartFile);
                $table[$inx]["end"]   = strtotime($dateStringUTCEndFile);
                $table[$inx]["file"]  = $filename;
                $inx++;

            } else {

                $otherParseableFiles[$jnx]["start"]
                    = strtotime($dateStringUTCStartFile);
                $otherParseableFiles[$jnx]["end"]
                    = strtotime($dateStringUTCEndFile);
                $otherParseableFiles[$jnx]["file"] = $filename;
                $jnx++;

            } // end if within bounds

        } // if file is not "." nor ".."

    } // end loop over files in dir

    if(LOCAL_DEBUG)print_r($table);
    return $table;

}
function parse_nav33($navfilelist, $datapath, $fout) {
   //----------- Initialize variables: -----------//
   if(LOCAL_DEBUG)print "DEBUG: START: parse_nav33() \n";
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
       $filename = $datapath . "/" . $lineRec[0];
       $fid = fopen($filename, 'r');

       //----------- Loop Over Contents of Single File ----------//
       while (!feof($fid)) {

           // Skip the header 
           ##$dump = trim(fgets($fid));
           // Get NMEA message:
           $line = trim(fgets($fid));
           $date_time_out= get_date_time_from_string_nav33($line);


           // Skip over non-data records.  Records start with 2-digit month [00-12]
           if (preg_match('/GGA/', $line)) {

               $lines = preg_split('/\,\$/', $line);
               // preg_split removes leading '$' from NMEA string.  Put it back:
               $lines[1] = '$' . $lines[1];


              # $stringDateTime = preg_split("/\,/", $lines[0]);
              # $mm_dd_yyyy = preg_split("/\//", $stringDateTime[0]);
               $pc->month = $date_time_out[2];
               $pc->day   = $date_time_out[3];
               $pc->year  = $date_time_out[1];

               # $hh_mm_ss = preg_split("/\:/", $stringDateTime[1]);
               $pc->hour = $date_time_out[4];
               $pc->minute = $date_time_out[5];
               $pc->second = $date_time_out[6];
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
