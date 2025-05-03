#!/bin/bash

set -e  # Exit on error
set -x  # Print each command as it runs

# Function to log messages with timestamp
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1"
}

# Function to log errors with timestamp
log_error() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] ERROR: $1" >&2
}

# Set timezone to Berlin
export TZ='Europe/Berlin'
log "Timezone set to $TZ"

# Function to get current date in YYYYMMDD format
get_current_date() {
    date +"%Y%m%d"
}

# Function to get the appropriate office based on current time
get_current_office() {
    local hour=$(date +"%H")
    log "Current hour: $hour"
    case $hour in
        06) echo "office-of-readings" ;;
        12) echo "morning-prayer" ;;
        22) echo "evening-prayer" ;;
        *) echo "" ;;
    esac
}

# Function to create and publish the note
publish_note() {
    local office=$1
    local relay=$2
    local date=$(get_current_date)
    local d_tag="liturgy-of-the-hours-for-${date}-${office}"
    local url="https://next-alexandria.gitcitadel.eu/publication?d=${d_tag}"
    
    log "Preparing to publish note for office: $office"
    log "Using relay: $relay"
    log "Date tag: $d_tag"
    log "URL: $url"
    
    # Create the note content
    local content="Today's ${office//-/ } is now available at ${url}"
    log "Note content: $content"
    
    # Publish using Sybil
    log "Executing: php bin/sybil note \"$content\" \"$relay\""
    if ! php bin/sybil note "${content}" "$relay" 2>&1; then
        log_error "Failed to publish note. Check the error messages above."
        return 1
    fi
    log "Note published successfully"
    return 0
}

# Main script
main() {
    log "Starting Office Notes script"
    
    # Check if we're in test mode
    local test_mode=false
    if [ "$1" = "--test" ]; then
        test_mode=true
        log "Running in test mode - will publish Office of Readings"
        relay="ws://localhost:8080"
    else
        relay="wss://christpill.nostr1.com"
    fi
    log "Using relay: $relay"

    # Get current office based on time (or force Office of Readings in test mode)
    if [ "$test_mode" = true ]; then
        current_office="office-of-readings"
        log "Test mode: Publishing Office of Readings..."
    else
        current_office=$(get_current_office)
        log "Current office determined: $current_office"
    fi

    # If we're not at a scheduled time and not in test mode, exit
    if [ -z "$current_office" ] && [ "$test_mode" = false ]; then
        log "Not a scheduled publishing time."
        exit 0
    fi

    # Publish the note
    log "Publishing note for ${current_office}..."
    if ! publish_note "$current_office" "$relay"; then
        log_error "Note publishing failed"
        exit 1
    fi
    log "Note publishing successful"
}

# Run the main function with any arguments
log "Script started with arguments: $@"
main "$@"
log "Script completed successfully" 