<?php
namespace FacturaScripts\Plugins\ClassLog\Model;

use FacturaScripts\Core\Template\ModelClass;
use FacturaScripts\Core\Template\ModelTrait;
use FacturaScripts\Core\Tools;

class Profesor extends ModelClass
{
    use ModelTrait;

    public $id;
    public $nombre;
    public $apellidos;
    public $email;
    public $telefono;
    public $especialidad;
    

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
        return 'cl_profesores';
    }
}