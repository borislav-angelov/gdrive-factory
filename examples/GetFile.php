<?php

include_once '../lib/GdriveClient.php';

$accessToken = '';

$client = new GdriveClient($accessToken);

$file = fopen('./Car.jpg', 'wb');
$response = $client->getFile('/Photos/BMW.jpg', $file);
fclose($file);

print_r($response);
