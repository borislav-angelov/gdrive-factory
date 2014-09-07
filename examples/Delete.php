<?php

include_once '../lib/GdriveClient.php';

$accessToken = '';

$client = new GdriveClient($accessToken);
$response = $client->delete('/Migrations');

print_r($response);
