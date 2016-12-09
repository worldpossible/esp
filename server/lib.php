<?php

# esp/server/lib.php
#
# This file provides a few library functions that are shared
# between the other esp/server scripts

function getdb() {

    # we need to keep a copy so we can close it in a callback later
    global $_db;
    # and also because caching per-request is smart
    if (isset($_db)) { return $_db; }
    
    # open the database connection
    try {
        $dbfile = "esp.sqlite";
        $_db = new SQLite3($dbfile);
        $_db->busyTimeout(5000);
        @chmod($dbfile, 0666);
    } catch (Exception $ex) {
        echo "DB ERROR 1"; exit;
    }

    # just make the table if it isn't there
    $rv = $_db->exec("
        CREATE TABLE IF NOT EXISTS device (
            id VARCHAR(255) UNIQUE,
            registered_at INTEGER, -- timestamp
            last_seen_at  INTEGER, -- timestamp
            last_ip VARCHAR(15),
            state VARCHAR(255) -- requested|connected|dismissed|disconnected|error
        )
    ");

    if (!$rv) { echo "DB ERROR 2"; exit; }

    return $_db;

}

#-------------------------------------------
# If we don't do this, dangling filehandles build up
# and after a while we can't open any more... yikes. 
#-------------------------------------------
function cleanup() {
    global $_db;
    if (isset($_db)) {
        $_db->close();
        unset($_db);
    }
}

register_shutdown_function('cleanup');

# lifted from http://www.phpdevtips.com/2011/06/the-php-time-ago-function/
# plus some small tweaks
function time_ago($unix_date) {

    if (empty($unix_date)) {
        return "No date provided";
    }

    $periods = array(
        "second", "minute", "hour", "day", "week", "month", "year", "decade"
    );
    $lengths = array("60", "60", "24", "7", "4.35", "12", "10");
    $now = time();

    if ($now >= $unix_date) {
        $difference = $now - $unix_date;
        $tense = "ago";
    } else {
        $difference = $unix_date - $now;
        $tense = "from now";
    }

    for (
        $j = 0;
        $difference >= $lengths[$j] && $j < count($lengths) - 1;
        $j++
    ) {
        $difference /= $lengths[$j];
    }

    $difference = round($difference);

    if ($difference != 1) {
        $periods[$j] .= "s";
    }

    return "$difference $periods[$j] {$tense}";

}

?>
