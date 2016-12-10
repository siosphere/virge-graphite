<?php
namespace Virge\Graphite\Service;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

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
     * @var \DateTime
     */
    protected $lastKeepAliveCallback;

    /**
     * @var callable
     */
    protected $keepAliveCallback;

    public function setKeepAliveCallback($callback)
    {
        $this->keepAliveCallback = $callback;
    }
    
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
            $this->keepAliveCallback();
            $task = unserialize($message->body);
            call_user_func($callback, $task);
            
            $this->complete($message);
        });
        while(count($this->getChannel()->callbacks)) {
            try {
                $this->getChannel()->wait();
            } catch (\PhpAmqpLib\Exception\AMQPTimeoutException $timeout) {
                echo "TIMEOUT EXCEPTION: \n";
                echo $timeout->getMessage() . "\n\n";
            }
        }
    }

    public function keepAliveCallback()
    {
        if(!$this->keepAliveCallback) {
            return;
        }

        $now = new \DateTime;
        if($this->lastKeepAliveCallback && intval($this->lastKeepAliveCallback->diff($now)->format('%s')) - $this->lastKeepAliveCallback <= 10) {
            return;
        }

        $this->lastKeepAliveCallback = $now;
        call_user_func($this->keepAliveCallback);
    }
    
    /**
     * Mark the message as completed
     * @param type $message
     */
    public function complete($message) {
        $message->delivery_info['channel']->basic_ack($message->delivery_info['delivery_tag']);
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
        
        $host = Config::get('queue', 'host');
        $port = Config::get('queue', 'port');
        $user = Config::get('queue', 'user');
        $pass = COnfig::get('queue', 'pass');
        
        return $this->connection = new AMQPStreamConnection($host, $port, $user, $pass);
    }
    
    /**
     * @return array
     */
    protected function getMessageProperties() {
        return [
            'delivery_mode' => 2
        ];
    }
}