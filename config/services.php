<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;

return function(ContainerConfigurator $container) {
    $services = $container->services();

    // Parameters
    $container->parameters()
        ->set('doctrine.entity_dirs', [dirname(__DIR__) . '/src/Entity'])
        ->set('doctrine.dev_mode', true)
        ->set('doctrine.db_path', dirname(__DIR__) . '/var/data.db');

    // Create configuration
    $config = ORMSetup::createAttributeMetadataConfiguration(
        [dirname(__DIR__) . '/src/Entity'],
        true
    );

    // Create connection
    $connection = DriverManager::getConnection([
        'driver' => 'pdo_sqlite',
        'path' => dirname(__DIR__) . '/var/data.db',
    ], $config);

    // Create EntityManager
    $entityManager = new EntityManager($connection, $config);

    // Register services
    $services->set(EntityManager::class)
        ->synthetic()
        ->factory(function() use ($entityManager) {
            return $entityManager;
        });

    // Auto-configure and auto-wire commands
    $services->load('Sybil\\Command\\', '../src/Command/*')
        ->autowire()
        ->autoconfigure();
}; 