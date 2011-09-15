#!/bin/bash
##Filename: watch.sh
##Desc: simple bash wrapper script for watch.php.   Can be run from cron every 5-10 minutes to ensure watch.php is running
##See https://github.com/justyns/phpSysWatch
##Full path to watch.php
watch=watch.php
LOG_FILE=/tmp/watch.php.log
LOCK_FILE=/tmp/watch.php.lock

#function from http://ajohnstone.com/achives/lock-files-in-php-bash-missing/
function check_lock {
    (set -C; : > $LOCK_FILE) 2> /dev/null
    if [ $? != "0" ]; then
        RUNNING_PID=$(cat $LOCK_FILE 2> /dev/null || echo "0");
        if [ "$RUNNING_PID" -gt 0 ]; then
            if [ `ps -p $RUNNING_PID -o comm= | wc -l` -eq 0 ]; then
                echo "`date +'%Y-%m-%d %H:%M:%S'` WARN [Cron wrapper] Lock File exists but no process running $RUNNING_PID, continuing" >> $LOG_FILE;
            else
                echo "`date +'%Y-%m-%d %H:%M:%S'` INFO [Cron wrapper] Lock File exists and process running $RUNNING_PID - exiting" >> $LOG_FILE;
                exit 1;
            fi
        else
            echo "`date +'%Y-%m-%d %H:%M:%S'` CRIT [Cron wrapper] Lock File exists with no PID" >> $LOG_FILE;
            exit 1;
        fi
    fi
    trap "rm $LOCK_FILE;" EXIT
}
check_lock;
echo "`date +'%Y-%m-%d %H:%M:%S'` INFO [Cron wrapper] Starting process" >> $LOG_FILE;
$watch &
CURRENT_PID=$!;
echo "$CURRENT_PID" > $LOCK_FILE;
trap "rm -f $LOCK_FILE 2> /dev/null ; kill -9 $CURRENT_PID 2> /dev/null;" EXIT;
echo "`date +'%Y-%m-%d %H:%M:%S'` INFO [Cron wrapper] Started ($CURRENT_PID)" >> $LOG_FILE;
wait;
# remove the trap kill so it won't try to kill process which took place of the php one in mean time (paranoid)
trap "rm -f $LOCK_FILE 2> /dev/null" EXIT;
rm -f $LOCK_FILE 2> /dev/null;
echo "`date +'%Y-%m-%d %H:%M:%S'` INFO [Cron wrapper] Finished process" >> $LOG_FILE;

