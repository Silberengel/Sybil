<?php

namespace Sybil\Core;

use Sybil\Command\CommandInterface;
use Sybil\Command\FeedCommand;
use Sybil\Command\HelpCommand;
use Sybil\Command\NoteCommand;
use Sybil\Command\QueryCommand;
use Sybil\Command\RelayInfoCommand;
use Sybil\Command\ConvertCommand;
use Sybil\Command\ReplyCommand;
use Sybil\Command\RelayTestCommand;
use Sybil\Command\GitPatchCommand;
use Sybil\Command\NoteFeedCommand;
use Sybil\Command\GitStatusCommand;
use Sybil\Command\CitationFeedCommand;
use Sybil\Command\PublicationCommand;
use Sybil\Command\LongformFeedCommand;
use Sybil\Command\GitFeedCommand;
use Sybil\Command\WikiFeedCommand;
use Sybil\Command\CompletionCommand;
use Sybil\Command\GitStateCommand;
use Sybil\Command\GitIssueCommand;
use Sybil\Command\GitAnnounceCommand;
use Sybil\Command\BroadcastCommand;
use Sybil\Command\RelayRemoveCommand;
use Sybil\Command\LongformCommand;
use Sybil\Command\HighlightCommand;
use Sybil\Command\PublicationFeedCommand;
use Sybil\Command\NipInfoCommand;
use Sybil\Command\DeleteCommand;
use Sybil\Command\WikiCommand;
use Sybil\Command\RelayAddCommand;
use Sybil\Command\RelayListCommand;
use Sybil\Command\RepublishCommand;
use Sybil\Utility\Log\Logger;
use Sybil\Utility\Error\ErrorHandler;
use Sybil\Utility\Key\KeyUtility;
use Sybil\Exception\ApplicationException;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Config\FileLocator;
use Doctrine\ORM\EntityManagerInterface;
use Sybil\Repository\NostrEventRepository;
use Sybil\Service\RelayQueryService;
use Sybil\Service\NostrEventService;
use Sybil\Utility\Event\EventBroadcast;
use Sybil\Factory\EventFactory;
use Sybil\Security\AuthenticationManager;
use Sybil\Security\TokenManager;
use Sybil\Security\SessionManager;
use WebSocket\Client;
use Monolog\Logger as MonologLogger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Formatter\LineFormatter;
use Sybil\Utility\Log\LoggerFactory;
use Sybil\Exception\SybilException;
use Sybil\Service\ServiceInterface;
use Sybil\Service\ServiceFactoryInterface;
use Sybil\Service\ServiceInitializerInterface;
use Sybil\Service\ServiceShutdownInterface;
use Sybil\Utility\Event\EventBroadcastUtility;
use Sybil\Service\EventBroadcastService;
use Sybil\Command\CitationCommand;
use Sybil\Command\VersionCommand;
use Sybil\Command\NgitPatchCommand;
use Sybil\Command\NgitStatusCommand;
use Sybil\Command\NgitFeedCommand;
use Sybil\Command\NgitStateCommand;
use Sybil\Command\NgitIssueCommand;
use Sybil\Command\NgitAnnounceCommand;

/**
 * Main application class
 * 
 * This class is the main entry point for the application.
 * It handles command registration, service management, and execution.
 */
class Application extends BaseApplication implements EventSubscriberInterface
{
    private const SEVERITY_ERROR = 'error';
    private const SEVERITY_WARNING = 'warning';
    private const SEVERITY_INFO = 'info';
    private const SEVERITY_DEBUG = 'debug';

    /**
     * @var array<string, CommandInterface> Registered commands
     */
    private array $commands = [];
    
    /**
     * @var array<string, mixed> Registered services
     */
    private array $services = [];
    
    /**
     * @var LoggerInterface Logger instance
     */
    private LoggerInterface $logger;
    
    /**
     * @var ErrorHandler Error handler instance
     */
    private ErrorHandler $errorHandler;
    
    /**
     * @var ConsoleOutput Console output instance
     */
    private ConsoleOutput $output;

    /**
     * @var EntityManagerInterface Entity manager instance
     */
    private EntityManagerInterface $entityManager;

    /**
     * @var ContainerBuilder Service container
     */
    private ContainerBuilder $container;

