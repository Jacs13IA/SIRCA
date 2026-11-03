# Sistema Inteligente de Reportes Ciudadanos (SIRCA)

SIRCA es una plataforma web para recibir, administrar y dar seguimiento a reportes ciudadanos sobre servicios públicos del Ayuntamiento de Champotón. La solución combina un portal administrativo, un chat para la ciudadanía y herramientas de inteligencia artificial conectadas mediante Model Context Protocol (MCP).

## Alcance del proyecto

El MVP se enfoca en **Servicios Públicos**, comenzando por reportes de alumbrado público. La solución se diseña para incorporar otras dependencias y tipos de incidencia, como Protección Civil, Agua Potable, Bacheo, Parques y Jardines y Ecología.

El sistema contempla:

- Recepción de reportes ciudadanos mediante chat, con descripción y ubicación.
- Consulta del estado de un reporte mediante su folio.
- Gestión administrativa de usuarios, dependencias, tipos de reporte y reportes.
- Asignación de responsables y seguimiento de actividades y estatus.
- Evidencias, comentarios e historial de seguimiento.
- Indicadores básicos para la operación municipal.

El flujo de estatus previsto es: **Registrado → Asignado → En Proceso → Atendido → Cerrado**. También se contempla el estatus **Cancelado**.

## Arquitectura y tecnologías

El flujo de la solución es:

**Ciudadano → Chat web → Laravel → Servidor MCP → Azure AI Foundry Agent → herramientas MCP → API de Laravel → SQLite**

El agente no accede directamente a la base de datos. El servidor MCP consume la API de Laravel, que es responsable de las operaciones con los reportes.

| Componente | Tecnología |
| --- | --- |
| Aplicación web y API | Laravel 12, PHP 8.4 o superior |
| Interfaz | Blade, Bootstrap 5 y Vite |
| Base de datos local | SQLite |
| Servidor MCP | Node.js 24 LTS, JavaScript ES Modules y Express 5 |
| Inteligencia artificial | Azure AI Foundry Agent Service |

## Requisitos para desarrollo local

- PHP 8.4 o superior con las extensiones requeridas por Laravel y SQLite habilitado.
- Composer.
- Node.js 24 LTS y npm.
- Git.

En Windows se pueden ejecutar los comandos siguientes desde PowerShell.

## Instalación

### 1. Obtener el código e instalar dependencias de Laravel

```powershell
git clone https://github.com/Jacs13IA/SIRCA.git
Set-Location .\SIRCA
composer install
if (-not (Test-Path .env)) { Copy-Item .env.example .env }
npm.cmd install
```

Si ya existe un archivo `.env`, el comando lo conserva sin reemplazarlo.

### 2. Configurar Laravel y SQLite

Edita `.env` y configura, como mínimo:

- `APP_URL` para la dirección local de la aplicación.
- `SIRCA_ADMIN_NAME`, `SIRCA_ADMIN_EMAIL` y `SIRCA_ADMIN_PASSWORD` para la cuenta administrativa local.
- `MCP_API_TOKEN` con un valor secreto aleatorio.
- `CITIZEN_MATCHING_KEY` con una clave aleatoria dedicada de al menos 32 bytes para generar huellas HMAC de contactos.
- `FOUNDRY_PROJECT_ENDPOINT`, `FOUNDRY_AGENT_NAME` y `FOUNDRY_MODEL_DEPLOYMENT` si se va a probar el chat conectado a Azure AI Foundry.

No uses credenciales reales compartidas ni guardes el archivo `.env` en Git. No reutilices `APP_KEY` como `CITIZEN_MATCHING_KEY`; mantenla privada y dedicada. Para desarrollo local, la conexión de base de datos predeterminada es SQLite.

```powershell
php artisan key:generate
if (-not (Test-Path database\database.sqlite)) {
    New-Item -ItemType File database\database.sqlite | Out-Null
}
php artisan migrate --seed
```

El seeder crea catálogos y el usuario administrador a partir de las variables `SIRCA_ADMIN_*`. Configúralas antes de ejecutar `migrate --seed`.

### 3. Instalar dependencias del servidor MCP

