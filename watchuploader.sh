#!/bin/bash

uploadurl="http://dev.justynshull.com/watch/upload.php"
hostname=`hostname`
uname=`uname -a`
watchdir="/home/justyns/.watch"

if [ "$1" == "" ]; then
	#no arguments, continue on
	#Get server ID
	serverid=$(curl -s -F "a=serverid" -F "hostname=${hostname}" -F "uname=${uname}" ${uploadurl})
	if [ "$serverid" == "0" ]; then
		echo "Error getting serverid"
		exit 1
	fi

	echo "Server ID: ${serverid}"
	echo "Processing files.."
	find $watchdir -type f -name "free*.txt" -exec $0 $serverid free {} \;
	find $watchdir -type f -name "ps*.txt" -exec $0 $serverid ps {} \;
fi

if [ "$2" == "free" ]; then
#process free file
	echo "Processing $3"
	totalmem=$(cat $3 | grep Mem | awk ' { print $2 }')
	usedmem=$(cat $3 | grep 'buffers/cache' | awk ' { print $3; }')
	freemem=$(cat $3 | grep 'buffers/cache' | awk ' { print $4; }')
	epoch=$(echo $3 | sed -e 's/.*free\.//g' -e 's/.txt//g')
	curl -s -F "a=free" -F "sid=$1" -F "epoch=$epoch" -F "total=$totalmem" -F "used=$usedmem" -F "free=$freemem" ${uploadurl}
fi
if [ "$2" == "ps" ]; then
#process free file
        echo "Processing $3"
        epoch=$(echo $3 | sed -e 's/.*ps\.//g' -e 's/.txt//g')
	ps=$(cat $3 | base64 -w 0)
	procs=$(cat $3 | wc -l)
        curl -s -F "a=ps" -F "sid=$1" -F "procs=$procs" -F "epoch=$epoch" -F "ps=$ps" ${uploadurl}
fi

