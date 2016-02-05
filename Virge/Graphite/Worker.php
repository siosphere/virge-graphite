<?php
namespace Virge\Graphite;

use Virge\Graphite\Component\Task;

/**
 * 
 * @author Michael Kramer
 */
class Worker {
    
    /**
     * Hold all of our workers
     * @var array
     */
    protected static $workers = [];
    
    /**
     * Attach a consumer to a given task
     * @param string $taskName
     * @param mixed $worker
     * @param string $serviceMethod
     */
    public static function consume($taskName, $worker, $serviceMethod) {
        if(!isset(self::$workers[$taskName])) {
            self::$workers[$taskName] = [];
        }
        
        self::$workers[$taskName][] = [
            'worker'        =>      $worker,
            'serviceMethod' =>      $serviceMethod,
        ];
    }
    
    /**
     * Dispatch a task to all workers who can work on it
     * @param Task $task
     * @throws \InvalidArgumentException
     */
    public static function dispatch(Task $task) {
        $taskName = $task::TASK_NAME;
        
        if(!isset(self::$workers[$taskName])) {
            return;
        }
        
        foreach(self::$workers[$taskName] as $workerDefinition) {
            $worker = $workerDefinition['worker'];
            if(!$worker) {
                throw new \InvalidArgumentException(sprintf("Invalid worker, work cannot be completed"));
            }
            
            call_user_func_array([$worker, $workerDefinition['serviceMethod']], [$task]);
        }
    }
}