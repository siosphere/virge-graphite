<?php
namespace Virge\Graphite\Component;

/**
 * 
 * @author Michael Kramer
 */
class Task {

    //overriden by all other tasks
    const TASK_NAME = '';
    
    protected $id;
    
    public function getId() {
        return $this->id;
    }
    
    public function setId($id) {
        $this->id = $id;
    }
    
}