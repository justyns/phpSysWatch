<?php
/*
 * filename: watchview.php
 * author: Justyn Shull <justyn [at] justynshull.com>
 * Created: July 21, 2011
 * Last Updated: August 11, 2011
 *
 * Displays stats taken from watch.db created by watch.php
 *
 * Changelog:
 *  08/03/2011 - Added ob_start() before outputting the html pages
 *              - Added a no_cache header to the primary html page
 *  08/11/2011 - Added previous and next links to detail view
 *  10/16/2011 - Added swap usage details to memory graph
 */
/** Config * */
$dbfile = "watch.db";
$watchview = $_SERVER['PHP_SELF'];

function compress_data($data_string) {
    return gzcompress($data_string, 6);
}

function decompress_data($data_string) {
    return gzuncompress($data_string);
}

if (!isset($_REQUEST['a'])) {
    if (!file_exists($dbfile)) {
        die("$dbfile doesn't exist");
    }
    if (isset($_REQUEST['date1']) && isset($_REQUEST['date2'])) {
        $startstamp = $_REQUEST['date1'];
        $endstamp = $_REQUEST['date2'];
    } else {
        $startstamp = time() - 86400;  // Last 24 hours by default
        $endstamp = time();
    }
    /** Find oldest and newest record in the db **/
    try {
        $db = new PDO("sqlite:$dbfile");
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $oldestrec = $db->query("SELECT time FROM load ORDER BY time ASC LIMIT 1;")->fetch();
        $newestrec = $db->query("SELECT time FROM load ORDER BY time DESC LIMIT 1;")->fetch();
        unset($db);
    } catch(PDOException $e) {
        die($e);
    }
    /** Display the main page with graphs **/
    header("Cache-Control: no-cache, must-revalidate");
    ob_start();
    ?>
    <html><head>
            <title>Watchview.php</title>
            <script type="text/javascript" src="https://www.google.com/jsapi"></script>
            <script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jquery/1.6.2/jquery.min.js"></script>
            <script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jqueryui/1.8.14/jquery-ui.min.js"></script>
            <script type="text/javascript" src="http://trentrichardson.com/examples/timepicker/js/jquery-ui-timepicker-addon.js"></script>
            <link rel="stylesheet" href="http://ajax.googleapis.com/ajax/libs/jqueryui/1.7.2/themes/ui-lightness/jquery-ui.css" type="text/css" media="all" />
            <script type="text/javascript">
                google.load("visualization", "1", {packages:["corechart"]});
                $(document).ready(function() {
                    $("#from").datetimepicker({
                        minDate: new Date(<?php echo $oldestrec[0]*1000; ?>),
                        maxDate: new Date(<?php echo $newestrec[0]*1000; ?>)
                        
                    });
                    $("#to").datetimepicker({
                        minDate: new Date(<?php echo $oldestrec[0]*1000; ?>),
                        maxDate: new Date(<?php echo $newestrec[0]*1000; ?>)
                    });
                    $("#to").datetimepicker("setDate",new Date(<?php echo $endstamp*1000; ?>));
                    $("#from").datetimepicker("setDate",new Date(<?php echo $startstamp*1000; ?>));
                    $("#ui-datepicker-div").hide();
                    var startstamp = <?php echo $startstamp; ?>;
                    var endstamp = <?php echo $endstamp; ?>;
                    $("#changedate").button();
                    $("#changedate").click(function() { 
                        var from = $("#from").datetimepicker('getDate');
                        var to = $("#to").datetimepicker('getDate');
                        window.location = "<?php echo $watchview; ?>?date1="+from.getTime()/1000+"&date2="+to.getTime()/1000;
                        return false; 
                    });
                    drawChart("LoadAvg", "&load", "chart_load", "ps", startstamp, endstamp);
                    drawChart("Memory", "&mem", "chart_mem", "ps", startstamp, endstamp);
                    drawChart("Processes", "&ps", "chart_ps", "ps", startstamp, endstamp);
                    drawChart("Network", "&net", "chart_net", "netstat", startstamp, endstamp);
                });
                // this function creates an Array that contains the JS code of every <script> tag in parameter
                // then apply the eval() to execute the code in every script collected
                // TODO: find the source for this function again
                function parseScript(strcode) {
                    var scripts = new Array();         // Array which will store the script's code
                                      
                    // Strip out tags
                    while(strcode.indexOf("<script") > -1 || strcode.indexOf("</script") > -1) {
                        var s = strcode.indexOf("<script");
                        var s_e = strcode.indexOf(">", s);
                        var e = strcode.indexOf("</script", s);
                        var e_e = strcode.indexOf(">", e);
                                        
                        // Add to scripts array
                        scripts.push(strcode.substring(s_e+1, e));
                        // Strip from strcode
                        strcode = strcode.substring(0, s) + strcode.substring(e_e+1);
                    }
                                      
                    // Loop through every script collected and eval it
                    for(var i=0; i<scripts.length; i++) {
                        try {
                            eval(scripts[i]);
                        }
                        catch(ex) {
                            // do what you want here when a script fails
                        }
                    }
                }
                function getDetails(timestamp,tab) {
                    //fill detailview with everything happening during timestamp
                    window.open("<?php echo $watchview; ?>?a=loaddetail&timestamp="+timestamp+"#"+tab, "detailview","width=750,height=600");
                }
                function drawChart(title,params,chartdiv,tab, startstamp,endstamp) {
                    document.getElementById(chartdiv).innerHTML='<img src="http://dev.justynshull.com/watch/local/35-0.gif" />';
                    $.ajax({
                        type: "GET",
                        cache: true,
                        timeout: 30000,
                        url: "<?php echo $watchview; ?>?date1="+startstamp+"&date2="+endstamp+"&tab="+tab+"&div="+chartdiv+"&title="+title+"&a=loadgraph"+params,
                        dataType: "html",
                        success: function(html){
                            parseScript(html);
                        }
                    });
                }
            </script>
            <style type="text/css">
                /* css for timepicker */
                .ui-timepicker-div .ui-widget-header{ margin-bottom: 8px; }
                .ui-timepicker-div dl{ text-align: left; }
                .ui-timepicker-div dl dt{ height: 25px; }
                .ui-timepicker-div dl dd{ margin: -25px 0 10px 65px; }
                .ui-timepicker-div td { font-size: 90%; }
            </style></head>
        <body>
            <div id="dates">
                <label for="from">From</label>
                <input type="text" id="from" name="from"/>
                <label for="to">to</label>
                <input type="text" id="to" name="to"/>
                <button id="changedate">Go</button>
            </div>
            <div id="detailview"></div>
            <div id="chart_load">

            </div>
            <div id="chart_mem">
            </div>
            <div id="chart_ps">
            </div>
            <div id="chart_net">
            </div>
        </body>
        <html>
            <?php
        } else {
            /** Handle ajax functions * */
            try {
                $db = new PDO("sqlite:$dbfile");
                $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                //function to gunzip data from sqlite
                $db->sqliteCreateFunction('decompress', 'decompress_data', 1);
                switch ($_REQUEST['a']) {
                    case "loadgraph":
                        //build sql query first
                        $cols = array();
                        $tables = array();
                        if (isset($_REQUEST['mem'])) {
                            array_push($cols, "used");
                            array_push($cols, "free");
                            array_push($cols, "swused");
                            array_push($cols, "swfree");
                            array_push($tables, "memory");
                        }
                        if (isset($_REQUEST['load'])) {
                            array_push($cols, "min1");
                            array_push($tables, "load");
                        }
                        if (isset($_REQUEST['ps'])) {
                            array_push($cols, "procs");
                            array_push($tables, "ps");
                        }
                        if (isset($_REQUEST['net'])) {
                            array_push($cols, "connections");
                            array_push($cols, "sqlconnections");
                            array_push($cols, "httpconnections");
                            array_push($tables, "netstat");
                            array_push($tables, "mysql");
                            array_push($tables, "http");
                        }
                        if (isset($_REQUEST['date1']) && isset($_REQUEST['date2'])) {
                            $startstamp = $_REQUEST['date1'];
                            $endstamp = $_REQUEST['date2'];
                        } else {
                            $startstamp = time() - 86400;  // Last 24 hours by default
                            $endstamp = time();
                        }
                        $sort = "ORDER BY {$tables[0]}.time";
                        //print_r($cols);
                        //print_r($tables);
                        $x = count($tables);
                        $y = 0;
                        $wherejoin = "${tables[0]}.time < $endstamp AND ${tables[0]}.time > $startstamp ";
                        $daterange = date('r', $startstamp) . " to " . date('r', $endstamp);
                        if (count($tables) > 1)
                            $wherejoin .= " AND ";
                        foreach ($tables as $table) {
                            if ($y == 0) {
                                //skip the first table
                                $y++;
                            } else {
                                $y++;
                                if ($x > $y) {
                                    $wherejoin .= "{$tables[0]}.time=$table.time AND ";
                                } else {
                                    $wherejoin .= "{$tables[0]}.time=$table.time";
                                }
                            }
                        }
                        $query = "SELECT {$tables[0]}.time," . implode(",", $cols) . " FROM " . implode(",", $tables) .
                                " WHERE $wherejoin $sort;";
                        echo $query;
                        $result = $db->prepare("$query");
                        $result->execute();
                        $results = $result->fetchAll();
                        $x = 0;
                        $js = "\n";
                        //print_r($results);
                        foreach ($results as $row) {
                            //print_r($row);
                            $y = 1;
                            $js .= "data.setValue($x, 0, '" . date('r', $row['time']) . "');\n";
                            foreach ($cols as $col) {
                                if (($col == "used") OR ($col == "free") OR ($col == "swused") OR ($col == "swfree")) {
                                    $js .= "data.setValue($x, $y," . round($row["$col"] / 1024) . ");\n";
                                } else {
                                    $js .= "data.setValue($x, $y," . $row["$col"] . ");\n";
                                }
                                $y++;
                            }
                            $x++;
                        }
                        ob_start();
                        echo "\n<script type='text/javascript'>\n" .
                        "var data = new google.visualization.DataTable();\n" .
                        "data.addColumn('string', 'date');\n";
                        foreach ($cols as $col) {
                            echo "data.addColumn('number', '$col');\n";
                        }
                        echo "data.addRows($x);";
                        echo $js;
                        echo "var chart = new google.visualization.LineChart(document.getElementById('{$_REQUEST['div']}'));\n" .
                        "chart.draw(data, {width: 800, height: 550, title: '{$_REQUEST['title']}'});\n";
                        echo "google.visualization.events.addListener(chart, 'select', function() {
                            console.log(chart.getSelection());
                            row = chart.getSelection()[0].row;
                            var date = new Date(data.getValue(row,0));                            
                            console.log(data.getValue(row,0)+' changed to: '+date.getTime()/1000.0);
                            getDetails(date.getTime()/1000.0,'${_REQUEST['tab']}');
                            });\n";
                        echo "\n</script>\n";
                        break;
                    case "loaddetail":
                        if ((isset($_REQUEST['timestamp'])) || (isset($_REQUEST['id']))) {
                            if (isset($_REQUEST['id'])) {
                            //using the next/prev links, we need to get and set the timestamp
                            $id = $db->quote($_REQUEST['id']); //TODO: Better escape
                            $result = $db->query("SELECT time FROM netstat WHERE netstat.id=$id;")->fetchAll();
                            $time = $result[0]['time'];
                        } else {
                            $time = $_REQUEST['timestamp'];
                        }
                            //populate detailview with the information we have for this timestamp
                            $result = $db->query("SELECT netstat.id,
                                    decompress(netstat),
                                    decompress(ps),
                                    decompress(serverstatus),
                                    decompress(processlist)
                                                  FROM netstat, ps, http, mysql 
                                                  WHERE netstat.time=$time AND ps.time=$time AND http.time=$time AND mysql.time=$time;");
                            $results = $result->fetchAll();
                            ob_start();
                            ?>
                            <html><head>
                                    <title>watchview.php: <?php echo $time; ?></title>
                                    <script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jquery/1.6.2/jquery.min.js"></script>
                                    <script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jqueryui/1.8.14/jquery-ui.min.js"></script>
                                    <script>
                                        $(document).ready(function() {
                                            $( "#tabs" ).tabs();
                                        });
                                    </script>
                                    <link rel="stylesheet" href="http://ajax.googleapis.com/ajax/libs/jqueryui/1.7.2/themes/ui-lightness/jquery-ui.css" type="text/css" media="all" />
                                </head>
                                <body>
                                   <p>
                                       <strong>Viewing Timestamp: <?php echo $time . " (" . date('r', $time) . ")";?></strong>
                                       |    <a href="<?php echo $watchview . "?a=loaddetail&id=" . ($results[0]['id']-1);?> ">Prev</a> | <a href="<?php echo $watchview . "?a=loaddetail&id=" . ($results[0]['id']+1);?> ">Next</a>
                                   </p>
                                    <div id="tabs">
                                        <ul>
                                            <li><a href="#netstat">Netstat</a></li>
                                            <li><a href="#ps">ps aux</a></li>
                                            <li><a href="#mysql">Mysql</a></li>
                                            <li><a href="#http">http server-status</a></li>
                                        </ul>

                                        <div id="netstat">
                                            <pre>
                                                <?php echo $results[0]['decompress(netstat)']; ?>
                                            </pre>
                                        </div>
                                        <div id="ps">
                                            <pre>
                                                <?php echo "\n" . htmlentities($results[0]['decompress(ps)']); ?>
                                            </pre>
                                        </div>
                                        <div id="mysql">
                                            <pre>
                                                <?php echo "\n" . htmlentities($results[0]['decompress(processlist)']); ?>
                                            </pre>    
                                        </div>
                                        <div id="http">
                                            <?php echo "\n" . $results[0]['decompress(serverstatus)']; ?>
                                        </div>
                                    </div>
                                </body>
                            </html>
                            <?php
                        }
                        break;
                }
            } catch (PDOException $e) {
                die($e->getMessage());
            } catch (Exception $e) {
                die($e->getMessage());
            }
        }
        ?>
