<?php

namespace Sybil\Command;

use Sybil\Service\NostrEventService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Uid\Uuid;

class TimeBasedCalendarEventCommand extends Command implements CommandInterface
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
            ->setName('calendar:time')
            ->setDescription('Create a time-based calendar event (kind 31923)')
            ->addArgument('title', InputArgument::REQUIRED, 'Title of the calendar event')
            ->addArgument('start', InputArgument::REQUIRED, 'Start Unix timestamp in seconds')
            ->addArgument('end', InputArgument::OPTIONAL, 'End Unix timestamp in seconds')
            ->addOption('content', 'c', InputOption::VALUE_OPTIONAL, 'Description of the calendar event', '')
            ->addOption('summary', 's', InputOption::VALUE_OPTIONAL, 'Brief description of the calendar event')
            ->addOption('image', 'i', InputOption::VALUE_OPTIONAL, 'URL of an image to use for the event')
            ->addOption('start-tzid', null, InputOption::VALUE_OPTIONAL, 'Time zone of the start timestamp (IANA Time Zone Database)')
            ->addOption('end-tzid', null, InputOption::VALUE_OPTIONAL, 'Time zone of the end timestamp (IANA Time Zone Database)')
            ->addOption('location', 'l', InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'Location(s) of the calendar event')
            ->addOption('geohash', 'g', InputOption::VALUE_OPTIONAL, 'Geohash for the event location')
            ->addOption('participant', 'p', InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'Participant pubkey, optional relay URL, and role')
            ->addOption('label', null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'Label(s) to categorize the event')
            ->addOption('tag', 't', InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'Hashtag(s) to categorize the event')
            ->addOption('reference', 'r', InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'Reference link(s)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $uuid = Uuid::v4()->toRfc4122();
        $title = $input->getArgument('title');
        $start = $input->getArgument('start');
        $end = $input->getArgument('end');
        $content = $input->getOption('content');
        $summary = $input->getOption('summary');
        $image = $input->getOption('image');
        $startTzid = $input->getOption('start-tzid');
        $endTzid = $input->getOption('end-tzid');
        $locations = $input->getOption('location');
        $geohash = $input->getOption('geohash');
        $participants = $input->getOption('participant');
        $labels = $input->getOption('label');
        $tags = $input->getOption('tag');
        $references = $input->getOption('reference');

        // Validate timestamps
        if (!is_numeric($start)) {
            $output->writeln('<error>Start timestamp must be a number</error>');
            return Command::FAILURE;
        }

        if ($end && !is_numeric($end)) {
            $output->writeln('<error>End timestamp must be a number</error>');
            return Command::FAILURE;
        }

        // Build tags array
        $eventTags = [
            ['d', $uuid],
            ['title', $title],
            ['start', (string)$start]
        ];

        if ($end) {
            $eventTags[] = ['end', (string)$end];
        }

        if ($summary) {
            $eventTags[] = ['summary', $summary];
        }

        if ($image) {
            $eventTags[] = ['image', $image];
        }

        if ($startTzid) {
            $eventTags[] = ['start_tzid', $startTzid];
        }

        if ($endTzid) {
            $eventTags[] = ['end_tzid', $endTzid];
        }

        foreach ($locations as $location) {
            $eventTags[] = ['location', $location];
        }

        if ($geohash) {
            $eventTags[] = ['g', $geohash];
        }

        foreach ($participants as $participant) {
            $parts = explode(',', $participant);
            $tag = ['p', $parts[0]];
            if (isset($parts[1])) $tag[] = $parts[1];
            if (isset($parts[2])) $tag[] = $parts[2];
            $eventTags[] = $tag;
        }

        foreach ($labels as $label) {
            $parts = explode(',', $label);
            if (count($parts) === 2) {
                $eventTags[] = ['L', $parts[0]];
                $eventTags[] = ['l', $parts[1], $parts[0]];
            } else {
                $eventTags[] = ['l', $label];
            }
        }

        foreach ($tags as $tag) {
            $eventTags[] = ['t', $tag];
        }

        foreach ($references as $reference) {
            $eventTags[] = ['r', $reference];
        }

        try {
            $event = $this->eventService->createEvent(
                31923,
                $content,
                $eventTags
            );

            $output->writeln('<info>Calendar event created successfully</info>');
            $output->writeln("Event ID: {$event->getId()}");
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln("<error>Failed to create calendar event: {$e->getMessage()}</error>");
            return Command::FAILURE;
        }
    }
} 