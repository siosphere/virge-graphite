<?php
namespace Virge\Graphite\Service;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Message\AMQPMessage;

use Virge\Cli;
use Virge\Core\Config;
use Virge\Graphite\Component\Task;

/**
 * 
 * @author Michael Kramer
 */
class QueueService {
    
    const SERVICE_ID = 'graphite.service.queue';
    
    protected $channel;
    
    /**
     * @var AMQPStreamConnection 
     */
    protected $connection;

    /**
     * @param string $queue
     * @param Task $task
     */
    public function push($queue, Task $task) {
        $serializedTask = serialize($task);
        
        $this->declareQueue($queue);
        
        $message = new AMQPMessage($serializedTask, $this->getMessageProperties());
        $this->getChannel()->basic_publish($message, '', $queue);
    }

    /**
     * Get a task from the queue and dispatch it
     * @param type $queue
     */
    public function listen($queue, $callback) {
        $this->declareQueue($queue);
        
        $this->getChannel()
            ->basic_qos(null, 1, null);

        $this->getChannel()->basic_consume($queue, '', false, false, false, false, function($message) use($callback) {
            try {
                $task = unserialize($message->body);
                call_user_func($callback, $task);
                $this->complete($message);
            } catch(\Throwable $err) {
                Cli::output($err->getMessage());
                $this->reject($message);
            }
        });
        
        while(count($this->getChannel()->callbacks)) {
            try {
                $this->getChannel()->wait();
            } catch (AMQPTimeoutException $timeout) {
                echo "TIMEOUT EXCEPTION: \n";
                echo $timeout->getMessage() . "\n\n";
                break;
            } catch(\Throwable $err) {
                Cli::output($err->getMessage());
                die();
            }
        }
        //always retry
        $this->close();
        $this->listen($queue, $callback);
    }

    /**
     * Mark the message as completed
     * @param type $message
     */
    public function complete($message) {
        $message->delivery_info['channel']->basic_ack($message->delivery_info['delivery_tag']);
    }

    public function reject($message)
    {
        return $message->delivery_info['channel']->basic_nack($message->delivery_info['delivery_tag']);
    }
    
    protected function declareQueue($queue) {
        $this->getChannel()
            ->queue_declare($queue, false, true, false, false);
    }
    
    /**
     * @return AMQPChannel
     */
    protected function getChannel() {
        if($this->channel) {
            return $this->channel;
        }
        
        return $this->channel = $this->getConnection()->channel();
    }
    
    /**
     * @return AMQPStreamConnection
     */
    protected function getConnection() {
        if(isset($this->connection)) {
            return $this->connection;
        }

        //close out our queue connections on shutdown
        register_shutdown_function(function() {
            $this->close();
        });
        
        $host = Config::get('queue', 'host');
        $port = Config::get('queue', 'port');
        $user = Config::get('queue', 'user');
        $pass = COnfig::get('queue', 'pass');
        
        return $this->connection = new AMQPStreamConnection($host, $port, $user, $pass);
    }

    public function close()
    {
        $this->getChannel()->close();
        $this->getConnection()->close();
        unset($this->connection);
        unset($this->channel);
    }
    
    /**
     * @return array
     */
    protected function getMessageProperties() {
        return [
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT
        ];
    }
}