#!/usr/bin/env php
<?php
/**
 * Define functions for creating R2R navigation processing configuration
 * file
 *
 * PHP version 5
 *
 * @category R2R_Products
 * @package  R2R_Nav
 * @author   Aaron Sweeney <asweeney@ucsd.edu>
 * @license  http://opensource.org/licenses/GPL-3.0 GNU General Public License
 * @link     http://www.rvdata.us
 */
require 'r2rdb-config.inc.php';
require 'jsontools.inc.php';

//----------- Class Definitions ----------//
/**
 * Physical Quantity (value, unit-of-measure)
 *
 * @category R2R_Products
 * @package  R2R_Nav
 * @author   Aaron Sweeney <asweeney@ucsd.edu>
 * @license  http://opensource.org/licenses/GPL-3.0 GNU General Public License
 * @link     http://www.rvdata.us
 */
class PhysicalQuantity
{
    public $uom, $value;
}  // end class PhysicalQuantity


/**
 * Navigation quality assessment parameters
 *
 * @category R2R_Products
 * @package  R2R_Nav
 * @author   Aaron Sweeney <asweeney@ucsd.edu>
 * @license  http://opensource.org/licenses/GPL-3.0 GNU General Public License
 * @link     http://www.rvdata.us
 */
class ProcessingParameters
{
    public $datetime_start_UTC, $datetime_end_UTC; 
    public $port_start_longitude, $port_start_latitude;
    public $port_end_longitude, $port_end_latitude;
    public $speed_threshold;
    public $acceleration_threshold;
    public $gap_threshold;
} // end class ProcessingParameters;


/**
 * Navigation processing configuration information
 *
 * @category R2R_Products
 * @package  R2R_Nav
 * @author   Aaron Sweeney <asweeney@ucsd.edu>
 * @license  http://opensource.org/licenses/GPL-3.0 GNU General Public License
 * @link     http://www.rvdata.us
 */
class Config
{
    public $creation_date, $cruiseid, $R2R_device_id;
    public $R2R_fileset_id, $R2R_file_format;
    public $nav_source;
    public $processing_parameters;
    public $directory_rawdata, $directory_rawdata_quality, $directory_products;
} // end class Config


/**
 * Vessel name and reference frame definition
 *
 * @category R2R_Products
 * @package  R2R_Nav
 * @author   Aaron Sweeney <asweeney@ucsd.edu>
 * @license  http://opensource.org/licenses/GPL-3.0 GNU General Public License
 * @link     http://www.rvdata.us
 */
class Vessel
{
    public $name, $reference_frame_definition;
} // end class Vessel


/**
 * Datatype and file format
 *
 * @category R2R_Products
 * @package  R2R_Nav
 * @author   Aaron Sweeney <asweeney@ucsd.edu>
 * @license  http://opensource.org/licenses/GPL-3.0 GNU General Public License
 * @link     http://www.rvdata.us
 */
class DeviceInterface
{
    public $data_type, $R2R_file_format;
} // end class Interface


/**
 * Device properties
 *
 * @category R2R_Products
 * @package  R2R_Nav
 * @author   Aaron Sweeney <asweeney@ucsd.edu>
 * @license  http://opensource.org/licenses/GPL-3.0 GNU General Public License
 * @link     http://www.rvdata.us
 */
class Device
{
    public $R2R_device_id, $type, $make, $model, $serial_num, $location_xyz;
    public $interface;
    public $R2R_fileset_id;
} // end class Device


/**
 * Metadata information
 *
 * @category R2R_Products
 * @package  R2R_Nav
 * @author   Aaron Sweeney <asweeney@ucsd.edu>
 * @license  http://opensource.org/licenses/GPL-3.0 GNU General Public License
 * @link     http://www.rvdata.us
 */
class Info
{
    public $cruiseid, $vessel, $device; 
} // end class Info

//------------ End Class Definitions ------------//

/**
 * Make the navigation processing configuration file from
 * R2R database queries and default processing parameters.
 *
 * @param string $cruiseid Cruise ID
 *
 * @return string Returns configuration filename
 */
