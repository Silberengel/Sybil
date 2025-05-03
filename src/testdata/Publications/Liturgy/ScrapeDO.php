<?php

include 'DivineOffice.php';

function printUsage() {
    fwrite(STDERR, "Usage: php ScrapeDO.php <start_date> [end_date]\n");
}

function isValidArgCount($argc) {
    return $argc >= 2;
}

function getArg($argv, $index) {
    return $argv[$index] ?? null;
}

function normalizeDate($date) {
    if (isTypicalDate($date)) return toYmdFromTypicalDate($date);
    if (isBackwardsDate($date)) return toYmdFromBackwardsDate($date);
    if (isNoHyphens($date)) return toYmdFromNoHyphens($date);
    return false;
}

function isTypicalDate($date) {
    return preg_match('/^\d{2}[-\/\.]\d{2}[-\/\.]\d{4}$/', $date);
}

function isBackwardsDate($date) {
    return preg_match('/^\d{4}[-\/\.]\d{2}[-\/\.]\d{2}$/', $date);
}

function isNoHyphens($date) {
    return preg_match('/^\d{8}$/', $date);
}

function toYmdFromTypicalDate($date) {
    $dt = DateTime::createFromFormat('d-m-Y', str_replace(['/', '.'], '-', $date));
    return $dt ? $dt->format('Y-m-d') : false;
}

function toYmdFromBackwardsDate($date) {
    $dt = DateTime::createFromFormat('Y-m-d', str_replace(['/', '.'], '-', $date));
    return $dt ? $dt->format('Y-m-d') : false;
}

function toYmdFromNoHyphens($date) {
    $dt = DateTime::createFromFormat('Ymd', $date);
    return $dt ? $dt->format('Y-m-d') : false;
}

function errorAndExit($msg) {
    fwrite(STDERR, $msg . "\n");
    exit(1);
}

function runScraper($start, $end) {
    $scraper = new DivineOfficeScraper();
    $scraper->run($start, $end);
}

function main($argc, $argv) {
    if (!isValidArgCount($argc)) {
        printUsage();
        exit(1);
    }
    $start = normalizeDate(getArg($argv, 1));
    $end = getArg($argv, 2) ? normalizeDate(getArg($argv, 2)) : $start;
    if (!$start || !$end) errorAndExit("Invalid date format. Please use dd-mm-yyyy.");
    runScraper($start, $end);
}

main($argc, $argv);