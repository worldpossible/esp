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
    }

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

    var hostname = window.location.hostname;

    function checkall() {
        $.ajax({
            url: "stat.php",
            success: function(response) {
                $("#msg").html("");
                $("#msg").hide();
                var arrayLength = response.length;
                $("#devices").empty();
                for (var i=0; i < arrayLength; ++i) {

                    var dev = response[i];
                    $("#devices").append(
                        "<tr>" +
                        "<td><a href='#' onclick=\"connect('" + dev.id + "'); return false;\">" + dev.id + "</a></td>" +
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
                // we ignore errors for now, except to log them
                console.log(xhr);
                $("#msg").html(xhr.statusText + "\: " + xhr.responseText + ", retrying...");
                $("#msg").show();
            },
            complete: function() {
                setTimeout(checkall, 2000);
            },
        });
    }

    // onload
    $(checkall);

</script>
</head>
<body>
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
