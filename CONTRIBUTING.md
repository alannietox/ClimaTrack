# Contribuir a ClimaTrack

## Flujo

1. Crea una rama para el cambio.
2. Mantén los scripts con una responsabilidad clara.
3. No introduzcas credenciales ni datos de producción.
4. Ejecuta el chequeo de sintaxis de PHP antes de hacer commit.
5. Documenta cambios que afecten a configuración, APIs o formato XML.

## Comprobación local

Con PHP instalado:

```bash
find . -type f -name "*.php" -not -path "./vendor/*" -exec php -l {} \;
```

## Convenciones

- PHP 8.2+ para desarrollo.
- UTF-8.
- `PDO` y consultas preparadas para acceso a MySQL/MariaDB.
- Variables de entorno para secretos.
- Evitar lógica duplicada cuando pueda convertirse en un helper reutilizable.
