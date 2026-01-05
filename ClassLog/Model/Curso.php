<?php
namespace FacturaScripts\Plugins\ClassLog\Model;

use FacturaScripts\Core\Template\ModelClass;
use FacturaScripts\Core\Template\ModelTrait;
use FacturaScripts\Core\Tools;

class Curso extends ModelClass
{
    use ModelTrait;

    public $id;
    public $nombre;
    public $descripcion;
    public $profesor_id;
    public $icono;
    public $color;

    public function clear(): void
    {
        parent::clear();
        $this->icono = 'book';
        $this->color = '#3B82F6';
    }

    public function profesorNombre(): string
    {
        if(empty($this->profesor_id)){
            return 'Sin profesor';
        }

        $profesor = new Profesor();
        $profesor->load('id', $this->profesor_id);

        if(!empty($profesor->nombre)){
            return trim($profesor->nombre . ' ' . $profesor->apellidos);
        }

        return 'Profesor no encontrado (ID: ' . $this->profesor_id . ')';
    }

    public static function primaryColumn(): string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'cl_cursos';
    }
}