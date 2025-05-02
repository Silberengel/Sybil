#!/bin/bash

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
    php bin/sybil.php note "${content}"
}

# Main script
main() {
    # Get current date
    current_date=$(get_current_date)
    
    # Run the Divine Office scraper
    echo "Running Divine Office scraper for ${current_date}..."
    php src/testdata/Publications/Liturgy/DivineOffice.php
    
    # Publish the AsciiDoc file
    echo "Publishing AsciiDoc file..."
    php bin/sybil.php publication "src/testdata/Publications/Liturgy/output_modern/${current_date}.adoc"
    
    # Get current office based on time
    current_office=$(get_current_office)
    
    # If we're at one of the scheduled times, publish the note
    if [ ! -z "$current_office" ]; then
        echo "Publishing note for ${current_office}..."
        publish_note "$current_office"
    else
        echo "Not a scheduled publishing time."
    fi
}

# Run the main function
main 