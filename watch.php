#!/usr/bin/php -d disable_functions=""
<?php

/*
 * filename: watch.php
 * author: Justyn Shull <justyn [at] justynshull.com>
 * Created: July 21, 2011
 * Last Updated: October 16, 2011
 * 
 * Meant to be used as a replacement for Pat's watch.py script
 * Instructions:
 *  change $dbfile to something readable by the webserver user
 *  watch.php does not need to be in a web-accessible directory, and 
 *      should only be run through ssh as:  ./watch.php
 *  watchview.php needs to be in a web-accessible directory and have
 *      access to $dbfile
 * 
 */
/**  Config * */
$dbfile = __DIR__ . DIRECTORY_SEPARATOR . "watch.db";
$interval = 60;             // Every 60 seconds
$watchmysql = true;         //Whether or not to monitor mysql
$watchhttp = true;          //whether to get /server-status
$serverstatusurl = "http://localhost/server-status";
if (is_readable("/etc/psa/.psa.shadow")) {
    //Appears to be a plesk machine
    $mysql['user'] = 'admin';
    $mysql['pass'] = file_get_contents("/etc/psa/.psa.shadow");
} else {
    //Non-Plesk machine.   Set mysql information manually
    $mysql['user'] = 'root';    //User and pass to login to 
    $mysql['pass'] = '';        //  mysql with
}

/** Check requirements * */
//dl() doesn't work after 5.3 and only works in certain cases anyway
if (!extension_loaded("pdo_sqlite") && !dl("pdo_sqlite.so"))
    die("pdo_sqlite not enabled\n");
if (!extension_loaded("PDO") && !dl("pdo.so"))
    die("PDO not enabled\n");
if (!function_exists("exec"))
    die("exec is not enabled\n");
