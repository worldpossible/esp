<?php

# this is the unit's ID. It must be unique. If two go
# out with the same ID, then we won't be able to tell
# them apart in the field, and may not be able to connect
# to them at all if they're both online at the same time.
$id   = "1";

# for this reason, we append the last four of the MAC address.
$id .= "-" . exec("ifconfig | grep eth0 | awk '{ print $5 }' | sed s/://g | grep -o '.\{4\}$'");

echo "Device ID: $id\n";
    
# checker.php
# 
# This script checks in with the dev server, then polls it
# to see if we want it to connect. If it gets a request to
# connect, it creates ssh tunnels for our desired ports, and
# then manages those processes.
#

# these are the ports we want to tunnel
$ports = array(
    "80",   # <- http
    "81",   # <- kiwix
    "8008", # <- kalite
    "22"    # <- ssh
);

$host = "dev.worldpossible.org";
$name = "esp";
$url  = "http://$host/$name/server.php";
$findflag = "$name-findflag"; # for finding our procs

# we use these as sensible limits for when the
# server tells us to use a different interval
$std_interval = 2; #60; # default polling rate, in seconds
$min_interval = 2; #5;  # fastest allowed polling rate
$max_interval = 60 * 60; # slowest allowed
$interval = $std_interval;

# check if there is already an ssh tunnel
echo  "Startup check\n";
$pids = getpids();
if ($pids) {
    echo "Existing processes -- mark as conntected\n";
    $response = file_get_contents( "$url?id=$id&connected=1" );
} else {
    echo "No existing processes -- mark as disconntected\n";
    $response = file_get_contents( "$url?id=$id&disconnected=1" );
}

# enter our loop
while (true) {

    $response = file_get_contents( "$url?id=$id" );

    echo "Server says: $response\n";

    if ($response == "OK") {

        # The most common response is just "OK" (with no number), which
        # requires no action at all. Everything else is handled below:

    } else if (preg_match("/OK (\d+)/", $response, $match)) {

        # This means the server is requesting you change
        # the polling frequency -- this allows us to tell
        # devices to back off under load

        $interval = $match[1];
        if ($interval < $min_interval) {
            $interval = $min_interval;
        } else if ($interval > $max_interval) {
            $interval = $max_interval;
        }
        echo "Polling interval set to: $interval\n";

#    } else if ($response == "CONNECT") {
    } else if (preg_match("/CONNECT (\d+)/", $response, $match)) {

        # This requests a connection -- we use the number provided
        # as the starting point for the new port numbers
        $offset = $match[1];

        # clear out anything that's there
        killpids();

        # connect each port, using sequential numbers starting at the offset
        $i = 0;
        foreach ($ports as $rport) {
            # a bit complex - we need to
            # 1) specify the private key for login
            # 2) add a harmless string that will let us find the ssh process
            # 3) skip host checking
            # 4) configure some thigns so that failed connections get closed
            # 5) run the actual revesre tunnel on the right port
            # 6) send all output to the dustbin so PHP can return
            $lport = $offset + $i++;
            exec(
                "ssh -i /root/$name.sshkey -S $findflag " .
                "-o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no " .
                "-o ExitOnForwardFailure=yes -o ServerAliveInterval=20 -o ServerAliveCountMax=3 " .
                "-fN -R $lport:localhost:$rport $name@$host > /dev/null 2>&1 &",
                $output, $retval
            );
            if ($retval > 0) {
                $output = implode("\n", $output);
                echo "Error: ($retval) - $output\n";
                $response = file_get_contents( "$url?id=$id&error=1" );
                # XXX probably should bail here and kill off
                # any tunnels that were successfully connected
            }
        }

        echo "Waiting for tunnels to fork\n";
        $success = false;
        $retries = 0;
        $max_retries = 10;
        while (true) {
            $pids = getpids();
            #echo "pids: " . count($pids) . ", ports: " . count($ports) . "\n";
            if (count($pids) == count($ports)) {
                $success = true;
                break;
            }
            if (++$retries > $max_retries) {
                break;
            }
            sleep(1);
        }

        if ($success) {
            echo "Tunneling success\n";
            $response = file_get_contents( "$url?id=$id&connected=1" );
        } else {
            echo "Tunneling failed\n";
            $response = file_get_contents( "$url?id=$id&error=1" );
        }

        # once we're connected, we poll quickly so we can
        # pick up disconnect messages quickly
        $interval = $min_interval;

    } else if ($response == "DISCONNECT") {

        killpids();
        $response = file_get_contents( "$url?id=$id&disconnected=1" );

	# after disconnecting, we go back to the standard polling rate
        $interval = $std_interval;
        
    }

# XXX we don't bother checking the pids for now -- until we get around
# to writing the code to do something useful with lost connections
#    if ($pids) {
#        echo "CHECKING pids:\n";
#        $newpids = array();
#        foreach ($pids as $pid) {
#            echo "\t$pid...";
#            if (!$pid || ! file_exists("/proc/$pid")) {
#                $response = file_get_contents( "$url?id=$id&error=1" );
#                echo "GONE.\n";
#            } else {
#                echo "OK.\n";
#                array_push($newpids, $pid);
#            }
#        }
#        # we refill it with only the pids that still existed
#        $pids = $newpids;
#    }

    # and now we chill
    sleep($interval);

}

function getpids() {

    global $findflag;

    # the ' ? ' detects the forked/detached tunnel, as opposed to the
    # short-lived process that forks it off (they otherwise look the same)
    exec("ps x | grep $findflag | grep ' ? ' | grep -v grep", $output, $retval);

    $pids = array();
    if ($output) {
        foreach ($output as $proc) {
            $pid = preg_replace("/^ *(\d+).*/", "$1", $proc);
            array_push($pids, $pid);
        }
    }

    return $pids;

}

function killpids() {
    global $pids;
    if ($pids) {
        foreach ($pids as $pid) {
            echo "Killing $pid...\n";
            exec("kill $pid");
            # check result?
        }
        $pids = array();
    }
}

?>
