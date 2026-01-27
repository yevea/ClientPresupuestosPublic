# ClientPresupuestosPublic

Plugin simple para FacturaScripts que hace pública la funcionalidad de crear presupuestos.

## ¿Qué hace?

Permite que cualquier persona (sin necesidad de login) pueda crear un presupuesto desde una página web pública.

## Instalación

1. Copia la carpeta `ClientPresupuestosPublic` en `/Plugins/`
2. Activa el plugin desde: Panel de Control > Plugins
3. Accede a: `https://tu-dominio.com/PublicPresupuesto`

## Características

- ✅ Acceso público (sin autenticación)
- ✅ Usa los modelos existentes de FacturaScripts (Cliente, PresupuestoCliente, Producto)
- ✅ Crea clientes automáticamente o usa existentes (busca por email)
- ✅ Selección simple de productos
- ✅ Mínimo código - máxima compatibilidad

## Cómo funciona

1. El cliente ingresa sus datos (nombre, email, CIF, teléfono)
2. Selecciona productos del catálogo
3. Se crea automáticamente:
   - El cliente (si no existe)
   - El presupuesto con los productos seleccionados
4. El presupuesto aparece en Ventas > Presupuestos

## Ventajas vs crear desde cero

- ✅ Reutiliza toda la lógica de FacturaScripts
- ✅ Menos código = menos bugs
- ✅ Actualizaciones de FS se aplican automáticamente
- ✅ Compatible con otros plugins que modifiquen presupuestos

## Personalización

### URL amigable
Añade en `.htaccess`:
```apache
RewriteRule ^presupuesto$ /PublicPresupuesto [L]
```

### Limitar productos
Edita `Controller/PublicPresupuesto.php`, línea ~30:
```php
// Solo productos con stock
$this->productos = $productoModel->all([['stockfis' => 'GT:0']], ['descripcion' => 'ASC']);
```

## Requisitos

- FacturaScripts 2022+
- Productos creados en el sistema
- Tabla de clientes y presupuestos activa

## Versión

1.0 - Versión inicial simplificada
