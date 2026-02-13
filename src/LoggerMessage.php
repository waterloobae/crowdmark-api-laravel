<?php
namespace Waterloobae\CrowdmarkDashboard;
if (session_status() === PHP_SESSION_NONE) {
    session_start();
} elseif (session_status() !== PHP_SESSION_ACTIVE) {
    session_destroy();
    session_start();
}
// This file is used to log messages and errors in the Crowdmark Dashboard application.
// header('Content-Type: application/json');
// echo json_encode(["error_msg" => $_SESSION['crowdmark_dashboard']['error_msg'] ?? "NA", "warning_msg" => $_SESSION['crowdmark_dashboard']['warning_msg'] ?? "NA", "info_msg" => $_SESSION['crowdmark_dashboard']['info_msg'] ?? "NA"]);
