<?php
namespace FacturaScripts\Plugins\ClassLog\Model;

use FacturaScripts\Core\Template\ModelClass;
use FacturaScripts\Core\Template\ModelTrait;
use FacturaScripts\Core\Tools;

class Estudiante extends ModelClass
{
    use ModelTrait;

    public $id;
    public $nombre;
    public $apellidos;
    public $email;
    public $password_hash;
    public $telefono;
    public $avatar_url;
    public $created_at;
    public $updated_at;

    public function clear(): void
    {
        parent::clear();
        $this->created_at = Tools::dateTime();
    }

    public static function primaryColumn(): string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'cl_usuarios';
    }
}