    /**
     * @var bool Whether the application is initialized
     */
    private bool $initialized = false;

    /**
     * @var AuthenticationManager Authentication manager
     */
    private AuthenticationManager $authManager;

    /**
     * @var TokenManager Token manager
     */
    private TokenManager $tokenManager;

    /**
     * @var SessionManager Session manager
     */
    private SessionManager $sessionManager;

    /**
     * @var EventDispatcherInterface Event dispatcher
     */
    private EventDispatcherInterface $dispatcher;

    /**
     * Constructor
     */
    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct('Sybil', '1.0.0');
        $this->entityManager = $entityManager;
        $this->output = new ConsoleOutput();
        $this->logger = $this->initializeLogger();
        $this->errorHandler = new ErrorHandler($this->logger, $this->output);
        
        $this->container = new ContainerBuilder();
        $this->dispatcher = $this->container->get('event_dispatcher');
        
        $this->logger->info('Initializing application');
        
        try {
            // Register core services
            $this->registerService('logger', fn() => $this->logger);
            $this->registerService('errorHandler', fn() => $this->errorHandler);
            $this->registerService('output', fn() => $this->output);
            
            // Initialize authentication services
            $this->authManager = new AuthenticationManager($this->logger);
            $this->tokenManager = new TokenManager($this->logger);
            $this->sessionManager = new SessionManager($this->logger);
            
            $this->registerService('authManager', fn() => $this->authManager);
            $this->registerService('tokenManager', fn() => $this->tokenManager);
            $this->registerService('sessionManager', fn() => $this->sessionManager);
            
            // Create and register RelayQueryService first since it's needed by NostrEventRepository
            $client = new Client('wss://localhost');
            $relayQueryService = new RelayQueryService($client, $this->logger);
            $this->registerService('relayQueryService', fn() => $relayQueryService);
            
            // Create and register NostrEventRepository with required dependencies
            $eventRepository = new NostrEventRepository($this->entityManager, $relayQueryService);
            $this->registerService('eventRepository', fn() => $eventRepository);

            // Register EventFactory and EventBroadcast
            $eventFactory = new EventFactory();
            $eventBroadcastService = new EventBroadcastService($relayQueryService);
            $eventBroadcastUtility = new EventBroadcastUtility($relayQueryService);
            
            $this->container->set('event_factory', $eventFactory);
            $this->container->set('event_broadcast_service', $eventBroadcastService);
            $this->container->set('event_broadcast_utility', $eventBroadcastUtility);
            
            // Register NostrEventService with correct dependencies
            $eventService = new NostrEventService(
                $this->logger,
                $eventRepository,
                $eventBroadcastService,
                $eventFactory,
                $relayQueryService
            );
            $this->registerService('eventService', fn() => $eventService);

            // Register commands
            $this->registerCommand(new NoteCommand($eventService));
            $this->registerCommand(new QueryCommand($eventService));
            $this->registerCommand(new RelayInfoCommand($relayQueryService));
            $this->registerCommand(new HelpCommand($this->logger));
            $this->registerCommand(new CitationCommand(
                $eventService,
                $this->logger,
                $this->container->get('parameter_bag')
            ));
            $this->registerCommand(new CitationFeedCommand(
                $eventService,
                $this->logger
            ));
            $this->registerCommand(new VersionCommand($this->container->get('parameter_bag')));
            $this->registerCommand(new ConvertCommand());
            
            // Register additional commands
            $this->registerCommand(new ReplyCommand(
                $eventService,
                $this->logger,
                $this->container->get('parameter_bag')
            ));
            $this->registerCommand(new RelayTestCommand($relayQueryService));
            $this->registerCommand(new NgitPatchCommand());
            $this->registerCommand(new NoteFeedCommand($eventService));
            $this->registerCommand(new NgitStatusCommand());
            $this->registerCommand(new PublicationCommand($eventService));
            $this->registerCommand(new LongformFeedCommand($eventService));
            $this->registerCommand(new NgitFeedCommand());
            $this->registerCommand(new WikiFeedCommand($eventService));
            $this->registerCommand(new CompletionCommand());
            $this->registerCommand(new NgitStateCommand());
            $this->registerCommand(new NgitIssueCommand());
            $this->registerCommand(new NgitAnnounceCommand());
            $this->registerCommand(new BroadcastCommand($eventService));
            $this->registerCommand(new RelayRemoveCommand($relayQueryService));
            $this->registerCommand(new LongformCommand($eventService));
            $this->registerCommand(new HighlightCommand($eventService));
            $this->registerCommand(new PublicationFeedCommand($eventService));
            $this->registerCommand(new NipInfoCommand($this->logger));
            $this->registerCommand(new DeleteCommand($eventService));
            $this->registerCommand(new WikiCommand($eventService));
            $this->registerCommand(new RelayAddCommand($relayQueryService));
            $this->registerCommand(new RelayListCommand($relayQueryService));
            $this->registerCommand(new RepublishCommand($eventService));

            // Register event subscribers
            $this->dispatcher->addSubscriber($this);

            $this->logger->info('Application initialized successfully');
        } catch (\Exception $e) {
            $this->logger->error('Failed to initialize application', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new ApplicationException(
                'Failed to initialize application',
                ApplicationException::ERROR_INITIALIZATION,
                $e
            );
        }
    }

