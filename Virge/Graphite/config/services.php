<?php

use Virge\Graphite\Service\QueueService;
use Virge\Virge;

/**
 * 
 * @author Michael Kramer
 */
Virge::registerService(QueueService::SERVICE_ID, new QueueService());