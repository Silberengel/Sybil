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

# Function to check if a file exists
check_file_exists() {
    if [ ! -f "$1" ]; then
        log_error "File $1 does not exist"
        return 1
    fi
    log "File $1 exists and is accessible"
    return 0
}

# Main script
main() {
    log "Starting Divine Office publication script"
    
    # Check if we're in test mode
    local test_mode=false
    if [ "$1" = "--test" ]; then
        test_mode=true
        log "Running in test mode"
        relay="ws://localhost:8080"
    else
        # Check if it's 2 AM
        local hour=$(date +"%H")
        log "Current hour: $hour"
        if [ "$hour" != "02" ]; then
            log "Not 2 AM yet. Exiting."
            exit 0
        fi
        relay="wss://thecitadel.nostr1.com"
    fi
    log "Using relay: $relay"

    # Get current date
    current_date=$(get_current_date)
    log "Current date: $current_date"
    output_file="src/testdata/Publications/Liturgy/output_modern/${current_date}.adoc"
    log "Output file path: $output_file"
    
    # Run the Divine Office scraper
    log "Running Divine Office scraper for ${current_date}..."
    if ! php src/testdata/Publications/Liturgy/ScrapeDO.php $current_date 2>&1; then
        log_error "Scraping failed. Check the error messages above."
        exit 1
    fi
    log "Scraping completed successfully"
    
    # Verify the output file exists
    if ! check_file_exists "$output_file"; then
        log_error "Scraped file not found at $output_file"
        exit 1
    fi
    
    # Publish the AsciiDoc file
    log "About to publish hours with sybil..."
    log "Command: php bin/sybil publication \"$output_file\" \"$relay\""
    if ! php bin/sybil publication "$output_file" "$relay" 2>&1; then
        log_error "Publication failed. Check the error messages above."
        exit 1
    fi
    log "Publication successful"
}

# Run the main function with any arguments
log "Script started with arguments: $@"
main "$@"
log "Script completed successfully" 