En otra terminal de PowerShell:

```powershell
Set-Location .\mcp-server
npm.cmd install
if (-not (Test-Path .env)) { Copy-Item .env.example .env }
```

Configura `mcp-server\.env`:

- `MCP_SERVER_TOKEN`: secreto que autentica las solicitudes de Foundry hacia el servidor MCP.
- `LARAVEL_API_BASE_URL`: URL local de Laravel; por defecto `http://127.0.0.1:8000`.
- `LARAVEL_MCP_API_TOKEN`: debe coincidir con `MCP_API_TOKEN` configurado en el `.env` de Laravel.

Usa valores aleatorios y distintos para `MCP_SERVER_TOKEN` y `MCP_API_TOKEN`. No compartas ni publiques estos valores.

## Ejecución local

### Laravel y la interfaz web

Desde la raíz del proyecto:

```powershell
composer run dev
```

Este comando inicia Laravel, el listener de colas, los logs de desarrollo y Vite. La aplicación queda disponible normalmente en `http://127.0.0.1:8000`. Si prefieres iniciar solo el servidor web, ejecuta `php artisan serve`; para compilar los recursos de frontend en modo desarrollo, inicia también `npm.cmd run dev` en otra terminal.

### Servidor MCP

Desde `mcp-server`:

```powershell
npm.cmd start
```

El servidor escucha en el puerto `3100` por defecto. El endpoint MCP es `POST /mcp` y el endpoint de disponibilidad es `GET /healthz`. Laravel debe estar ejecutándose para que las herramientas del MCP puedan consultar o registrar información mediante su API.

### Azure AI Foundry

Para probar la conversación integrada, configura las variables `FOUNDRY_*` en el `.env` de Laravel y registra el servidor MCP en la configuración de herramientas del agente de Azure AI Foundry. El agente llama al servidor MCP; MCP se comunica con la API autenticada de Laravel.

## Herramientas MCP ciudadanas

El servidor ciudadano expone:

- `create_report`
- `get_report_status`
- `search_reports` (búsqueda por folio exacto)
- `list_report_types`
- `upload_evidence`

La evidencia se procesa mediante una referencia temporal emitida por Laravel. El servidor MCP no accede directamente a SQLite ni descarga URLs arbitrarias. Las operaciones administrativas, como cambiar el estatus de reportes, no están expuestas en este servidor ciudadano.

## Vinculación de reportes y deduplicación de ciudadanos

Cuando la ciudadanía consiente proporcionar correo o teléfono, esos datos se cifran y también se usan para vincular reportes de una misma persona. El sistema normaliza el correo y el teléfono (E.164, con México como país predeterminado) y busca mediante huellas HMAC; no compara por nombre ni expone contactos o huellas en la API.

Si se reciben correo y teléfono, ambos deben apuntar al mismo ciudadano activo para reutilizarlo. Si solo se recibe uno, debe identificar un único perfil. Las coincidencias parciales o conflictivas registran el reporte y quedan pendientes de revisión; el administrador global decide si los perfiles corresponden a la misma persona. La bandeja administrativa muestra los contactos enmascarados.

Después de configurar `CITIZEN_MATCHING_KEY` y ejecutar las migraciones, revisa los duplicados existentes desde la raíz del proyecto. La primera ejecución es solo una simulación y no modifica los perfiles:

```powershell
php artisan citizens:deduplicate
```

Revisa los conteos. Para recalcular huellas, consolidar grupos con correo y teléfono idénticos y enviar coincidencias ambiguas a la bandeja administrativa, ejecuta:

```powershell
php artisan citizens:deduplicate --apply
```

La consolidación conserva los reportes y da de baja lógicamente los perfiles duplicados; no elimina el historial. No rotes `CITIZEN_MATCHING_KEY` sin planificar antes el recálculo de todas las huellas existentes.

## Pruebas y compilación

Desde la raíz del proyecto:

```powershell
php artisan test
npm.cmd run build
```

Desde `mcp-server`:

```powershell
npm.cmd run check
```

El comando `check` verifica la sintaxis de entrada del servidor y ejecuta sus pruebas automatizadas.
