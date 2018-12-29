#!/bin/bash

echo "loading..."
pid="pidof robot_9909"
echo $pid
kill -USR1 $pid
echo "loading success"
