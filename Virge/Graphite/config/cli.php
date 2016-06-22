<?php
use Virge\Cli;
use Virge\Graphite\Command\WorkerCommand;
use Virge\Graphite\Command\SupervisorCommand;

Cli::add(WorkerCommand::COMMAND, new WorkerCommand(), 'run');
Cli::add(SupervisorCommand::COMMAND, new SupervisorCommand(), 'run');