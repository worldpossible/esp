<?php

# esp/lib.php
#
# This file provides a few library functions that are shared
# between the other esp/server scripts

function getdb() {
    # we need to keep a copy so we can close it in a callback later
    # and also because caching per-request is smart
    global $_db;
    
    if (isset($_db)){
        return $_db;
    }
    
    # open the database connection
    try {
        $dbfile = "esp.sqlite";
        $_db    = new SQLite3($dbfile);
        $_db->busyTimeout(5000);
        @chmod($dbfile, 0666);
    } catch (Exception $ex) {
        error_exit("DB ERROR 1");
    }

    # just make the table if it isn't there
    $rv = $_db->exec("
        CREATE TABLE IF NOT EXISTS devices (
            id VARCHAR(255) UNIQUE,
            registered_at INTEGER, -- timestamp
            last_seen_at  INTEGER, -- timestamp
            last_country VARCHAR(255),
            last_prov    VARCHAR(255),
            last_city    VARCHAR(255),
            last_lat     REAL,
            last_long    REAL,
            last_time_zone VARCHAR(255),
            last_isp VARCHAR(255),
            last_ip VARCHAR(15),
            last_location VARCHAR(255), -- geoip: city, state, country
            state VARCHAR(255), -- connecting|connected|disconnecting|disconnected|error
            offset INTEGER, -- what port we starting from, when connected
            notes TEXT -- admin can add notes here as needed
        )
    ");

    if (!$rv) { 
        error_exit("DB ERROR 2"); 
    }

    return $_db;
}

#-------------------------------------------
# If we don't do this, dangling filehandles build up
# and after a while we can't open any more... yikes. 
#-------------------------------------------
function cleanup() {
    global $_db;
    if (isset($_db)) {
        $_db->close();
        unset($_db);
    }
}

register_shutdown_function('cleanup');

# lifted from http://www.phpdevtips.com/2011/06/the-php-time-ago-function/
# plus some small tweaks
function time_ago($unix_date) {

    if (empty($unix_date)) {
        return "No date provided";
    }

    $periods = array(
        "second", "minute", "hour", "day", "week", "month", "year", "decade"
    );
    
    $lengths = array("60", "60", "24", "7", "4.35", "12", "10");
    $now     = time();

    if ($now >= $unix_date) {
        $difference = $now - $unix_date;
        $tense      = "ago";
    } else {
        $difference = $unix_date - $now;
        $tense      = "from now";
    }

    for (
        $j = 0;
        $difference >= $lengths[$j] && $j < count($lengths) - 1;
        $j++
    ) {
        $difference /= $lengths[$j];
    }

    $difference = round($difference);

    if ($difference != 1) {
        $periods[$j] .= "s";
    }

    return "$difference $periods[$j] {$tense}";
}

function error_exit($error) {
    header("HTTP/1.1 500 Internal Server Error");
    echo $error;
    exit;
}

define("CKNAME", "esp-auth");
function authorized() {
    $token = "686f5110c09c3e847bf3aee688cdb54f";
    
    # if we've got a good cookie, return true
    if (isset($_COOKIE[CKNAME]) && $_COOKIE[CKNAME] == $token) {
        return true;
    } 

    # Check login if we have a login and pass
    if (isset($_POST['user']) && isset($_POST['pass'])){
        $check_user = $_POST['user'];
        $check_pass = $_POST['pass'];
        $check_md5  = md5("$check_user:$check_pass");
        
        if($check_md5 == $token){
            setcookie(CKNAME, $token, 0, "/");
            header("Location: //$_SERVER[HTTP_HOST]" . strtok($_SERVER["REQUEST_URI"],'?'));
            return true;        
        }
    }

    print <<<EOT
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ESP - Login</title>
    <link rel="icon" type="image/png" href="assets/images/favicon.png"/>
    <link href="assets/fontawesome/css/all.css" rel="stylesheet">
    <style>
      body { 
        background-color: #3f414d;
        font-family: sans-serif; 
        color:#fff; }
      .content {
        padding: 0 20px 40px 20px;
        margin: auto;
        width: 300px;
        background:#fff;
        border:1px solid #ccc;
        height: 310px;
	    border-radius:4px;
        position:fixed;
        top:0;
        bottom:0;
        left:0;
        right:0;
      }
      .content img {
	    display: block;
        margin-left: auto;
        margin-right: auto;
	    width:117px;
	    height:146px;
	    padding:14px 14px 14px 0px;
      }
      .content h1 {
	    font-size:16px;
	    color:#363636;
      }
      .login input[type=submit] {
        width:100%;
        height 28px;
        background-color: #2c3150;
        color: #fff;
        padding: 8px;
        border: none;
        cursor: pointer;
        font-size:14px;
	    border-radius:4px;
      }
      .login input[type=submit]:hover {
        background-color: #2abfd8;
      }
      .login input[type=text], input[type=password], select {
        width: 100%;
        height: 36px;
        padding: 8px;
        display: inline-block;
        border: 1px solid #2c3150;
        box-sizing: border-box;
        margin-bottom:10px;
      }
      .login input:focus {
        border: 1px solid #2c3150;
      }
      .login {
        padding-bottom:12px;
	    width:100%;
      }
      .login i {
        text-align:center;
      }
     .login td:last-child {
        width: 100%;
      }
     .login h1 {
       font-weight:lighter;
       text-align:center;
       font-size:36px;
       color:#777;
      }
     .fontAwesome {
       font-family:FontAwesome;
     }
     ::placeholder { /* Chrome, Firefox, Opera, Safari 10.1+ */
       opacity: 0.4;
     }
     ::-ms-input-placeholder { /* Microsoft Edge */
       opacity: 0.4;
     }
    </style>
  </head>
  <body onload="document.getElementById('user').focus()">
    <div class="content">
      <form method="POST">
        <table class="login"><tr>
	      <tr>
	        <td>
              <a href="/esp/view.php">
			    <img src="assets/images/esp-login.png">
			  </a>
            </td>
	      </tr>
	      <tr>
	        <td><input type="text" name="user" id="user" placeholder="username"></td>
          </tr>
          <tr>
	        <td><input type="password" name="pass" placeholder="password"></td>
          </tr>
	      <tr>
	        <td>
	          <input type="submit" value="log in">
	        </td>
	      </tr>
        </table>
      </form>
	</div>
  </body>
</html>
EOT;
}

function logout() {
    setcookie(CKNAME, null, -1, "/");
    header( "Location: //$_SERVER[HTTP_HOST]/esp/view.php" );
}

?>
