<?php

include_once '../lib/GdriveClient.php';

$accessToken = '';

$client = new GdriveClient($accessToken);

$file = fopen('./Car.jpg', 'wb');
$response = $client->getFile('fileId', $file);
fclose($file);

print_r($response);