/** CLI Check **/
if (php_sapi_name() == 'cli' && empty($_SERVER['REMOTE_ADDR'])) {
    //Running from cli
} else {
    die("watch.php can only be run from the cli\n");
}
/** DB Initilize **/
$createtables = false;
if (!file_exists($dbfile)) {
    $createtables = true;
    echo "Creating database $dbfile \n";
}
try {
    $db = new PDO("sqlite:$dbfile");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    if ($createtables) {
        // Create DB structure
        echo "Creating tables in $dbfile ...\n";
        $db->exec("CREATE TABLE memory (
    id INTEGER PRIMARY_KEY AUTO_INCREMENT,
    time INTEGER,
    total INTEGER,
    used INTEGER,
    free INTEGER,
    swused INTEGER,
    swfree INTEGER,
    swtotal INTEGER
    );");
        $db->exec("CREATE TABLE ps (
    id INTEGER PRIMARY KEY ,
    time INTEGER,
    procs INTEGER,
    ps TEXT
    );");
        $db->exec("CREATE TABLE load (
    id INTEGER PRIMARY KEY,
    time INTEGER,
    min1 INTEGER,
    min5 INTEGER,
    min15 INTEGER
    );");
        $db->exec("CREATE TABLE http (
    id INTEGER PRIMARY KEY,
    time INTEGER,
    httpconnections INTEGER,
    serverstatus TEXT
    );");
        $db->exec("CREATE TABLE netstat (
    id INTEGER PRIMARY KEY,
    time INTEGER,
    connections INTEGER,
    netstat TEXT
    );");
        $db->exec("CREATE TABLE mysql (
    id INTEGER PRIMARY KEY,
    time INTEGER,
    sqlconnections INTEGER,
    processlist TEXT,
    status TEXT
    );");
    }
    //**  Upgrade Check **/
    /** Sep 9, 2011 - Swap columns **/
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
    if (!$db->query("SELECT swused FROM memory LIMIT 1;")) {
        echo "Upgrading memory table to include swap stats..\n";
        $db->exec("ALTER TABLE memory ADD swused INTEGER;");
        $db->exec("ALTER TABLE memory ADD swfree INTEGER;");
        $db->exec("ALTER TABLE memory ADD swtotal INTEGER;");
    }
} catch (PDOException $e) {
    die($e->getMessage());
} catch (Exception $e) {
    die($e->getMessage());
}
$db = NULL;
/** Main Loop **/
function compress_data($data_string) { return gzcompress($data_string, 6); }
while (true) {
    $time = time();  //seconds since epoch is how we store timestamps
    //some of this is from Ryan Uber
    //http://www.ryanuber.com/basic-server-statistics-script.html
    //Server Load
    $loadavg = explode(' ', file_get_contents('/proc/loadavg'));
    //Memory Usage
    foreach (file('/proc/meminfo') as $result) {
        $array = explode(':', str_replace(' ', '', $result));
        $value = preg_replace('/kb/i', '', $array[1]);
        if (preg_match('/^MemTotal/', $result)) {
            $totalmem = $value;
        } elseif (preg_match('/^MemFree/', $result)) {
            $freemem = $value;
        } elseif (preg_match('/^Buffers/', $result)) {
            $buffers = $value;
        } elseif (preg_match('/^Cached/', $result)) {
            $cached = $value;
        } elseif (preg_match('/^SwapTotal/', $result)) {
            $swaptotal = $value;
        } elseif (preg_match('/^SwapFree/', $result)) {
            $swapfree = $value;
        }
        unset($result);
    }
    $freemem = ( $freemem + $buffers + $cached );
    $usedmem = $totalmem - $freemem;
    $swapused = $swaptotal - $swapfree;
    //netstat 
    @exec("netstat -nat", $results);
    $netstatcons = count($results);
    //before we implode this, we can use a foreach loop to count http connections, etc
    $netstat = implode("\n", $results);
    //processes
    @exec("ps aux", $ps);
    $noprocs = count($ps);
    $procs = implode("\n", $ps);
    //mysql
    if ($watchmysql) {
        $mysqlpass = "-u'{$mysql['user']}'";
        if ($mysql['pass'] != '')
            $mysqlpass = "-u'{$mysql['user']}' -p'{$mysql['pass']}'";
        $link = mysql_connect("localhost", $mysql['user'], $mysql['pass']);
        $result = mysql_list_processes($link);
        $mysqlstatus = mysql_stat($link);
        $mysqlnoprocs = 0;
        $mysqlprocs = "";
        while ($row = mysql_fetch_assoc($result)) {
            $mysqlprocs .= implode(" ", $row) . "\n";
            $mysqlnoprocs++;
        }
        unset($row);
        mysql_free_result($result);
        mysql_close($link);
    } //endif watchmysql
    if ($watchhttp) {
        $httpstatus = file_get_contents($serverstatusurl);
        $httpconns = shell_exec("netstat -nat | egrep ':80|:443' | wc -l");
        //TODO: get $httpconns from the netstat command ran earlier
    } //endif watchhttp
    /** Insert into database * */
    //TODO: Use beginTransaction instead of seperate queries
    try {
        echo "$time: Writing to db..\n";
        $db = new PDO("sqlite:$dbfile");
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->sqliteCreateFunction('compress', 'compress_data', 1); //function to gzip data before adding it to the db
        $db->exec("
            INSERT INTO load (time, min1, min5, min15)
            VALUES ($time, {$loadavg[0]}, {$loadavg[1]}, {$loadavg[2]});
            INSERT INTO memory (time, total, used, free, swused, swfree, swtotal)
            VALUES ($time, $totalmem, $usedmem, $freemem, $swapused, $swapfree, $swaptotal);");
        $dbq = $db->prepare("
            INSERT INTO netstat (time, connections, netstat)
            VALUES ($time, $netstatcons, compress(:netstat));")->execute(array(":netstat" => $netstat));
        $dbq = $db->prepare("INSERT INTO ps (time, procs, ps)
            VALUES ($time, $noprocs, compress(:procs));")->execute(array(":procs" => $procs));
        if ($watchmysql) {
            $dbq = $db->prepare("INSERT INTO mysql (time, sqlconnections, processlist, status)
            VALUES ($time, $mysqlnoprocs, compress(:procs), compress(:status));")->execute(array(":procs" => $mysqlprocs, ":status" => $mysqlstatus));
        }
        if ($watchhttp) {
            $dbq = $db->prepare("INSERT INTO http (time, httpconnections, serverstatus)
            VALUES ($time, $httpconns, compress(:status));")->execute(array(":status" => $httpstatus));
        }

        /** Try and clean up what we can * */
        unset($netstat, $usedmem, $totalmem, $freemem, $loadavg, $httpconns);
        unset($results, $dbq, $db, $procs, $ps, $mysqlprocs, $mysqlstatus, $httpstatus);
        unset($swapfree, $swapused,$swaptotal);
    } catch (PDOException $e) {
        die("$time: PDO Exception: $e \n");
    } catch (Exception $e) {
        die($e->getMessage());
    }
    sleep($interval);
}
?>
