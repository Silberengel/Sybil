<?php

namespace Sybil\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;

/**
 * Command for displaying help information
 * 
 * This command handles the 'help' command, which displays help information
 * for the application and its commands.
 * Usage: nostr:help [<command>]
 */
class HelpCommand extends Command
{
    private LoggerInterface $logger;
    private array $commandTopics = [
        'core' => [
            'name' => 'Core Functions',
            'description' => 'Basic Nostr operations and utilities',
            'commands' => ['note', 'reply', 'query', 'delete', 'republish', 'broadcast', 'highlight', 'note:feed']
        ],
        'relay' => [
            'name' => 'Relay Management',
            'description' => 'Commands for managing Nostr relays',
            'commands' => ['relay:add', 'relay:remove', 'relay:list', 'relay:info', 'relay:test']
        ],
        'article' => [
            'name' => 'Article and Content Management',
            'description' => 'Commands for managing long-form content, publications, and wikis. All publications require type and c tags (default: "book").',
            'commands' => [
                'longform', 'longform:feed',
                'publication', 'publication:feed',
                'wiki', 'wiki:feed'
            ]
        ],
        'git' => [
            'name' => 'Git Integration',
            'description' => 'Commands for Git repository integration',
            'commands' => [
                'ngit-patch', 'ngit-status', 'ngit-state',
                'ngit-issue', 'ngit-announce', 'ngit-feed'
            ]
        ],
        'citation' => [
            'name' => 'Citation Management',
            'description' => 'Commands for managing citations and references',
            'commands' => ['citation', 'citation:feed']
        ],
        'utility' => [
            'name' => 'Utility Commands',
            'description' => 'General utility and configuration commands',
            'commands' => [
                'version', 'completion', 'nip:info',
                'help', 'convert'
            ]
        ]
    ];

    public function __construct(LoggerInterface $logger)
    {
        parent::__construct();
        $this->logger = $logger;
    }

    public function getName(): string
    {
        return 'nostr:help';
    }

    public function getDescription(): string
    {
        return 'Display help information';
    }

    public function getHelp(): string
    {
        return <<<'HELP'
The <info>%command.name%</info> command displays help information for the application and its commands.

<info>php %command.full_name% [<command>]</info>

Arguments:
  <command>    The command to get help for (optional)

Examples:
  <info>php %command.full_name%</info>
  <info>php %command.full_name% feed</info>
  <info>php %command.full_name% note</info>
HELP;
    }

