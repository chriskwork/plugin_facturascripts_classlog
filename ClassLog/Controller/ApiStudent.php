<?php
namespace FacturaScripts\Plugins\ClassLog\Controller;

use FacturaScripts\Core\Template\ApiController;

class ApiStudent extends ApiController
{
    protected function runResource(): void
    {
        header('Content-Type: application/json');
        echo json_encode([
        'success' => true,
        'message' => 'hola mundo'
    ]);
    }
}