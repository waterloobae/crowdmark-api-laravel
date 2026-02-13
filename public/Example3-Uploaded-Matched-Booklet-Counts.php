<?php
namespace Waterloobae\CrowdmarkDashboard;
require_once __DIR__ . '/../vendor/autoload.php';
use Waterloobae\CrowdmarkDashboard\Crowdmark;
$crowdmark_api_key = "your_crowdmark_api_key";
$dashboard = new Dashboard($crowdmark_api_key);

echo "<h1>Uploaded and Matched Booklet Counts</h1>";
echo "<p>Check the uploaded and matched booklet counts</p>";
echo "<p>It is good to check the uploaded and matched booklet counts to make sure progress.</p>";

$start_time = "Start Time:" . date("Y-m-d H:i:s") . "<br>";
echo($start_time);

$crowdmark->createDownloadLinks('uploadedmatched', ['Course A','Course B', 'Course C']);

echo("End Time:" . date("Y-m-d H:i:s") . "<br>");
