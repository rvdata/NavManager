<?php
/**
 * Define function to create a time-ordered list of navigation rawdata 
 * files that fall between the specified start and end datetimes.
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
require_once 'nmeatools.inc.php';

/**
 * Return the last line of a file.
 * 
 * @param resource &$filePointer Pointer to file
 * @param string   $eolString    Character string denoting end-of-line 
 *                                (e.g. "\n", "\r\n", etc.)
 *                               Notes: Handles up to two characters 
 *                                as end-of-line string.
 *
 * @return string Returns last line in file
 */
function lastLine(&$filePointer, $eolString) 
{  
    $oldchar = '';
    $line = '';
    // Read file backwards and return last line.
    for ($x_pos=0; fseek($filePointer, $x_pos, SEEK_END) !== -1; $x_pos--) {
        
        $char = fgetc($filePointer);
        
        if (strlen($eolString) == 2) {
            $ending = $char . $oldchar;
        } else {
            $ending = $char;
        }
        
        if ($ending === $eolString) {
            
            if (strlen($line) > strlen($eolString)) {
                //	if (strlen($line) > 1) {
                
                return $line;
                
                // Reset $line:
                $line = $char;
                
            } // end if ($line != '')
            
        } // end if ($ending === $eolString)
        
        // Add character to line:
        $line = $char . $line;
        $oldchar = $char;
        
    } // end for(): read file backwards line-by-line
    
}  // end function lastLine()

/**
 * Function to return the first non commented line of file
 *
 * @param resource	&4filePointer	Pointer to file
 * @param string	$header		Characters indicating header lines
 *								with regex characters escaped (use preg_quote())
 *								ie \/\/ insead of //
 *
 * @return	string	Returns first non-header line in file
 */
function firstLine(&$filePointer, $header) {
    $line = fgets($filePointer);
    if(preg_match('/^' . $header . '/', $line)) {
        return firstLine($filePointer, $header);
    } else {
        rewind($filePointer);
        return $line;
    }   
}


/**
 * Returns a list of navigation rawdata files and a report
 *
 * Returns a list of navigation rawdata files that fall between the specified 
 * start and end datetimes.
 * The report lists (1) Gaps between parseable files that last longer than 
 * 12 hours, (2) Files that could not be parsed, and/or (3) Files that could be 
 * parsed, but do not fall within the cruise start/end dates.
 *
 * @param string $inputFormatSpec    R2R fileformat short name (e.g. "nav1")
 * @param string $dateStringUTCStart Start datetime (RFC-2524)
 * @param string $dateStringUTCEnd   End datetime (RFC-2524)
 * @param string $path               Path to navigation raw data directory
 *
 * @return array Returns an array of filenames and a report array.
 */