    /**
     * Get subscribed events
     *
     * @return array<string, string> The event names to listen to
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'console.command' => 'onConsoleCommand',
            'console.terminate' => 'onConsoleTerminate',
        ];
    }

    /**
     * Handle console command event
     *
     * @param ConsoleCommandEvent $event The event
     */
    public function onConsoleCommand(ConsoleCommandEvent $event): void
    {
        $command = $event->getCommand();
        $this->logger->debug('Executing command', [
            'name' => $command->getName(),
            'description' => $command->getDescription()
        ]);

        // Authenticate command if needed
        if ($command instanceof CommandInterface && $command->requiresAuthentication()) {
            try {
                $this->authManager->authenticate();
            } catch (\Exception $e) {
                $this->logger->error('Authentication failed', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                throw new ApplicationException(
                    'Authentication failed',
                    ApplicationException::ERROR_AUTHENTICATION,
                    $e
                );
            }
        }
    }

    /**
     * Handle console terminate event
     *
     * @param ConsoleTerminateEvent $event The event
     */
    public function onConsoleTerminate(ConsoleTerminateEvent $event): void
    {
        $command = $event->getCommand();
        $this->logger->debug('Command terminated', [
            'name' => $command->getName(),
            'exit_code' => $event->getExitCode()
        ]);
    }

    /**
     * Initialize the application
     * 
     * @param ParameterBagInterface $params The parameter bag containing configuration
     * @throws ApplicationException If initialization fails
     */
    public function initialize(ParameterBagInterface $params): void
    {
        if ($this->initialized) {
            $this->logger->warning('Application already initialized');
            return;
        }

        try {
            $this->logger->info('Initializing application with configuration');

            // Initialize KeyUtility with configuration
            KeyUtility::initialize($params);

            // Initialize authentication services
            $this->authManager->initialize($params);
            $this->tokenManager->initialize($params);
            $this->sessionManager->initialize($params);

            // Initialize services that implement ServiceInitializerInterface
            foreach ($this->services as $name => $service) {
                if (is_callable($service)) {
                    $service = $service($this);
                    $this->services[$name] = $service;
                }

                if ($service instanceof ServiceInitializerInterface) {
                    $this->logger->debug('Initializing service', ['name' => $name]);
                    $service->initialize($params);
                }
            }

            $this->initialized = true;
            $this->logger->info('Application initialized successfully');
        } catch (\Exception $e) {
            $this->logger->error('Failed to initialize application', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new ApplicationException(
                'Failed to initialize application',
                ApplicationException::ERROR_INITIALIZATION,
                $e
            );
        }
    }
    
    /**
     * Register a command
     *
     * @param CommandInterface $command The command to register
     * @return self
     * @throws ApplicationException If command registration fails
     */
    public function registerCommand(CommandInterface $command): self
    {
        try {
            $this->commands[$command->getName()] = $command;
            $this->logger->debug('Registered command', [
                'name' => $command->getName(),
                'description' => $command->getDescription()
            ]);
            $this->add($command);
            return $this;
        } catch (\Exception $e) {
            $this->logger->error('Failed to register command', [
                'name' => $command->getName(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new ApplicationException(
                "Failed to register command '{$command->getName()}'",
                ApplicationException::ERROR_COMMAND_REGISTRATION,
                $e
            );
        }
    }
    
    /**
     * Get all registered commands
     *
     * @return array<string, CommandInterface> Array of registered commands
     */
    public function getCommands(): array
    {
        return $this->commands;
    }
    
    /**
     * Register a service
     *
     * @param string $name The service name
     * @param callable $factory The service factory
     * @return self
     * @throws ApplicationException If service registration fails
     */
    public function registerService(string $name, callable $factory): self
    {
        try {
            $this->services[$name] = $factory;
            $this->logger->debug('Registered service', ['name' => $name]);
            return $this;
        } catch (\Exception $e) {
            $this->logger->error('Failed to register service', [
                'name' => $name,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new ApplicationException(
                "Failed to register service '$name'",
                ApplicationException::ERROR_SERVICE_REGISTRATION,
                $e
            );
        }
    }
    
    /**
     * Get a service
     *
     * @template T
     * @param string $name The service name
     * @return T The service
     * @throws ApplicationException If the service is not registered or initialization fails
     */
    public function getService(string $name): mixed
    {
        if (!isset($this->services[$name])) {
            $this->logger->error('Service not registered', ['name' => $name]);
            throw new ApplicationException(
                "Service '$name' is not registered.",
                ApplicationException::ERROR_UNKNOWN,
                null,
                ['service' => $name],
                self::SEVERITY_ERROR
            );
        }
        
        try {
            if (is_callable($this->services[$name])) {
                $this->logger->debug('Initializing service', ['name' => $name]);
                $this->services[$name] = ($this->services[$name])($this);
            }
            
            return $this->services[$name];
        } catch (\Exception $e) {
            $this->logger->error('Failed to initialize service', [
                'name' => $name,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new ApplicationException(
                "Failed to initialize service '$name'",
                ApplicationException::ERROR_SERVICE_INITIALIZATION,
                $e
            );
        }
    }
    
    /**
     * Check if debug mode is enabled
     *
     * @return bool Whether debug mode is enabled
     */
    private function isDebugMode(): bool
    {
        return getenv('APP_DEBUG') === 'true';
    }

    /**
     * Initialize the logger
     *
     * @return LoggerInterface The initialized logger
     */
    private function initializeLogger(): LoggerInterface
    {
        $logger = new MonologLogger('sybil');
        
        // Add rotating file handler
        $fileHandler = new RotatingFileHandler(
            getenv('LOG_PATH') ?: 'var/log/sybil.log',
            7, // Keep 7 days of logs
            MonologLogger::DEBUG
        );
        $fileHandler->setFormatter(new LineFormatter(
            "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
            "Y-m-d H:i:s"
        ));
        $logger->pushHandler($fileHandler);
        
        // Add error log handler for errors
        $errorHandler = new ErrorLogHandler(
            ErrorLogHandler::OPERATING_SYSTEM,
            MonologLogger::ERROR
        );
        $errorHandler->setFormatter(new LineFormatter(
            "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
            "Y-m-d H:i:s"
        ));
        $logger->pushHandler($errorHandler);
        
        return $logger;
    }

    /**
     * Run the application
     *
     * @param InputInterface|null $input The input interface
     * @param OutputInterface|null $output The output interface
     * @return int The exit code
     */
    public function run(InputInterface $input = null, OutputInterface $output = null): int
    {
        try {
            $this->logger->info('Starting application');
            $result = parent::run($input, $output);
            $this->logger->info('Application finished', ['exit_code' => $result]);
            return $result;
        } catch (\Exception $e) {
            $this->logger->error('Application error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        } finally {
            $this->shutdown();
        }
    }

    /**
     * Shutdown the application
     */
    private function shutdown(): void
    {
        try {
            $this->logger->info('Shutting down application');

            // Shutdown services that implement ServiceShutdownInterface
            foreach ($this->services as $name => $service) {
                if ($service instanceof ServiceShutdownInterface) {
                    $this->logger->debug('Shutting down service', ['name' => $name]);
                    $service->shutdown();
                }
            }

            // Shutdown authentication services
            $this->authManager->shutdown();
            $this->tokenManager->shutdown();
            $this->sessionManager->shutdown();

            $this->logger->info('Application shutdown complete');
        } catch (\Exception $e) {
            $this->logger->error('Error during application shutdown', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
} 