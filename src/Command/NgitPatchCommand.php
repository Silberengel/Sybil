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
    name: 'ngit-patch',
    description: 'Submit a git patch on Nostr',
)]
class NgitPatchCommand extends Command
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
The <info>%command.name%</info> command submits a git patch using kind 1617.

This command follows NIP-34 for git patches. It creates an event that:
- Submits a patch for a git repository
- Can include commit information and PGP signatures
- Supports patch revisions and replies
- Can be referenced by issues

Example:
  <info>%command.full_name% --repo-id my-project --owner <pubkey> --content patch.diff \
    --commit abc123 --parent-commit def456 \
    --commit-pgp-sig "-----BEGIN PGP SIGNATURE-----\n..." \
    --committer "John Doe,john@example.com,1234567890,+0000"</info>

Required:
- Repository ID (--repo-id): Must match the identifier in the repository announcement
- Owner (--owner): Repository owner's pubkey
- Content (--content): Path to patch file or patch content

Optional:
- Commit (--commit): Current commit ID
- Parent Commit (--parent-commit): Parent commit ID
- PGP Signature (--commit-pgp-sig): PGP signature of the commit
- Committer (--committer): Committer info (name,email,timestamp,timezone)
- Root (--root): Mark as root patch
- Root Revision (--root-revision): Mark as root revision
- Reply To (--reply-to): Event ID to reply to

The command automatically:
- Validates repository ID format
- Validates pubkey format
- Reads patch content from file if path is provided
- Adds proper tags for repository address and owner
- Handles patch revisions and replies
HELP
            )
            ->addOption('repo-id', null, InputOption::VALUE_REQUIRED, 'Repository ID')
            ->addOption('owner', null, InputOption::VALUE_REQUIRED, 'Repository owner pubkey')
            ->addOption('content', null, InputOption::VALUE_REQUIRED, 'Path to patch file or patch content')
            ->addOption('commit', null, InputOption::VALUE_REQUIRED, 'Current commit ID')
            ->addOption('parent-commit', null, InputOption::VALUE_REQUIRED, 'Parent commit ID')
            ->addOption('commit-pgp-sig', null, InputOption::VALUE_REQUIRED, 'PGP signature of the commit')
            ->addOption('committer', null, InputOption::VALUE_REQUIRED, 'Committer info (format: name,email,timestamp,timezone)')
            ->addOption('root', null, InputOption::VALUE_NONE, 'Mark as root patch')
            ->addOption('root-revision', null, InputOption::VALUE_NONE, 'Mark as root revision')
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

        if ($input->getOption('root')) {
            $tags[] = ['t', 'root'];
        }

        if ($input->getOption('root-revision')) {
            $tags[] = ['t', 'root-revision'];
        }

        if ($replyTo = $input->getOption('reply-to')) {
            $tags[] = ['e', $replyTo, '', 'reply'];
        }

        if ($commit = $input->getOption('commit')) {
            $tags[] = ['commit', $commit];
            $tags[] = ['r', $commit];
        }

        if ($parentCommit = $input->getOption('parent-commit')) {
            $tags[] = ['parent-commit', $parentCommit];
        }

        if ($pgpSig = $input->getOption('commit-pgp-sig')) {
            $tags[] = ['commit-pgp-sig', $pgpSig];
        }

        if ($committer = $input->getOption('committer')) {
            $tags[] = array_merge(['committer'], explode(',', $committer));
        }

        $event = $this->nostrService->createEvent(
            kind: 1617,
            content: $content,
            tags: $tags
        );

        $this->nostrService->publishEvent($event);

        $output->writeln('Patch submitted successfully!');
        return Command::SUCCESS;
    }
} 