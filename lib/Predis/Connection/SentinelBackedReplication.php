<?php

/*
 * This file is part of the Predis package.
 *
 * (c) Daniele Alessandri <suppakilla@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Predis\Connection;

use Predis\Command\CommandInterface;
use Predis\Replication\ReplicationStrategy;

/**
 * @author Ville Mattila <ville@eventio.fi>
 */
class SentinelBackedReplication extends MasterSlaveReplication
{
    /**
     * Predis Client used to connect the sentinels.
     * 
     * @var \Predis\Client
     */
    protected $sentinelClient;

    /**
     * Name of the master (in sentinel configuration)
     */
    protected $sentinelMasterName;

    /**
     * @param array|string|ConnectionInterface $sentinelConnection Sentinel connection definition, anything that \Predis\Client accepts as constructor first argument
     * @param string                           $masterName         Sentinel master name
     * @param ReplicationStrategy              $strategy           ReplicationStrategy passed to MasterSlaveReplication
     */
    public function __construct($sentinelConnection, $masterName, ReplicationStrategy $strategy = null)
    {
        parent::__construct($strategy);

        // Creating connection to Sentinel
        $this->sentinelClient = new \Predis\Client($sentinelConnection);
        $this->sentinelMasterName = $masterName;
    }

    /**
     * 
     */
    protected function check()
    {
        // The actual master/slave configuration is queried from Sentinel
        $this->querySentinels();

        // Rest of checking from MasterSlaveReplication
        parent::check();
    }

    /**
     * This function makes a query to the configured Sentionels and receive the master & slave configuration
     */
    protected function querySentinels()
    {
        // Querying sentinels for master configuration
        $masterResult = $this->sentinelClient->sentinel('get-master-addr-by-name', $this->sentinelMasterName);
        $masterConnection = new StreamConnection(new ConnectionParameters(array(
            'host' => $masterResult[0],
            'port' => $masterResult[1],
            'alias' => 'master'
        )));
        
        $this->add($masterConnection);
        
        // Slave configuration
        $slavesResult = $this->sentinelClient->sentinel('slaves',$this->sentinelMasterName);
        foreach ($slavesResult as $slave) {
            $slaveConnection = new StreamConnection(new ConnectionParameters(array(
                'host' => $slave[3],
                'port' => $slave[5]
            )));
            
            $this->add($slaveConnection);
        }
    }
}
