<?php

# esp/server/geoip.php
#
# This script fills geoip info into the DB asynchronously
#
# 1. Look in the DB for any IP addresses without geoip info
# 2. Request geoip info from ipgeolocation.io (50k/mo limit)
# 3. Fill the info in the DB

require_once("lib.php");

$db_devices  = array();
$updates     = array();

$db      = getdb();
$stmt    = "SELECT rowid, last_ip 
              FROM devices 
             WHERE last_location IS NULL"; 
$rv      = $db->query($stmt);

while ($device = $rv->fetchArray(SQLITE3_ASSOC)) {
    array_push($db_devices, $device);
}

$ips = array();

foreach ($db_devices as $device) {
    
    $add_ip = $device['last_ip'];
    
    if(in_array($add_ip, $ips)){
        print("Skipping duplicate ip". "\n");
        continue;
    }
    
    # API key that was running out constantly for some reason
    #$api_data = file_get_contents("https://api.ipgeolocation.io/ipgeo?apiKey=2ff6a3cfbac34272afc205e05a9d629c&ip=$device[last_ip]&fields=geo");
   
    $api_data = file_get_contents("https://api.ipgeolocation.io/ipgeo?apiKey=4b060192e84d485e9235eda6fee93121&ip=$add_ip&fields=geo");
   
    array_push($ips, $add_ip);
   
    if($api_data == null){
        print("breaking due to null" . "\n");
        break;
    }

    $geodata          = json_decode($api_data, true);
    $country          = $db->escapeString($geodata['country_name']);
    $ip               = $db->escapeString($geodata['ip']);
    $state_prov       = $db->escapeString($geodata['state_prov']);
    $city             = $db->escapeString($geodata['city']);
    $lat              = $db->escapeString($geodata['latitude']);
    $long             = $db->escapeString($geodata['longitude']);     
    $location         = "$geodata[city], $geodata[state_prov], $geodata[country_name]";
    #$db_ip            = $db->escapeString($ip);
    $db_last_location = $db->escapeString($location);

    $upd_stmt         = "UPDATE devices 
                            SET last_location = '$db_last_location',
                                last_country  = '$country',
                                last_prov     = '$state_prov',
                                last_city     = '$city',
                                last_lat      = '$lat',
                                last_long     = '$long'
                          WHERE last_ip       = '$ip'";
    
    array_push($updates, $upd_stmt);
    print("Added an entry for " . $ip . "\n");
    sleep(1);
}

$db->exec("BEGIN");

foreach ($updates as $update){
    $db->exec($update);
}

$db->exec("COMMIT");

?>
