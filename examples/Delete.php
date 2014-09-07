<?php

include_once '../lib/GdriveClient.php';

$accessToken = '';

$client = new GdriveClient($accessToken);
$response = $client->delete('fileId');

print_r($response);
