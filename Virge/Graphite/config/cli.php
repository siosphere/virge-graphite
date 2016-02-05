<?php
use Virge\Cli;
use Virge\Graphite\Command\WorkerCommand;

Cli::add(WorkerCommand::COMMAND, new WorkerCommand(), 'run');