    protected function configure(): void
    {
        $this
            ->setName('help')
            ->setDescription('Display help information')
            ->addArgument('topic', InputArgument::OPTIONAL, 'The topic or command to get help for')
            ->addOption('all', 'a', InputOption::VALUE_NONE, 'Show all command details');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $topic = $input->getArgument('topic');
        $showAll = $input->getOption('all');

        try {
            if ($showAll) {
                return $this->showAllCommands($io);
            }

            if ($topic === null) {
                return $this->showMainHelp($io);
            }

            if (isset($this->commandTopics[$topic])) {
                return $this->showTopicHelp($io, $topic);
            }

            // Check if it's a command name
            $command = $this->getApplication()->find($topic);
            if ($command) {
                return $this->showCommandHelp($io, $command);
            }

            $io->error("Unknown topic or command: $topic");
            return Command::FAILURE;

        } catch (\Exception $e) {
            $this->logger->error('Error displaying help', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $io->error('Error displaying help: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function showMainHelp(SymfonyStyle $io): int
    {
        $io->title('Sybil Command Line Tool');
        $io->text('A powerful command-line interface for interacting with Nostr.');
        $io->newLine();
        
        $io->section('Documentation');
        $io->text('For detailed documentation:');
        $io->listing([
            '<href=README.md>README.md</> - Project overview and getting started',
            '<href=docs/command-reference.md>docs/command-reference.md</> - Complete command reference',
            '<href=docs/scriptorium.md>docs/scriptorium.md</> - Document conversion guide',
            '<href=docs/relay-management.md>docs/relay-management.md</> - Relay management guide',
            '<href=docs/git-integration.md>docs/git-integration.md</> - Git integration guide',
            '<href=docs/content-management.md>docs/content-management.md</> - Content management guide'
        ]);
        $io->newLine();

        $io->section('Available Topics');
        $table = new Table($io);
        $table->setHeaders(['Topic', 'Description']);
        
        foreach ($this->commandTopics as $key => $topic) {
            $table->addRow([
                "<info>$key</info>",
                $topic['description']
            ]);
        }
        
        $table->render();
        $io->newLine();

        $io->section('Usage');
        $io->text('  sybil help <topic>     Show help for a specific topic');
        $io->text('  sybil help <command>   Show help for a specific command');
        $io->text('  sybil help --all       Show all command details');
        $io->newLine();

        $io->section('Global Options');
        $io->text('  -h, --help            Display this help message');
        $io->text('  -V, --version         Display version information');
        $io->text('  --no-interaction      Do not ask any interactive question');
        $io->text('  -v|vv|vvv, --verbose  Increase the verbosity of messages');
        $io->newLine();

        return Command::SUCCESS;
    }

    private function showTopicHelp(SymfonyStyle $io, string $topic): int
    {
        $topicInfo = $this->commandTopics[$topic];
        $io->title($topicInfo['name']);
        $io->text($topicInfo['description']);
        $io->newLine();

        // Add documentation link for specific topics
        $docLinks = [
            'relay' => 'docs/relay-management.md',
            'git' => 'docs/git-integration.md',
            'article' => 'docs/content-management.md',
            'format' => 'docs/scriptorium.md'
        ];

        if (isset($docLinks[$topic])) {
            $io->section('Documentation');
            $io->text("For detailed documentation, see <href={$docLinks[$topic]}>{$docLinks[$topic]}</>");
            $io->newLine();
        }

        // Add specific information for article topic
        if ($topic === 'article') {
            $io->section('Required Tags');
            $io->text('All publications must include the following tags:');
            $io->listing([
                'type - Publication type (default: "book")',
                'c - Content type (default: "book")'
            ]);
            $io->text('These tags can be overridden in the YAML frontmatter of your publication.');
            $io->newLine();
        }

        $io->section('Available Commands');
        $table = new Table($io);
        $table->setHeaders(['Command', 'Description']);

        foreach ($topicInfo['commands'] as $commandName) {
            $command = $this->getApplication()->find($commandName);
            if ($command) {
                $table->addRow([
                    "<info>$commandName</info>",
                    $command->getDescription()
                ]);
            }
        }

        $table->render();
        $io->newLine();

        $io->section('Usage');
        $io->text("  sybil help $topic <command>   Show help for a specific command");
        $io->newLine();

        return Command::SUCCESS;
    }

    private function showCommandHelp(SymfonyStyle $io, Command $command): int
    {
        $io->title($command->getName());
        $io->text($command->getDescription());
        $io->newLine();

        if ($command->getHelp()) {
            $io->section('Help');
            $io->text($command->getHelp());
            $io->newLine();
        }

        $io->section('Usage');
        $io->text($command->getSynopsis());
        $io->newLine();

        if ($command->getAliases()) {
            $io->section('Aliases');
            $io->text(implode(', ', $command->getAliases()));
            $io->newLine();
        }

        $definition = $command->getDefinition();
        if ($definition->getArguments()) {
            $io->section('Arguments');
            $table = new Table($io);
            $table->setHeaders(['Name', 'Description']);
            foreach ($definition->getArguments() as $argument) {
                $table->addRow([
                    $argument->getName(),
                    $argument->getDescription()
                ]);
            }
            $table->render();
            $io->newLine();
        }

        if ($definition->getOptions()) {
            $io->section('Options');
            $table = new Table($io);
            $table->setHeaders(['Name', 'Description']);
            foreach ($definition->getOptions() as $option) {
                $table->addRow([
                    '--' . $option->getName(),
                    $option->getDescription()
                ]);
            }
            $table->render();
        }

        return Command::SUCCESS;
    }

    private function showAllCommands(SymfonyStyle $io): int
    {
        $io->title('Sybil Command Line Tool - All Commands');
        $io->text('A comprehensive list of all available commands and their details.');
        $io->newLine();

        foreach ($this->commandTopics as $topic => $topicInfo) {
            $io->section($topicInfo['name']);
            $io->text($topicInfo['description']);
            $io->newLine();

            foreach ($topicInfo['commands'] as $commandName) {
                $command = $this->getApplication()->find($commandName);
                if ($command) {
                    $io->section($command->getName());
                    $io->text($command->getDescription());
                    $io->newLine();

                    if ($command->getHelp()) {
                        $io->text($command->getHelp());
                        $io->newLine();
                    }

                    $io->text('Usage: ' . $command->getSynopsis());
                    $io->newLine();

                    $definition = $command->getDefinition();
                    if ($definition->getArguments()) {
                        $io->text('Arguments:');
                        foreach ($definition->getArguments() as $argument) {
                            $io->text('  ' . $argument->getName() . ': ' . $argument->getDescription());
                        }
                        $io->newLine();
                    }

                    if ($definition->getOptions()) {
                        $io->text('Options:');
                        foreach ($definition->getOptions() as $option) {
                            $io->text('  --' . $option->getName() . ': ' . $option->getDescription());
                        }
                        $io->newLine();
                    }

                    $io->newLine(2);
                }
            }
        }

        return Command::SUCCESS;
    }
} 