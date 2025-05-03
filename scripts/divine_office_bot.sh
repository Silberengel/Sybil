#!/bin/bash

set -e  # Exit on error
set -x  # Print each command as it runs

# Set timezone to Berlin
export TZ='Europe/Berlin'

# Function to get current date in YYYYMMDD format
get_current_date() {
    date +"%Y%m%d"
}

# Function to get the appropriate office based on current time
get_current_office() {
    local hour=$(date +"%H")
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
    local date=$(get_current_date)
    local d_tag="liturgy-of-the-hours-for-${date}-${office}"
    local url="https://next-alexandria.gitcitadel.eu/publication?d=${d_tag}"
    
    # Create the note content
    local content="Today's ${office//-/ } is now available at ${url}"
    
    # Publish using Sybil
    php bin/sybil note "${content}" wss://christpill.nostr1.com
}

# Function to check if a file exists
check_file_exists() {
    if [ ! -f "$1" ]; then
        echo "Error: File $1 does not exist"
        return 1
    fi
    return 0
}

# Main script
main() {
    # Check if we're in test mode
    local test_mode=false
    if [ "$1" = "--test" ]; then
        test_mode=true
        echo "Running in test mode - will publish Office of Readings"
    fi

    # Get current date
    current_date=$(get_current_date)
    output_file="src/testdata/Publications/Liturgy/output_modern/${current_date}.adoc"
    
    # Run the Divine Office scraper
    echo "Running Divine Office scraper for ${current_date}..."
    if ! php src/testdata/Publications/Liturgy/ScrapeDO.php $current_date; then
        echo "Error: Scraping failed"
        exit 1
    fi
    
    # Verify the output file exists
    if ! check_file_exists "$output_file"; then
        echo "Error: Scraped file not found at $output_file"
        exit 1
    fi
    
    # Publish the AsciiDoc file
    echo "About to publish hours with sybil..."
    if ! php bin/sybil publication "$output_file"; then
        echo "Error: Publication failed"
        exit 1
    fi
    echo "Publication successful"

    # Get current office based on time (or force Office of Readings in test mode)
    if [ "$test_mode" = true ]; then
        current_office="office-of-readings"
        echo "Test mode: Publishing Office of Readings..."
    else
        current_office=$(get_current_office)
    fi

    # If we're at one of the scheduled times or in test mode, publish the note
    if [ ! -z "$current_office" ]; then
        echo "Publishing note for ${current_office}..."
        if ! publish_note "$current_office"; then
            echo "Error: Note publishing failed"
            exit 1
        fi
        echo "Note publishing successful"
    else
        echo "Not a scheduled publishing time."
    fi
}

# Run the main function with any arguments
main "$@" 