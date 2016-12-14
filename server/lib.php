<?php

# esp/server/lib.php
#
# This file provides a few library functions that are shared
# between the other esp/server scripts

function getdb() {

    # we need to keep a copy so we can close it in a callback later
    global $_db;
    # and also because caching per-request is smart
    if (isset($_db)) { return $_db; }
    
    # open the database connection
    try {
        $dbfile = "esp.sqlite";
        $_db = new SQLite3($dbfile);
        $_db->busyTimeout(5000);
        @chmod($dbfile, 0666);
    } catch (Exception $ex) {
        error_exit("DB ERROR 1");
    }

    # just make the table if it isn't there
    $rv = $_db->exec("
        CREATE TABLE IF NOT EXISTS device (
            id VARCHAR(255) UNIQUE,
            registered_at INTEGER, -- timestamp
            last_seen_at  INTEGER, -- timestamp
            last_ip VARCHAR(15),
            state VARCHAR(255), -- connecting|connected|disconnecting|disconnected|error
            offset INTEGER -- what port we starting from, when connected
        )
    ");

    if (!$rv) { error_exit("DB ERROR 2"); }

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
    $now = time();

    if ($now >= $unix_date) {
        $difference = $now - $unix_date;
        $tense = "ago";
    } else {
        $difference = $unix_date - $now;
        $tense = "from now";
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

    # XXX if not under https, vulnerable to cookie replay attack

    $user   = "admin";
    $pass   = "password";
    $token  = md5("$user:$pass");

    # if we've got a good cookie, return true
    if (isset($_COOKIE[CKNAME]) && $_COOKIE[CKNAME] == $token) {
        return true;

    # if we've got good user/pass, issue cookie
    } else if (isset($_POST['user']) && isset($_POST['pass'])) {

        if ($_POST['user'] == $user && $_POST['pass'] == $pass) {
            # we used to let the path be current directory, but then
            # we had cookies getting set that were difficult to unset
            # (if you don't know what directory they were set in, even
            # unsetting at "/" doesn't work) -- so now we set and
            # unset everything at the root
            setcookie(CKNAME, $token, 0, "/");
            header(
                "Location: //$_SERVER[HTTP_HOST]"
                . strtok($_SERVER["REQUEST_URI"],'?')
            );
            return true;
        }

    }

    # if we made it here it means they're not authorized
    # -- so give them a chance to log in

    print <<<EOT
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <title>Login</title>
    <style>
        body { background: #ccc; font-family: sans-serif; }
    </style>
  </head>
  <body onload="document.getElementById('user').focus()">
    <center>
    <h1>esp Login</h1>
    <form method="POST">
    <table cellpadding="10">
    <tr><td>User</td><td><input name="user" id="user"></td></tr>
    <tr><td>Pass</td><td><input name="pass" type="password"></td></tr>
    <tr><td colspan="2" align="right"><input type="submit" value="Login"></td></tr>
    </table>
    </center>
    </form>
  </body>
</html>
EOT;

}

function logout() {
    setcookie(CKNAME, null, -1, "/");
    header( "Location: //$_SERVER[HTTP_HOST]/" );
}

?>
