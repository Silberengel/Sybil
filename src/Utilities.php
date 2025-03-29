<?php
/**
 * Class Utilities
 * 
 * This file contains utility functions offered directly to the user:
 * - fetching events
 * - broadcasting events
 * - publishing and broadcasting deletion requests
 * 
 */

use React\Dns\RecordNotFoundException;
use swentel\nostr\Relay\Relay;
use swentel\nostr\Relay\RelaySet;
use swentel\nostr\Filter\Filter;
use swentel\nostr\Subscription\Subscription;
use swentel\nostr\Message\RequestMessage;
use swentel\nostr\Message\EventMessage;
use swentel\nostr\Sign\Sign;
use swentel\nostr\Event\Event;

class Utilities
{

    public string $eventID = '';
    
    // Constants
    public const DEFAULT_RELAY = 'wss://thecitadel.nostr1.com';


    /**
     * Constructor for Utilities
     * 
     */
        public function __construct(){


        }

    /**
     *  Run utility function
     * 
     *  Get an event from a set of relays, using the hex ID.
     * 
     * @param string $command What does the user want to do?
     * @return void 
     */
    public function run_utility(string $command): void
    {
        // forward to individual command functions

        switch($command){
            case 'fetch':
                $this->fetch_event();
                break;
            case 'broadcast':
                $this->broadcast_event();
                break;
            case'delete':
                $this->delete_event();
                break;
            default:
                throw new InvalidArgumentException(
                PHP_EOL.'That is not a valid command.'.PHP_EOL.PHP_EOL);
        }
    }

    /**
     *  Fetching event
     * 
     *  Get an event from a set of relays, using the hex ID.
     * 
     * @param string $eventID
     * @return array 
     */
    public function fetch_event(): array
    {
        $eventIDs[] = $this->eventID;

        $filter1 = new Filter();
        $filter1->setIds($eventIDs);
        $filters = [$filter1]; // You can add multiple filters.

        $subscription = new Subscription();
        $requestMessage = new RequestMessage($subscription->getid(), $filters);
        return send_event_with_retry($requestMessage);
    }

    /**
     *  Broadcast event
     * 
     *  Find an event, by searching a set of relays and then broadcast it to the same set.
     * 
     * @return array with the publishing results of each deletion event to each relay in the list
     */
    public function broadcast_event(): array
    {
        $eventIDs[] = $this->eventID;

        $filter1 = new Filter();
        $filter1->setIds($eventIDs);
        $filters = [$filter1]; // You can add multiple filters.

        $subscription = new Subscription();
        $requestMessage = new RequestMessage($subscription->getid(), $filters);
        
        return send_event_with_retry($requestMessage);
    }
    
    /**
     *  Delete event
     * 
     *  Find an event, figure out the kind number, 
     *  and then send an event deletion request to a set of relays,
     *  using the hex event ID.
     * 
     * @return array with the publishing results of each deletion event 
     * to each relay in the list
     */
    public function delete_event(): array
    {
        $eventIDs[] = $this->eventID;

        $filter1 = new Filter();
        $filter1->setIds($eventIDs);

        // Get private key from environment
        $privateKey = getenv('NOSTR_SECRET_KEY');
            
        // Validate private key
        if (!str_starts_with($privateKey, 'nsec')) {
            throw new InvalidArgumentException('Please place your nsec in the nostr-private.key file.');
        }

        $fetchedEvent = $this->fetch_event();
        
        if(!$fetchedEvent){
            throw new RecordNotFoundException("The event could not be found in the relay set.");
        }

        $kindNum = $fetchedEvent['kind'];
        if(!$kindNum){
            throw new InvalidArgumentException("The kind could not be determined.");
        }

        $note = new Event();
        $note->setKind(5);
        $note->setTags([
        ['e', $eventIDs[0], self::DEFAULT_RELAY],
        ['k', $kindNum]
        ]);

        $signer = new Sign();
        $signer->signEvent($note, $privateKey);
        $eventMessage = new EventMessage($note);

        return send_event_with_retry($eventMessage);
    }

}