<?php
namespace FacturaScripts\Plugins\WooSync\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Base\ControllerPermissions;
use FacturaScripts\Core\Tools;
use Symfony\Component\HttpFoundation\Response;

class WooSyncConfig extends Controller
{
    public $woocommerce_url = '';
    public $woocommerce_key = '';
    public $woocommerce_secret = '';
    
    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['title'] = 'WooSync Configuration';
        $pageData['menu'] = 'admin';
        $pageData['icon'] = 'fas fa-sync-alt';
        $pageData['showonmenu'] = true;
        return $pageData;
    }

    public function privateCore(&$response, $user, $permissions): void
    {
        parent::privateCore($response, $user, $permissions);
        
        // DEBUG: Log what's happening
        Tools::log()->debug('=== WOO SYNC DEBUG START ===');
        Tools::log()->debug('Method: ' . $this->request->getMethod());
        Tools::log()->debug('URL: ' . $this->request->getUri());
        
        // Always load settings first - DEBUG version
        $this->debugLoadSettings();
        
        // Process POST actions (form submission)
        if ($this->request->getMethod() === 'POST') {
            $action = $this->request->request->get('action', '');
            Tools::log()->debug('POST Action: ' . $action);
            
            if ($action === 'save') {
                $this->debugSaveSettings();
                $this->redirect($this->url() . '?saved=1');
                return;
            }
        }
        
        // Process GET actions (test, sync buttons)
        $action = $this->request->get('action', '');
        Tools::log()->debug('GET Action: ' . $action);
        
        if (!empty($action) && $action !== 'save') {
            $this->processAction($action);
        }
        
        Tools::log()->debug('=== WOO SYNC DEBUG END ===');
    }
    
    private function debugLoadSettings(): void
    {
        // DEBUG: Check what Tools::settings returns
        $url = Tools::settings('WooSync', 'woocommerce_url', 'NOT_FOUND');
        $key = Tools::settings('WooSync', 'woocommerce_key', 'NOT_FOUND');
        $secret = Tools::settings('WooSync', 'woocommerce_secret', 'NOT_FOUND');
        
        Tools::log()->debug('DEBUG - Tools::settings results:');
        Tools::log()->debug('  URL: ' . $url);
        Tools::log()->debug('  Key exists: ' . (!empty($key) && $key !== 'NOT_FOUND' ? 'YES' : 'NO'));
        Tools::log()->debug('  Secret exists: ' . (!empty($secret) && $secret !== 'NOT_FOUND' ? 'YES' : 'NO'));
        
        // Also check database directly
        $sql = "SELECT * FROM settings WHERE name LIKE 'WooSync%'";
        $data = $this->dataBase->select($sql);
        Tools::log()->debug('DEBUG - Database settings: ' . json_encode($data));
        
        // Set values
        $this->woocommerce_url = $url !== 'NOT_FOUND' ? $url : '';
        $this->woocommerce_key = $key !== 'NOT_FOUND' ? $key : '';
        $this->woocommerce_secret = $secret !== 'NOT_FOUND' ? $secret : '';
    }
    
    private function debugSaveSettings(): bool
    {
        $url = $this->request->request->get('woocommerce_url', '');
        $key = $this->request->request->get('woocommerce_key', '');
        $secret = $this->request->request->get('woocommerce_secret', '');
        
        Tools::log()->debug('DEBUG - Saving settings:');
        Tools::log()->debug('  URL to save: ' . $url);
        Tools::log()->debug('  Key to save: ' . (!empty($key) ? 'SET' : 'EMPTY'));
        Tools::log()->debug('  Secret to save: ' . (!empty($secret) ? 'SET' : 'EMPTY'));
        
        if (empty($url) || empty($key) || empty($secret)) {
            Tools::log()->error('WooSync: Validation failed');
            return false;
        }
        
        // Save using Tools::settingsSet
        Tools::settingsSet('WooSync', 'woocommerce_url', $url);
        Tools::settingsSet('WooSync', 'woocommerce_key', $key);
        Tools::settingsSet('WooSync', 'woocommerce_secret', $secret);
        
        Tools::log()->info('WooSync: Settings saved to database');
        
        // Force database commit
        if ($this->dataBase->inTransaction()) {
            $this->dataBase->commit();
        }
        
        // Verify save
        $savedUrl = Tools::settings('WooSync', 'woocommerce_url', 'NOT_SAVED');
        Tools::log()->debug('DEBUG - Verify save: ' . $savedUrl);
        
        // Update current values
        $this->woocommerce_url = $url;
        $this->woocommerce_key = $key;
        $this->woocommerce_secret = $secret;
        
        return true;
    }
    
    private function processAction(string $action): void
    {
        switch ($action) {
            case 'test':
                $this->testConnection();
                break;
            case 'sync':
                $this->syncAll();
                break;
            case 'sync-orders':
                $this->syncOrders();
                break;
            case 'sync-products':
                $this->syncProducts();
                break;
            case 'sync-stock':
                $this->syncStock();
                break;
        }
    }
    
    private function testConnection(): void
    {
        Tools::log()->info('WooSync: Testing connection...');
        
        if (empty($this->woocommerce_url) || empty($this->woocommerce_key) || empty($this->woocommerce_secret)) {
            Tools::log()->error('WooSync: Cannot test - settings empty');
            $this->redirect($this->url() . '?error=' . urlencode('Settings not loaded. Please save again.'));
            return;
        }
        
        try {
            $wooApi = new \FacturaScripts\Plugins\WooSync\Lib\WooCommerceAPI();
            
            if ($wooApi->testConnection()) {
                $this->redirect($this->url() . '?success=' . urlencode('✅ Connection successful!'));
            } else {
                $this->redirect($this->url() . '?error=' . urlencode('❌ Connection failed. Check credentials.'));
            }
        } catch (\Exception $e) {
            Tools::log()->error('WooSync: Connection test error: ' . $e->getMessage());
            $this->redirect($this->url() . '?error=' . urlencode('Connection error: ' . $e->getMessage()));
        }
    }
    
    private function syncAll(): void
    {
        Tools::log()->info('WooSync: Starting full synchronization');
        $this->redirect($this->url() . '?info=' . urlencode('Sync feature coming soon.'));
    }
    
    private function syncOrders(): void
    {
        Tools::log()->info('WooSync: Starting order synchronization');
        $this->redirect($this->url() . '?info=' . urlencode('Order sync feature coming soon.'));
    }
    
    private function syncProducts(): void
    {
        Tools::log()->info('WooSync: Starting product synchronization');
        $this->redirect($this->url() . '?info=' . urlencode('Product sync feature coming soon.'));
    }
    
    private function syncStock(): void
    {
        Tools::log()->info('WooSync: Starting stock synchronization');
        $this->redirect($this->url() . '?info=' . urlencode('Stock sync feature coming soon.'));
    }
    
    protected function createViews(): void
    {
        // Empty
    }
}