function make_cfg($cruiseid) 
{  
    // Set default configuration parameters:
    $gapThreshold = 300 ;  // Minimum time interval [s] for reporting data gaps.
    $accelHoriMax = 1 ;    // Absolute value of maximum horizontal acceleration [m/s^2].
    
    // Create objects:
    $cfg = new Config;
    $cfg->processing_parameters = new ProcessingParameters;
    $cfg->processing_parameters->speed_threshold = new PhysicalQuantity;
    $cfg->processing_parameters->acceleration_threshold = new PhysicalQuantity;
    $cfg->processing_parameters->gap_threshold = new PhysicalQuantity;
    
    $vessel = new Vessel;
    
    // Get the start/end port lat/lons and the local start/end dates of this cruise:
    // Open connection to r2r db or use config file if provided.  (Possibly plan 
    // to replace r2r db connection with r2r web service call in future versions.)

	// Test for functions needed to connect to database
    if(!function_exists(pg_connect)) {
        echo "Database failed to connect: don't have php-psql functions installed.\n";
        $template_cfgfile = make_template_cfg($cruiseid);
        echo "Created template configuration file: ", $template_cfgfile, "\n";
        exit(1);
    }
    
    // Open connection to r2r database:
    $db = @pg_connect(DATABASE);
    if (!$db) {
        echo "Database failed to connect: ", DATABASE, "\n";
        $template_cfgfile = make_template_cfg($cruiseid);
        echo "Created template configuration file: ", $template_cfgfile, "\n";
        exit(1);
    }

	// Check to see if the cruise is in the database, if not, build an empty template config file
	$query = "SELECT id FROM cruise  WHERE cruise.id = '$cruiseid'";
	$result_SELECT = pg_query($db, $query);
	if (!$result_SELECT) {
		echo "No database record for cruise id " . $cruiseid . "\n";
		$temmplate_cfgfile = make_template_cfg($cruiseid);
        echo "Created template configuration file: ", $template_cfgfile, "\n";
        exit(1);
    }
    
    // Query the database for the cruise start lon, lat, and local date:
    $query = "SELECT port.longitude, port.latitude, cruise_port_map.depart_date FROM port INNER JOIN cruise_port_map ON port.id = cruise_port_map.depart_port_id WHERE cruise_port_map.cruise_id = '$cruiseid'";
    $result_SELECT = pg_query($db, $query);
    if (!$result_SELECT) {
        echo "Query on cruise_port_map and port tables failed for start of ", 
            $cruiseid, "\n"; 
        exit(1);
    }
    
    if (pg_num_rows($result_SELECT) == 0) {
        echo "0 records from cruise_port_map and port tables for start of ", 
            $cruiseid, "\n";
        exit(1);
    } else {
        while ($row = pg_fetch_row($result_SELECT)) {
            $start_longitude  = $row[0];
            $start_latitude   = $row[1];
            $start_date_local = $row[2];
        }
    }
    
    // Query the database for the cruise end lon, lat, and local date:
    $query = "SELECT port.longitude, port.latitude, cruise_port_map.arrive_date
FROM port INNER JOIN cruise_port_map ON port.id = cruise_port_map.arrive_port_id WHERE cruise_port_map.cruise_id = '$cruiseid'"; 
    $result_SELECT = pg_query($db, $query);
    if (!$result_SELECT) {
        echo "Query on cruise_port_map and port tables failed for end of ", 
            $cruiseid, "\n";  
        exit(1);
    }
    
    if (pg_num_rows($result_SELECT) == 0) {
        echo "0 records from cruise_port_map and port tables for end of ", 
            $cruiseid, "\n";
        exit(1);
    } else {
        while ($row = pg_fetch_row($result_SELECT)) {
            $end_longitude  = $row[0];
            $end_latitude   = $row[1];
            $end_date_local = $row[2];
        }
    }
    
    // Use Geonames web service to get timezone offset from UTC of start port:
    $request_REST = 'http://ws.geonames.org/timezone?lat=' . $start_latitude 
        . '&lng=' . $start_longitude;
    $response_REST = file_get_contents($request_REST);
    if ($response_REST === false) {
        echo "getmeta: $request_REST\n";
        echo "getmeta: Failed to get timezone from Geonames webservice.\n";
        exit(1);
    }
    
    //echo $response_REST;
    
    // Since DOM support may not be included with every PHP version,
    // this is a hack to read the XML document for the localtime
    // offset from UTC (not including daylight saving time [dst],
    // whose policy is not necessarily tracked by Geonames):
    
    $xml_lines = explode("\n", $response_REST);
    foreach ($xml_lines as $xml_line) {
        if (preg_match('/<gmtOffset>/', $xml_line) > 0) {
            $xml_records = preg_split('/\<|\>/', $xml_line);
            $hoursOffsetUTCStartPort = floatval(trim($xml_records[2]));
            break;
        }
    }
    
    //echo "Start port offset from UTC [hrs] (localtime = UTC + offset): ", $hoursOffsetUTCStartPort, "\n";
    
    // Use Geonames web service to get timezone offset from UTC of end port:
    $request_REST = 'http://ws.geonames.org/timezone?lat=' . $end_latitude
        . '&lng=' . $end_longitude;
    $response_REST = file_get_contents($request_REST);
    if ($response_REST === false) {
        echo "getmeta: $request_REST\n";
        echo "getmeta: Failed to get timezone from Geonames webservice.\n";
        exit(1);
    }
    
    //echo $response_REST;
    
    // Since DOM support may not be include with every PHP version,
    // this is a hack to read the XML document for the localtime
    // offset from UTC (not including daylight saving time [dst],
    // whose policy is not necessarily tracked by Geonames):
    
    $xml_lines = explode("\n", $response_REST);
    foreach ($xml_lines as $xml_line) {
        if (preg_match('/<gmtOffset>/', $xml_line) > 0) {
            $xml_records = preg_split('/\<|\>/', $xml_line);
            $hoursOffsetUTCEndPort = floatval(trim($xml_records[2]));
            break;
        }
    }
    
    //echo "End port offset from UTC [hrs] (localtime = UTC + offset): ", $hoursOffsetUTCEndPort, "\n";
    
    // Determine UTC start and end date/times of cruise.  Because we
    // don't know what time the ship leaves or enters port, we define
    // the UTC start date/time to be 00:00:00 on the local start date
    // and the UTC end date/time to be 23:59:59 on the local end date.
    // UTC = localtime - hoursOffset  
    
    list($yearLocalStart, $monthLocalStart, $dayLocalStart) 
        = sscanf($start_date_local, "%4d-%2d-%2d");
    
    if ($hoursOffsetUTCStartPort >= 0) {
        $dateTimeUnixStart = strtotime("-" . abs($hoursOffsetUTCStartPort) . " hours", gmmktime(0, 0, 0, $monthLocalStart, $dayLocalStart, $yearLocalStart));
    } else {
        $dateTimeUnixStart = strtotime("+" . abs($hoursOffsetUTCStartPort) . " hours", gmmktime(0, 0, 0, $monthLocalStart, $dayLocalStart, $yearLocalStart));
    }
    $dateStringUTCStart = gmdate("Y-m-d\TH:i:s\Z", $dateTimeUnixStart);
    
    list($yearLocalEnd, $monthLocalEnd, $dayLocalEnd) = sscanf($end_date_local, "%4d-%2d-%2d");
    
    if ($hoursOffsetUTCEndPort >= 0) {
        $dateTimeUnixEnd = strtotime("-" . abs($hoursOffsetUTCEndPort) . " hours", gmmktime(23, 59, 59, $monthLocalEnd, $dayLocalEnd, $yearLocalEnd));
    } else{
        $dateTimeUnixEnd = strtotime("+" . abs($hoursOffsetUTCEndPort) . " hours", gmmktime(23, 59, 59, $monthLocalEnd, $dayLocalEnd, $yearLocalEnd));
    }
    $dateStringUTCEnd = gmdate("Y-m-d\TH:i:s\Z", $dateTimeUnixEnd);
    
    // Get maximum vessel speed [m/s]:
    $query = "SELECT vessel.max_speed FROM vessel INNER JOIN cruise ON vessel.id = cruise.vessel_id WHERE cruise.id = '$cruiseid'";
    $result_SELECT = pg_query($db, $query);
    if (!$result_SELECT) {
        echo "Query to get start max vessel speed failed\n";
        exit(1);
    }
    
    if (pg_num_rows($result_SELECT) == 0) {
        echo "0 records for max vessel speed for ", $cruiseid, "\n";
        exit(1);
    } else {
        while ($row = pg_fetch_row($result_SELECT)) {
            $speedVesselMax = $row[0];
        }
    }
    
    // Add 1 m/s to max vessel speed to allow for forward pitch.
    $speedHoriMax = $speedVesselMax + 1;
    
    // Get fileset metadata for nav_source = 1 (there is only one of these per cruise):
    //
    // Old method:
    //  $query = "SELECT fileset.id, fileset.device_id, fileformat.short_name, fileset.nav_source FROM fileset INNER JOIN fileformat ON fileformat.id = fileset.fileformat_id WHERE fileset.cruise_id = '$cruiseid' and fileset.nav_source = '1'";
    //
    // New method (2011-07-20):
    $query = "SELECT fileset.id, device_interface_map.device_id, fileformat.short_name,
	    device_interface_map.nav_source 
            FROM fileset INNER JOIN device_interface_map ON 
            device_interface_map.id = fileset.device_interface_map_id
	    INNER JOIN fileformat ON fileformat.id = 
            device_interface_map.fileformat_id
            WHERE fileset.cruise_id = '$cruiseid' and
            device_interface_map.nav_source = '1'";
    $result_SELECT = pg_query($db, $query);
    if (!$result_SELECT) {
        echo "Query on fileset and fileformat tables where nav_source='1' failed for ",
            $cruiseid, "\n";  
        exit(1);
    }
    
    if (pg_num_rows($result_SELECT) == 0) {
        echo "0 records from fileset and fileformat tables where nav_source='1' for ",
            $cruiseid, "\n";
        $nav_source_defined = false;
    } else {
        while ($row = pg_fetch_row($result_SELECT)) {
            $r2r_fileset_id  = $row[0];
            $r2r_device_id   = $row[1];
            $r2r_file_format = $row[2];
            $nav_source      = $row[3];
        }
        $nav_source_defined = true;
    }
    
    
    if ($nav_source_defined) {
        
        // Define relative paths:
        $relPathNavigationRaw = "/data/r2r/cruise/" . $cruiseid . "/fileset/" . $r2r_fileset_id . "/data";
        $relPathNavigationRawQuality = "/data/r2r/cruise/" . $cruiseid . "/fileset/" . $r2r_fileset_id . "/qa";
        $relPathNavigationProducts = "/data/r2r/cruise/" . $cruiseid . "/products/r2rnav";
        
        // Build the configuration object that will be written to file: 
        $cfg->creation_date = gmdate("Y-m-d\TH:i:s\Z");
        $cfg->cruiseid = $cruiseid;
        $cfg->R2R_device_id = $r2r_device_id;
        $cfg->R2R_fileset_id = $r2r_fileset_id;
        $cfg->R2R_file_format = $r2r_file_format;
        $cfg->nav_source = $nav_source;   // 0 = unset, 1 = primary, 2 = secondary, 3 = tertiary, etc.
        $cfg->processing_parameters->datetime_start_UTC = $dateStringUTCStart;
        $cfg->processing_parameters->datetime_end_UTC = $dateStringUTCEnd;
        $cfg->processing_parameters->port_start_longitude = $start_longitude;
        $cfg->processing_parameters->port_start_latitude = $start_latitude;
        $cfg->processing_parameters->port_end_longitude = $end_longitude;
        $cfg->processing_parameters->port_end_latitude = $end_latitude;
        $cfg->processing_parameters->speed_threshold->uom = "m/s";
        $cfg->processing_parameters->speed_threshold->value = $speedHoriMax;
        $cfg->processing_parameters->acceleration_threshold->uom = "m/s^2";
        $cfg->processing_parameters->acceleration_threshold->value = $accelHoriMax;
        $cfg->processing_parameters->gap_threshold->uom = "s";
        $cfg->processing_parameters->gap_threshold->value = $gapThreshold;
        $cfg->directory_rawdata = $relPathNavigationRaw;
        $cfg->directory_rawdata_quality = $relPathNavigationRawQuality;
        $cfg->directory_products = $relPathNavigationProducts;
        
        // Serialize the object as JSON and pretty it up before printing.
        // Pretty-printing makes it easier for a human to modify the *cfg file.
        $cfg_json = json_format($cfg);
        $cfgfile = $cruiseid . "_" . $r2r_fileset_id . "_qacfg.json";
        file_put_contents($cfgfile, $cfg_json);
        $cfgfilelist[] = $cfgfile;  // Push new cfg filename onto cfg file list.
        
    } // if ($nav_source_defined = true)
    
    // Get fileset metadata for all gnss devices (except nav_source = 1, which was 
    // caught above).  There may be zero, one, or more than one of these.
    //
    // Old method:
    //$query = "SELECT fileset.id, fileset.device_id, fileformat.short_name, fileset.nav_source FROM fileset INNER JOIN fileformat ON fileformat.id = fileset.fileformat_id INNER JOIN device ON device.id = fileset.device_id WHERE fileset.cruise_id = '$cruiseid' and device.devicetype_id = 'gnss' and fileset.nav_source != '1'";
    //
    // New method (2011-07-20):
    //  $query = "SELECT fileset.id, device_interface_map.device_id, fileformat.short_name,
    //        device_interface_map.nav_source
    //        FROM fileset INNER JOIN device_interface_map ON 
    //        device_interface_map.id = fileset.device_interface_map_id
    //        INNER JOIN device ON device.id = device_interface_map.device_id
    //        INNER JOIN fileformat ON fileformat.id = 
    //        device_interface_map.fileformat_id
    //        WHERE fileset.cruise_id = '$cruiseid'
    //        and device.devicetype_id = 'gnss'
    //        and device_interface_map.nav_source != '1'";
    //
    // Latest method (2011-09-12):
    $query = "SELECT fileset.id, device_interface_map.device_id, fileformat.short_name,
            device_interface_map.nav_source
            FROM fileset INNER JOIN device_interface_map ON 
            device_interface_map.id = fileset.device_interface_map_id
            INNER JOIN device ON device.id = device_interface_map.device_id
            INNER JOIN model ON model.id = device.model_id
            INNER JOIN fileformat ON fileformat.id = device_interface_map.fileformat_id
            WHERE fileset.cruise_id = '$cruiseid'
            and (model.devicetype_id = 'gnss' or model.devicetype_id = 'ins')
            and device_interface_map.nav_source != '1'"; 
    $result_SELECT = pg_query($db, $query);
    if (!$result_SELECT) {
        echo "Query on fileset, device, and fileformat tables where nav_source!='1' failed for ", $cruiseid, "\n";  
        exit(1);
    }
    
    if (pg_num_rows($result_SELECT) != 0) {  // There may be no secondary devices.
        while ($row = pg_fetch_row($result_SELECT)) {
            $r2r_fileset_id  = $row[0];
            $r2r_device_id   = $row[1];
            $r2r_file_format = $row[2];
            $nav_source      = $row[3];
            
            // There may be more than one result row:
            // Define relative paths:
            $relPathNavigationRaw = $cruiseid . "/fileset/" . $r2r_fileset_id . "/data";
            $relPathNavigationRawQuality = $cruiseid . "/fileset/" . $r2r_fileset_id . "/qa";
            $relPathNavigationProducts = $cruiseid . "/products/r2rnav";
            
            // Build the configuration object that will be written to file: 
            $cfg->creation_date = gmdate("Y-m-d\TH:i:s\Z");
            $cfg->cruiseid = $cruiseid;
            $cfg->R2R_device_id = $r2r_device_id;
            $cfg->R2R_fileset_id = $r2r_fileset_id;
            $cfg->R2R_file_format = $r2r_file_format;
            $cfg->nav_source = $nav_source;   // 0 = unset, 1 = primary, 2 = secondary, 3 = tertiary, etc.
            $cfg->processing_parameters->datetime_start_UTC = $dateStringUTCStart;
            $cfg->processing_parameters->datetime_end_UTC = $dateStringUTCEnd;
            $cfg->processing_parameters->port_start_longitude = $start_longitude;
            $cfg->processing_parameters->port_start_latitude = $start_latitude;
            $cfg->processing_parameters->port_end_longitude = $end_longitude;
            $cfg->processing_parameters->port_end_latitude = $end_latitude;
            $cfg->processing_parameters->speed_threshold->uom = "m/s";
            $cfg->processing_parameters->speed_threshold->value = $speedHoriMax;
            $cfg->processing_parameters->acceleration_threshold->uom = "m/s^2";
            $cfg->processing_parameters->acceleration_threshold->value = $accelHoriMax;
            $cfg->processing_parameters->gap_threshold->uom = "s";
            $cfg->processing_parameters->gap_threshold->value = $gapThreshold;
            $cfg->directory_rawdata = $relPathNavigationRaw;
            $cfg->directory_rawdata_quality = $relPathNavigationRawQuality;
            $cfg->directory_products = $relPathNavigationProducts;
            
            // Serialize the object as JSON and pretty it up before printing.
            // Pretty-printing makes it easier for a human to modify the *cfg file.
            $cfg_json = json_format($cfg);
            $cfgfile = $cruiseid . "_" . $r2r_fileset_id . "_qacfg.json";
            file_put_contents($cfgfile, $cfg_json);
            $cfgfilelist[] = $cfgfile;  // Push new cfg filename onto cfg file list.
            
        } //  end while ($row = pg_fetch_row($result_SELECT)) 
    } // end if (pg_num_rows($result_SELECT) == 0)
    
    // Free resultset:
    pg_free_result($result_SELECT);
    
    // Close connection to db:
    pg_close($db);
    
    // Return the name(s) of the config file(s).
    return $cfgfilelist;
    
} // end make_cfg( $cruiseid )


