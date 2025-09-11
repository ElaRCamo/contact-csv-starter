# Contact-csv-starter
Starter minimal para formulario de contacto en Hostinger que guarda en CSV

Formulario de contacto ultraligero para Hostinger:
2 archivos (index.php, styles.css) + 1 carpeta privada (/private) fuera de public_html.
Guarda fecha/hora, nombre, apellidos, email, mensaje en contacts.csv (UTF-8 con BOM).
Sin base de datos. Sin dependencias. Con CSRF y protección contra CSV/Excel injection.

✨ Características

⚡️ Instalación simple: sube index.php y styles.css a public_html/ y crea /private/ junto a public_html.

🔐 Seguro por defecto: CSRF, sanitización, anti-formula injection, cabeceras CSP/nosniff/frame-deny.

🗂️ Datos en CSV (Excel abre directo), con encabezados y BOM.

🌓 Tema y estilos en styles.css (tokens CSS).

🌌 Fondo opcional de estrellas en todo el body (solo CSS/JS, sin imágenes).

## 📁 Estructura
/ (raíz de tu hosting)
├─ private/                      # carpeta privada (hermana de public_html)
│  └─ contacts.csv               # se crea al primer envío
└─ public_html/
   ├─ index.php                  # página + guardado en CSV
   └─ styles.css                 # tokens + estilos (tú lo personalizas)


## 🎨 Tokens base (`styles.css`)

```css
:root {
  /* Colores */
  --bg: #0a0a1a;           /* fondo */
  --text: #e9e9f1;         /* texto */
  --primary: #8a2be2;      /* color principal */
  --secondary: #ff2d96;    /* secundario vibrante */
  --accent: #00ffe5;       /* acento */

  /* Bordes / sombras */
  --radius: 16px;          /* bordes */
  --shadow-drop: 4px 6px 0 rgba(0, 0, 0, .35);

  /* Tipografías */
  --ff-body: "Poppins", system-ui, -apple-system, "Segoe UI", Arial, sans-serif;
  --ff-title: "Fredoka", "Baloo 2", "Poppins", sans-serif;
}
```

Nota: private/ no es accesible por URL. Si no puedes crearla fuera de public_html, crea public_html/_private y protégela con .htaccess (Require all denied). Aun así, el proyecto intenta primero usar ../private.

🚀 Puesta en marcha (Hostinger)

En hPanel → Archivos, crea la carpeta /private al mismo nivel que public_html.

Permisos sugeridos: 0755.

Sube index.php y styles.css a public_html/.

Abre tu dominio y envía un mensaje de prueba.

Verifica que se generó /private/contacts.csv con tu fila.

⚙️ Requisitos

PHP 8.0+ (mejor 8.1+).

Apache/LiteSpeed (Hostinger) o Nginx con PHP-FPM.

Permisos de escritura en /private.

🎨 Personalización de estilos

Todos los colores y fuentes están en tokens dentro de styles.css.
Ejemplo de bloque de tokens (ajústalo a tu branding):

:root{
  --bg: #0a0a1a;           /* fondo */
  --text: #e9e9f1;         /* texto */
  --primary: #8a2be2;      /* color principal */
  --secondary: #ff2d96;    /* secundario vibrante */
  --accent: #00ffe5;       /* acento */
  --radius: 16px;          /* bordes */
  --shadow-drop: 4px 6px 0 rgba(0,0,0,.35);
  --ff-body: "Poppins", system-ui, -apple-system, "Segoe UI", Arial, sans-serif;
  --ff-title:"Fredoka","Baloo 2","Poppins",sans-serif;
}


🔒 Seguridad (resumen)

CSRF: token de sesión + validación con hash_equals.

Sanitización: elimina caracteres de control y limita longitudes.

CSV/Excel injection: si una celda comienza con = + - @, se escapa con '.

CSP / nosniff / X-Frame-Options: cabeceras ya incluidas.

Sin endpoints de descarga: el CSV solo se ve desde el Administrador de archivos.

🧪 Prueba rápida

En la página, llena Nombre, Apellidos, Email, Mensaje.

Envía. Debe aparecer un aviso “Guardado correctamente”.

Revisa /private/contacts.csv: primera línea con encabezados y luego tu fila.

🛠️ Solución de problemas

No se crea contacts.csv
Revisa permisos de /private (0755) y que sí esté fuera de public_html.
Confirma PHP 8.x en hPanel.

Correo inválido
El email se valida con filter_var. Si es inválido se guarda como vacío.

🧾 Licencia

MIT. Úsalo libremente en proyectos personales o comerciales.
