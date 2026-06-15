<?php
require 'conexion.php';

// Estadísticas para el dashboard
$count_loc = $pdo->query("SELECT count(*) FROM localidades")->fetchColumn();
$count_clima = $pdo->query("SELECT count(*) FROM datos_clima")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clima Dashboard - Zero Scroll</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .pill-btn {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid rgba(255, 255, 255, 0.05);
            color: var(--text-muted);
            padding: 0.8rem 0.5rem;
            border-radius: 0.6rem;
            font-size: 0.75rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .pill-btn:hover {
            border-color: rgba(255, 255, 255, 0.2);
            color: white;
        }
        .toggle-pill input:checked + .pill-btn {
            background: rgba(59, 130, 246, 0.15);
            border-color: var(--accent);
            color: white;
            box-shadow: 0 0 15px rgba(59, 130, 246, 0.1);
        }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <!-- SIDEBAR -->
        <aside class="sidebar">
            <header>
                <h1>Centro Meteorológico</h1>
                <p>Automatización de Clima para Prensa</p>
            </header>

            <div class="stats-container">
                <div class="stat-row" id="last-capture-row" style="display: none;">
                    <span class="stat-label">Última captura</span>
                    <span class="stat-update-value">Ninguna</span>
                </div>
                <div class="stat-row" style="margin-top: 1rem;">
                    <button id="clear-cache-btn" onclick="clearCache()" class="btn-clear-cache">
                        LIMPIAR CACHÉ
                    </button>
                </div>
            </div>

            <!-- Panel de Selección (Dinámico) -->
            <div id="selection-panel" style="flex-grow: 1; display: flex; flex-direction: column; justify-content: flex-end;">
                <div id="no-selection-msg" style="text-align: center; padding: 2rem; border: 1px dashed var(--glass-border); border-radius: 1rem; color: var(--text-muted); font-size: 0.85rem;">
                    Selecciona un periódico para comenzar
                </div>
                
                <div id="selection-card" style="display: none;">
                    <div class="selected-title" id="selected-name">Periódico</div>
                    
                    <div class="date-toggles" style="display: flex; gap: 0.5rem; margin-bottom: 1rem;">
                        <label class="toggle-pill" style="flex: 1; text-align: center;">
                            <input type="checkbox" id="toggle-plus1" style="display: none;">
                            <div class="pill-btn">+1 Día</div>
                        </label>
                    </div>
                    
                    <button id="download-filtered-btn" onclick="captureAndDownload()" class="btn-main">
                        DESCARGAR
                    </button>
                    
                    <div id="download-list" style="margin-top: 1rem; font-size: 0.75rem; color: var(--accent); text-align: left; background: rgba(255,255,255,0.03); padding: 0.75rem; border-radius: 0.5rem; border: 1px solid var(--glass-border);">
                        <!-- Dinámico -->
                    </div>
                </div>
            </div>

            <footer class="compact-footer">
                <a href="javascript:void(0)" onclick="downloadFile('exportar_clima.php', 'base_datos_completa')" style="color: var(--text-muted); text-decoration: none; opacity: 0.6; transition: opacity 0.2s;" onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=0.6">Base de datos completa</a>
            </footer>
        </aside>

        <!-- MAIN CONTENT -->
        <main class="content">
            <div class="grid-header">
                <h2>Periódicos Disponibles</h2>
                <div style="font-size: 0.75rem; color: var(--text-muted);">Format: XML | UTF-8</div>
            </div>

            <div class="newspaper-grid">
                <?php 
                require 'periodicos_mapping.php';
                
                $displayNames = [
                    'diario_el_sur_malaga' => 'Diario SUR<br><span style="font-size:0.7em; opacity:0.7;">(Málaga)</span>',
                    'diario_montanes_cantabria' => 'El Diario Montañés<br><span style="font-size:0.7em; opacity:0.7;">(Cantabria)</span>',
                    'el_comercio_asturias' => 'El Comercio<br><span style="font-size:0.7em; opacity:0.7;">(Asturias)</span>',
                    'el_correo_alava' => 'El Correo Alava<br><span style="font-size:0.7em; opacity:0.7;">(Álava)</span>',
                    'el_correo_guipuzcoa' => 'El Correo Guipuzcoa<br><span style="font-size:0.7em; opacity:0.7;">(Guipúzcoa)</span>',
                    'el_correo_larioja' => 'El Correo La Rioja<br><span style="font-size:0.7em; opacity:0.7;">(La Rioja)</span>',
                    'el_correo_vizcaya' => 'El Correo Vizcaya<br><span style="font-size:0.7em; opacity:0.7;">(Vizcaya)</span>',
                    'la_rioja_la_rioja' => 'Diario La Rioja<br><span style="font-size:0.7em; opacity:0.7;">(La Rioja)</span>',
                    'la_voz_cadiz' => 'La Voz de Cádiz<br><span style="font-size:0.7em; opacity:0.7;">(Cádiz)</span>',
                    'norte_de_castilla' => 'El Norte de Castilla<br><span style="font-size:0.7em; opacity:0.7;">(Castilla y León)</span>',
                    'las_provincias' => 'Las Provincias<br><span style="font-size:0.7em; opacity:0.7;">(Valencia)</span>',
                    'el_ideal' => 'Ideal<br><span style="font-size:0.7em; opacity:0.7;">(Andalucía)</span>',
                    'laverdad_murcia' => 'La Verdad<br><span style="font-size:0.7em; opacity:0.7;">(Murcia)</span>',
                    'hoy' => 'Hoy<br><span style="font-size:0.7em; opacity:0.7;">(Extremadura)</span>',
                    'diario_vasco_guipuzcoa' => 'El Diario Vasco<br><span style="font-size:0.7em; opacity:0.7;">(Guipúzcoa)</span>',
                    'diario_navarra' => 'Diario de Navarra<br><span style="font-size:0.7em; opacity:0.7;">(Navarra)</span>'
                ];

                foreach ($periodicos as $name => $ids): 
                    if (empty($ids)) continue; // Borrar o saltar los no configurados
                    $displayName = isset($displayNames[$name]) ? $displayNames[$name] : ucwords(str_replace('_', ' ', $name));
                    $baseName = explode('<br>', $displayName)[0];
                    $hasIds = !empty($ids);
                    $count = count($ids);
                ?>
                        <button onclick="selectPeriodico('<?= $name ?>', '<?= addslashes($baseName) ?>', this)" 
                                class="btn-periodico" 
                                <?= $hasIds ? '' : 'disabled' ?>
                                title="<?= $hasIds ? "Ver $count municipios" : 'No configurado' ?>">
                            <span class="btn-name"><?= $displayName ?></span>
                        </button>
                <?php endforeach; ?>
            </div>
        </main>
    </div>

    <!-- GSAP for advanced animations -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>

    <script>
        async function clearCache() {
            const btn = document.getElementById('clear-cache-btn');
            const originalText = btn.innerText;
            
            gsap.to(btn, { scale: 0.95, duration: 0.1, yoyo: true, repeat: 1 });
            
            btn.innerText = "⏳ LIMPIANDO...";
            btn.disabled = true;
            try {
                const res = await fetch('limpiar_cache.php');
                const text = await res.text();
                if (text.trim() === 'OK') {
                    gsap.to(btn, { backgroundColor: 'rgba(16, 185, 129, 0.2)', color: '#10b981', borderColor: 'rgba(16, 185, 129, 0.4)', duration: 0.3 });
                    btn.innerText = "¡LISTO!";
                    setTimeout(() => {
                        gsap.to('body', { opacity: 0, duration: 0.5, onComplete: () => location.reload() });
                    }, 800);
                } else {
                    btn.innerText = "ERROR";
                    setTimeout(() => {
                        btn.innerText = originalText;
                        btn.disabled = false;
                    }, 2000);
                }
            } catch (e) {
                btn.innerText = "ERROR";
                setTimeout(() => {
                    btn.innerText = originalText;
                    btn.disabled = false;
                }, 2000);
            }
        }

        // Animate entrance on load
        window.addEventListener('DOMContentLoaded', () => {
            gsap.from('.sidebar', { x: -50, opacity: 0, duration: 1, ease: 'power3.out' });
            gsap.from('.grid-header', { y: -20, opacity: 0, duration: 0.8, delay: 0.3, ease: 'power2.out' });
            gsap.from('.btn-periodico', { 
                scale: 0.8, 
                opacity: 0, 
                duration: 0.6, 
                stagger: 0.03, 
                ease: 'back.out(1.7)',
                delay: 0.5 
            });
        });

        const periodicosMapping = <?= json_encode($periodicos) ?>;
        const extraConfig = <?= json_encode($extra_config) ?>;
        let selectedPeriodicoName = '';

        function selectPeriodico(name, baseDisplayName, element) {
            const ids = periodicosMapping[name] || [];
            if (ids.length === 0) return;

            selectedPeriodicoName = name;
            
            // Pulse effect on click
            gsap.to(element, { scale: 0.92, duration: 0.4, yoyo: true, repeat: 1, ease: 'power2.inOut' });

            // UI Updates
            document.querySelectorAll('.btn-periodico').forEach(b => b.classList.remove('active'));
            element.classList.add('active');

            // Actualizar última captura
            const captureRow = document.getElementById('last-capture-row');
            if (captureRow.style.display === 'none') {
                captureRow.style.display = 'flex';
                gsap.fromTo(captureRow, { opacity: 0, y: -10 }, { opacity: 1, y: 0, duration: 0.5, ease: 'power2.out' });
            }

            fetch(`get_ultima_captura.php?periodico=${name}`)
                .then(res => res.text())
                .then(text => {
                    const valueEl = document.querySelector('.stat-update-value');
                    gsap.to(valueEl, { opacity: 0, y: 10, duration: 0.4, ease: 'power2.in', onComplete: () => {
                        valueEl.innerText = text;
                        gsap.to(valueEl, { opacity: 1, y: 0, duration: 0.6, ease: 'power2.out' });
                    }});
                });


            const noSelection = document.getElementById('no-selection-msg');
            const card = document.getElementById('selection-card');
            
            if (noSelection.style.display !== 'none') {
                gsap.to(noSelection, { opacity: 0, scale: 0.9, duration: 0.5, ease: 'power2.in', onComplete: () => {
                    noSelection.style.display = 'none';
                    card.style.display = 'block';
                    gsap.fromTo(card, { opacity: 0, y: 50 }, { opacity: 1, y: 0, duration: 0.8, ease: 'power3.out' });
                }});
            } else {
                gsap.fromTo(card, { y: 15 }, { y: 0, duration: 0.6, ease: 'elastic.out(1, 0.5)' });
            }
            
            document.getElementById('selected-name').innerText = baseDisplayName;
            
            const hasExtended = !!(extraConfig[name] && extraConfig[name].has_extended);

            // Actualizar lista de descargas con animación
            const listDiv = document.getElementById('download-list');
            const coastalPeriodicos = ['el_comercio_asturias', 'diario_montanes_cantabria', 'el_correo_vizcaya', 'diario_vasco_guipuzcoa', 'diario_el_sur_malaga', 'la_voz_cadiz', 'las_provincias'];
            const specialPeriodicos = ['diario_el_sur_malaga', 'norte_de_castilla', 'hoy', 'laverdad_murcia', 'la_rioja_la_rioja', 'las_provincias', 'el_ideal'];
            
            let html = '<div class="list-header">Archivos XML a generar:</div>';
            html += `<div class="download-item"><span>Predicción Meteorológica</span></div>`;
            if (coastalPeriodicos.includes(name)) html += `<div class="download-item"><span>Tabla de Mareas</span></div>`;
            if (name === 'el_comercio_asturias') html += `<div class="download-item"><span>Riesgo de Incendio</span></div>`;
            if (specialPeriodicos.includes(name)) html += `<div class="download-item"><span>Ficha Resumen (Ayer)</span></div>`;
            if (name === 'diario_navarra') {
                html += `<div class="download-item"><span>Estado de Embalses</span></div>`;
                html += `<div class="download-item"><span>Ficha Resumen (Ayer/Anteayer)</span></div>`;
            }
            if (name === 'diario_vasco_guipuzcoa') html += `<div class="download-item"><span>Resumen Costa (Hoy/Mañana)</span></div>`;
            
            listDiv.innerHTML = html;
            gsap.from('.download-item', { 
                opacity: 0, 
                x: -10, 
                stagger: 0.05, 
                duration: 0.3, 
                ease: 'power1.out',
                clearProps: 'all'
            });
        }

        function downloadFile(url, baseName = 'archivo') {
            const now = new Date();
            // Formato local: AAAA-MM-DD_HH-mm
            const dateStr = now.getFullYear() + '-' + 
                           String(now.getMonth() + 1).padStart(2, '0') + '-' + 
                           String(now.getDate()).padStart(2, '0');
            const timeStr = String(now.getHours()).padStart(2, '0') + '-' + 
                           String(now.getMinutes()).padStart(2, '0');
            
            const fileName = `${baseName}_${dateStr}_${timeStr}.xml`;
            
            const link = document.createElement('a');
            link.href = url;
            link.setAttribute('download', fileName);
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }



        async function captureAndDownload() {
            const btn = document.getElementById('download-filtered-btn');
            const originalText = btn.innerText;
            const name = selectedPeriodicoName;
            const ids = periodicosMapping[name] || [];

            if (ids.length === 0) return;

            gsap.to(btn, { scale: 0.98, duration: 0.1 });
            btn.innerText = "⏳ CAPTURANDO...";
            btn.disabled = true;

            const updateStatus = (text) => {
                btn.innerText = text;
                gsap.fromTo(btn, { filter: 'brightness(1.5)' }, { filter: 'brightness(1)', duration: 0.5 });
            };

            const specialPeriodicos = ['diario_el_sur_malaga', 'norte_de_castilla', 'hoy', 'laverdad_murcia', 'la_rioja_la_rioja', 'las_provincias', 'el_ideal'];
            const coastalPeriodicos = ['el_comercio_asturias', 'diario_montanes_cantabria', 'el_correo_vizcaya', 'diario_vasco_guipuzcoa', 'diario_el_sur_malaga', 'la_voz_cadiz', 'las_provincias'];

            try {
                // 1. AEMET España
                try {
                    updateStatus("CAPTURANDO ESPAÑA...");
                    await fetch(`capturar_aemet.php?ajax=1&periodico=${name}&ids=${ids.join(',')}`);
                } catch (e) { console.error("Error AEMET:", e); }

                // 2. Mundo + Mareas + Resumen en PARALELO
                updateStatus("CAPTURANDO DATOS...");
                const parallelFetches = [
                    fetch(`capturar_mundo.php?ajax=1&periodico=${name}`).catch(e => console.error("Error Mundo:", e))
                ];
                if (coastalPeriodicos.includes(name)) {
                    parallelFetches.push(fetch(`capturar_mareas.php?ajax=1&periodico=${name}`).catch(e => console.error("Error Mareas:", e)));
                }
                if (specialPeriodicos.includes(name)) {
                    parallelFetches.push(fetch('capturar_resumen.php').catch(e => console.error("Error Resumen:", e)));
                }
                if (name === 'diario_navarra') {
                    parallelFetches.push(fetch('capturar_embalses.php').catch(e => console.error("Error Embalses:", e)));
                    parallelFetches.push(fetch('capturar_resumen_navarra.php').catch(e => console.error("Error Resumen Navarra:", e)));
                }
                if (name === 'diario_vasco_guipuzcoa') {
                    parallelFetches.push(fetch('capturar_resumen_vasco.php').catch(e => console.error("Error Resumen Vasco:", e)));
                }
                await Promise.all(parallelFetches);

                updateStatus("DESCARGANDO...");

                // Lógica de OFFSETS acumulativos
                let offsets = [1]; 
                if (document.getElementById('toggle-plus1').checked) {
                    offsets = [1, 2]; 
                }

                // 3. Descargas por cada offset
                offsets.forEach((offset, index) => {
                    const delay = index * 1500; 
                    
                    setTimeout(() => {
                        downloadFile(`exportar_clima.php?periodico=${name}&offset=${offset}`, `predicciones_${name}_dia${offset}`);
                        
                        const targetDate = new Date();
                        targetDate.setDate(targetDate.getDate() + offset);
                        const year = targetDate.getFullYear();
                        const month = String(targetDate.getMonth() + 1).padStart(2, '0');
                        const day = String(targetDate.getDate()).padStart(2, '0');
                        const fechaStr = `${year}-${month}-${day}`;

                        if (coastalPeriodicos.includes(name)) {
                            downloadFile(`exportar_mareas.php?periodico=${name}&fecha=${fechaStr}`, `mareas_${name}_dia${offset}`);
                        }
                        
                        if (specialPeriodicos.includes(name)) {
                            const summaryDate = new Date();
                            summaryDate.setDate(summaryDate.getDate() + (offset - 1));
                            const sYear = summaryDate.getFullYear();
                            const sMonth = String(summaryDate.getMonth() + 1).padStart(2, '0');
                            const sDay = String(summaryDate.getDate()).padStart(2, '0');
                            const summaryFechaStr = `${sYear}-${sMonth}-${sDay}`;
                            
                            downloadFile(`exportar_resumen_especial.php?periodico=${name}&fecha=${summaryFechaStr}`, `resumen_datos_${name}_dia${offset}`);
                        }

                        if (name === 'el_comercio_asturias') {
                            downloadFile(`exportar_incendios_periodico.php?periodico=${name}`, `incendios_asturias_dia${offset}`);
                        }

                        if (name === 'diario_navarra') {
                            downloadFile(`exportar_resumen_navarra.php?offset=${offset}`, `resumen_navarra_dia${offset}`);
                            downloadFile(`exportar_embalses_navarra.php?offset=${offset}`, `embalses_navarra_dia${offset}`);
                        }

                        if (name === 'diario_vasco_guipuzcoa') {
                            downloadFile(`exportar_resumen_vasco.php?offset=${offset}`, `resumen_costa_dv_dia${offset}`);
                        }
                    }, delay);
                });

                setTimeout(() => {
                    gsap.to(btn, { scale: 1, duration: 0.3, ease: 'back.out' });
                    btn.innerText = originalText;
                    btn.disabled = false;
                    
                    fetch(`get_ultima_captura.php?periodico=${name}`)
                        .then(res => res.text())
                        .then(text => {
                            const val = document.querySelector('.stat-update-value');
                            gsap.fromTo(val, { scale: 1.2, color: '#10b981' }, { scale: 1, color: 'white', duration: 1 });
                            val.innerText = text;
                        });
                }, 8000);

            } catch (error) {
                console.error('Error general:', error);
                alert("Error inesperado en el proceso.");
                btn.innerText = originalText;
                btn.disabled = false;
            }
        }
    </script>
</body>
</html>
