<?php
namespace FacturaScripts\Plugins\ClassLog\Controller;

use FacturaScripts\Core\Lib\ExtendedController\ListController;

class ListCurso extends ListController
{
    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'ClassLog 🧾';
        $data['title'] = 'Cursos';
        $data['icon'] = 'fas fa-book';
        return $data;
    }

    protected function createViews()
    {
        $this->addView('ListCurso', 'Curso', 'Cursos', 'fas fa-book')
            ->addOrderBy(['id'], 'id')
            ->addOrderBy(['nombre'], 'nombre')
            ->addSearchFields(['nombre', 'descripcion']);
    }
}