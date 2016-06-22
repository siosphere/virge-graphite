<?php
namespace Virge\Graphite\Command;

use Virge\Cli;
use Virge\Cli\Component\Process;
use Virge\Core\Config;
use Virge\Graphite\Component\Task;
use Virge\Graphite\Service\QueueService;
use Virge\Graphite\Worker;
use Virge\Virge;

/**
 * 
 * @author Michael Kramer
 */
class SupervisorCommand
{
    
    const COMMAND = 'virge:graphite:supervisor';
    protected $queueName;
    protected $workers = [];
    
    public function run($queueName = 'graphite_queue', $totalWorkers = 3) 
    {
        $this->queueName = $queueName;
        while(true) {
            $workers = $this->filterWorkers();
            if(count($workers) < $totalWorkers) {
                $this->startWorkers($totalWorkers - count($workers));
            }
            sleep(1);
        }
    }

    public function startWorkers($numToStart)
    {
        $vadmin = Config::get('base_path') . 'vadmin';

        for($i = 0; $i <= $numToStart; $i++) {
            $this->workers[] = new Process(sprintf("php -f %s virge:graphite:worker %s", $vadmin, $this->queueName));
        }
    }

    public function filterWorkers()
    {
        return $this->workers = array_filter($this->workers, function($worker) {
            !$worker->isFinished();
        });
    }
}