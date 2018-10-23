<?php
namespace Virge\Graphite\Service\Provider;

use Virge\Cli;
use Virge\Core\Config;
use Virge\Graphite\Component\Task;

/**
 * 
 * @author Michael Kramer
 */
abstract class AbstractProvider {
    

    /**
     * @param string $queue
     * @param Task $task
     */
    abstract public function push($queueName, Task $task) : bool;

    /**
     * Get a task from the queue and dispatch it
     * @param type $queue
     */
    abstract public function listen($queueName, $callback);
}