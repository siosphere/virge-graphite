<?php
use Virge\Cli;
use Virge\Graphite\Command\WorkerCommand;
use Virge\Graphite\Command\SupervisorCommand;

Cli::add(WorkerCommand::COMMAND, new WorkerCommand(), 'run')
    ->setHelpText(WorkerCommand::COMMAND_HELP)
    ->setUsage(WorkerCommand::COMMAND_USAGE)
;
Cli::add(SupervisorCommand::COMMAND, new SupervisorCommand(), 'run')
    ->setHelpText(SupervisorCommand::COMMAND_HELP)
    ->setUsage(SupervisorCommand::COMMAND_USAGE)
;