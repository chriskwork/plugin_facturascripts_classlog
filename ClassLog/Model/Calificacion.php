<?php
namespace FacturaScripts\Plugins\ClassLog\Model;

use FacturaScripts\Core\Template\ModelClass;
use FacturaScripts\Core\Template\ModelTrait;
use FacturaScripts\Core\Tools;

class Calificacion extends ModelClass
{
    use ModelTrait;

    public $id;
    public $usuario_id;
    public $curso_id;
    public $tipo;
    public $nobre;
    public $nota;
    public $nota_maxima;
    public $fecha;

    public function clear(): void
    {
        parent::clear();
        $this->nota_maxima = 10.00;
    }

    public static function primaryColumn(): string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'cl_calificaciones';
    }
}