/**
 * Build a template configuration object that will be written to file
 *
 * @param string $cruiseid Cruise ID
 *
 * @return string Returns template configuration filename
 */
function make_template_cfg($cruiseid) 
{
    // Create objects:
    $cfg = new Config;
    $cfg->processing_parameters = new ProcessingParameters;
    $cfg->processing_parameters->speed_threshold = new PhysicalQuantity;
    $cfg->processing_parameters->acceleration_threshold = new PhysicalQuantity;
    $cfg->processing_parameters->gap_threshold = new PhysicalQuantity;
    
    $cfg->creation_date = gmdate("Y-m-d\TH:i:s\Z");
    $cfg->cruiseid = $cruiseid;
    $cfg->R2R_device_id = "";
    $cfg->R2R_fileset_id = "";
    $cfg->R2R_file_format = "";
    $cfg->nav_source = "";   // 0 = unset, 1 = primary, 2 = secondary, 3 = tertiary, etc.
    $cfg->processing_parameters->datetime_start_UTC = "";
    $cfg->processing_parameters->datetime_end_UTC = "";
    $cfg->processing_parameters->port_start_longitude = "";
    $cfg->processing_parameters->port_start_latitude = "";
    $cfg->processing_parameters->port_end_longitude = "";
    $cfg->processing_parameters->port_end_latitude = "";
    $cfg->processing_parameters->speed_threshold->uom = "m/s";
    $cfg->processing_parameters->speed_threshold->value = "";
    $cfg->processing_parameters->acceleration_threshold->uom = "m/s^2";
    $cfg->processing_parameters->acceleration_threshold->value = "";
    $cfg->processing_parameters->gap_threshold->uom = "s";
    $cfg->processing_parameters->gap_threshold->value = "";
    $cfg->directory_rawdata = "";
    $cfg->directory_rawdata_quality = "";
    $cfg->directory_products = "";
    
    // Serialize the object as JSON and pretty it up before printing.
    // Pretty-printing makes it easier for a human to modify the *cfg file.
    $cfg_json = json_format($cfg);
    $cfgfile = $cruiseid . "_template_qacfg.json";
    file_put_contents($cfgfile, $cfg_json);
    
    // Return the name of the template config file.
    return $cfgfile;
    
} // end function make_template_cfg()


