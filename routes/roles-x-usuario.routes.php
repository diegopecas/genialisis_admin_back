<?php
Flight::route('GET /roles-x-usuario/rol/@idRol', [RolesXUsuario::class, 'getUsuariosByRol']);
Flight::route('GET /roles-x-usuario/usuario/@idUsuario', [RolesXUsuario::class, 'getRolesByUsuario']);
Flight::route('POST /roles-x-usuario/sincronizar-rol', [RolesXUsuario::class, 'sincronizarRol']);
Flight::route('POST /roles-x-usuario/sincronizar-usuario', [RolesXUsuario::class, 'sincronizarUsuario']);
