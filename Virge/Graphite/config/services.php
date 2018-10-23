<?php

use Virge\Graphite\Service\QueueService;
use Virge\Core\Config;
use Virge\Virge;

/**
 * 
 * @author Michael Kramer
 */
Virge::registerService(QueueService::SERVICE_ID, function() {
    return new QueueService(Config::get('queue', 'provider') ?? QueueService::PROVIDER_RABBITMQ);
});