<!--
# esp/server/view.php
#
# Displays a list of available RACHEL devices,
# provides ability to connect/disconnect to them
-->

<?php
require_once("lib.php");
if (!authorized()) { exit(); }

$option = "all";

if(isset($_POST['views'])){
    switch($_POST['views']){
          case "all": 
              $option = "all";
              break;
          case "24h": 
              $option = "24h"; 
              break;
          case "datapost": 
              $option = "datapost";
              break;
          default: 
              echo("Error!"); 
              exit(); 
              break;
    }
}

$db   = getdb();
$stmt = "SELECT * 
           FROM devices";
           

# Doing this here instead of the switch above to give a little more room to work with the 
# different options for now            
if($option == "24h"){
    $db_24hrs = $db->escapeString(time()-86400);
    $stmt     = "SELECT * 
                   FROM devices 
                  WHERE last_seen_at > '$db_24hrs'";
}

if($option == "datapost"){
    $stmt     = "SELECT * 
                   FROM devices 
                  WHERE datapost = TRUE";
}

$rv = $db->query($stmt);

if (!$rv) { # detect DB error
    return;
}

$devices = array();

while ($row = $rv->fetchArray(SQLITE3_ASSOC)) {
    array_push($devices, $row);
}

$devices_json = json_encode($devices);
    
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>esp - Device Map</title>
    <link rel="icon" type="image/png" href="assets/images/favicon.png"/>
    <link href="assets/fontawesome/css/all.css" rel="stylesheet">
    <script src="assets/js/jquery-3.6.0.min.js"></script>
    <script>
      var mapref;
      const irvine = { lat: 33.669445, lng: -117.823059 };
    
      function centerMap(){
          mapref.panTo(irvine);
          mapref.setZoom(mapref.getZoom() + 2);
      }
      
      function centerOnDevice(id){
          var devices = <?php echo $devices_json; ?>;
          
          for(var i = 0; i < devices.length; i++){
              if(devices[i]['id'] != id){
                  continue;
              }   
              
              var mappos = { lat: devices[i]['last_lat'], 
                             lng: devices[i]['last_long']};
              mapref.panTo(mappos);
              mapref.setZoom(9);
              break;
          }
      }
    
      // Initialize and add the map
      function initMap() {
          
          const map = new google.maps.Map(document.getElementById("map"), 
                                          { zoom: 4, center: irvine,});
          
          mapref       = map;
          var image = "https://dev.worldpossible.org/esp/assets/images/cap-marker.png";
          var devices = <?php echo $devices_json; ?>;

          for(var i = 0; i < devices.length; i++){
              var location  = devices[i]['last_location'];
              var id        = devices[i]['id'];
              var latcoord  = devices[i]['last_lat'];
              var longcoord = devices[i]['last_long'];

              if(latcoord == "undefined" || longcoord == "undefined"){
                  latcoord  = 0;
                  longcoord = 0;
              }

              var mappos = { lat: latcoord, lng: longcoord };

              if (location != null){
                  var marker = new google.maps.Marker({
                      title: "RACHEL - " + id,
                      position: mappos,
                      map: map,
                      icon: image});

                    google.maps.event.addListener(marker, 'click', function() {
                       map.panTo(this.getPosition());
                    });
              }
          }          
      }
    </script>
    <style>
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

body {
  font-family: sans-serif;
  color: #999;
  margin:0px;
  padding:0px;
}    
#map {
  margin-top:20px;
    marging-right:10px;
  position:absolute;
  height:86%;
  width:98%;

}

.selection {
  margin-bottom:20px;
}

.controls {
  float:left;
}
 
select {
  float:left;
  margin-left:4px;
  margin-right:4px;
  margin-bottom:20px;
}

    </style>
</head>
<body>
<div class="navbar">
  <div class="title">
    <h2>esp - Device Map</h2>
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
  <div class="controls">
    <button onclick="centerMap()" title="Center Map"><i class="fas fa-home"></i></button>
    <button onclick="refresh()" title="Refresh Map"><i class="fas fa-sync" ></i></button>
    <button onclick="refresh()" title="Show DataPost"><i class="fas fa-at"></i></button>
    
  </div>
  <div class="selection">
    <form action="map.php" method="post">
      <select name="views" id="views" onchange="this.form.submit()">
        <option value="" selected disabled hidden>Select a View</option>
        <option value="all">All Devices</option>
        <option value="24h">Last 24 hours</option>
        <!--<option value="datapost" disabled>DataPost</option>-->
      </select> 
    </form>
    <select name="device" id="device" onchange="centerOnDevice(this.value)">
      <option value="" selected disabled hidden>Select a Device</option>
      <?php foreach($devices as $device) : ?>
        <option value="<?php echo $device['id']; ?>"><?php echo $device['id']; ?></option>
      <?php endforeach; ?>
    </select>
    
  </div>
  <div id="map"></div>
  <script
    src="https://maps.googleapis.com/maps/api/js?key=AIzaSyBIOVv0vbuxFT50NEvnyp2JyS3q8DfuFD8&callback=initMap&libraries=&v=weekly"
    async>
  </script>

</div>
</body>
</html>
