<?php

# esp/server/server.php
#
# This script lets client machines check in over the internet
#
# 1. Take incoming connections from devices in the field
# 2. Record their presence in the database
# 3. If they are flagged in the DB, tell the client to open a connection

require_once("lib.php");

$base_port = 10000;
$port_gap = 1000;

# if they fail this basic check, bail
if (empty($_GET['id'])) { echo "INPUT ERROR"; exit; }

$id = $_GET['id'];
$ip = $_SERVER['REMOTE_ADDR'];
$db = getdb();

# get ready to check if this device is registered
$db_id = $db->escapeString($id);
$db_ip = $db->escapeString($ip);
$db_now = $db->escapeString(time());

# second arg true means "return row as array" instead of just the first val
$device = $db->querySingle("SELECT * FROM device WHERE id = '$db_id'", true);

# was this device in the DB?
if (!$device) {
    # nope... register it
    $db->exec("INSERT INTO device (id, registered_at, last_seen_at, last_ip)
               VALUES ('$db_id', '$db_now', '$db_now', '$db_ip')");
} else {
    # yep... update the record
    $db->exec("UPDATE device SET last_seen_at = '$db_now', last_ip = '$db_ip'
                WHERE id = '$db_id'");
}

# did the device ask us to update anything?
if (!empty($_GET['connected'])) {
    # the device reports that it connected successfully
    $db->exec("UPDATE device SET state = 'connected' WHERE id = '$db_id'");

} else if (!empty($_GET['error'])) {
    # the device reports that there was an error
    $db->exec("UPDATE device SET state = 'error', offset = NULL WHERE id = '$db_id'");

} else if (!empty($_GET['disconnected'])) {
    # the device reports that it disconnected successfully
    $db->exec("UPDATE device SET state = 'disconnected', offset = NULL WHERE id = '$db_id'");

# TODO these should be separate from the logic above - allow an update
# and status check at once -- but you have to update checker.php as well
} else if ($device['state'] == 'requested') {

    # an admin requested this device to connect, so we tell the device
    # to connect here -- providing a unique port number offset
    $offset = $db->querySingle(
        "UPDATE device SET offset = (" .
        "SELECT COALESCE(MAX(offset), $base_port) + $port_gap FROM device" .
        ") WHERE id = '$db_id'"
    );
    $device = $db->querySingle("SELECT * FROM device WHERE id = '$db_id'", true);
    echo "CONNECT " . $device['offset'];
    exit;

} else if ($device['state'] == 'dismissed') {

    echo "DISCONNECT";
    exit;

}

echo "OK";

?>