/**
 * Read configuration from file, assumed to be in JSON format.
 *
 * @param string $cfgfile Configuration filename
 *
 * @return object Returns $cfg object
 */
function read_cfg($cfgfile) 
{   
    $cfgBlock = file_get_contents($cfgfile, "r");
    
    $cfg = json_decode($cfgBlock);
    
    // JSON syntax check:
    switch(json_last_error()) {
    case JSON_ERROR_DEPTH:
        echo "Error: ", $cfgfile, " - Maximum stack depth exceeded.\n";
        break;
    case JSON_ERROR_CTRL_CHAR:
        echo "Error: ", $cfgfile, " - Unexpected control character found.\n";
        break;
    case JSON_ERROR_SYNTAX:
        echo "Error: ", $cfgfile, " - Syntax error, malformed JSON.\n";
        break;
    case JSON_ERROR_NONE:
        break;
    }
    
    // In the absence of a JSON schema and method of validation,  this 
    // function does its best to check the contents of the config file.
    // validate_cfg() also modifies the $cfg object to include full
    // pathnames and creates those paths if they do not already exist.
    validate_cfg($cfg, $cfgfile);
    
    //  print_r($cfg);
    
    return $cfg;
    
} // end function read_cfg( $cfgfile )


/**
 * Test the config file for the required properties and check
 * that values are within expectations.
 *
 * @param object $cfg     Configuration object
 * @param string $cfgfile Configuration filename
 */
