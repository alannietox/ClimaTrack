# ClimaTrack

Plataforma en PHP para **capturar, normalizar y exportar información meteorológica** destinada a flujos editoriales de prensa. Integra varias fuentes externas, persiste los datos en MySQL/MariaDB y genera XML adaptados a diferentes cabeceras.

> Proyecto personal/educativo orientado a automatización de datos meteorológicos y generación de contenido estructurado.

## Funcionalidades

- **AEMET OpenData**: predicciones meteorológicas para municipios españoles.
- **wttr.in**: previsiones y fallback para determinadas capturas.
- **Puertos del Estado / Portus**: mareas, oleaje, viento y temperatura del agua.
- **Open-Meteo**: previsión y datos históricos utilizados en resúmenes.
- **112 Asturias**: extracción del índice de riesgo de incendios.
- **Embalses**: captura de información de embalses configurados.
- **Astronomía**: fases lunares y horas de orto/ocaso.
- **Multi-periódico**: mapas de localidades, puertos e iconografía específicos.
- **Caché**: evita llamadas repetidas durante intervalos configurados.
- **Exportación XML**: transforma datos a formatos consumibles por sistemas editoriales.
- **Panel web**: selección de periódicos y lanzamiento de procesos.

## Arquitectura

```text
ClimaTrack/
├── index.php
├── capturar_*.php
├── exportar_*.php
├── get_*.php
├── periodicos_mapping.php
├── refranes_helper.php
├── conexion.example.php
├── schema.sql
├── styles.css
├── iconos/
├── .env.example
├── .github/workflows/ci.yml
├── SECURITY.md
└── CONTRIBUTING.md
```

Los scripts de `capturar_*.php` adquieren y almacenan datos. Los `exportar_*.php` transforman esos datos y generan XML. La configuración editorial se concentra actualmente en `periodicos_mapping.php`.

La siguiente evolución técnica es separar clientes HTTP, caché, persistencia y transformadores para reducir duplicación entre scripts.

## Requisitos

- PHP **8.2+** recomendado.
- Extensiones: `curl`, `dom`, `mbstring`, `pdo_mysql`, `simplexml`.
- MySQL o MariaDB.
- Apache, Nginx o ejecución mediante CLI.
- Acceso a Internet para las fuentes externas.

## Instalación

### 1. Clonar

```bash
git clone https://github.com/alannietox/ClimaTrack.git
cd ClimaTrack
```

### 2. Configurar la base de datos

```bash
cp conexion.example.php conexion.php
```

Variables disponibles:

```text
DB_HOST=localhost
DB_NAME=climatrack
DB_USER=usuario
DB_PASSWORD=contraseña
DB_CHARSET=utf8mb4
```

Importar el esquema:

```bash
mysql -u usuario -p climatrack < schema.sql
```

### 3. Configurar AEMET

Configura `AEMET_API_KEY` con una clave de AEMET OpenData. Consulta `.env.example` para el resto de variables.

**No introduzcas credenciales reales en Git.**

## Uso

### Capturas

```bash
php capturar_aemet.php
php capturar_mundo.php
php capturar_resumen.php
php capturar_resumen_navarra.php
php capturar_resumen_vasco.php
php capturar_embalses.php
php capturar_incendios.php
```

### Exportación

Ejemplos:

```text
/exportar_clima.php?periodico=diario_montanes_cantabria
/exportar_mareas.php?periodico=el_comercio_asturias
/exportar_incendios_periodico.php?periodico=el_comercio_asturias
/exportar_embalses_navarra.php
```

## Fuentes de datos

| Fuente | Función | Integración |
|---|---|---|
| AEMET OpenData | Predicción española | API REST |
| Open-Meteo | Forecast / histórico | API REST |
| wttr.in | Forecast / fallback | API REST |
| Puertos del Estado | Mareas, oleaje y datos marítimos | API REST |
| 112 Asturias | Riesgo de incendios | Scraping HTML |
| Embalses configurados | Estado de embalses | Scraping HTML |

Las fuentes externas pueden cambiar sus respuestas. Los capturadores deben considerar errores de red y cambios de formato como condiciones esperables.

## Base de datos

Principales tablas:

- `localidades`: municipios y coordenadas.
- `datos_clima`: previsiones y variables meteorológicas.
- `ciudades_mundo`: ciudades internacionales.
- `datos_mundo`: previsiones internacionales.
- `embalses`: estado de embalses.
- `resumen_vasco`: datos del resumen costero vasco.

El esquema completo está en `schema.sql`.

## Calidad y CI

Cada push y pull request contra `main` ejecuta GitHub Actions para:

1. Instalar PHP 8.2 y las extensiones necesarias.
2. Ejecutar `php -l` sobre todos los archivos PHP.
3. Verificar archivos y directorios esenciales.

Comprobación local:

```bash
find . -type f -name "*.php" -not -path "./vendor/*" -exec php -l {} \;
```

## Seguridad

Las credenciales y la configuración local no forman parte del repositorio.

Consulta `SECURITY.md` para las recomendaciones de despliegue y gestión de secretos.

## Limitaciones conocidas

- Algunas fuentes utilizan scraping y pueden cambiar su HTML.
- Existen reglas específicas por periódico concentradas en scripts grandes.
- Parte de la configuración editorial está representada mediante arrays PHP.
- Todavía no hay una suite automatizada de tests de integración contra APIs externas.

## Roadmap

- [x] Separar configuración sensible del código.
- [x] Añadir validación automática de sintaxis PHP.
- [x] Documentar configuración y seguridad.
- [ ] Crear un cliente HTTP común con timeouts y validación de respuestas.
- [ ] Centralizar el sistema de caché.
- [ ] Separar captura, transformación y persistencia.
- [ ] Añadir tests unitarios para mapeos meteorológicos.
- [ ] Añadir tests de generación XML.
- [ ] Reducir lógica duplicada entre exportadores.
- [ ] Añadir logging estructurado.

## Licencia

Proyecto de uso personal/educativo. Las fuentes externas, datos y recursos gráficos mantienen sus respectivas condiciones de uso.
