<?php
namespace FacturaScripts\Plugins\ClassLog\Model;

use FacturaScripts\Core\Template\ModelClass;
use FacturaScripts\Core\Template\ModelTrait;
use FacturaScripts\Core\Tools;

class Evento extends ModelClass
{
    use ModelTrait;

    public $id;
    public $curso_id;
    public $usuario_id;
    public $tipo;
    public $titulo;
    public $descripcion;
    public $fecha_limite;
    public $completado;

    public function clear(): void
    {
        parent::clear();
        $this->completado = false;
    }

    public static function primaryColumn(): string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'cl_eventos';
    }
}