<?php
namespace Virge\Graphite\Command;

use Virge\Cli;
use Virge\Cli\Component\{
    Command,
    Input,
    Process
};
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
    const COMMAND_HELP = 'Start a number of workers against a given queue';
    const COMMAND_USAGE = 'virge:graphite:supervisor <queueName> <totalWorkers>';


    protected $queueName;
    protected $workers = [];
    protected $totalWorkers = 3;
    protected $queueUpCommand = "virge:graphite:worker";
    
    public function run(Input $input) 
    {
        $queueName = $input->getArgument(0) ?? 'graphite_queue';
        $totalWorkers = $input->getArgument(1) ?? 3;

        if($this->instanceAlreadyRunning([$queueName])) {
            Cli::error('Instance already running');
            $this->terminate(-1);
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