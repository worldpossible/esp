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

    .editicon {
        width: 24px; height: 24px;
        background-repeat: no-repeat;
        background-image: url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABgAAAAYCAYAAADgdz34AAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAAdNJREFUeNrMlUEoBGEUx7+Z3YmT5mBLjsrBYTnZHByQKO5CuTlycJaUIg4c18lNUiSKFOWyShxQcnFYkcMuq0TtZXdm/N/2Zpv5dkw7s2159avp1bz/9973vfcUy7JEPU0Vdba6C0Ttj3w+L1KplDBNM0ycJrAGzvH/QSKRELFYzC1QKBREOp0WhmEIRVGCVuEE9ILpYrE4EY/H9ysyoKCapglVVYMININ2cMkCUfy7Bzrx/eASIA0wA/r526+sz2AF7IAeMAh+wDJ4BB8VGcBawIbk87JPsAm2+DBkp2AMzHK5Ml4CGoiAJzD1R3CDT7oORqRLXuAyeb8imMWloQA3PuU5BsOS/w6MV9sHqs9TPPMIfg2GwFstjUbBDx01t41ez6KHP5BAKzjyCHIF5kASdNciQOn3Sb5bvuQO0Aa+wwpMgi8wLwUfZX+Do4dCCdDJd7nWq+CeT55xvLyapmkXn3KboXeeDTVNpX6wbQm8g1eQ40aTGy+wgMZDjOyC60s+XcrYZJ/vHbg6mUY1iGMavlSRPWXaKAtQDOcaLgtgTGd1XU9iYQxAIBKgzDnuk5JhH5TGfnlEO9V4mwUJ7nkPtE/sneK6A1o21Vzcv1r6vwIMAIDAeNAWIPyaAAAAAElFTkSuQmCC);
    }

</style>
<script src="jquery.2.1.4.min.js"></script>
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
                        "<td id='notes-" + dev.id + "'>" + (dev.notes === null ? "" : dev.notes) + 
                        "<div class='editicon'></div></td>" +
                        "</tr>"
                    );
                    if (dev.state == "connected") {
                        $("#devices").append(
                            "<tr><td colspan='5' class='info' id='info-" + dev.id + "'>" +
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
    <th>Notes<th>
</tr>
<tbody id="devices">
</tbody>
</table>
<small>Note: it can take up to 20 seconds for a connection to be established</small>
</body>
</html>
