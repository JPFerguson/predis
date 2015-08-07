<?php
/**
 * This is a temporary development test script used in Sentinel support development
 */
 
require 'src/Autoloader.php';

\Predis\Autoloader::register();

$sentinelConnections = array('tcp://127.0.0.1:26379', 'tcp://127.0.0.1:26380');
shuffle($sentinelConnections);

$sentinel = new \Predis\Connection\Aggregate\SentinelBackedReplication($sentinelConnections,'mymaster');
$client = new \Predis\Client($sentinel);

echo "Slave: " . $client->get('test') . "\n";
$client->set('test',time());
echo "Master: " .  $client->get('test') . "\n";

exit;