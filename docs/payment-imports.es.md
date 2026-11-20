# Importar pagos

[English](payment-imports.md) · [Deutsch](payment-imports.de.md) · [Français](payment-imports.fr.md) · **Español**

Activa **Configuración → Funciones → Importación de pagos** para usar ambos métodos. La opción está desactivada de forma predeterminada. Al desactivarla, las importaciones desde carpetas se pausan y conservan su configuración y su progreso. Las importaciones activas se reanudan al volver a activar la opción.

Para una importación puntual, abre **Importación masiva**. Para importaciones periódicas, configura una carpeta. Ambos métodos utilizan el mismo formato JSON. Tu cuenta figura como pagadora en los gastos y como receptora en los ingresos. En un área compartida, CoBudget aplica el reparto habitual del área.

## Formato del archivo

Incluye un objeto por pago en la lista `payments`. Solo `date` y `amount` son obligatorios. Cada archivo admite hasta 5.000 pagos y un tamaño máximo de 5 MiB (5.242.880 bytes). Los importes se expresan en la moneda de tu cuenta. En una importación automática, la moneda de la cuenta al validar el primer lote se aplica a todo el archivo, incluso si la cambias antes de que termine la importación. Para importar ingresos o pagos con fecha futura, activa la opción correspondiente en la configuración.

El ejemplo incluye un gasto de supermercado y un ingreso por nómina. Adapta las fechas y los nombres a tus datos. En la aplicación, **Formato de importación** permite copiar o descargar un ejemplo con la fecha local de hoy.

```json
{
  "payments": [
    {
      "date": "2026-11-19",
      "amount": -42.75,
      "category": "Groceries",
      "payment_partner": "Corner Market",
      "description": "Weekly shopping #household",
      "review": 1
    },
    {
      "date": "2026-11-19",
      "amount": 1850.00,
      "category": "Salary",
      "payment_partner": "Employer",
      "description": "November salary"
    }
  ]
}
```

| Campo | Significado |
| --- | --- |
| `date` | Fecha del pago en formato `YYYY-MM-DD`. |
| `amount` | Importe distinto de cero con un máximo de dos decimales, separados con un punto. Negativo para gastos y positivo para ingresos. |
| `category` | Nombre de la categoría para este tipo de pago, hasta 128 caracteres. |
| `payment_partner` | Nombre del beneficiario o del pagador para este tipo de pago, hasta 128 caracteres. |
| `description` | Texto de hasta 512 caracteres, que puede incluir hashtags como `#household`. |
| `important` | Pago importante. |
| `review` | Pago pendiente de revisión. |
| `fixed_cost` | Gasto fijo (solo gastos). |
| `children` | Pago relacionado con los hijos. |
| `subscription` | Suscripción (solo gastos). |
| `tax_relevant` | Pago con relevancia fiscal. |
| `insurance` | Pago de seguro. |

Cada etiqueta de `important` a `insurance` admite `1` (activada) o `0` (desactivada). Si omites una etiqueta, queda desactivada. CoBudget aplica las etiquetas habilitadas en tu configuración.

## Importación masiva manual

1. Abre **Importación masiva**. Elige **Sin área** para tus pagos personales o selecciona un área compartida.
2. Selecciona el archivo JSON o pega su contenido y pulsa **Mostrar vista previa**.
3. Revisa los importes, las fechas y las categorías o los terceros que se vayan a crear. Confirma la importación para registrar los pagos.

Al importar pagos personales o en un área de tu propiedad, CoBudget puede crear las categorías y los terceros que falten. Los demás miembros deben usar nombres existentes para ese tipo de pago u omitir esos campos. Si la validación detecta un nombre desconocido, se rechaza todo el archivo. **Formato de importación** muestra los nombres disponibles para el destino elegido.

Tras una interrupción, vuelve a abrir la vista previa con los mismos datos y el mismo destino. Verás los pagos que quedan por registrar.

## Importación automática desde una carpeta

