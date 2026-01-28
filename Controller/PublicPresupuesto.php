<?php
namespace FacturaScripts\Plugins\ClientPresupuestosPublic\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Dinamic\Model\Cliente;
use FacturaScripts\Dinamic\Model\PresupuestoCliente;
use FacturaScripts\Dinamic\Model\Producto;

/**
 * Public presupuesto creation
 */
class PublicPresupuesto extends Controller
{
    public $productos = [];
    public $message = '';
    public $presupuesto = null;
    public $debug = [];
    public $formData = [];

    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['showonmenu'] = false;
        $pageData['title'] = 'Crear Presupuesto';
        return $pageData;
    }

    public function publicCore(&$response)
    {
        parent::publicCore($response);
        
        // Capture all request info for debugging
        $this->debug[] = "REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD'];
        $this->debug[] = "GET params: " . print_r($_GET, true);
        $this->debug[] = "POST params: " . print_r($_POST, true);
        
        // Load products
        $this->loadProducts();
        
        // Handle form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save') {
            $this->debug[] = "=== FORM SUBMITTED ===";
            $this->formData = $_POST;
            $this->savePresupuesto();
        }
        
        // Render HTML
        $html = $this->getHTMLContent();
        $response->setContent($html);
    }

    private function loadProducts()
    {
        try {
            $productoModel = new Producto();
            $allProducts = $productoModel->all([], ['descripcion' => 'ASC'], 0, 0);
            
            $this->debug[] = "Productos en BD: " . count($allProducts);
            
            foreach ($allProducts as $producto) {
                $variantes = $producto->getVariants();
                if (!empty($variantes)) {
                    foreach ($variantes as $variante) {
                        $this->productos[] = [
                            'idproducto' => $producto->idproducto,
                            'referencia' => $variante->referencia,
                            'descripcion' => $producto->descripcion,
                            'precio' => $variante->precio
                        ];
                    }
                } else {
                    $this->productos[] = [
                        'idproducto' => $producto->idproducto,
                        'referencia' => $producto->referencia,
                        'descripcion' => $producto->descripcion,
                        'precio' => $producto->precio
                    ];
                }
            }
            
            $this->debug[] = "Productos cargados: " . count($this->productos);
        } catch (\Exception $e) {
            $this->debug[] = "ERROR cargando productos: " . $e->getMessage();
        }
    }

    private function savePresupuesto()
    {
        try {
            $nombre = $_POST['nombre'] ?? '';
            $email = $_POST['email'] ?? '';
            $cifnif = $_POST['cifnif'] ?? '';
            $telefono = $_POST['telefono'] ?? '';
            
            $this->debug[] = "Nombre: '$nombre', Email: '$email'";
            
            if (empty($nombre) || empty($email)) {
                $this->message = 'Nombre y email son obligatorios';
                return;
            }

            // Get or create client
            $cliente = new Cliente();
            $where = [new DataBaseWhere('email', $email)];
            $existing = $cliente->all($where, [], 0, 1);
            
            if (!empty($existing)) {
                $cliente = $existing[0];
                $this->debug[] = "Cliente existente: " . $cliente->codcliente;
            } else {
                $cliente->nombre = $nombre;
                $cliente->razonsocial = $nombre;
                $cliente->email = $email;
                $cliente->cifnif = $cifnif;
                $cliente->telefono1 = $telefono;
                
                if (!$cliente->save()) {
                    $this->message = 'Error al guardar cliente';
                    $this->debug[] = "ERROR guardando cliente";
                    return;
                }
                $this->debug[] = "Cliente creado: " . $cliente->codcliente;
            }

            // Create presupuesto
            $presupuesto = new PresupuestoCliente();
            $presupuesto->setSubject($cliente);
            $presupuesto->setDate(date('d-m-Y'), date('H:i:s'));
            
            if (!$presupuesto->save()) {
                $this->message = 'Error al crear presupuesto';
                $this->debug[] = "ERROR creando presupuesto";
                return;
            }
            
            $this->debug[] = "Presupuesto creado: ID=" . $presupuesto->idpresupuesto;

            // Add products
            $productosSeleccionados = $_POST['productos'] ?? [];
            $cantidades = $_POST['cantidades'] ?? [];
            
            $this->debug[] = "Productos seleccionados: " . count($productosSeleccionados);
            
            $added = 0;
            foreach ($productosSeleccionados as $index => $idproducto) {
                if (empty($idproducto)) continue;
                
                $cantidad = isset($cantidades[$index]) ? (float)$cantidades[$index] : 1;
                
                $producto = new Producto();
                if ($producto->loadFromCode($idproducto)) {
                    $variantes = $producto->getVariants();
                    $referencia = !empty($variantes) ? $variantes[0]->referencia : $producto->referencia;
                    
                    $newLine = $presupuesto->getNewProductLine($referencia);
                    if ($newLine) {
                        $newLine->cantidad = $cantidad;
                        if ($newLine->save()) {
                            $added++;
                            $this->debug[] = "Producto añadido: " . $producto->descripcion;
                        }
                    }
                }
            }
            
            $this->debug[] = "Total productos añadidos: $added";
            
            if ($presupuesto->save()) {
                $presupuesto->loadFromCode($presupuesto->primaryColumnValue());
                $this->presupuesto = $presupuesto;
                $this->message = 'Presupuesto creado: ' . $presupuesto->codigo;
                $this->debug[] = "✓ ÉXITO - Código: " . $presupuesto->codigo;
            }

        } catch (\Exception $e) {
            $this->message = 'Error: ' . $e->getMessage();
            $this->debug[] = "EXCEPTION: " . $e->getMessage();
        }
    }

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
        </head>
        <body>
            <div class="container mt-5">
                <div class="row justify-content-center">
                    <div class="col-md-10">
                        <h2><i class="fas fa-file-invoice text-primary"></i> Crear Presupuesto</h2>
                        <hr>

                        <!-- DEBUG ALWAYS VISIBLE -->
                        <div class="alert alert-info">
                            <strong><i class="fas fa-bug"></i> Debug Info:</strong>
                            <ul class="mb-0 mt-2" style="font-size: 0.85em; font-family: monospace;">
                            <?php foreach ($this->debug as $msg): ?>
                                <li><?= htmlspecialchars($msg) ?></li>
                            <?php endforeach; ?>
                            </ul>
                            <hr>
                            <small><strong>Productos disponibles:</strong> <?= count($this->productos) ?></small>
                        </div>

                        <?php if ($this->message): ?>
                        <div class="alert alert-<?= $this->presupuesto ? 'success' : 'danger' ?>">
                            <?= htmlspecialchars($this->message) ?>
                            <?php if ($this->presupuesto): ?>
                            <br><a href="PublicPresupuesto" class="btn btn-sm btn-primary mt-2">Crear otro</a>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <?php if (!$this->presupuesto): ?>
                        <form method="POST" action="PublicPresupuesto">
                            <input type="hidden" name="action" value="save">
                            
                            <div class="card mb-3">
                                <div class="card-header bg-primary text-white">
                                    <strong>Datos del Cliente</strong>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label>Nombre *</label>
                                            <input type="text" name="nombre" class="form-control" required
                                                   value="<?= htmlspecialchars($this->formData['nombre'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label>Email *</label>
                                            <input type="email" name="email" class="form-control" required
                                                   value="<?= htmlspecialchars($this->formData['email'] ?? '') ?>">
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label>CIF/NIF</label>
                                            <input type="text" name="cifnif" class="form-control"
                                                   value="<?= htmlspecialchars($this->formData['cifnif'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label>Teléfono</label>
                                            <input type="text" name="telefono" class="form-control"
                                                   value="<?= htmlspecialchars($this->formData['telefono'] ?? '') ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="card mb-3">
                                <div class="card-header bg-primary text-white">
                                    <strong>Productos (<?= count($this->productos) ?> disponibles)</strong>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($this->productos)): ?>
                                        <div class="alert alert-warning">
                                            ⚠️ No hay productos en la base de datos.
                                            <br><small>Ve a FacturaScripts > Almacén > Productos y crea algunos productos primero.</small>
                                        </div>
                                    <?php else: ?>
                                        <div id="productos-container">
                                            <div class="row mb-2 producto-row">
                                                <div class="col-md-8">
                                                    <select name="productos[]" class="form-select">
                                                        <option value="">-- Seleccionar --</option>
                                                        <?php foreach ($this->productos as $p): ?>
                                                        <option value="<?= $p['idproducto'] ?>">
                                                            <?= htmlspecialchars($p['descripcion']) ?>
                                                            <?php if ($p['referencia']): ?>
                                                                (<?= htmlspecialchars($p['referencia']) ?>)
                                                            <?php endif; ?>
                                                            - <?= number_format($p['precio'], 2) ?>€
                                                        </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-3">
                                                    <input type="number" name="cantidades[]" class="form-control" 
                                                           value="1" min="0.01" step="0.01">
                                                </div>
                                                <div class="col-md-1">
                                                    <button type="button" class="btn btn-danger btn-sm w-100 btn-remove" style="display:none;">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        <button type="button" class="btn btn-secondary btn-sm mt-2" onclick="addRow()">
                                            <i class="fas fa-plus"></i> Añadir producto
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-success btn-lg w-100">
                                <i class="fas fa-check"></i> Crear Presupuesto
                            </button>
                        </form>

                        <script>
                        function addRow() {
                            const container = document.getElementById('productos-container');
                            const row = container.querySelector('.producto-row').cloneNode(true);
                            row.querySelector('select').value = '';
                            row.querySelector('input').value = '1';
                            row.querySelector('.btn-remove').style.display = 'block';
                            container.appendChild(row);
                            updateButtons();
                        }
                        
                        document.addEventListener('click', function(e) {
                            if (e.target.closest('.btn-remove')) {
                                e.target.closest('.producto-row').remove();
                                updateButtons();
                            }
                        });
                        
                        function updateButtons() {
                            const rows = document.querySelectorAll('.producto-row');
                            rows.forEach((row, i) => {
                                row.querySelector('.btn-remove').style.display = rows.length > 1 ? 'block' : 'none';
                            });
                        }
                        </script>
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
