<?php
namespace FacturaScripts\Plugins\ClassLog\Controller;

use FacturaScripts\Core\Lib\ExtendedController\ListController;

class ListEstudiante extends ListController
{
    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'ClassLog 🧾';
        $data['title'] = 'Estudiantes';
        $data['icon'] = 'fas fa-user-graduate';
        return $data;
    }

    protected function createViews()
    {
        $this->createViewUsuarios();
    }

    protected function createViewUsuarios(): void
    {
        $this->addView('ListEstudiante', 'Estudiante', 'Estudiantes', 'fas fa-user-graduate')
        ->addOrderBy(['id'], 'id')
        ->addOrderBy(['nombre'], 'nombre')
        ->addSearchFields(['nombre', 'email']);

    }
}