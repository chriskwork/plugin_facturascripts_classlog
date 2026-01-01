<?php
namespace FacturaScripts\Plugins\ClassLog\Controller;

use FacturaScripts\Core\Lib\ExtendedController\ListController;

class ListProfesor extends ListController
{
    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'ClassLog 🧾';
        $data['title'] = 'Profesores';
        $data['icon'] = 'fas fa-chalkboard-teacher';
        return $data;
    }

    protected function createViews()
    {
        $this->addView('ListProfesor', 'Profesor', 'Profesores', 'fas fa-chalkboard-teacher')
            ->addOrderBy(['id'], 'id')
            ->addOrderBy(['nombre'], 'nombre')
            ->addSearchFields(['nombre', 'email']);
    }
}