<?php
class Colaboradores
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT c.id, c.id_persona, c.id_rol_colaborador, rc.nombre nombre_rol, rc.codigo rol_codigo, rc.descripcion descripcion_rol,
        c.id_nivel_escolaridad, ne.nombre nivel_escolaridad,
        c.correo_electronico, c.sobrenombre, c.fecha_ingreso, c.fecha_retiro, c.id_motivo_retiro, mr.nombre nombre_motivo_retiro,
        c.id_cargo, car.nombre nombre_cargo, c.salario_mensual, c.id_tipo_contrato, tc.nombre nombre_tipo_contrato, tc.aplica_nomina,
        c.id_jefe_directo, c.activo, c.valida_ingreso_jornada, c.valida_ingreso_descanso,
        p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido, p.foto,
        p.id_tipo_identificacion, ti.nombre tipo_identificacion, p.numero_identificacion, 
        p.fecha_nacimiento, p.id_genero, g.nombre nombre_genero, p.direccion, p.telefono, p.id_ciudad,
        CONCAT(IFNULL(p.primer_nombre, ''), ' ', IFNULL(p.segundo_nombre, ''), ' ', IFNULL(p.primer_apellido, ''), ' ', IFNULL(p.segundo_apellido, '')) AS nombre_completo,
        CONCAT(IFNULL(pj.primer_nombre, ''), ' ', IFNULL(pj.segundo_nombre, ''), ' ', IFNULL(pj.primer_apellido, ''), ' ', IFNULL(pj.segundo_apellido, '')) AS nombre_jefe_directo
        FROM colaboradores c 
        INNER JOIN roles_colaborador rc ON c.id_rol_colaborador = rc.id
        INNER JOIN niveles_escolaridad ne ON c.id_nivel_escolaridad = ne.id 
        INNER JOIN personas p ON c.id_persona = p.id
        INNER JOIN tipos_identificacion ti ON p.id_tipo_identificacion = ti.id
        INNER JOIN generos g ON p.id_genero = g.id
        LEFT OUTER JOIN motivos_retiro mr ON c.id_motivo_retiro = mr.id
        LEFT OUTER JOIN cargos car ON c.id_cargo = car.id
        LEFT OUTER JOIN tipos_contrato tc ON c.id_tipo_contrato = tc.id
        LEFT OUTER JOIN colaboradores cj ON c.id_jefe_directo = cj.id
        LEFT OUTER JOIN personas pj ON cj.id_persona = pj.id
        WHERE c.id_tenant = :id_tenant
        ORDER BY p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        foreach ($response as &$row) {
            if (isset($row['nombre_completo'])) $row['nombre_completo'] = trim(preg_replace('/\s+/', ' ', $row['nombre_completo']));
            if (isset($row['nombre_jefe_directo'])) $row['nombre_jefe_directo'] = trim(preg_replace('/\s+/', ' ', $row['nombre_jefe_directo']));
        }
        Flight::json($response);
    }

    /**
     * Listado filtrado de colaboradores. Filtros opcionales por query string;
     * los vacíos no se aplican (sin filtros => trae todos los del tenant).
     *   id_rol  => c.id_rol_colaborador
     *   estado  => 'activo' | 'inactivo' (sobre c.activo)
     *   nombre  => coincidencia parcial sobre el nombre completo
     */
    public static function getPorFiltros()
    {
        $db = Flight::db();

        $idRol  = isset(Flight::request()->query['id_rol']) ? trim(Flight::request()->query['id_rol']) : '';
        $estado = isset(Flight::request()->query['estado']) ? trim(Flight::request()->query['estado']) : '';
        $nombre = isset(Flight::request()->query['nombre']) ? trim(Flight::request()->query['nombre']) : '';

        $sql = "SELECT c.id, c.id_persona, c.id_rol_colaborador, rc.nombre nombre_rol, rc.codigo rol_codigo, rc.descripcion descripcion_rol,
        c.id_nivel_escolaridad, ne.nombre nivel_escolaridad,
        c.correo_electronico, c.sobrenombre, c.fecha_ingreso, c.fecha_retiro, c.id_motivo_retiro, mr.nombre nombre_motivo_retiro,
        c.id_cargo, car.nombre nombre_cargo, c.salario_mensual, c.id_tipo_contrato, tc.nombre nombre_tipo_contrato, tc.aplica_nomina,
        c.id_jefe_directo, c.activo, c.valida_ingreso_jornada, c.valida_ingreso_descanso,
        p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido, p.foto,
        p.id_tipo_identificacion, ti.nombre tipo_identificacion, p.numero_identificacion, 
        p.fecha_nacimiento, p.id_genero, g.nombre nombre_genero, p.direccion, p.telefono, p.id_ciudad,
        CONCAT(IFNULL(p.primer_nombre, ''), ' ', IFNULL(p.segundo_nombre, ''), ' ', IFNULL(p.primer_apellido, ''), ' ', IFNULL(p.segundo_apellido, '')) AS nombre_completo,
        CONCAT(IFNULL(pj.primer_nombre, ''), ' ', IFNULL(pj.segundo_nombre, ''), ' ', IFNULL(pj.primer_apellido, ''), ' ', IFNULL(pj.segundo_apellido, '')) AS nombre_jefe_directo
        FROM colaboradores c 
        INNER JOIN roles_colaborador rc ON c.id_rol_colaborador = rc.id
        INNER JOIN niveles_escolaridad ne ON c.id_nivel_escolaridad = ne.id 
        INNER JOIN personas p ON c.id_persona = p.id
        INNER JOIN tipos_identificacion ti ON p.id_tipo_identificacion = ti.id
        INNER JOIN generos g ON p.id_genero = g.id
        LEFT OUTER JOIN motivos_retiro mr ON c.id_motivo_retiro = mr.id
        LEFT OUTER JOIN cargos car ON c.id_cargo = car.id
        LEFT OUTER JOIN tipos_contrato tc ON c.id_tipo_contrato = tc.id
        LEFT OUTER JOIN colaboradores cj ON c.id_jefe_directo = cj.id
        LEFT OUTER JOIN personas pj ON cj.id_persona = pj.id
        WHERE c.id_tenant = :id_tenant";

        $params = [];

        if ($idRol !== '') {
            $sql .= " AND c.id_rol_colaborador = :id_rol";
            $params[':id_rol'] = $idRol;
        }
        if ($estado === 'activo') {
            $sql .= " AND c.activo = 1";
        } elseif ($estado === 'inactivo') {
            $sql .= " AND c.activo = 0";
        }
        if ($nombre !== '') {
            $sql .= " AND CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido) LIKE :nombre";
            $params[':nombre'] = '%' . $nombre . '%';
        }

        $sql .= " ORDER BY p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido";

        $sentence = $db->prepare($sql);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        foreach ($params as $clave => $valor) {
            $sentence->bindValue($clave, $valor);
        }
        $sentence->execute();
        $response = $sentence->fetchAll();
        foreach ($response as &$row) {
            if (isset($row['nombre_completo'])) $row['nombre_completo'] = trim(preg_replace('/\s+/', ' ', $row['nombre_completo']));
            if (isset($row['nombre_jefe_directo'])) $row['nombre_jefe_directo'] = trim(preg_replace('/\s+/', ' ', $row['nombre_jefe_directo']));
        }
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT c.id, c.id_persona, c.id_rol_colaborador, rc.nombre nombre_rol, rc.codigo rol_codigo, rc.descripcion descripcion_rol,
        c.id_nivel_escolaridad, ne.nombre nivel_escolaridad,
        c.correo_electronico, c.sobrenombre, c.fecha_ingreso, c.fecha_retiro, c.id_motivo_retiro, mr.nombre nombre_motivo_retiro,
        c.id_cargo, car.nombre nombre_cargo, c.salario_mensual, c.id_tipo_contrato, tc.nombre nombre_tipo_contrato, tc.aplica_nomina,
        c.id_jefe_directo, c.activo, c.valida_ingreso_jornada, c.valida_ingreso_descanso,
        p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido, 
        p.id_tipo_identificacion, ti.nombre tipo_identificacion, p.numero_identificacion, 
        p.fecha_nacimiento, p.id_genero, g.nombre nombre_genero, p.direccion, p.telefono, p.id_ciudad,
        CONCAT(IFNULL(p.primer_nombre, ''), ' ', IFNULL(p.segundo_nombre, ''), ' ', IFNULL(p.primer_apellido, ''), ' ', IFNULL(p.segundo_apellido, '')) AS nombre_completo,
        TIMESTAMPDIFF(YEAR, p.fecha_nacimiento, CURDATE()) AS edad,
        CONCAT(IFNULL(pj.primer_nombre, ''), ' ', IFNULL(pj.segundo_nombre, ''), ' ', IFNULL(pj.primer_apellido, ''), ' ', IFNULL(pj.segundo_apellido, '')) AS nombre_jefe_directo
        FROM colaboradores c 
        INNER JOIN roles_colaborador rc ON c.id_rol_colaborador = rc.id
        INNER JOIN niveles_escolaridad ne ON c.id_nivel_escolaridad = ne.id 
        INNER JOIN personas p ON c.id_persona = p.id
        INNER JOIN tipos_identificacion ti ON p.id_tipo_identificacion = ti.id
        INNER JOIN generos g ON p.id_genero = g.id
        LEFT OUTER JOIN motivos_retiro mr ON c.id_motivo_retiro = mr.id
        LEFT OUTER JOIN cargos car ON c.id_cargo = car.id
        LEFT OUTER JOIN tipos_contrato tc ON c.id_tipo_contrato = tc.id
        LEFT OUTER JOIN colaboradores cj ON c.id_jefe_directo = cj.id
        LEFT OUTER JOIN personas pj ON cj.id_persona = pj.id
        WHERE c.id = :id AND c.id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        if (!empty($response)) {
            foreach ($response as &$row) {
                if (isset($row['nombre_completo'])) $row['nombre_completo'] = trim(preg_replace('/\s+/', ' ', $row['nombre_completo']));
                if (isset($row['nombre_jefe_directo'])) $row['nombre_jefe_directo'] = trim(preg_replace('/\s+/', ' ', $row['nombre_jefe_directo']));
            }
        }
        Flight::json($response);
    }

    public static function getByIdPersona($id_persona)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT c.id, c.id_persona, c.id_rol_colaborador, rc.nombre nombre_rol, rc.codigo rol_codigo, rc.descripcion descripcion_rol,
        c.id_nivel_escolaridad, ne.nombre nivel_escolaridad,
        c.correo_electronico, c.sobrenombre, c.fecha_ingreso, c.fecha_retiro, c.id_motivo_retiro, mr.nombre nombre_motivo_retiro,
        c.id_cargo, car.nombre nombre_cargo, c.salario_mensual, c.id_tipo_contrato, tc.nombre nombre_tipo_contrato, tc.aplica_nomina,
        c.id_jefe_directo, c.activo, c.valida_ingreso_jornada, c.valida_ingreso_descanso,
        p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido, 
        p.id_tipo_identificacion, ti.nombre tipo_identificacion, p.numero_identificacion, 
        p.fecha_nacimiento, p.id_genero, g.nombre nombre_genero, p.direccion, p.telefono, p.id_ciudad
        FROM colaboradores c 
        INNER JOIN roles_colaborador rc ON c.id_rol_colaborador = rc.id
        INNER JOIN niveles_escolaridad ne ON c.id_nivel_escolaridad = ne.id 
        INNER JOIN personas p ON c.id_persona = p.id
        INNER JOIN tipos_identificacion ti ON p.id_tipo_identificacion = ti.id
        INNER JOIN generos g ON p.id_genero = g.id
        LEFT OUTER JOIN motivos_retiro mr ON c.id_motivo_retiro = mr.id
        LEFT OUTER JOIN cargos car ON c.id_cargo = car.id
        LEFT OUTER JOIN tipos_contrato tc ON c.id_tipo_contrato = tc.id
        WHERE c.id_persona = :id_persona AND c.id_tenant = :id_tenant");
        $sentence->bindParam(':id_persona', $id_persona);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        $db = Flight::db();
        try {
            $db->beginTransaction();
            $id_persona = Flight::request()->data['id_persona'];
            $id_rol_colaborador = Flight::request()->data['id_rol_colaborador'];
            $id_nivel_escolaridad = Flight::request()->data['id_nivel_escolaridad'];
            $correo_electronico = isset(Flight::request()->data['correo_electronico']) ? Flight::request()->data['correo_electronico'] : null;
            $sobrenombre = isset(Flight::request()->data['sobrenombre']) ? Flight::request()->data['sobrenombre'] : null;
            $fecha_ingreso = isset(Flight::request()->data['fecha_ingreso']) ? Flight::request()->data['fecha_ingreso'] : null;
            $fecha_retiro = isset(Flight::request()->data['fecha_retiro']) ? Flight::request()->data['fecha_retiro'] : null;
            $id_motivo_retiro = isset(Flight::request()->data['id_motivo_retiro']) ? Flight::request()->data['id_motivo_retiro'] : null;
            $id_cargo = isset(Flight::request()->data['id_cargo']) ? Flight::request()->data['id_cargo'] : null;
            $salario_mensual = isset(Flight::request()->data['salario_mensual']) ? Flight::request()->data['salario_mensual'] : null;
            $id_tipo_contrato = isset(Flight::request()->data['id_tipo_contrato']) ? Flight::request()->data['id_tipo_contrato'] : null;
            $id_jefe_directo = isset(Flight::request()->data['id_jefe_directo']) ? Flight::request()->data['id_jefe_directo'] : null;
            $activo = isset(Flight::request()->data['activo']) ? Flight::request()->data['activo'] : 1;
            $valida_ingreso_jornada = isset(Flight::request()->data['valida_ingreso_jornada']) ? Flight::request()->data['valida_ingreso_jornada'] : 1;
            $valida_ingreso_descanso = isset(Flight::request()->data['valida_ingreso_descanso']) ? Flight::request()->data['valida_ingreso_descanso'] : 0;

            error_log("Datos recibidos para crear colaborador: id_persona=$id_persona, id_rol_colaborador=$id_rol_colaborador");

            $checkSentence = $db->prepare("SELECT id FROM colaboradores WHERE id_persona = :id_persona AND id_tenant = :id_tenant");
            $checkSentence->bindParam(':id_persona', $id_persona);
            $checkSentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $checkSentence->execute();
            if ($checkSentence->fetch()) {
                $db->rollBack();
                Flight::json(array('error' => 'Ya existe un colaborador registrado con esta persona'), 400);
                return;
            }

            $sentence = $db->prepare("INSERT INTO colaboradores(id, id_tenant, id_persona, id_rol_colaborador, id_nivel_escolaridad, correo_electronico, sobrenombre, fecha_ingreso, fecha_retiro, id_motivo_retiro, id_cargo, salario_mensual, id_tipo_contrato, id_jefe_directo, activo, valida_ingreso_jornada, valida_ingreso_descanso) VALUES (:id, :id_tenant, :id_persona, :id_rol_colaborador, :id_nivel_escolaridad, :correo_electronico, :sobrenombre, :fecha_ingreso, :fecha_retiro, :id_motivo_retiro, :id_cargo, :salario_mensual, :id_tipo_contrato, :id_jefe_directo, :activo, :valida_ingreso_jornada, :valida_ingreso_descanso)");
            $sentence->bindParam(':id_persona', $id_persona);
            $sentence->bindParam(':id_rol_colaborador', $id_rol_colaborador);
            $sentence->bindParam(':id_nivel_escolaridad', $id_nivel_escolaridad);
            $sentence->bindParam(':correo_electronico', $correo_electronico);
            $sentence->bindParam(':sobrenombre', $sobrenombre);
            $sentence->bindParam(':fecha_ingreso', $fecha_ingreso);
            $sentence->bindParam(':fecha_retiro', $fecha_retiro);
            $sentence->bindParam(':id_motivo_retiro', $id_motivo_retiro);
            $sentence->bindParam(':id_cargo', $id_cargo);
            $sentence->bindParam(':salario_mensual', $salario_mensual);
            $sentence->bindParam(':id_tipo_contrato', $id_tipo_contrato);
            $sentence->bindParam(':id_jefe_directo', $id_jefe_directo);
            $sentence->bindParam(':activo', $activo);
            $sentence->bindParam(':valida_ingreso_jornada', $valida_ingreso_jornada);
            $sentence->bindParam(':valida_ingreso_descanso', $valida_ingreso_descanso);
            $idColab = Uuid::generar();
            $sentence->bindValue(':id', $idColab);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            $id_colaborador = $idColab;
            if ($id_colaborador == 0) {
                $db->rollBack();
                error_log("Error: El ID del colaborador insertado es 0.");
                Flight::json(array('error' => 'No se pudo crear el colaborador. Intente de nuevo.'), 500);
                return;
            }
            if ($id_jefe_directo !== null && $id_jefe_directo == $id_colaborador) {
                $db->rollBack();
                Flight::json(array('error' => 'El colaborador no puede ser jefe de sí mismo'), 400);
                return;
            }

            error_log("ID colaborador insertado: $id_colaborador");

            $db->commit();
            Flight::json(array('id' => $id_colaborador));
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Error en new de colaboradores: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function replace()
    {
        try {
            $db = Flight::db();
            $db->beginTransaction();

            $id = Flight::request()->data['id'];
            $id_persona = Flight::request()->data['id_persona'];
            $id_rol_colaborador = Flight::request()->data['id_rol_colaborador'];
            $id_nivel_escolaridad = Flight::request()->data['id_nivel_escolaridad'];
            $correo_electronico = isset(Flight::request()->data['correo_electronico']) ? Flight::request()->data['correo_electronico'] : null;
            $sobrenombre = isset(Flight::request()->data['sobrenombre']) ? Flight::request()->data['sobrenombre'] : null;
            $fecha_ingreso = isset(Flight::request()->data['fecha_ingreso']) ? Flight::request()->data['fecha_ingreso'] : null;
            $fecha_retiro = isset(Flight::request()->data['fecha_retiro']) ? Flight::request()->data['fecha_retiro'] : null;
            $id_motivo_retiro = isset(Flight::request()->data['id_motivo_retiro']) ? Flight::request()->data['id_motivo_retiro'] : null;
            $id_cargo = isset(Flight::request()->data['id_cargo']) ? Flight::request()->data['id_cargo'] : null;
            $salario_mensual = isset(Flight::request()->data['salario_mensual']) ? Flight::request()->data['salario_mensual'] : null;
            $id_tipo_contrato = isset(Flight::request()->data['id_tipo_contrato']) ? Flight::request()->data['id_tipo_contrato'] : null;
            $id_jefe_directo = isset(Flight::request()->data['id_jefe_directo']) ? Flight::request()->data['id_jefe_directo'] : null;
            $activo = isset(Flight::request()->data['activo']) ? Flight::request()->data['activo'] : 1;
            $valida_ingreso_jornada = isset(Flight::request()->data['valida_ingreso_jornada']) ? Flight::request()->data['valida_ingreso_jornada'] : 1;
            $valida_ingreso_descanso = isset(Flight::request()->data['valida_ingreso_descanso']) ? Flight::request()->data['valida_ingreso_descanso'] : 0;

            error_log("Actualizando colaborador id=$id, rol=$id_rol_colaborador");

            if ($id_jefe_directo !== null && $id_jefe_directo == $id) {
                $db->rollBack();
                Flight::json(array('error' => 'El colaborador no puede ser jefe de sí mismo'), 400);
                return;
            }

            $checkSentence = $db->prepare("SELECT id FROM colaboradores WHERE id_persona = :id_persona AND id != :id AND id_tenant = :id_tenant");
            $checkSentence->bindParam(':id_persona', $id_persona);
            $checkSentence->bindParam(':id', $id);
            $checkSentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $checkSentence->execute();
            if ($checkSentence->fetch()) {
                $db->rollBack();
                Flight::json(array('error' => 'Ya existe otro colaborador registrado con esta persona'), 400);
                return;
            }

            $getRolActual = $db->prepare("SELECT id_rol_colaborador FROM colaboradores WHERE id = :id AND id_tenant = :id_tenant");
            $getRolActual->bindParam(':id', $id);
            $getRolActual->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $getRolActual->execute();
            $colaboradorActual = $getRolActual->fetch();
            if (!$colaboradorActual) {
                $db->rollBack();
                Flight::json(array('error' => 'No se encontró el colaborador'), 404);
                return;
            }

            $rol_anterior = $colaboradorActual['id_rol_colaborador'];
            $rol_nuevo = $id_rol_colaborador;
            error_log("Cambio de rol: anterior=$rol_anterior, nuevo=$rol_nuevo");

            $sentence = $db->prepare("UPDATE colaboradores SET id_persona = :id_persona, id_rol_colaborador = :id_rol_colaborador, id_nivel_escolaridad = :id_nivel_escolaridad, correo_electronico = :correo_electronico, sobrenombre = :sobrenombre, fecha_ingreso = :fecha_ingreso, fecha_retiro = :fecha_retiro, id_motivo_retiro = :id_motivo_retiro, id_cargo = :id_cargo, salario_mensual = :salario_mensual, id_tipo_contrato = :id_tipo_contrato, id_jefe_directo = :id_jefe_directo, activo = :activo, valida_ingreso_jornada = :valida_ingreso_jornada, valida_ingreso_descanso = :valida_ingreso_descanso WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id_persona', $id_persona);
            $sentence->bindParam(':id_rol_colaborador', $id_rol_colaborador);
            $sentence->bindParam(':id_nivel_escolaridad', $id_nivel_escolaridad);
            $sentence->bindParam(':correo_electronico', $correo_electronico);
            $sentence->bindParam(':sobrenombre', $sobrenombre);
            $sentence->bindParam(':fecha_ingreso', $fecha_ingreso);
            $sentence->bindParam(':fecha_retiro', $fecha_retiro);
            $sentence->bindParam(':id_motivo_retiro', $id_motivo_retiro);
            $sentence->bindParam(':id_cargo', $id_cargo);
            $sentence->bindParam(':salario_mensual', $salario_mensual);
            $sentence->bindParam(':id_tipo_contrato', $id_tipo_contrato);
            $sentence->bindParam(':id_jefe_directo', $id_jefe_directo);
            $sentence->bindParam(':activo', $activo);
            $sentence->bindParam(':valida_ingreso_jornada', $valida_ingreso_jornada);
            $sentence->bindParam(':valida_ingreso_descanso', $valida_ingreso_descanso);
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            $db->commit();
            Flight::json(array('id' => $id, 'message' => 'Colaborador actualizado correctamente'));
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Error en replace: " . $e->getMessage());
            Flight::json(array('error' => 'Hubo un problema al actualizar el colaborador'), 500);
        }
    }

    public static function delete()
    {
        $db = Flight::db();
        try {
            $db->beginTransaction();
            $id = Flight::request()->data['id'];
            error_log("Eliminando colaborador id: $id");

            $getRol = $db->prepare("SELECT rc.codigo AS codigo_rol FROM colaboradores c LEFT JOIN roles_colaborador rc ON rc.id = c.id_rol_colaborador WHERE c.id = :id AND c.id_tenant = :id_tenant");
            $getRol->bindParam(':id', $id);
            $getRol->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $getRol->execute();
            $colaborador = $getRol->fetch();
            if (!$colaborador) {
                $db->rollBack();
                Flight::json(array('error' => 'No se encontró el colaborador'), 404);
                return;
            }

            $sentence = $db->prepare("DELETE FROM colaboradores WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            if ($sentence->rowCount() == 0) {
                $db->rollBack();
                Flight::json(array('error' => 'No se encontró el colaborador'), 404);
                return;
            }

            $db->commit();
            Flight::json(array('id' => $id, 'message' => 'Colaborador eliminado correctamente'));
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Error en delete: " . $e->getMessage());
            Flight::json(array('error' => 'Hubo un problema al eliminar el colaborador'), 500);
        }
    }

    public static function verificarDuplicados()
    {
        $db = Flight::db();
        $id_persona = Flight::request()->data['id_persona'];
        error_log("Verificando duplicados para id_persona: $id_persona");
        $sentence = $db->prepare("SELECT COUNT(*) as total FROM colaboradores WHERE id_persona = :id_persona AND id_tenant = :id_tenant");
        $sentence->bindParam(':id_persona', $id_persona);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetch();
        Flight::json(array('existe' => $response['total'] > 0));
    }
}