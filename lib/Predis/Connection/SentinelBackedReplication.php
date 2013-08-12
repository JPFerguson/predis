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
use Predis\Command\ServerSentinel;
use Predis\Replication\ReplicationStrategy;

/**
 * @author Ville Mattila <ville@eventio.fi>
 */
class SentinelBackedReplication extends MasterSlaveReplication
{
    /**
     * Sentinel connections definition
     */
    protected $sentinelConnections;

    /**
     * Name of the master (in sentinel configuration)
     */
    protected $sentinelMasterName;

    /**
     * The current sentinel connection instance
     *
     * @var SingleConnectionInterface
     */
    protected $currentSentinelConnection;

    /**
     * @var ConnectionFactory
     */
    protected $connectionFactory;

    /**
     * @param array               $sentinelConnections Sentinel connections definition
     * @param string              $masterName          Sentinel master name
     * @param ReplicationStrategy $strategy            ReplicationStrategy passed to MasterSlaveReplication
     */
    public function __construct(array $sentinelConnections, $masterName, ReplicationStrategy $strategy = null)
    {
        parent::__construct($strategy);

        $this->sentinelConnections = $sentinelConnections;
        $this->sentinelMasterName = $masterName;
        $this->connectionFactory = new ConnectionFactory();
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
     * Returns the current sentinel connection or builds a new, if none
     * is currently active.
     *
     * @return SingleConnectionInterface
     */
    private function getSentinelConnection()
    {
        if (null === $this->currentSentinelConnection) {
            // In case there is no more sentinel connections, we'll throw
            // an exception
            if (count($this->sentinelConnections) < 1) {
                throw new \Predis\ClientException('No working sentinels.');
            }

            // Otherwise, shifting one connection definition from the stack
            $connectionDef = array_shift($this->sentinelConnections);
            $this->currentSentinelConnection = $this->connectionFactory->create($connectionDef);
        }

        return $this->currentSentinelConnection;
    }
    
    /**
     * Discards the current sentinel connection
     */
    private function discardCurrentSentinel()
    {
        trigger_error('Sentinel connection ' . $this->currentSentinelConnection . ' failed, discarding.');
        $this->currentSentinelConnection = null;
    }
    
    /**
     * Creates a new ServerSentinel instance with given arguments.
     */
    private function createSentinelCommand($arguments = array()) {
        $command = new ServerSentinel();
        $command->setArguments($arguments);
        return $command;
    }

    /**
     * This function makes a query to the configured sentinels. The query loops through 
     */
    protected function querySentinels()
    {
        do {
            $sentinel = $this->getSentinelConnection();

            try {
                // Querying sentinels for master configuration
                $masterResult = $sentinel->executeCommand($this->createSentinelCommand(array('get-master-addr-by-name', $this->sentinelMasterName)));
                $masterConnection = $this->connectionFactory->create(new ConnectionParameters(array(
                    'host' => $masterResult[0],
                    'port' => $masterResult[1],
                    'alias' => 'master'
                )));

                $this->add($masterConnection);

                // Slave configuration
                $slavesResult = $sentinel->executeCommand($this->createSentinelCommand(array('slaves', $this->sentinelMasterName)));
                foreach ($slavesResult as $slave) {
                    $slaveConnection = $this->connectionFactory->create(new ConnectionParameters(array(
                        'host' => $slave[3],
                        'port' => $slave[5]
                    )));

                    $this->add($slaveConnection);
                }

                break;
            } catch (\Predis\Connection\ConnectionException $exception) {
                $this->discardCurrentSentinel();
            }
        } while(true);
    }
}
