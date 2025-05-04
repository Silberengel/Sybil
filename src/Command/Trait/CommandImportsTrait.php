<?php

namespace Sybil\Command\Trait;

use Sybil\Service\NostrEventService;
use Sybil\Service\RelayQueryService;
use Sybil\Repository\NostrEventRepository;
use Sybil\Utility\Key\KeyUtility;
use Sybil\Utility\RelayUtility;
use Sybil\Utility\EventUtility;
use Sybil\Utility\Crypto\KeyPair;
use Sybil\Exception\CommandException;
use Sybil\Exception\RelayException;
use Sybil\Exception\EventException;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Console\Exception\RuntimeException;

use swentel\nostr\Filter\Filter;
use swentel\nostr\Event\Event;
use swentel\nostr\Event\EventInterface;
use swentel\nostr\Key\KeyPair as NostrKeyPair;

/**
 * Trait that consolidates all common imports used across command files
 * 
 * This trait provides a centralized location for all commonly used imports
 * in command classes. By using this trait, we reduce code duplication and
 * make import management easier.
 */
trait CommandImportsTrait
{
    // This trait is intentionally empty as it only serves to consolidate imports
} 