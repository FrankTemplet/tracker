# Power BI Dashboard - Demo Mode

Este dashboard funciona en **modo demostración** automáticamente cuando no hay credenciales de Power BI configuradas.

## 🎯 Ver el Dashboard con Datos de Prueba

### Opción 1: Sin Configuración (Modo Demo)

El dashboard ya está listo para usar con datos de prueba. Simplemente:

1. Inicia el servidor de desarrollo:
   ```bash
   php artisan serve
   # O si usas Valet/Herd: visita tu dominio local
   ```

2. Inicia el frontend:
   ```bash
   npm run dev
   ```

3. Accede a `/dashboard` después de iniciar sesión

4. Verás un banner azul indicando "Demo Mode" con 4 campañas de ejemplo:
   - Summer Sale 2026 (5 emails)
   - Newsletter May 2026 (3 emails)
   - Product Launch Campaign (4 emails)
   - Customer Retention Email (3 emails)

### Opción 2: Configurar Power BI Real

Si deseas conectar con Power BI real, configura tu `.env`:

```env
POWERBI_TENANT_ID=tu-tenant-id
POWERBI_CLIENT_ID=tu-client-id
POWERBI_CLIENT_SECRET=tu-client-secret
POWERBI_WORKSPACE_ID=tu-workspace-id
POWERBI_DATASET_ID=tu-dataset-id
POWERBI_SCOPE=https://analysis.windows.net/powerbi/api/.default
```

## 📊 Datos de Prueba Incluidos

### Campañas
- 4 campañas diferentes con nombres realistas
- Fechas de creación variadas

### Emails
- 15 emails totales distribuidos entre las campañas
- Asuntos de email realistas
- Remitentes y destinatarios de ejemplo

### Analytics por Email
- Bounces: 5-28 (0.5% - 4.0% tasa)
- Opens: 520-950 (52% - 95% tasa)  
- Clicks: 208-665 (20% - 66% tasa)

Cada email tiene métricas únicas para simular un escenario realista.

## 🔧 Estructura del Código

Los datos de prueba están en:
- `app/Services/FakePowerBiData.php` - Generador de datos fake
- `app/Services/PowerBiService.php` - Detecta automáticamente modo demo

El dashboard detecta automáticamente si no hay credenciales configuradas y usa los datos de prueba sin necesidad de configuración adicional.

## 🚀 Funcionalidades del Dashboard

### Panel Principal
- ✅ Selector de campaña (dropdown)
- ✅ Lista de emails enviados (scroll infinito)
- ✅ Panel de detalles del email
- ✅ Métricas de analytics (bounces, opens, clicks)
- ✅ Indicador de última actualización
- ✅ Banner de modo demo (cuando aplica)

### Interacción
- Selecciona una campaña → ve sus emails
- Haz clic en un email → ve detalles y analytics
- Diseño responsivo de 3 columnas

## 📝 Notas

- El modo demo **no requiere** conexión a Power BI
- Los datos son **estáticos** y no cambian (perfecto para desarrollo)
- El banner desaparece automáticamente cuando configuras credenciales reales
- Todos los tests pasan con datos mock y datos demo
