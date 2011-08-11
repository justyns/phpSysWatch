phpSysWatch
===========

Description
-----------
This script will take a snapshot of the following information every 60 seconds(changeable) and input it into a sqlite database:
+ netstat -nat
+ ps aux
+ mysql processlist
+ apache's /server-status
+ Server load

It is not a complete replacement for sar or the systat package, but compliments it nicely since you will be able to see exactly what processes were running or what connections were open at specific times making troubleshooting server issues easier.   watchview.php also uses the GoogleChart api to create nice graphs to view this information.


Requirements
------------
+ Root access to the server you want to monitor(possibly not required)
+ php
+ php-pdo (with sqlite support)

Installation
------------
1. Open watch.php and configure it:
	+ $dbfile needs to be in a path accessible by the webserver user
	+ $interval is set to 60 seconds by default, but can be changed
	+ $serverstatusurl may need to be changed if you do not have server-status acessible on localhost
	+ $mysql['pass'] should be updated to the mysql root password, or another user created for this script with the necessary permissions
2. Copy/move watch.php pretty much anywhere
3. chmod +x watch.php
4. Run it:  ./watch.php
5. Open watchview.php and change $dbfile to match watch.php's $dbfile
6. Copy/move watchview.php to a web-accessible directory
7. (Optional) configure .htaccess to restrict access to watchview.php by IP