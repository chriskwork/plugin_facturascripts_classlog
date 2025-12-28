<?php
namespace FacturaScripts\Plugins\ClassLog\Model;

use FacturaScripts\Core\Template\ModelClass;
use FacturaScripts\Core\Template\ModelTrait;
use FacturaScripts\Core\Tools;

class Horario extends ModelClass
{
    use ModelTrait;

    public $id;
    public $curso_id;
    public $dia_semana;
    public $hora_inicio;
    public $hora_fin;
    public $aula;

    public function clear(): void
    {
        parent::clear();

    }

    public static function primaryColumn(): string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'cl_horarios';
    }
}