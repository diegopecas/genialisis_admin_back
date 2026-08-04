<?php
class Prestamo
{
    public static function getAll()
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                select 
                    p.id,
                    p.id_colaborador,
                    concat(per.primer_nombre, ' ', coalesce(per.segundo_nombre, ''), ' ', 
                           per.primer_apellido, ' ', coalesce(per.segundo_apellido, '')) as nombre_colaborador,
                    p.id_tipo_prestamo,
                    tp.nombre as nombre_tipo_prestamo,
                    p.fecha_prestamo,
                    p.fecha_inicio_cobro,
                    p.monto_prestado,
                    p.numero_cuotas,
                    p.monto_cuota,
                    p.tasa_interes,
                    p.monto_total,
                    p.id_tipo_descuento,
                    td.nombre as nombre_tipo_descuento,
                    p.id_estado,
                    ep.nombre as nombre_estado,
                    p.observaciones,
                    p.fecha_registro,
                    p.id_usuario_registro,
                    u.usuario as nombre_usuario_registro,
                    concat(p_ur.primer_nombre, ' ', coalesce(p_ur.segundo_nombre, ''), ' ', 
                           p_ur.primer_apellido, ' ', coalesce(p_ur.segundo_apellido, '')) as nombre_completo_usuario_registro,
                    p.fecha_aprobacion,
                    p.id_usuario_aprobacion,
                    ua.usuario as nombre_usuario_aprobacion,
                    concat(p_ua.primer_nombre, ' ', coalesce(p_ua.segundo_nombre, ''), ' ', 
                           p_ua.primer_apellido, ' ', coalesce(p_ua.segundo_apellido, '')) as nombre_completo_usuario_aprobacion,
                    p.fecha_anulacion,
                    p.id_usuario_anulacion,
                    uan.usuario as nombre_usuario_anulacion,
                    concat(p_uan.primer_nombre, ' ', coalesce(p_uan.segundo_nombre, ''), ' ', 
                           p_uan.primer_apellido, ' ', coalesce(p_uan.segundo_apellido, '')) as nombre_completo_usuario_anulacion,
                    p.motivo_anulacion,
                    coalesce((select sum(pp.monto_pagado) from prestamos_pagos pp where pp.id_prestamo = p.id), 0) as total_pagado,
                    (p.monto_total - coalesce((select sum(pp.monto_pagado) from prestamos_pagos pp where pp.id_prestamo = p.id), 0)) as saldo
                from 
                    prestamos p
                inner join 
                    colaboradores c on p.id_colaborador = c.id
                inner join 
                    personas per on c.id_persona = per.id
                left join 
                    tipos_prestamos tp on p.id_tipo_prestamo = tp.id
                left join 
                    tipos_descuento_prestamo td on p.id_tipo_descuento = td.id
                left join 
                    estados_prestamo ep on p.id_estado = ep.id
                left join 
                    usuarios u on p.id_usuario_registro = u.id
                left join 
                    personas p_ur on u.id_persona = p_ur.id
                left join 
                    usuarios ua on p.id_usuario_aprobacion = ua.id
                left join 
                    personas p_ua on ua.id_persona = p_ua.id
                left join 
                    usuarios uan on p.id_usuario_anulacion = uan.id
                left join 
                    personas p_uan on uan.id_persona = p_uan.id
                where 
                    p.id_tenant = :id_tenant
                order by 
                    p.fecha_prestamo desc, p.id desc
            ");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll();
            Flight::json($response);
        } catch (Exception $e) {
            error_log('Error en getAll prestamos: ' . $e->getMessage());
            Flight::json([
                'error' => true,
                'message' => 'Error al obtener los préstamos',
                'detalles' => $e->getMessage()
            ], 500);
        }
    }

    public static function getById($id)
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                select 
                    p.id,
                    p.id_colaborador,
                    concat(per.primer_nombre, ' ', coalesce(per.segundo_nombre, ''), ' ', 
                           per.primer_apellido, ' ', coalesce(per.segundo_apellido, '')) as nombre_colaborador,
                    p.id_tipo_prestamo,
                    tp.nombre as nombre_tipo_prestamo,
                    p.fecha_prestamo,
                    p.fecha_inicio_cobro,
                    p.monto_prestado,
                    p.numero_cuotas,
                    p.monto_cuota,
                    p.tasa_interes,
                    p.monto_total,
                    p.id_tipo_descuento,
                    td.nombre as nombre_tipo_descuento,
                    p.id_estado,
                    ep.nombre as nombre_estado,
                    p.observaciones,
                    p.fecha_registro,
                    p.id_usuario_registro,
                    u.usuario as nombre_usuario_registro,
                    concat(p_ur.primer_nombre, ' ', coalesce(p_ur.segundo_nombre, ''), ' ', 
                           p_ur.primer_apellido, ' ', coalesce(p_ur.segundo_apellido, '')) as nombre_completo_usuario_registro,
                    p.fecha_aprobacion,
                    p.id_usuario_aprobacion,
                    ua.usuario as nombre_usuario_aprobacion,
                    concat(p_ua.primer_nombre, ' ', coalesce(p_ua.segundo_nombre, ''), ' ', 
                           p_ua.primer_apellido, ' ', coalesce(p_ua.segundo_apellido, '')) as nombre_completo_usuario_aprobacion,
                    p.fecha_anulacion,
                    p.id_usuario_anulacion,
                    uan.usuario as nombre_usuario_anulacion,
                    concat(p_uan.primer_nombre, ' ', coalesce(p_uan.segundo_nombre, ''), ' ', 
                           p_uan.primer_apellido, ' ', coalesce(p_uan.segundo_apellido, '')) as nombre_completo_usuario_anulacion,
                    p.motivo_anulacion,
                    coalesce((select sum(pp.monto_pagado) from prestamos_pagos pp where pp.id_prestamo = p.id), 0) as total_pagado,
                    (p.monto_total - coalesce((select sum(pp.monto_pagado) from prestamos_pagos pp where pp.id_prestamo = p.id), 0)) as saldo
                from 
                    prestamos p
                inner join 
                    colaboradores c on p.id_colaborador = c.id
                inner join 
                    personas per on c.id_persona = per.id
                left join 
                    tipos_prestamos tp on p.id_tipo_prestamo = tp.id
                left join 
                    tipos_descuento_prestamo td on p.id_tipo_descuento = td.id
                left join 
                    estados_prestamo ep on p.id_estado = ep.id
                left join 
                    usuarios u on p.id_usuario_registro = u.id
                left join 
                    personas p_ur on u.id_persona = p_ur.id
                left join 
                    usuarios ua on p.id_usuario_aprobacion = ua.id
                left join 
                    personas p_ua on ua.id_persona = p_ua.id
                left join 
                    usuarios uan on p.id_usuario_anulacion = uan.id
                left join 
                    personas p_uan on uan.id_persona = p_uan.id
                where 
                    p.id = :id
                    and p.id_tenant = :id_tenant
            ");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll();
            Flight::json($response);
        } catch (Exception $e) {
            error_log('Error en getById prestamo: ' . $e->getMessage());
            Flight::json([
                'error' => true,
                'message' => 'Error al obtener el préstamo',
                'detalles' => $e->getMessage()
            ], 500);
        }
    }

    public static function getByColaborador($id_colaborador)
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                select 
                    p.id,
                    p.id_colaborador,
                    p.id_tipo_prestamo,
                    tp.nombre as nombre_tipo_prestamo,
                    p.fecha_prestamo,
                    p.fecha_inicio_cobro,
                    p.monto_prestado,
                    p.numero_cuotas,
                    p.monto_cuota,
                    p.tasa_interes,
                    p.monto_total,
                    p.id_tipo_descuento,
                    td.nombre as nombre_tipo_descuento,
                    p.id_estado,
                    ep.nombre as nombre_estado,
                    p.observaciones,
                    coalesce((select sum(pp.monto_pagado) from prestamos_pagos pp where pp.id_prestamo = p.id), 0) as total_pagado,
                    (p.monto_total - coalesce((select sum(pp.monto_pagado) from prestamos_pagos pp where pp.id_prestamo = p.id), 0)) as saldo
                from 
                    prestamos p
                left join 
                    tipos_prestamos tp on p.id_tipo_prestamo = tp.id
                left join 
                    tipos_descuento_prestamo td on p.id_tipo_descuento = td.id
                left join 
                    estados_prestamo ep on p.id_estado = ep.id
                where 
                    p.id_colaborador = :id_colaborador
                    and p.id_tenant = :id_tenant
                order by 
                    p.fecha_prestamo desc, p.id desc
            ");
            $sentence->bindParam(':id_colaborador', $id_colaborador);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll();
            Flight::json($response);
        } catch (Exception $e) {
            error_log('Error en getByColaborador prestamo: ' . $e->getMessage());
            Flight::json([
                'error' => true,
                'message' => 'Error al obtener los préstamos del colaborador',
                'detalles' => $e->getMessage()
            ], 500);
        }
    }

    public static function new()
    {
        try {
            $db = Flight::db();

            $id_colaborador = Flight::request()->data['id_colaborador'];
            $id_tipo_prestamo = Flight::request()->data['id_tipo_prestamo'];
            $fecha_prestamo = Flight::request()->data['fecha_prestamo'];
            $fecha_inicio_cobro = Flight::request()->data['fecha_inicio_cobro'];
            $monto_prestado = Flight::request()->data['monto_prestado'];
            $numero_cuotas = Flight::request()->data['numero_cuotas'];
            $monto_cuota = Flight::request()->data['monto_cuota'];
            $tasa_interes = Flight::request()->data['tasa_interes'];
            $monto_total = Flight::request()->data['monto_total'];
            $id_tipo_descuento = Flight::request()->data['id_tipo_descuento'];
            $id_estado = Flight::request()->data['id_estado'];
            $observaciones = Flight::request()->data['observaciones'];
            $fecha_registro = Flight::request()->data['fecha_registro'];
            $id_usuario_registro = Flight::request()->data['id_usuario_registro'];

            $sentence = $db->prepare("
                insert into prestamos(
                    id, id_tenant, id_colaborador, id_tipo_prestamo, fecha_prestamo, fecha_inicio_cobro,
                    monto_prestado, numero_cuotas, monto_cuota, tasa_interes, monto_total,
                    id_tipo_descuento, id_estado, observaciones, fecha_registro, id_usuario_registro
                ) values (
                    :id, :id_tenant, :id_colaborador, :id_tipo_prestamo, :fecha_prestamo, :fecha_inicio_cobro,
                    :monto_prestado, :numero_cuotas, :monto_cuota, :tasa_interes, :monto_total,
                    :id_tipo_descuento, :id_estado, :observaciones, :fecha_registro, :id_usuario_registro
                )
            ");

            $id = Uuid::generar();
            $sentence->bindValue(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_colaborador', $id_colaborador);
            $sentence->bindParam(':id_tipo_prestamo', $id_tipo_prestamo);
            $sentence->bindParam(':fecha_prestamo', $fecha_prestamo);
            $sentence->bindParam(':fecha_inicio_cobro', $fecha_inicio_cobro);
            $sentence->bindParam(':monto_prestado', $monto_prestado);
            $sentence->bindParam(':numero_cuotas', $numero_cuotas);
            $sentence->bindParam(':monto_cuota', $monto_cuota);
            $sentence->bindParam(':tasa_interes', $tasa_interes);
            $sentence->bindParam(':monto_total', $monto_total);
            $sentence->bindParam(':id_tipo_descuento', $id_tipo_descuento);
            $sentence->bindParam(':id_estado', $id_estado);
            $sentence->bindParam(':observaciones', $observaciones);
            $sentence->bindParam(':fecha_registro', $fecha_registro);
            $sentence->bindParam(':id_usuario_registro', $id_usuario_registro);

            $sentence->execute();

            Flight::json(array('id' => $id));
        } catch (PDOException $e) {
            error_log("Error PDO en new prestamo: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        } catch (Exception $e) {
            error_log("Error general en new prestamo: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function replace()
    {
        try {
            $db = Flight::db();

            $id = Flight::request()->data['id'];
            $id_colaborador = Flight::request()->data['id_colaborador'];
            $id_tipo_prestamo = Flight::request()->data['id_tipo_prestamo'];
            $fecha_prestamo = Flight::request()->data['fecha_prestamo'];
            $fecha_inicio_cobro = Flight::request()->data['fecha_inicio_cobro'];
            $monto_prestado = Flight::request()->data['monto_prestado'];
            $numero_cuotas = Flight::request()->data['numero_cuotas'];
            $monto_cuota = Flight::request()->data['monto_cuota'];
            $tasa_interes = Flight::request()->data['tasa_interes'];
            $monto_total = Flight::request()->data['monto_total'];
            $id_tipo_descuento = Flight::request()->data['id_tipo_descuento'];
            $id_estado = Flight::request()->data['id_estado'];
            $observaciones = Flight::request()->data['observaciones'];

            $sentence = $db->prepare("
                update prestamos set 
                    id_colaborador = :id_colaborador,
                    id_tipo_prestamo = :id_tipo_prestamo,
                    fecha_prestamo = :fecha_prestamo,
                    fecha_inicio_cobro = :fecha_inicio_cobro,
                    monto_prestado = :monto_prestado,
                    numero_cuotas = :numero_cuotas,
                    monto_cuota = :monto_cuota,
                    tasa_interes = :tasa_interes,
                    monto_total = :monto_total,
                    id_tipo_descuento = :id_tipo_descuento,
                    id_estado = :id_estado,
                    observaciones = :observaciones
                where id = :id
                and id_tenant = :id_tenant
            ");

            $sentence->bindParam(':id', $id);
            $sentence->bindParam(':id_colaborador', $id_colaborador);
            $sentence->bindParam(':id_tipo_prestamo', $id_tipo_prestamo);
            $sentence->bindParam(':fecha_prestamo', $fecha_prestamo);
            $sentence->bindParam(':fecha_inicio_cobro', $fecha_inicio_cobro);
            $sentence->bindParam(':monto_prestado', $monto_prestado);
            $sentence->bindParam(':numero_cuotas', $numero_cuotas);
            $sentence->bindParam(':monto_cuota', $monto_cuota);
            $sentence->bindParam(':tasa_interes', $tasa_interes);
            $sentence->bindParam(':monto_total', $monto_total);
            $sentence->bindParam(':id_tipo_descuento', $id_tipo_descuento);
            $sentence->bindParam(':id_estado', $id_estado);
            $sentence->bindParam(':observaciones', $observaciones);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);

            $sentence->execute();
            self::getById($id);
        } catch (Exception $e) {
            error_log("Error en replace prestamo: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function delete()
    {
        try {
            $data = Flight::request()->data->getData();
            $id = $data['id'];

            if (!$id) {
                Flight::json(['error' => 'ID no proporcionado'], 400);
                return;
            }

            $db = Flight::db();

            // Iniciar transacción
            $db->beginTransaction();

            try {
                // 1. Primero eliminar los pagos relacionados con las cuotas del préstamo
                $stmtPagos = $db->prepare("
                DELETE FROM prestamos_pagos 
                WHERE id_prestamo = :id AND id_tenant = :id_tenant
            ");
                $stmtPagos->execute(['id' => $id, 'id_tenant' => TenantContext::id()]);

                // 2. Luego eliminar las cuotas del préstamo
                $stmtCuotas = $db->prepare("
                DELETE FROM prestamos_cuotas 
                WHERE id_prestamo = :id AND id_tenant = :id_tenant
            ");
                $stmtCuotas->execute(['id' => $id, 'id_tenant' => TenantContext::id()]);

                // 3. Finalmente eliminar el préstamo
                $stmtPrestamo = $db->prepare("
                DELETE FROM prestamos 
                WHERE id = :id AND id_tenant = :id_tenant
            ");
                $stmtPrestamo->execute(['id' => $id, 'id_tenant' => TenantContext::id()]);

                // Confirmar transacción
                $db->commit();

                Flight::json([
                    'success' => true,
                    'message' => 'Préstamo eliminado correctamente'
                ], 200);
            } catch (Exception $e) {
                // Revertir transacción en caso de error
                $db->rollBack();
                throw $e;
            }
        } catch (Exception $e) {
            Flight::json([
                'error' => 'Error al eliminar el préstamo',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public static function aprobar()
    {
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];
            $id_usuario_aprobacion = Flight::request()->data['id_usuario_aprobacion'];
            $fecha_aprobacion = isset(Flight::request()->data['fecha_aprobacion']) ?
                Flight::request()->data['fecha_aprobacion'] :
                date('Y-m-d H:i:s');

            $sentence = $db->prepare("
                update prestamos 
                set fecha_aprobacion = :fecha_aprobacion, 
                    id_usuario_aprobacion = :id_usuario_aprobacion
                where id = :id
                and id_tenant = :id_tenant
            ");

            $sentence->bindParam(':id', $id);
            $sentence->bindParam(':fecha_aprobacion', $fecha_aprobacion);
            $sentence->bindParam(':id_usuario_aprobacion', $id_usuario_aprobacion);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            self::getById($id);
        } catch (Exception $e) {
            error_log('Error en aprobar prestamo: ' . $e->getMessage());
            Flight::json([
                'error' => true,
                'message' => 'Error al aprobar el préstamo',
                'detalles' => $e->getMessage()
            ], 500);
        }
    }

    public static function anular()
    {
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];
            $id_usuario_anulacion = Flight::request()->data['id_usuario_anulacion'];
            $motivo_anulacion = isset(Flight::request()->data['motivo_anulacion']) ?
                Flight::request()->data['motivo_anulacion'] :
                "Préstamo anulado";

            $sentence = $db->prepare("
                update prestamos 
                set id_estado = 3,
                    fecha_anulacion = now(), 
                    id_usuario_anulacion = :id_usuario_anulacion,
                    motivo_anulacion = :motivo_anulacion
                where id = :id
                and id_tenant = :id_tenant
            ");

            $sentence->bindParam(':id', $id);
            $sentence->bindParam(':id_usuario_anulacion', $id_usuario_anulacion);
            $sentence->bindParam(':motivo_anulacion', $motivo_anulacion);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            self::getById($id);
        } catch (Exception $e) {
            error_log('Error en anular prestamo: ' . $e->getMessage());
            Flight::json([
                'error' => true,
                'message' => 'Error al anular el préstamo',
                'detalles' => $e->getMessage()
            ], 500);
        }
    }
}
