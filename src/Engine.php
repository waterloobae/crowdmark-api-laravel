<?php
namespace Waterloobae\CrowdmarkDashboard;

class Engine
{
    public function render(string $viewsName, array $data = []): string
    {
        $path = __DIR__ . '/views/' . $viewsName . '.php';
        if (!file_exists($path)) {
            die('View ['.$path.'] not found');
        }
        $contents = file_get_contents($path);
        foreach ($data as $key => $value) {

            $contents = str_replace(
                '{'.$key.'}', (string)$value, $contents
            );
}
        return $contents;
    }
}