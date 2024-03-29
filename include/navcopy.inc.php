<?php
/**
 * Define function to copy navigation rawdata from original format
 * into R2R navigation standard product format.  Quality assessment
 * and/or quality control is performed on the output file by separate
 * functions defined elsewhere.
 *
 * PHP version 5
 *
 * @category R2R_Products
 * @package  R2R_Nav
 * @author   Aaron Sweeney <asweeney@ucsd.edu>
 * @license  http://opensource.org/licenses/GPL-3.0 GNU General Public License
 * @link     http://www.rvdata.us
 */
date_default_timezone_set('UTC');
require_once 'flags.inc.php';
require_once 'nmeatools.inc.php';

//------------ Function Definitions -----------//

/**
 *  Get the position of the first non-zero element
 *  to the right of the decimal point.
 *
 * @param float $num Real number (positive)
 *
 * @return int  Return index
 */
function getLeastCount($num)
{
    if ($num < 0) return 0;  // $num must be positive.

    $inx = 0;
    while (floor($num) == 0) {
        $num = $num * 10;
        $inx++;
    }

    return $inx;
}

/**
 * Print one record in R2R nav standard format from GGA buffer
 *
 * @param string $fout      Output filename
 * @param array  $gpsBuffer GGA buffer, associative array
 * @param int    $inx       Buffer index
 */
function printBuffer($fout, $gpsBuffer, $inx)
{
    //  $gga = clone $gpsBuffer[$inx]->gga;

    if ($gpsBuffer[$inx]->gga->tim_nroz == 0) {
        $time_format = "%02d:%02d:%02d";
    } else {
        $time_format = "%02d:%02d:%0" . ($gpsBuffer[$inx]->gga->tim_nroz + 3)
            . "." . $gpsBuffer[$inx]->gga->tim_nroz . "f";
    }
    $hhmmss = sprintf(
        $time_format, $gpsBuffer[$inx]->gga->hour,
        $gpsBuffer[$inx]->gga->minute, $gpsBuffer[$inx]->gga->second
    );

    $datestamp = sprintf(
        "%4d-%02d-%02dT%sZ", $gpsBuffer[$inx]->gga->year,
        $gpsBuffer[$inx]->gga->month,
        $gpsBuffer[$inx]->gga->day, $hhmmss
    );

    fprintf(
        $fout, "%s\t%s\t%s\t%s\t%s\t%s\t%s\n", $datestamp,
        $gpsBuffer[$inx]->gga->lon, $gpsBuffer[$inx]->gga->lat,
        $gpsBuffer[$inx]->gga->gpsQuality,
        $gpsBuffer[$inx]->gga->numberOfSatellites,
        $gpsBuffer[$inx]->gga->horizontalDilution,
        $gpsBuffer[$inx]->gga->antennaAltitude
    );

} // end function printBuffer()


/**
 * Print one record in R2R nav standard format from RMC buffer
 *
 * @param string $fout      Output filename
 * @param array  $gpsBuffer RMC buffer, associative array
 * @param int    $inx       Buffer index
 */
function printBufferRMC($fout, $gpsBuffer, $inx)
{
    if ($gpsBuffer[$inx]->rmc->tim_nroz == 0) {
        $time_format = "%02d:%02d:%02d";
    } else {
        $time_format = "%02d:%02d:%0" . ($gpsBuffer[$inx]->rmc->tim_nroz + 3)
            . "." . $gpsBuffer[$inx]->rmc->tim_nroz . "f";
    }
    $hhmmss = sprintf(
        $time_format, $gpsBuffer[$inx]->rmc->hh,
        $gpsBuffer[$inx]->rmc->mm, $gpsBuffer[$inx]->rmc->ss
    );

    $datestamp = sprintf(
        "%4d-%02d-%02dT%sZ", $gpsBuffer[$inx]->rmc->year,
        $gpsBuffer[$inx]->rmc->month,
        $gpsBuffer[$inx]->rmc->day, $hhmmss
    );

    $gpsQuality = ($gpsBuffer[$inx]->rmc->status == 'A') ? 1 : 0;

    fprintf(
        $fout, "%s\t%s\t%s\t%s\tNAN\tNAN\tNAN\n", $datestamp,
        $gpsBuffer[$inx]->rmc->lon, $gpsBuffer[$inx]->rmc->lat,
        $gpsQuality
    );

} // end function printBufferRMC()


/**
 * Print one record in R2R nav standard format from GGA buffer
 *
 * @param string $fout Output filename
 * @param object $gga  GGA buffer object
 */
function printDatedGGA($fout, $gga)
{
    if ($gga->tim_nroz == 0) {
        $time_format = "%02d:%02d:%02d";
    } else {
        $time_format = "%02d:%02d:%0" . ($gga->tim_nroz + 3) . "."
            . $gga->tim_nroz . "f";
    }
    $hhmmss = sprintf($time_format, $gga->hour, $gga->minute, $gga->second);

    $datestamp = sprintf(
        "%4d-%02d-%02dT%sZ", $gga->year, $gga->month, $gga->day, $hhmmss
    );

    fprintf(
        $fout, "%s\t%s\t%s\t%s\t%s\t%s\t%s\n", $datestamp, $gga->lon, $gga->lat,
        $gga->gpsQuality, $gga->numberOfSatellites, $gga->horizontalDilution,
        $gga->antennaAltitude
    );

} // end function printDatedGGA()


/**
 * Translate navigation raw data file into R2R standard navigation format.
 *
 * @param string $inputFormatSpec R2R fileformat (e.g. "nav1")
 * @param string $path            Path to raw data files
 * @param array  $navfilelist     Input raw data file list (contains just
 *                                 filenames; not full path)
 * @param string $outfile         Output file in R2R standard navigation format.
 *
 * @return bool Returns true on successful completion.
 */
