<?php
namespace FacturaScripts\Plugins\ClassLog\Model;

use FacturaScripts\Core\Template\ModelClass;
use FacturaScripts\Core\Template\ModelTrait;
use FacturaScripts\Core\Tools;

class Asistencia extends ModelClass
{
    use ModelTrait;

    public $id;
    public $usuario_id;
    public $horario_id;
    public $fecha;
    public $estado;
    public $created_at;

    public function clear(): void
    {
        parent::clear();
        $this->estado = 'pendiente';
    }

    public static function primaryColumn(): string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'cl_asistencias';
    }
}