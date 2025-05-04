<?php

namespace Sybil\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;
use Psr\Log\LoggerInterface;
use Sybil\Utility\Log\LoggerFactory;
use Sybil\Utility\Format\ScriptoriumConverter;

class ConvertCommand extends Command
{
    private LoggerInterface $logger;
    private ScriptoriumConverter $converter;

    public function __construct()
    {
        parent::__construct();
        $this->logger = LoggerFactory::createLogger('convert_command');
        $this->converter = new ScriptoriumConverter();
    }

    protected function configure(): void
    {
        $this
            ->setName('convert')
            ->setDescription('Convert documents to AsciiDoc format using Scriptorium')
            ->addArgument('input', InputArgument::REQUIRED, 'Input file path')
            ->addOption('output', 'o', InputOption::VALUE_OPTIONAL, 'Output file path (defaults to input.adoc)')
            ->addOption('title', 't', InputOption::VALUE_OPTIONAL, 'Document title (defaults to filename)')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force overwrite of existing output file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $inputPath = $input->getArgument('input');
        $outputPath = $input->getOption('output') ?? pathinfo($inputPath, PATHINFO_FILENAME) . '.adoc';
        $title = $input->getOption('title') ?? pathinfo($inputPath, PATHINFO_FILENAME);
        $force = $input->getOption('force');

        try {
            // Check if input file exists
            if (!file_exists($inputPath)) {
                throw new \RuntimeException("Input file not found: $inputPath");
            }

            // Check if output file exists and handle force option
            if (file_exists($outputPath) && !$force) {
                throw new \RuntimeException("Output file already exists: $outputPath. Use --force to overwrite.");
            }

            // Get file extension
            $extension = strtolower(pathinfo($inputPath, PATHINFO_EXTENSION));

            // Handle PDF files
            if ($extension === 'pdf') {
                $io->warning('PDF conversion is not yet implemented.');
                return Command::SUCCESS;
            }

            // Validate supported formats
            $supportedFormats = ['txt', 'rtf', 'html', 'md'];
            if (!in_array($extension, $supportedFormats)) {
                throw new \RuntimeException("Unsupported file format: $extension. Supported formats are: " . implode(', ', $supportedFormats));
            }

            // Convert the file
            $this->logger->info('Starting conversion', [
                'input' => $inputPath,
                'output' => $outputPath,
                'format' => $extension
            ]);

            $result = $this->converter->convert($inputPath, $outputPath, $title);

            if ($result) {
                $io->success("Successfully converted $inputPath to $outputPath");
                $this->logger->info('Conversion completed successfully');
                return Command::SUCCESS;
            } else {
                throw new \RuntimeException("Conversion failed");
            }
        } catch (\Exception $e) {
            $io->error($e->getMessage());
            $this->logger->error('Conversion failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        }
    }
} 