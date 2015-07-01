<?php

define('START', microtime(1));
define('RESULTS', __DIR__.'/results/');
define('DEBUG', isset($_SERVER['argv'][1]));

if (!file_exists(RESULTS)) {
	mkdir(RESULTS);
}

$urls = array(
	'http://pcso-lotto-results-and-statistics.webnatin.com/6-55results.asp',
	'http://pcso-lotto-results-and-statistics.webnatin.com/6-49results.asp',
	'http://pcso-lotto-results-and-statistics.webnatin.com/6-45results.asp',
	'http://pcso-lotto-results-and-statistics.webnatin.com/6-42results.asp',
	'http://pcso-lotto-results-and-statistics.webnatin.com/6-dresults.asp',
	'http://pcso-lotto-results-and-statistics.webnatin.com/4-dresults.asp',
	'http://pcso-lotto-results-and-statistics.webnatin.com/3-dresults.asp',
	'http://pcso-lotto-results-and-statistics.webnatin.com/2-dresults.asp',
);
make_request($urls, 'convert_csv');

function get_rows($data, $large)
{
	if (!$large) {
		$data = str_replace('&nbsp;', '', $data);
	}
	$i = 0;
	while(true)
	{
		$start = strpos($data, '<tr', $i);
		if ($start === false) break;
		$end = strpos($data, '</tr>', $start);
		$i = $end;
		$length = $end - $start + 5;
		$row = substr($data, $start, $length);
		if ($large) {
			$row = str_replace('&nbsp;', '', $row);
		}
		$cols = explode('<td', $row);
		unset($cols[0]); // tr
		$row = array_map(function($r) { // clean
			return trim(strip_tags('<td'.$r));
		}, $cols);
		yield $row;
	}
}

function convert_csv($url, $data, $size)
{
	$start = microtime(1);
	if (DEBUG) echo sprintf('%s%s  => [ download:  %.2fs, ', $url, PHP_EOL, $start-START);
	$filename = RESULTS.str_replace('.asp', '.csv', basename($url));
	$fp = fopen($filename, 'w');
	$large = $size > 90000;
	foreach (get_rows($data, $large) as $row) {
		fputcsv($fp, $row);
	}
	fclose($fp);
	$end = microtime(1);
	if (DEBUG) echo sprintf('parsing: %.2fs, total: %.2fs ]', $end-$start, $end-START), PHP_EOL;
}

function make_request($urls, $callback)
{
    $master = curl_multi_init();
	$options = array(
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_HTTPHEADER => ['Accept-Encoding: gzip,deflate'],
		CURLOPT_ENCODING => 'gzip,deflate',
	);
    foreach ($urls as $url) {
        $ch = curl_init($url);
        curl_setopt_array($ch, $options);
        curl_multi_add_handle($master, $ch);
    }

    do {
        while(($execrun = curl_multi_exec($master, $running)) == CURLM_CALL_MULTI_PERFORM);
        if($execrun != CURLM_OK)
            break;
        while($done = curl_multi_info_read($master)) {
            $info = curl_getinfo($done['handle']);
            if ($info['http_code'] == 200)  {
                $output = curl_multi_getcontent($done['handle']);
                curl_multi_remove_handle($master, $done['handle']);
                $callback($info['url'], $output, $info['size_download']);
            }
        }
    } while ($running);
    
    curl_multi_close($master);
}
