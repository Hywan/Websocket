<?php

/**
 * Hoa
 *
 *
 * @license
 *
 * New BSD License
 *
 * Copyright © 2007-2014, Ivan Enderlin. All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *     * Redistributions of source code must retain the above copyright
 *       notice, this list of conditions and the following disclaimer.
 *     * Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *     * Neither the name of the Hoa nor the names of its contributors may be
 *       used to endorse or promote products derived from this software without
 *       specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDERS AND CONTRIBUTORS BE
 * LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
 * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

namespace {

from('Hoa')

/**
 * \Hoa\Websocket\Exception
 */
-> import('Websocket.Exception.~')

/**
 * \Hoa\Websocket\Exception\BadProtocol
 */
-> import('Websocket.Exception.BadProtocol')

/**
 * \Hoa\Websocket\Node
 */
-> import('Websocket.Node')

/**
 * \Hoa\Websocket\Protocol\Rfc6455
 */
-> import('Websocket.Protocol.Rfc6455')

/**
 * \Hoa\Websocket\Protocol\Hybi00
 */
-> import('Websocket.Protocol.Hybi00')

/**
 * \Hoa\Socket\Connection\Handler
 */
-> import('Socket.Connection.Handler');

}

namespace Hoa\Websocket {

/**
 * Class \Hoa\Websocket\Connection.
 *
 * A cross-protocol Websocket connection.
 *
 * @author     Ivan Enderlin <ivan.enderlin@hoa-project.net>
 * @copyright  Copyright © 2007-2014 Ivan Enderlin.
 * @license    New BSD License
 */

abstract class Connection
    extends    \Hoa\Socket\Connection\Handler
    implements \Hoa\Core\Event\Listenable {

    /**
     * Opcode: continuation frame.
     *
     * @const int
     */
    const OPCODE_CONTINUATION_FRAME = 0x0;

    /**
     * Opcode: text frame.
     *
     * @const int
     */
    const OPCODE_TEXT_FRAME         = 0x1;

    /**
     * Opcode: binary frame.
     *
     * @const int
     */
    const OPCODE_BINARY_FRAME       = 0x2;

    /**
     * Opcode: connection close.
     *
     * @const int
     */
    const OPCODE_CONNECTION_CLOSE   = 0x8;

    /**
     * Opcode: ping.
     *
     * @const int
     */
    const OPCODE_PING               = 0x9;

    /**
     * Opcode: pong.
     *
     * @const int
     */
    const OPCODE_PONG               = 0xa;

    /**
     * Close: normal.
     *
     * @const int
     */
    const CLOSE_NORMAL              = 1000;

    /**
     * Close: going away.
     *
     * @const int
     */
    const CLOSE_GOING_AWAY          = 1001;

    /**
     * Close: protocol error.
     *
     * @const int
     */
    const CLOSE_PROTOCOL_ERROR      = 1002;

    /**
     * Close: data error.
     *
     * @const int
     */
    const CLOSE_DATA_ERROR          = 1003;

    /**
     * Close: status error.
     *
     * @const int
     */
    const CLOSE_STATUS_ERROR        = 1005;

    /**
     * Close: abnormal.
     *
     * @const int
     */
    const CLOSE_ABNORMAL            = 1006;

    /**
     * Close: message error.
     *
     * @const int
     */
    const CLOSE_MESSAGE_ERROR       = 1007;

    /**
     * Close: policy error.
     *
     * @const int
     */
    const CLOSE_POLICY_ERROR        = 1008;

    /**
     * Close: message too big.
     *
     * @const int
     */
    const CLOSE_MESSAGE_TOO_BIG     = 1009;

    /**
     * Close: extension missing.
     *
     * @const int
     */
    const CLOSE_EXTENSION_MISSING   = 1010;

    /**
     * Close: server error.
     *
     * @const int
     */
    const CLOSE_SERVER_ERROR        = 1011;

    /**
     * Close: TLS.
     *
     * @const int
     */
    const CLOSE_TLS                 = 1015;


    /**
     * Listeners.
     *
     * @var \Hoa\Core\Event\Listener object
     */
    protected $_on      = null;



    /**
     * Create a websocket connection.
     * 6 events can be listened: open, message, binary-message, ping, close and
     * error.
     *
     * @access  public
     * @param   \Hoa\Socket\Connection  $connection    Connection.
     * @return  void
     * @throw   \Hoa\Socket\Exception
     */
    public function __construct ( \Hoa\Socket\Connection $connection ) {

        parent::__construct($connection);
        $this->getConnection()->setNodeName('\Hoa\Websocket\Node');
        $this->_on = new \Hoa\Core\Event\Listener($this, array(
            'open',
            'message',
            'binary-message',
            'ping',
            'close',
            'error'
        ));

        return;
    }

    /**
     * Attach a callable to this listenable object.
     *
     * @access  public
     * @param   string  $listenerId    Listener ID.
     * @param   mixed   $callable      Callable.
     * @return  \Hoa\Websocket\Server
     * @throw   \Hoa\Core\Exception
     */
    public function on ( $listenerId, $callable ) {

        $this->_on->attach($listenerId, $callable);

        return $this;
    }

    /**
     * Run a node.
     *
     * @access  protected
     * @param   \Hoa\Socket\Node  $node    Node.
     * @return  void
     */
    protected function _run ( \Hoa\Socket\Node $node ) {

        try {

            if(FAILED === $node->getHandshake()) {

                $this->doHandshake();
                $this->_on->fire(
                    'open',
                    new \Hoa\Core\Event\Bucket()
                );

                return;
            }

            $frame = $node->getProtocolImplementation()->readFrame();

            if(false === $frame)
                return;

            $fromText   = false;
            $fromBinary = false;

            switch($frame['opcode']) {

                case self::OPCODE_BINARY_FRAME:
                    $fromBinary = true;

                case self::OPCODE_TEXT_FRAME:
                    if(0x1 === $frame['fin']) {

                        if(0 < $node->getNumberOfFragments()) {

                            $this->close(self::CLOSE_PROTOCOL_ERROR);

                            break;
                        }

                        if(true === $fromBinary) {

                            $fromBinary = false;
                            $this->_on->fire(
                                'binary-message',
                                new \Hoa\Core\Event\Bucket(array(
                                    'message' => $frame['message']
                                ))
                            );

                            break;
                        }

                        if(false === (bool) preg_match('//u', $frame['message'])) {

                            $this->close(self::CLOSE_MESSAGE_ERROR);

                            break;
                        }

                        $this->_on->fire(
                            'message',
                            new \Hoa\Core\Event\Bucket(array(
                                'message' => $frame['message']
                            ))
                        );

                        break;
                    }
                    else
                        $node->setComplete(false);

                    $fromText = true;

                case self::OPCODE_CONTINUATION_FRAME:
                    if(false === $fromText) {

                        if(0 === $node->getNumberOfFragments()) {

                            $this->close(self::CLOSE_PROTOCOL_ERROR);

                            break;
                        }
                    }
                    else {

                        $fromText = false;

                        if(true === $fromBinary) {

                            $node->setBinary(true);
                            $fromBinary = false;
                        }
                    }

                    $node->appendMessageFragment($frame['message']);

                    if(0x1 === $frame['fin']) {

                        $message  = $node->getFragmentedMessage();
                        $isBinary = $node->isBinary();
                        $node->clearFragmentation();

                        if(true === $isBinary) {

                            $this->_on->fire(
                                'binary-message',
                                new \Hoa\Core\Event\Bucket(array(
                                    'message' => $message
                                ))
                            );

                            break;
                        }

                        if(false === (bool) preg_match('//u', $message)) {

                            $this->close(self::CLOSE_MESSAGE_ERROR);

                            break;
                        }

                        $this->_on->fire(
                            'message',
                            new \Hoa\Core\Event\Bucket(array(
                                'message' => $message
                            ))
                        );
                    }
                    else
                        $node->setComplete(false);
                  break;

                case self::OPCODE_PING:
                    $message = &$frame['message'];

                    if(   0x0  === $frame['fin']
                       || 0x7d  <  $frame['length']) {

                        $this->close(self::CLOSE_PROTOCOL_ERROR);

                        break;
                    }

                    $node->getProtocolImplementation()
                         ->writeFrame(
                             $message,
                             self::OPCODE_PONG,
                             true
                         );

                    $this->_on->fire(
                        'ping',
                        new \Hoa\Core\Event\Bucket(array(
                            'message' => $message
                        ))
                    );
                  break;

                case self::OPCODE_PONG:
                    if(0 === $frame['fin']) {

                        $this->close(self::CLOSE_PROTOCOL_ERROR);

                        break;
                    }
                  break;

                case self::OPCODE_CONNECTION_CLOSE:
                    $length = &$frame['length'];

                    if(   1    === $length
                       || 0x7d  <  $length) {

                        $this->close(self::CLOSE_PROTOCOL_ERROR);

                        break;
                    }

                    $code   = self::CLOSE_NORMAL;
                    $reason = null;

                    if(0 < $length) {

                        $message = &$frame['message'];
                        $_code   = unpack('nc', substr($message, 0, 2));
                        $code    = &$_code['c'];

                        if(   1000  >  $code
                           || (1004 <= $code && $code <= 1006)
                           || (1012 <= $code && $code <= 1016)
                           || 5000  <= $code) {

                            $this->close(self::CLOSE_PROTOCOL_ERROR);

                            break;
                        }

                        if(2 < $length) {

                            $reason = substr($message, 2);

                            if(false === (bool) preg_match('//u', $reason)) {

                                $this->close(self::CLOSE_MESSAGE_ERROR);

                                break;
                            }
                        }
                    }

                    $this->close(self::CLOSE_NORMAL);
                    $this->_on->fire(
                        'close',
                        new \Hoa\Core\Event\Bucket(array(
                            'code'   => $code,
                            'reason' => $reason
                        ))
                    );
                  break;

                default:
                    $this->close(self::CLOSE_PROTOCOL_ERROR);
            }
        }
        catch ( \Hoa\Core\Exception\Idle $e ) {

            try {

                $this->close(self::CLOSE_SERVER_ERROR);
                $exception = $e;
            }
            catch ( \Hoa\Core\Exception\Idle $ee ) {

                $this->getConnection()->disconnect();
                $exception = new \Hoa\Core\Exception\Group(
                    'An exception has been thrown. We have tried to close ' .
                    'the connection but another exception has been thrown.', 42
                );
                $exception[] = $e;
                $exception[] = $ee;
            }

            $this->_on->fire('error', new \Hoa\Core\Event\Bucket(array(
                'exception' => $exception
            )));
        }

        return;
    }

    /**
     * Try the handshake by trying different protocol implementation.
     *
     * @access  protected
     * @return  void
     * @throw   \Hoa\Websocket\Exception\BadProtocol
     */
    abstract protected function doHandshake ( );

    /**
     * Send a message.
     *
     * @access  protected
     * @param   string            $message    Message.
     * @param   \Hoa\Socket\Node  $node       Node.
     * @return  \Closure
     */
    protected function _send ( $message, \Hoa\Socket\Node $node ) {

        return function ( $opcode, $end ) use ( &$message, $node ) {

            return $node->getProtocolImplementation()
                        ->send($message, $opcode, $end);
        };
    }

    /**
     * Send a message to a specific node/connection.
     *
     * @access  public
     * @param   string            $message    Message.
     * @param   \Hoa\Socket\Node  $node       Node (if null, current node).
     * @param   int               $opcode     Opcode.
     * @param   bool              $end        Whether it is the last frame of
     *                                        the message.
     * @return  void
     */
    public function send ( $message, \Hoa\Socket\Node $node = null,
                           $opcode = self::OPCODE_TEXT_FRAME, $end = true ) {

        $send = parent::send($message, $node);

        if(null === $send)
            return null;

        return $send($opcode, $end);
    }

    /**
     * Close a specific node/connection.
     * It is just a “inline” method, a shortcut.
     *
     * @access  public
     * @param   int     $code      Code (please, see
     *                             self::CLOSE_* constants).
     * @param   string  $reason    Reason.
     * @return  void
     */
    public function close ( $code = self::CLOSE_NORMAL, $reason = null ) {

        $connection = $this->getConnection();
        $protocol   = $connection->getCurrentNode()->getProtocolImplementation();

        if(null !== $protocol)
            $protocol->close($code, $reason);

        return $connection->disconnect();
    }
}

}
