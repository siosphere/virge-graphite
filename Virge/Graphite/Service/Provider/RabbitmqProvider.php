<?php
namespace Virge\Graphite\Service\Provider;

use Virge\Cli;
use Virge\Core\Config;
use Virge\Graphite\Component\Task;

/**
 * 
 * @author Michael Kramer
 */
class RabbitmqProvider extends AbstractProvider
{
    
    const DEFAULT_EXCHANGE = 'virge_graphite';
    
    public static $maxSocketFailures = 10;
    
    /**
     * @var \AMQPConnection 
     */
    protected $connection;

    /**
     * @var \AMQPChannel
     */
    protected $channel;

    /**
     * @var string
     */
    protected $consumerTag;

    protected $numSocketFailures = 0;

    /**
     * @param string $queue
     * @param Task $task
     */
    public function push($queueName, Task $task, $exchangeName = self::DEFAULT_EXCHANGE) : bool
    {
        $serializedTask = serialize($task);
        
        $message = $serializedTask;

        $ex = $this->getExchange($exchangeName);

        $queue = $this->declareQueue($queueName, $exchangeName);
        
        $success = $ex->publish($message, $queueName, AMQP_MANDATORY, [
            'delivery_mode' => 2,
        ]);

        $this->close();

        return $success;
    }

    public function getChannel() : \AMQPChannel
    {
        if(isset($this->channel)) {
            return $this->channel;
        }

        return $this->channel = new \AMQPChannel($this->getConnection());
    }

    public function getExchange($exchangeName)
    {
        $ex = new \AMQPExchange($this->getChannel());
        $ex->setName($exchangeName);
        $ex->setType(AMQP_EX_TYPE_DIRECT);
        $ex->setFlags(AMQP_DURABLE);
        $ex->declareExchange();

        return $ex;
    }

    /**
     * Get a task from the queue and dispatch it
     * @param type $queue
     */
    public function listen($queueName, $callback, $exchangeName = self::DEFAULT_EXCHANGE) 
    {
        $ex = $this->getExchange($exchangeName);

        $queue = $this->declareQueue($queueName, $exchangeName);

        $consumerTag = $this->getConsumerTag();

        try {
            $queue->consume(function(\AMQPEnvelope $message, \AMQPQueue $q) use($callback) {
                try {
                    $task = unserialize($message->getBody());
                    call_user_func($callback, $task);
                    $q->ack($message->getDeliveryTag());
                } catch(\Throwable $err) {
                    Cli::output($err->getMessage());
                    $q->nack($message->getDeliveryTag());
                }
                return true;
            }, AMQP_NOPARAM, $consumerTag);
        } catch ( \AMQPQueueException $ex) {
            Cli::output($ex->getMessage());

        } catch(\AMQPException $ex) {
            $this->close();

            if($this->numSocketFailures++ > static::$maxSocketFailures) {
                throw $ex;
            }
            
            //try to reconnect
            $this->listen($queueName, $callback, $exchangeName);
        } catch(\AMQPConnectionException $ex) {
            $this->reset();

            if($this->numSocketFailures++ > static::$maxSocketFailures) {
                throw $ex;
            }

            //try to reconnect
            $this->listen($queueName, $callback, $exchangeName);

        } catch (\Throwable $t) {
            Cli::output($t->getMessage());
            $this->close();

            throw $t;
        }

        $this->close();

    }

    protected function getConsumerTag()
    {
        if(isset($this->consumerTag)) {
            return $this->consumerTag;
        }

        return $this->consumerTag = gethostname() . ':' . getmypid();
    }

    protected function declareQueue($queueName, $exchangeName) : \AMQPQueue
    {
        $queue = new \AMQPQueue($this->getChannel());
        $queue->setName($queueName);
        $queue->setFlags(AMQP_DURABLE);
        $queue->declareQueue();
        $queue->bind($exchangeName, $queueName);

        return $queue;
    }
    
    /**
     * @return AMQPStreamConnection
     */
    protected function getConnection() : \AMQPConnection
    {
        if(isset($this->connection)) {
            return $this->connection;
        }

        $host = Config::get('queue', 'host');
        $port = Config::get('queue', 'port');
        $user = Config::get('queue', 'user');
        $pass = Config::get('queue', 'pass');
        $heartbeat = Config::get('queue', 'heartbeat') ?? 5;
        $vhost = Config::get('queue', 'vhost');
        $vhost = $vhost ? $vhost : null;


        $this->connection = new \AMQPConnection([
            'host' => $host,
            'port' => $port,
            'login' => $user,
            'password' => $pass,
            'heartbeat' => $heartbeat,
            'vhost' => $vhost,
        ]);

        $this->connection->connect();

        return $this->connection;
    }

    public function close()
    {
        $this->getConnection()->disconnect();
        $this->reset();
    }

    public function reset()
    {
        unset($this->connection);
        unset($this->channel);
    }
}