1. Abre **Configuración → Importaciones automáticas**. En la fila del destino, elige una carpeta de Archivos de Nextcloud. Al abrir la configuración, CoBudget prepara la carpeta `CoBudget/Import` como punto de partida. Usa una carpeta distinta para cada destino. Al pausar la importación, la carpeta sigue reservada.
2. Elige el intervalo (cada 5 o 15 minutos, cada hora o cada día) y cuándo recibir notificaciones (nunca, solo en caso de error o después de cada importación). Luego activa **Activo**. Una vez activa una importación, puedes elegir Nextcloud, correo electrónico o tu chat personal de Talk en **Configuración → Notificaciones**, según los servicios disponibles en el servidor.
3. Coloca los archivos `.json` completos directamente en la carpeta elegida. Las tareas en segundo plano de Nextcloud revisan la carpeta con el intervalo configurado. Para revisarla de inmediato, pulsa el botón de inicio junto a **Activo**. Los lotes grandes pueden requerir varias ejecuciones.
4. Para ver los detalles en **Ejecución**, pasa el puntero sobre la fecha y hora, selecciónala con el teclado o tócala. Un icono rojo indica un error o un archivo en espera. Dentro de la carpeta de importación, los archivos procesados pasan a `Verarbeitet` y los rechazados a `Fehler`. Selecciona el nombre del archivo para abrirlo en una pestaña nueva.

Las importaciones desde carpetas requieren el bloqueo de archivos de Nextcloud. Si un archivo no se puede leer o mover, permanece en la carpeta de entrada. Si el archivo de origen cambia mientras se registran sus pagos y CoBudget no puede comprobar la nueva versión por un bloqueo o por falta de permisos de lectura, las nuevas entregas de esa carpeta esperan a la comprobación. Si solo está bloqueado el traslado, CoBudget puede rechazar las copias de la versión modificada e importar los demás archivos.

Al archivar un área, su importación por carpeta se oculta y se pausa. Al restaurar el área, se recupera la configuración anterior. Una regla activa se reanuda en la siguiente comprobación.

CoBudget revisa en una comprobación posterior los archivos que se añadan o modifiquen durante una comprobación. Puedes seguir subiendo archivos mientras se procesa una importación. Si la sincronización modifica los pagos mientras un archivo ya contabilizado se mueve a `Verarbeitet`, CoBudget traslada el archivo modificado a `Fehler` y rechaza las copias posteriores. Tras una interrupción, CoBudget sigue rechazando las copias de esos pagos modificados, incluso si has movido el archivo archivado a otra carpeta.

Si la carpeta de destino ya contiene un archivo con el mismo nombre, se conservan ambos. Si corriges un archivo rechazado antes de que pase a `Fehler`, CoBudget comprueba el archivo corregido en una revisión posterior. Si después de una interrupción otro archivo ocupa la ruta de destino prevista, conserva su nombre y puede importarse más adelante. Esto también se aplica si la sincronización sobrescribe el archivo archivado y Nextcloud conserva su identificador.

### Nombres de archivo, duplicados y correcciones

Elige un nombre nuevo **antes de subir cada lote nuevo**, por ejemplo `payments-2026-11-20.json`. Si cambias la fecha de un pago en un archivo ya importado y lo subes con el mismo nombre, CoBudget rechaza el archivo modificado. El número del mensaje indica cuántos pagos se registraron desde el archivo original. La copia rechazada sigue bloqueada aunque le cambies el nombre después. Para corregir un pago ya registrado, edítalo en CoBudget. Si cambias el nombre de un archivo en la carpeta mientras se registran sus pagos, quedan reservados tanto el nombre original como el nuevo.

Si la validación rechaza un borrador antes de registrar pagos, puedes corregirlo y reutilizar su nombre de archivo. Si más adelante se rechaza una versión por un conflicto de nombre, las copias de esa versión siguen bloqueadas.

Si vuelves a subir un lote idéntico con la misma cuenta, en el mismo espacio de trabajo y para el mismo destino, los pagos se registran una sola vez. Esto también se aplica si cambias el nombre del archivo o el orden de los campos dentro de un pago. Para reintentar una importación, conserva el orden original de los pagos. Si dos exportaciones se solapan, elimina los pagos ya enviados antes de subir el lote nuevo.

Si durante el registro desaparece una categoría o un tercero, **Ejecución** muestra el archivo en espera, el nombre que falta y cuántos pagos se han registrado. Los demás archivos se siguen procesando. Pide al propietario del área que restablezca el nombre original. CoBudget retoma el archivo guardado y registra los pagos pendientes con sus respectivas partes.

Si la carpeta se eliminó y se volvió a crear en la misma ruta, selecciónala de nuevo en **Configuración → Importaciones automáticas**. Una vez activa la importación, CoBudget puede terminar de registrar los pagos guardados aunque la nueva carpeta esté vacía.