function navdatalist(
    $inputFormatSpec, $dateStringUTCStart, $dateStringUTCEnd, $path
) {
    // Initialize navigation raw data filelist:
    $navfilelist = null;
    
    // Initialize report:
    $feedback = null;
   
    if (strtotime($dateStringUTCStart) >= strtotime($dateStringUTCEnd)) {
        echo "navdatalist(): Start date/time must be earlier than end date/time.\n";
        echo "Start: $dateStringUTCStart\n";
        echo "End:   $dateStringUTCEnd\n";
        exit(1);
    }
    
    if ($handle = opendir("$path")) {
        
        switch ($inputFormatSpec) {
            
            // "nav1": raw NMEA: GGA + ZDA
            // Vessels: Melville, Roger Revelle
        case "nav1":   
   		    // "nav24": raw NMEA: GGA + ZDA
	 	    // Vessels: Sikuliaq           
	 	case "nav24":
	   	    // "nav25": raw NMEA: GGA + ZDA
	 	    // Vessels: Sikuliaq           
	 	case "nav25":
	   	    // "nav27": raw NMEA: GLL + ZDA
	 	    // Vessels: Sproul           
	 	case "nav27":
	   	    // "nav29": raw NMEA: GLL + ZDA
	 	    // Vessels: Sproul, Revelle, Sally Ride, Healy (STARC)
	 	case "nav29":
            // "nav20": external clock + tags + raw NMEA: GGA + ZDA
            // Vessels: Knorr (2013+)
        case "nav20":
            $date_format = "%4d-%02d-%02dT%02d:%02d:%02dZ";
            $dateObjectUTCStartFile = new DateTimeSimple();
            $dateObjectUTCEndFile = new DateTimeSimple();
            
            // Initialize the row index for the table of nav files.  The table will 
            // contain the start and end times of the data within each file and the 
            // filename.  The table will be sorted from earliest to latest start 
            // times.
            $inx = 0;
            $jnx = 0;
            while (false !== ($file = readdir($handle))) {
                
                if ($file != "." && $file != "..") {
                    
                    $filename = $file;
                    
                    // Check for presence of ZDA message:
                    $cmd_str_zda = "( grep \"ZDA\" $path/$filename | head -1 )" 
                        . " 2> /dev/null";
                    exec($cmd_str_zda, $resultZDA, $ret_status);
                    
                    // Check for presence of RMC message:
                    $cmd_str_zda = "( grep \"RMC\" $path/$filename | head -1 )"
                        . " 2> /dev/null";
                    exec($cmd_str_zda, $resultRMC, $ret_status);
                    
                    if (!empty($resultZDA[0]) || !empty($resultRMC[0])) {
                        
                        unset($resultZDA);
                        unset($resultRMC);
                        
                        $fid = fopen($path . "/" . $filename, 'r'); 
                        //	  echo $path,"/", $filename,"\n";
                        if (is_null($fid)) {
                            echo "navdatalist(): Could not open file: ", $path, "/", 
                                $filename, "\n";
                            exit(1);
                        }
                        
                        //----------- Get Start Datetime ----------//
                        $dateObjectUTCStartFile->init($fid);
                        
                        rewind($fid);	    
                        $year = $dateObjectUTCStartFile->year;
                        $month = $dateObjectUTCStartFile->month;
                        $day = $dateObjectUTCStartFile->day;
                        $hour = $dateObjectUTCStartFile->hh;
                        $minute = $dateObjectUTCStartFile->mm;
                        $second = $dateObjectUTCStartFile->ss;
                        $dateStringUTCStartFile = sprintf(
                            $date_format, $year, $month, $day, 
                            $hour, $minute, $second
                        );
                        
                        //----------- Get End Datetime ----------//
                        $dateObjectUTCEndFile->last($fid);
                        
                        fclose($fid);
                        $year = $dateObjectUTCEndFile->year;
                        $month = $dateObjectUTCEndFile->month;
                        $day = $dateObjectUTCEndFile->day;
                        $hour = $dateObjectUTCEndFile->hh;
                        $minute = $dateObjectUTCEndFile->mm;
                        $second = $dateObjectUTCEndFile->ss;
                        $dateStringUTCEndFile = sprintf(
                            $date_format, $year, $month, $day, 
                            $hour, $minute, $second
                        );
                        
                        // Check that the start/end date/times in each file overlap 
                        // the start/end date/times entered on the command line:
                        if (!( (strtotime($dateStringUTCEndFile) < strtotime($dateStringUTCStart) ) 
                            || (strtotime($dateStringUTCStartFile) > strtotime($dateStringUTCEnd) ) ) 
                        ) {
                            
                            $table[$inx]["start"] 
                                = strtotime($dateStringUTCStartFile);
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
                        
                    } else {
                        
                        $otherNonParseableFiles[] = $filename;
                        
                    } // end if ZDA message found
                    
                } // if file is not "." nor ".."
                
            } // end loop over files in dir
            break;
            
            // "nav2: DAS: NOAA Shipboad Computer System (SCS): external clock + GGA
            // Vessels: Atlantic Explorer, Clifford A. Barnes, Cape Hatteras, 
            //           Endeavor, Savannah
        case "nav2":
            // "nav3": DAS: NOAA SCS - Partial GLL + occasional GGA
            // Vessels: Blue Heron
        case "nav3":
            
            // Initialize the row index for the table of nav files.  The table will 
            // contain the start and end times of the data within each file and the 
            // filename.  The table will be sorted from earliest to latest start 
            // times.
            $inx = 0;
            $jnx = 0;
            while (false !== ($file = readdir($handle))) {
                
                if ($file != "." && $file != "..") {
                    
                    $filename = $file;
                    $fid = fopen($path . "/" . $filename, 'r');
                    
                    if (has_GGA($fid)) {
                        
                        //----------- Loop Over Contents of Single File ----------//
                        while (!feof($fid)) {
                            
                            // Get message:
                            $record = fgets($fid);

                            // Check for Windows EOL
                            if (preg_match("/\r\n/", $record)) {
                                $eol = "\r\n";
                                //		echo "Windows line endings...\n";
                            } else {
                                $eol = "\n";
                                //		echo "Unix line endings...\n";
                            }
                            $line = trim($record);
                            //	    if ($line=="") break;
                            
                            // comma-separated values
                            $NavRec = preg_split("/\,/", $line);
                            
                            // values separated by slash "/"
                            $dateRec = preg_split("/\//", $NavRec[0]);
                            // values separated by colon ":"
                            $timeRec = preg_split("/\:/", $NavRec[1]);
                            
                            // Decode the date and time and the time precision:
                            $month = $dateRec[0];
                            $day   = $dateRec[1];
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
                            
                            // Print exactly the same precision time stamp 
                            // as in the recorded data.
                            if ($tim_nroz == 0) {
                                $time_format = "%4d-%02d-%02dT%02d:%02d:%02dZ";
                            } else {
                                $time_format = "%4d-%02d-%02dT%02d:%02d:%0" 
                                    . ($tim_nroz + 3) . "." . $tim_nroz . "fZ";
                            }
                            
                            $dateStringUTCStartFile = sprintf(
                                $time_format, $year, $month, $day, 
                                $hour, $minute, $second
                            );
                            break;
                            
                        } // end loop over file
                        
                        // Grab last complete data record from file:
                        rewind($fid);
                        $line = trim(lastLine($fid, $eol));
                        //	    echo $filename,": ", $line,"\n";
                        fclose($fid);
                        
                        if (preg_match("/\,/", $line)) {
                            
                            $NavRec = preg_split("/\,/", $line);

                            // values separated by slash "/"
                            $dateRec = preg_split("/\//", $NavRec[0]);
                            // values separated by colon ":"
                            $timeRec = preg_split("/\:/", $NavRec[1]);
                            
                            // Decode the date and time and the time precision:
                            $month = $dateRec[0];
                            $day   = $dateRec[1];
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
                            
                            // Print exactly the same precision time stamp 
                            // as in the recorded data.
                            if ($tim_nroz == 0) {
                                $time_format = "%4d-%02d-%02dT%02d:%02d:%02dZ";
                            } else {
                                $time_format = "%4d-%02d-%02dT%02d:%02d:%0" 
                                    . ($tim_nroz + 3) . "." . $tim_nroz . "fZ";
                            }
                            
                            $dateStringUTCEndFile = sprintf(
                                $time_format, $year, $month, $day, 
                                $hour, $minute, $second
                            );
                            
                        } else {
                            
                            $dateStringUTCEndFile = $dateStringUTCStartFile;
                            
                        }
                        
                        // Check that the start/end date/times in each file overlap 
                        // the start/end date/times entered on the command line:
                        if (!( (strtotime($dateStringUTCEndFile) < strtotime($dateStringUTCStart) ) 
                            || (strtotime($dateStringUTCStartFile) > strtotime($dateStringUTCEnd) ) ) 
                        ) {
                            
                            $table[$inx]["start"] 
                                = strtotime($dateStringUTCStartFile);
                            $table[$inx]["end"] = strtotime($dateStringUTCEndFile);
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
                        
                    } // end if (has_GGA($fid))
                    
                } // if file is not "." nor ".."
                
            } // end loop over files in dir
            break;
            

            // "nav4": DAS: WHOI Calliope
            // Vessels: Atlantis, Knorr
        case "nav4":
            // "nav5": DAS: WHOI Calliope (2007)
            // Vessels: Oceanus
        case "nav5":
            // "nav17": DAS: WHOI Calliope (2010, 2011)
            // Vessels: Oceanus
        case "nav17":
            // "nav18": DAS: WHOI Calliope (2009)
            // Vessels: Oceanus
        case "nav18":
            
            $date_format = "%4d-%02d-%02dT%02d:%02d:%02dZ";
            $dateObjectUTCStartFile = new DateTimeSimple();
            $dateObjectUTCEndFile = new DateTimeSimple();
            
            // Initialize the row index for the table of nav files.  The table will 
            // contain the start and end times of the data within each file and the 
            // filename.  The table will be sorted from earliest to latest start 
            // times.
            $inx = 0;
            $jnx = 0;
            while (false !== ($file = readdir($handle))) {
                
                if ($file != "." && $file != "..") {
                    
                    $filename = $file;
                    
                    $dateStringUTCStartFile = null;
                    $dateStringUTCEndFile = null;
                    
                    // Check for presence of ZDA message:
                    $cmd_str_zda = "( grep \"ZDA\" $path/$filename | head -1 )" 
                        . " 2> /dev/null";
                    exec($cmd_str_zda, $resultZDA, $ret_status);

                    // Check for presence of RMC message:
                    $cmd_str_zda = "( grep \"RMC\" $path/$filename | head -1 )"
                        . " 2> /dev/null";
                    exec($cmd_str_zda, $resultRMC, $ret_status);
                    
                    if (!empty($resultZDA[0]) || !empty($resultRMC[0])) {
                        
                        unset($resultZDA);
                        unset($resultRMC);
                        
                        $fid = fopen($path . "/" . $filename, 'r'); 
                        //	  echo $path,"/", $filename,"\n";
                        if (is_null($fid)) {
                            echo "navdatalist(): Could not open file: ", 
                                $path, "/", $filename, "\n";
                            exit(1);
                        }
                        
                        //----------- Get Start Datetime ----------//
                        $dateObjectUTCStartFile->init($fid);
                        
                        rewind($fid);	    
                        $year = $dateObjectUTCStartFile->year;
                        $month = $dateObjectUTCStartFile->month;
                        $day = $dateObjectUTCStartFile->day;
                        $hour = $dateObjectUTCStartFile->hh;
                        $minute = $dateObjectUTCStartFile->mm;
                        $second = $dateObjectUTCStartFile->ss;
                        $dateStringUTCStartFile = sprintf(
                            $date_format, $year, $month, $day, 
                            $hour, $minute, $second
                        );
                        
                        //----------- Get End Datetime ----------//
                        $dateObjectUTCEndFile->last($fid);
                        
                        fclose($fid);
                        $year = $dateObjectUTCEndFile->year;
                        $month = $dateObjectUTCEndFile->month;
                        $day = $dateObjectUTCEndFile->day;
                        $hour = $dateObjectUTCEndFile->hh;
                        $minute = $dateObjectUTCEndFile->mm;
                        $second = $dateObjectUTCEndFile->ss;
                        $dateStringUTCEndFile = sprintf(
                            $date_format, $year, $month, $day, 
                            $hour, $minute, $second
                        );
                        
                        //echo "$filename: $dateStringUTCStartFile " 
                        //  . "$dateStringUTCEndFile\n";
                        
                        // Check that the start/end date/times in each file overlap
                        // the start/end date/times entered on the command line:
                        if (!( (strtotime($dateStringUTCEndFile) < strtotime($dateStringUTCStart) ) 
                            || (strtotime($dateStringUTCStartFile) > strtotime($dateStringUTCEnd) ) ) 
                        ) {
                            
                            $table[$inx]["start"] 
                                = strtotime($dateStringUTCStartFile);
                            $table[$inx]["end"] = strtotime($dateStringUTCEndFile);
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
                        
                    }  // end if ZDA or RMC messages
                    
                } // if file is not "." nor ".."
                
            } // end loop over files in dir
            break;
            
            // "nav12": DAS: device_id + external clock + GGA and ZDA 
            // Vessels: Healy, Marcus G. Langseth
        case "nav12":
            
            $date_format = "%4d-%02d-%02dT%02d:%02d:%02dZ";
            $dateObjectUTCStartFile = new DateTimeSimple();
            $dateObjectUTCEndFile = new DateTimeSimple();
            
            // Initialize the row index for the table of nav files.  The table will 
            // contain the start and end times of the data within each file and the 
            // filename.  The table will be sorted from earliest to latest start 
            // times.
            $inx = 0;
            $jnx = 0;
            while (false !== ($file = readdir($handle))) {
                
                if ($file != "." && $file != "..") {
                    
                    $filename = $file;
                    
                    $dateStringUTCStartFile = null;
                    $dateStringUTCEndFile = null;
                    
                    // Check for presence of ZDA message:
                    $cmd_str_zda = "( grep \"ZDA\" $path/$filename | head -1 )"
                        . " 2> /dev/null";
                    exec($cmd_str_zda, $resultZDA, $ret_status);
                    
                    // Check for presence of RMC message:
                    $cmd_str_zda = "( grep \"RMC\" $path/$filename | head -1 )"
                        . " 2> /dev/null";
                    exec($cmd_str_zda, $resultRMC, $ret_status);
                    
                    if (!empty($resultZDA[0]) || !empty($resultRMC[0])) {
                        
                        unset($resultZDA);
                        unset($resultRMC);
                        
                        $fid = fopen($path . "/" . $filename, 'r'); 
                        //	  echo $path,"/", $filename,"\n";
                        if (is_null($fid)) {
                            echo "navdatalist(): Could not open file: ", 
                                $path, "/", $filename, "\n";
                            exit(1);
                        }
                        
                        //----------- Get Start Datetime ----------//
                        $dateObjectUTCStartFile->init($fid);
                        
                        rewind($fid);	    
                        $year = $dateObjectUTCStartFile->year;
                        $month = $dateObjectUTCStartFile->month;
                        $day = $dateObjectUTCStartFile->day;
                        $hour = $dateObjectUTCStartFile->hh;
                        $minute = $dateObjectUTCStartFile->mm;
                        $second = $dateObjectUTCStartFile->ss;
                        $dateStringUTCStartFile = sprintf(
                            $date_format, $year, $month, $day, 
                            $hour, $minute, $second
                        );
                        
                        //----------- Get End Datetime ----------//
                        $dateObjectUTCEndFile->last($fid);
                        
                        fclose($fid);
                        $year = $dateObjectUTCEndFile->year;
                        $month = $dateObjectUTCEndFile->month;
                        $day = $dateObjectUTCEndFile->day;
                        $hour = $dateObjectUTCEndFile->hh;
                        $minute = $dateObjectUTCEndFile->mm;
                        $second = $dateObjectUTCEndFile->ss;
                        $dateStringUTCEndFile = sprintf(
                            $date_format, $year, $month, $day, 
                            $hour, $minute, $second
                        );
                        
                        //echo "$filename: $dateStringUTCStartFile "
                        //  . "$dateStringUTCEndFile\n";
                        
                        // Check that the start/end date/times in each file overlap
                        // the start/end date/times entered on the command line:
                        if (!( (strtotime($dateStringUTCEndFile) < strtotime($dateStringUTCStart) ) 
                            || (strtotime($dateStringUTCStartFile) > strtotime($dateStringUTCEnd) ) ) 
                        ) {
                            
                            $table[$inx]["start"]
                                = strtotime($dateStringUTCStartFile);
                            $table[$inx]["end"] = strtotime($dateStringUTCEndFile);
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
                        
                    } else {
                        
                        // Added in case ZDA messages were not recorded 
                        // but external timestamp exists:
                        
                        $fid = fopen($path . "/" . $filename, 'r'); 
                        //	  echo $path,"/", $filename,"\n";
                        if (is_null($fid)) {
                            echo "navdatalist(): Could not open file: ", 
                                $path, "/", $filename, "\n";
                            exit(1);
                        }
                        
                        //----------- Loop Over Contents of Single File ----------//
                        while (!feof($fid)) {
                            
                            // Get message:
                            $line = trim(fgets($fid));
                            //	    if ($line=="") break;
                            
                            // whitespace-separated values
                            $NavRec = preg_split("/\s+/", $line);

                            // values separated by colon ":"
                            $datetimeRec = preg_split("/\:/", $NavRec[1]);
                            
                            // Decode the date and time and the time precision:
                            $year  = $datetimeRec[0];
                            $doy   = $datetimeRec[1];
                            
                            // Convert DOY to Month and Day:
                            $result = doy2mmdd($year, $doy);
                            $month = $result["month"];
                            $day = $result["day"];
                            
                            $hour   = $datetimeRec[2];
                            $minute = $datetimeRec[3];
                            $second = $datetimeRec[4];
                            
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
                            
                            $dateStringUTCStartFile = sprintf(
                                $time_format, $year, $month, $day, 
                                $hour, $minute, $second
                            );
                            break;
                            
                        } // end loop over file
                        
                        // Grab last complete data record from file:
                        rewind($fid);
                        $line = trim(lastLine($fid, "\n"));
                        fclose($fid);

                        // whitespace-separated values
                        $NavRec = preg_split("/\s+/", $line);
                        // values separated by colon ":"
                        $datetimeRec = preg_split("/\:/", $NavRec[1]);

                        // Decode the date and time and the time precision:
                        $year  = $datetimeRec[0];
                        $doy   = $datetimeRec[1];
                        
                        // Convert DOY to Month and Day:
                        $result = doy2mmdd($year, $doy);
                        $month = $result["month"];
                        $day = $result["day"];
                        
                        $hour   = $datetimeRec[2];
                        $minute = $datetimeRec[3];
                        $second = $datetimeRec[4];
                        
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
                        
                        $dateStringUTCEndFile = sprintf(
                            $time_format, $year, $month, $day, 
                            $hour, $minute, $second
                        );
                        
                        // Check that the start/end date/times in each file overlap
                        // the start/end date/times entered on the command line:
                        if (!( (strtotime($dateStringUTCEndFile) < strtotime($dateStringUTCStart) ) 
                            ||  (strtotime($dateStringUTCStartFile) > strtotime($dateStringUTCEnd) ) ) 
                        ) {
                            
                            $table[$inx]["start"]
                                = strtotime($dateStringUTCStartFile);
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
                        
                    }  // end if ZDA or RMC messages
                    
                } // if file is not "." nor ".."
                
            } // end loop over files in dir
            break;
            
            // "nav6": DAS: UDel Surface Mapping System (SMS)
            // Vessels: Hugh R. Sharp
        case "nav6":
            
            // Initialize the row index for the table of nav files.  The table will 
            // contain the start and end times of the data within each file and the 
            // filename.  The table will be sorted from earliest to latest start
            // times.
            $inx = 0;
            $jnx = 0;
            while (false !== ($file = readdir($handle))) {
                
                if ($file != "." && $file != "..") {
                    
                    $filename = $file;
                    $fid = fopen($path . "/" . $filename, 'r');
                    
                    //----------- Loop Over Contents of Single File ----------//
                    while (!feof($fid)) {
                        
                        // Get message:
                        // Windows line endings.
                        $line = stream_get_line($fid, 1024, "\r\n");
                        // Remove line breaks from records.
                        $line = preg_replace("/\n/", "", $line);
                        //	    $line = trim( fgets($fid) );
                        //	    if ($line=="") break;

                        // comma-separated values
                        $NavRec = preg_split("/\,/", $line);

                        // values separated by slash "/"
                        $dateRec = preg_split("/\//", $NavRec[1]);
                        // values separated by colon ":"
                        $timeRec = preg_split("/\:/", $NavRec[2]);
                        
                        // Decode the date and time and the time precision:
                        $month = $dateRec[0];
                        $day   = $dateRec[1];
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
                        
                        // Print exactly the same precision time stamp
                        // as in the recorded data.
                        if ($tim_nroz == 0) {
                            $time_format = "%4d-%02d-%02dT%02d:%02d:%02dZ";
                        } else {
                            $time_format = "%4d-%02d-%02dT%02d:%02d:%0"
                                . ($tim_nroz + 3) . "." . $tim_nroz . "fZ";
                        }
                        
                        $dateStringUTCStartFile = sprintf(
                            $time_format, $year, $month, $day, 
                            $hour, $minute, $second
                        );
                        break;
                        
                    } // end loop over file
                    
                    // Grab last complete data record from file:
                    rewind($fid);
                    $line = trim(lastLine($fid, "\r\n"));  // Windows line endings.
                    // Remove line breaks from within records.
                    $line = preg_replace("/\n/", "", $line);
                    fclose($fid);
                    
                    // Check to see if line contains a timestamp:
                    if (preg_match("/[0-9]{2}\:[0-9]{2}\:[0-9]{2}/", $line)) {
                        
                        $NavRec = preg_split("/\,/", $line);

                        // values separated by slash "/"
                        $dateRec = preg_split("/\//", $NavRec[1]);
                        // values separated by colon ":"
                        $timeRec = preg_split("/\:/", $NavRec[2]);
                        
                        // Decode the date and time and the time precision:
                        $month = $dateRec[0];
                        $day   = $dateRec[1];
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
                        
                        // Print exactly the same precision time stamp
                        // as in the recorded data.
                        if ($tim_nroz == 0) {
                            $time_format = "%4d-%02d-%02dT%02d:%02d:%02dZ";
                        } else {
                            $time_format = "%4d-%02d-%02dT%02d:%02d:%0"
                                . ($tim_nroz + 3) . "." . $tim_nroz . "fZ";
                        }
                        
                        $dateStringUTCEndFile = sprintf(
                            $time_format, $year, $month, $day, 
                            $hour, $minute, $second
                        );
                        
                    } else {
                        
                        $dateStringUTCEndFile = $dateStringUTCStartFile;
                    }
                    
                    // Check that the start/end date/times in each file overlap the 
                    // start/end date/times entered on the command line:
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
            break;
            
            // "nav7": DAS: UH-specific
            // Vessels: Ka'imikai-o-Kanaloa
        case "nav7":
            echo "navdatalist(): Unsupported input file format.\n";
            exit(1);
            break;
            
            // "nav8": DAS: UH-specific
            // Vessels: Kilo Moana
        case "nav8":
            
            $have_nav = false;
            $inx = 0;
            $jnx = 0;
            while (false !== ($file = readdir($handle))) {
                
                if ($file != "." && $file != "..") {
                    
                    $filename = $file;
                    $fid = fopen($path . "/" . $filename, 'r');
                    
                    //----------- Loop Over Contents of Single File ----------//
                    while (!feof($fid)) {
                        
                        // Get message:
                        $line = trim(fgets($fid));
                        //	    if ($line=="") break;

                        // whitespace-separated values
                        $NavRec = preg_split("/\s+/", $line);
                        
                        $year = $NavRec[0];
                        $doy = $NavRec[1];
                        $hour = $NavRec[2];
                        $minute = $NavRec[3];
                        $second = $NavRec[4];
                        
                        // Convert DOY to Month and Day:
                        $result = doy2mmdd($year, $doy);
                        $month = $result["month"];
                        $day = $result["day"];
                        
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
                        
                        $dateStringUTCStartFile = sprintf(
                            $time_format, $year, $month, $day, 
                            $hour, $minute, $second
                        );
                        break;
                        
                    } // end loop over file
                    
                    // Grab last complete data record from file:
                    rewind($fid);
                    $line = trim(lastLine($fid, "\n"));
                    fclose($fid);
                    
                    $NavRec = preg_split("/\s+/", $line);
                    
                    $year = $NavRec[0];
                    $doy = $NavRec[1];
                    $hour = $NavRec[2];
                    $minute = $NavRec[3];
                    $second = $NavRec[4];
                    
                    // Convert DOY to Month and Day:
                    $result = doy2mmdd($year, $doy);
                    $month = $result["month"];
                    $day = $result["day"];
                    
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
                    
                    $dateStringUTCEndFile = sprintf(
                        $time_format, $year, $month, $day, 
                        $hour, $minute, $second
                    );
                    
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
            break;
            
            // "nav9": // DAS: SIO-specific (satdata)
            // Vessels: New Horizon, Robert Gordon Sproul
        case "nav9":
            
            $date_format = "%4d-%02d-%02dT%02d:%02d:%02dZ";
            
            // Initialize the row index for the table of nav files.  The table will 
            // contain the start and end times of the data within each file and the 
            // filename.  The table will be sorted from earliest to latest start
            // times.
            $inx = 0;
            $jnx = 0;
            while (false !== ($file = readdir($handle))) {
                
                if ($file != "." && $file != "..") {
                    
                    $filename = $file;
                    $fid = fopen($path . "/" . $filename, 'r');
                    
                    //----------- Loop Over Contents of Single File ----------//
                    while (!feof($fid)) {
                        
                        // Get message:
                        $line = trim(fgets($fid));
                        //	    if ($line=="") break;
                        
                        // Add missing white-space before minus signs '-' in
                        // input record:
                        $line = preg_replace("/-/", " -", $line);
                        $NavRec = preg_split("/[\s]+/", $line);
                        
                        $day = $NavRec[0];
                        $month = $NavRec[1];
                        $year = $NavRec[2];
                        $hhmm = intval($NavRec[3]);
                        $hour = intval($hhmm/1e2);
                        $minute = intval($hhmm - ($hour*1e2));
                        // Time is reported to nearest minute in satdata file.
                        $second = 0;
                        
                        $dateStringUTCStartFile = sprintf(
                            $date_format, $year, $month, $day, 
                            $hour, $minute, $second
                        );
                        break;
                        
                    } // end loop over file
                    
                    // Grab last complete data record from file:
                    rewind($fid);
                    $line = trim(lastLine($fid, "\n"));
                    fclose($fid);
                    
                    // Add missing white-space before minus signs '-' in 
                    // input record:
                    $line = preg_replace("/-/", " -", $line);
                    $NavRec = preg_split("/[\s]+/", $line);

                    $day = $NavRec[0];
                    $month = $NavRec[1];
                    $year = $NavRec[2];
                    $hhmm = intval($NavRec[3]);
                    $hour = intval($hhmm/1e2);
                    $minute = intval($hhmm - ($hour*1e2));
                    // Time is reported to nearest minute in satdata file.
                    $second = 0;	    
                    $dateStringUTCEndFile = sprintf(
                        $date_format, $year, $month, $day, 
                        $hour, $minute, $second
                    );
                    
                    // Check that the start/end date/times in each file overlap the 
                    // start/end date/times entered on the command line:
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
                        $otherParseableFiles[$jnx]["file"]  = $filename;
                        $jnx++;
                        
                    } // end if within bounds
                    
                } // if file is not "." nor ".."
                
            } // end loop over files in dir
            break;
            
            // "nav10": DAS: UW-specific
            // Vessels: Thomas G. Thompson
        case "nav10":
            
            // Initialize the row index for the table of nav files.  The table will 
            // contain the start and end times of the data within each file and the 
            // filename.  The table will be sorted from earliest to latest start
            // times.
            $inx = 0;
            $jnx = 0;
            while (false !== ($file = readdir($handle))) {
                
                if ($file != "." && $file != "..") {
                    
                    $filename = $file;
                    $fid = fopen($path . "/" . $filename, 'r');
                    
                    //----------- Loop Over Contents of Single File ----------//
                    while (!feof($fid)) {
                        
                        // Get message:
                        $line = trim(fgets($fid));
                        //	    if ($line=="") break;
                        
                        // comma-separated values
                        $NavRec = preg_split("/\,/", $line);
                        
                        // Sometime in 2010, UW changed the date format from 
                        // DD/MM/YYYY to DD-MM-YYYY.
                        // Test whether there are slashes (/) or hyphens (-) 
                        // in the date string and read accordingly:

                        // values separated by hyphen "-" or slash "/" 
                        $dateRec = preg_split("/\-|\//", $NavRec[0]);
                        // values separated by colon ":"
                        $timeRec = preg_split("/\:/", $NavRec[1]);
                        
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
                        
                        // Print exactly the same precision time stamp
                        // as in the recorded data.
                        if ($tim_nroz == 0) {
                            $time_format = "%4d-%02d-%02dT%02d:%02d:%02dZ";
                        } else {
                            $time_format = "%4d-%02d-%02dT%02d:%02d:%0"
                                . ($tim_nroz + 3) . "." . $tim_nroz . "fZ";
                        }
                        
                        $dateStringUTCStartFile = sprintf(
                            $time_format, $year, $month, $day, 
                            $hour, $minute, $second
                        );
                        break;
                        
                    } // end loop over file
                    
                    // Grab last complete data record from file:
                    rewind($fid);
                    $line = trim(lastLine($fid, "\n"));
                    fclose($fid);
                    
                    $NavRec = preg_split("/\,/", $line);
                    
                    // values separated by hyphen "-" or slash "/" 
                    $dateRec = preg_split("/\-|\//", $NavRec[0]);

                    // values separated by colon ":"
                    $timeRec = preg_split("/\:/", $NavRec[1]);
                    
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
                    
                    // Print exactly the same precision time stamp
                    // as in the recorded data.
                    if ($tim_nroz == 0) {
                        $time_format = "%4d-%02d-%02dT%02d:%02d:%02dZ";
                    } else {
                        $time_format = "%4d-%02d-%02dT%02d:%02d:%0"
                            . ($tim_nroz + 3) . "." . $tim_nroz . "fZ";
                    }
                    
                    $dateStringUTCEndFile = sprintf(
                        $time_format, $year, $month, $day, 
                        $hour, $minute, $second
                    );
                    
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
            break;
            
            // "nav11": DAS: UMiami-specific
            // Vessels: F. G. Walton Smith
        case "nav11":
            
            $date_format = "%4d-%02d-%02dT%02d:%02d:%02dZ";
            
            // Initialize the row index for the table of nav files.  The table will 
            // contain the start and end times of the data within each file and the 
            // filename.  The table will be sorted from earliest to latest start 
            // times.
            $inx = 0;
            $jnx = 0;
            while (false !== ($file = readdir($handle))) {
                
                if ($file != "." && $file != "..") {
                    
                    $filename = $file;
                    
                    // Sort the files whose names end in "POSMVGGA.dat".  There
                    // will generally be more than one per cruise.
                    if (preg_match("/POSMVGGA.dat$/i", $filename)) {
                        
                        $fid = fopen($path . "/" . $filename, 'r');
                        
                        //----------- Loop Over Contents of Single File ----------//
                        while (!feof($fid)) {
                            
                            // Get message:
                            $line = trim(fgets($fid));
                            //	      if ($line=="") break;
                            
                            // Skip over non-data records.  
                            // Records start with 2-digit month [00-12]
                            if (preg_match('/GGA/', $line)) {
                                //if ($line[0] == 0 || $line[0] == 1) {
                                
                                $NavRec = preg_split("/\s+/", $line);
                                
                                // External clock date:
                                $mm_dd_yyyy = preg_split("/\//", $NavRec[0]);
                                $month = $mm_dd_yyyy[0];
                                $day   = $mm_dd_yyyy[1];
                                $year  = $mm_dd_yyyy[2];
                                
                                // External clock time:
                                $hh_mm_ss_ms = preg_split("/\:/", $NavRec[1]);
                                $hour   = $hh_mm_ss_ms[0];
                                $minute = $hh_mm_ss_ms[1];
                                $second = $hh_mm_ss_ms[2]; 
                                
                                // GPS receiver clock time:
                                // Drop fractions of a second
                                //$hhmmss = intval($NavRec[2]);
                                //$hour   = intval($hhmmss/1e4);
                                //$minute = intval(($hhmmss - ($hour*1e4))/1e2);
                                //$second = $hhmmss - ($hour*1e4) - ($minute*1e2);
                                
                                $dateStringUTCStartFile = sprintf(
                                    $date_format, $year, $month, $day, 
                                    $hour, $minute, $second
                                );
                                break;
                                
                            } // end if data record
                            
                        } // end loop over file
                        
                        // Grab last complete data record from file:
                        rewind($fid);
                        $line = trim(lastLine($fid, "\n"));
                        fclose($fid);
                        
                        if (preg_match('/GGA/', $line)) {

                            $NavRec = preg_split("/\s+/", $line);
                            
                            // External clock date:
                            $mm_dd_yyyy = preg_split("/\//", $NavRec[0]);
                            $month = $mm_dd_yyyy[0];
                            $day   = $mm_dd_yyyy[1];
                            $year  = $mm_dd_yyyy[2];
                            
                            // External clock time:
                            $hh_mm_ss_ms = preg_split("/\:/", $NavRec[1]);
                            $hour   = $hh_mm_ss_ms[0];
                            $minute = $hh_mm_ss_ms[1];
                            $second = $hh_mm_ss_ms[2];
                            
                            // GPS receiver clock time:
                            // Drop fractions of a second
                            //$hhmmss = intval($NavRec[2]);
                            //$hour   = intval($hhmmss/1e4);
                            //$minute = intval(($hhmmss - ($hour*1e4))/1e2);
                            //$second = $hhmmss - ($hour*1e4) - ($minute*1e2);
                            
                            $dateStringUTCEndFile = sprintf(
                                $date_format, $year, $month, $day, 
                                $hour, $minute, $second
                            );
                            
                            if ($dateStringUTCEndFile < $dateStringUTCStartFile) {
                                $dateStringUTCEndFile = $dateStringUTCStartFile;
                            }
                            
                        } else {
                            
                            $dateStringUTCEndFile = $dateStringUTCStartFile;
                            
                        }
                        
                        // Check that the start/end date/times in each file overlap
                        // the start/end date/times entered on the command line:
                        if (!( (strtotime($dateStringUTCEndFile) < strtotime($dateStringUTCStart) ) 
                            || (strtotime($dateStringUTCStartFile) > strtotime($dateStringUTCEnd) ) ) 
                        ) {
                            
                            $table[$inx]["start"]
                                = strtotime($dateStringUTCStartFile);
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
                        
                    } // end if "POSMVGGA.dat"
                    
                } // if file is not "." nor ".."
                
            } // end loop over files in dir
            break;
            
            // "nav13": DAS: LUMCON 
            //                Multiple Instrument Data Aquisition System (MIDAS)
            // Vessels: Pelican
        case "nav13":
            
            // Initialize the row index for the table of nav files.  The table will 
            // contain the start and end times of the data within each file and the 
            // filename.  The table will be sorted from earliest to latest start
            // times.
            $inx = 0;
            $jnx = 0;
            while (false !== ($file = readdir($handle))) {
                
                if ($file != "." && $file != "..") {
                    
                    $filename = $file;
                    $fid = fopen($path . "/" . $filename, 'r');
                    
                    $knx = 1;
                    //----------- Loop Over Contents of Single File ----------//
                    while (!feof($fid)) {
                        
                        // Get message:
                        $line = trim(fgets($fid));
                        //	    if ($line=="") break;
                        
                        if ($knx > 1) { // Skip the header record.

                            // comma-separated values
                            $NavRec = preg_split("/\,/", $line);

                            // values separated by slash "/"
                            $dateRec = preg_split("/\//", $NavRec[0]);
                            // values separated by colon ":"
                            $timeRec = preg_split("/\:/", $NavRec[1]);
                            
                            // Decode the date and time and the time precision:
                            $month = $dateRec[0];
                            $day   = $dateRec[1];
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
                            
                            // Print exactly the same precision time stamp
                            // as in the recorded data.
                            if ($tim_nroz == 0) {
                                $time_format = "%4d-%02d-%02dT%02d:%02d:%02dZ";
                            } else {
                                $time_format = "%4d-%02d-%02dT%02d:%02d:%0"
                                    . ($tim_nroz + 3) . "." . $tim_nroz . "fZ";
                            }
                            
                            $dateStringUTCStartFile = sprintf(
                                $time_format, $year, $month, $day, 
                                $hour, $minute, $second
                            );
                            break;
                            
                        } // end if knx > 1 (skip header record)
                        
                        $knx++;
                        
                    } // end loop over file
                    
                    // Grab last complete data record from file:
                    rewind($fid);
                    $line = trim(lastLine($fid, "\n"));
                    fclose($fid);
                    
                    $NavRec = preg_split("/\,/", $line);

                    // values separated by slash "/"
                    $dateRec = preg_split("/\//", $NavRec[0]);
                    // values separated by colon ":"
                    $timeRec = preg_split("/\:/", $NavRec[1]);
                    
                    // Decode the date and time and the time precision:
                    $month = $dateRec[0];
                    $day   = $dateRec[1];
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
                    
                    // Print exactly the same precision time stamp
                    // as in the recorded data.
                    if ($tim_nroz == 0) {
                        $time_format = "%4d-%02d-%02dT%02d:%02d:%02dZ";
                    } else {
                        $time_format = "%4d-%02d-%02dT%02d:%02d:%0"
                            . ($tim_nroz + 3) . "." . $tim_nroz . "fZ";
                    }
                    
                    $dateStringUTCEndFile = sprintf(
                        $time_format, $year, $month, $day, 
                        $hour, $minute, $second
                    );
                    
                    // Check that the start/end date/times in each file overlap the 
                    // start/end date/times entered on the command line:
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
            break;
            
            // "nav14": DAS: MLML Underway Data Aquisition System (UDAS)
            // Vessels: Point Sur
        case "nav14":
            // "nav23": DAS: MLML Underway Data Aquisition System (UDAS) -- minus signs included!
            // Vessels: Point Sur
        case "nav23":
            
            // Initialize the row index for the table of nav files.  The table will 
            // contain the start and end times of the data within each file and the 
            // filename.  The table will be sorted from earliest to latest start
            // times.
            $inx = 0;
            $jnx = 0;
            while (false !== ($file = readdir($handle))) {
                
                if ($file != "." && $file != "..") {
                    
                    $filename = $file;
                    $fid = fopen($path . "/" . $filename, 'r');
                    
                    $knx = 1;  // Initialize the counter for data records in each file.
                    //----------- Loop Over Contents of Single File ----------//
                    while (!feof($fid)) {
                        
                        // Get message:
                        $line = trim(fgets($fid));
                        //	    if ($line=="") break;
                        
                        if ($knx > 1) { // Skip the header record.

                            // comma-separated values
                            $NavRec = preg_split("/\,/", $line);
                            
                            $yyyymmdd = trim($NavRec[2]);
                            $hhmmss   = trim($NavRec[3]);
                            
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
                            
                            // Print exactly the same precision time stamp
                            // as in the recorded data.
                            if ($tim_nroz == 0) {
                                $time_format = "%4d-%02d-%02dT%02d:%02d:%02dZ";
                            } else {
                                $time_format = "%4d-%02d-%02dT%02d:%02d:%0"
                                    . ($tim_nroz + 3) . "." . $tim_nroz . "fZ";
                            }
                            
                            $dateStringUTCStartFile = sprintf(
                                $time_format, $year, $month, $day, 
                                $hour, $minute, $second
                            );
                            break;
                            
                        } // end if knx > 1 (skip header record)
                        
                        $knx++;
                        
                    } // end loop over file
                    
                    // Grab last complete data record from file:
                    rewind($fid);
                    $line = trim(lastLine($fid, "\n"));
                    fclose($fid);
                    
                    $NavRec = preg_split("/\,/", $line);
                    
                    $yyyymmdd = trim($NavRec[2]);
                    $hhmmss   = trim($NavRec[3]);
                    
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
                    
                    // Print exactly the same precision time stamp
                    // as in the recorded data.
                    if ($tim_nroz == 0) {
                        $time_format = "%4d-%02d-%02dT%02d:%02d:%02dZ";
                    } else {
                        $time_format = "%4d-%02d-%02dT%02d:%02d:%0"
                            . ($tim_nroz + 3) . "." . $tim_nroz . "fZ";
                    }
                    
                    $dateStringUTCEndFile = sprintf(
                        $time_format, $year, $month, $day, 
                        $hour, $minute, $second
                    );
                    
                    // Check that the start/end date/times in each file overlap the 
                    // start/end date/times entered on the command line:
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
                        $otherParseableFiles[$jnx]["file"]  = $filename;
                        $jnx++;
                        
                    } // end if within bounds
                    
                } // if file is not "." nor ".."
                
            } // end loop over files in dir
            break;
            
            // "nav15": DAS: OSU comma-separated value format (csv)
            // Vessels: Wecoma
        case "nav15":
            
            // Initialize the row index for the table of nav files.  The table will 
            // contain the start and end times of the data within each file and the 
            // filename.  The table will be sorted from earliest to latest start
            // times.
            $inx = 0;
            $jnx = 0;
            while (false !== ($file = readdir($handle))) {
                
                if ($file != "." && $file != "..") {
                    
                    $filename = $file;
                    $fid = fopen($path . "/" . $filename, 'r');
                    
                    //----------- Loop Over Contents of Single File ----------//
                    while (!feof($fid)) {
                        
                        // Get message:
                        $line = trim(fgets($fid));
                        //	    if ($line=="") break;
                        
                        if (preg_match("/^DATA/", $line)) { // Skip non-data records.

                            // comma-separated values
                            $NavRec = preg_split("/\,/", $line);
                            
                            $dateStringUTCStartFile = trim($NavRec[1], " \t\n\r\0\x0B'\"");
                            
                            break;
                            
                        } // end if (preg_match("/^DATA/", $line))
                        
                    } // end loop over file
                    
                    // Grab last complete data record from file:
                    rewind($fid);
                    $line = trim(lastLine($fid, "\n"));
                    fclose($fid);
                    
                    $NavRec = preg_split("/\,/", $line);
                    
                    $dateStringUTCEndFile = trim($NavRec[1], " \t\n\r\0\x0B'\"");
                    
                    // Check that the start/end date/times in each file overlap the 
                    // start/end date/times entered on the command line:
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
            break;
            
            // "nav16": DAS: NobelTec track points
            // Vessels: Blue Heron
        case "nav16":
            
            // Initialize the row index for the table of nav files.  The table will 
            // contain the start and end times of the data within each file and the 
            // filename.  The table will be sorted from earliest to latest start
            // times.
            $inx = 0;
            $jnx = 0;
            while (false !== ($file = readdir($handle))) {
                
                if ($file != "." && $file != "..") {
                    
                    $filename = $file;
                    $fid = fopen($path . "/" . $filename, 'r');
                    
                    //----------- Loop over Contents of Single File to get first nav line ----------//
                    while (!feof($fid)) {
                        
                        $line = trim(fgets($fid));
                        
                        // Skip header records and get first navigation 
                        // record in file:
                        // Reached start of navigation data.
                        if (preg_match('/^TrackMarks = \{\{/', $line)) {
                            // Get first navigation record:
                            $line = trim(fgets($fid));
                            
                            // whitespace-separated values
                            $NavRec = preg_split("/[\s]+/", $line);

                            // values separated by slash "-"
                            $dateRec = preg_split("/\-/", $NavRec[6]);
                            // values separated by colon ":"
                            $timeRec = preg_split("/\:/", $NavRec[7]);
                            
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
                            
                            // Print exactly the same precision time stamp
                            // as in the recorded data.
                            if ($tim_nroz == 0) {
                                $time_format = "%4d-%02d-%02dT%02d:%02d:%02dZ";
                            } else {
                                $time_format = "%4d-%02d-%02dT%02d:%02d:%0"
                                    . ($tim_nroz + 3) . "." . $tim_nroz . "fZ";
                            }
                            
                            $dateStringUTCStartFile = sprintf(
                                $time_format, $year, $month, $day, 
                                $hour, $minute, $second
                            );
                            
                            break;
                            
                        } // if (preg_match('/^TrackMarks = \{\{/', $line))
                        
                    } // end while (!feof($fid))
                    
                    //----- Loop Over Contents of Single File to get last nav line -----//
                    while (!feof($fid)) {
                        
                        $line = trim(fgets($fid));
                        
                        if (preg_match('/^\}\}/', $line)) {
                            break;  // Reached end of navigation data.
                        } else {
                            
                            // whitespace-separated values
                            $NavRec = preg_split("/[\s]+/", $line);
                            
                            // values separated by slash "-"
                            $dateRec = preg_split("/\-/", $NavRec[6]);
                            // values separated by colon ":"
                            $timeRec = preg_split("/\:/", $NavRec[7]);
                            
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
                            
                            // Print exactly the same precision time stamp
                            // as in the recorded data.
                            if ($tim_nroz == 0) {
                                $time_format = "%4d-%02d-%02dT%02d:%02d:%02dZ";
                            } else {
                                $time_format = "%4d-%02d-%02dT%02d:%02d:%0"
                                    . ($tim_nroz + 3) . "." . $tim_nroz . "fZ";
                            }
                            
                            $dateStringUTCEndFile = sprintf(
                                $time_format, $year, $month, $day, 
                                $hour, $minute, $second
                            );
                            
                        } // end if not end of TrackMarks block
                        
                    } // end loop over navigation data records
                    
                    fclose($fid);
                    
                    // Check that the start/end date/times in each file overlap the 
                    // start/end date/times entered on the command line:
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
            break;
            
            // "nav19": DAS: OSU-specific (2009 and 2010)
            // Vessels: Wecoma
        case "nav19":
            
            $tmp = preg_split("/-/", $dateStringUTCStart);
            $baseyear = $tmp[0];
            $date_format = "%4d-%02d-%02dT%02d:%02d:%02dZ";
            
            // Initialize the row index for the table of nav files.  The table will 
            // contain the start and end times of the data within each file and the 
            // filename.  The table will be sorted from earliest to latest start
            // times.
            $inx = 0;
            $jnx = 0;
            while (false !== ($file = readdir($handle))) {
                
                if ($file != "." && $file != "..") {
                    
                    $filename = $file;
                    
                    // Sort the files whose names end in ".csv".  There
                    // will generally be more than one per cruise.
                    if (preg_match("/\.csv$/i", $filename)) {
                        
                        $fid = fopen($path . "/" . $filename, 'r');
                        
                        $lnx = 1;
                        //----------- Loop Over Contents of Single File ----------//
                        while (!feof($fid)) {
                            
                            // Get message:
                            $line = trim(fgets($fid));
                            
                            if ($lnx > 1) {  // Skip over header record.

                                // comma-separated values
                                $NavRec = preg_split("/\,/", $line);
                                
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
                                
                                $year   = $pc_year;
                                $month  = $pc_month;
                                $day    = $pc_day;
                                $hour   = $pc_hour;
                                $minute = $pc_minute;
                                $second = intval($pc_second);
                                
                                $dateStringUTCStartFile = sprintf(
                                    $date_format, $year, $month, $day, 
                                    $hour, $minute, $second
                                );
                                break;
                                
                            } // end if data record
                            
                            $lnx++;
                            
                        } // end loop over file
                        
                        // Grab last complete data record from file:
                        rewind($fid);
                        $line = trim(lastLine($fid, "\n"));
                        fclose($fid);
                        
                        // comma-separated values
                        $NavRec = preg_split("/\,/", $line);
                        
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
                        
                        $year   = $pc_year;
                        $month  = $pc_month;
                        $day    = $pc_day;
                        $hour   = $pc_hour;
                        $minute = $pc_minute;
                        $second = intval($pc_second);
                        
                        $dateStringUTCEndFile = sprintf(
                            $date_format, $year, $month, $day, 
                            $hour, $minute, $second
                        );
                        
                        if ($dateStringUTCEndFile < $dateStringUTCStartFile) {
                            $dateStringUTCEndFile = $dateStringUTCStartFile;
                        }
                        
                        // Check that the start/end date/times in each file overlap
                        // the start/end date/times entered on the command line:
                        if (!( (strtotime($dateStringUTCEndFile) < strtotime($dateStringUTCStart) ) 
                            || (strtotime($dateStringUTCStartFile) > strtotime($dateStringUTCEnd) ) ) 
                        ) {
                            
                            $table[$inx]["start"]
                                = strtotime($dateStringUTCStartFile);
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
                        
                    } // end if "POSMVGGA.dat"
                    
                } // if file is not "." nor ".."
                
            } // end loop over files in dir
            break;
            
            // "nav21": DAS: MLML Underway Data Aquisition System (UDAS)
            // Vessels: Point Sur (2008)
        case "nav21":
            
            // Initialize the row index for the table of nav files.  The table will 
            // contain the start and end times of the data within each file and the 
            // filename.  The table will be sorted from earliest to latest start
            // times.
            $inx = 0;
            $jnx = 0;
            while (false !== ($file = readdir($handle))) {
                
                if ($file != "." && $file != "..") {
                    
                    $filename = $file;
                    $fid = fopen($path . "/" . $filename, 'r');

                    // Initialize the counter for data records in each file.
                    $knx = 1;
                    //----------- Loop Over Contents of Single File ----------//
                    while (!feof($fid)) {
                        
                        // Get message:
                        $line = trim(fgets($fid));
                        //      if ($line=="") break;
                        
                        if ($knx > 1) { // Skip the header record.

                            // comma-separated values
                            $NavRec = preg_split("/\,/", $line);
                            
                            $dateRec = preg_split("/\//", $NavRec[0]);
                            $timeRec = preg_split("/\:/", $NavRec[1]);
                            
                            // Decode the date and time and the time precision:
                            $month  = $dateRec[0];
                            $day    = $dateRec[1];
                            // 2-digit year (only works for 2000+)
                            $year   = 2000 + $dateRec[2];
                            
                            $hour   = $timeRec[0];
                            $minute = $timeRec[1];
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
                            
                            $dateStringUTCStartFile = sprintf(
                                $time_format, $year, $month, $day, 
                                $hour, $minute, $second
                            );
                            break;
                            
                        } // end if knx > 1 (skip header record)
                        
                        $knx++;
                        
                    } // end loop over file
                    
                    // Grab last complete data record from file:
                    rewind($fid);
                    $line = trim(lastLine($fid, "\n"));
                    fclose($fid);
                    
                    $NavRec = preg_split("/\,/", $line);
                    
                    $dateRec = preg_split("/\//", $NavRec[0]);
                    $timeRec = preg_split("/\:/", $NavRec[1]);
                    
                    // Decode the date and time and the time precision:
                    $month  = $dateRec[0];
                    $day    = $dateRec[1];
                    // 2-digit year (only works for 2000+)
                    $year   = 2000 + $dateRec[2];
                    
                    $hour   = $timeRec[0];
                    $minute = $timeRec[1];
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
                    
                    $dateStringUTCEndFile = sprintf(
                        $time_format, $year, $month, $day, 
                        $hour, $minute, $second
                    );
                    
                    // Check that the start/end date/times in each file overlap the 
                    // start/end date/times entered on the command line:
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
            break;

            
            // "nav22: DAS: GGA only; date contained in filename
            // Vessels: Roger Revelle (secondary nav), New Horizon (secondary nav)
        case "nav22":

            $nmea = new NMEA0183Message();

            // Initialize the row index for the table of nav files.  The table will 
            // contain the start and end times of the data within each file and the 
            // filename.  The table will be sorted from earliest to latest start 
            // times.
            $inx = 0;
            $jnx = 0;
            while (false !== ($file = readdir($handle))) {
                
                if ($file != "." && $file != "..") {
                    
                    $filename = $file;

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

                    $fid = fopen($path . "/" . $filename, 'r');
 
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
                                
                                $NavRec = preg_split('/\,/', $nmea->data);
                                
                                // Do we have a valid GGA message?
                                if (preg_match('/^\$.{2}GGA$/', $NavRec[0]) 
                                    && valid_gga_message($NavRec)
                                ) {
                                    //echo "Found GGA.\n";
                                    
                                    // Process NMEA message as a GGA message:
                                    $gga = new NMEA0183_GGA();
                                    $gga->init($NavRec);
                            
                                    // Decode the date and time and the time precision:
                                    $month = $pc->month;
                                    $day   = $pc->day;
                                    $year  = $pc->year;
                                    
                                    $hour   = $gga->hour;
                                    $minute = $gga->minute;
                                    $second = $gga->second;
                            
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
                                    
                                    $dateStringUTCStartFile = sprintf(
                                        $time_format, $year, $month, $day, 
                                        $hour, $minute, $second
                                    );
                                    break;

                                } // end if valid GGA

                            } // end if valid checksum

                        } // end if record contains GGA string

                    } // end loop over file
                        
                    // Close file
                    fclose($fid);
 
                    // Get last GGA message in file:
                    $cmd_str2 = "tail $path/$filename | grep \"GGA\" |"
                        . " tail -1";
                        exec($cmd_str2, $output, $ret_status);
                        $line = $output[0];
                        unset($output);
                    
                    // Skip over non-data records.
                    if (preg_match('/^\$.{2}GGA/', $line)) {
                        
                        $nmea->init($line);
                        
                        // Is checksum valid?  (We don't allow data without checksums
                        // to be processed.)
                        if ($nmea->validCheckSum === true) {
                            
                            $NavRec = preg_split('/\,/', $nmea->data);
                            
                            // Do we have a valid GGA message?
                            if (preg_match('/^\$.{2}GGA$/', $NavRec[0]) 
                            && valid_gga_message($NavRec)
                            ) {
                                //echo "Found GGA.\n";
                                
                                // Process NMEA message as a GGA message:
                                $gga = new NMEA0183_GGA();
                                $gga->init($NavRec);
                                
                                // Decode the date and time and the time precision:
                                $month = $pc->month;
                                $day   = $pc->day;
                                $year  = $pc->year;
                                
                                $hour   = $gga->hour;
                                $minute = $gga->minute;
                                $second = $gga->second;
                                
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
                                
                                $dateStringUTCEndFile = sprintf(
                                    $time_format, $year, $month, $day, 
                                    $hour, $minute, $second
                                );

                            } // end if valid GGA

                        } // end if valid checsum

                    } else {  // if record does not contain GGA string
                             
                        $dateStringUTCEndFile = $dateStringUTCStartFile;
                        
                    } // end if record contains GGA string
                        
                    // Check that the start/end date/times in each file overlap 
                    // the start/end date/times entered on the command line:
                    if (!( (strtotime($dateStringUTCEndFile) < strtotime($dateStringUTCStart) ) 
                    || (strtotime($dateStringUTCStartFile) > strtotime($dateStringUTCEnd) ) ) 
                    ) {
                        
                        $table[$inx]["start"] 
                            = strtotime($dateStringUTCStartFile);
                        $table[$inx]["end"] = strtotime($dateStringUTCEndFile);
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
            break;

        case "nav26":   
     
            // Initialize the row index for the table of nav files.  The table will 
            // contain the start and end times of the data within each file and the 
            // filename.  The table will be sorted from earliest to latest start
            // times.
            $inx = 0; 
            $jnx = 0; 
            while (false !== ($file = readdir($handle))) {
     
                if ($file != "." && $file != "..") {
     
                    $filename = $file;
                    $fid = fopen($path . "/" . $filename, 'r');
     
                    //----------- Loop Over Contents of Single File ----------//
                    while (!feof($fid)) {

						$line = fgets($fid);

                        // comma-separated values
                        $NavRec = preg_split("/\,/", $line);

                        // values separated by dash "-"
                        $dateRec = preg_split("/-/", $NavRec[0]);
                        // values separated by colon ":"
                        $timeRec = preg_split("/\:/", $NavRec[1]);

                        // Decode the date and time and the time precision:
                        $month = $dateRec[0];
                        $day   = $dateRec[1];
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

                        // Print exactly the same precision time stamp
                        // as in the recorded data.
                        if ($tim_nroz == 0) {
                            $time_format = "%4d-%02d-%02dT%02d:%02d:%02dZ";
                        } else {
                            $time_format = "%4d-%02d-%02dT%02d:%02d:%0"
                                . ($tim_nroz + 3) . "." . $tim_nroz . "fZ";
                        }

						if (isset($year) && isset($month) && isset($day) && isset($hour) && isset($minute) && isset($second)) {
							$dateStringUTCStartFile = sprintf(
								$time_format, $year, $month, $day,
								$hour, $minute, $second
							);
							break;
						}

                    } // end loop over file

                    // Grab last complete data record from file:
                    rewind($fid);
                    $line = trim(lastLine($fid, "\n"));  // Windows line endings.
                    fclose($fid);

                    // Check to see if line contains a timestamp:
                    if (preg_match("/[0-9]{2}\:[0-9]{2}\:[0-9]{2}/", $line)) {

                        $NavRec = preg_split("/\,/", $line);

                        // values separated by dash "-"
                        $dateRec = preg_split("/-/", $NavRec[0]);
                        // values separated by colon ":"
                        $timeRec = preg_split("/\:/", $NavRec[1]);

                        // Decode the date and time and the time precision:
                        $month = $dateRec[0];
                        $day   = $dateRec[1];
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

                        // Print exactly the same precision time stamp
                        // as in the recorded data.
                        if ($tim_nroz == 0) {
                            $time_format = "%4d-%02d-%02dT%02d:%02d:%02dZ";
                        } else {
                            $time_format = "%4d-%02d-%02dT%02d:%02d:%0"
                                . ($tim_nroz + 3) . "." . $tim_nroz . "fZ";
                        }

                        $dateStringUTCEndFile = sprintf(
                            $time_format, $year, $month, $day,
                            $hour, $minute, $second
                        );

                    } else {

                        $dateStringUTCEndFile = $dateStringUTCStartFile;
                    }

                    // Check that the start/end date/times in each file overlap the 
                    // start/end date/times entered on the command line:
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
            break;

            
        case "Endeavor":
            
            $have_nav = false;
            while (false !== ($file = readdir($handle))) {
                
                if ($file != "." && $file != "..") {
                    
                    $filename = $file;
                    
                    // Grab the file with some flavor of "Nstar" (Northstar) in
                    // the name.  There is only one per cruise, so there is no
                    // need to compare with the user-specified start/end 
                    // date/times.
                    if (preg_match("/Nstar/i", $filename)) {
                        //echo $filename;
                        $navfilelist[] = $filename;  // Push filename onto array.
                        $have_nav = true;
                        break;
                    }
                    
                } // if file is not "." nor ".."
                
            } // end loop over files in dir
            
            if (!$have_nav) { 
                echo "navdatalist(): No Northstar 952 GPS receiver files in "
                    . "directory $path.\n";
                exit(1);
            } // end if no nav file found
            break;
            
        case "Cape Hatteras":
            
            $have_nav = false;
            while (false !== ($file = readdir($handle))) {
                
                if ($file != "." && $file != "..") {
                    
                    $filename = $file;
                    
                    // Grab the file with some flavor of "Nstar" (Northstar) in
                    // the name.  There is only one per cruise, so there is no 
                    // need to compare with the user-specified start/end date/times.
                    if (preg_match("/NS951/i", $filename)) {
                        //echo $filename;
                        $navfilelist[] = $filename;  // Push filename onto array.
                        $have_nav = true;
                        break;
                    }
                    
                } // if file is not "." nor ".."
                
            } // end loop over files in dir
            
            if (!$have_nav) {
                echo "navdatalist(): No NS951 GPS receiver files in "
                    . "directory $path.\n";
                exit(1);
            } // end if no nav file found
            break;
            
        case "nav31":  // UNIXD decimal day    
            
            // Initialize the row index for the table of nav files.  The table will 
            // contain the start and end times of the data within each file and the 
            // filename.  The table will be sorted from earliest to latest start
            // times.
            $inx = 0;
            while (false !== ($file = readdir($handle))) {
                
                if ($file != "." && $file != "..") {
                    
                    $filename = $file;
                    $cmd_str_unixd = "( head $path/$filename | grep \"PYRTM\" |"
                        . " head -1 ) 2> /dev/null";
                    exec($cmd_str_unixd, $result, $ret_status);
                    
                    if ($result[0]) {
                        
                        //	echo "$filename: $result[0]\n";
                        unset($result);
                        
                        // Get first and last date/time strings in each file:
                        $cmd_str1 = "( head $path/$filename | grep \"PYRTM\" | head -1 ) 2> /dev/null";
                        exec($cmd_str1, $output, $ret_status);
                        $pyrtm_start = new UHDAS_PYRTM();
                        $pyrtm_start->init(preg_split('/\,/', $output[0]));
                        $dateStringUTCStartFile = $pyrtm_start->time_stamp;
                        unset($output);
                        $cmd_str2 = "( tail $path/$filename | grep \"PYRTM\" | tail -1 ) 2> /dev/null";
                        exec($cmd_str2, $output, $ret_status);
                        $pyrtm_end = new UHDAS_PYRTM();
                        $pyrtm_end->init(preg_split('/\,/', $output[0]));
                        $dateStringUTCEndFile = $pyrtm_end->time_stamp;
                        unset($output);
                        
                       // 	echo "$filename: $dateStringUTCStartFile "
                       //      . "$dateStringUTCEndFile\n";
                        

                        // Check that the start/end date/times in each file overlap the 
                        // start/end date/times entered on the command line:
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
                        
                    } // end if PYRTM message found
                    
                } // if file is not "." nor ".."
                
            } // end loop over files in dir
            
            break;
            
            
        case "uhdas":  // UNIXD decimal day    
            
            // Initialize the row index for the table of nav files.  The table will 
            // contain the start and end times of the data within each file and the 
            // filename.  The table will be sorted from earliest to latest start
            // times.
            $inx = 0;
            while (false !== ($file = readdir($handle))) {
                
                if ($file != "." && $file != "..") {
                    
                    $filename = $file;
                    $cmd_str_unixd = "( head $path/$filename | grep \"UNIXD\" |"
                        . " head -1 ) 2> /dev/null";
                    exec($cmd_str_unixd, $result, $ret_status);
                    
                    if ($result[0]) {
                        
                        //	echo "$filename: $result[0]\n";
                        unset($result);
                        
                        // Get first and last date/time strings in each file:
                        $cmd_str1 = "( head $path/$filename | grep \"UNIXD\" |"
                            . " head -1 | awk -F , '{print $2}' ) 2> /dev/null";
                        exec($cmd_str1, $output, $ret_status);
                        $dateStringUTCStartFile = $output[0];
                        unset($output);
                        $cmd_str2 = "tail $path/$filename | grep \"UNIXD\" |"
                            . " tail -1 | awk -F , '{print $2}'";
                        exec($cmd_str2, $output, $ret_status);
                        $dateStringUTCEndFile = $output[0];
                        unset($output);
                        
                        //	echo "$filename: $dateStringUTCStartFile "
                        //     . "$dateStringUTCEndFile\n";
                        
                        // Check that the start/end date/times in each file overlap
                        // the start/end date/times entered on the command line:
                        //if (!( ($dateStringUTCEndFile < $dateNumberUTCStart ) || 
                        //	 ($dateStringUTCStartFile > $dateNumberUTCEnd ) ) ) {
                        
                        $table[$inx]["start"] = $dateStringUTCStartFile;
                        $table[$inx]["file"]  = $filename;
                        $inx++;
                        
                        // } // end if within bounds
                        
                    } // end if UNIXD message found
                    
                } // if file is not "." nor ".."
                
            } // end loop over files in dir
            
            // If table exists, sort in ascending order from earliest to 
            // latest start date/time:
            if ($table) {
                sort($table);
                
                $tmp = preg_split("/-/", $dateStringUTCStart);
                $baseyear = $tmp[0];
                
                $inxMax = count($table);
                for ($inx=0; $inx<$inxMax; $inx++) {
                    // Push filename onto array.
                    $navfilelist[] = $table[$inx]["file"];
                } // end loop over files in table
                
            } else {
                
                echo "navdatalist(): No files contain UNIXD decimal day strings "
                    . "between $dateStringUTCStart and $dateStringUTCEnd.\n";
                exit(1);
                
            } // end if $table
            break;
            
            // "siomet": DAS: SIO MET data acquisition system
            // Vessels: Roger Revelle, Melville
        case "siomet":
            
            // Initialize the row index for the table of nav files.  The table will
            // contain the start and end times of the data within each file and the
            // filename.  The table will be sorted from earliest to latest start
            // times.
            $inx = 0;
            $jnx = 0;
            while (false !== ($file = readdir($handle))) {
                
                if ($file != "." && $file != "..") {
                    
                    $fileextension = pathinfo($file, PATHINFO_EXTENSION);
                    
                    //	  if ($fileextension == "MET" || $fileextension = "met") {
                    if (preg_match("/MET$/", $file) || preg_match("/met$/", $file)) {
                        
                        $filename = $file;
                        $fid = fopen($path . "/" . $filename, 'r');
                        
                        $recnum = 1;
                        //----------- Loop Over Contents of Single File ----------//
                        while (!feof($fid)) {
                            
                            // Get message:
                            $line = trim(fgets($fid));
                            //            if ($line=="") break;
                            
                            // Read date from header record:
                            if (preg_match("/^\#/", $line)) {
                                  
                                // Grab date from second line of header record:
                                if ($recnum == 2) {

                                    $dateRec = preg_split(
                                        "/\s+/", trim($line, "# ")
                                    );
                                    
                                    $dateStr = strtotime($dateRec[1]);
                                    
                                    $year  = date('Y', $dateStr);
                                    $month = date('m', $dateStr);
                                    $day   = date('d', $dateStr); 
                                    
                                } // end if recnum==2
                                
                                $recnum++;
                                
                            } else { // Read data record:

                                // whitespace-separated values
                                $MetRec = preg_split("/\s+/", $line);
                                
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
                                
                                // Print exactly the same precision time stamp
                                // as in the recorded data.
                                if ($tim_nroz == 0) {
                                    $time_format = "%4d-%02d-%02dT%02d:%02d:%02dZ";
                                } else {
                                    $time_format = "%4d-%02d-%02dT%02d:%02d:%0"
                                        . ($tim_nroz + 3) . "." . $tim_nroz . "fZ";
                                }
                                
                                $dateStringUTCStartFile = sprintf(
                                    $time_format, $year, $month, $day, 
                                    $hour, $minute, $second
                                );
                                break;
                                
                            } // end if header vs data record
                            
                        } // end loop over file
                        
                        // Grab last complete data record from file:
                        rewind($fid);
                        $line = trim(lastLine($fid, "\n"));
                        
                        fclose($fid);

                        // whitespace-separated values
                        $MetRec = preg_split("/\s+/", $line);
                        
                        // Note: Each file is for a single day.  Re-use the date 
                        // found in the header record above.
                        
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
                        
                        // Print exactly the same precision time stamp
                        // as in the recorded data.
                        if ($tim_nroz == 0) {
                            $time_format = "%4d-%02d-%02dT%02d:%02d:%02dZ";
                        } else {
                            $time_format = "%4d-%02d-%02dT%02d:%02d:%0"
                                . ($tim_nroz + 3) . "." . $tim_nroz . "fZ";
                        }
                        
                        $dateStringUTCEndFile = sprintf(
                            $time_format, $year, $month, $day, 
                            $hour, $minute, $second
                        );
                        
                        // echo $dateStringUTCStartFile, " to ", 
                        //      $dateStringUTCEndFile, "\n";
                        
                        // Check that the start/end date/times in each file overlap
                        // the start/end date/times entered on the command line:
                        if (!( (strtotime($dateStringUTCEndFile) < strtotime($dateStringUTCStart) ) 
                            || (strtotime($dateStringUTCStartFile) > strtotime($dateStringUTCEnd) ) ) 
                        ) {

                            $table[$inx]["start"]
                                = strtotime($dateStringUTCStartFile);
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
                        
                    } // end if met file
                    
                } // if file is not "." nor ".."
                
            } // end loop over files in dir
            break;

        case "metoc_nav":

            // Initialize the row index for the table of nav files.  The table will
            // contain the start and end times of the data within each file and the
            // filename.  The table will be sorted from earliest to latest start
            // times.
            $inx = 0;
            $jnx = 0;
            while (false !== ($file = readdir($handle))) {

                if ($file != "." && $file != "..") {

                    if (preg_match("/dat$/", $file)) {

                        $filename = $file;
                        $fid = fopen($path . "/" . $filename, 'r');

                        $recnum = 1;
                        //----------- Loop Over Contents of Single File ----------//
                        while (!feof($fid)) {

                            // Get message:
                            $line = trim(fgets($fid));

                            // Grab date and time from first data record
                            if ($recnum == 5) {

                                $dateTimeRec = preg_split("/\s+/",trim(preg_split("/,/", $line)[0], "\""));

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

                                $dateStringUTCStartFile = sprintf(
                                    $time_format, $year, $month, $day,
                                    $hour, $minute, $second
                                );

                                break;
                            } else {
                                $recnum++;
                            }
                        } // end loop over file

                        // Grab last complete data record from file:
                        rewind($fid);
                        $line = trim(lastLine($fid, "\n"));

                        fclose($fid);

                        $dateTimeRec = preg_split("/\s+/",trim(preg_split("/,/", $line)[0], "\""));

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

                        $dateStringUTCEndFile = sprintf(
                            $time_format, $year, $month, $day,
                            $hour, $minute, $second
                        );

                        // Check that the start/end date/times in each file overlap
                        // the start/end date/times entered on the command line:
                        if (!( (strtotime($dateStringUTCEndFile) < strtotime($dateStringUTCStart) )
                            || (strtotime($dateStringUTCStartFile) > strtotime($dateStringUTCEnd) ) )
                        ) {

                            $table[$inx]["start"]
                                = strtotime($dateStringUTCStartFile);
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

                    } // end if met file

                } // if file is not "." nor ".."

            } // end loop over files in dir
            break;


        default:
            echo "navdatalist(): Unsupported input file format: ", 
                $inputFormatSpec, "\n";
            exit(1);
            break;  
            
        } // end switch $inputFormatSpec
        
    } else { // if don't have dir handle
        
        echo "navdatalist(): Could not access path: $path\n";
        exit(1);
    }
    
    // If table exists, sort in ascending order from earliest to 
    // latest start date/time:
    if ($table) {
        sort($table);
        
        // Print sorted table:
        $inxMax = count($table);
        for ($inx=0; $inx<$inxMax; $inx++) {
            echo "table[", $inx, "]: ", $table[$inx]["file"], "\t",
                gmdate("Y-m-d\TH:i:s", $table[$inx]["start"]), "Z", "\t",
                gmdate("Y-m-d\TH:i:s", $table[$inx]["end"]), "Z", "\n";
        }
        
        $inxMax = count($table);
        // Push filename and/or baseyear onto array:
        for ($inx=0; $inx<$inxMax; $inx++) {
            $navfilelist[] = isset($baseyear) 
                ? $table[$inx]["file"] . " " . $baseyear 
                : $table[$inx]["file"];
        } // end loop over files in table
        
        // Report if there are gaps longer than 12 hours between files.
        for ($inx=1; $inx<$inxMax; $inx++) {
            $delt = $table[$inx]["start"] - $table[$inx-1]["end"];
            if ( $delt > 3600*12 ) {
                $feedback[] = "About " . intval($delt/3600) . 
                    " hours elapsed between the end of " .
                    $table[$inx-1]["file"] . " and " . $table[$inx]["file"] .
                    ".  Is a file missing?\n";
            }
        } // end loop over files in table
        
    } else {
        
        echo "navdatalist(): No files contain date/time strings between "
            . "$dateStringUTCStart and $dateStringUTCEnd.\n";
        return array(false, false); 
        exit(1);
        
    } // end if $table
    
    // Report if there are parseable files which fall outside the departure and
    // arrival dates of this cruise:
    if (isset($otherParseableFiles)) {
        sort($otherParseableFiles);
        
        // Print sorted table:
        $inxMax = count($otherParseableFiles);
        for ($inx=0; $inx<$inxMax; $inx++) {
            echo "otherParseableFiles[", $inx, "]: ", 
                $otherParseableFiles[$inx]["file"], "\t",
                gmdate("Y-m-d\TH:i:s", $otherParseableFiles[$inx]["start"]), "Z", 
                "\t",
                gmdate("Y-m-d\TH:i:s", $otherParseableFiles[$inx]["end"]), "Z", "\n";
        }
        
        $otherParseableList = '';
        $jnxMax = count($otherParseableFiles);
        for ($jnx=0; $jnx<$jnxMax; $jnx++) {
            $otherParseableList .= sprintf(
                "%s\n", $otherParseableFiles[$jnx]["file"]
            );
        } // end loop over files in "other parseable" table
        
        $feedback[] = "The following parseable files fall outside the departure "
            . "and arrival dates of this cruise:\n" . $otherParseableList;
        
    }  // end if ($otherParseableFiles)
    
    // Report if there are files which could not be parsed:
    if (isset($otherNonParseableFiles)) {
        
        $otherNonParseableList = '';
        foreach ($otherNonParseableFiles as $other) { 
            $otherNonParseableList .= sprintf("%s\n", $other);
        }
        
        $feedback[] = "The following files could not be parsed:\n"
            . $otherNonParseableList;
        
    } // end if ($otherNonParseableFiles)

    
    return array($navfilelist, $feedback);
    
} // end function navdatalist()
?>
