<?php
    require_once("lib.php");
    if (!authorized()) { exit(); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>esp - RACHEL</title>
<!--

# esp/server/view.php
#
# Displays a list of available RACHEL devices,
# provides ability to connect/disconnect to them

-->
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
        padding: 10px;
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

    #msg {
        background: #fcc;
        color: #900;
        font-weight: bold;
        display: inline-block;
        padding: 10px;
        border-radius: 5px;
        margin-bottom: 10px;
    }

</style>
<script src=" ../jquery.2.1.4.min.js"></script>
<script>

    // requests a connection
    function connect(id) {
        $("#constat-"+id).html("connecting... (1)");
        $.ajax({
            url: "stat.php?connect=1&id=" + id,
            success: function(response) {
                $("#constat-"+response.id).html("connecting... (2)");
                //setTimeout("check('"+id+"')", checkInterval);
            },
            error: function(xhr) {
                response = $.parseJSON(xhr);
                $("#constat-").html("connection error (1)");
            }
        });
        return false;
    }

    // requests a disconnection
    function disconnect(id) {
        $("#constat-"+id).html("disconnecting... (1)");
        $.ajax({
            url: "stat.php?disconnect=1&id=" + id,
            success: function(response) {
                $("#constat-"+response.id).html("disconnecting... (2)");
                //setTimeout("check('"+id+"')", checkInterval);
            },
            error: function(xhr) {
                response = $.parseJSON(xhr);
                $("#constat-"+response.id).html("disconnection error (1)");
            }
        });
        return false;
    }

    var checkInterval = 2000; // polling delay (in ms) for connection check
    var hostname = window.location.hostname;

    // get info on all devices
    function checkall() {
        $.ajax({
            url: "stat.php",
            success: function(response, status, xhr) {

                // check that it's a valid response
                if (xhr.getResponseHeader("content-type") != "application/json") {
                    $("#msg").html("Invalid response from server, retrying... (did you log out?)");
                    $("#msg").show();
                    return;
                }

                $("#msg").html("");
                $("#msg").hide();

                var arrayLength = response.length;
                $("#devices").empty();
                for (var i=0; i < arrayLength; ++i) {

                    var dev = response[i];
                    if (dev.state == "connecting" || dev.state == "disconnecting") { dev.state += "... (3)"; }
                    if (dev.is_stale) { devlink = dev.id; } else { 
                        devlink = "<a href='#' onclick=\"connect('" + dev.id + "'); return false;\">" + dev.id + "</a>";
                    }
                    $("#devices").append(
                        "<tr>" +
                        "<td>" + devlink + "</a></td>" +
                        "<td>" + dev.last_seen_at + "</td>" +
                        "<td>" + dev.last_ip + "</td>" +
                        "<td id='constat-" + dev.id + "'>" + dev.state + "</td>" +
                        "</tr>"
                    );

                    if (dev.state == "connected") {
                        $("#devices").append(
                            "<tr><td colspan='4' class='info' id='info-" + dev.id + "'>" +
                            "<label>RACHEL Index</label> <a href='//" + hostname + ":" + dev.offset +
                            "/' target='_blank'>http://" + hostname + ":" + dev.offset +"/</a><br>" +
                            "<label>Kiwix Library</label> <a href='//" + hostname + ":" + (dev.offset + 1) +
                            "/' target='_blank'>http://" + hostname + ":" + (dev.offset + 1) + "/</a><br>" +
                            "<label>KA-Lite Index</label> <a href='//" + hostname + ":" + (dev.offset + 2) +
                            "/' target='_blank'>http://" + hostname + ":" + (dev.offset + 2) + "/</a><br>" +
                            "<label>SSH root</label> <input value='ssh root@" + hostname +
                            " -p " + (dev.offset + 3)  + "' onclick='this.select();'><br>" +
                            "<button onclick=\"disconnect('" + dev.id + "')\">disconnect</button>" +
                            "</td></tr>"
                        );
                    }
                    
                }

            },
            error: function(xhr) {
                $("#msg").html(xhr.statusText + "\: " + xhr.responseText + ", retrying...");
                $("#msg").show();
            },
            complete: function() {
                setTimeout(checkall, checkInterval);
            },
        });
    }

    // onload
    $(checkall);

</script>
</head>
<body>
<div style="float: right;"><a href="logout.php">logout</a></div>
<h2>esp - RACHEL</h2>
<div id="msg" style="display: none;"></div>
<table>
<tr>
    <th>Device ID</th>
    <th>Last Seen</th>
    <th>Last IP</th>
    <th>Connection</th>
</tr>
<tbody id="devices">
</tbody>
</table>
</body>
</html>
