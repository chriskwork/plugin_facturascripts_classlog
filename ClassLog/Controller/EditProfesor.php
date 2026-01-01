<?php

namespace FacturaScripts\Plugins\ClassLog\Controller;

use FacturaScripts\Core\Lib\ExtendedController\EditController;

class EditProfesor extends EditController
{
    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'ClassLog 🧾';
        $data['title'] = 'Editar Profesor';
        $data['icon'] = 'fas fa-chalkboard-teacher'; 
        return $data;
    }

    public function getModelClassName(): string
    {
        return 'Profesor';
    }
}