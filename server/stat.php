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

$id = $_GET['id'];

# if they fail this basic check, bail
if (!$id) {
    header("HTTP/1.1 500 Internal Server Error");
    exit;
}

$stale_request_time = 300; # if it's not resolved in five minutes...

$db = getdb();
$db_id = $db->escapeString($id);
$json_out = '{"id":"' . $db_id . '"}';

# handle ajax request for connection
if (!empty($_GET['connect'])) {
    $rv = $db->exec("UPDATE device SET state = 'requested' WHERE id = '$db_id'");
} else if (!empty($_GET['disconnect'])) {
    $rv = $db->exec("UPDATE device SET state = 'dismissed' WHERE id = '$db_id'");
} else {
    # second arg true means "return row as array" instead of just the first val
    $rv = $db->querySingle("SELECT * FROM device WHERE id = '$db_id'", true);

    if ($rv) {
        # detect stale requests and flag them
        if (($rv['state'] == "requested" || $rv['state'] == "dismissed")
                && $rv['last_seen_at'] < (time() - $stale_request_time)) {
            # we could go with "failed" here, but for now...
            $db->exec("UPDATE device SET state = 'disconnected' WHERE id = '$db_id'");
            $rv['state'] = "disconnected";
        }
        $json_out = json_encode($rv);
    }
    
}

header("Content-Type: application/json");
if (!$rv) {
    header("HTTP/1.1 500 Internal Server Error");
    echo $json_out;
} else {
    header("HTTP/1.1 200 OK");
    echo $json_out;
}

?>
