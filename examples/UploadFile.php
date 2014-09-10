<?php

include_once '../lib/GdriveClient.php';

$accessToken = 'ya29.ewCtW6c8loihzkUSwTn3W2SPzR68zbTx1LVVRKFVek7aOzM6vUIrkGMa';

$client = new GdriveClient($accessToken);

$file = fopen('./bmw.jpg', 'rb');
$response = $client->uploadFile('Car.jpg', $file, filesize('./bmw.jpg'));
fclose($file);

print_r($response);
