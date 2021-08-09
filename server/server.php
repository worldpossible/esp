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
$port_gap  = 1000;

# if they fail this basic check, bail
if (empty($_GET['id'])) { 
    echo "INPUT ERROR"; 
    exit; 
}

$id = strtolower($_GET['id']);

# Check that the ID provided is the HEX
if(!ctype_xdigit($id)){
    echo "Non-hexidecimal ID provided";
    exit;
}

# Check that the ID provided is 6 chars ( 3 octets )
if(strlen($id) != 6){
    echo "Malformed ID provided";
    exit;
}

$ip = $_SERVER['REMOTE_ADDR'];
$db = getdb();

# get ready to check if this device is registered
$db_id    = $db->escapeString($id);
$db_ip    = $db->escapeString($ip);
$db_now   = $db->escapeString(time());
$do_geoip = false;

# second arg true means "return row as array" instead of just the first val
$stmt   = "SELECT rowid, * 
             FROM devices 
            WHERE id = '$db_id'";
$device = $db->querySingle($stmt, true);

# was this device in the DB?
if (!$device) {
    # nope... register it
    $stmt = "INSERT INTO devices (id, registered_at, last_seen_at, last_ip)
                  VALUES ('$db_id', '$db_now', '$db_now', '$db_ip')";
    
    $db->exec($stmt);
    $do_geoip = true;
} else {
    # yep... update the record
    $db_rowid = $db->escapeString($device['rowid']);

    if ($device['last_ip'] != $db_ip) {
        # we have to blank the location if the ip has changed
        $stmt = "UPDATE devices
                    SET last_seen_at = '$db_now',
                        last_ip = '$db_ip',
                        last_location = null
                  WHERE rowid = '$db_rowid'";
        
        $db->exec($stmt);
        $do_geoip = true;
    } else {
        # but we don't want to blank it if it's the same because
        # we don't want to have to do a new geoip lookup
        $stmt = "UPDATE devices 
                    SET last_seen_at = '$db_now' 
                  WHERE rowid = '$db_rowid'";
        $db->exec($stmt);
    }
}

# run the geoip script asynchronously if we've got new ip data
if ($do_geoip) {
    exec("php geoip.php > /dev/null &");
}

# did the device ask us to update anything?
if (!empty($_GET['connected'])) {
    $stmt = "UPDATE devices 
                SET state = 'connected' 
              WHERE rowid = '$db_rowid'";
              
    # the device reports that it connected successfully
    $db->exec($stmt);

} else if (!empty($_GET['error'])) {
    $db_rowid = $db->escapeString($device['rowid']);
    
    # the device reports that there was an error
    $stmt = "UPDATE devices 
                SET state = 'error', offset = NULL 
              WHERE rowid = '$db_rowid'";
    $db->exec($stmt);

} else if (!empty($_GET['disconnected'])) {
    # the device reports that it disconnected successfully
    $stmt = "UPDATE devices
                SET state = 'disconnected', offset = NULL 
              WHERE rowid = '$db_rowid'";
    $db->exec($stmt);

# TODO these should be separate from the logic above - allow an update
# and status check at once -- but you have to update checker.php as well
} else if ($device['state'] == 'connecting') {
    # an admin requested this device to connect, so we tell the device
    # to connect here -- providing a unique port number offset
    $stmt = "UPDATE devices 
                SET offset = (" .  "SELECT COALESCE(MAX(offset), $base_port) + $port_gap FROM devices" .
           ") WHERE rowid = '$db_rowid'";
    
    $offset = $db->querySingle($stmt);
    
    $stmt   = "SELECT * 
                 FROM devices
                WHERE rowid = '$db_rowid'";
    $device = $db->querySingle($stmt, true);

    echo "CONNECT " . $device['offset'];
    exit;

} else if ($device['state'] == 'disconnecting') {

    echo "DISCONNECT";
    exit;
}

echo "OK";

?>
