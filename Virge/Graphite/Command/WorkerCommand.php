<?php
namespace Virge\Graphite\Command;

use Virge\Cli;
use Virge\Cli\Component\{
    Command,
    Input
};
use Virge\Graphite\Component\Task;
use Virge\Graphite\Service\QueueService;
use Virge\Graphite\Worker;
use Virge\Virge;

/**
 * 
 * @author Michael Kramer
 */
class WorkerCommand extends Command
{
    
    const COMMAND = 'virge:graphite:worker';
    const COMMAND_HELP = 'Worker that will process the given queue';
    const COMMAND_USAGE = 'virge:graphite:worker <queueName>';
    
    public function run(Input $input) {
        $queueName = $input->getArgument(0) ?? 'graphite_queue';

        Cli::important(sprintf("Started worker for: " . $queueName));
        
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