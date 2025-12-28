<?php
namespace FacturaScripts\Plugins\ClassLog\Model;

use FacturaScripts\Core\Template\ModelClass;
use FacturaScripts\Core\Template\ModelTrait;
use FacturaScripts\Core\Tools;

class Matricula extends ModelClass
{
    use ModelTrait;

    public $id;
    public $usuario_id;
    public $curso_id;
    public $fecha_matricula;
    public $activo;
    

    public function clear(): void
    {
        parent::clear();
        $this->fecha_matricula = Tools::date();
        $this->activo = true;
    }

    public static function primaryColumn(): string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'cl_matriculas';
    }
}