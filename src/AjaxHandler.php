<?php
namespace Waterloobae\CrowdmarkDashboard;
if (session_status() === PHP_SESSION_NONE) {
    session_start();
} elseif (session_status() !== PHP_SESSION_ACTIVE) {
    session_destroy();
    session_start();
}

if (strpos(__DIR__, '/workspaces') !== false) {
    require_once '/workspaces/vendor/autoload.php';
}else{
    if (function_exists('base_path')) {
        $this_site_root = base_path();
    } else {
        $this_site_root = $_SERVER['DOCUMENT_ROOT'];
    }
}
require_once $this_site_root . '/vendor/autoload.php';

use Waterloobae\CrowdmarkDashboard\Crowdmark;

class AjaxHandler {
    private $actions;
    private object $crowdmark;
    private object $logger;
    // handling closures
    //
    public function __construct() {
        $this->actions = [
            'sayHello' => function($params) {
                return "<h2>Hello There!</h2>".$params;
                //return "Hello, " . $params;
                //return "Hello, ";
            },
            'page_1' => function($params) {
                $this->crowdmark = new Crowdmark();
                $params = is_array($params) ? $params : json_decode($params, true);
                $output = $this->crowdmark->createDownloadLinks('page', $params, '1');
                return $output;
                //return json_encode($output);
            },
            'page_2' => function($params) {
                $this->crowdmark = new Crowdmark();
                $params = is_array($params) ? $params : json_decode($params, true);
                $output = $this->crowdmark->createDownloadLinks('page', $params);
                return $output;
                //return json_encode($output);
            },
            'studentinfo' => $this->createClosure('studentinfo'),
            'studentemaillist' => $this->createClosure('studentemaillist'),
            'grader' => $this->createClosure('grader'),
            'grading' => $this->createClosure('grading'),
            'uploadedmatched' => $this->createClosure('uploadedmatched'),
            'integritycheck' => $this->createClosure('integritycheck'),
            // Add more closures as needed
        ];
    }

    private function createClosure($action) {
        return function($params) use ($action) {
            $this->crowdmark = new Crowdmark();
            $params = is_array($params) ? $params : json_decode($params, true);
            $output = $this->crowdmark->createDownloadLinks($action, $params);
            return $output;
            //return json_encode($output);
        };
    }

    public function handleRequest($actionName, $params) {
        if (isset($this->actions[$actionName])) {
            if($this->actions[$actionName] == 'page_1' || $this->actions[$actionName] == 'page_2') {
                return call_user_func_array('page', $params);
            }
            return call_user_func_array($this->actions[$actionName], $params);
        } else {
            return "Invalid action!";
        }
    }

    public static function generateCSRFToken() {
        if (empty($_SESSION['crowdmark_dashboard']['csrf_token'])) {
            $_SESSION['crowdmark_dashboard']['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['crowdmark_dashboard']['csrf_token'];
    }

    public static function validateCSRFToken($token) {
        return $token === $_SESSION['crowdmark_dashboard']['csrf_token'];
    }

}

$ajaxHandler = new AjaxHandler();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    $csrfToken = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    
    if(!$ajaxHandler::validateCSRFToken($csrfToken)) {
        $response = array("status" => "error", "message" => "Invalid CSRF token.");
    } else {
        $params = isset($_POST['selectedChips']) ? $_POST['selectedChips'] : [];        
        $response = $ajaxHandler->handleRequest($action, [$params]);
        echo json_encode($response);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['csrf'])) {
    echo json_encode(array("csrf_token" => AjaxHandler::generateCSRFToken()));
}