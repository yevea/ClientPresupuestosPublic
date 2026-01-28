<?php
namespace FacturaScripts\Plugins\ClientPresupuestosPublic\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Dinamic\Model\Cliente;
use FacturaScripts\Dinamic\Model\PresupuestoCliente;
use FacturaScripts\Dinamic\Model\Producto;

/**
 * Public presupuesto creation - reuses existing FacturaScripts models
 */
class PublicPresupuesto extends Controller
{
    public $productos = [];
    public $message = '';
    public $presupuesto = null;
    public $debug = [];

    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['showonmenu'] = false;
        $pageData['title'] = 'Crear Presupuesto';
        return $pageData;
    }

    /**
     * Runs the public controller logic
     */
    public function publicCore(&$response)
    {
        parent::publicCore($response);
        
        // Load products with variants
        $this->loadProducts();
        
        // Handle form submission
        if ($this->request->request->get('action') === 'save') {
            $this->debug[] = "Form submitted via POST";
            $this->savePresupuesto();
        }
        
        // Render custom HTML
        $this->renderHTML($response);
    }

    /**
     * Load products from database
     */
    private function loadProducts()
    {
        try {
            $productoModel = new Producto();
            $allProducts = $productoModel->all([], ['descripcion' => 'ASC'], 0, 0);
            
            $this->debug[] = "Total productos en BD: " . count($allProducts);
            
            // Get products with their variants
            foreach ($allProducts as $producto) {
                $variantes = $producto->getVariants();
                if (!empty($variantes)) {
                    foreach ($variantes as $variante) {
                        $this->productos[] = [
                            'idproducto' => $producto->idproducto,
                            'idvariante' => $variante->idvariante,
                            'referencia' => $variante->referencia,
                            'descripcion' => $producto->descripcion,
                            'precio' => $variante->precio
                        ];
                    }
                } else {
                    // Product without variants
                    $this->productos[] = [
                        'idproducto' => $producto->idproducto,
                        'idvariante' => null,
                        'referencia' => $producto->referencia,
                        'descripcion' => $producto->descripcion,
                        'precio' => $producto->precio
                    ];
                }
            }
            
            $this->debug[] = "Total productos/variantes cargados: " . count($this->productos);
        } catch (\Exception $e) {
            $this->debug[] = "Error cargando productos: " . $e->getMessage();
        }
    }

    /**
     * Save the presupuesto using existing FS models
     */
    private function savePresupuesto()
    {
        try {
            $this->debug[] = "Iniciando proceso de guardado...";
            
            // Get form data
            $nombre = $this->request->request->get('nombre');
            $email = $this->request->request->get('email');
            $cifnif = $this->request->request->get('cifnif', '');
            $telefono = $this->request->request->get('telefono', '');
            
            $this->debug[] = "Datos recibidos - Nombre: '$nombre', Email: '$email'";
            
            if (empty($nombre) || empty($email)) {
                $this->message = 'Error: Nombre y email son obligatorios';
                $this->debug[] = "Validación fallida: campos vacíos";
                return;
            }

            // Get or create client
            $cliente = new Cliente();
            
            // Try to find existing client by email
            $where = [new DataBaseWhere('email', $email)];
            $existing = $cliente->all($where, [], 0, 1);
            
            if (!empty($existing)) {
                $cliente = $existing[0];
                $this->debug[] = "Cliente existente: " . $cliente->codcliente;
            } else {
                // Create new client
                $cliente->nombre = $nombre;
                $cliente->razonsocial = $nombre;
                $cliente->email = $email;
                $cliente->cifnif = $cifnif;
                $cliente->telefono1 = $telefono;
                
                if (!$cliente->save()) {
                    $this->message = 'Error al guardar el cliente';
                    $this->debug[] = "Error guardando cliente";
                    return;
                }
                $this->debug[] = "Nuevo cliente creado: " . $cliente->codcliente;
            }

            // Create presupuesto
            $presupuesto = new PresupuestoCliente();
            $presupuesto->setSubject($cliente);
            $presupuesto->setDate(date('d-m-Y'), date('H:i:s'));
            
            if (!$presupuesto->save()) {
                $this->message = 'Error al crear el presupuesto';
                $this->debug[] = "Error guardando presupuesto inicial";
                return;
            }
            
            $this->debug[] = "Presupuesto creado con ID: " . $presupuesto->idpresupuesto;

            // Add products to presupuesto
            $productosSeleccionados = $this->request->request->get('productos', []);
            $cantidades = $this->request->request->get('cantidades', []);
            
            $this->debug[] = "Productos recibidos: " . print_r($productosSeleccionados, true);
            $this->debug[] = "Cantidades recibidas: " . print_r($cantidades, true);
            
            $productoCount = 0;
            foreach ($productosSeleccionados as $index => $idproducto) {
                if (empty($idproducto)) {
                    $this->debug[] = "Producto vacío en índice $index, saltando";
                    continue;
                }
                
                $cantidad = isset($cantidades[$index]) ? (float)$cantidades[$index] : 1;
                
                $producto = new Producto();
                if ($producto->loadFromCode($idproducto)) {
                    $this->debug[] = "Añadiendo producto: {$producto->descripcion}, cantidad: $cantidad";
                    
                    // Get first variant or use product reference
                    $variantes = $producto->getVariants();
                    $referencia = !empty($variantes) ? $variantes[0]->referencia : $producto->referencia;
                    
                    $newLine = $presupuesto->getNewProductLine($referencia);
                    if ($newLine) {
                        $newLine->cantidad = $cantidad;
                        if ($newLine->save()) {
                            $productoCount++;
                            $this->debug[] = "Línea guardada OK";
                        } else {
                            $this->debug[] = "Error guardando línea";
                        }
                    }
                } else {
                    $this->debug[] = "No se pudo cargar producto con ID: $idproducto";
                }
            }
            
            $this->debug[] = "Total productos añadidos: $productoCount";
            
            // Recalculate totals
            $lines = $presupuesto->getLines();
            $this->debug[] = "Líneas después de añadir: " . count($lines);
            
            if ($presupuesto->save()) {
                // Reload to get updated data
                $presupuesto->loadFromCode($presupuesto->primaryColumnValue());
                
                $this->presupuesto = $presupuesto;
                $this->message = 'Presupuesto creado correctamente. Código: ' . $presupuesto->codigo;
                $this->debug[] = "✓ Éxito! Código: " . $presupuesto->codigo . ", Total: " . $presupuesto->total;
            } else {
                $this->message = 'Error al finalizar el presupuesto';
                $this->debug[] = "Error en save final";
            }

        } catch (\Exception $e) {
            $this->message = 'Error: ' . $e->getMessage();
            $this->debug[] = "Exception: " . $e->getMessage() . " en " . $e->getFile() . ":" . $e->getLine();
        }
    }

    /**
     * Render HTML directly
     */
    private function renderHTML(&$response)
    {
        $html = $this->getHTMLContent();
        $response->setContent($html);
    }

    /**
     * Generate HTML content
     */
    private function getHTMLContent(): string
    {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Crear Presupuesto</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
            <style>
                .producto-row { background: #f8f9fa; padding: 15px; margin-bottom: 10px; border-radius: 5px; }
            </style>
        </head>
        <body>
            <div class="container mt-5">
                <div class="row justify-content-center">
                    <div class="col-md-10">
                        <h2><i class="fas fa-file-invoice text-primary"></i> Crear Presupuesto</h2>
                        <hr>

                        <?php if (!empty($this->debug)): ?>
                        <div class="alert alert-info alert-dismissible">
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            <strong><i class="fas fa-bug"></i> Debug Info:</strong>
                            <ul class="mb-0 mt-2" style="font-size: 0.9em;">
                            <?php foreach ($this->debug as $msg): ?>
                                <li><?= htmlspecialchars($msg) ?></li>
                            <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endif; ?>

                        <?php if ($this->message): ?>
                        <div class="alert alert-<?= $this->presupuesto ? 'success' : 'danger' ?>">
                            <strong><?= $this->presupuesto ? '✓' : '✗' ?></strong>
                            <?= htmlspecialchars($this->message) ?>
                            <?php if ($this->presupuesto): ?>
                            <br><br>
                            <a href="PublicPresupuesto" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Crear otro presupuesto
                            </a>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <?php if (!$this->presupuesto): ?>
                        <form method="post" id="presupuestoForm">
                            <input type="hidden" name="action" value="save">
                            
                            <div class="card mb-3">
                                <div class="card-header bg-primary text-white">
                                    <strong><i class="fas fa-user"></i> Datos del Cliente</strong>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Nombre / Razón Social *</label>
                                            <input type="text" name="nombre" class="form-control" required 
                                                   placeholder="Ej: Juan Pérez">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Email *</label>
                                            <input type="email" name="email" class="form-control" required
                                                   placeholder="Ej: juan@example.com">
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">CIF/NIF</label>
                                            <input type="text" name="cifnif" class="form-control"
                                                   placeholder="Ej: 12345678A">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Teléfono</label>
                                            <input type="text" name="telefono" class="form-control"
                                                   placeholder="Ej: 600123456">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="card mb-3">
                                <div class="card-header bg-primary text-white">
                                    <strong><i class="fas fa-shopping-cart"></i> Seleccionar Productos</strong>
                                    <span class="badge bg-light text-dark float-end">
                                        <?= count($this->productos) ?> disponibles
                                    </span>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($this->productos)): ?>
                                        <div class="alert alert-warning">
                                            <i class="fas fa-exclamation-triangle"></i>
                                            No hay productos disponibles. Por favor, crea productos en FacturaScripts primero.
                                        </div>
                                    <?php else: ?>
                                        <div id="productos-container">
                                            <div class="producto-row">
                                                <div class="row">
                                                    <div class="col-md-8 mb-2">
                                                        <label class="form-label fw-bold">Producto</label>
                                                        <select name="productos[]" class="form-select">
                                                            <option value="">-- Seleccionar producto --</option>
                                                            <?php foreach ($this->productos as $prod): ?>
                                                            <option value="<?= $prod['idproducto'] ?>">
                                                                <?= htmlspecialchars($prod['descripcion']) ?>
                                                                <?php if (!empty($prod['referencia'])): ?>
                                                                    (Ref: <?= htmlspecialchars($prod['referencia']) ?>)
                                                                <?php endif; ?>
                                                                - <?= number_format($prod['precio'], 2) ?>€
                                                            </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-3 mb-2">
                                                        <label class="form-label fw-bold">Cantidad</label>
                                                        <input type="number" name="cantidades[]" class="form-control" 
                                                               value="1" min="0.01" step="0.01">
                                                    </div>
                                                    <div class="col-md-1 mb-2 d-flex align-items-end">
                                                        <button type="button" class="btn btn-danger btn-sm btn-remove w-100" 
                                                                style="display:none;">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <button type="button" class="btn btn-secondary btn-sm mt-2" onclick="addProducto()">
                                            <i class="fas fa-plus"></i> Añadir otro producto
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-success btn-lg">
                                    <i class="fas fa-check"></i> Crear Presupuesto
                                </button>
                            </div>
                        </form>

                        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
                        <script>
                        function addProducto() {
                            const container = document.getElementById('productos-container');
                            const firstRow = container.querySelector('.producto-row');
                            const newRow = firstRow.cloneNode(true);
                            
                            // Reset values
                            newRow.querySelector('select').value = '';
                            newRow.querySelector('input[type="number"]').value = '1';
                            newRow.querySelector('.btn-remove').style.display = 'block';
                            
                            container.appendChild(newRow);
                            updateRemoveButtons();
                        }
                        
                        document.addEventListener('click', function(e) {
                            if (e.target.closest('.btn-remove')) {
                                e.target.closest('.producto-row').remove();
                                updateRemoveButtons();
                            }
                        });
                        
                        function updateRemoveButtons() {
                            const rows = document.querySelectorAll('.producto-row');
                            rows.forEach((row, index) => {
                                const btn = row.querySelector('.btn-remove');
                                btn.style.display = rows.length > 1 ? 'block' : 'none';
                            });
                        }
                        
                        // Log form submission
                        document.getElementById('presupuestoForm').addEventListener('submit', function(e) {
                            console.log('Form submitting...');
                        });
                        </script>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-check-circle text-success" style="font-size: 4em;"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
}
