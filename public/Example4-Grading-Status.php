<?php
namespace Waterloobae\CrowdmarkDashboard;
require_once __DIR__ . '/../vendor/autoload.php';
use Waterloobae\CrowdmarkDashboard\Crowdmark;
$crowdmark_api_key = "your_crowdmark_api_key";
$dashboard = new Dashboard($crowdmark_api_key);

echo "<h1>Grading Status</h1>";
echo "<p>Check the grading status of the booklets</p>";
echo "<p>It is good to check the grading status of the booklets to make sure that the grading is progressing.</p>";

$start_time = "Start Time:" . date("Y-m-d H:i:s") . "<br>";
echo($start_time);

$crowdmark->createDownloadLinks('grading', ['Course A','Course B', 'Course C']);

echo("End Time:" . date("Y-m-d H:i:s") . "<br>");
