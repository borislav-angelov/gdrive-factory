<?php

include_once '../lib/GdriveClient.php';

$accessToken = '';

$client = new GdriveClient($accessToken);

$file = fopen('./bmw.jpg', 'rb');
$response = $client->uploadFile(array('title' => 'Car3.jpg'), $file, filesize('./bmw.jpg'));
fclose($file);

print_r($response);
