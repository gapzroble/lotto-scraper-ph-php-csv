$start = get-date
start-process php dowload-to-csv.php -nonewwindow -wait
$end = get-date
$elapsed = $end - $start
write-host $end-$start
# datetime format
write-host $elapsed
# easy-to-read format
echo $elapsed
pause
