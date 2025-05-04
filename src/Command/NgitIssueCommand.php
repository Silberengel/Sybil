<?php

namespace App\Command;

use App\Command\Trait\NgitCommandTrait;
use App\Service\NostrService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'ngit-issue',
    description: 'Create a git issue on Nostr (kind 1621)',
)]
class NgitIssueCommand extends Command
{
    use NgitCommandTrait;

    public function __construct(
        private readonly NostrService $nostrService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp(<<<'HELP'
The <info>%command.name%</info> command creates a git issue using kind 1621.

This command follows NIP-34 for git issues. It creates an event that:
- Creates an issue for a git repository
- Can include labels and status
- Supports issue replies
- Can be referenced by patches

Example:
  <info>%command.full_name% --repo-id my-project --owner <pubkey> \
    --subject "Bug in feature X" --content issue.md \
    --labels bug,high-priority</info>

Required:
- Repository ID (--repo-id): Must match the identifier in the repository announcement
- Owner (--owner): Repository owner's pubkey
- Subject (--subject): Issue title/subject
- Content (--content): Path to issue file or issue content

Optional:
- Labels (--labels): Comma-separated list of labels
- Reply To (--reply-to): Event ID to reply to

The command automatically:
- Validates repository ID format
- Validates pubkey format
- Reads issue content from file if path is provided
- Adds proper tags for repository address and owner
- Handles issue replies
HELP
            )
            ->addOption('repo-id', null, InputOption::VALUE_REQUIRED, 'Repository ID')
            ->addOption('owner', null, InputOption::VALUE_REQUIRED, 'Repository owner pubkey')
            ->addOption('subject', null, InputOption::VALUE_REQUIRED, 'Issue title/subject')
            ->addOption('content', null, InputOption::VALUE_REQUIRED, 'Path to issue file or issue content')
            ->addOption('labels', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Issue labels')
            ->addOption('reply-to', null, InputOption::VALUE_REQUIRED, 'Event ID to reply to');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $repoId = $input->getOption('repo-id');
        $ownerPubkey = $input->getOption('owner');
        
        $this->validateRepositoryId($repoId);
        $this->validatePubkey($ownerPubkey);

        $content = $input->getOption('content');
        if (file_exists($content)) {
            $content = file_get_contents($content);
        }

        $tags = [
            ['a', $this->getRepositoryAddress($repoId, $ownerPubkey)],
            ['p', $ownerPubkey],
        ];

        if ($replyTo = $input->getOption('reply-to')) {
            $tags[] = ['e', $replyTo, '', 'reply'];
        }

        foreach ($input->getOption('labels') as $label) {
            $tags[] = ['l', $label];
        }

        $event = $this->nostrService->createEvent(
            kind: 1621,
            content: $content,
            tags: $tags
        );

        $this->nostrService->publishEvent($event);

        $output->writeln('Issue created successfully!');
        return Command::SUCCESS;
    }
} 