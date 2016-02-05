<?php
namespace Virge\Graphite\Command;

use Virge\Cli;
use Virge\Graphite\Component\Task;
use Virge\Graphite\Service\QueueService;
use Virge\Graphite\Worker;
use Virge\Virge;

/**
 * 
 * @author Michael Kramer
 */
class WorkerCommand {
    
    const COMMAND = 'virge:graphite:worker';
    
    public function run($queueName = 'graphite_queue') {
        
        $this->getQueueService()->listen($queueName, function(Task $task) {
            //dispatch this task
            Worker::dispatch($task);
        });
    }
    
    /**
     * @return QueueService
     */
    protected function getQueueService() {
        return Virge::service(QueueService::SERVICE_ID);
    }
}