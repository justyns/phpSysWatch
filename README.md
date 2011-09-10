phpSysWatch
===========

Please visit these two links for the most up to date version of this script, and information:

+ https://github.com/justyns/phpSysWatch
+ http://justynshull.com/

Report any bugs/suggestions to the issue tracker on github, or via e-mail to justyn [at] justynshull.com

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

Download
--------
Just clone the repository to a web-accessible directory:
```git clone git://github.com/justyns/phpSysWatch.git```

If you don't have git: 
```wget 'https://github.com/justyns/phpSysWatch/tarball/master' -O phpsyswatch.tar.gz```


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
8. Enable Apache server-status with something like this in the conf:

```
ExtendedStatus On
<Location /server-status>
    SetHandler server-status
    Order deny,allow
    Deny from all
    Allow from 127.0.0.1
</Location>
```

Files
-----
README.md - readme with markup for github
watch.php - php script that does all of the logging
watchview.php - php script that views/graphs the data logged by watch.php
watch.py - original python script that does something similar but logs to separate files
watchuploader.sh - my first attempt at uploading the data from watch.py to a remote server for graphing/viewing
