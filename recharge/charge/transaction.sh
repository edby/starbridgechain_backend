#!/bin/sh
transaction_pid=`ps -ef | grep 'transaction.php' | grep -v grep |awk '{print $2}'`
notifu_task_pid=`ps -ef | grep 'charge_notify_task.php' | grep -v grep |awk '{print $2}'`

if [ "${transaction_pid}" = "" ]
    then
    php transaction.php
fi

if [ "${notifu_task_pid}" = "" ]
    then
    php charge_notify_task.php
fi