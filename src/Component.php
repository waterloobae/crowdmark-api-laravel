<?php
namespace Waterloobae\CrowdmarkDashboard;

class Component {
    private $actions;

    public function __construct() {
        $this->actions = [
            'sayHello' => function($name) {
                return "Hello, $name!";
            },
            'addNumbers' => function($a, $b) {
                return $a + $b;
            },
            // Add more closures as needed
        ];
    }

    public function handleRequest($actionName, $params) {
        if (isset($this->actions[$actionName])) {
            return call_user_func_array($this->actions[$actionName], $params);
        } else {
            return "Invalid action!";
        }
    }
}

$ajaxHandler = new AjaxHandler();

if (isset($_POST['action'])) {
    $action = $_POST['action'];
    $params = isset($_POST['params']) ? $_POST['params'] : [];
    $response = $ajaxHandler->handleRequest($action, $params);
    echo json_encode($response);
}
?>