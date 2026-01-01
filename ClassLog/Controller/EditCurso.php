<?php
namespace FacturaScripts\Plugins\ClassLog\Controller;

use FacturaScripts\Core\Lib\ExtendedController\EditController;

class EditCurso extends EditController
{
    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'ClassLog 🧾';
        $data['title'] = 'Editar Curso';
        $data['icon'] = 'fas fa-book';
        return $data;
    }

    public function getModelClassName(): string
    {
        return 'Curso';
    }
}