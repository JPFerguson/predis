<?php

/*
 * This file is part of the Predis package.
 *
 * (c) Daniele Alessandri <suppakilla@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Predis\Connection\Aggregate;

use Predis\Command\CommandInterface;
use Predis\Command\ServerSentinel;
use Predis\Replication\ReplicationStrategy;
use Predis\Connection\Factory as ConnectionFactory;
use Predis\Connection\Parameters as ConnectionParameters;

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
     * @param array               $options             connection options usually passed to Predis/Client
     */
    public function __construct(array $sentinelConnections, $masterName, ReplicationStrategy $strategy = null, $options=null)
    {
        parent::__construct($strategy);

        $this->sentinelConnections = $sentinelConnections;
        $this->sentinelMasterName = $masterName;
        $this->connectionFactory = new ConnectionFactory();
        $this->connectionOptions = ($options) ? $options : array();
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

                $masterConnectionParams = $this->connectionOptions;
                $masterConnectionParams['host'] =  $masterResult[0];
                $masterConnectionParams['port'] =  $masterResult[1];
                $masterConnectionParams['alias'] =  'master';

                $this->addConnectionFromParams($masterConnectionParams);

                // Slave configuration
                $okSlaves = 0;
                $slavesResult = $sentinel->executeCommand($this->createSentinelCommand(array('slaves', $this->sentinelMasterName)));
                foreach ($slavesResult as $slave) {
                    $slave = $this->convertSlaveResponse($slave);

                    if ($this->shouldDiscardSlave($slave)) {
                        continue;
                    }

                    $slaveConnectionParams = $this->connectionOptions;
                    $slaveConnectionParams['host'] =  $slave['ip'];
                    $slaveConnectionParams['port'] =  $slave['port'];

                    $this->addConnectionFromParams($slaveConnectionParams);

                    $okSlaves++;
                }

                if (!$okSlaves) {
                    // If there is no connectable slaves, we'll add also master
                    // as a slave to support read queries.
                    unset($masterConnectionParams['alias']);
                    $this->addConnectionFromParams($masterConnectionParams);
                }

                break;
            } catch (\Predis\Connection\ConnectionException $exception) {
                $this->discardCurrentSentinel();
            }
        } while(true);
    }

    private function addConnectionFromParams($params) {
        $connection = $this->connectionFactory->create(new ConnectionParameters($params));

        $this->add($connection);
    }

    /**
     * This is a copy of ServerSentinel::processMastersOrSlaves.
     * It seems that no Command::parseResponse is used.
     */
    private function convertSlaveResponse($node) {
        $processed = array();
        $count = count($node);

        for ($i = 0; $i < $count; $i++) {
            $processed[$node[$i]] = $node[++$i];
        }

        return $processed;
    }

    /**
     * Decides whether this slave should be taken into configuration.
     */
    private function shouldDiscardSlave(array $slave) {
        $flags = explode(',', $slave['flags']);

        if (array_intersect($flags, array('s_down','disconnected'))) {
            return true;
        }
    }
}
