<?php

include_once '../lib/GdriveClient.php';

$accessToken = '';

$client = new GdriveClient($accessToken);
$response = $client->metadata('/Photos');

print_r($response);
