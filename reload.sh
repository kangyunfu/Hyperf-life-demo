#!/bin/bash
basepath=$(cd `dirname $0`; pwd)
cd $basepath

# 重启服务
if [ -f "./runtime/hyperf.pid" ];then
  PID=`ps -A |grep "php hyperf.php start"| awk '{print $1}'`

#cat ../runtime/hyperf.pid | awk '{print $1}' | xargs kill -9 && rm -rf ../runtime/hyperf.pid && rm -rf ../runtime/container
#cat ../runtime/hyperf.pid | awk '{print $2}' | xargs kill -9 && rm -rf ../runtime/hyperf.pid && rm -rf ../runtime/container
kill -9 $PID &&  rm -rf ./runtime/container

fi
php hyperf.php start