function validate_cfg($cfg, $cfgfile) 
{
    // Purpose: Test the config file for the required properties and check
    // that values are within expectations.
    //
    // Arguments: configuration object, configuration filename
    // Exits with error, otherwise returns nothing.
    
    if (!property_exists($cfg, 'R2R_device_id')) {
        echo "Error: ", $cfgfile, " - 'R2R_device_id' does not exist.\n";
        exit(1);
    } else if (!isset($cfg->R2R_device_id)) {
        echo "Error: ", $cfgfile, " - 'R2R_device_id' is not set.\n";
        exit(1);
    }
    
    if (!property_exists($cfg, 'R2R_fileset_id')) {
        echo "Error: ", $cfgfile, " - 'R2R_fileset_id' does not exist.\n";
        exit(1);
    } else if (!isset($cfg->R2R_fileset_id)) {
        echo "Error: ", $cfgfile, " - 'R2R_fileset_id' is not set.\n";
        exit(1);
    }
    
    if (!property_exists($cfg, 'R2R_file_format')) {
        echo "Error: ", $cfgfile, " - 'R2R_file_format' does not exist.\n";
        exit(1);
    } else if (!isset($cfg->R2R_file_format)) {
        echo "Error: ", $cfgfile, " - 'R2R_file_format' is not set.\n";
        exit(1);
    }
    
    if (!property_exists($cfg, 'nav_source')) {
        echo "Error: ", $cfgfile, " - 'nav_source' does not exist.\n";
        exit(1);
    }
    
    if (!property_exists($cfg, 'processing_parameters')) {
        echo "Error: ", $cfgfile, " - 'processing_parameters' does not exist.\n";
        exit(1);
    }
    
    if (!property_exists($cfg->processing_parameters, 'datetime_start_UTC')) {
        echo "Error: ", $cfgfile, " - 'datetime_start_UTC' does not exist.\n";
        exit(1);
    }
    
    if (!property_exists($cfg->processing_parameters, 'datetime_end_UTC')) {
        echo "Error: ", $cfgfile, " - 'datetime_end_UTC' does not exist.\n";
        exit(1);
    }
    
    //  if (!property_exists($cfg->processing_parameters,'port_start_longitude')) {
    //   echo "Error: ", $cfgfile, " - 'port_start_longitude' does not exist.\n";
    //  exit(1);
    // }
    
    //if (!property_exists($cfg->processing_parameters,'port_start_latitude')) {
    //  echo "Error: ", $cfgfile, " - 'port_start_latitude' does not exist.\n";
    //  exit(1);
    // }
    
    //if (!property_exists($cfg->processing_parameters,'port_end_longitude')) {
    //  echo "Error: ", $cfgfile, " - 'port_end_longitude' does not exist.\n";
    //  exit(1);
    // }
    
    //if (!property_exists($cfg->processing_parameters,'port_end_latitude')) {
    //  echo "Error: ", $cfgfile, " - 'port_end_latitude' does not exist.\n";
    //  exit(1);
    // }
    
    if (!property_exists($cfg->processing_parameters, 'speed_threshold')) {
        echo "Error: ", $cfgfile, " - 'speed_threshold' does not exist.\n";
        exit(1);
    }
    
    if (!property_exists($cfg->processing_parameters->speed_threshold, 'uom') 
        || !property_exists($cfg->processing_parameters->speed_threshold, 'value')
    ) {
        echo "Error: ", $cfgfile, 
            " - 'speed_threshold' must have both 'uom' and 'value'.\n";
        exit(1);
    }
    
    if (!property_exists($cfg->processing_parameters, 'acceleration_threshold')) {
        echo "Error: ", $cfgfile, " - 'acceleration_threshold' does not exist.\n";
        exit(1);
    }
    
    if (!property_exists($cfg->processing_parameters->acceleration_threshold, 'uom') 
        || !property_exists($cfg->processing_parameters->acceleration_threshold, 'value')
    ) {
        echo "Error: ", $cfgfile, 
            " - 'acceleration_threshold' must have both 'uom' and 'value'.\n";
        exit(1);
    }
    
    if (!property_exists($cfg->processing_parameters, 'gap_threshold')) {
        echo "Error: ", $cfgfile, " - 'gap_threshold' does not exist.\n";
        exit(1);
    }
    
    if (!property_exists($cfg->processing_parameters->gap_threshold, 'uom') 
        || !property_exists($cfg->processing_parameters->gap_threshold, 'value')
    ) {
        echo "Error: ", 
            $cfgfile, " - 'gap_threshold' must have both 'uom' and 'value'.\n";
        exit(1);
    }
    
    if (!property_exists($cfg, 'directory_rawdata')) {
        echo "Error: ", $cfgfile, " - 'directory_rawdata' does not exist.\n";
        exit(1);
    }
    
    if (!property_exists($cfg, 'directory_rawdata_quality')) {
        echo "Error: ", $cfgfile, " - 'directory_rawdata_quality' does not exist.\n";
        exit(1);
    }
    
    if (!property_exists($cfg, 'directory_products')) {
        echo "Error: ", $cfgfile, " - 'directory_products' does not exist.\n";
        exit(1);
    }
    
    // Start date/time must be earlier than end date/time:
    $dateStringUTCStart = $cfg->processing_parameters->datetime_start_UTC;
    $dateStringUTCEnd = $cfg->processing_parameters->datetime_end_UTC;
    
    if (strtotime($dateStringUTCStart) >= strtotime($dateStringUTCEnd)) {
        echo "Error: ", $cfgfile, " - Start date/time must be earlier than end date/time.\n";
        echo "Start: ", $cfg->processing_parameters->datetime_start_UTC, "\n";
        echo "End:   ", $cfg->processing_parameters->datetime_end_UTC, "\n";
        exit(1);
    }
    
    // Longitudes must be [-180, 180] and latitudes [-90,90]:
    //  if ($cfg->processing_parameters->port_start_longitude < -180 
    //      || $cfg->processing_parameters->port_start_longitude > 180) {
    //  echo "Error: ", $cfgfile, " - Port start longitude must be within [-180,180].\n";
    //  echo "Port start longitude: ", $cfg->processing_parameters->port_start_longitude, "\n";
    //  exit(1);
    // }
    
    //if ($cfg->processing_parameters->port_start_latitude < -90 
    //    || $cfg->processing_parameters->port_start_latitude > 90) {
    //  echo "Error: ", $cfgfile, " - Port start latitude must be within [-90,90].\n";
    // echo "Port start latitude: ", $cfg->processing_parameters->port_start_latitude, "\n";
    //  exit(1);
    // }
    
    //if ($cfg->processing_parameters->port_end_longitude < -180 
    // || $cfg->processing_parameters->port_end_longitude > 180) {
    //  echo "Error: ", $cfgfile, " - Port end longitude must be within [-180,180].\n";
    //  echo "Port end longitude: ", $cfg->processing_parameters->port_end_longitude, "\n";
    //  exit(1);
    // }
    
    //if ($cfg->processing_parameters->port_end_latitude < -90 
    // || $cfg->processing_parameters->port_end_latitude > 90) {
    //  echo "Error: ", $cfgfile, " - Port end latitude must be within [-90,90].\n";
    //  echo "Port end latitude: ", $cfg->processing_parameters->port_end_latitude, "\n";
    //  exit(1);
    // }
    
    // Allowed uom for speed is 'm/s':
    if ($cfg->processing_parameters->speed_threshold->uom != "m/s") {
        echo "Error: ", $cfgfile,
            " - Allowed unit of measure for speed threshold is 'm/s'.\n";
        exit(1);
    }
    
    // Allowed value for speed is >0:
    if (!is_numeric($cfg->processing_parameters->speed_threshold->value) 
        || ($cfg->processing_parameters->speed_threshold->value <= 0)
    ) {
        echo "Error: ", $cfgfile, " - Speed threshold must be a number > 0.\n";
        exit(1);
    }
    
    // Allowed uom for acceleration is 'm/s^2':
    if ($cfg->processing_parameters->acceleration_threshold->uom != "m/s^2") {
        echo "Error: ", $cfgfile, 
            " - Allowed unit of measure for acceleration threshold is 'm/s^2'.\n";
        exit(1);
    }
    
    // Allowed value for acceleration is >0:
    if (!is_numeric($cfg->processing_parameters->acceleration_threshold->value) 
        || ($cfg->processing_parameters->acceleration_threshold->value <= 0)
    ) {
        echo "Error: ", $cfgfile, 
            " - Acceleration threshold must be a number > 0.\n";
        exit(1);
    }
    
    // Allowed uom for gap is 's':
    if ($cfg->processing_parameters->gap_threshold->uom != "s") {
        echo "Error: ", $cfgfile, 
            " - Allowed unit of measure for gap threshold is 's'.\n";
        exit(1);
    }
    
    // Allowed value for gap is >0:
    if (!is_numeric($cfg->processing_parameters->gap_threshold->value) 
        || ($cfg->processing_parameters->gap_threshold->value <= 0)
    ) {
        echo "Error: ", $cfgfile, " - Gap threshold must be a number > 0.\n";
        exit(1);
    }
    
    // Verify that rawdata directory exists:
    if (is_dir($cfg->directory_rawdata)) {
        $cfg->directory_rawdata = $cfg->directory_rawdata;
    } else {
        echo "Error: ", $cfgfile, " - Invalid rawdata path: ",
            $cfg->directory_rawdata, "\n";
        exit(1);
    }
    
    // If rawdata quality directory does not already exist, create it:
    if (is_dir($cfg->directory_rawdata_quality) 
        || @mkdir($cfg->directory_rawdata_quality, 0775, true)
    ) {
        $cfg->directory_rawdata_quality = $cfg->directory_rawdata_quality;
    } else {
        echo "Error: ", $cfgfile, " - Cannot create rawdata quality path: ", 
            $cfg->directory_rawdata_quality, "\n";
        exit(1);
    }
    
    // If products directory does not already exist, create it:
    if (is_dir($cfg->directory_products) 
        || @mkdir($cfg->directory_products, 0775, true)
    ) {
        $cfg->directory_products = $cfg->directory_products;
    } else {
        echo "Error: ", $cfgfile, " - Cannot create products path: ", 
            $cfg->directory_products, "\n";
        exit(1);
    }
    
    // Allowed values for nav_source are 0, 1, 2, 3, etc. (up to 9):
    if (!preg_match('/^[0-9]{1}$/', $cfg->nav_source)) {  
        echo "Error: ", $cfgfile, 
            " - nav_source must be 0, 1, 2, 3, etc. (0 is unknown, 1 is primary, 2 is secondary, etc.)\n";
        echo $cfg->nav_source, "\n";
        exit(1);
    }
    
} // end function validate_cfg( $cfg )


