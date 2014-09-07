<?php

include_once '../lib/GdriveClient.php';

$accessToken = '';

$client = new GdriveClient($accessToken);

$file = fopen('./bmw.jpg', 'rb');
$response = $client->uploadFile('/Photos/Car.jpg', $file );
fclose($file);

print_r($response);
