# Prestashop para FacturaScripts 2025

Plugin para importar pedidos de PrestaShop a FacturaScripts 2025 como **albaranes de cliente**.

## Características

- ✅ **Compatible con FacturaScripts 2025.51+**
- 📦 Importa pedidos de PrestaShop como **albaranes** (no como pedidos)
- 🔢 Guarda el número de pedido de PrestaShop en el campo **numero2** del albarán
- 🎯 Selector de **estados de pedidos** a importar desde PrestaShop
- 👥 Creación automática de clientes si no existen
- 📍 Importación de direcciones de envío
- 🔄 Sincronización automática mediante CRON
- ⚙️ Interfaz de configuración completa

## Requisitos

- FacturaScripts 2025.0 o superior
- Plugin Almacen instalado
- PHP 8.0 o superior
- PrestaShop con WebService habilitado

## Instalación

1. Descarga el plugin
2. Renombra la carpeta a **Prestashop** (importante: respeta mayúsculas)
3. Copia la carpeta en `Plugins/` de tu instalación de FacturaScripts
4. Accede al panel de administración
5. Ve a **Menú > Administrador > Plugins**
6. Habilita el plugin **Prestashop**

## Configuración en PrestaShop

### Habilitar el WebService

1. En tu panel de PrestaShop, ve a **Parámetros avanzados > Web service**
2. Activa el Web service si no está activado
3. Crea una nueva clave API

### Permisos necesarios para la API Key

La clave API debe tener permisos de **lectura** para:
- `orders` (pedidos)
- `order_states` (estados de pedidos)
- `customers` (clientes)
- `addresses` (direcciones)
- `products` (productos)

## Configuración en FacturaScripts

1. Ve a **Menú > Administrador > Configuración PrestaShop**
2. Completa los campos:
   - **URL de la tienda**: URL completa (ej: `https://mitienda.com`)
   - **API Key**: La clave generada en PrestaShop
   - **Almacén**: Almacén donde se crearán los albaranes
   - **Serie**: Serie para los albaranes importados
3. Haz clic en **"Probar Conexión"** para verificar que todo funciona
4. Haz clic en **"Cargar Estados"** para obtener los estados de PrestaShop
5. Selecciona los **estados de pedidos** que quieres importar
6. Marca **"Activo"** para habilitar la sincronización automática
7. Guarda la configuración

## Funcionamiento

### Importación de pedidos

- Los pedidos se importan como **albaranes de cliente** (no como pedidos)
- El **número de pedido de PrestaShop** se guarda en el campo **"Número 2"** del albarán
- Solo se importan pedidos con los **estados seleccionados**
- Los **clientes se crean automáticamente** si no existen (usando el email como referencia)
- Las **direcciones de envío** se importan al albarán
- Los **productos** se buscan por referencia y se añaden como líneas

### Sincronización automática

Si el plugin está marcado como **"Activo"**, se ejecutará automáticamente mediante CRON según la configuración de FacturaScripts.

### Evitar duplicados

El plugin verifica el campo **numero2** antes de importar. Si ya existe un albarán con el mismo número de pedido de PrestaShop, no se importa de nuevo.

## Campos importantes

### En Albaranes (FacturaScripts)

- **Código**: Código interno del albarán en FacturaScripts
- **Número 2**: 🔹 **Número de pedido de PrestaShop** (ej: XKBKNABJK)
- **Cliente**: Cliente importado o creado desde PrestaShop
- **Observaciones**: Incluye el ID del pedido en PrestaShop

## Estructura del plugin

```
Prestashop/
├── Controller/
│   └── ConfigPrestashop.php         # Controlador de configuración
├── Extension/
│   └── Controller/
│       └── EditAlbaranCliente.php   # Extensión para albaranes
├── Lib/
│   ├── Actions/
│   │   ├── InvoiceDownload.php      # (Pendiente)
│   │   └── OrdersDownload.php       # Importación de pedidos
│   └── PrestashopConnection.php     # Conexión con PrestaShop
├── Model/
│   └── PrestashopConfig.php         # Modelo de configuración
├── Table/
│   └── prestashop_config.xml        # Definición de tabla
├── View/
│   └── ConfigPrestashop.html.twig   # Vista de configuración
├── XMLView/
│   └── ConfigPrestashop.xml         # Definición de vista
├── Cron.php                         # Tarea CRON
├── Init.php                         # Inicialización del plugin
├── composer.json                    # Dependencias
└── facturascripts.ini              # Metadatos del plugin
```

## Cambios en la versión 3.0

### ✨ Novedades

- ✅ **Compatible con FacturaScripts 2025.51+**
- 📦 **Importa a albaranes** en lugar de pedidos
- 🔢 **Número de pedido en numero2** del albarán
- 🎯 **Selector de estados** de PrestaShop
- ⚙️ **Interfaz de configuración** completa

### 🔄 Cambios respecto a versiones anteriores

| Antes (< 3.0) | Ahora (3.0+) |
|---------------|--------------|
| Pedidos | **Albaranes** |
| Sin selector de estados | **Selector de estados** |
| Configuración limitada | **Configuración completa** |
| FacturaScripts 2024 | **FacturaScripts 2025** |

## Solución de problemas

### "No se pudo establecer conexión"
- Verifica que la URL de la tienda sea correcta y accesible
- Verifica que la API Key sea correcta
- Asegúrate de que el WebService esté habilitado en PrestaShop

### "No se encontraron estados"
- Verifica los permisos de la API Key (debe tener lectura en `order_states`)
- Prueba la conexión primero antes de cargar estados

### "No se importan pedidos"
- Verifica que hayas seleccionado al menos un estado
- Verifica que haya pedidos con ese estado en PrestaShop
- Verifica que el almacén y serie estén configurados correctamente
- Revisa los logs de FacturaScripts para más detalles

### Los productos no se importan correctamente
- Verifica que las referencias de productos coincidan entre PrestaShop y FacturaScripts
- Si no existe el producto, se creará una línea sin producto asociado

## Licencia

ESTE PLUGIN NO ES SOFTWARE LIBRE. NO SE PERMITE LA DISTRIBUCIÓN NI PUBLICACIÓN.

## Soporte

Para reportar problemas o sugerir mejoras, contacta con el desarrollador.

## Empaquetado

Para crear un ZIP del plugin:

```bash
zip -r Prestashop.zip Prestashop/ -x "*.git*" ".gitignore"
```