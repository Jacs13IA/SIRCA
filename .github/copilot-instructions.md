# Instrucciones del Proyecto: Sistema Inteligente de Reportes Ciudadanos

## Qué es este proyecto

Sistema web para la recepción, administración y seguimiento de reportes ciudadanos del Ayuntamiento de Champotón.

El sistema permitirá que la ciudadanía reporte incidencias relacionadas con servicios públicos mediante un chatbot impulsado por Inteligencia Artificial.

La solución contará con dos módulos principales:

### Módulo Ciudadano

Permite:

- Registrar reportes mediante chatbot.
- Adjuntar evidencias fotográficas.
- Proporcionar ubicación del incidente.
- Consultar estatus del reporte mediante folio.

### Módulo Administrativo

Permite:

- Gestionar usuarios.
- Gestionar dependencias.
- Consultar reportes.
- Asignar responsables.
- Registrar actividades realizadas.
- Actualizar estatus.
- Cerrar reportes.
- Generar indicadores básicos.

El primer MVP estará enfocado en:

- Alumbrado Público.

Posteriormente deberá poder ampliarse a:

- Protección Civil.
- Agua Potable.
- Bacheo.
- Parques y Jardines.
- Ecología.
- Otras dependencias municipales.

---

# Contexto de Negocio

Actualmente las dependencias municipales registran la información mediante:

- Formatos impresos.
- Bitácoras físicas.
- WhatsApp.

El objetivo del sistema es digitalizar estos procesos.

Durante el levantamiento de requerimientos se identificó que:

Servicios Públicos utiliza formatos para:

- Reportes de alumbrado público.
- Actividades de mantenimiento.
- Control de materiales utilizados.
- Registro de brigadas de trabajo.

Protección Civil recibe reportes principalmente por WhatsApp y no cuenta con un sistema estructurado para dar seguimiento a los casos.

---

# Stack Tecnológico

## Backend

- Laravel 12
- PHP 8.4 o superior

## Frontend

- Bootstrap 5
- Blade

## Base de Datos

- SQLite durante desarrollo
- Compatible con MySQL para futuras implementaciones

## Inteligencia Artificial

- Microsoft Azure AI Foundry
- Azure AI Agent Service

## Integración IA

- Model Context Protocol (MCP)

## Servidor MCP

- Node.js 24 LTS
- JavaScript ES Modules
- Express 5

---

# Arquitectura Obligatoria

Ciudadano

↓

Chat Web

↓

Laravel

↓

MCP Server

↓

Azure AI Foundry Agent

↓

Herramientas MCP

↓

Base de Datos SQLite

---

# Arquitectura del Backend Laravel

Utilizar arquitectura basada en:

- Controllers
- Services
- Form Requests
- Policies
- Models
- Resources

Evitar colocar lógica de negocio dentro de:

- Controladores
- Blade Views

La lógica de negocio debe residir en servicios.

---

# Convenciones Laravel

Utilizar siempre:

- Eloquent ORM
- Resource Controllers
- Route Model Binding
- Form Requests
- Soft Deletes
- Middleware
- Policies
- Seeders
- Factories

No utilizar:

- Consultas SQL crudas salvo casos estrictamente necesarios.
- Lógica de negocio en vistas.

---

# Convenciones Frontend

Utilizar exclusivamente:

- Bootstrap 5

Evitar:

- Frameworks frontend adicionales.
- Dependencias innecesarias.
- CSS inline.

Crear componentes Blade reutilizables cuando sea posible.

---

# Roles del Sistema

## Administrador

Control total del sistema.

## Supervisor

Supervisión y asignación de reportes.

## Operador

Seguimiento y actualización de reportes.

## Ciudadano

Creación y consulta de reportes.

---

# Dependencias Iniciales

## Servicios Públicos

Tipos de reporte:

- Luminaria apagada.
- Poste sin lámpara.
- Lámpara intermitente.
- Cableado dañado.
- Levantamiento eléctrico.

## Protección Civil

Tipos de reporte:

- Árbol en riesgo.
- Incendio.
- Riesgo eléctrico.
- Situación de emergencia.

---

# Flujo de Reportes

Todo reporte debe seguir el siguiente ciclo:

Registrado

↓

Asignado

↓

En Proceso

↓

Atendido

↓

Cerrado

Opcionalmente:

↓

Cancelado

No se deben crear estatus fuera de este flujo sin autorización.

---

# Modelo de Datos

Todo reporte debe almacenar como mínimo:

- Folio único.
- Fecha de creación.
- Ciudadano.
- Dependencia.
- Tipo de reporte.
- Descripción.
- Ubicación.
- Latitud.
- Longitud.
- Estatus.
- Responsable asignado.

Además deberá permitir:

- Evidencias fotográficas.
- Comentarios.
- Historial de seguimiento.
- Auditoría de cambios.

---

# MCP Server

El servidor MCP será responsable de exponer herramientas consumidas por Azure AI Foundry.

Herramientas mínimas:

- create_report
- update_report_status
- get_report_status
- upload_evidence
- get_report_details
- search_reports
- list_report_types

El agente nunca debe acceder directamente a la base de datos.

Toda operación deberá realizarse mediante herramientas MCP.

---

# Azure AI Foundry

El agente deberá:

- Interpretar lenguaje natural.
- Identificar el tipo de incidencia.
- Solicitar información faltante.
- Clasificar reportes.
- Invocar herramientas MCP.
- Generar respuestas amigables al ciudadano.

No deberá tomar decisiones administrativas.

No deberá modificar registros sin confirmación.

---

# Seguridad

Utilizar:

- CSRF Protection.
- Authentication.
- Authorization.
- Validación de entrada.
- Sanitización de datos.
- Protección contra Mass Assignment.

Nunca almacenar:

- Contraseñas en texto plano.
- Tokens.
- Secretos.
- Cadenas de conexión dentro del código fuente.

Toda configuración deberá provenir de variables de entorno.

---

# Escalabilidad

Todo el código debe diseñarse considerando:

- Nuevas dependencias municipales.
- Nuevos tipos de reporte.
- Integración futura con Power BI.
- Integración futura con Azure Maps.
- Integración futura con Microsoft Teams.
- Implementación multimunicipio.

No construir componentes específicos únicamente para Alumbrado Público.

Todo debe ser configurable mediante catálogos.

---

# Generación de Código

Antes de generar código:

1. Analizar requerimientos.
2. Explicar diseño propuesto.
3. Verificar impacto en arquitectura.
4. Mantener compatibilidad con Laravel 12.
5. Mantener compatibilidad con SQLite.
6. Mantener compatibilidad con Azure AI Foundry.

Generar siempre:

- Código completo.
- Buenas prácticas.
- Comentarios relevantes.
- Código listo para producción.
- Sin pseudocódigo.

Evitar reescribir archivos completos cuando una modificación puntual sea suficiente.