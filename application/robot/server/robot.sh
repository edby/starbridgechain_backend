#!/bin/bash

URL1="https://apissl.starbridgechain.com:9001/robot_info"
URL2="https://apissl.starbridgechain.com:9001/robot_del_timer"
DATE=$(date -d today +"%Y-%m-%d %T")

request(){
    curl -s $URL2
    info=`curl -s $URL1`

    if [ "$info" == "201" ]
    then
    echo $DATE"--脚本执行失败!未获取到机器人ID!\n" >> robot_status.log

    elif [ "$info" == "202" ]
    then
    echo $DATE"--脚本执行失败!存储机器人ID失败!\n" >> robot_status.log

    elif [ "$info" == "200" ]
    then
    echo $DATE"--成功存储机器人ID!\n" >> robot_status.log

    fi
}

request