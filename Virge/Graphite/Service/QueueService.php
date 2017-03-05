<?php
namespace Virge\Graphite\Service;

use Virge\Cli;
use Virge\Core\Config;
use Virge\Graphite\Component\Task;

/**
 * 
 * @author Michael Kramer
 */
class QueueService {
    
    const SERVICE_ID = 'graphite.service.queue';
    const DEFAULT_EXCHANGE = 'virge_graphite';
    
    
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

    /**
     * @param string $queue
     * @param Task $task
     */
    public function push($queueName, Task $task, $exchangeName = self::DEFAULT_EXCHANGE) {
        $serializedTask = serialize($task);
        
        $message = $serializedTask;

        $ex = $this->getExchange($exchangeName);

        $queue = $this->declareQueue($queueName, $exchangeName);
        
        $success = $ex->publish($message, $queueName, AMQP_MANDATORY, [
            'delivery_mode' => 2,
        ]);

        $this->close();
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

        //close out our queue connections on shutdown
        register_shutdown_function(function() use ($queue, $consumerTag) {
            $queue->cancel($consumerTag);
            $this->close();
        });

        try {
            $queue->consume(function(\AMQPEnvelope $message, \AMQPQueue $q) use($callback) {
                try {
                    $task = unserialize($message->getBody());
                    call_user_func($callback, $task);
                } catch(\Throwable $err) {
                    Cli::output($err->getMessage());
                }
                return true;
            }, AMQP_AUTOACK, $consumerTag);
        } catch ( \AMQPQueueException $ex) {
            Cli::output($ex->getMessage());

        } catch (\Throwable $t) {

            $queue->cancel($consumerTag);
            $this->close();

            throw $t;
        }

        $queue->cancel($consumerTag);
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
        $pass = COnfig::get('queue', 'pass');
        
        $this->connection = new \AMQPConnection([
            'host' => $host,
            'port' => $port,
            'login' => $user,
            'password' => $pass,
        ]);

        $this->connection->connect();

        return $this->connection;
    }

    public function close()
    {
        $this->getConnection()->disconnect();
        unset($this->connection);
        unset($this->channel);
    }
}