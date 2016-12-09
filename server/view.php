<?php

# esp/server/view.php
#
# Displays a list of available RACHEL devices,
# provides ability to connect/disconnect to them

?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Remote Service - RACHEL</title>
<style>

    body {
        font-family: sans-serif;
        color: #999;
    }
    table {
        border-spacing: 10px;
    }
    td {
        border-top: 1px solid #ccc;
        padding: 10px 10px 0 10px;
    }
    a {
        color: #66c;
        text-decoration: none;
    }
    a:hover {
        text-decoration: underline;
    }

    .info {
        background: #eee;
        border-bottom-left-radius: 10px;
        border-bottom-right-radius: 10px;
    }
    .info label, a, input {
        display: block;
        float: left;
        margin-bottom: 5px;
    }
    .info label {
        text-align: right;
        padding-right: 10px;
        width: 120px;
    }
    .info br { clear: both; }
    .info button { float: right; }
    .info input { width: 240px; }


</style>
<script src=" ../jquery.2.1.4.min.js"></script>
<script>

    var checkInterval = 1000; // polling delay (in ms) for connection check

    // requests a connection
    function connect(id) {
        $("#constat-"+id).html("requesting (1)");
        $.ajax({
            url: "stat.php?connect=1&id=" + id,
            success: function(response) {
                $("#constat-"+response.id).html("requested (1)");
                setTimeout("check('"+id+"')", checkInterval);
            },
            error: function(xhr) {
                response = $.parseJSON(xhr);
                $("#constat-").html("error (a)");
            }
        });
        return false;
    }

    // requests a disconnection
    function disconnect(id) {
        $("#constat-"+id).html("dismissing (2)");
        $.ajax({
            url: "stat.php?disconnect=1&id=" + id,
            success: function(response) {
                $("#constat-"+response.id).html("dismissed (2)");
                setTimeout("check('"+id+"')", checkInterval);
            },
            error: function(xhr) {
                response = $.parseJSON(xhr);
                $("#constat-"+response.id).html("error (b)");
            }
        });
        return false;
    }

    // polls to see if a connection was made 
    function check(id) {
        $.ajax({
            url: "stat.php?id=" + id,
            success: function(response) {
                $("#constat-"+response.id).html(response.state + " (3)");
                if (response.state == "connected") {
                    $("#info-"+response.id).show();
                } else {
                    $("#info-"+response.id).hide();
                    if (response.state != "disconnected") {
                        // in process... set it to check again
                        setTimeout("check('"+id+"')", checkInterval);
                    }
                }
            },
            error: function(xhr) {
                response = $.parseJSON(xhr);
                $("#constat-"+response.id).html("error (c)");
            }
        });
    }

</script>
</head>
<body>
<h2>Remote Service - RACHEL</h2>
<table>
<tr>
    <th>Device ID</th>
    <th>Last Seen</th>
    <th>Last IP</th>
    <th>Connection</th>
</tr>
<?php 

require_once("lib.php");
$db = getdb();

# get and display info on all devices
$rv = $db->query("SELECT * FROM device");
$tocheck = array();
while ($row = $rv->fetchArray()) {

    $constat = "--";
    $showinfo = false;
    if (!empty($row['state'])) {
        $constat = $row['state'] . " (4)";
        if ($row['state'] == "connected") {
            $showinfo = true;
        } else if ($row['state'] != "disconnected") {
            array_push($tocheck, $row['id']);
        }
    }
    $row['last_seen_at'] = time_ago($row['last_seen_at']);

    if ($showinfo) {
        $showinfo = "";
    } else {
        $showinfo = " style='display: none;'";
    }

    echo <<<____END
        <tr>
        <td><a href="#" onclick="connect('$row[id]'); return false;">$row[id]</a></td>
        <td>$row[last_seen_at]</td>
        <td>$row[last_ip]</td>
        <td id="constat-$row[id]">$constat</td>
        </tr>
        <tr>
        <td colspan="4" class="info" id="info-$row[id]"$showinfo>
        <label>RACHEL Index</label> <a href="//$_SERVER[SERVER_NAME]:10080/" target="_blank">http://$_SERVER[SERVER_NAME]:10080/</a><br>
        <label>Kiwix Library</label> <a href="//$_SERVER[SERVER_NAME]:10081/" target="_blank">http://$_SERVER[SERVER_NAME]:10081/</a><br>
        <label>KA-Lite Index</label> <a href="//$_SERVER[SERVER_NAME]:18008/" target="_blank">http://$_SERVER[SERVER_NAME]:18008/</a><br>
        <label>SSH root</label> <input value="ssh root@$_SERVER[SERVER_NAME] -p 10022" onclick="this.select();"><br>
        <button onclick="disconnect('$row[id]')">disconnect</button>
        </td>
        </tr>
____END;
    

}
echo "\n</table>\n\n";

# if there are devices in "requested" mode when we get here,
# fire off a check process for each of them...
if ($tocheck) {
    echo "<script>\n\$(function() {\n";
    foreach ($tocheck as $id) {
        echo "\tsetTimeout(\"check('$id')\", checkInterval);\n";
    }
    echo "});\n</script>\n";
}


?>
</body>
</html>
