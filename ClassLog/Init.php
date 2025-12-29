<?php
namespace FacturaScripts\Plugins\ClassLog;

use FacturaScripts\Core\Template\InitClass;
use FacturaScripts\Core\Controller\ApiRoot;
use FacturaScripts\Core\Kernel;

class Init extends InitClass
{
    public function init(): void
    {
        // Registra la nueva ruta en la API y vincúlala al controlador personalizado
        Kernel::addRoute('/api/3/cl-student', 'ApiStudent', -1);
        ApiRoot::addCustomResource('cl-student');
    }

    public function uninstall(): void
    {
        // Limpieza de datos o configuraciones al desinstalar el plugin
    }

    public function update(): void
    {
        // Ajustes al instalar o actualizar el plugin
    }

}