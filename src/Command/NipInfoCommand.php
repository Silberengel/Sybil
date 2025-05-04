<?php

namespace Sybil\Command;

use Sybil\Command\Trait\{
    CommandTrait,
    RelayCommandTrait,
    EventCommandTrait,
    CommandImportsTrait
};

/**
 * Command for displaying information about Nostr Improvement Proposals (NIPs)
 * 
 * This command handles the 'nip-info' command, which displays
 * information about Nostr Improvement Proposals. You can filter NIPs
 * by category or status, and use --raw for JSON output.
 * 
 * Usage: nostr:nip-info [<nip>] [--category CATEGORY] [--status STATUS] [--raw]
 * 
 * Examples:
 *   sybil nip-info 1
 *   sybil nip-info --category protocol
 *   sybil nip-info --status final
 *   sybil nip-info 1 --raw
 * 
 * Test examples can be found in:
 *   tests/Integration/CoreIntegrationTest.php
 */
class NipInfoCommand extends Command implements CommandInterface
{
    use CommandTrait;
    use RelayCommandTrait;
    use EventCommandTrait;
    use CommandImportsTrait;
    
    private array $nips;
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        parent::__construct();
        $this->logger = $logger;
        $this->initializeNips();
    }

    public function getName(): string
    {
        return 'nostr:nip-info';
    }

    public function getDescription(): string
    {
        return 'Display information about Nostr Improvement Proposals (NIPs)';
    }

    public function getHelp(): string
    {
        return <<<'HELP'
The <info>%command.name%</info> command displays information about Nostr Improvement Proposals (NIPs).

<info>php %command.full_name% [<nip>] [--category CATEGORY] [--status STATUS] [--raw]</info>

Arguments:
  <nip>          The NIP number to display information for (optional)

Options:
  --category     Filter NIPs by category (e.g., protocol, client, relay)
  --status       Filter NIPs by status (e.g., draft, final, deprecated)
  --raw          Output in JSON format

Examples:
  <info>php %command.full_name% 1</info>
  <info>php %command.full_name% --category protocol</info>
  <info>php %command.full_name% --status final</info>
  <info>php %command.full_name% 1 --raw</info>
HELP;
    }

    protected function configure(): void
    {
        $this
            ->setName('nip-info')
            ->setDescription('Display information about Nostr Improvement Proposals (NIPs)')
            ->addArgument('nip', InputArgument::OPTIONAL, 'The NIP number to display information for')
            ->addOption('category', null, InputOption::VALUE_REQUIRED, 'Filter NIPs by category')
            ->addOption('status', null, InputOption::VALUE_REQUIRED, 'Filter NIPs by status')
            ->addOption('raw', null, InputOption::VALUE_NONE, 'Output in JSON format');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->executeWithErrorHandling($input, $output, function (InputInterface $input, OutputInterface $output) {
            $nipNumber = $input->getArgument('nip');
            $category = $input->getOption('category');
            $status = $input->getOption('status');
            $raw = $input->getOption('raw');

            if ($nipNumber !== null) {
                $nipNumber = (int) $nipNumber;
                if (!$this->isValidNipNumber($nipNumber)) {
                    throw new \InvalidArgumentException(sprintf('Invalid NIP number: %d', $nipNumber));
                }
                $result = $this->getNipInfo($nipNumber);
            } else {
                $result = $this->getFilteredNips($category, $status);
            }

            if ($raw) {
                $output->writeln(json_encode($result, JSON_PRETTY_PRINT));
            } else {
                $this->formatNipInfo($result, $output);
            }

            return Command::SUCCESS;
        });
    }

    private function initializeNips(): void
    {
        $this->nips = require __DIR__ . '/config/nips.php';
    }

    private function isValidNipNumber(int $nipNumber): bool
    {
        return isset($this->nips['nips'][$nipNumber]);
    }

    private function getNipInfo(int $nipNumber): array
    {
        $nip = $this->nips['nips'][$nipNumber];
        $nip['number'] = $nipNumber;
        
        // Add dependent NIPs if any
        $deps = $this->getDependentNips($nipNumber);
        if (!empty($deps)) {
            $nip['dependent_nips'] = $deps;
        }

        return $nip;
    }

    private function getFilteredNips(?string $category, ?string $status): array
    {
        $nips = $this->nips['nips'];

        if ($category !== null) {
            $nips = array_filter($nips, fn($nip) => $nip['category'] === $category);
        }

        if ($status !== null) {
            $nips = array_filter($nips, fn($nip) => $nip['status'] === $status);
        }

        return $nips;
    }

    private function getDependentNips(int $nipNumber): array
    {
        return array_filter($this->nips['nips'], function($nip) use ($nipNumber) {
            return isset($nip['deprecated_by']) && $nip['deprecated_by'] === $nipNumber;
        });
    }

    private function formatNipInfo(array $result, OutputInterface $output): void
    {
        if (isset($result['number'])) {
            // Single NIP/NKBIP info
            if (str_starts_with($result['number'], 'nkbip-')) {
                $nkbipNumber = substr($result['number'], 6);
                $nkbipUrl = sprintf('https://next-alexandria.gitcitadel.eu/publication?d=nkbip-%02d', $nkbipNumber);
                $output->writeln(sprintf('<info>NKBIP-%s: %s</info>', $nkbipNumber, $result['name']));
                $output->writeln(sprintf('<href=%s>%s</>', $nkbipUrl, $nkbipUrl));
            } else {
                $nipUrl = sprintf('https://github.com/nostr-protocol/nips/blob/master/%02d.md', $result['number']);
                $output->writeln(sprintf('<info>NIP-%d: %s</info>', $result['number'], $result['name']));
                $output->writeln(sprintf('<href=%s>%s</>', $nipUrl, $nipUrl));
            }

            $output->writeln(sprintf('Category: %s', $result['category']));
            $output->writeln(sprintf('Status: %s', $result['status']));
            $output->writeln(sprintf('Description: %s', $result['description']));

            if (isset($result['dependent_nips'])) {
                $output->writeln('');
                $output->writeln('Dependent NIPs:');
                foreach ($result['dependent_nips'] as $dep) {
                    $output->writeln(sprintf('  NIP-%d: %s', $dep['number'], $dep['name']));
                }
            }
        } else {
            // List of NIPs
            $output->writeln('<info>Nostr Improvement Proposals (NIPs)</info>');
            $output->writeln('');

            foreach ($result as $nip) {
                $output->writeln(sprintf('NIP-%d: %s', $nip['number'], $nip['name']));
                $output->writeln(sprintf('  Category: %s', $nip['category']));
                $output->writeln(sprintf('  Status: %s', $nip['status']));
                $output->writeln('');
            }
        }
    }
} 