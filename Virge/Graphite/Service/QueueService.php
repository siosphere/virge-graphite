<?php
namespace Virge\Graphite\Service;

use Virge\Cli;
use Virge\Core\Config;
use Virge\Graphite\Component\Task;
use Virge\Graphite\Service\Provider\{
    AbstractProvider,
    RabbitmqProvider,
    SqsProvider
};

/**
 * 
 * @author Michael Kramer
 */
class QueueService {
    
    const SERVICE_ID = 'graphite.service.queue';

    const PROVIDER_RABBITMQ = 'rabbitmq';
    const PROVIDER_SQS = 'sqs';

    public function __construct(string $providerType = self::PROVIDER_RABBITMQ)
    {
        switch($providerType)
        {
            case self::PROVIDER_RABBITMQ:
                $this->provider = new RabbitmqProvider();
                break;
            case self::PROVIDER_SQS:
                $this->provider = new SqsProvider();
                break;
            default:
                throw new \Exception("Invalid provider: " . $providerType);
        }
    }

    /**
     * @param string $queue
     * @param Task $task
     */
    public function push($queueName, Task $task) : bool
    {
        return $this->getProvider()->push($queueName, $task);
    }

    /**
     * Get a task from the queue and dispatch it
     * @param type $queue
     */
    public function listen($queueName, $callback) 
    {
        return $this->getProvider()->listen($queueName, $callback);
    }

    protected function getProvider()
    {
        return $this->provider;
    }
}