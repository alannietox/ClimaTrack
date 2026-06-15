# ClimaTrack 🌦️

Sistema de captura y exportación de datos meteorológicos para periódicos españoles. Obtiene predicciones de la API de AEMET, datos marítimos de Puertos del Estado, y genera XMLs personalizados para cada publicación.

## Características

- 🌡️ **Predicción meteorológica** — Captura datos de AEMET OpenData con fallback a wttr.in
- 🌊 **Mareas y oleaje** — Datos en tiempo real de Puertos del Estado (POEM/Portus)
- 🌙 **Datos astronómicos** — Cálculo de fases lunares, orto/ocaso solar y lunar
- 🔥 **Índice de incendios** — Scraping del 112 Asturias
- 💧 **Embalses** — Nivel de embalses de Navarra
- 🗺️ **Multi-periódico** — XMLs personalizados con iconos específicos para cada cabecera
- ⏱️ **Cache inteligente** — Sistema de caché para evitar llamadas excesivas a las APIs

## Requisitos

- PHP 7.4+ con extensiones: `curl`, `pdo_mysql`, `simplexml`, `mbstring`
- MySQL / MariaDB
- Servidor web (Apache/Nginx) o CLI

## Instalación

1. **Clonar el repositorio**
   ```bash
   git clone https://github.com/tu-usuario/ClimaTrack.git
   cd ClimaTrack
   ```

2. **Configurar base de datos**
   ```bash
   cp conexion.example.php conexion.php
   ```
   Edita `conexion.php` con tus credenciales de MySQL.

3. **Crear las tablas**
   Importa el esquema de base de datos:
   ```bash
   mysql -u tu_usuario -p tu_base_de_datos < schema.sql
   ```

4. **Configurar API Key de AEMET**
   
   Obtén tu clave gratuita en [AEMET OpenData](https://opendata.aemet.es/centrodedescargas/altaUsuario).
   
   Opción A — Variable de entorno:
   ```bash
   export AEMET_API_KEY="tu_api_key"
   ```
   
   Opción B — Editar directamente en `capturar_aemet.php` y `capturar_resumen_navarra.php`.

## Uso

### Captura de datos (CLI)
```bash
# Capturar datos meteorológicos de todas las localidades
php capturar_aemet.php

# Capturar datos de un periódico específico
php capturar_aemet.php periodico=diario_montanes_cantabria

# Capturar resumen del País Vasco
php capturar_resumen_vasco.php

# Capturar datos de Navarra
php capturar_resumen_navarra.php
```

### Exportación XML (Web)
```
GET /exportar_clima.php?periodico=diario_montanes_cantabria
GET /exportar_mareas.php?periodico=el_comercio_asturias
GET /exportar_incendios_periodico.php
GET /exportar_embalses_navarra.php
```

### Panel de control
Accede a `index.php` para gestionar localidades y lanzar capturas desde la interfaz web.

## Estructura del proyecto

```
├── index.php                       # Panel de control web
├── conexion.example.php            # Plantilla de conexión a BD
├── capturar_aemet.php              # Captura datos de AEMET
├── capturar_mundo.php              # Captura datos internacionales (wttr.in)
├── capturar_resumen_navarra.php    # Captura resumen Navarra
├── capturar_resumen_vasco.php      # Captura resumen País Vasco
├── capturar_embalses.php           # Captura niveles de embalses
├── capturar_incendios.php          # Captura índice de incendios
├── exportar_clima.php              # Genera XML del clima
├── exportar_mareas.php             # Genera XML de mareas y viento
├── exportar_incendios_periodico.php # Genera XML de incendios
├── exportar_embalses_navarra.php   # Genera XML de embalses
├── exportar_resumen_especial.php   # Genera XML resumen especial
├── exportar_resumen_navarra.php    # Genera XML resumen Navarra
├── exportar_resumen_vasco.php      # Genera XML resumen vasco
├── periodicos_mapping.php          # Mapeo periódicos → localidades
├── refranes_helper.php             # Refranes del tiempo por fecha
├── get_municipios_periodico.php    # API de municipios por periódico
├── get_ultima_captura.php          # Comprueba última captura
├── limpiar_cache.php               # Limpia caché de capturas
├── styles.css                      # Estilos del panel de control
├── schema.sql                      # Esquema de base de datos
└── iconos/                         # Sets de iconos meteorológicos
```

## APIs utilizadas

| API | Uso | Auth |
|-----|-----|------|
| [AEMET OpenData](https://opendata.aemet.es/) | Predicción meteorológica España | API Key (gratis) |
| [Open-Meteo](https://open-meteo.com/) | Datos históricos y forecast | Sin auth |
| [wttr.in](https://wttr.in/) | Fallback meteorológico mundial | Sin auth |
| [Puertos del Estado](https://www.puertos.es/) | Mareas, oleaje, viento costero | Sin auth |
| [112 Asturias](https://www.112asturias.es/) | Índice de incendios | Scraping |

## Licencia

Este proyecto es de uso personal/educativo.