/**
 * Get the vessel, device, and fileset metadata from R2R database queries.
 *
 * @param string $cruiseid Cruise ID
 * @param object $cfg      Configuration object
 * 
 * @return object Returns $info object
 */
function getinfo($cruiseid, $cfg) 
{
    // Create objects:
    $info = new Info;
    $info->vessel = new Vessel;
    $info->device = new Device;
    
    $vessel = new Vessel;
    
    $device = new Device;
    $device->interface = new DeviceInterface;
    
    $deviceid = $cfg->R2R_device_id;
    
    // Open connection to r2r database:
    $db = @pg_connect(DATABASE);
    if (!$db) {
        echo "getinfo(): Database failed to connect: ", DATABASE, "\n";
        $template_info = make_template_info($cruiseid, $cfg);
        echo "getinfo(): Created template info object.\n";
        return $template_info;
    }
    
    // Query the database for the vessel name and reference frame definition:
    $query = "SELECT vessel.id, vessel.referenceframe FROM vessel INNER JOIN cruise ON vessel.id = cruise.vessel_id WHERE cruise.id = '$cruiseid'";
    $result_SELECT = pg_query($db, $query);
    if (!$result_SELECT) {
        echo "Query on vessel and cruise tables failed for ", $cruiseid, "\n"; 
        exit(1);
    }
    
    if (pg_num_rows($result_SELECT) == 0) {
        echo "0 records from vessel and cruise tables for ", $cruiseid, "\n";
        exit(1);
    } else {
        while ($row = pg_fetch_row($result_SELECT)) {
            $vessel->name = $row[0];
            $vessel->reference_frame_definition = $row[1];
        }
    }
    
    // Query the database for the device metadata:
    // TBD: R2R device_history table does not yet reflect design changes proposed
    // by SO and JM.
    //
    // Old method:
    //$query = "SELECT fileset.device_id,
    //   device.devicetype_id,
    //   device.make_id,
    //   device.model,
    //   device.serial,
    //   device.location_xyz,
    //   device_interface_map.datatype_id,
    //   fileformat.short_name,
    //   fileset.id
    //FROM device INNER JOIN fileset ON device.id = fileset.device_id
    //INNER JOIN fileformat ON fileformat.id = fileset.fileformat_id
    //INNER JOIN device_interface_map ON device_interface_map.device_id = device.id
    //AND device_interface_map.fileformat_id = fileset.fileformat_id
    //WHERE fileset.cruise_id  = '$cruiseid' and device.id = '$deviceid'";
    ////device_history.time,
    ////device_history.detail
    ////INNER JOIN device_history ON device_history.device_id = device.id
    //
    // New method (2011-07-20):
    //$query = "SELECT device.devicetype_id,
    //    device.make_id,
    //    device.model,
    //    device.serial,
    //    device.location_xyz,
    //    device_interface_map.datatype_id,
    //    fileformat.short_name,
    //    fileset.id,
    //    device_interface_map.device_id
    //    FROM device INNER JOIN device_interface_map ON 
    //    device_interface_map.device_id = device.id
    //    INNER JOIN fileset ON fileset.device_interface_map_id = 
    //    device_interface_map.id
    //    INNER JOIN fileformat ON device_interface_map.fileformat_id = 
    //    fileformat.id
    //    WHERE fileset.cruise_id  = '$cruiseid' and device.id = '$deviceid'";
    //
    // Latest method (2011-09-12):
    $query = "SELECT device_interface_map.device_id,
            model.devicetype_id,
            model.make_id,
            model.name,
            device.serial,
            device.location_xyz,
            device_interface_map.datatype_id,
            fileformat.short_name,
            fileset.id
            FROM device INNER JOIN device_interface_map ON device_interface_map.device_id = device.id
            INNER JOIN fileset ON fileset.device_interface_map_id = device_interface_map.id
            INNER JOIN fileformat ON device_interface_map.fileformat_id = fileformat.id
            INNER JOIN model ON model.id = device.model_id 
            WHERE fileset.cruise_id = '$cruiseid' and device.id = '$deviceid'";
    $result_SELECT = pg_query($db, $query);
    if (!$result_SELECT) {
        echo "Query on device and fileset tables where device.id='", 
            $deviceid, "' failed for ", $cruiseid, "\n"; 
        exit(1);
    }
    
    if (pg_num_rows($result_SELECT) == 0) {
        echo "0 records from device and fileset tables where device.id='", 
            $deviceid, "' for ", $cruiseid, "\n";
        exit(1);
    } else {
        while ($row = pg_fetch_row($result_SELECT)) {
            $device->R2R_device_id =$row[0];
            $device->type = $row[1];
            $device->make = $row[2];
            $device->model = $row[3];
            $device->serial_num = $row[4];
            $device->location_xyz = $row[5];
            $device->interface->data_type = $row[6];
            $device->interface->R2R_file_format = $row[7];
            $device->R2R_fileset_id = $row[8];
        }
    }
    
    // Free resultset:
    pg_free_result($result_SELECT);
    
    // Close connection to db:
    pg_close($db);
    
    $info->cruiseid = $cruiseid;
    $info->vessel = $vessel;
    $info->device = $device;
    
    // Serialize the object as JSON and pretty it up before printing.
    // Pretty-printing makes it easier for a human to modify the *info file.
    //  $info_json = json_format($info);
    //  $infofile = $cruiseid . "_" . $device->R2R_fileset_id . "_info.json";
    //  file_put_contents($infofile, $info_json);
    
    // Return the information object.
    return $info;
    
} // end function getinfo( $cruiseid )


/**
 * Build a template info object that will be returned to the caller
 * 
 * @param string $cruiseid Cruise ID
 * @param object $cfg      Configuration object
 *
 * @return object Returns template $info object
 */
function make_template_info($cruiseid, $cfg) 
{
    //  
    $vessel = new Vessel;
    $vessel->name = null;
    $vessel->reference_frame_definition = null;
    
    $device = new Device;
    $device->interface = new DeviceInterface;
    
    $device->R2R_device_id = $cfg->R2R_device_id;
    $device->type = null;
    $device->make = null;
    $device->model = null;
    $device->serial_num = null;
    $device->location_xyz = null;
    $device->interface->data_type = null;
    $device->interface->R2R_file_format = $cfg->R2R_file_format;
    $device->R2R_fileset_id = $cfg->R2R_fileset_id;
    
    $info = new Info;
    $info->vessel = new Vessel;
    $info->device = new Device;
    
    $info->cruiseid = $cruiseid;
    $info->vessel = $vessel;
    $info->device = $device;
    
    // Return the template info object.
    return $info;
    
} // end function make_template_info()
?>
