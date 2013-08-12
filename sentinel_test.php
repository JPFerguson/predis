<?php
/**
 * This is a temporary development test script used in Sentinel support development
 */
 
require 'lib/Predis/Autoloader.php';

\Predis\Autoloader::register();

$sentinelConnections = array('tcp://127.0.0.1:26379', 'tcp://127.0.0.1:26380');
shuffle($sentinelConnections);

$sentinel = new \Predis\Connection\SentinelBackedReplication($sentinelConnections,'mymaster');
$client = new \Predis\Client($sentinel);

echo $client->get('test');   // From slave
$client->set('test',time()); // Master
echo $client->get('test');   // Master

exit;