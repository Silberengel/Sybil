<?php

namespace Sybil;

use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\DependencyInjection\Reference;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;

class Kernel extends BaseKernel
{
    public function registerBundles(): array
    {
        return [];
    }

    public function registerContainerConfiguration(LoaderInterface $loader)
    {
        $loader->load(function(ContainerBuilder $container) {
            $container->setParameter('kernel.secret', 'dev_secret');
            
            // Register commands
            $container->registerForAutoconfiguration(\Symfony\Component\Console\Command\Command::class)
                ->addTag('console.command');

            // Register EntityManager
            $container->register('doctrine.orm.entity_manager', EntityManager::class)
                ->setPublic(true)
                ->setFactory([EntityManager::class, 'create'])
                ->setArguments([
                    [
                        'driver' => 'pdo_sqlite',
                        'path' => $this->getProjectDir() . '/var/data.db',
                    ],
                    ORMSetup::createAttributeMetadataConfiguration(
                        [$this->getProjectDir() . '/src/Entity'],
                        true
                    )
                ]);

            // Register commands
            $container->register(\Sybil\Command\DoctrineTestCommand::class)
                ->setAutoconfigured(true)
                ->setAutowired(true)
                ->addArgument(new Reference('doctrine.orm.entity_manager'))
                ->addTag('console.command');
        });
    }

    public function getProjectDir(): string
    {
        return dirname(__DIR__);
    }
} 