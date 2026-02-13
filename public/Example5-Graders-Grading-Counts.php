<?php
namespace Waterloobae\CrowdmarkDashboard;
require_once __DIR__ . '/../vendor/autoload.php';
use Waterloobae\CrowdmarkDashboard\Crowdmark;
$crowdmark_api_key = "your_crowdmark_api_key";
$dashboard = new Dashboard($crowdmark_api_key);

echo "<h1>Graders Grading Counts</h1>";
echo "<p>generate a list of questions counts for each grader</p>";

$start_time = "Start Time:" . date("Y-m-d H:i:s") . "<br>";
echo($start_time);

$crowdmark->createDownloadLinks('grader', ['Course A','Course B', 'Course C']);

echo("End Time:" . date("Y-m-d H:i:s") . "<br>");
