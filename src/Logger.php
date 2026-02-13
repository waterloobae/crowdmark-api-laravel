<?php
namespace Waterloobae\CrowdmarkDashboard;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
} elseif (session_status() !== PHP_SESSION_ACTIVE) {
    session_destroy();
    session_start();
}

class Logger {
    private string $error_msg = '';
    private string $warning_msg = '';
    private string $info_msg = '';

    public function __construct() {
        // constructor
    }

    public function getError() {
        return $this->error_msg;
    }

    public function getWarning() {
        return $this->warning_msg;
    }

    public function getInfo() {
        return $this->info_msg;
    }

    public function setError($message) {
        $this->error_msg = $message;
        $_SESSION['crowdmark_dashboard']['error_msg'] = $message;
    }

    public function setWarning($message) {
        $this->warning_msg = $message;
        $_SESSION['crowdmark_dashboard']['warning_msg'] = $message;
    }

    public function setInfo($message) {
        $this->info_msg = $message;
        $_SESSION['crowdmark_dashboard']['info_msg'] = $message;
    }

    public function clearError() {
        $this->error_msg = '';
        $_SESSION['crowdmark_dashboard']['error_msg'] = '';
    }

    public function clearWarning() {
        $this->warning_msg = '';
        $_SESSION['crowdmark_dashboard']['warning_msg'] = '';
    }

    public function clearInfo() {
        $this->info_msg = '';
        $_SESSION['crowdmark_dashboard']['info_msg'] = '';
    }
}
