<?php
namespace Waterloobae\CrowdmarkDashboard;
if (session_status() === PHP_SESSION_NONE) {
    session_start();
} elseif (session_status() !== PHP_SESSION_ACTIVE) {
    session_destroy();
    session_start();
}

require_once __DIR__ . '/../vendor/autoload.php';
use Waterloobae\CrowdmarkDashboard\Crowdmark;
$crowdmark_api_key = "your_crowdmark_api_key";
$dashboard = new Dashboard($crowdmark_api_key);

echo "<h1>Downloading Pages</h1>";
echo "<p>Download the pages of the booklets</p>";

$start_time = "Start Time:" . date("Y-m-d H:i:s") . "<br>";
echo($start_time);

//$dashboard->echoLoggerMessage();
//$crowdmark->createDownloadLinks('page', ['Course A','Course B', 'Course C'], '2');
$crowdmark->createDownloadLinks('page', ['Course A','Course B', 'Course C'], '2');

echo("End Time:" . date("Y-m-d H:i:s") . "<br>");

