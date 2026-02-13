<?php
namespace Waterloobae\CrowdmarkDashboard;
require_once __DIR__ . '/../vendor/autoload.php';
use Waterloobae\CrowdmarkDashboard\Crowdmark;
$crowdmark_api_key = "your_crowdmark_api_key";
$dashboard = new Dashboard($crowdmark_api_key);

echo "<h1>Check Response Count</h1>";
echo "<p>Check the response count of the booklets</p>";
echo "<p>It is good to check the response count of the booklets to make sure that the count is correct.</p>";

$start_time = "Start Time:" . date("Y-m-d H:i:s") . "<br>";
echo($start_time);

$crowdmark->createDownloadLinks('integritycheck', ['Course A','Course B', 'Course C']);

echo("End Time:" . date("Y-m-d H:i:s") . "<br>");