function navcopy($inputFormatSpec, $path, $navfilelist, $outfile)
{
    $fout = fopen($outfile, "w");
    if (is_null($fout)) {
        echo "navcopy(): Could not open file: " . $outfile . "\n";
        exit(1);
    }
    switch ($inputFormatSpec) {

    // "nav1": raw NMEA: GGA + ZDA
    // Vessels: Melville, Roger Revelle
    case "nav1":

        include('parsers/nav1.php');

        parse_nav1($navfilelist, $path, $fout);

        break;


    // "nav2": DAS: NOAA Shipboard Computer System (SCS)
    // Vessels: Atlantic Explorer, Clifford A. Barnes, Cape Hatteras,
    //          Endeavor, Savannah
    case "nav2":

        include('parsers/nav2.php');

        parse_nav2($navfilelist, $path, $fout);

        break;

    // "nav3": DAS: NOAA SCS - Partial GLL + occasional GGA
    // Vessels: Blue Heron
    case "nav3":

        include('parsers/nav3.php');

        parse_nav3($navfilelist, $path, $fout);

        break;

    // "nav4": DAS: WHOI Calliope
    // Vessels: Atlantis, Knorr
    case "nav4":

        //----------- Initialize variables: -----------//
        $maxBuffer = 86400;  // Max number of elements array can hold
        $gpsBuffer = array();
        $dateBufferLast = array();

        $nmea = new NMEA0183Message();
        $gga = new NMEA0183_GGA();
        $zda = new NMEA0183_ZDA();
        $rmc = new NMEA0183_RMC();
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
            $filename = $path . "/" . $line;
            //     echo "Reading " . $filename . "\n";
            $fid = fopen($filename, 'r');

            //----------- Get Date ----------//
            $datetimeLastKnown->init($fid);
            if (is_null($datetimeLastKnown)) {
                echo "No ZDA nor RMC date stamp in file.\n";
                exit(1);
            }
            rewind($fid);
            //------------ End Get Date -----------//

            //----------- Loop Over Contents of Single File ----------//
            while (!feof($fid)) {

                // Get Calliope record:
                $line = trim(fgets($fid));

                // GPS records start with 'GP' in Calliope files (skip all others):
                //	if (preg_match('/^GP/', $line)) {

                // 2012-04-04: Modified preg_match to look for 'GPS', not simply 'GP',
                // because some records are GPRMC_xxxx for a different instrument.
                if (preg_match('/^GPS/', $line)) {

                    // Skip forward to first NMEA message on line.
                    $newline = strstr($line, '$');
                    $line = $newline;

                    // Split multiple NMEA messages on same line into separate
                    // records:
                    $lines = preg_split('/\,\$/', $line);
                    // '$' is removed from $lines[1] and $lines[2] by preg_split(),
                    // so put it back in:
                    $lnxMax = count($lines);
                    for ($lnx=1; $lnx<$lnxMax; $lnx++) {
                        $lines[$lnx] = '$' . $lines[$lnx];
                    }

                    foreach ($lines as $line) {

                        $nmea->init($line);

                        // Is checksum valid?  (We allow data without checksums
                        // to be processed.)
                        if ((is_null($nmea->suppliedCheckSum))
                            || ($nmea->validCheckSum)
                        ) {

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

                                // Process buffer if it is full.
                                if ($binx < $maxBuffer) {
                                    // Still room in buffer--keep reading file.
                                    $binx++;
                                } else {
                                    // Buffer full--process it before continuing with file read.

                                    // Check to make sure we have read an ZDA or RMC message prior to filling
                                    // the buffer:
                                    if (!isset($zda->year)) {
                                        if (!isset($rmc->year)) {
                                            echo "No ZDA nor RMC message found prior to end of GPS buffer.\n";
                                            echo "Maybe the buffer size is too small?\n";
                                            exit(1);
                                        } else {
                                            $zda->year   = $rmc->year;
                                            $zda->month  = $rmc->month;
                                            $zda->day    = $rmc->day;
                                            $zda->hhmmss = $rmc->hhmmss;
                                        }
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
                                    $dateBufferLast["year"]   = $gpsBuffer[$linx]->gga->year;
                                    $dateBufferLast["month"]  = $gpsBuffer[$linx]->gga->month;
                                    $dateBufferLast["day"]    = $gpsBuffer[$linx]->gga->day;
                                    $dateBufferLast["hhmmss"] = $gpsBuffer[$linx]->gga->hhmmss;

                                    // Buffer has been printed.  Unset buffer and re-initialize
                                    // GPS buffer index:
                                    unset($gpsBuffer);
                                    $binx = 1;

                                } // end if $binx < $maxBuffer

                                // Or do we have a ZDA date/time stamp?
                            } else if ( preg_match('/^\$.{2}ZDA$/', $NavRec[0]) ) {

                                // echo "Found ZDA.\n";
                                // Process NMEA message as a ZDA date/time stamp:
                                $zda->init($NavRec);

                                // When we encounter a ZDA date/time stamp, we process the GPS buffer,
                                // starting from the beginning of the buffer (the earliest GGA records
                                // in the buffer):
                                // (1) Assign dates to GGA (tricky when day advances within buffer
                                //     or when RMC date/time is reported late.)
                                // (2) Print GPS buffer (all GGA messages dated)
                                // (3) Unset GPS buffer
                                $inx = 1;
                                $inxMax = count($gpsBuffer);
                                while ($inx<=$inxMax
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
                                $dateBufferLast["year"]   = $gpsBuffer[$linx]->gga->year;
                                $dateBufferLast["month"]  = $gpsBuffer[$linx]->gga->month;
                                $dateBufferLast["day"]    = $gpsBuffer[$linx]->gga->day;
                                $dateBufferLast["hhmmss"] = $gpsBuffer[$linx]->gga->hhmmss;

                                // Buffer has been printed.  Unset buffer and re-initialize
                                // GPS buffer index:
                                unset( $gpsBuffer );
                                $binx = 1;

                                // Or do we have a RMC date/time stamp?
                            } else if ( preg_match('/^\$.{2}RMC$/', $NavRec[0]) ) {

                                // echo "Found RMC.\n";
                                // Process NMEA message as a RMC date/time stamp:
                                $rmc->init($NavRec);

                                // When we encounter a RMC date/time stamp, we process the GPS buffer,
                                // starting from the beginning of the buffer (the earliest GGA records
                                // in the buffer):
                                // (1) Assign dates to GGA (tricky when day advances within buffer
                                //     or when RMC date/time is reported late.)
                                // (2) Print GPS buffer (all GGA messages dated)
                                // (3) Unset GPS buffer
                                $inx = 1;
                                $inxMax = count($gpsBuffer);
                                while ($inx<=$inxMax
                                    && ($gpsBuffer[$inx]->gga->hhmmss <= $rmc->hhmmss)
                                ) {
                                    // GGA same day as RMC
                                    $gpsBuffer[$inx]->gga->year  = $rmc->year;
                                    $gpsBuffer[$inx]->gga->month = $rmc->month;
                                    $gpsBuffer[$inx]->gga->day   = $rmc->day;
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

                                        if ($gpsBuffer[$jnx]->gga->hhmmss <= $rmc->hhmmss) { // GGA same day as RMC
                                            $gpsBuffer[$jnx]->gga->year  = $rmc->year;
                                            $gpsBuffer[$jnx]->gga->month = $rmc->month;
                                            $gpsBuffer[$jnx]->gga->day   = $rmc->day;
                                        } else {

                                            // Convert date to unix time, subtract 1 day,
                                            // and convert back to date:
                                            $dateUnix = strtotime( "-1 day", gmmktime(0, 0, 0, $rmc->month, $rmc->day, $rmc->year) );
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
                                $dateBufferLast["year"]   = $gpsBuffer[$linx]->gga->year;
                                $dateBufferLast["month"]  = $gpsBuffer[$linx]->gga->month;
                                $dateBufferLast["day"]    = $gpsBuffer[$linx]->gga->day;
                                $dateBufferLast["hhmmss"] = $gpsBuffer[$linx]->gga->hhmmss;

                                // Buffer has been printed.  Unset buffer and re-initialize
                                // GPS buffer index:
                                unset( $gpsBuffer );
                                $binx = 1;

                            } // end identify which NMEA message type

                        } // end if valid checksum (or checksum not supplied)

                        $irec++;

                    } // end loop over all NMEA messages in single line

                } // end if GPS record

            } // end while (!feof($fid))
            //------------ End Loop Over Contents of Single File ----------//

            fclose($fid);

        } // end foreach($navfilelist as $line)
        //------------ End Main Loop Over All Nav Files ------------//

        //--------- Might have unprocessed buffer at end of last file read -------
        if (isset($gpsBuffer) && count($gpsBuffer)>0) {

            //     echo "binx: " . $binx . "\n";
            // printf("%4d-%02d-%02dT%02d:%02d:%f\n", $zda->year, $zda->month, $zda->day,
            //	    $zda->hh, $zda->mm, $zda->ss);

            //     echo "hhmmss: " . $zda->hhmmss . " " . $gpsBuffer[1]->gga->hhmmss . "\n";

            // Check to make sure we have read a ZDA message prior to filling
            // the buffer:
            if (!isset($zda->year)) {
                if (!isset($rmc->year)) {
                    echo "No ZDA message found prior to end of GPS buffer.\n";
                    echo "Maybe the buffer size is too small?\n";
                    exit(1);
                } else {
                    $zda->year   = $rmc->year;
                    $zda->month  = $rmc->month;
                    $zda->day    = $rmc->day;
                    $zda->hhmmss = $rmc->hhmmss;
                }
            }

            // Initialize first GGA day based on last ZDA date/time stamp:
            // This fails if GGA is before midnight and ZDA is after midnight.
            if ($gpsBuffer[1]->gga->hhmmss >= $zda->hhmmss) {  // GGA same day as ZDA
                $gpsBuffer[1]->gga->year  = $zda->year;
                $gpsBuffer[1]->gga->month = $zda->month;
                $gpsBuffer[1]->gga->day   = $zda->day;
            } else {
                if (($gpsBuffer[1]->gga->hhmmss - $dateBufferLast["hhmmss"]) >= 0) {
                    // GGA is same day as end of previous buffer:
                    $gpsBuffer[1]->gga->year  = $dateBufferLast["year"];
                    $gpsBuffer[1]->gga->month = $dateBufferLast["month"];
                    $gpsBuffer[1]->gga->day   = $dateBufferLast["day"];
                } else { // GGA belongs to next day
                    // Convert date to unix time, add 1 day,
                    // and convert back to date:
                    $dateUnix = strtotime("+1 day", gmmktime(0, 0, 0, $zda->month, $zda->day, $zda->year));
                    $dateString = gmdate("Y-m-d", $dateUnix);
                    $dateArray = preg_split("/\-/", $dateString);

                    $gpsBuffer[1]->gga->year  = $dateArray[0];
                    $gpsBuffer[1]->gga->month = $dateArray[1];
                    $gpsBuffer[1]->gga->day   = $dateArray[2];
                }  // end if GGA day same as end of previous buffer
            } // end if

            for ($inx=1; $inx<$binx; $inx++) {

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
        break;

    // "nav5": WHOI Calliope (2007)
    // Vessels: Oceanus
    case "nav5":

        include('parsers/nav5.php');

        parse_nav5($navfilelist, $path, $fout);

        break;


    // "nav6": DAS: UDel Surface Mapping System (SMS)
    // Vessels: Hugh R. Sharp
    case "nav6":

        include('parsers/nav6.php');

        parse_nav6($navfilelist, $path, $fout);

        break;


    // "nav7": DAS: UH-specific
    // Vessels: Ka'imikai-o-Kanaloa
    case "nav7":
        echo "navcopy(): Unsupported input file format.\n";
        exit(1);
        break;

    // "nav8": DAS: UH-specific
    // Vessel: Kilo Moana
    case "nav8":

        include('parsers/nav8.php');

        parse_nav8($navfilelist, $path, $fout);

        break;


    // "nav9": DAS: SIO-specific (satdata)
    // Vessels: New Horizon, Robert Gordon Sproul
    case "nav9":

        include('parsers/nav9.php');

        parse_nav9($navfilelist, $path, $fout);

        break;


    // "nav10": DAS: UW-specific
    // Vessels: Thomas G. Thompson
    case "nav10":

        include('parsers/nav10.php');

        parse_nav10($navfilelist, $path, $fout);

        break;


    // "nav11": DAS: UMiami-specific
    // Vessel: F. G. Walton Smith
    case "nav11":

        include('parsers/nav11.php');

        parse_nav11($navfilelist, $path, $fout);

        break;


    // "nav12": DAS: device_id + external clock + GGA and ZDA
    // Vessels: Healy, Marcus G. Langseth
    case "nav12":

        include('parsers/nav12.php');

        parse_nav12($navfilelist, $path, $fout);

        break;


    // "nav13": DAS: LUMCON Multiple Instrument Data Aquisition System (MIDAS)
    // Vessels: Pelican
    case "nav13":

        // Note: Uses external clock time instead of GPS receiver clock time.

        // Need to loop over all nav files in a cruise, in the order specified
        // by external control file.
        foreach ($navfilelist as $line) {

            // $line = trim( fgets($fin) );
            if ($line == "") break;
            $filename = $path . "/" . $line;
            $fid = fopen($filename, 'r');

            $inx = 1;
            //----------- Loop Over Contents of Single File ----------//
            while (!feof($fid)) {

                $line = trim(fgets($fid));
                if ($line=="") break;

                if ($inx > 1) {  // Skip over header record.

                    $NavRec = preg_split("/\,/", $line);  // comma-separated values

                    $dateRec = preg_split("/\//", $NavRec[0]);  // values separated by slash "/"
                    $timeRec = preg_split("/\:/", $NavRec[1]);  // values separated by colon ":"
                    $hhmmss = $NavRec[2];    // GPS receiver clock

                    // Check the number of digits to the left of '.' in lat:
                    if (preg_match('/\./', $NavRec[3])) {
                        $roz = preg_split('/\./', $NavRec[3]);
                        $lat_nloz = strlen($roz[0]);
                    } else {
                        $lat_nloz = strlen($NavRec[3]);
                    }

                    // Check for no lat or incomplete lat:
                    if (!is_numeric($NavRec[3]) || $lat_nloz < 4) {

                        $lat = "NAN" ;
                        $lat_format = "%s";

                    } else {  // if lat:

                        // Decode the latitude and its precision:
                        $lat_nmea0183 = floatval($NavRec[3]);      // DDMM.MMMM

                        $lat_deg = intval($lat_nmea0183/100);
                        $lat_min = $lat_nmea0183 - ($lat_deg*100);
                        $lat = $lat_deg + ($lat_min/60);
                        if (preg_match('/\./', $NavRec[3])) {
                            $roz = preg_split('/\./', $NavRec[3]);
                            $lat_nroz = strlen($roz[1]);
                        } else {
                            $lat_nroz = 0;
                        }
                        $northsouth = 'N';  // Not provided by data!!!

                        if ($northsouth == 'S') {
                            $lat = -$lat;
                        }
                        $lat_format = "%." . ($lat_nroz + 2) . "f";

                    }  // end if no lat

                    // Check the number of digits to the left of '.' in lon:
                    if (preg_match('/\./', $NavRec[4])) {
                        $roz = preg_split('/\./', $NavRec[4]);
                        $lon_nloz = strlen($roz[0]);
                    } else {
                        $lon_nloz = strlen($NavRec[4]);
                    }

                    // Check for no lon or incomplete lon:
                    if (!is_numeric($NavRec[4]) || $lon_nloz < 5) {

                        $lon = "NAN" ;
                        $lon_format = "%s";

                    } else {  // if lon:

                        // Decode the longitude and its precision:
                        $lon_nmea0183 = floatval($NavRec[4]);   // DDDMM.MMMM

                        $lon_deg = intval($lon_nmea0183/100);
                        $lon_min = $lon_nmea0183 - ($lon_deg*100);
                        $lon = $lon_deg + ($lon_min/60);
                        if (preg_match('/\./', $NavRec[4])) {
                            $roz = preg_split('/\./', $NavRec[4]);
                            $lon_nroz = strlen($roz[1]);
                        } else {
                            $lon_nroz = 0;
                        }
                        $eastwest = 'W';  // Not provided by data!!!

                        if ($eastwest == 'W') {
                            $lon = -$lon;
                        }
                        $lon_format = "%." . ($lon_nroz + 2) . "f";

                    } // end if no lon

                    // Decode the date and time and the time precision:
                    $month = $dateRec[0];
                    $day   = $dateRec[1];
                    $year  = $dateRec[2];

                    // External clock time:
                    $hour   = $timeRec[0];
                    $minute = $timeRec[1];
                    $second = $timeRec[2];

                    if (preg_match("/\./", $second)) {
                        $roz = preg_split('/\./', $second);
                        $tim_nroz = strlen($roz[1]);
                    } else {
                        $tim_nroz = 0;
                    }

                    // GPS receiver time:
                    //	  $hour   = intval($hhmmss/1e4);
                    // $minute = intval(($hhmmss - ($hour*1e4))/1e2);
                    //$second = $hhmmss - ($hour*1e4) - ($minute*1e2);

                    //if (preg_match("/\./", $NavRec[2])) {
                    //  $roz = preg_split('/\./', $NavRec[2]);
                    //  $tim_nroz = strlen($roz[1]);
                    //} else {
                    //  $tim_nroz = 0;
                    // }

                    // Print exactly the same precision time stamp as in the recorded data.
                    if ($tim_nroz == 0) {
                        $time_format = "%4d-%02d-%02dT%02d:%02d:%02dZ";
                    } else {
                        $time_format = "%4d-%02d-%02dT%02d:%02d:%0" . ($tim_nroz + 3) . "." . $tim_nroz . "fZ";
                    }

                    $datestamp = sprintf( $time_format, $year, $month, $day, $hour, $minute, $second );

                    $print_format = "%s\t" . $lon_format . "\t" . $lat_format . "\t%s\t%s\t%s\t%s\n";

                    // This format does not record GGA information.  Fill in with "NAN".
                    $qual = "NAN";
                    $nsat = "NAN";
                    $hdop = "NAN";
                    $alt  = "NAN";

                    fprintf(
                        $fout, $print_format,
                        $datestamp, $lon, $lat,
                        $qual, $nsat, $hdop, $alt
                    );

                } // end if not header record

                $inx++;

            } //end while (!feof($fid))

            fclose($fid);

        } // end foreach($navfilelist as $line)
        //------------ End Main Loop Over All Nav Files ------------//
        break;

        // "nav14": DAS: MLML Underway Data Aquisition System (UDAS)
        // Vessels: Point Sur
    case "nav14":

        // Need to loop over all nav files in a cruise, in the order specified
        // by external control file.
        foreach ($navfilelist as $line) {

            // $line = trim( fgets($fin) );
            if ($line == "") break;
            $filename = $path . "/" . $line;
            $fid = fopen($filename, 'r');

            $inx = 1;
            //----------- Loop Over Contents of Single File ----------//
            while (!feof($fid)) {

                $line = trim(fgets($fid));
                if ($line=="") break;

                if ($inx > 1) {  // Skip the header record.

                    $NavRec = preg_split("/\,/", $line);  // comma-separated values

                    $yyyymmdd = $NavRec[2];
                    $hhmmss   = $NavRec[3];

                    // Check for no lat:
                    if (empty($NavRec[4]) || empty($NavRec[5])) {

                        $lat = "NAN" ;
                        $lat_format = "%s";

                    } else {  // if lat:

                        // Decode the latitude and its precision:
                        $lat_deg = $NavRec[4];
                        $lat_min = $NavRec[5];
                        $lat = $lat_deg + ($lat_min/60);
                        if (preg_match('/\./', $NavRec[5])) {
                            $roz = preg_split('/\./', $NavRec[5]);
                            $lat_nroz = strlen($roz[1]);
                        } else {
                            $lat_nroz = 0;
                        }
                        $northsouth = 'N';  // Not present in data!!!

                        if ($northsouth == 'S') {
                            $lat = -$lat;
                        }
                        $lat_format = "%." . ($lat_nroz + 2) . "f";

                    } // if no lat

                    if (empty($NavRec[6]) || empty($NavRec[7])) {

                        $lon = "NAN" ;
                        $lon_format = "%s";

                    } else {  // if lon:

                        // Decode the longitude and its precision:
                        $lon_deg = $NavRec[6];
                        $lon_min = $NavRec[7];
                        $lon = $lon_deg + ($lon_min/60);
                        if (preg_match('/\./', $NavRec[7])) {
                            $roz = preg_split('/\./', $NavRec[7]);
                            $lon_nroz = strlen($roz[1]);
                        } else {
                            $lon_nroz = 0;
                        }
                        $eastwest = 'W';  // Not present in data!!!

                        if ($eastwest == 'W') {
                            $lon = -$lon;
                        }
                        $lon_format = "%." . ($lat_nroz + 2) . "f";

                    } // if no lon

                    // Decode the date and time and the time precision:
                    $year  = intval($yyyymmdd/1e4);
                    $month = intval(($yyyymmdd - ($year*1e4))/1e2);
                    $day   = $yyyymmdd - ($year*1e4) - ($month*1e2);

                    $hour   = intval($hhmmss/1e4);
                    $minute = intval(($hhmmss - ($hour*1e4))/1e2);
                    $second = $hhmmss - ($hour*1e4) - ($minute*1e2);

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

                    $print_format = "%s\t" . $lon_format . "\t" . $lat_format . "\t%s\t%s\t%s\t%s\n";

                    // This format does not record GGA information.  Fill in with "NAN".
                    $qual = "NAN";
                    $nsat = "NAN";
                    $hdop = "NAN";
                    $alt  = "NAN";

                    fprintf(
                        $fout, $print_format,
                        $datestamp, $lon, $lat,
                        $qual, $nsat, $hdop, $alt
                    );

                } // end if inx > 1 (skip header record)

                $inx++;  // increment the record counter

            } //end while (!feof($fid))

            fclose($fid);

        } // end foreach($navfilelist as $line)
        //------------ End Main Loop Over All Nav Files ------------//
        break;

        // "nav15": DAS: OSU comma-separated value format (csv)
        // Vessels: Wecoma, Oceanus
    case "nav15":

        //----------- Initialize variables: -----------//
        $maxBuffer = 86400;  // Max number of elements array can hold
        $gpsBuffer = array();

        $dateBufferLast = null;  // Initially unknown date.
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

                // Skip over non-data records.  Data Records start with "DATA".
                if (preg_match('/GGA/', $line)) {

                    $lines = preg_split('/\,/', $line);

                    // External clock:
                    $dateStringUTC = trim($lines[1], " \t\n\r\0\x0B'\"");
                    $count = sscanf($dateStringUTC, "%4c-%2c-%2cT%2c:%2c:%s",
                                    $year, $month, $day, $hour, $minute, $second);
                    $ntp["year"]   = $year;
                    $ntp["month"]  = $month;
                    $ntp["day"]    = $day;
                    $ntp["hour"]   = $hour;
                    $ntp["minute"] = $minute;
                    $ntp["second"] = trim($second, "Z");

                    if (preg_match("/\./", $ntp["second"])) {
                        $roz = preg_split('/\./', $ntp["second"]);
                        $tim_nroz = strlen($roz[1]);
                    } else {
                        $tim_nroz = 0;
                    }
                    // Print exactly the same precision time stamp as in the recorded data.
                    if ($tim_nroz == 0) {
                        $time_format = "%02d%02d%02d";
                    } else {
                        $time_format = "%02d%02d%0" . ($tim_nroz + 3) . "." . $tim_nroz . "f";
                    }
                    $ntp["hhmmss"] = floatval(sprintf($time_format, $ntp["hour"], $ntp["minute"], $ntp["second"]));

                    // NMEA string may or may not be double-quoted (""):
#                    if (preg_match("/\"/", $line)) {
#
#                        // Get NMEA record in double-quotes (""):
#                        $lines = preg_split("/\"/", $line);
#
#                    } else {
#
#                        // Use the first character of the NMEA string, a dollar-sign ($):
#                        $lines = preg_split('/\$/', $line);
#                        // preg_split removes leading '$' from NMEA string.  Put it back:
#                        $lines[1] = '$' . $lines[1];
#
#                    }

					// Quotes may be present in various forms on all strings - try splitting on comma
					$lines = preg_split("/\, /", $line);

                    $nmea->init(trim($lines[2], " \t\n\r\0\x0B'\""));

                    // Is checksum valid?  (We don't allow data without checksums to be processed.)
                    if ($nmea->validCheckSum === true) {
                        // Is checksum valid?  (We allow data without checksums to be processed.)
                        //if ((is_null($nmea->suppliedCheckSum)) || ($nmea->validCheckSum)) {

                        $NavRec = preg_split('/\,/', $nmea->data);
                        //echo "NavRec: " . $line . "\n";

                        // Do we have a valid GGA message?
                        //if (preg_match('/^\$.{2}GGA$/', $NavRec[0])) {
                        if (preg_match('/^\$.{2}GGA$/', $NavRec[0]) && valid_gga_message( $NavRec )) {
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
                            $gpsBuffer[$binx]->gga->year  = $ntp["year"];
                            $gpsBuffer[$binx]->gga->month = $ntp["month"];
                            $gpsBuffer[$binx]->gga->day   = $ntp["day"];

                            // Process buffer if it is full.
                            if ($binx < $maxBuffer) {
                                // Still room in buffer--keep reading file.
                                $binx++;
                            } else {
                                // Buffer full--process it before continuing with file read.

                                for ($inx=1; $inx<=$maxBuffer; $inx++) {

                                    $delt = $gpsBuffer[$inx+1]->gga->hhmmss - $gpsBuffer[$inx]->gga->hhmmss;

                                    if ($gpsBuffer[$inx+1]->gga->hhmmss < $gpsBuffer[$inx]->gga->hhmmss && $delt < -100000) {
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
                                // GPS buffer index:                unset($gpsBuffer);
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

                $delt = $gpsBuffer[$inx+1]->gga->hhmmss - $gpsBuffer[$inx]->gga->hhmmss;

                if ($gpsBuffer[$inx+1]->gga->hhmmss < $gpsBuffer[$inx]->gga->hhmmss && $delt < -100000) {
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

            // Buffer has been printed.  Unset buffer and re-initialize
            // GPS buffer index:
            unset($gpsBuffer);
            $binx = 1;

        } // end if (isset())
        break;

        // "nav16": DAS: NobelTec track point file
        // Vessels: Blue Heron
    case "nav16":

        // Need to loop over all nav files in a cruise, in the order specified
        // by external control file.
        foreach ($navfilelist as $line) {

            // $line = trim( fgets($fin) );
            if ($line == "") break;
            $filename = $path . "/" . $line;
            $fid = fopen($filename, 'r');

            //----------- Loop to Skip Header of Single File ----------//
            while (!feof($fid)) {

                $line = trim(fgets($fid));

                if (preg_match('/^TrackMarks = \{\{/', $line)) {
                    break;  // Reached start of navigation data.
                }

            } // end loop over header records

            //----------- Loop Over Contents of Single File ----------//
            while (!feof($fid)) {

                $line = trim(fgets($fid));

                if (preg_match('/^\}\}/', $line)) {
                    break;  // Reached end of navigation data.
                } else {

                    $NavRec = preg_split("/[\s]+/", $line);  // whitespace-separated values

                    //	  if (count($NavRec) != 8) {
                    //  echo $line, "\n";
                    // }

                    $lat_deg = $NavRec[0];
                    $lat_min = $NavRec[1];
                    $lat = $lat_deg + ($lat_min/60);

                    // Check the number of digits to the left of '.' in lat:
                    if (preg_match('/\./', $NavRec[1])) {
                        $roz = preg_split('/\./', $NavRec[1]);
                        $lat_nroz = strlen($roz[1]);
                    } else {
                        $lat_nroz = 0;
                    }
                    $lat_format = "%." . ($lat_nroz + 2) . "f";

                    $northsouth = $NavRec[2];
                    if ($northsouth == 'S') {
                        $lat = -$lat;
                    }

                    $lon_deg = $NavRec[3];
                    $lon_min = $NavRec[4];
                    $lon = $lon_deg + ($lon_min/60);

                    // Check the number of digits to the left of '.' in lon:
                    if (preg_match('/\./', $NavRec[4])) {
                        $roz = preg_split('/\./', $NavRec[4]);
                        $lon_nroz = strlen($roz[1]);
                    } else {
                        $lon_nroz = 0;
                    }
                    $lon_format = "%." . ($lon_nroz + 2) . "f";

                    $eastwest = $NavRec[5];
                    if ($eastwest == 'W') {
                        $lon = -$lon;
                    }

                    $dateRec = preg_split("/\-/", $NavRec[6]);  // values separated by slash "-"
                    $timeRec = preg_split("/\:/", $NavRec[7]);  // values separated by colon ":"

                    // Decode the date and time and the time precision:
                    $year  = $dateRec[0];
                    $month = $dateRec[1];
                    $day   = $dateRec[2];

                    // External clock time:
                    $hour   = $timeRec[0];
                    $minute = $timeRec[1];
                    $second = trim($timeRec[2], "Z");

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

                    $print_format = "%s\t" . $lon_format . "\t" . $lat_format . "\t%s\t%s\t%s\t%s\n";

                    // This format does not record GGA information.  Fill in with "NAN".
                    $qual = "NAN";
                    $nsat = "NAN";
                    $hdop = "NAN";
                    $alt  = "NAN";

                    fprintf(
                        $fout, $print_format,
                        $datestamp, $lon, $lat,
                        $qual, $nsat, $hdop, $alt
                    );

                } // end if not end of TrackMarks block

            } // end loop over navigation data records

            fclose($fid);

        } // end foreach($navfilelist as $line)
        //------------ End Main Loop Over All Nav Files ------------//
        break;

        // "nav17": WHOI Calliope (2010, 2011)
        // Vessels: Oceanus
    case "nav17":

        //----------- Initialize variables: -----------//
        $maxBuffer = 86400;  // Max number of elements array can hold

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
            $filename = $path . "/" . $line;
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

                // Get Calliope record:
                $line = trim(fgets($fid));

                // GPS records start with 'GP' in Calliope files (skip all others):
                if (preg_match('/^GPRMC_90D/', $line)) {   // 2010, 2011

                    // Skip forward to first NMEA message on line.
                    $newline = strstr($line, '$');
                    $line = $newline;

                    // Split multiple NMEA messages on same line into separate records:
                    $lines = preg_split('/\,\$/', $line);
                    // '$' is removed from $lines[1] and $lines[2] by preg_split(), so put it back in:
                    $lnxMax = count($lines);
                    for ($lnx=1; $lnx<$lnxMax; $lnx++) {
                        $lines[$lnx] = '$' . $lines[$lnx];
                    }

                    foreach ($lines as $line) {

                        $nmea->init($line);

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

                    } // end loop over all NMEA messages in single line

                } // end if GPS record

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
        break;


        // "nav18": WHOI Calliope (2009)
        // Vessels: Oceanus
    case "nav18":

        //----------- Initialize variables: -----------//
        $maxBuffer = 86400;  // Max number of elements array can hold

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
            $filename = $path . "/" . $line;
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

                // Get Calliope record:
                $line = trim(fgets($fid));

                // GPS records start with 'GP' in Calliope files (skip all others):
                if (preg_match('/^GPRMC_(GP)?1850/', $line)) {   // 2009, matches GPRMC_GP1850 and GPRMC_1850

                    // Skip forward to first NMEA message on line.
                    $newline = strstr($line, '$');
                    $line = $newline;

                    // Split multiple NMEA messages on same line into separate records:
                    $lines = preg_split('/\,\$/', $line);
                    // '$' is removed from $lines[1] and $lines[2] by preg_split(), so put it back in:
                    $lnxMax = count($lines);
                    for ($lnx=1; $lnx<$lnxMax; $lnx++) {
                        $lines[$lnx] = '$' . $lines[$lnx];
                    }

                    foreach ($lines as $line) {

                        $nmea->init($line);

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

                    } // end loop over all NMEA messages in single line

                } // end if GPS record

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
        break;

        // "nav19": DAS: OSU-specific (2009 and 2010)
        // Vessel: Wecoma
    case "nav19":

        //----------- Initialize variables: -----------//
        $dateBufferLast = null;  // Initially unknown date.

        // Need to loop over all nav files in a cruise, in the order specified
        // by external control file.
        foreach ($navfilelist as $line) {

            $lineRec = preg_split("/[\s]+/", $line);
            $filename = $path . "/" . $lineRec[0];
            $baseyear = $lineRec[1];  // baseyear for use with day-of-year
            $fid = fopen($filename , 'r');

            $inx = 1;  // Record index
            $last_known_rec = 0;
            //----------- Loop Over Contents of Single File ----------//
            while (!feof($fid)) {

                $line = trim(fgets($fid));

                if (($inx > 1)  && ($line != "")) {  // Skip over header record and any blank lines.

                    $NavRec = preg_split("/\,/", $line);  // comma-separated values

                    $nrec = $NavRec[0];
                    if ($nrec > $last_known_rec) {

                        $pc_timestamp = preg_split("/\:/", $NavRec[1]);
                        $pc_doy = $pc_timestamp[0];
                        $pc_hour = $pc_timestamp[1];
                        $pc_minute = $pc_timestamp[2];
                        $pc_second = $pc_timestamp[3];

                        // Convert DOY to Month and Day:
                        $pc_year  = $baseyear;
                        $result   = doy2mmdd($pc_year, $pc_doy);
                        $pc_month = $result["month"];
                        $pc_day   = $result["day"];

                        $gps_timestamp = preg_split("/\:/", $NavRec[3]);
                        $hour   = $gps_timestamp[0];
                        if ($hour >= 0) {  // This DAS uses -9:99:99 and -999999 when values are absent.
                            $minute = $gps_timestamp[1];
                            $second = $gps_timestamp[2];

                            if (is_null($dateBufferLast)) {
                                $year  = $pc_year;
                                $month = $pc_month;
                                $day   = $pc_day;
                                $dateBufferLast->year  = $year;
                                $dateBufferLast->month = $month;
                                $dateBufferLast->day   = $day;
                            } else {
                                if ($hhmmss <= $pc_hhmmss) { // Same day:
                                    $year  = $pc_year;
                                    $month = $pc_month;
                                    $day   = $pc_day;
                                } else { // Previous day:
                                    $year  = $dateBufferLast->year;
                                    $month = $dateBufferLast->month;
                                    $day   = $dateBufferLast->day;
                                }
                            }
                            $dateBufferLast->year  = $year;
                            $dateBufferLast->month = $month;
                            $dateBufferLast->day   = $day;

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

                            $lat = $NavRec[4];
                            $lon = $NavRec[5];
                            $qual = "NAN";
                            $nsat = "NAN";
                            $hdop = "NAN";

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

                            $datestamp = sprintf( $time_format, $year, $month, $day, $hour, $minute,
                                                  $second, $millisecond );

                            $print_format = "%s\t" . $lon_format . "\t" . $lat_format . "\t" .
                                $qual_format . "\t%s\n";

                            fprintf(
                                $fout, $print_format,
                                $datestamp, $lon, $lat,
                                $qual, $nsat, $hdop, $alt
                            );

                        } // end if ($hour >= 0)

                        $last_known_rec = $nrec;

                    } // end if ($nrec > $last_known_rec)

                } // end if not header record

                $inx++;

            } //end while (!feof($fid))

            fclose($fid);

        } // end foreach($navfilelist as $line)
        //------------ End Main Loop Over All Nav Files ------------//
        break;

        // "nav20": external clock + tags + raw NMEA: GGA + ZDA
        // Vessels: Knorr (2013+)
    case "nav20":

        include('parsers/nav20.php');

        parse_nav20($navfilelist, $path, $fout);

        break;


        // "nav21": DAS: MLML Underway Data Aquisition System (UDAS)
        // Vessels: Point Sur (2008)
    case "nav21":

        // Need to loop over all nav files in a cruise, in the order specified
        // by external control file.
        foreach ($navfilelist as $line) {

            // $line = trim( fgets($fin) );
            if ($line == "") break;
            $filename = $path . "/" . $line;
            $fid = fopen($filename, 'r');

            $inx = 1;
            //----------- Loop Over Contents of Single File ----------//
            while (!feof($fid)) {

                $line = trim(fgets($fid));
                if ($line=="") break;

                if ($inx > 1) {  // Skip the header record.

                    $NavRec = preg_split("/\,/", $line);  // comma-separated values

                    $dateRec = preg_split("/\//", $NavRec[0]);
                    $timeRec = preg_split("/\:/", $NavRec[1]);

                    // Check for no lat:
                    if (empty($NavRec[2]) || empty($NavRec[3])) {

                        $lat = "NAN" ;
                        $lat_format = "%s";

                    } else {  // if lat:

                        // Decode the latitude and its precision:
                        $lat_deg = $NavRec[2];
                        $lat_min = $NavRec[3];
                        $lat = $lat_deg + ($lat_min/60);
                        if (preg_match('/\./', $NavRec[3])) {
                            $roz = preg_split('/\./', $NavRec[3]);
                            $lat_nroz = strlen($roz[1]);
                        } else {
                            $lat_nroz = 0;
                        }
                        $northsouth = 'N';  // Not present in data!!!

                        if ($northsouth == 'S') {
                            $lat = -$lat;
                        }
                        $lat_format = "%." . ($lat_nroz + 2) . "f";

                    } // if no lat

                    // Check for no lon:
                    if (empty($NavRec[4]) || empty($NavRec[5])) {

                        $lon = "NAN" ;
                        $lon_format = "%s";

                    } else {  // if lon:

                        // Decode the longitude and its precision:
                        $lon_deg = $NavRec[4];
                        $lon_min = $NavRec[5];
                        $lon = $lon_deg + ($lon_min/60);
                        if (preg_match('/\./', $NavRec[5])) {
                            $roz = preg_split('/\./', $NavRec[5]);
                            $lon_nroz = strlen($roz[1]);
                        } else {
                            $lon_nroz = 0;
                        }
                        $eastwest = 'W';  // Not present in data!!!

                        if ($eastwest == 'W') {
                            $lon = -$lon;
                        }
                        $lon_format = "%." . ($lat_nroz + 2) . "f";

                    } // if no lon

                    // Decode the date and time and the time precision:
                    $month  = $dateRec[0];
                    $day    = $dateRec[1];
                    $year   = 2000 + $dateRec[2];  // 2-digit year (only works for 2000+)

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

                    $print_format = "%s\t" . $lon_format . "\t" . $lat_format . "\t%s\t%s\t%s\t%s\n";

                    // This format does not record GGA information.  Fill in with "NAN".
                    $qual = "NAN";
                    $nsat = "NAN";
                    $hdop = "NAN";
                    $alt  = "NAN";

                    fprintf(
                        $fout, $print_format,
                        $datestamp, $lon, $lat,
                        $qual, $nsat, $hdop, $alt
                    );

                } // end if inx > 1 (skip header record)

                $inx++;  // increment the record counter

            } //end while (!feof($fid))

            fclose($fid);

        } // end foreach($navfilelist as $line)
        //------------ End Main Loop Over All Nav Files ------------//
        break;


        // "nav22": NMEA GGA only (date in filename)
        // Vessels: Roger Revelle, New Horizon
    case "nav22":
        //----------- Initialize variables: -----------//
        $maxBuffer = 86400;  // Max number of elements array can hold
        $gpsBuffer = array();

        $dateBufferLast = null;  // Initially unknown date.
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

            // Read date from filename:
            $pieces = preg_split('/\_/', $filename);
            $datestamp = $pieces[1];

            // Decode the file date and time:
            $pc->year   = intval(substr($datestamp, 0, 4));
            $pc->month  = intval(substr($datestamp, 4, 2));
            $pc->day    = intval(substr($datestamp, 6, 2));
            $pc->hour   = intval(substr($datestamp, 8, 2));
            $pc->minute = intval(substr($datestamp, 10, 2));
            $pc->second = intval(substr($datestamp, 12, 2));

            $fid = fopen($filename, 'r');

            //----------- Loop Over Contents of Single File ----------//
            while (!feof($fid)) {

                // Get NMEA message:
                $line = trim(fgets($fid));

                // Skip over non-data records.
                if (preg_match('/^\$.{2}GGA/', $line)) {

                    $nmea->init($line);

                    // Is checksum valid?  (We don't allow data without checksums
                    // to be processed.)
                    if ($nmea->validCheckSum === true) {
                        // Is checksum valid?  (We allow data without checksums to
                        // be processed.)
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

                                for ($inx=1; $inx<=$maxBuffer; $inx++) {

                                    $delt = $gpsBuffer[$inx+1]->gga->hhmmss - $gpsBuffer[$inx]->gga->hhmmss;

                                    if ($gpsBuffer[$inx+1]->gga->hhmmss < $gpsBuffer[$inx]->gga->hhmmss && $delt < -100000) {
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

            for ($inx=1; $inx<$binx; $inx++) {

                $delt = $gpsBuffer[$inx+1]->gga->hhmmss - $gpsBuffer[$inx]->gga->hhmmss;

                if ($gpsBuffer[$inx+1]->gga->hhmmss < $gpsBuffer[$inx]->gga->hhmmss && $delt < -100000) {
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

            // Buffer has been printed.  Unset buffer and re-initialize
            // GPS buffer index:
            unset($gpsBuffer);
            $binx = 1;

        } // end if (isset())
        break;


        // "nav23": DAS: MLML Underway Data Aquisition System (UDAS) -- minus signs included!
        // Vessels: Point Sur
    case "nav23":

        // Need to loop over all nav files in a cruise, in the order specified
        // by external control file.
        foreach ($navfilelist as $line) {

            // $line = trim( fgets($fin) );
            if ($line == "") break;
            $filename = $path . "/" . $line;
            $fid = fopen($filename, 'r');

            $inx = 1;
            //----------- Loop Over Contents of Single File ----------//
            while (!feof($fid)) {

                $line = trim(fgets($fid));
                if ($line=="") break;

                if ($inx > 1) {  // Skip the header record.

                    $NavRec = preg_split("/\,/", $line);  // comma-separated values

                    $yyyymmdd = $NavRec[2];
                    $hhmmss   = $NavRec[3];

                    // Check for no lat:
                    if (empty($NavRec[4]) || empty($NavRec[5])) {

                        $lat = "NAN" ;
                        $lat_format = "%s";

                    } else {  // if lat:

                        // Decode the latitude and its precision:
                        $lat_deg = $NavRec[4];  // minus sign possibly included w/integer degrees
                        $lat_min = $NavRec[5];  // but not with decimal minutes
                        $lat = abs($lat_deg) + ($lat_min/60);
                        if (preg_match('/\-/', $NavRec[4])) {  // Check for minus sign
                            $lat = -$lat;
                        }
                        if (preg_match('/\./', $NavRec[5])) {
                            $roz = preg_split('/\./', $NavRec[5]);
                            $lat_nroz = strlen($roz[1]);
                        } else {
                            $lat_nroz = 0;
                        }
                        $lat_format = "%." . ($lat_nroz + 2) . "f";

                    } // if no lat

                    if (empty($NavRec[6]) || empty($NavRec[7])) {

                        $lon = "NAN" ;
                        $lon_format = "%s";

                    } else {  // if lon:

                        // Decode the longitude and its precision:
                        $lon_deg = $NavRec[6];  // minus sign possibly included w/integer degrees
                        $lon_min = $NavRec[7];  // but not with decimal minutes
                        $lon = abs($lon_deg) + ($lon_min/60);
                        if (preg_match('/\-/', $NavRec[6])) {  // Check for minus sign
                            $lon = -$lon;
                        }
                        if (preg_match('/\./', $NavRec[7])) {
                            $roz = preg_split('/\./', $NavRec[7]);
                            $lon_nroz = strlen($roz[1]);
                        } else {
                            $lon_nroz = 0;
                        }
                        $lon_format = "%." . ($lat_nroz + 2) . "f";

                    } // if no lon

                    // Decode the date and time and the time precision:
                    $year  = intval($yyyymmdd/1e4);
                    $month = intval(($yyyymmdd - ($year*1e4))/1e2);
                    $day   = $yyyymmdd - ($year*1e4) - ($month*1e2);

                    $hour   = intval($hhmmss/1e4);
                    $minute = intval(($hhmmss - ($hour*1e4))/1e2);
                    $second = $hhmmss - ($hour*1e4) - ($minute*1e2);

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

                    $print_format = "%s\t" . $lon_format . "\t" . $lat_format . "\t%s\t%s\t%s\t%s\n";

                    // This format does not record GGA information.  Fill in with "NAN".
                    $qual = "NAN";
                    $nsat = "NAN";
                    $hdop = "NAN";
                    $alt  = "NAN";

                    fprintf(
                        $fout, $print_format,
                        $datestamp, $lon, $lat,
                        $qual, $nsat, $hdop, $alt
                    );

                } // end if inx > 1 (skip header record)

                $inx++;  // increment the record counter

            } //end while (!feof($fid))

            fclose($fid);

        } // end foreach($navfilelist as $line)
        //------------ End Main Loop Over All Nav Files ------------//
        break;

		// "nav24": raw NMEA: GGA + ZDA
		// Vessels: Sikuliaq
	case "nav24":

		//----------- Initialize variables: -----------//
		$maxBuffer = 86400;  // Max number of elements array can hold
		$gpsBuffer = array();

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

			//	  $line = trim( fgets($fin) );
			if ($line == "") break;
			$filename = $path . "/" . $line;
			//	 echo "Reading " . $filename . "\n";
			$fid = fopen($filename, 'r');
				if (! $fid) echo "Error reading" . $filename . "\n";

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
				$linestring = trim(fgets($fid));

				// Check that the line contains one (and only one) NMEA message.
				// On rare occasions, the real-time data stream that created the
				// raw navigation file may be interrupted, resulting in a partial
				// NMEA message followed by a complete NMEA message on the same line.
				// We try to catch the last complete NMEA message on the line if it
				// appears there may be more than one, as indicated by multiple '$'.
				if (substr_count($linestring, '$') > 1) {
					$newline = strrchr($linestring, '$');
					$linestring = $newline;
				}
				if (substr_count($linestring, '$') == 1) {

					$line = preg_split("/[\s]+/", $linestring);

					$nmea->init($line[2]);

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
								$dateBufferLast->day	= $gpsBuffer[$linx]->gga->day;
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
							//	 or when ZDA date/time is reported late.)
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
							$dateBufferLast = new stdClass();
							$dateBufferLast->year   = $gpsBuffer[$linx]->gga->year;
							$dateBufferLast->month  = $gpsBuffer[$linx]->gga->month;
							$dateBufferLast->day	= $gpsBuffer[$linx]->gga->day;
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

			//	 echo "binx: " . $binx . "\n";
			// printf("%4d-%02d-%02dT%02d:%02d:%f\n", $zda->year, $zda->month, $zda->day,
			//		$zda->hh, $zda->mm, $zda->ss);

			//	 echo "hhmmss: " . $zda->hhmmss . " " . $gpsBuffer[1]->gga->hhmmss . "\n";

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
		break;

		// "nav25": seapath stamp, time stamp, nmea ZDA + GGA + VTG
		// Vessels: Healy
	case "nav25":

		//----------- Initialize variables: -----------//
		$maxBuffer = 86400;  // Max number of elements array can hold
		$gpsBuffer = array();

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

			//	  $line = trim( fgets($fin) );
			if ($line == "") break;
			$filename = $path . "/" . $line;
			//	 echo "Reading " . $filename . "\n";
			$fid = fopen($filename, 'r');
				if (! $fid) echo "Error reading" . $filename . "\n";

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
				$linestring = trim(fgets($fid));

				// Check that the line contains one (and only one) NMEA message.
				// On rare occasions, the real-time data stream that created the
				// raw navigation file may be interrupted, resulting in a partial
				// NMEA message followed by a complete NMEA message on the same line.
				// We try to catch the last complete NMEA message on the line if it
				// appears there may be more than one, as indicated by multiple '$'.
				if (substr_count($linestring, '$') > 1) {
					$newline = strrchr($linestring, '$');
					$linestring = $newline;
				}
				if (substr_count($linestring, '$') == 1) {

					$lines = preg_split('/\,\$/', $linestring);
					// preg_split removes leading '$' from NMEA string.  Put it back:
					$lines[1] = '$' . $lines[1];

					$nmea->init($lines[1]);

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
								$dateBufferLast->day	= $gpsBuffer[$linx]->gga->day;
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
							//	 or when ZDA date/time is reported late.)
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
							$dateBufferLast = new stdClass();
							$dateBufferLast->year   = $gpsBuffer[$linx]->gga->year;
							$dateBufferLast->month  = $gpsBuffer[$linx]->gga->month;
							$dateBufferLast->day	= $gpsBuffer[$linx]->gga->day;
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

			//	 echo "binx: " . $binx . "\n";
			// printf("%4d-%02d-%02dT%02d:%02d:%f\n", $zda->year, $zda->month, $zda->day,
			//		$zda->hh, $zda->mm, $zda->ss);

			//	 echo "hhmmss: " . $zda->hhmmss . " " . $gpsBuffer[1]->gga->hhmmss . "\n";

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
		break;


	// Parser for general csvs, just have to point to the correct columns
	// This was made to parse .elg files from Endeavor cruises where the nave went missing
    case "nav26":

        //----------- Initialize variables: -----------//
        $dateBufferLast = null;  // Initially unknown date.

        // Need to loop over all nav files in a cruise, in the order specified
        // by external control file.
        foreach ($navfilelist as $line) {

            if ($line == "") break;
            $filename = $path . "/" . $line;
            $fid = fopen($filename, 'r');

            $inx = 1;  // Record index
            //----------- Loop Over Contents of Single File ----------//
            while (!feof($fid)) {

                $line = trim(fgets($fid));

                if (($inx > 1)  && ($line != "")) {  // Skip over header record and any blank lines.

					$NavRec = preg_split("/\,/", $line);  // comma-separated values


					if (count($NavRec) > 15) {

						$dateRec = preg_split("/\-/", $NavRec[0]);  // values separated by dash "-"

						$month = $dateRec[0];
						$day = $dateRec[1];
						$year = $dateRec[2];

						$hour = substr($NavRec[11], 0, 2);
						$min = substr($NavRec[11], 2, 2);
						$sec = substr($NavRec[11], 4);


                            if (preg_match("/\./", $sec)) {
                                $roz = preg_split('/\./', $sec);
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

                            $lat = $NavRec[12];
                            $lon = $NavRec[13];
                            $qual = $NavRec[14];
                            $nsat = $NavRec[15];
                            $hdop = $NavRec[16];

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

                            $datestamp = sprintf( $time_format, $year, $month, $day, $hour, $min,
                                                  $sec);

                            $print_format = "%s\t" . $lon_format . "\t" . $lat_format . "\t" .
                                $qual_format . "\t%s\n";

                            fprintf(
                                $fout, $print_format,
                                $datestamp, $lon, $lat,
                                $qual, $nsat, $hdop, $alt
                            );


					} // end if not header record

				} // end if NavRec length is too short

                $inx++;

            } //end while (!feof($fid))

            fclose($fid);

        } // end foreach($navfilelist as $line)
        //------------ End Main Loop Over All Nav Files ------------//
        break; // end of case "nav26"



	// ZDA + GLL for Sproul nav where the GGA wasn't found
    case "nav27":

        //----------- Initialize variables: -----------//
        $maxBuffer = 86400;  // Max number of elements array can hold
        $gpsBuffer = array();
        $dateBufferLast = new stdClass();
        $nmea = new NMEA0183Message();
        $zda = new NMEA0183_ZDA();
        $gll = new NMEA0183_GLL();
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
            $filename = $path . "/" . $line;
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

                        // Do we have a GLL message?
                        if (preg_match('/^\$.{2}GLL$/', $NavRec[0])) {

                            // Save GPS fix to buffer:
                            //$gpsBuffer[$binx]->gga = clone $gga;
                            $gll->init($NavRec);
                            $gpsBuffer[$binx] = new stdClass();
                            $gpsBuffer[$binx]->gga = new NMEA0183_GGA();
                            $gpsBuffer[$binx]->gga->hhmmss = $gll->hhmmss;
                            $gpsBuffer[$binx]->gga->lat = $gll->lat;
                            $gpsBuffer[$binx]->gga->lon = $gll->lon;
                            $gpsBuffer[$binx]->gga->tim_nroz = $gll->tim_nroz;
                            $gpsBuffer[$binx]->gga->lat_nroz = $gll->lat_nroz;
                            $gpsBuffer[$binx]->gga->lon_nroz = $gll->lon_nroz;
                            $gpsBuffer[$binx]->gga->hour = $gll->hour;
                            $gpsBuffer[$binx]->gga->minute = $gll->minute;
                            $gpsBuffer[$binx]->gga->second = $gll->second;
                            $gpsBuffer[$binx]->gga->year = $datetimeLastKnown->year;
                            $gpsBuffer[$binx]->gga->month = $datetimeLastKnown->month;
                            $gpsBuffer[$binx]->gga->day = $datetimeLastKnown->day;
                            switch ($gll->status) {
                                case "A":
                                    $gpsBuffer[$binx]->gga->gpsQuality = 1;
                                    break;
                                case "V":
                                    $gpsBuffer[$binx]->gga->gpsQuality = 0;
                                    break;
                                default:
                                    $gpsBuffer[$binx]->gga->gpsQuality = "NAN";
                                    break;
                            }
                            $gpsBuffer[$binx]->gga->numberOfSatellites = "NAN";
                            $gpsBuffer[$binx]->gga->horizontalDilution = "NAN";
                            $gpsBuffer[$binx]->gga->antennaAltitude    = "NAN";

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
            //        $zda->hh, $zda->mm, $zda->ss);

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
        break; //end of case "nav27"

    case "nav29":

        include('parsers/nav29.php');

        parse_nav29($navfilelist, $path, $fout);

        break;


    case "nav31":

        // If GPS not logged separately, only recourse is to grab
        // it from UHDAS GPS feed.

        //----------- Initialize variables: -----------//
        $maxBuffer = 86400;  // Max number of elements array can hold
        $gpsBuffer = array();

        $nmea = new NMEA0183Message();
        $pyrtm = new UHDAS_PYRTM();
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
            $lineRec = preg_split("/[\s]+/", $line);
            $filename = $path . "/" . $lineRec[0];
            $fid = fopen($filename, 'r');

            //----------- Get Date ----------//
            $datetimeLastKnown->init($fid);
            if (is_null($datetimeLastKnown)) {
                echo "No ZDA nor PYRTM date stamp in file.\n";
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

                            // Process buffer if it is full.
                            if ($binx < $maxBuffer) {
                                // Still room in buffer--keep reading file.
                                $binx++;
                            } else {
                                // Buffer full--process it before continuing with file read.

                                // Check to make sure we have read a PYRTM message prior to filling
                                // the buffer:
                                if (is_null($pyrtm)) {
                                    echo "No PYRTM message found prior to end of GPS buffer.\n";
                                    echo "Maybe the buffer size is too small?\n";
                                    exit(1);
                                }

                                // Initialize first GGA day based on last PYRTM date/time stamp:
                                if ($gpsBuffer[1]->gga->hhmmss >= $pyrtm->hhmmss) {  // GGA same day as PYRTM
                                    $gpsBuffer[1]->gga->year  = $pyrtm->year;
                                    $gpsBuffer[1]->gga->month = $pyrtm->month;
                                    $gpsBuffer[1]->gga->day   = $pyrtm->day;
                                } else { // GGA belongs to next day
                                    // Convert date to unix time, add 1 day,
                                    // and convert back to date:
                                    $dateUnix = strtotime("+1 day", gmmktime(0, 0, 0, $pyrtm->month, $pyrtm->day, $pyrtm->year));
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

                            // Or do we have a PYRTM date/time stamp?
                        } else if (preg_match('/^\$PYRTM$/', $NavRec[0])) {

                            // echo "Found PYRTM.\n";
                            // Process NMEA message as a PYRTM date/time stamp:
                            $pyrtm->init($NavRec);

                            // When we encounter a PYRTM date/time stamp, we process the GPS buffer,
                            // starting from the beginning of the buffer (the earliest GGA records
                            // in the buffer):
                            // (1) Assign dates to GGA (tricky when day advances within buffer
                            //     or when PYRTM date/time is reported late.)
                            // (2) Print GPS buffer (all GGA messages dated)
                            // (3) Unset GPS buffer
                            $inx = 1;
                            $inxMax = count($gpsBuffer);
                            while ($inx <= $inxMax
                                && ($gpsBuffer[$inx]->gga->hhmmss <= $pyrtm->hhmmss)
                            ) {
                                // GGA same day as PYRTM
                                $gpsBuffer[$inx]->gga->year  = $pyrtm->year;
                                $gpsBuffer[$inx]->gga->month = $pyrtm->month;
                                $gpsBuffer[$inx]->gga->day   = $pyrtm->day;
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

                                        //		 echo "added day\n";

                                    }

                                } // end loop over remainder of buffer

                            } else { // GGA belongs to previous day

                                $jnxMax = count($gpsBuffer);
                                for ($jnx=$jnxMax; $jnx>=1; $jnx--) {

                                    if ($gpsBuffer[$jnx]->gga->hhmmss <= $pyrtm->hhmmss) { // GGA same day as PYRTM
                                        $gpsBuffer[$jnx]->gga->year  = $pyrtm->year;
                                        $gpsBuffer[$jnx]->gga->month = $pyrtm->month;
                                        $gpsBuffer[$jnx]->gga->day   = $pyrtm->day;
                                    } else {

                                        // Convert date to unix time, subtract 1 day,
                                        // and convert back to date:
                                        $dateUnix = strtotime("-1 day", gmmktime(0, 0, 0, $pyrtm->month, $pyrtm->day, $pyrtm->year));
                                        $dateString = gmdate("Y-m-d", $dateUnix);
                                        $dateArray = preg_split("/\-/", $dateString);

                                        $gpsBuffer[$jnx]->gga->year  = $dateArray[0];
                                        $gpsBuffer[$jnx]->gga->month = $dateArray[1];
                                        $gpsBuffer[$jnx]->gga->day   = $dateArray[2];

                                        //		 echo $gpsBuffer[$jnx]->gga->hhmmss," ", $pyrtm->hhmmss,"\n";
                                        //echo "Subtracted day\n";

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

                } // end if one and only one NMEA message line

            } // end while (!feof($fid))
            //------------ End Loop Over Contents of Single File ----------//

            fclose($fid);

        } // end foreach($navfilelist as $line)
        //------------ End Main Loop Over All Nav Files ------------//

        //--------- Might have unprocessed buffer at end of last file read -------//
        if (isset($gpsBuffer) && count($gpsBuffer) > 0) {

            //     echo "binx: " . $binx . "\n";
            // printf("%4d-%02d-%02dT%02d:%02d:%f\n", $pyrtm->year, $pyrtm->month, $pyrtm->day,
            //    $pyrtm->hh, $pyrtm->mm, $pyrtm->ss);

            //     echo "hhmmss: " . $pyrtm->hhmmss . " " . $gpsBuffer[1]->gga->hhmmss . "\n";

            // Check to make sure we have read a PYRTM message prior to filling
            // the buffer:
            if (is_null($pyrtm)) {
                echo "No PYRTM message found prior to end of GPS buffer.\n";
                echo "Maybe the buffer size is too small?\n";
                exit(1);
            }

            // Initialize first GGA day based on last PYRTM date/time stamp:
            // This fails if GGA is before midnight and PYRTM is after midnight.
            if ($gpsBuffer[1]->gga->hhmmss >= $pyrtm->hhmmss) {  // GGA same day as PYRTM
                $gpsBuffer[1]->gga->year  = $pyrtm->year;
                $gpsBuffer[1]->gga->month = $pyrtm->month;
                $gpsBuffer[1]->gga->day   = $pyrtm->day;
            } else {
                if (($gpsBuffer[1]->gga->hhmmss - $dateBufferLast->hhmmss) >= 0) {
                    // GGA is same day as end of previous buffer:
                    $gpsBuffer[1]->gga->year  = $dateBufferLast->year;
                    $gpsBuffer[1]->gga->month = $dateBufferLast->month;
                    $gpsBuffer[1]->gga->day   = $dateBufferLast->day;
                } else { // GGA belongs to next day
                    // Convert date to unix time, add 1 day,
                    // and convert back to date:
                    $dateUnix = strtotime("+1 day", gmmktime(0, 0, 0, $pyrtm->month, $pyrtm->day, $pyrtm->year));
                    $dateString = gmdate("Y-m-d", $dateUnix);
                    $dateArray = preg_split("/\-/", $dateString);

                    $gpsBuffer[1]->gga->year  = $dateArray[0];
                    $gpsBuffer[1]->gga->month = $dateArray[1];
                    $gpsBuffer[1]->gga->day   = $dateArray[2];
                    //	   echo $gpsBuffer[1]->gga->hhmmss," ", $pyrtm->hhmmss,"\n";
                    // echo "added day\n";
                } // end if same day as end of previous buffer

            }  // end if same day as current PYRTM

            for ($inx=1; $inx<$binx; $inx++) {

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
        break;

    case "uhdas":

        // If GPS not logged separately, only recourse is to grab
        // it from UHDAS GPS feed.

        //----------- Initialize variables: -----------//
        $maxBuffer = 86400;  // Max number of elements array can hold
        $gpsBuffer = array();

        $nmea = new NMEA0183Message();
        $unixd = new NMEA0183_UNIXD();
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
            $lineRec = preg_split("/[\s]+/", $line);
            $filename = $path . "/" . $lineRec[0];
            $fid = fopen($filename, 'r');

            //----------- Get Date ----------//
            $datetimeLastKnown->init($fid);
            if (is_null($datetimeLastKnown)) {
                echo "No ZDA nor UNIXD date stamp in file.\n";
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
                            $gpsBuffer[$binx]->gga = new NMEA0183_GGA();
                            $gpsBuffer[$binx]->gga->init($NavRec);

                            // Process buffer if it is full.
                            if ($binx < $maxBuffer) {
                                // Still room in buffer--keep reading file.
                                $binx++;
                            } else {
                                // Buffer full--process it before continuing with file read.

                                // Check to make sure we have read a UNIXD message prior to filling
                                // the buffer:
                                if (is_null($unixd)) {
                                    echo "No UNIXD message found prior to end of GPS buffer.\n";
                                    echo "Maybe the buffer size is too small?\n";
                                    exit(1);
                                }

                                // Initialize first GGA day based on last UNIXD date/time stamp:
                                if ($gpsBuffer[1]->gga->hhmmss >= $unixd->hhmmss) {  // GGA same day as UNIXD
                                    $gpsBuffer[1]->gga->year  = $unixd->year;
                                    $gpsBuffer[1]->gga->month = $unixd->month;
                                    $gpsBuffer[1]->gga->day   = $unixd->day;
                                } else { // GGA belongs to next day
                                    // Convert date to unix time, add 1 day,
                                    // and convert back to date:
                                    $dateUnix = strtotime("+1 day", gmmktime(0, 0, 0, $unixd->month, $unixd->day, $unixd->year));
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

                            // Or do we have a UNIXD date/time stamp?
                        } else if (preg_match('/^\$UNIXD$/', $NavRec[0])) {

                            // echo "Found UNIXD.\n";
                            // Process NMEA message as a UNIXD date/time stamp:
                            $unixd->init($baseyear, $NavRec);

                            // When we encounter a UNIXD date/time stamp, we process the GPS buffer,
                            // starting from the beginning of the buffer (the earliest GGA records
                            // in the buffer):
                            // (1) Assign dates to GGA (tricky when day advances within buffer
                            //     or when UNIXD date/time is reported late.)
                            // (2) Print GPS buffer (all GGA messages dated)
                            // (3) Unset GPS buffer
                            $inx = 1;
                            $inxMax = count($gpsBuffer);
                            while ($inx <= $inxMax
                                && ($gpsBuffer[$inx]->gga->hhmmss <= $unixd->hhmmss)
                            ) {
                                // GGA same day as UNIXD
                                $gpsBuffer[$inx]->gga->year  = $unixd->year;
                                $gpsBuffer[$inx]->gga->month = $unixd->month;
                                $gpsBuffer[$inx]->gga->day   = $unixd->day;
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

                                        //		 echo "added day\n";

                                    }

                                } // end loop over remainder of buffer

                            } else { // GGA belongs to previous day

                                $jnxMax = count($gpsBuffer);
                                for ($jnx=$jnxMax; $jnx>=1; $jnx--) {

                                    if ($gpsBuffer[$jnx]->gga->hhmmss <= $unixd->hhmmss) { // GGA same day as UNIXD
                                        $gpsBuffer[$jnx]->gga->year  = $unixd->year;
                                        $gpsBuffer[$jnx]->gga->month = $unixd->month;
                                        $gpsBuffer[$jnx]->gga->day   = $unixd->day;
                                    } else {

                                        // Convert date to unix time, subtract 1 day,
                                        // and convert back to date:
                                        $dateUnix = strtotime("-1 day", gmmktime(0, 0, 0, $unixd->month, $unixd->day, $unixd->year));
                                        $dateString = gmdate("Y-m-d", $dateUnix);
                                        $dateArray = preg_split("/\-/", $dateString);

                                        $gpsBuffer[$jnx]->gga->year  = $dateArray[0];
                                        $gpsBuffer[$jnx]->gga->month = $dateArray[1];
                                        $gpsBuffer[$jnx]->gga->day   = $dateArray[2];

                                        //		 echo $gpsBuffer[$jnx]->gga->hhmmss," ", $unixd->hhmmss,"\n";
                                        //echo "Subtracted day\n";

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

                } // end if one and only one NMEA message line

            } // end while (!feof($fid))
            //------------ End Loop Over Contents of Single File ----------//

            fclose($fid);

        } // end foreach($navfilelist as $line)
        //------------ End Main Loop Over All Nav Files ------------//

        //--------- Might have unprocessed buffer at end of last file read -------//
        if (isset($gpsBuffer) && count($gpsBuffer) > 0) {

            //     echo "binx: " . $binx . "\n";
            // printf("%4d-%02d-%02dT%02d:%02d:%f\n", $unixd->year, $unixd->month, $unixd->day,
            //    $unixd->hh, $unixd->mm, $unixd->ss);

            //     echo "hhmmss: " . $unixd->hhmmss . " " . $gpsBuffer[1]->gga->hhmmss . "\n";

            // Check to make sure we have read a UNIXD message prior to filling
            // the buffer:
            if (is_null($unixd)) {
                echo "No UNIXD message found prior to end of GPS buffer.\n";
                echo "Maybe the buffer size is too small?\n";
                exit(1);
            }

            // Initialize first GGA day based on last UNIXD date/time stamp:
            // This fails if GGA is before midnight and UNIXD is after midnight.
            if ($gpsBuffer[1]->gga->hhmmss >= $unixd->hhmmss) {  // GGA same day as UNIXD
                $gpsBuffer[1]->gga->year  = $unixd->year;
                $gpsBuffer[1]->gga->month = $unixd->month;
                $gpsBuffer[1]->gga->day   = $unixd->day;
            } else {
                if (($gpsBuffer[1]->gga->hhmmss - $dateBufferLast->hhmmss) >= 0) {
                    // GGA is same day as end of previous buffer:
                    $gpsBuffer[1]->gga->year  = $dateBufferLast->year;
                    $gpsBuffer[1]->gga->month = $dateBufferLast->month;
                    $gpsBuffer[1]->gga->day   = $dateBufferLast->day;
                } else { // GGA belongs to next day
                    // Convert date to unix time, add 1 day,
                    // and convert back to date:
                    $dateUnix = strtotime("+1 day", gmmktime(0, 0, 0, $unixd->month, $unixd->day, $unixd->year));
                    $dateString = gmdate("Y-m-d", $dateUnix);
                    $dateArray = preg_split("/\-/", $dateString);

                    $gpsBuffer[1]->gga->year  = $dateArray[0];
                    $gpsBuffer[1]->gga->month = $dateArray[1];
                    $gpsBuffer[1]->gga->day   = $dateArray[2];
                    //	   echo $gpsBuffer[1]->gga->hhmmss," ", $unixd->hhmmss,"\n";
                    // echo "added day\n";
                } // end if same day as end of previous buffer

            }  // end if same day as current UNIXD

            for ($inx=1; $inx<$binx; $inx++) {

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
        break;

    // "nav33": Why is the code inline here for all the other parsers?? Should be in parsers/navxx.php
    // Vessels: FKt
    case "nav33":
        include_once('parsers/nav33.php');
        print " Running with nav33 format\n";
        parse_nav33($navfilelist, $path, $fout);
        break;

    case "siomet":

        $lat_col_num = 0;
        $lon_col_num = 0;
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
                //if ($line=="") break;

                if (preg_match("/^\#/", $line)) {  // Read date from header record:

                    if ($recnum == 2) { // Grab date from second line of header record:

                        $dateRec = preg_split("/\s+/", trim($line, "# "));

                        $dateStr = strtotime($dateRec[1]);

                        $year  = date('Y', $dateStr);
                        $month = date('m', $dateStr);
                        $day   = date('d', $dateStr);

                    } elseif ($recnum == 4) { // Parameter names in line 4 - find lat and lon

                        $parameters = preg_split("/\s+/", trim($line, "#"));

                        $parameter_count = count($parameters);

                        foreach ($parameters as $index => $parameter) {

                            if ($parameter == 'LA') {
                                $lat_col_num = $index;
                            } elseif ($parameter == 'LO') {
                                $lon_col_num = $index;
                            }
                        }
                    }

                    $recnum++;

                } else { // Read data record:

                    if ($line != "") {

                        $MetRec = preg_split("/\s+/", $line);  // whitespace-separated values

                        if (count($MetRec) != $parameter_count) {
                            continue;
                        }

                        $hhmmss = trim($MetRec[0]);

                        // Decode the time and the time precision:
                        $hour   = intval($hhmmss/1e4);
                        $minute = intval(($hhmmss - ($hour*1e4))/1e2);
                        $second = $hhmmss - ($hour*1e4) - ($minute*1e2);

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

                        // Decode the latitude and its precision:
                        $lat = $MetRec[$lat_col_num];

                        if (preg_match('/\./', $MetRec[$lat_col_num])) {
                            $roz = preg_split('/\./', $MetRec[$lat_col_num]);
                            $lat_nroz = strlen($roz[1]);
                        } else {
                            $lat_nroz = 0;
                        }
                        $lat_format = "%." . ($lat_nroz + 2) . "f";

                        // Decode the longitude and its precision:
                        $lon = $MetRec[$lon_col_num];

                        if (preg_match('/\./', $MetRec[$lon_col_num])) {
                            $roz = preg_split('/\./', $MetRec[$lon_col_num]);
                            $lon_nroz = strlen($roz[1]);
                        } else {
                            $lon_nroz = 0;
                        }
                        $lon_format = "%." . ($lon_nroz + 2) . "f";

                        $datestamp = sprintf($time_format, $year, $month, $day, $hour, $minute, $second);

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

                    } // end if data record not empty

                } // end if data or header record

            } //end while (!feof($fid))

            fclose($fid);

        } // end foreach($navfilelist as $line)
        break;

    case "nav30":
    case "nav32":

        include('parsers/nav32.php');

        parse_nav32($navfilelist, $path, $fout);

        break;

    case "metoc_nav":

        include('parsers/metoc_nav.php');

        parse_metoc_nav($navfilelist, $path, $fout);

        break;


    default:
        echo "navcopy(): Unsupported input file format.\n";
        exit(1);
        break;

    } // end switch ($inputFormatSpec)

    fclose($fout);

    // Successful execution:
    return true;

} // end function navcopy()
?>
