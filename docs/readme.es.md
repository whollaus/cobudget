# CoBudget

[English](../README.md) · [Deutsch](readme.de.md) · [Français](readme.fr.md) · **Español**

**Entiende tus gastos. Reparte los costes de forma justa. Mantén el control en Nextcloud.**

CoBudget es una aplicación de presupuesto doméstico para personas, parejas,
familias y compañeros de piso. Registra ingresos y gastos, planifica próximos
pagos y consulta tu parte de los gastos compartidos. Usa áreas compartidas para
el hogar, un viaje o un grupo pequeño, con justificantes y recordatorios asociados
a sus pagos.

> [!WARNING]
> **Beta — versión de desarrollo actual: 0.8.8.** Haz copias de seguridad
> periódicas y no utilices CoBudget como único registro de datos financieros
> importantes. El desarrollo cuenta con asistencia de IA. Las pruebas automatizadas
> no sustituyen una auditoría de seguridad independiente, que aún no se ha realizado.
> Consulta la [política de seguridad](../SECURITY.md).

## Las finanzas del día a día, a solas o en compañía

- **Mantén el control:** ingresos y gastos con categorías, destinatarios de pagos,
  etiquetas y tags.
- **Planifica:** pagos recurrentes, objetivos de presupuesto, informes,
  comparaciones y previsiones.
- **Reparte los costes:** indica quién pagó, elige el reparto y liquida los saldos
  pendientes. Tu vista personal contabiliza tu parte; el área compartida muestra
  el importe total.
- **Guarda el contexto:** justificantes en Nextcloud Files y recordatorios
  independientes o vinculados a pagos, que puedes completar y archivar.
- **Elige las notificaciones:** Nextcloud, un chat privado de CoBudget en Talk y
  correo electrónico opcional, por separado según el evento. Los recordatorios
  también se sincronizan con un calendario; los cambios en calendarios privados
  se reflejan en CoBudget.
- **Reutiliza los datos:** imprime informes, exporta CSV, copia tablas a una hoja
  de cálculo y crea exportaciones personales o copias de seguridad de administrador.
- **Adapta la aplicación:** conjuntos de datos separados mediante espacios de
  trabajo, vistas para móvil y escritorio y temas claro, oscuro o del sistema.

La interfaz está disponible en inglés, alemán, francés y español.
La documentación técnica adicional está en inglés:
[funciones](../FEATURES.md), [cálculos de los informes](reports.md) y
[concepto de CoBudget](concept.md).

## Cómo se organiza CoBudget

CoBudget se organiza en torno a los pagos, las partes individuales y las áreas
compartidas. Tu presupuesto personal muestra tus propios gastos; el área compartida
conserva el total, quién pagó y cuánto se deben los miembros entre sí.
CoBudget no se conecta a cuentas bancarias ni sincroniza movimientos bancarios.
Los informes se basan en los pagos registrados y su reparto. Su saldo refleja tu
presupuesto, no un saldo bancario conciliado.

## Requisitos y versión 1.0

La beta actual admite **Nextcloud 33–35**. **CoBudget 1.0 requerirá Nextcloud 34 o
posterior. La compatibilidad con Nextcloud 33 termina con la serie 0.x.** Las
pruebas previstas para la 1.0 cubren Nextcloud 34 y 35; aún no hay fecha de publicación.

Usa una versión de PHP compatible con tu Nextcloud (8.2 o posterior para 33/34,
8.3 o posterior para 35), un navegador moderno y tareas en segundo plano operativas.
Talk, calendario y correo son opcionales. Los correos se envían a la dirección de
la cuenta de Nextcloud; el servidor debe tener configurado el envío de correo.
Consulta la [revisión para la 1.0](release-readiness-1.0.md) y las
[pruebas](../tests/README.md).

## Instalación y actualizaciones

Instala CoBudget desde la administración de aplicaciones de Nextcloud a través de
la [App Store](https://apps.nextcloud.com/apps/cobudget), o usa
`occ app:install cobudget`. Para instalar manualmente:

1. Descarga el archivo firmado `cobudget.tar.gz` de las [publicaciones de GitHub](https://github.com/whollaus/cobudget/releases).
2. Extráelo en `apps/` o `custom_apps/` de Nextcloud, con el nombre de carpeta `cobudget`.
3. Activa la aplicación y completa cualquier actualización pendiente.

Los archivos de código fuente que GitHub genera automáticamente no son paquetes
instalables. Haz una copia de seguridad antes de actualizar. Configura `cron.php`
para que se ejecute cada cinco minutos como usuario del servidor web.
Consulta la [guía de administración](operations.md).

## ¿Qué incluye una copia de seguridad?

Una **exportación personal** conserva tu perspectiva financiera y solo se puede
importar en una cuenta sin datos de CoBudget. Las partes de gastos compartidos se
convierten en pagos personales independientes. Una **copia completa de
administrador** contiene los datos financieros de todos los usuarios de CoBudget
y sus preferencias transferibles. Al restaurarla, se reemplazan los datos actuales
de la aplicación.

**Los archivos justificantes no se incluyen; solo se guardan sus rutas.** Haz
copias de esos archivos con tu solución habitual de copias de seguridad para
Nextcloud Files. Esa tarea se mantiene separada de CoBudget. Estas copias no
sustituyen una copia completa de Nextcloud. CoBudget bloquea temporalmente el
acceso a la aplicación durante las copias y restauraciones. Si hay una operación
en curso, vuelve a intentarlo cuando termine. Recarga las pestañas abiertas de
CoBudget después de una restauración. Consulta el
[procedimiento de recuperación](operations.md#backup-and-recovery).

## Comentarios y apoyo

Los informes de errores y los resultados de pruebas son especialmente útiles
durante la beta. CoBudget se mantiene principalmente por una persona; acuerda antes
las contribuciones de código.
[GitHub Issues](https://github.com/whollaus/cobudget/issues) · [Colaborar](../CONTRIBUTING.md) ·
[Cambios](../CHANGELOG.md) · [Comunicar una vulnerabilidad](../SECURITY.md).

Apoya el desarrollo en [GitHub Sponsors](https://github.com/sponsors/whollaus)
o [Stripe](https://donate.stripe.com/aFaeVdfARbPNe897bu5J600).
Licencia: [AGPL-3.0 o posterior](../LICENSE).
