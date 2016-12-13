<?php

# esp/server/stat.php
#
# This script is polled via ajax by the view.php script
# to keep the list of connections updated
#
# Take requests from the admin front end (via ajax)
# and either:
#   a) update connect/disconnect if asked, return id in json
#   b) get info about the device, return in json
#
# You must always return the ID or the front end won't
# know what device you're talking about

require_once("lib.php");
$db = getdb();
$json_out = "{}";

# main logic
if (empty($_GET['id'])) {

    # get and display info on all (recently active) devices
    $db_24hrs = $db->escapeString(time()-86400);
    $rv = $db->query("SELECT * FROM device WHERE last_seen_at > '$db_24hrs'");
    if ($rv) { # detect DB error
        $devices = array();
        while ($row = $rv->fetchArray(SQLITE3_ASSOC)) {
            $row = process_device($row);
            array_push($devices, $row);
        }
        $json_out = json_encode($devices); # this can be an empty array
    }

} else {

    # all device-specific requests below
    # -- the rest of the code can use these
    $db_id = $db->escapeString($_GET['id']);
    # even in errors, we return the id so the client
    # knows what device had the problem
    $json_out = '{"id":"' . $db_id . '"}';

    if (!empty($_GET['connect'])) {
        # handle ajax request for connection
        $rv = $db->exec("UPDATE device SET state = 'requested' WHERE id = '$db_id'");
    } else if (!empty($_GET['disconnect'])) {
        # handle ajax request for disconnection
        $rv = $db->exec("UPDATE device SET state = 'dismissed' WHERE id = '$db_id'");
    } else {

        # id specified, but not an action -- we return info on that id
        # note: calling querySingle() with the second arg true means
        # "return whole row as hash" instead of just the first column value
        $rv = $db->querySingle("SELECT * FROM device WHERE id = '$db_id'", true);
        if ($rv) { $json_out = json_encode(process_device($rv)); }

    }

}

header("Content-Type: application/json");
if (!$rv) {
    error_exit($json_out);
} else {
    header("HTTP/1.1 200 OK");
    echo $json_out;
}

exit;

# we use this to prepare each row from the database before
# returning it to the client (view.php)
function process_device($dev) {

    global $db;
    $stale_request_time = 60 * 5; # if it's not resolved in five minutes...
    # detect stale connections and flag them
    if (($dev['state'] == "requested" || $dev['state'] == "dismissed")
            && $dev['last_seen_at'] < (time() - $stale_request_time)) {
        $db_id = $db->escapeString($dev['id']);
        $db->exec("UPDATE device SET state = 'disconnected' WHERE id = '$db_id'");
        $dev['state'] = "disconnected";
    }
    $dev['last_seen_at'] = time_ago($dev['last_seen_at']);

    return $dev;
}

?>
