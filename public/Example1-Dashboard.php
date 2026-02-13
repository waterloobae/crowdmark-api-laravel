<?php
namespace Waterloobae\CrowdmarkDashboard;
if (session_status() === PHP_SESSION_NONE) {
  session_start();
} elseif (session_status() !== PHP_SESSION_ACTIVE) {
  session_destroy();
  session_start();
}
require_once __DIR__ . '/../vendor/autoload.php';
use Waterloobae\CrowdmarkDashboard\Dashboard;
$crowdmark_api_key = "your_crowdmark_api_key";
$dashboard = new Dashboard($crowdmark_api_key);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Crowdmark Dashboard</title>
    </head>
<body>
  <?=$dashboard->getForm()?>
</body>
</html>