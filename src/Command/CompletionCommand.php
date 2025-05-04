<?php

namespace Sybil\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

class CompletionCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('completion')
            ->setDescription('Manage command completion (enabled by default)')
            ->setHelp(<<<'HELP'
Manage command completion for Sybil.

Command completion is enabled by default during installation. This command allows you to:
1. Reinstall completion if it's not working
2. Uninstall completion if you don't want it

Usage:
  sybil completion [--uninstall]

Options:
  --uninstall    Uninstall command completion (completion is enabled by default)
HELP
            )
            ->addOption('uninstall', null, InputOption::VALUE_NONE, 'Uninstall command completion (completion is enabled by default)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $uninstall = $input->getOption('uninstall');
        $completionDir = $_SERVER['HOME'] . '/.local/share/bash-completion/completions';
        $completionFile = $completionDir . '/sybil';
        $scriptPath = __DIR__ . '/../../bin/completion.sh';

        if ($uninstall) {
            // Remove completion script
            if (file_exists($completionFile)) {
                unlink($completionFile);
                $output->writeln('<info>Removed completion script</info>');
            }

            // Remove from .bashrc
            $bashrc = $_SERVER['HOME'] . '/.bashrc';
            if (file_exists($bashrc)) {
                $content = file_get_contents($bashrc);
                $content = preg_replace('/^source.*completions\/sybil$/m', '', $content);
                file_put_contents($bashrc, $content);
                $output->writeln('<info>Removed from .bashrc</info>');
            }

            // Remove from .zshrc
            $zshrc = $_SERVER['HOME'] . '/.zshrc';
            if (file_exists($zshrc)) {
                $content = file_get_contents($zshrc);
                $content = preg_replace('/^source.*completions\/sybil$/m', '', $content);
                file_put_contents($zshrc, $content);
                $output->writeln('<info>Removed from .zshrc</info>');
            }

            $output->writeln('<info>Command completion uninstalled successfully!</info>');
            $output->writeln('Please restart your terminal or run:');
            $output->writeln('  source ~/.bashrc  # or source ~/.zshrc');
        } else {
            // Create completion directory if it doesn't exist
            if (!file_exists($completionDir)) {
                mkdir($completionDir, 0755, true);
            }

            // Copy completion script
            copy($scriptPath, $completionFile);
            chmod($completionFile, 0755);
            $output->writeln('<info>Installed completion script</info>');

            // Add to .bashrc
            $bashrc = $_SERVER['HOME'] . '/.bashrc';
            if (file_exists($bashrc)) {
                $content = file_get_contents($bashrc);
                if (!str_contains($content, 'source.*completions/sybil')) {
                    file_put_contents($bashrc, "\nsource $completionFile\n", FILE_APPEND);
                    $output->writeln('<info>Added to .bashrc</info>');
                }
            }

            // Add to .zshrc
            $zshrc = $_SERVER['HOME'] . '/.zshrc';
            if (file_exists($zshrc)) {
                $content = file_get_contents($zshrc);
                if (!str_contains($content, 'source.*completions/sybil')) {
                    file_put_contents($zshrc, "\nsource $completionFile\n", FILE_APPEND);
                    $output->writeln('<info>Added to .zshrc</info>');
                }
            }

            $output->writeln('<info>Command completion installed successfully!</info>');
            $output->writeln('Please restart your terminal or run:');
            $output->writeln('  source ~/.bashrc  # or source ~/.zshrc');
        }

        return Command::SUCCESS;
    }
} 