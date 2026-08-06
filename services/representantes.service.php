<?php
class Representantes
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, id_cliente, id_persona, id_tipo_representante, empresa, cargo, telefono_oficina, es_responsable_pago, autorizado_recoger, autorizado_sistema, activo FROM representantes WHERE id_tenant = :id_tenant");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, id_cliente, id_persona, id_tipo_representante, empresa, cargo, telefono_oficina, es_responsable_pago, autorizado_recoger, autorizado_sistema, activo FROM representantes WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByCliente($idCliente)
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, 'clientes.representantes');

        $db = Flight::db();
        $sentence = $db->prepare("SELECT 
                                  a.id,
                                  a.id_cliente,
                                  a.id_persona,
                                  a.id_tipo_representante,
                                  a.es_responsable_pago,
                                  a.autorizado_recoger,
                                  a.autorizado_sistema,
                                  a.activo,
                                  ta.nombre AS nombre_tipo_representante,
                                  TRIM(CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido)) AS nombre_persona,
                                  p.numero_identificacion AS documento_representante,
                                  p.correo_electronico,
                                  p.foto
                                FROM representantes a
                                INNER JOIN tipos_representante ta ON ta.id = a.id_tipo_representante
                                INNER JOIN personas p ON p.id = a.id_persona
                                WHERE a.id_cliente = :id_cliente AND a.id_tenant = :id_tenant
                                ORDER BY p.primer_apellido ASC, p.primer_nombre ASC");
        $sentence->bindParam(':id_cliente', $idCliente);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'clientes.representantes.administrar');

            $db = Flight::db();
            $db->beginTransaction();

            $id_cliente = Flight::request()->data['id_cliente'];
            $id_persona = Flight::request()->data['id_persona'];
            $id_tipo_representante = Flight::request()->data['id_tipo_representante'];
            $empresa = Flight::request()->data['empresa'] ?? null;
            $cargo = Flight::request()->data['cargo'] ?? null;
            $telefono_oficina = Flight::request()->data['telefono_oficina'] ?? null;
            $es_responsable_pago = Flight::request()->data['es_responsable_pago'];
            $autorizado_recoger = Flight::request()->data['autorizado_recoger'];
            $autorizado_sistema = Flight::request()->data['autorizado_sistema'];
            $activo = Flight::request()->data['activo'];

            $idNew = Uuid::generar();
            $sentence = $db->prepare("INSERT INTO representantes(id, id_tenant, id_cliente, id_persona, id_tipo_representante, empresa, cargo, telefono_oficina, es_responsable_pago, autorizado_recoger, autorizado_sistema, activo) 
                                 VALUES (:id, :id_tenant, :id_cliente, :id_persona, :id_tipo_representante, :empresa, :cargo, :telefono_oficina, :es_responsable_pago, :autorizado_recoger, :autorizado_sistema, :activo)");
            $sentence->bindValue(':id', $idNew);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_cliente', $id_cliente);
            $sentence->bindParam(':id_persona', $id_persona);
            $sentence->bindParam(':id_tipo_representante', $id_tipo_representante);
            $sentence->bindParam(':empresa', $empresa);
            $sentence->bindParam(':cargo', $cargo);
            $sentence->bindParam(':telefono_oficina', $telefono_oficina);
            $sentence->bindParam(':es_responsable_pago', $es_responsable_pago);
            $sentence->bindParam(':autorizado_recoger', $autorizado_recoger);
            $sentence->bindParam(':autorizado_sistema', $autorizado_sistema);
            $sentence->bindParam(':activo', $activo);
            $sentence->execute();
            $id = $idNew;

            $db->commit();
            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            $db->rollback();
            error_log("Error en new representante: " . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    public static function replace()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'clientes.representantes.administrar');

            $db = Flight::db();
            $db->beginTransaction();

            $id = Flight::request()->data['id'];
            $id_cliente = Flight::request()->data['id_cliente'];
            $id_persona = Flight::request()->data['id_persona'];
            $id_tipo_representante = Flight::request()->data['id_tipo_representante'];
            $empresa = Flight::request()->data['empresa'] ?? null;
            $cargo = Flight::request()->data['cargo'] ?? null;
            $telefono_oficina = Flight::request()->data['telefono_oficina'] ?? null;
            $es_responsable_pago = Flight::request()->data['es_responsable_pago'];
            $autorizado_recoger = Flight::request()->data['autorizado_recoger'];
            $autorizado_sistema = Flight::request()->data['autorizado_sistema'];
            $activo = Flight::request()->data['activo'];

            $sentence = $db->prepare("UPDATE representantes SET 
                                id_cliente = :id_cliente, 
                                id_persona = :id_persona, 
                                id_tipo_representante = :id_tipo_representante, 
                                empresa = :empresa,
                                cargo = :cargo,
                                telefono_oficina = :telefono_oficina,
                                es_responsable_pago = :es_responsable_pago,
                                autorizado_recoger = :autorizado_recoger,
                                autorizado_sistema = :autorizado_sistema,
                                activo = :activo 
                                WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_cliente', $id_cliente);
            $sentence->bindParam(':id_persona', $id_persona);
            $sentence->bindParam(':id_tipo_representante', $id_tipo_representante);
            $sentence->bindParam(':empresa', $empresa);
            $sentence->bindParam(':cargo', $cargo);
            $sentence->bindParam(':telefono_oficina', $telefono_oficina);
            $sentence->bindParam(':es_responsable_pago', $es_responsable_pago);
            $sentence->bindParam(':autorizado_recoger', $autorizado_recoger);
            $sentence->bindParam(':autorizado_sistema', $autorizado_sistema);
            $sentence->bindParam(':activo', $activo);
            $sentence->execute();

            $db->commit();
            self::getById($id);
        } catch (Exception $e) {
            $db->rollback();
            error_log("Error en replace representante: " . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    public static function getByIdConUsuario($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT 
                              a.id,
                              a.id_cliente,
                              a.id_persona,
                              a.id_tipo_representante,
                              a.es_responsable_pago,
                              a.autorizado_recoger,
                              a.autorizado_sistema,
                              a.activo,
                              ta.nombre AS nombre_tipo_representante,
                              CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido) AS nombre_persona,
                              p.numero_identificacion AS documento_representante,
                              u.id as id_usuario,
                              u.usuario,
                              u.activo as usuario_activo
                            FROM representantes a
                            INNER JOIN tipos_representante ta ON ta.id = a.id_tipo_representante
                            INNER JOIN personas p ON p.id = a.id_persona
                            LEFT JOIN usuarios u ON u.id_persona = a.id_persona
                            WHERE a.id = :id AND a.id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }


    public static function delete($id)
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'clientes.representantes.administrar');

            $db = Flight::db();

            $sentence = $db->prepare("DELETE FROM representantes WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            if ($sentence->rowCount() > 0) {
                Flight::json(["success" => true, "message" => "Registro eliminado correctamente"]);
            } else {
                Flight::json(["success" => false, "message" => "No se encontró el registro para eliminar"], 404);
            }
        } catch (Exception $e) {
            Flight::json(["success" => false, "message" => "Error en la eliminación", "error" => $e->getMessage()], 500);
        }
    }

    public static function verificarDuplicados()
    {
        $db = Flight::db();
        $id_cliente = Flight::request()->data['id_cliente'];
        $id_persona = Flight::request()->data['id_persona'];
        $id_tipo_representante = Flight::request()->data['id_tipo_representante'];

        $sentence = $db->prepare("SELECT COUNT(*) as total FROM representantes 
                                WHERE id_cliente = :id_cliente 
                                AND id_persona = :id_persona 
                                AND id_tipo_representante = :id_tipo_representante
                                AND id_tenant = :id_tenant");
        $sentence->bindParam(':id_cliente', $id_cliente);
        $sentence->bindParam(':id_persona', $id_persona);
        $sentence->bindParam(':id_tipo_representante', $id_tipo_representante);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetch();

        Flight::json(array('existe' => $response['total'] > 0));
    }

    public static function getClientesByRepresentante($idPersona)
    {
        error_log("=== DEBUG getClientesByRepresentante ===");
        error_log("idPersona recibido: " . $idPersona);

        $db = Flight::db();

        // Debug: verificar representantes de esta persona
        $checkRepresentante = $db->prepare("SELECT id, id_cliente, id_persona, autorizado_sistema, activo FROM representantes WHERE id_persona = :id_persona AND id_tenant = :id_tenant");
        $checkRepresentante->bindParam(':id_persona', $idPersona);
        $checkRepresentante->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $checkRepresentante->execute();
        $representantesDebug = $checkRepresentante->fetchAll();
        error_log("Representantes encontrados para id_persona $idPersona: " . json_encode($representantesDebug));

        $sentence = $db->prepare("SELECT 
                            e.id as id_cliente,
                            e.id_persona,
                            e.fecha_ingreso,
                            e.activo,
                            e.alimentacion,
                            e.telefono_emergencia,
                            e.eps,
                            e.anno,
                            p.primer_nombre,
                            p.segundo_nombre,
                            p.primer_apellido,
                            p.segundo_apellido,
                            p.id_tipo_identificacion,
                            ti.nombre AS tipo_identificacion,
                            p.numero_identificacion,
                            p.fecha_nacimiento,
                            TIMESTAMPDIFF(YEAR, p.fecha_nacimiento, CURDATE()) AS edad,
                            p.id_genero,
                            g.nombre AS nombre_genero,
                            p.direccion,
                            grp.id AS id_plan,
                            grp.nombre AS nombre_plan,
                            CONCAT(p.primer_nombre, ' ', IFNULL(p.segundo_nombre, ''), ' ', p.primer_apellido, ' ', IFNULL(p.segundo_apellido, '')) AS nombre_completo,
                            a.es_responsable_pago,
                            a.autorizado_recoger,
                            ta.nombre AS tipo_representante
                            FROM representantes a
                            INNER JOIN clientes e ON a.id_cliente = e.id
                            INNER JOIN personas p ON e.id_persona = p.id
                            INNER JOIN tipos_identificacion ti ON p.id_tipo_identificacion = ti.id
                            LEFT JOIN generos g ON p.id_genero = g.id
                            INNER JOIN tipos_representante ta ON a.id_tipo_representante = ta.id
                            LEFT JOIN clientes_x_planes eg ON e.id = eg.id_cliente AND eg.activo = 1
                            LEFT JOIN planes grp ON eg.id_plan = grp.id
                            WHERE a.id_persona = :id_persona 
                            AND a.activo = 1 
                            AND a.autorizado_sistema = 1
                            AND e.activo = 1
                            AND a.id_tenant = :id_tenant
                            ORDER BY grp.orden ASC, p.primer_apellido ASC, p.primer_nombre ASC");

        $sentence->bindParam(':id_persona', $idPersona);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();

        Flight::json($response);
    }

    public static function getClientesIdsOnly($idPersona)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT e.id as id_cliente
                                FROM representantes a
                                INNER JOIN clientes e ON a.id_cliente = e.id
                                WHERE a.id_persona = :id_persona 
                                AND a.activo = 1 
                                AND a.autorizado_sistema = 1
                                AND e.activo = 1
                                AND a.id_tenant = :id_tenant");

        $sentence->bindParam(':id_persona', $idPersona);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }
}