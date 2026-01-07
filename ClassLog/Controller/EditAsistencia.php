<?php
namespace FacturaScripts\Plugins\ClassLog\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\ClassLog\Model\Horario;
use FacturaScripts\Plugins\ClassLog\Model\Curso;
use FacturaScripts\Plugins\ClassLog\Model\Matricula;
use FacturaScripts\Plugins\ClassLog\Model\Asistencia;

class EditAsistencia extends Controller
{
    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'ClassLog 🧾';
        $data['title'] = 'Pasar Asistencia';
        $data['icon'] = 'fas fa-user-check';
        return $data;
    }

    public function privateCore(&$response, $user, $permissions)
    {
        parent::privateCore($response, $user, $permissions);

        $action = $this->request->request->get('action', '');

        if ($action === 'marcar-asistencia') {
            $this->marcarAsistenciaAction();
        }

        $this->setTemplate('EditAsistencia');
    }

    private function marcarAsistenciaAction(): void
    {
        // 디버깅 로그
        Tools::log()->notice('=== marcarAsistenciaAction START ===');
        
        // POST 데이터 받기
        $horarioId = $this->request->request->get('horario_id');
        
        // estudiantes 배열 - $_POST 직접 사용
        $estudiantesPresentes = $_POST['estudiantes'] ?? [];
        
        $fecha = date('Y-m-d');

        // Tools::log()->notice('horario_id: ' . $horarioId);
        // Tools::log()->notice('estudiantes (raw): ' . json_encode($estudiantesPresentes));
        // Tools::log()->notice('estudiantes count: ' . count($estudiantesPresentes));
        // Tools::log()->notice('fecha: ' . $fecha);

        if (empty($horarioId)) {
            Tools::log()->error('no-horario-specified');
            return;
        }

        if (!is_array($estudiantesPresentes)) {
            Tools::log()->warning('estudiantes is not array, converting...');
            $estudiantesPresentes = [];
        }

        // horarioId를 파라미터로 전달
        $todosEstudiantes = $this->getEstudiantesList($horarioId);
        
        Tools::log()->notice('Total estudiantes: ' . count($todosEstudiantes));

        foreach ($todosEstudiantes as $estudiante) {
            $usuarioId = $estudiante['id'];
            $asistencia = new Asistencia();

            $where = [
                new DataBaseWhere('usuario_id', $usuarioId),
                new DataBaseWhere('horario_id', $horarioId),
                new DataBaseWhere('fecha', $fecha)
            ];

            $existing = $asistencia->all($where, [], 0, 1);

            $nuevoEstado = in_array($usuarioId, $estudiantesPresentes) 
                            ? 'presente' 
                            : 'ausente';

            Tools::log()->notice("Usuario {$usuarioId}: estado = {$nuevoEstado}, in_array = " . (in_array($usuarioId, $estudiantesPresentes) ? 'true' : 'false'));

            if (!empty($existing)) {
                // 업데이트
                $asistencia = $existing[0];
                
                if ($asistencia->estado !== $nuevoEstado) {
                    $asistencia->estado = $nuevoEstado;
                    
                    if ($asistencia->save()) {
                        Tools::log()->notice("Updated usuario {$usuarioId}");
                    } else {
                        Tools::log()->error("Failed to update usuario {$usuarioId}");
                    }
                }
            } else {
                // presente만 새로 저장
                if ($nuevoEstado === 'presente') {
                    $asistencia->usuario_id = $usuarioId;
                    $asistencia->horario_id = $horarioId;
                    $asistencia->fecha = $fecha;
                    $asistencia->estado = $nuevoEstado;
                    
                    if ($asistencia->save()) {
                        Tools::log()->notice("Inserted usuario {$usuarioId}");
                    } else {
                        Tools::log()->error("Failed to insert usuario {$usuarioId}");
                    }
                }
            }
        }

        Tools::log()->notice('=== marcarAsistenciaAction END ===');
        Tools::log()->notice('record-updated-correctly');
    }

    public function getEstudiantesList($horarioId = null): array
    {
        // POST 요청일 때는 파라미터로 받기
        if ($horarioId === null) {
            $horarioId = filter_input(INPUT_GET, 'code');
        }

        if (empty($horarioId)) {
            return [];
        }

        $horario = new Horario();
        $horarios = $horario->all([new DataBaseWhere('id', $horarioId)], [], 0, 1);

        if (empty($horarios)) {
            return [];
        }

        $horario = $horarios[0];

        $matriculaModel = new Matricula();
        $where = [
            new DataBaseWhere('curso_id', $horario->curso_id),
            new DataBaseWhere('activo', true)
        ];

        $matriculas = $matriculaModel->all($where, [], 0, 0);

        $estudiantes = [];
        foreach ($matriculas as $matricula) {
            $sql = "SELECT u.id, u.nombre, u.apellidos, u.email
                    FROM cl_usuarios u
                    WHERE u.id = " . intval($matricula->usuario_id);

            $db = $this->dataBase;
            $result = $db->select($sql);

            if (!empty($result)) {
                $estudiante = $result[0];

                $asistenciaModel = new Asistencia();
                $whereAsistencia = [
                    new DataBaseWhere('usuario_id', $estudiante['id']),
                    new DataBaseWhere('horario_id', $horarioId),
                    new DataBaseWhere('fecha', date('Y-m-d'))
                ];

                $asistencias = $asistenciaModel->all($whereAsistencia, [], 0, 1);
                $yaRegistrado = !empty($asistencias);
                $presente = $yaRegistrado && $asistencias[0]->estado === 'presente';

                $estudiantes[] = [
                    'id' => $estudiante['id'],
                    'nombre' => $estudiante['nombre'],
                    'apellidos' => $estudiante['apellidos'],
                    'email' => $estudiante['email'],
                    'presente' => $presente
                ];
            }
        }

        return $estudiantes;
    }

    public function getHorarioInfo(): ?Horario
    {
        $horarioId = filter_input(INPUT_GET, 'code');

        if (empty($horarioId)) {
            return null;
        }

        $horario = new Horario();
        $horarios = $horario->all([new DataBaseWhere('id', $horarioId)], [], 0, 1);

        if (!empty($horarios)) {
            return $horarios[0];
        }

        return null;
    }

    public function getCursoInfo(): ?Curso
    {
        $horario = $this->getHorarioInfo();

        if ($horario === null) {
            return null;
        }

        $curso = new Curso();
        $cursos = $curso->all([new DataBaseWhere('id', $horario->curso_id)], [], 0, 1);

        if (!empty($cursos)) {
            return $cursos[0];
        }

        return null;
    }
}