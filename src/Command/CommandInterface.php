<?php

namespace Sybil\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Command\Command;

/**
 * Interface for all commands
 * 
 * This interface defines the contract that all commands must implement.
 * It extends Symfony's command system while providing additional functionality
 * specific to Sybil commands.
 */
interface CommandInterface
{
    /**
     * Get the name of the command
     *
     * @return string The command name
     */
    public function getName(): string;

    /**
     * Get the description of the command
     *
     * @return string The command description
     */
    public function getDescription(): string;

    /**
     * Configure the command
     *
     * This method is called during command initialization to set up
     * arguments, options, and other command configuration.
     *
     * @return void
     */
    public function configure(): void;

    /**
     * Execute the command
     *
     * @param InputInterface $input The input interface
     * @param OutputInterface $output The output interface
     * @return int The command exit code (Command::SUCCESS or Command::FAILURE)
     */
    public function execute(InputInterface $input, OutputInterface $output): int;

    /**
     * Get the command help
     *
     * @return string The command help text
     */
    public function getHelp(): string;
} 