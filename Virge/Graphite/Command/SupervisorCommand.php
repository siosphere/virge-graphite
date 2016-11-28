<?php
namespace Virge\Graphite\Command;

use Virge\Cli;
use Virge\Cli\Component\Command;
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
class SupervisorCommand extends Command
{
    
    const COMMAND = 'virge:graphite:supervisor';
    protected $queueName;
    protected $workers = [];
    protected $totalWorkers = 3;
    protected $queueUpCommand = "virge:graphite:worker";
    
    public function run($queueName = 'graphite_queue', $totalWorkers = 3) 
    {
        
        if($this->instanceAlreadyRunning([$queueName])) {
            $this->terminate();
        }
        $this->totalWorkers = $totalWorkers;
        $this->queueName = $queueName;

        while(true) {
            $this->tick();
            sleep(1);
        }
    }

    public function tick()
    {
        $workers = $this->filterWorkers();
        if(count($workers) < $this->totalWorkers) {
            $this->startWorkers($this->totalWorkers - count($workers));
        }
    }

    public function startWorkers($numToStart)
    {
        $vadmin = Config::get('base_path') . 'vadmin';

        for($i = 0; $i < $numToStart; $i++) {
            $this->workers[] = new Process(sprintf("php -f %s %s %s", $vadmin, $this->queueUpCommand, $this->queueName));
        }
    }

    public function filterWorkers()
    {
        return $this->workers = array_filter($this->workers, function($worker) {
            return !$worker->isFinished();
        });
    }
}   