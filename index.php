<?php
/*************************************************
 *  Teramorphosis: Contacto minimal seguro
 *  - CSV privado fuera de public_html (con fallback protegido)
 *  - SIN enlaces ni rutas públicas de descarga
 *  - CSRF + sanitización + defensa CSV injection
 *************************************************/

session_start();
if (!isset($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }

// Cabeceras defensivas
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer-when-downgrade');
header("Permissions-Policy: interest-cohort=()");
header("Content-Security-Policy: default-src 'self' https://cdn.jsdelivr.net https://fonts.googleapis.com https://fonts.gstatic.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; font-src https://fonts.gstatic.com; img-src 'self' data:;");


define('PRIVATE_DIR', dirname(__DIR__) . '/private'); // ../private (hermana de public_html)
if (!is_dir(PRIVATE_DIR)) { @mkdir(PRIVATE_DIR, 0755, true); }//si el herchivo lo creas tu cambia 0755 por 0644
define('CONTACT_CSV', PRIVATE_DIR . '/contacts.csv');

// ===== Resolver directorio privado =====
function ensure_private_dir(): string {
  // 1) Intentar FUERA de public_html
  $outside = dirname(__DIR__) . '/private'; //Aqui modificas el nombre de la carpeta
  if (@is_dir($outside) || @mkdir($outside, 0755, true)) return $outside;

  // 2) Fallback DENTRO de public_html con .htaccess de bloqueo
  $inside = __DIR__ . '/private';//Aqui modificas el nombre de la carpeta
  if (@is_dir($inside) || @mkdir($inside, 0755, true)) {
    $ht = $inside . '/.htaccess';
    if (!is_file($ht)) {
      @file_put_contents($ht, "Require all denied\nOrder allow,deny\nDeny from all\n");
    }
    return $inside;
  }

  // 3) Último recurso (no ideal): el mismo dir. Intenta escribir .htaccess
  @file_put_contents(__DIR__ . '/.htaccess', "Options -Indexes\n");
  return __DIR__;
}

// ===== Helpers de saneado =====
function clean($s, $max = 2000) {
  $s = trim((string)$s);
  $s = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $s); // quita control chars
  $s = preg_replace('/\s{2,}/u', ' ', $s);          // comprime espacios
  return mb_substr($s, 0, $max);
}
function cleanMultiline($s, $max = 4000) {
  $s = (string)$s;
  $s = str_replace(["\r\n", "\r"], "\n", $s); // normaliza saltos
  $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', ' ', $s); // conserva \n
  $s = preg_replace('/\n{3,}/', "\n\n", $s);
  return mb_substr(trim($s), 0, $max);
}
// CSV “formula injection”: si empieza con =, +, -, @ → anteponer apóstrofe
function defuse_csv_formula($s) {
  $t = ltrim((string)$s);
  return ($t !== '' && preg_match('/^[=\-+@]/', $t)) ? ("'".$s) : $s;
}

// ===== Manejo de POST: guardar en CSV privado =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // CSRF
  if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
    $payload = ['ok'=>false,'error'=>'CSRF token inválido'];
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
      header('Content-Type: application/json; charset=UTF-8'); echo json_encode($payload);
    } else {
      http_response_code(400); echo 'CSRF inválido';
    }
    exit;
  }

  // Saneado
  $first   = clean($_POST['firstName'] ?? '', 120);
  $last    = clean($_POST['lastName']  ?? '', 120);
  $emailIn = clean($_POST['email']     ?? '', 254);
  $email   = filter_var($emailIn, FILTER_VALIDATE_EMAIL) ? $emailIn : '';
  $message = cleanMultiline($_POST['message'] ?? '', 2000);

  // Defuse para CSV + eliminar saltos del mensaje en CSV (opcional)
  $row = [
    date('Y-m-d H:i:s'),
    defuse_csv_formula($first),
    defuse_csv_formula($last),
    defuse_csv_formula($email),
    defuse_csv_formula(str_replace("\n", ' | ', $message)),
  ];

  try {
    $isNew = !file_exists(CONTACT_CSV) || filesize(CONTACT_CSV) === 0;
    $fp = fopen(CONTACT_CSV, 'a');
    if ($fp === false) throw new Exception('No se pudo abrir el CSV.');
    if (function_exists('flock')) @flock($fp, LOCK_EX);

    if ($isNew) {
      // BOM UTF-8 para Excel + encabezados
      fwrite($fp, "\xEF\xBB\xBF");
      fputcsv($fp, ['fecha_hora','nombre','apellidos','email','mensaje']);
    }
    fputcsv($fp, $row);

    if (function_exists('flock')) @flock($fp, LOCK_UN);
    fclose($fp);

    $payload = ['ok'=>true,'message'=>'Guardado correctamente.'];
  } catch (Throwable $e) {
    $payload = ['ok'=>false,'error'=>'No se pudo guardar. Revisa permisos de la carpeta privada.'];
  }

  if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload);
    exit;
  } else {
    $msg = $payload['ok'] ? $payload['message'] : ($payload['error'] ?? 'Error');
    echo "<!doctype html><meta charset='utf-8'><title>Contacto</title>
          <p style='font-family:system-ui, Roboto, sans-serif'>{$msg}</p>
          <p><a href='./'>Volver</a></p>";
    exit;
  }
}
?>
<!doctype html>
<html lang="es" data-bs-theme="dark">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Teramorphosis</title>

  <!-- Bootstrap + Fuentes -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&family=Orbitron:wght@600;700&display=swap" rel="stylesheet">

  <!-- Tus estilos con TOKENS -->
  <link href="./styles.css" rel="stylesheet">
