<?php
/**
 * This is a temporary development test script used in Sentinel support development
 */
 
require 'lib/Predis/Autoloader.php';

\Predis\Autoloader::register();

$sentinel = new \Predis\Connection\SentinelBackedReplication(array('tcp://127.0.0.1:6380'),'mymaster');
$client = new \Predis\Client($sentinel);

echo $client->get('test');   // From slave
$client->set('test',time()); // Master
echo $client->get('test');   // Master

exit;