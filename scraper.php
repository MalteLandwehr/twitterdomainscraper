<?php
require 'scraperwiki.php';
$account = "MySEOSolution";
$maxTweets = 20;

/**
DO NOT EDIT BELOW
(c) Pascal Landau
*/

$url = "https://twitter.com/i/profiles/show/".urlencode($account)."/timeline/with_replies?include_available_features=1&include_entities=1&last_note_ts=0";
$content = file_get_contents($url);
$json = json_decode($content, true);
if($json === false){
    die("unexpected response");
}
$pattern = "#data-item-id=\"(?P<id>\\d+?\")#";
$seenIds = array();
if(!preg_match($pattern, $json["items_html"], $match)){
    die("no tweets found");
}else{
    $offset = "&max_id=".$match["id"];
    $seenIds[$match["id"]] = "";
}
$tweetsPerPage = 20;
$maxScrolls = ceil($maxTweets / $tweetsPerPage);

$fullHtml = array('<?xml encoding="UTF-8"><html><head><meta encoding="utf-8"></head><body>');
for($i = 0; $i < $maxScrolls; $i++){
    echo "Scrolling ".($i).". time...\n";
    $content = file_get_contents($url.$offset);
    $json = json_decode($content, true);
    if($json === false){
        continue;
    }
    $fullHtml[] = $json["items_html"];
    $maxId = "";
    if(array_key_exists("max_id", $json)){
        $maxId = $json["max_id"];
    }
    else{
        echo "ERROR: no next max_id found";
        break;
    }
    echo $maxId."\n";
    if(array_key_exists($maxId, $seenIds) || $maxId == -1){
        echo "Finished scrolling...\n";
        break;
    }
    $seenIds[$maxId] = "";
    $offset = "&max_id=$maxId";
}
$fullHtml[] = "</body></html>";
$content = implode("\n", $fullHtml);
// echo $content;
$doc = new DOMDocument();
$previous_value = libxml_use_internal_errors(TRUE); //surpress HTML validation warnings, see http://stackoverflow.com/a/7082487/413531
if(!@$doc->loadHTML($content)){
    die("Error: Could not generate HTML document from $url!");
}
libxml_clear_errors();
libxml_use_internal_errors($previous_value);

$xpath = new DOMXPath($doc);
$urls = array();
$expression = "//p[contains(./@class,'tweet-text')]//a[contains(./@href,'http://t.co/')]/@href";
$nodes = $xpath->query($expression);
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_ENCODING, "");
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);

    $i = 0;
foreach($nodes as $node){
    $i++;
    echo "Checking $i. of ".$nodes->length." URLs...\n";
    $url = trim($node->nodeValue);
    curl_setopt($ch, CURLOPT_URL, $url);
    if(!curl_exec($ch)){
        echo "Error at $url\n";
        continue;
    }
    $curl_info = curl_getinfo($ch);
    $foundUrl = $curl_info['url'];
    $host = parse_url($foundUrl, PHP_URL_HOST);
    if(!array_key_exists($host,$urls)){
        $urls[$host] = array();
    }
    echo "$url => $host ($foundUrl)\n";
    $urls[$host][] = $foundUrl;
}
curl_close($ch);
$urlCounts = array();
foreach($urls as $host => $foundUrls){
    $urlCounts[$host] = count($foundUrls);
}
arsort($urlCounts);
echo "\n========\nRESULTS\n========\n";
foreach($urlCounts as $host => $count){
    echo "$host => $count\n";
    $records[] = array("Host" => $host, "Count" => $count);
}
//scraperwiki::save_sqlite(array("URL", "Count"),$records);

?>
