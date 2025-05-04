<?php

namespace Sybil\Command;

use Sybil\Service\NostrEventService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Uid\Uuid;

class CalendarEventRsvpCommand extends Command implements CommandInterface
{
    private NostrEventService $eventService;

    public function __construct(NostrEventService $eventService)
    {
        parent::__construct();
        $this->eventService = $eventService;
    }

    protected function configure(): void
    {
        $this
            ->setName('calendar:rsvp')
            ->setDescription('Create a calendar event RSVP (kind 31925)')
            ->addArgument('event-coordinates', InputArgument::REQUIRED, 'Event coordinates in format <kind>:<pubkey>:<d-identifier>')
            ->addArgument('status', InputArgument::REQUIRED, 'RSVP status (accepted/declined/tentative)')
            ->addOption('content', 'c', InputOption::VALUE_OPTIONAL, 'Note about the RSVP', '')
            ->addOption('event-id', 'e', InputOption::VALUE_OPTIONAL, 'Event ID of the calendar event being responded to')
            ->addOption('free-busy', 'f', InputOption::VALUE_OPTIONAL, 'Free/busy status (free/busy)')
            ->addOption('relay', 'r', InputOption::VALUE_OPTIONAL, 'Recommended relay URL');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $uuid = Uuid::v4()->toRfc4122();
        $eventCoordinates = $input->getArgument('event-coordinates');
        $status = strtolower($input->getArgument('status'));
        $content = $input->getOption('content');
        $eventId = $input->getOption('event-id');
        $freeBusy = strtolower($input->getOption('free-busy'));
        $relay = $input->getOption('relay');

        // Validate status
        if (!in_array($status, ['accepted', 'declined', 'tentative'])) {
            $output->writeln('<error>Status must be one of: accepted, declined, tentative</error>');
            return Command::FAILURE;
        }

        // Validate free/busy if provided
        if ($freeBusy && !in_array($freeBusy, ['free', 'busy'])) {
            $output->writeln('<error>Free/busy status must be one of: free, busy</error>');
            return Command::FAILURE;
        }

        // Validate event coordinates format
        $parts = explode(':', $eventCoordinates);
        if (count($parts) !== 3 || !in_array($parts[0], ['31922', '31923'])) {
            $output->writeln('<error>Event coordinates must be in format <kind>:<pubkey>:<d-identifier></error>');
            return Command::FAILURE;
        }

        // Build tags array
        $eventTags = [
            ['d', $uuid],
            ['a', $eventCoordinates],
            ['status', $status]
        ];

        if ($eventId) {
            $eventTags[] = ['e', $eventId];
        }

        if ($freeBusy && $status !== 'declined') {
            $eventTags[] = ['fb', $freeBusy];
        }

        if ($relay) {
            $eventTags[] = ['p', $parts[1], $relay];
        } else {
            $eventTags[] = ['p', $parts[1]];
        }

        try {
            $event = $this->eventService->createEvent(
                31925,
                $content,
                $eventTags
            );

            $output->writeln('<info>Calendar event RSVP created successfully</info>');
            $output->writeln("Event ID: {$event->getId()}");
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln("<error>Failed to create calendar event RSVP: {$e->getMessage()}</error>");
            return Command::FAILURE;
        }
    }
} 