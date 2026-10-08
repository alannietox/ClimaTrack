# Seguridad

## Configuración

No guardes credenciales, API keys ni contraseñas dentro del repositorio.

Usa variables de entorno:

- `DB_HOST`
- `DB_NAME`
- `DB_USER`
- `DB_PASSWORD`
- `AEMET_API_KEY`
- `ICONS_BASE_URL`

El archivo `conexion.php` es local y está excluido mediante `.gitignore`.

## Producción

- Sirve el proyecto detrás de HTTPS.
- No expongas archivos de configuración ni logs de producción desde un servidor público.
- Limita el acceso al panel `index.php` si el despliegue es de uso interno.
- Mantén PHP y las extensiones del servidor actualizadas.
- Revisa periódicamente las fuentes externas utilizadas por los scrapers.
- No desactives la verificación TLS en nuevas integraciones.

## Incidencias

Si detectas una vulnerabilidad que pueda comprometer credenciales, datos o el servidor, no publiques secretos en una issue. Contacta primero con el mantenedor.
