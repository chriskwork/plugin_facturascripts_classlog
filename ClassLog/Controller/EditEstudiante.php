<?php
namespace FacturaScripts\Plugins\ClassLog\Controller;

use FacturaScripts\Core\Lib\ExtendedController\EditController;

class EditEstudiante extends EditController
{
    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'ClassLog 🧾';
        $data['title'] = 'Editar Estudiante';
        $data['icon'] = 'fas fa-user-graduate';
        return $data;
    }

    public function getModelClassName(): string
    {
        return 'Estudiante';
    }
}