<?php
namespace FacturaScripts\Plugins\ClassLog;

use FacturaScripts\Core\Template\InitClass;
use FacturaScripts\Core\Controller\ApiRoot;
use FacturaScripts\Core\Kernel;

class Init extends InitClass
{
    public function init(): void
    {
        // registrar rutas de la api
        Kernel::addRoute('/api/3/cl-auth', 'ApiAuth', -1);
        ApiRoot::addCustomResource('cl-auth');

        Kernel::addRoute('/api/3/cl-student', 'ApiStudent', -1);
        ApiRoot::addCustomResource('cl-student');

    }

    public function uninstall(): void
    {
        // limpiar datos al desinstalar
    }

    public function update(): void
    {
        // ajustes al actualizar
    }

}