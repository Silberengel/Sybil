<?php

namespace Sybil\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * Command for displaying the Sybil version
 * 
 * This command displays the current version of Sybil from the configuration.
 * Usage: sybil version
 */
class VersionCommand extends Command implements CommandInterface
{
    private ParameterBagInterface $params;

    public function __construct(ParameterBagInterface $params)
    {
        parent::__construct();
        $this->params = $params;
    }

    public function getName(): string
    {
        return 'version';
    }

    public function getDescription(): string
    {
        return 'Display the Sybil version';
    }

    public function getHelp(): string
    {
        return <<<'HELP'
The <info>%command.name%</info> command displays the current version of Sybil.

<info>php %command.full_name%</info>

Example:
  <info>php %command.full_name%</info>
HELP;
    }

    protected function configure(): void
    {
        $this
            ->setName('version')
            ->setDescription('Display the Sybil version');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $version = $this->params->get('app')['version'];
        $output->writeln("Sybil version {$version}");
        return Command::SUCCESS;
    }
} 