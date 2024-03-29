<!--
# esp/server/view.php
#
# Displays a list of available RACHEL devices,
# provides ability to connect/disconnect to them
-->

<?php
    require_once("lib.php");
    if (!authorized()) { exit(); }
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>esp - Home</title>
    <link rel="icon" type="image/png" href="assets/images/favicon.png"/>
    <link href="assets/fontawesome/css/all.css" rel="stylesheet">
    <script type="text/javascript" src="assets/js/jquery-3.6.0.min.js"></script>

    <script>
    // requests a connection
    function connect(id) {
        $("#constat-"+id).html("connecting... (1)");

        $.ajax({
            url: "stat.php",
            type: "GET",
            data: { id: id, connect: 1},
            success: function(response) {
                $("#constat-"+response.id).html("connecting... (2)");
                //setTimeout("check('"+id+"')", checkInterval);
            },
            error: function(xhr) {
                //response = $.parseJSON(xhr);
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
    var hostname      = window.location.hostname;

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
                        // The device link we click to connect
                        devlink = "<a href='#' onclick=\"connect('" + dev.id + "'); return false;\">" + dev.id + "</a>";
                    }
                    $("#devices").append(
                        "<tr>" +
                        "<td>" + devlink + "</a></td>" +
                        "<td><small>" + dev.last_ip + "<br>" + dev.last_location + "</small></td>" +
                        "<td>" + dev.last_seen_at + "</td>" +
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
    <style>
    html{
      margin: 0;
      padding: 0;
      border: 0;
      outline: 0;
      font-size: 100%;
      vertical-align: baseline;
      background: transparent;
    }
    body {
      font-family: sans-serif;
      color: #999;
      margin:0px;
      padding:0px;
    }    
    .logo {
      float:left;
      position: relative;
      top: 50%;
      -webkit-transform: translateY(-50%);
      -ms-transform: translateY(-50%);
      transform: translateY(-50%);
      padding:10px;
      width:80px;
    }
    .title {
      float:left;
      font-size:16px;
      color:#fff;
      padding-left:30px;
      position: relative;
      top: 50%;
      -webkit-transform: translateY(-50%);
      -ms-transform: translateY(-50%);
      transform: translateY(-50%);
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
    h2 {
      color:#fff;
    }
.navbar {
  width: 100%;
  background-color: #3f414d;
  margin:0px;
  height:50px;
}

.navbar ul {
  display: inline-block;
  list-style-type: none;
  margin: 0;
  padding: 0;
  padding-right:40px;
  overflow: hidden;
}

.navbar li {
  float:left;
}

.navbar li a {
  display: block;
  color: #fff;
  font-size:16px;
  text-align: center;
  padding: 14px 12px;
  text-decoration: none;
}

.navbar li i {
  font-size:14px;
}

.navbar li a:active{
    color: #28cae4;
}

.navbar li a:hover{
    color: #28cae4;
}

.navbar-right {
  float: right;
}

.active {
  background-color: #313045;
  color:#fff;
}
.content {
  padding:20px;
}
 
    </style>
</head>
<body>
<div class="navbar">
  <div class="title">
    <h2>esp - Connections</h2>
  </div>
  <div class="navbar-right">
    <ul>
      <li><a href="view.php"><i class="fas fa-home"></i> Home</a></li>  
      <li><a href="map.php"><i class="fas fa-globe"></i> Map</a></li>
      <li><a href="logout.php" alt="log out"><i class="fas fa-sign-out-alt"></i> Logout</a></li>      
    </ul>
  </div>
</div>

<div class="content">
  <div id="msg" style="display: none;"></div>
  <table>
    <tr>
      <th>Device ID</th>
      <th>Last IP / Location</th>
      <th>Last Seen</th>
      <th>Connection</th>
    </tr>
    <tbody id="devices"></tbody>
  </table>
  <small><strong>Notes:</strong></small>
  <ul style="font-size: small">
    <li> Only devices seen in the past 24 hours show here</li>
    <li> It can take up to 20 seconds for a connection to be established</li>
  </ul>
</div><!-- end of content -->
</body>
</html>
