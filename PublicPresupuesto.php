<?php
namespace FacturaScripts\Plugins\ClientPresupuestosPublic\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Dinamic\Model\Cliente;
use FacturaScripts\Dinamic\Model\PresupuestoCliente;
use FacturaScripts\Dinamic\Model\Producto;

/**
 * Public presupuesto creation - reuses existing FacturaScripts models
 * Simple wrapper that makes presupuesto creation publicly accessible
 */
class PublicPresupuesto extends Controller
{
    public $productos;
    public $message;
    public $presupuesto;

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
        
        // Load products for selection
        $productoModel = new Producto();
        $this->productos = $productoModel->all([], ['descripcion' => 'ASC'], 0, 0);

        // Handle form submission
        if ($this->request->request->get('action') === 'save') {
            $this->savePresupuesto();
        }
    }

    /**
     * Save the presupuesto using existing FS models
     */
    private function savePresupuesto()
    {
        // Get or create client
        $clienteData = [
            'nombre' => $this->request->request->get('nombre'),
            'email' => $this->request->request->get('email'),
            'cifnif' => $this->request->request->get('cifnif'),
            'telefono1' => $this->request->request->get('telefono')
        ];

        $cliente = new Cliente();
        
        // Try to find existing client by email
        $existing = $cliente->all([['email' => $clienteData['email']]], [], 0, 1);
        if (!empty($existing)) {
            $cliente = $existing[0];
        } else {
            $cliente->loadFromData($clienteData);
            if (!$cliente->save()) {
                $this->message = 'Error al guardar cliente';
                return;
            }
        }

        // Create presupuesto
        $presupuesto = new PresupuestoCliente();
        $presupuesto->codcliente = $cliente->codcliente;
        $presupuesto->setSubject($cliente);
        
        // Add products from request
        $productos = $this->request->request->get('productos', []);
        foreach ($productos as $idproducto) {
            if (empty($idproducto)) continue;
            
            $producto = new Producto();
            if ($producto->loadFromCode($idproducto)) {
                $presupuesto->addProduct($producto);
            }
        }

        if ($presupuesto->save()) {
            $this->presupuesto = $presupuesto;
            $this->message = 'Presupuesto creado: ' . $presupuesto->codigo;
        } else {
            $this->message = 'Error al crear presupuesto';
        }
    }
}