</head>
<body>
<div class="nc-form-shell position-relative">
  <!-- Capa de estrellas (decorativa) -->
  <div class="nc-starfield" aria-hidden="true">
    <div class="nc-stars"></div>
  </div>
    <section id="coming-soon" class="nc-coming-soon overflow-hidden" aria-labelledby="comingSoonTitle" data-target="2025-09-25T00:00:00-06:00"><!--Aqui modificas fecha estimada de lanzamiento-->
      <div class="container py-5 py-lg-6">
        <div class="row g-4 align-items-center justify-content-between">
          <div class="col-12 col-lg-7">
            <div class="nc-card-gradient p-4 p-md-5 rounded-4 position-relative">
              <p class="nc-overline mb-2">teramorphosis.com</p><!--Aqui modificas por el nombre de tu página web-->
              <h1 id="comingSoonTitle" class="display-5 fw-bold font-display mb-3">
                Estamos construyendo algo <span class="nc-neon-underline">futurista</span> 🚧
              </h1>

              <div class="nc-countdown d-flex align-items-center gap-3" role="timer" aria-live="polite">
                <span class="fw-medium">Lanzamiento estimado:</span>
                <ul class="list-inline m-0">
                  <li class="list-inline-item text-center"><span class="h4 m-0 font-monospace" data-days>00</span><br><small>días</small></li>
                  <li class="list-inline-item text-center"><span class="h4 m-0 font-monospace" data-hours>00</span><br><small>horas</small></li>
                  <li class="list-inline-item text-center"><span class="h4 m-0 font-monospace" data-minutes>00</span><br><small>min</small></li>
                  <li class="list-inline-item text-center"><span class="h4 m-0 font-monospace" data-seconds>00</span><br><small>s</small></li>
                </ul>
              </div>

              <hr class="my-4 border-secondary opacity-25" role="presentation">

              <p class="lead mb-4 text-body-secondary">
                La web aún no está lista, pero puedes <strong>contactarnos</strong> y te avisamos en cuanto lancemos.
              </p>

              <!-- === FORM con starfield debajo === -->
              <div class="nc-form-shell position-relative">
                
                <form id="contact-form" method="post" role="form" aria-describedby="contact-help" class="nc-form-content" novalidate>
                  <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                  <div class="row g-3">
                    <div class="col-md-6">
                      <label for="firstName" class="form-label">Nombre</label>
                      <input type="text" class="form-control" id="firstName" name="firstName" placeholder="Tu nombre" autocomplete="given-name" required aria-required="true">
                    </div>
                    <div class="col-md-6">
                      <label for="lastName" class="form-label">Apellidos</label>
                      <input type="text" class="form-control" id="lastName" name="lastName" placeholder="Tus apellidos" autocomplete="family-name">
                    </div>
                    <div class="col-12">
                      <label for="email" class="form-label">Email</label>
                      <input type="email" class="form-control" id="email" name="email" placeholder="tucorreo@dominio.com" autocomplete="email" required aria-required="true">
                    </div>
                    <div class="col-12">
                      <label for="message" class="form-label">Mensaje</label>
                      <textarea id="message" name="message" class="form-control" rows="4" placeholder="Escribe tu mensaje" required aria-required="true"></textarea>
                    </div>
                    <div class="col-12 d-flex align-items-center justify-content-between gap-2 flex-wrap">
                      <small id="contact-help" class="text-body-secondary">Protegemos tus datos. No compartimos tu información.
                      </small>
                      <button type="submit" class="btn btn-primary btn-lg rounded-pill px-4">Enviar</button>
                    </div>
                  </div>
                  <div class="mt-3" aria-live="polite" aria-atomic="true" id="form-status"></div>
                </form>
              </div>
              <!-- === /FORM === -->
            </div>
          </div>
        </div>
      </div>
    </section>
  </div>
  <script>
    // Countdown
    (() => {
      const section = document.querySelector('#coming-soon');
      if (!section) return;
      const targetISO = section.getAttribute('data-target');
      if (!targetISO) return;
      const target = new Date(targetISO).getTime();
      const el = {
        d: section.querySelector('[data-days]'),
        h: section.querySelector('[data-hours]'),
        m: section.querySelector('[data-minutes]'),
        s: section.querySelector('[data-seconds]')
      };
      const pad = n => String(n).padStart(2, '0');
      const tick = () => {
        const now = Date.now();
        let diff = Math.max(0, target - now);
        const days = Math.floor(diff / 86400000); diff -= days * 86400000;
        const hours = Math.floor(diff / 3600000); diff -= hours * 3600000;
        const minutes = Math.floor(diff / 60000); diff -= minutes * 60000;
        const seconds = Math.floor(diff / 1000);
        if (el.d) el.d.textContent = pad(days);
        if (el.h) el.h.textContent = pad(hours);
        if (el.m) el.m.textContent = pad(minutes);
        if (el.s) el.s.textContent = pad(seconds);
      };
      tick(); setInterval(tick, 1000);
    })();

    // Starfield detrás del form
    (function makeFormStars(){
      const box = document.querySelector('.nc-form-shell .nc-stars');
      if (!box) return;
      const host = box.parentElement; // .nc-starfield
      const W = (host.clientWidth || 600) * 3;
      const H = host.clientHeight || 360;
      const N = 120;
      for (let i = 0; i < N; i++) {
        const s = document.createElement('i');
        s.className = 'nc-star';
        const size = Math.random() < 0.85 ? 2 : 3;
        s.style.setProperty('--size', size + 'px');
        s.style.left = (Math.random() * W) + 'px';
        s.style.top  = (Math.random() * H) + 'px';
        s.style.setProperty('--twinkle', (6 + Math.random()*8) + 's');
        s.style.setProperty('--delay', (-Math.random()*8) + 's');
        box.appendChild(s);
      }
    })();

    // Envío por fetch (sin exponer rutas)
    document.addEventListener('DOMContentLoaded', () => {
      const form = document.getElementById('contact-form');
      const status = document.getElementById('form-status');
      if (!form || !status) return;

      form.addEventListener('submit', async (e) => {
        e.preventDefault();
        status.innerHTML = '';
        if (!form.checkValidity()) { form.reportValidity?.(); return; }

        const fd = new FormData(form);
        status.insertAdjacentHTML('beforeend', `<div class="alert alert-info mb-2">📤 Enviando...</div>`);

        try {
          const res = await fetch('./index.php', {
            method: 'POST', body: fd,
            headers: { 'X-Requested-With': 'fetch' },
            cache: 'no-store'
          });
          const raw = await res.text();
          let data; try { data = JSON.parse(raw); } catch { data = { ok:false, error:'Respuesta no JSON', raw }; }
          if (res.ok && data.ok) {
            status.innerHTML = `<div class="alert alert-success">🎉 ${data.message}</div>`;
            form.reset();
          } else {
            status.innerHTML = `<div class="alert alert-danger">⚠️ ${data.error || 'Error'}</div>`;
          }
        } catch {
          status.innerHTML = `<div class="alert alert-danger">❌ Error de red</div>`;
        }
      });
    });
  </script>
  <script>
    /* Genera N estrellas dentro de .nc-stars */
    (function makeFormStars(){
      const box = document.querySelector('.nc-form-shell .nc-stars');
      if (!box) return;

      // ancho real de la cinta (300% del contenedor visible)
      const host = box.parentElement; // .nc-starfield
      const W = (host.clientWidth || 600) * 3;
      const H = host.clientHeight || 360;

      const N = 120; // cantidad de estrellas (ajusta si quieres)
      for (let i = 0; i < N; i++) {
        const s = document.createElement('i');
        s.className = 'nc-star';

        const size = Math.random() < 0.85 ? 2 : 3;     // 2px o 3px
        s.style.setProperty('--size', size + 'px');
        s.style.left = (Math.random() * W) + 'px';
        s.style.top  = (Math.random() * H) + 'px';
        s.style.setProperty('--twinkle', (6 + Math.random()*8) + 's');
        s.style.setProperty('--delay', (-Math.random()*8) + 's');

        box.appendChild(s);
      }
    })();
    </script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>
</body>
</html>
