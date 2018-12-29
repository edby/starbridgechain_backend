echo "loading..."
pid="pidof charge_notify_task.php"
echo $pid
kill -USR1 $pid
echo "loading success"