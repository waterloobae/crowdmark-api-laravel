<?php
namespace Waterloobae\CrowdmarkDashboard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AjaxHandler {
    private const GENERATION_ACTIONS = [
        'studentinfo',
        'studentemaillist',
        'grader',
        'grading',
        'uploadedmatched',
        'integritycheck',
    ];

    public function handleRequest(string $actionName, mixed $params): mixed
    {
        $selectedChips = $this->normalizeParams($params);
        $crowdmark = new Crowdmark();

        if ($actionName === 'sayHello') {
            return '<h2>Hello There!</h2>' . implode(', ', $selectedChips);
        }

        if ($actionName === 'page_1') {
            return $crowdmark->createDownloadLinks('page', $selectedChips, '1');
        }

        if ($actionName === 'page_2') {
            return $crowdmark->createDownloadLinks('page', $selectedChips);
        }

        if (in_array($actionName, self::GENERATION_ACTIONS, true)) {
            return $crowdmark->createDownloadLinks($actionName, $selectedChips);
        }

        return 'Invalid action!';
    }

    public function handleHttpRequest(Request $request): JsonResponse
    {
        if ($request->isMethod('get') && $request->boolean('csrf')) {
            return response()->json(['csrf_token' => self::generateCSRFToken()]);
        }

        if (!$request->isMethod('post')) {
            return response()->json(['status' => 'error', 'message' => 'Method not allowed.'], 405);
        }

        $csrfToken = (string) $request->input('csrf_token', $request->input('_token', ''));
        if (!self::validateCSRFToken($csrfToken)) {
            return response()->json(['status' => 'error', 'message' => 'Invalid CSRF token.'], 419);
        }

        $action = (string) $request->input('action', '');
        $params = $request->input('selectedChips', []);
        $result = $this->handleRequest($action, $params);

        if ($result === 'Invalid action!') {
            return response()->json(['status' => 'error', 'message' => 'Invalid action.'], 422);
        }

        return response()->json($result);
    }

    public static function generateCSRFToken(): string
    {
        if (function_exists('csrf_token')) {
            return csrf_token();
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (empty($_SESSION['crowdmark_dashboard']['csrf_token'])) {
            $_SESSION['crowdmark_dashboard']['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['crowdmark_dashboard']['csrf_token'];
    }

    public static function validateCSRFToken(string $token): bool
    {
        if (function_exists('csrf_token')) {
            return hash_equals(csrf_token(), $token);
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (empty($_SESSION['crowdmark_dashboard']['csrf_token'])) {
            return false;
        }

        return hash_equals($_SESSION['crowdmark_dashboard']['csrf_token'], $token);
    }

    private function normalizeParams(mixed $params): array
    {
        if (is_string($params)) {
            $decoded = json_decode($params, true);
            if (is_array($decoded)) {
                return $decoded;
            }

            return $params === '' ? [] : [$params];
        }

        if (is_array($params)) {
            return $params;
        }

        return [];
    }

}

if (PHP_SAPI !== 'cli' && basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    $ajaxHandler = new AjaxHandler();

    if (class_exists(Request::class) && function_exists('response')) {
        $request = Request::capture();
        $response = $ajaxHandler->handleHttpRequest($request);
        $response->send();
        exit;
    }

    header('Content-Type: application/json');
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!AjaxHandler::validateCSRFToken((string) $csrfToken)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token.']);
            exit;
        }

        $action = (string) ($_POST['action'] ?? '');
        $params = $_POST['selectedChips'] ?? [];
        echo json_encode($ajaxHandler->handleRequest($action, $params));
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['csrf'])) {
        echo json_encode(['csrf_token' => AjaxHandler::generateCSRFToken()]);
        exit;
    }
}