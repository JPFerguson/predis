<?php

/*
 * This file is part of the Predis package.
 *
 * (c) Daniele Alessandri <suppakilla@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Predis\Command;

/**
 * @link http://redis.io/topics/sentinel
 * @author Ville Mattila <ville@eventio.fi>
 */
class ServerSentinel extends AbstractCommand
{
    /**
     * {@inheritdoc}
     */
    public function getId()
    {
        return 'SENTINEL';
    }

    /**
     * {@inheritdoc}
     */
    public function parseResponse($data)
    {
        // TODO: The actual response parsing
        $args = array_change_key_case($this->getArguments(), CASE_LOWER);

        switch ($args[0]) {
            case 'get-master-addr-by-name':
            default:
                return $data;
        }
    }
}
