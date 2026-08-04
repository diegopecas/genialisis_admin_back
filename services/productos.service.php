<?php
class Productos
{
    public static function getAll()
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, 'admin.productos');

        $db = Flight::db();
        $sentence = $db->prepare("SELECT p.id, p.id_tipo_producto, p.nombre, p.descripcion, p.imagen, p.id_unidad_medida, 
                                    p.stock_actual, p.stock_minimo, p.precio_unitario, p.activo, p.fecha_registro,
                                    tp.nombre AS nombre_tipo_producto,
                                    um.nombre nombre_unidad, um.abreviatura abreviatura_unidad,
                                    COUNT(DISTINCT pp.id_proveedor) AS total_proveedores,
                                    GROUP_CONCAT(DISTINCT 
                                        CASE 
                                            WHEN per.razon_social IS NOT NULL AND per.razon_social != '' THEN per.razon_social
                                            ELSE CONCAT(IFNULL(per.primer_nombre, ''), ' ', IFNULL(per.primer_apellido, ''))
                                        END SEPARATOR ', '
                                    ) AS proveedores_nombres
                                    FROM productos p
                                    LEFT JOIN tipos_producto tp ON p.id_tipo_producto = tp.id
                                    LEFT JOIN unidades_medida um ON p.id_unidad_medida = um.id
                                    LEFT JOIN productos_proveedores pp ON p.id = pp.id_producto AND pp.activo = 1
                                    LEFT JOIN proveedores pr ON pp.id_proveedor = pr.id
                                    LEFT JOIN personas per ON pr.id_persona = per.id
                                    WHERE p.id_tenant = :id_tenant
                                    GROUP BY p.id
                                    ORDER BY p.fecha_registro DESC");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getActivos()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT p.id, p.id_tipo_producto, p.nombre, p.descripcion, p.imagen, p.id_unidad_medida, 
                                    p.stock_actual, p.stock_minimo, p.precio_unitario, p.activo,
                                    tp.nombre AS nombre_tipo_producto,
                                    um.nombre nombre_unidad, um.abreviatura abreviatura_unidad,
                                    GROUP_CONCAT(DISTINCT 
                                        CASE 
                                            WHEN per.razon_social IS NOT NULL AND per.razon_social != '' THEN per.razon_social
                                            ELSE CONCAT(IFNULL(per.primer_nombre, ''), ' ', IFNULL(per.primer_apellido, ''))
                                        END SEPARATOR ', '
                                    ) AS proveedores_nombres
                                    FROM productos p
                                    LEFT JOIN tipos_producto tp ON p.id_tipo_producto = tp.id
                                    LEFT JOIN unidades_medida um ON p.id_unidad_medida = um.id
                                    LEFT JOIN productos_proveedores pp ON p.id = pp.id_producto AND pp.activo = 1
                                    LEFT JOIN proveedores pr ON pp.id_proveedor = pr.id
                                    LEFT JOIN personas per ON pr.id_persona = per.id
                                    WHERE p.activo = 1
                                    AND p.id_tenant = :id_tenant
                                    GROUP BY p.id
                                    ORDER BY p.nombre");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
                                    SELECT p.id, 
                                        p.id_tipo_producto,
                                        p.nombre, 
                                        p.descripcion,
                                        p.imagen,
                                        p.id_unidad_medida, 
                                        p.stock_actual,
                                        p.stock_minimo,
                                        p.precio_unitario,
                                        p.activo, 
                                        p.fecha_registro,
                                        tp.nombre AS nombre_tipo_producto,
                                        um.nombre AS nombre_unidad,
                                        um.abreviatura AS abreviatura_unidad,
                                        GROUP_CONCAT(DISTINCT 
                                            CASE 
                                                WHEN per.razon_social IS NOT NULL AND per.razon_social != '' THEN per.razon_social
                                                ELSE CONCAT(IFNULL(per.primer_nombre, ''), ' ', IFNULL(per.primer_apellido, ''))
                                            END SEPARATOR ', '
                                        ) AS proveedores_nombres
                                    FROM productos p
                                    LEFT JOIN tipos_producto tp ON p.id_tipo_producto = tp.id
                                    LEFT JOIN unidades_medida um ON p.id_unidad_medida = um.id
                                    LEFT JOIN productos_proveedores pp ON p.id = pp.id_producto AND pp.activo = 1
                                    LEFT JOIN proveedores pr ON pp.id_proveedor = pr.id
                                    LEFT JOIN personas per ON pr.id_persona = per.id
                                    WHERE p.id = :id
                                    AND p.id_tenant = :id_tenant
                                    GROUP BY p.id
                                    ");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();

            $id_tipo_producto = Flight::request()->data['id_tipo_producto'];
            $nombre = Flight::request()->data['nombre'];
            $descripcion = Flight::request()->data['descripcion'];
            $imagen = Flight::request()->data['imagen'] ?? null;
            $id_unidad_medida = Flight::request()->data['id_unidad_medida'];
            $stock_actual = Flight::request()->data['stock_actual'];
            $stock_minimo = Flight::request()->data['stock_minimo'];
            $precio_unitario = Flight::request()->data['precio_unitario'];

            error_log("Datos recibidos para crear producto: nombre=$nombre, tipo=$id_tipo_producto, imagen=$imagen");

            $sentence = $db->prepare("INSERT INTO productos(
                                        id,
                                        id_tenant,
                                        id_tipo_producto,
                                        nombre,
                                        descripcion,
                                        imagen,
                                        id_unidad_medida,
                                        stock_actual,
                                        stock_minimo,
                                        precio_unitario,
                                        activo,
                                        fecha_registro
                                    ) VALUES (
                                        :id,
                                        :id_tenant,
                                        :id_tipo_producto,
                                        :nombre,
                                        :descripcion,
                                        :imagen,
                                        :id_unidad_medida,
                                        :stock_actual,
                                        :stock_minimo,
                                        :precio_unitario,
                                        1,
                                        NOW()
                                    )");

            $idProd = Uuid::generar();
            $sentence->bindValue(':id', $idProd);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_tipo_producto', $id_tipo_producto);
            $sentence->bindParam(':nombre', $nombre);
            $sentence->bindParam(':descripcion', $descripcion);
            $sentence->bindParam(':imagen', $imagen);
            $sentence->bindParam(':id_unidad_medida', $id_unidad_medida);
            $sentence->bindParam(':stock_actual', $stock_actual);
            $sentence->bindParam(':stock_minimo', $stock_minimo);
            $sentence->bindParam(':precio_unitario', $precio_unitario);
            $sentence->execute();

            $id = $idProd;

            if ($id == 0) {
                error_log("Error: El ID insertado es 0.");
                Flight::json(array('error' => 'No se pudo crear el producto.'), 500);
                return;
            }

            error_log("ID producto insertado: $id");
            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en la ejecución del método new de productos: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function replace()
    {
        $userData = JWTService::requerirAutenticacion();

        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $id_tipo_producto = Flight::request()->data['id_tipo_producto'];
        $nombre = Flight::request()->data['nombre'];
        $descripcion = Flight::request()->data['descripcion'];
        $imagen = Flight::request()->data['imagen'] ?? null;
        $id_unidad_medida = Flight::request()->data['id_unidad_medida'];
        $stock_actual = Flight::request()->data['stock_actual'];
        $stock_minimo = Flight::request()->data['stock_minimo'];
        $precio_unitario = Flight::request()->data['precio_unitario'];
        $activo = Flight::request()->data['activo'];

        $sentence = $db->prepare("UPDATE productos SET 
                            id_tipo_producto = :id_tipo_producto,
                            nombre = :nombre,
                            descripcion = :descripcion,
                            imagen = :imagen,
                            id_unidad_medida = :id_unidad_medida,
                            stock_actual = :stock_actual,
                            stock_minimo = :stock_minimo,
                            precio_unitario = :precio_unitario,
                            activo = :activo
                            WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id_tipo_producto', $id_tipo_producto);
        $sentence->bindParam(':nombre', $nombre);
        $sentence->bindParam(':descripcion', $descripcion);
        $sentence->bindParam(':imagen', $imagen);
        $sentence->bindParam(':id_unidad_medida', $id_unidad_medida);
        $sentence->bindParam(':stock_actual', $stock_actual);
        $sentence->bindParam(':stock_minimo', $stock_minimo);
        $sentence->bindParam(':precio_unitario', $precio_unitario);
        $sentence->bindParam(':activo', $activo);
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        self::getById($id);
    }

    public static function delete()
    {
        $userData = JWTService::requerirAutenticacion();

        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $sentence = $db->prepare("DELETE FROM productos WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        Flight::json(array('id' => $id));
    }

    public static function verificarDuplicados()
    {
        $db = Flight::db();
        $nombre = Flight::request()->data['nombre'];
        $id = Flight::request()->data['id'] ?? 0;

        error_log("Verificando duplicados para producto: nombre=$nombre, id=$id");

        $sentence = $db->prepare("SELECT COUNT(*) as total FROM productos 
                                 WHERE LOWER(nombre) = LOWER(:nombre) 
                                 AND id != :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':nombre', $nombre);
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetch();

        Flight::json(array('existe' => $response['total'] > 0));
    }

    public static function deleteOldImage()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];

        $sentence = $db->prepare("SELECT imagen FROM productos WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $producto = $sentence->fetch();

        if ($producto && $producto['imagen']) {
            $filePath = __DIR__ . '/../' . $producto['imagen'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }

        Flight::json(['success' => true]);
    }

    public static function getByProveedor($id_proveedor)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT p.*, um.nombre as nombre_unidad, um.abreviatura,
            pp.precio_compra, pp.codigo_proveedor
            FROM productos p
            LEFT JOIN unidades_medida um ON p.id_unidad_medida = um.id
            INNER JOIN productos_proveedores pp ON p.id = pp.id_producto
            WHERE pp.id_proveedor = :id_proveedor AND pp.activo = 1 AND p.activo = 1 AND p.id_tenant = :id_tenant
            ORDER BY p.nombre");
        $sentence->bindParam(':id_proveedor', $id_proveedor);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getBajoStock()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT p.*, um.nombre as nombre_unidad,
            GROUP_CONCAT(DISTINCT 
                CASE 
                    WHEN per.razon_social IS NOT NULL AND per.razon_social != '' THEN per.razon_social
                    ELSE CONCAT(IFNULL(per.primer_nombre, ''), ' ', IFNULL(per.primer_apellido, ''))
                END SEPARATOR ', '
            ) AS proveedores_nombres
            FROM productos p
            LEFT JOIN unidades_medida um ON p.id_unidad_medida = um.id
            LEFT JOIN productos_proveedores pp ON p.id = pp.id_producto AND pp.activo = 1
            LEFT JOIN proveedores pr ON pp.id_proveedor = pr.id
            LEFT JOIN personas per ON pr.id_persona = per.id
            WHERE p.stock_actual <= p.stock_minimo AND p.activo = 1 AND p.id_tenant = :id_tenant
            GROUP BY p.id
            ORDER BY (p.stock_actual / NULLIF(p.stock_minimo, 0))");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getProveedoresProducto($id_producto)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT 
        pp.id_producto,
        pp.id_proveedor, 
        pp.precio_compra,
        pp.codigo_proveedor,
        pp.activo,
        pr.id_tipo_proveedor,
        CASE 
            WHEN per.razon_social IS NOT NULL AND per.razon_social != '' THEN per.razon_social
            ELSE CONCAT(IFNULL(per.primer_nombre, ''), ' ', IFNULL(per.primer_apellido, ''))
        END AS nombre_proveedor,
        per.numero_identificacion,
        tp.nombre AS tipo_proveedor
        FROM productos_proveedores pp
        INNER JOIN proveedores pr ON pp.id_proveedor = pr.id
        INNER JOIN personas per ON pr.id_persona = per.id
        LEFT JOIN tipos_proveedor tp ON pr.id_tipo_proveedor = tp.id
        WHERE pp.id_producto = :id_producto AND pp.activo = 1 AND pp.id_tenant = :id_tenant
        ORDER BY nombre_proveedor");
        $sentence->bindParam(':id_producto', $id_producto);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function asignarProveedor()
    {
        $db = Flight::db();
        $id_producto = Flight::request()->data['id_producto'];
        $id_proveedor = Flight::request()->data['id_proveedor'];
        $precio_compra = Flight::request()->data['precio_compra'] ?? null;
        $codigo_proveedor = Flight::request()->data['codigo_proveedor'] ?? null;

        try {
            $sentence = $db->prepare("INSERT INTO productos_proveedores(
                id, id_tenant, id_producto, id_proveedor, precio_compra, codigo_proveedor, activo
            ) VALUES (
                :id, :id_tenant, :id_producto, :id_proveedor, :precio_compra, :codigo_proveedor, 1
            ) ON DUPLICATE KEY UPDATE 
                precio_compra = VALUES(precio_compra),
                codigo_proveedor = VALUES(codigo_proveedor),
                activo = 1");

            $idPP = Uuid::generar();
            $sentence->bindValue(':id', $idPP);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_producto', $id_producto);
            $sentence->bindParam(':id_proveedor', $id_proveedor);
            $sentence->bindParam(':precio_compra', $precio_compra);
            $sentence->bindParam(':codigo_proveedor', $codigo_proveedor);
            $sentence->execute();

            Flight::json(array('success' => true));
        } catch (Exception $e) {
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function quitarProveedor()
    {
        $db = Flight::db();
        $id_producto = Flight::request()->data['id_producto'];
        $id_proveedor = Flight::request()->data['id_proveedor'];

        if (!$id_producto || !$id_proveedor) {
            Flight::json(array('error' => 'id_producto e id_proveedor son requeridos'), 400);
            return;
        }

        try {
            $sentence = $db->prepare("UPDATE productos_proveedores 
            SET activo = 0 
            WHERE id_producto = :id_producto AND id_proveedor = :id_proveedor AND id_tenant = :id_tenant");
            $sentence->bindParam(':id_producto', $id_producto);
            $sentence->bindParam(':id_proveedor', $id_proveedor);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(array('success' => true));
        } catch (Exception $e) {
            error_log("Error en quitarProveedor: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function getByTipo($id_tipo)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT p.*, um.nombre as nombre_unidad, um.abreviatura as abreviatura_unidad
        FROM productos p
        LEFT JOIN unidades_medida um ON p.id_unidad_medida = um.id
        WHERE p.id_tipo_producto = :id_tipo AND p.activo = 1 AND p.id_tenant = :id_tenant
        ORDER BY p.nombre");
        $sentence->bindParam(':id_tipo', $id_tipo);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getImagenBase64($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT imagen FROM productos WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $producto = $sentence->fetch();

        if ($producto && $producto['imagen']) {
            $imagePath = UploadHelper::getFullPath($producto['imagen']);

            if (file_exists($imagePath)) {
                $imageData = file_get_contents($imagePath);

                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $imagePath);
                finfo_close($finfo);

                $imageBase64 = 'data:' . $mimeType . ';base64,' . base64_encode($imageData);

                Flight::json(['imagen_base64' => $imageBase64]);
                return;
            } else {
                error_log("Archivo no encontrado: " . $imagePath);
            }
        }

        Flight::json(['imagen_base64' => null, 'error' => 'Imagen no encontrada']);
    }
}