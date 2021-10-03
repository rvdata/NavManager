<?php


include('../include/globals.inc.php');
include('../include/navqa.inc.php');

# Constants: TODO clean up later

$tempfile = 'tmp.r2rnav';
$delimiter = "\t";
$empty_data = "NAN";
$verbose = TRUE;
$speedHoriMax = MAX_SPEED;
$accelHoriMax = MAX_ACCEL;
$gapThreshold = MAX_GAP;


unlink($tempfile);

# Grab data from the api - for now we'll just use a static file so we don't
# keep hitting their api every time we test

$json_data = json_decode(file_get_contents('demo_data.json'), true);

$firstLine = $json_data[0];
$dateStringUTCStart = $firstLine['datetime'];
$portLongitudeStart = $firstLine['longitude'];
$portLatitudeStart = $firstLine['latitude'];

$index_last = count($json_data)-1;
$lastLine = $json_data[$index_last];
$dateStringUTCEnd = $lastLine['datetime'];
$portLongitudeEnd = $lastLine['longitude'];
$portLatitudeEnd = $lastLine['latitude'];

$tmp_handle = fopen($tempfile, 'w');

foreach ($json_data as $datagram) {

   $data['datetime'] = $datagram['datetime'];
   $data['longitude'] = $datagram['longitude'];
   $data['latitude'] = $datagram['latitude'];
   $data['gps_quality'] = $empty_data;
   $data['number_satellites'] = $empty_data;
   $data['hdop'] = $empty_data;
   $data['antenna_height'] = $empty_data;

   $data_line = implode($delimiter, $data)."\n";

   #print($data_line);

   fwrite($tmp_handle, $data_line);
}

fclose($tmp_handle);

if ($verbose) {
   echo "\n";
   echo "Running navqa() with:\n";
   echo "\tInput file:               ", $tempfile, "\n";
   echo "\tStart:                    ", $dateStringUTCStart, "\n";
   echo "\tEnd:                      ", $dateStringUTCEnd, "\n";
   echo "\tSpeed threshold [m/s]:    ", $speedHoriMax, "\n";
   echo "\tAccel threshold [m/s^2]:  ", $accelHoriMax, "\n";
   echo "\tGap threshold [s]:        ", $gapThreshold, "\n";
   echo "\tDeparture Port Longitude: ", $portLongitudeStart, "\n";
   echo "\tDeparture Port Latitude:  ", $portLatitudeStart, "\n";
   echo "\tArrival Port Longitude:   ", $portLongitudeEnd, "\n";
   echo "\tArrival Port Latitude:    ", $portLatitudeEnd, "\n";
   echo "\n";
}

$qaNavigationRaw = @navqa(
   $tempfile, $dateStringUTCStart, $dateStringUTCEnd,
   $speedHoriMax, $accelHoriMax, $gapThreshold,
   $portLongitudeStart, $portLatitudeStart,
   $portLongitudeEnd, $portLatitudeEnd,
   $fqalog
);

var_dump($qaNavigationRaw);

#unlink($tmp_handle);

?>
