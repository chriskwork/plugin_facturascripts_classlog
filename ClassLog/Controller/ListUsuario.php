<?php
namespace FacturaScripts\Plugins\ClassLog\Controller;

use FacturaScripts\Core\Lib\ExtendedController\ListController;

class ListUsuario extends ListController
{
    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'classlog';
        $data['title'] = 'Usuarios';
        $data['icon'] = 'fas fa-users';
        return $data;
    }

    protected function createViews()
    {
        $this->createViewUsuarios();
    }

    protected function createViewUsuarios(string $viewName = 'ListUsuario')
    {
        $this->addView($viewName, 'Usuario', 'usuarios', 'fas fa-users');
        $this->addSearchFields($viewName, ['nombre', 'email']);
        $this->addOrderBy($viewName, ['id'], 'id');
        $this->addOrderBy($viewName, ['nombre'], 'nombre');
    }
}