#!/usr/bin/env python
import time, os, smtplib
DELAY = 60
path = "~/.watch/"

def outputFile(command, seconds, path):
    return "> " + path + command + "." + seconds + ".txt"

def sendEmail(to, subject, content):
    #TODO
    pass

if not os.path.isdir("~/.watch/"):
    print "Making .watch/"
    os.system("mkdir -p ~/.watch/")

while 1:
    seconds = str(int(time.time()))

    cmd = "free -m " + outputFile("free", seconds, path)
    #print cmd + "\n"
    os.system(cmd)

    
    cmd = "echo 'show full processlist' | mysql " + outputFile("mysqlproclist", seconds, path)
    os.system(cmd)

    cmd = "ps aux " + outputFile("ps", seconds, path)
    os.system(cmd)
    

    cmd = "curl -s http://localhost/server-status " + outputFile("serverstatus", seconds, path)
    os.system(cmd)

    time.sleep(DELAY)

