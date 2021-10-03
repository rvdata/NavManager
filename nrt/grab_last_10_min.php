<?php

$date = date('Y-m-d H:i:s', strtotime("-2 minutes"));

#$api_base = 'https://coriolix.ceoas.oregonstate.edu/oceanus/api/decimateData/';
$api_base = 'https://datapresence.ceoas.oregonstate.edu/demo/api/decimateData/';

$api_request_data = array(
   'model' => 'GnssGgaBow',
   'date_0' => $date,
   'decfactr' => '1',
   'format' => 'json'
);

$url = $api_base;

foreach ($api_request_data as $key => $value) {
   
}
$str = "https://coriolix.ceoas.oregonstate.edu/oceanus/api/decimateData/?model=GnssGgaBow&date_0=2021-05-07 01:13:40.996+00&decfactr=1&format=json&_=1620350005239";

echo $url . "\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_URL, $url);
$result = curl_exec($ch);
curl_close($ch);


$obj = json_decode($result);

var_dump($result);

?>
