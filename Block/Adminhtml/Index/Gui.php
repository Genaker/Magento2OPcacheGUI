<?php
/**
 * Copyright ©  All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Genaker\Opcache\Block\Adminhtml\Index;

use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Helper\Product as ProductHelper;
use Magento\Catalog\Helper\Category as CategoryHelper;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Framework\App\Area;
use Magento\Framework\UrlInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\App\State;
use Magento\Framework\App\Cache\Manager as CacheManager;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Module\Manager as ModuleManager;
use Genaker\Opcache\Performance\PerformaceToolkit;

class Gui extends \Magento\Backend\Block\Template
{
    /**
     * Constructor
     *
     * @param \Magento\Backend\Block\Template\Context  $context
     * @param Emulation $emulation
     * @param StoreManagerInterface $storeManager
     * @param ProductCollectionFactory $productCollectionFactory
     * @param CategoryCollectionFactory $categoryCollectionFactory
     * @param ProductHelper $productHelper
     * @param CategoryHelper $categoryHelper
     * @param UrlInterface $urlBuilder
     * @param ResourceConnection $resourceConnection
     * @param DeploymentConfig $deploymentConfig
     * @param ProductMetadataInterface $productMetadata
     * @param State $appState
     * @param CacheManager $cacheManager
     * @param ScopeConfigInterface $scopeConfig
     * @param ModuleManager $moduleManager
     * @param PerformaceToolkit $performanceToolkit
     * @param array $config
     * @param array $data
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        private Emulation $emulation,
        private StoreManagerInterface $storeManager,
        private ProductCollectionFactory $productCollectionFactory,
        private CategoryCollectionFactory $categoryCollectionFactory,
        private ProductHelper $productHelper,
        private CategoryHelper $categoryHelper,
        private UrlInterface $urlBuilder,
        private ResourceConnection $resourceConnection,
        private DeploymentConfig $deploymentConfig,
        private ProductMetadataInterface $productMetadata,
        private State $appState,
        private CacheManager $cacheManager,
        private ScopeConfigInterface $scopeConfig,
        private ModuleManager $moduleManager,
        private PerformaceToolkit $performanceToolkit,
        private array $config = [],
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Get performance configuration value
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getConfig(string $key, $default = null)
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * Get performance test iterations count
     *
     * @return int
     */
    public function getPerformanceIterations(): int
    {
        return (int)$this->getConfig('performance_iterations', 3);
    }

    /**
     * Get HTTP performance test iterations count
     *
     * @return int
     */
    public function getHttpPerformanceIterations(): int
    {
        return (int)$this->getConfig('http_performance_iterations', 3);
    }

    /**
     * Get DB performance test iterations count
     *
     * @return int
     */
    public function getDbPerformanceIterations(): int
    {
        return (int)$this->getConfig('db_performance_iterations', 3);
    }

    /**
     * Get collection page size for random selection
     *
     * @return int
     */
    public function getCollectionPageSize(): int
    {
        return (int)$this->getConfig('collection_page_size', 100);
    }

    /**
     * Get the performance toolkit instance
     *
     * @return PerformaceToolkit
     */
    public function getPerformanceToolkit(): PerformaceToolkit
    {
        return $this->performanceToolkit;
    }

    /**
     * Get a random visible+enabled product URL for the current store.
     */
    public function getRandomProductUrl(?int $storeId = null): ?string
    {
        try {
            $store = $storeId ? $this->storeManager->getStore($storeId) : $this->storeManager->getStore();
            $storeId = (int)$store->getId();

            $this->emulation->startEnvironmentEmulation($storeId, Area::AREA_FRONTEND, true);
            try {
                // Build a lightweight, store-scoped product collection
                $collection = $this->productCollectionFactory->create()
                    ->setStoreId($storeId)
                    ->addStoreFilter($storeId)
                    ->addAttributeToSelect(['name', 'url_key']) // minimal attributes; helper can resolve URL
                    ->addAttributeToFilter('status', Status::STATUS_ENABLED)
                    ->addAttributeToFilter('visibility', ['in' => [
                        Visibility::VISIBILITY_IN_CATALOG,
                        Visibility::VISIBILITY_IN_SEARCH,
                        Visibility::VISIBILITY_BOTH
                    ]])
                    ->setPageSize($this->getCollectionPageSize()) // cap for performance
                    ->setCurPage(1);

                $ids = $collection->getAllIds();
                if (empty($ids)) {
                    return null;
                }

                $randomId = $ids[array_rand($ids)];
                $product = $collection->getItemById($randomId);
                if (!$product) {
                    // fallback: load single item
                    $product = $this->productCollectionFactory->create()
                        ->setStoreId($storeId)
                        ->addStoreFilter($storeId)
                        ->addAttributeToSelect(['name', 'url_key'])
                        ->addAttributeToFilter('entity_id', $randomId)
                        ->getFirstItem();
                }

                $product->setStoreId($storeId);
                return $this->productHelper->getProductUrl($product);
            } finally {
                $this->emulation->stopEnvironmentEmulation();
            }
        } catch (\Exception $e) {
            // Log error and return null if something goes wrong
            $this->_logger->error('Error getting random product URL: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get a random active category URL for the current store (skips root categories).
     */
    public function getRandomCategoryUrl(?int $storeId = null): ?string
    {
        try {
            $store = $storeId ? $this->storeManager->getStore($storeId) : $this->storeManager->getStore();
            $storeId = (int)$store->getId();

            $this->emulation->startEnvironmentEmulation($storeId, Area::AREA_FRONTEND, true);
            try {
                $collection = $this->categoryCollectionFactory->create()
                    ->setStoreId($storeId)
                    ->addAttributeToSelect(['name', 'url_key'])
                    ->addIsActiveFilter()
                    ->addFieldToFilter('level', ['gt' => 1]) // skip root
                    ->setPageSize($this->getCollectionPageSize())
                    ->setCurPage(1);

                $ids = $collection->getAllIds();
                if (empty($ids)) {
                    return null;
                }

                $randomId = $ids[array_rand($ids)];
                $category = $collection->getItemById($randomId) ?: $this->categoryCollectionFactory
                    ->create()
                    ->setStoreId($storeId)
                    ->addAttributeToSelect(['name', 'url_key'])
                    ->addFieldToFilter('entity_id', $randomId)
                    ->getFirstItem();

                $category->setStoreId($storeId);
                return $this->categoryHelper->getCategoryUrl($category);
            } finally {
                $this->emulation->stopEnvironmentEmulation();
            }
        } catch (\Exception $e) {
            // Log error and return null if something goes wrong
            $this->_logger->error('Error getting random category URL: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get a random frontend URL (product or category) for performance testing
     */
    public function getRandomFrontendUrl(?int $storeId = null): ?string
    {
        // Randomly choose between product and category (70% product, 30% category)
        $useProduct = (rand(1, 100) <= 70);
        
        if ($useProduct) {
            $url = $this->getRandomProductUrl($storeId);
            // Fallback to category if product URL fails
            if (!$url) {
                $url = $this->getRandomCategoryUrl($storeId);
            }
        } else {
            $url = $this->getRandomCategoryUrl($storeId);
            // Fallback to product if category URL fails
            if (!$url) {
                $url = $this->getRandomProductUrl($storeId);
            }
        }
        
        return $url;
    }

    /**
     * Get Magento base URL
     *
     * @return string
     */
    public function getMagentoBaseUrl(): string
    {
            return $this->storeManager->getStore()->getBaseUrl();
    }

    /**
     * Check OPcache configuration
     *
     * @return array
     */
    public function checkOPcacheConfiguration(): array
    {
        $checks = [];
        
        if (!extension_loaded('Zend OPcache')) {
            $checks[] = ['type' => 'error', 'msg' => 'Zend OPcache extension is NOT LOADED - Critical performance impact'];
            return $checks;
        }
        
        $checks[] = ['type' => 'success', 'msg' => 'Zend OPcache extension: LOADED'];
        
        try {
            $opcacheStatus = opcache_get_status(false);
            $opcacheConfig = opcache_get_configuration();
            
            if (!is_array($opcacheStatus) || !is_array($opcacheConfig)) {
                $checks[] = ['type' => 'error', 'msg' => 'OPcache status/configuration data unavailable'];
                return $checks;
            }
            
            // Check if OPcache is enabled
            $enabled = $opcacheConfig['directives']['opcache.enable'] ?? false;
            if (!$enabled) {
                $checks[] = ['type' => 'error', 'msg' => 'OPcache is DISABLED - Enable opcache.enable in php.ini'];
                return $checks;
            }
            $checks[] = ['type' => 'success', 'msg' => 'OPcache: ENABLED'];
            
            // Memory checks
            $memoryUsed = $opcacheStatus['memory_usage']['used_memory'] ?? 0;
            $memoryFree = $opcacheStatus['memory_usage']['free_memory'] ?? 0;
            $memoryWasted = $opcacheStatus['memory_usage']['wasted_memory'] ?? 0;
            $memoryTotal = $memoryUsed + $memoryFree + $memoryWasted;
            $memoryConsumption = $opcacheConfig['directives']['opcache.memory_consumption'] ?? 0;
            
            // Check free memory (less than 32MB is concerning)
            if ($memoryFree < 32 * 1024 * 1024) {
                $checks[] = ['type' => 'error', 'msg' => 'OPcache free memory: ' . number_format($memoryFree / 1024 / 1024, 1) . 'MB - CRITICALLY LOW, increase opcache.memory_consumption'];
            } elseif ($memoryFree < 64 * 1024 * 1024) {
                $checks[] = ['type' => 'warning', 'msg' => 'OPcache free memory: ' . number_format($memoryFree / 1024 / 1024, 1) . 'MB - LOW, consider increasing memory'];
            } else {
                $checks[] = ['type' => 'success', 'msg' => 'OPcache free memory: ' . number_format($memoryFree / 1024 / 1024, 1) . 'MB - ADEQUATE'];
            }
            
            // Check memory consumption setting
            $memoryConsumption = $memoryConsumption / 1024 / 1024;
            if ($memoryConsumption < 256) {
                $checks[] = ['type' => 'warning', 'msg' => 'OPcache memory consumption: ' . number_format($memoryConsumption, 0) . 'MB - Increase to 512MB+ for Magento'];
            } else {
                $checks[] = ['type' => 'success', 'msg' => 'OPcache memory consumption: ' . number_format($memoryConsumption, 0) . 'MB'];
            }
            
            // Check timestamp validation
            $validateTimestamps = $opcacheConfig['directives']['opcache.validate_timestamps'] ?? null;
            if ($validateTimestamps === true) {
                $checks[] = ['type' => 'error', 'msg' => 'Timestamp validation: ENABLED - Disable opcache.validate_timestamps in production'];
            } else {
                $checks[] = ['type' => 'success', 'msg' => 'Timestamp validation: DISABLED - Optimal for production'];
            }
            
            // Check max accelerated files
            $maxFiles = $opcacheConfig['directives']['opcache.max_accelerated_files'] ?? 0;
            if ($maxFiles < 100000) {
                $checks[] = ['type' => 'warning', 'msg' => 'Max accelerated files: ' . number_format($maxFiles) . ' - Increase to 100,000+ for Magento'];
            } else {
                $checks[] = ['type' => 'success', 'msg' => 'Max accelerated files: ' . number_format($maxFiles)];
            }
            
            // Check hit rate
            if (isset($opcacheStatus['opcache_statistics']['hit_rate'])) {
                $hitRate = $opcacheStatus['opcache_statistics']['hit_rate'];
                if ($hitRate < 90) {
                    $checks[] = ['type' => 'warning', 'msg' => 'Hit rate: ' . number_format($hitRate, 1) . '% - LOW, consider increasing memory or file limits'];
                } elseif ($hitRate < 95) {
                    $checks[] = ['type' => 'warning', 'msg' => 'Hit rate: ' . number_format($hitRate, 1) . '% - MODERATE'];
                } else {
                    $checks[] = ['type' => 'success', 'msg' => 'Hit rate: ' . number_format($hitRate, 1) . '% - EXCELLENT'];
                }
            }
            
            // Check cached scripts
            if (isset($opcacheStatus['opcache_statistics']['num_cached_scripts'])) {
                $cachedScripts = $opcacheStatus['opcache_statistics']['num_cached_scripts'];
                $checks[] = ['type' => 'info', 'msg' => 'Cached scripts: ' . number_format($cachedScripts)];
            }
            
            // Check wasted memory percentage
            if ($memoryTotal > 0) {
                $wastedPercent = ($memoryWasted / $memoryTotal) * 100;
                if ($wastedPercent > 10) {
                    $checks[] = ['type' => 'warning', 'msg' => 'Wasted memory: ' . number_format($wastedPercent, 1) . '% - HIGH, consider restarting or optimizing'];
                } else {
                    $checks[] = ['type' => 'success', 'msg' => 'Wasted memory: ' . number_format($wastedPercent, 1) . '% - ACCEPTABLE'];
                }
            }
            
        } catch (\Exception $e) {
            $checks[] = ['type' => 'error', 'msg' => 'OPcache check error: ' . $e->getMessage()];
        }
        
        // Check for Xdebug
        if (extension_loaded('xdebug')) {
            $checks[] = ['type' => 'error', 'msg' => 'Xdebug: ENABLED - CRITICAL performance impact, disable on production'];
        } else {
            $checks[] = ['type' => 'success', 'msg' => 'Xdebug: NOT DETECTED - Good for production'];
        }
        
        return $checks;
    }

    /**
     * Check PHP configuration
     *
     * @return array
     */
    public function checkPHPConfiguration(): array
    {
        $checks = [];
        
        // Memory Limit
        $memory_limit = ini_get('memory_limit');
        $memory_bytes = str_replace(['K', 'M', 'G'], ['000', '000000', '000000000'], $memory_limit);
        $memory_mb = (int)$memory_bytes / 1024 / 1024;
        
        if ($memory_mb < 2048) {
            $checks[] = ['type' => 'error', 'msg' => "Memory limit too low: {$memory_limit}. Recommended: 2G+"];
        } else {
            $checks[] = ['type' => 'success', 'msg' => "Memory limit: {$memory_limit}"];
        }
        
        // Max Execution Time
        $max_exec = ini_get('max_execution_time');
        if ($max_exec > 0 && $max_exec < 1800) {
            $checks[] = ['type' => 'warning', 'msg' => "Max execution time: {$max_exec}s. Consider 1800+ for deployments"];
        } else {
            $checks[] = ['type' => 'success', 'msg' => "Max execution time: " . ($max_exec == 0 ? 'unlimited' : $max_exec . 's')];
        }
        
        // File Upload Limits
        $upload_max = ini_get('upload_max_filesize');
        $post_max = ini_get('post_max_size');
        $checks[] = ['type' => 'info', 'msg' => "Upload limits: {$upload_max} (upload), {$post_max} (post)"];
        
        // Required Extensions
        $required_extensions = [
            'curl' => 'cURL for API calls',
            'gd' => 'GD for image processing', 
            'intl' => 'Internationalization',
            'mbstring' => 'Multibyte strings',
            'soap' => 'SOAP web services',
            'zip' => 'ZIP file handling',
            'bcmath' => 'Precision mathematics'
        ];
        
        foreach ($required_extensions as $ext => $desc) {
            if (extension_loaded($ext)) {
                $checks[] = ['type' => 'success', 'msg' => "Extension {$ext}: LOADED ({$desc})"];
            } else {
                $checks[] = ['type' => 'error', 'msg' => "Extension {$ext}: MISSING - {$desc}"];
            }
        }
        
        // Performance Extensions
        $performance_extensions = [
            'Zend OPcache' => 'PHP bytecode caching',
            'redis' => 'Redis cache support',
            'apcu' => 'APCu support',
            'imagick' => 'Advanced image processing'
        ];
        
        foreach ($performance_extensions as $ext => $desc) {
            if (extension_loaded($ext)) {
                $checks[] = ['type' => 'success', 'msg' => "PHP extension {$ext}: AVAILABLE ({$desc})"];
            } else {
                $checks[] = ['type' => 'warning', 'msg' => "PHP extension {$ext}: NOT FOUND - {$desc}"];
            }
        }
        
        return $checks;
    }

    /**
     * Check server configuration
     *
     * @return array
     */
    public function checkServerConfiguration(): array
    {
        $checks = [];
        
        // Server Software
        $server_software = $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown';
        $checks[] = ['type' => 'info', 'msg' => "Server: {$server_software}"];
        
        // Magento Cloud Detection
        $this->checkMagentoCloud($checks);
        
        // Document Root Permissions
        $doc_root = $_SERVER['DOCUMENT_ROOT'] ?? '';
        if ($doc_root && is_writable($doc_root)) {
            $checks[] = ['type' => 'warning', 'msg' => "Document root({$doc_root}) is writable - security risk"];
        } else {
            $checks[] = ['type' => 'success', 'msg' => "Document root permissions: OK"];
        }

        if (is_writable(BP)) {
            $checks[] = ['type' => 'warning', 'msg' => "Magento root(" . BP . ") is writable - security risk"];
        } else {
            $checks[] = ['type' => 'success', 'msg' => "Magento root permissions: OK"];
        }
        
        // Directory Size Checks
        $this->checkDirectorySize($checks, BP . '/pub/media', 'Media Directory', 1024); // 1GB warning threshold
        $this->checkDirectorySize($checks, BP . '/var/cache', 'Cache Directory', 512); // 512MB warning threshold
        $this->checkDirectorySize($checks, BP . '/var/log', 'Log Directory', 256); // 256MB warning threshold
        $this->checkDirectorySize($checks, BP . '/var/session', 'Session Directory', 128); // 128MB warning threshold
        $this->checkDirectorySize($checks, BP . '/var/tmp', 'Temporary Directory', 512); // 512MB warning threshold
        $this->checkDirectorySize($checks, BP . '/generated', 'Generated Code Directory', 256); // 256MB warning threshold
        
        // Disk Space Check
        $free_space = disk_free_space('.');
        $total_space = disk_total_space('.');
        $free_gb = round($free_space / 1024 / 1024 / 1024, 2);
        $total_gb = round($total_space / 1024 / 1024 / 1024, 2);
        $usage_pct = round((($total_space - $free_space) / $total_space) * 100, 1);
        
        if ($usage_pct > 90) {
            $checks[] = ['type' => 'error', 'msg' => "Disk usage: {$usage_pct}% ({$free_gb}GB free of {$total_gb}GB)"];
        } elseif ($usage_pct > 80) {
            $checks[] = ['type' => 'warning', 'msg' => "Disk usage: {$usage_pct}% ({$free_gb}GB free of {$total_gb}GB)"];
        } else {
            $checks[] = ['type' => 'success', 'msg' => "Disk usage: {$usage_pct}% ({$free_gb}GB free of {$total_gb}GB)"];
        }
        
        // Load Average (Linux only)
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            $load_str = implode(', ', array_map(function($l) { return number_format($l, 2); }, $load));
            $checks[] = ['type' => 'info', 'msg' => "Load average: {$load_str}"];
        }
        
        return $checks;
    }

    /**
     * Check if running on Magento Cloud and provide migration recommendations
     *
     * @param array &$checks Reference to checks array to add results to
     * @return void
     */
    private function checkMagentoCloud(array &$checks): void
    {
        $isMagentoCloud = false;
        $cloudIndicators = [];
        
        // Check for Magento Cloud environment variables
        $cloudEnvVars = [
            'MAGENTO_CLOUD_PROJECT' => 'Project ID',
            'MAGENTO_CLOUD_ENVIRONMENT' => 'Environment',
            'MAGENTO_CLOUD_BRANCH' => 'Branch',
            'MAGENTO_CLOUD_TREE_ID' => 'Tree ID',
            'MAGENTO_CLOUD_APPLICATION_NAME' => 'Application',
            'PLATFORM_PROJECT' => 'Platform Project',
            'PLATFORM_ENVIRONMENT' => 'Platform Environment'
        ];
        
        foreach ($cloudEnvVars as $envVar => $description) {
            if (!empty($_ENV[$envVar]) || !empty(getenv($envVar))) {
                $isMagentoCloud = true;
                $cloudIndicators[] = $description;
                $checks[] = ['type' => 'info', 'msg' => "🔍 Found environment variable: {$envVar}"];
            }
        }
        
        // Check for cloud-specific directories and files
        $cloudPaths = [
            '/.magento.app.yaml' => 'Magento Cloud configuration',
            '/.magento/services.yaml' => 'Cloud services configuration',
            '/.magento/routes.yaml' => 'Cloud routes configuration',
            '/var/log/platform' => 'Platform log directory'
        ];
        
        foreach ($cloudPaths as $path => $description) {
            if (file_exists(BP . $path)) {
                $isMagentoCloud = true;
                $cloudIndicators[] = $description;
                $checks[] = ['type' => 'info', 'msg' => "🔍 Found cloud file: {$path}"];
            }
        }
        
        // Check hostname patterns common to Magento Cloud
        $hostname = gethostname();
        $checks[] = ['type' => 'info', 'msg' => "Hostname: " . ($hostname ?: 'Unknown')];
        if ($hostname && (
            strpos($hostname, 'web') === 0 || 
            strpos($hostname, 'app') === 0 ||
            preg_match('/^[a-z0-9]+-[a-z0-9]+-[a-z0-9]+$/', $hostname)
        )) {
            $cloudIndicators[] = 'Cloud hostname pattern';
            $checks[] = ['type' => 'info', 'msg' => " Cloud hostname pattern detected"];
        }
        
        // Check for cloud-specific PHP extensions or configurations
        if (extension_loaded('newrelic') && function_exists('newrelic_get_appname')) {
            $appName = newrelic_get_appname();
            if (strpos($appName, 'magento-cloud') !== false || strpos($appName, 'platform-sh') !== false) {
                $isMagentoCloud = true;
                $cloudIndicators[] = 'New Relic cloud integration';
            }
        }
        
        if ($isMagentoCloud) {
            $indicators = implode(', ', array_unique($cloudIndicators));
            $checks[] = ['type' => 'warning', 'msg' => "MAGENTO CLOUD DETECTED ({$indicators}) - Consider migrating to better cloud solutions"];
            $checks[] = ['type' => 'info', 'msg' => "→ Recommended alternatives: AWS (EC2/ECS), Microsoft Azure (App Service), Oracle Cloud (Compute)"];
            $checks[] = ['type' => 'info', 'msg' => "→ Benefits: Better performance, lower costs, more control, advanced caching (Redis/Varnish)"];
            $checks[] = ['type' => 'info', 'msg' => "→ Migration to: AWS, Azure, Oracle Cloud"];
        } else {
            $checks[] = ['type' => 'success', 'msg' => "Not running on Magento Cloud - Good choice for performance and cost optimization"];
        }
    }

    /**
     * Check database table sizes (top 5 largest tables)
     *
     * @return array
     */
    public function checkDatabaseTableSizes(): array
    {
        return $this->performanceToolkit->checkDatabaseTableSizes();
    }

    /**
     * Check Redis memory usage and statistics
     *
     * @return array
     */
    public function checkRedisMemoryUsage(): array
    {
        return $this->performanceToolkit->checkRedisMemoryUsage();
    }

    /**
     * Check directory size and add appropriate warnings
     *
     * @param array &$checks Reference to checks array to add results to
     * @param string $directory Directory path to check
     * @param string $name Human-readable name for the directory
     * @param int $warningThresholdMB Warning threshold in megabytes
     * @return void
     */
    private function checkDirectorySize(array &$checks, string $directory, string $name, int $warningThresholdMB, bool $fileCount = false): void
    {
        try {
            if (!is_dir($directory)) {
                $checks[] = ['type' => 'info', 'msg' => "{$name}({$directory}): Directory does not exist"];
                return;
            }

            $sizeBytes = $this->getDirectorySizeRecursive($directory);
            $sizeMB = round($sizeBytes / 1024 / 1024, 1);
            $sizeGB = round($sizeBytes / 1024 / 1024 / 1024, 2);
            
            // Count files in directory (now using fast Linux commands)
            if ($fileCount) {
                $fileCount = $this->countFilesInDirectory($directory);
            } else {
                $fileCount = 0;
            }
            
            // Determine size display format
            $sizeDisplay = $sizeMB > 1024 ? "{$sizeGB}GB" : "{$sizeMB}MB";
            $fileDisplay = $fileCount > 0 ? " ({$fileCount} files)" : " (N/A)";
            
            // Determine status based on thresholds
            if ($sizeMB > $warningThresholdMB * 2) {
                $checks[] = ['type' => 'error', 'msg' => "{$name}({$directory}): {$sizeDisplay}{$fileDisplay} - LARGE, consider cleanup"];
            } elseif ($sizeMB > $warningThresholdMB) {
                $checks[] = ['type' => 'warning', 'msg' => "{$name}({$directory}): {$sizeDisplay}{$fileDisplay} - Growing large, monitor size"];
            } else {
                $checks[] = ['type' => 'success', 'msg' => "{$name}({$directory}): {$sizeDisplay}{$fileDisplay}"];
            }
            
            // Special recommendations for specific directories
            if (strpos($directory, '/var/log') !== false && $sizeMB > 100) {
                $checks[] = ['type' => 'info', 'msg' => "Log cleanup: Run 'find {$directory} -name \"*.log\" -mtime +30 -delete' to clean old logs"];
            }
            
            if (strpos($directory, '/var/cache') !== false && $sizeMB > 200) {
                $checks[] = ['type' => 'info', 'msg' => "Cache cleanup: Run 'bin/magento cache:clean' and 'bin/magento cache:flush' to clear cache"];
            }
            
            if (strpos($directory, '/var/session') !== false && $sizeMB > 50) {
                $checks[] = ['type' => 'info', 'msg' => "Session cleanup: Old sessions may need cleanup, check session garbage collection"];
            }
            
        } catch (\Exception $e) {
            $checks[] = ['type' => 'warning', 'msg' => "{$name}({$directory}): Error checking size - {$e->getMessage()}"];
        }
    }

    /**
     * Get directory size recursively using fast Linux commands
     *
     * @param string $directory
     * @return int Size in bytes
     */
    private function getDirectorySizeRecursive(string $directory): int
    {
        $size = 0;
        
        // Primary method: Use Linux 'du' command (much faster)
        if (function_exists('exec') && PHP_OS_FAMILY === 'Linux') {
            $output = [];
            $returnVar = 0;
            
            // Use 'du -sb' for size in bytes, suppress errors
            exec("du -sb " . escapeshellarg($directory) . " 2>/dev/null", $output, $returnVar);
            
            if ($returnVar === 0 && !empty($output[0])) {
                $parts = explode("\t", $output[0]);
                $size = (int)$parts[0];
                return $size;
            }
            
            // Fallback: Try with different du options
            $output = [];
            exec("du -s " . escapeshellarg($directory) . " 2>/dev/null", $output, $returnVar);
            if ($returnVar === 0 && !empty($output[0])) {
                $parts = explode("\t", $output[0]);
                $sizeKB = (int)$parts[0];
                $size = $sizeKB * 1024; // Convert KB to bytes
                return $size;
            }
        }
        
        // Fallback method: PHP recursive iteration (slower but more compatible)
        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
            );
            
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $size += $file->getSize();
                }
            }
        } catch (\Exception $e) {
            // Last resort: Use basic filesize if it's a single file
            if (is_file($directory)) {
                $size = filesize($directory);
            }
        }
        
        return $size;
    }

    /**
     * Count files in directory recursively using fast Linux commands
     *
     * @param string $directory
     * @return int Number of files
     */
    private function countFilesInDirectory(string $directory): int
    {
        $count = 0;
        
        // Primary method: Use Linux commands (much faster)
        if (function_exists('exec') && PHP_OS_FAMILY === 'Linux') {
            $output = [];
            $returnVar = 0;
            
            // Use find + wc -l for fast file counting
            exec("find " . escapeshellarg($directory) . " -type f 2>/dev/null | wc -l", $output, $returnVar);
            
            if ($returnVar === 0 && !empty($output[0])) {
                $count = (int)trim($output[0]);
                return $count;
            }
            
            // Alternative approach using ls -laR (for some systems)
            $output = [];
            exec("ls -laR " . escapeshellarg($directory) . " 2>/dev/null | grep -c '^-'", $output, $returnVar);
            if ($returnVar === 0 && !empty($output[0])) {
                $count = (int)trim($output[0]);
                return $count;
            }
        }
        
        // Fallback method: PHP recursive iteration (slower but more compatible)
        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
            );
            
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $count++;
                }
            }
        } catch (\Exception $e) {
            // Last resort: Just return 0 if we can't count
            $count = 0;
        }
        
        return $count;
    }

    /**
     * Check Composer optimization
     *
     * @return array
     */
    public function checkComposerOptimization(): array
    {
        $checks = [];
        
        try {
            // Check if vendor/composer directory exists
            $composerDir = BP . '/vendor/composer';
            if (!is_dir($composerDir)) {
                $checks[] = ['type' => 'warning', 'msg' => 'Composer vendor directory not found'];
                return $checks;
            }
            
            // Check for optimized autoloader files
            $autoloadReal = $composerDir . '/autoload_real.php';
            $autoloadClassmap = $composerDir . '/autoload_classmap.php';
            $autoloadStatic = $composerDir . '/autoload_static.php';
            
            // Check if autoloader is optimized (-o flag)
            if (file_exists($autoloadClassmap)) {
                $classmapContent = file_get_contents($autoloadClassmap);
                // Safely parse the classmap file without using eval
                $classmapSize = 0;
                try {
                    $classmapContent = str_replace('<?php', '', $classmapContent);
                    $classmapArray = include $autoloadClassmap;
                    if (is_array($classmapArray)) {
                        $classmapSize = count($classmapArray);
                    }
                } catch (\Exception $e) {
                    // Fallback: check file size as an indicator
                    $fileSize = filesize($autoloadClassmap);
                    $classmapSize = $fileSize > 50000 ? 'many' : 'few';
                }
                
                if (is_numeric($classmapSize) && $classmapSize > 1000) {
                    $checks[] = ['type' => 'success', 'msg' => "Composer autoloader: OPTIMIZED (Level 1) - {$classmapSize} classes mapped"];
                } elseif ($classmapSize === 'many' || (is_numeric($classmapSize) && $classmapSize > 0)) {
                    $checks[] = ['type' => 'success', 'msg' => "Composer autoloader: OPTIMIZED (Level 1) - classmap exists"];
                } else {
                    $checks[] = ['type' => 'warning', 'msg' => "Composer autoloader: Partially optimized - Run 'composer dump-autoload -o' for better performance"];
                }
            } else {
                $checks[] = ['type' => 'error', 'msg' => "Composer autoloader: NOT OPTIMIZED - Run 'composer dump-autoload -o' for better performance"];
            }
            
            // Check for APCu optimization (-a flag)
            if (file_exists($autoloadStatic)) {
                $staticContent = file_get_contents($autoloadStatic);
                if (strpos($staticContent, 'getLoader') !== false) {
                    $checks[] = ['type' => 'success', 'msg' => 'Composer autoloader: Uses static loading (Level 2 optimization)'];
                }
            }
            
            // Check if APCu is available and could be used
            if (extension_loaded('apcu') && ini_get('apc.enabled')) {
                $checks[] = ['type' => 'info', 'msg' => "APCu available - Run 'composer dump-autoload -o -a' for maximum optimization"];
            } else {
                $checks[] = ['type' => 'warning', 'msg' => 'APCu not available - Install APCu extension for Level 2/A optimization'];
            }
            
            // Check composer.json for optimization settings
            $composerJson = BP . '/composer.json';
            if (file_exists($composerJson)) {
                $composerConfig = json_decode(file_get_contents($composerJson), true);
                
                // Check for optimize-autoloader config
                if (isset($composerConfig['config']['optimize-autoloader']) && $composerConfig['config']['optimize-autoloader'] === true) {
                    $checks[] = ['type' => 'success', 'msg' => 'Composer config: optimize-autoloader is enabled in composer.json'];
                } else {
                    $checks[] = ['type' => 'warning', 'msg' => 'Composer config: Add "optimize-autoloader": true to composer.json config section'];
                }
                
                // Check for apcu-autoloader config
                if (isset($composerConfig['config']['apcu-autoloader']) && $composerConfig['config']['apcu-autoloader'] === true) {
                    $checks[] = ['type' => 'success', 'msg' => 'Composer config: apcu-autoloader is enabled in composer.json'];
                } else {
                    $checks[] = ['type' => 'info', 'msg' => 'Composer config: Consider adding "apcu-autoloader": true for maximum performance (requires APCu)'];
                }
                
                // Check for classmap-authoritative
                if (isset($composerConfig['config']['classmap-authoritative']) && $composerConfig['config']['classmap-authoritative'] === true) {
                    $checks[] = ['type' => 'success', 'msg' => 'Composer config: classmap-authoritative is enabled - Maximum optimization active'];
                } else {
                    $checks[] = ['type' => 'warning', 'msg' => 'Composer config: Consider adding "classmap-authoritative": true for production (requires full class mapping)'];
                }
            }
            
            // Performance recommendations
            $checks[] = ['type' => 'info', 'msg' => 'Optimization Commands:'];
            $checks[] = ['type' => 'info', 'msg' => '• Level 1: composer dump-autoload -o (optimize autoloader)'];
            $checks[] = ['type' => 'info', 'msg' => '• Level 2: composer dump-autoload -o -a (APCu cache, requires APCu extension)'];
            $checks[] = ['type' => 'info', 'msg' => '• Production: composer dump-autoload -o --classmap-authoritative'];
            
        } catch (\Exception $e) {
            $checks[] = ['type' => 'error', 'msg' => 'Composer optimization check error: ' . $e->getMessage()];
        }
        
        return $checks;
    }

    /**
     * Check Magento configuration
     *
     * @return array
     */
    public function checkMagentoConfiguration(): array
    {
        $checks = [];
        
        try {
            // Magento Version Check
            $magentoVersion = $this->productMetadata->getVersion();
            $magentoEdition = $this->productMetadata->getEdition();
            
            // Check if version is up to date (2.4.8+ is latest as of 2024)
            if (version_compare($magentoVersion, '2.4.8', '<=')) {
                if (version_compare($magentoVersion, '2.4.6', '<=')) { // 2.4.6 is the EOL version as of 2025
                    $checks[] = ['type' => 'error', 'msg' => "Magento version: {$magentoEdition} {$magentoVersion} - OUTDATED, upgrade to 2.4.7+ for security patches and performance"];
                } else {
                    $checks[] = ['type' => 'warning', 'msg' => "Magento version: {$magentoEdition} {$magentoVersion} - Upgrade to 2.4.7+ for latest features and security"];
                }
            } else {
                $checks[] = ['type' => 'success', 'msg' => "Magento version: {$magentoEdition} {$magentoVersion} - CURRENT"];
            }
            
            // Deployment Mode
            $mode = $this->appState->getMode();
            
            if ($mode === 'production') {
                $checks[] = ['type' => 'success', 'msg' => "Deployment mode: PRODUCTION"];
            } elseif ($mode === 'developer') {
                $checks[] = ['type' => 'warning', 'msg' => "Deployment mode: DEVELOPER (not for production)"];
            } else {
                $checks[] = ['type' => 'warning', 'msg' => "Deployment mode: {$mode}"];
            }
            
            // Cache Status
            $cacheStatus = $this->cacheManager->getStatus();
            $enabled_caches = array_filter($cacheStatus);
            $disabled_caches = array_diff_key($cacheStatus, $enabled_caches);
            
            $checks[] = ['type' => 'info', 'msg' => "Cache types enabled: " . count($enabled_caches) . "/" . count($cacheStatus)];
            
            if (count($disabled_caches) > 0) {
                $checks[] = ['type' => 'warning', 'msg' => "Disabled caches: " . implode(', ', array_keys($disabled_caches))];
            }
            
            // Full Page Cache
            if (isset($enabled_caches['full_page'])) {
                $checks[] = ['type' => 'success', 'msg' => "Full Page Cache: ENABLED"];
            } else {
                $checks[] = ['type' => 'error', 'msg' => "Full Page Cache: DISABLED - Critical for performance"];
            }
            
            // JS/CSS Performance Settings
            // JavaScript Bundling Check
            $jsBundling = $this->scopeConfig->getValue('dev/js/enable_js_bundling', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
            if ($jsBundling) {
                $checks[] = ['type' => 'warning', 'msg' => "JavaScript Bundling: ENABLED - Can cause performance issues, consider disabling"];
            } else {
                $checks[] = ['type' => 'success', 'msg' => "JavaScript Bundling: DISABLED"];
            }
            
            // CSS Merge Check
            $cssMerge = $this->scopeConfig->getValue('dev/css/merge_css_files', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
            if ($cssMerge) {
                $checks[] = ['type' => 'warning', 'msg' => "CSS Merge: ENABLED - Can cause performance issues, consider disabling"];
            } else {
                $checks[] = ['type' => 'success', 'msg' => "CSS Merge: DISABLED"];
            }
            
            // JS Merge Check
            $jsMerge = $this->scopeConfig->getValue('dev/js/merge_files', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
            if ($jsMerge) {
                $checks[] = ['type' => 'warning', 'msg' => "JavaScript Merge: ENABLED - Can cause performance issues, consider disabling"];
            } else {
                $checks[] = ['type' => 'success', 'msg' => "JavaScript Merge: DISABLED"];
            }
            
            // CSS/JS Minification
            $cssMinify = $this->scopeConfig->getValue('dev/css/minify_files', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
            $jsMinify = $this->scopeConfig->getValue('dev/js/minify_files', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
            
            if ($cssMinify) {
                $checks[] = ['type' => 'success', 'msg' => "CSS Minification: ENABLED"];
            } else {
                $checks[] = ['type' => 'warning', 'msg' => "CSS Minification: DISABLED - Enable for production"];
            }
            
            if ($jsMinify) {
                $checks[] = ['type' => 'success', 'msg' => "JavaScript Minification: ENABLED"];
            } else {
                $checks[] = ['type' => 'warning', 'msg' => "JavaScript Minification: DISABLED - Enable for production"];
            }
            
            // Template Minification
            $htmlMinify = $this->scopeConfig->getValue('dev/template/minify_html', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
            if ($htmlMinify) {
                $checks[] = ['type' => 'success', 'msg' => "HTML Minification: ENABLED"];
            } else {
                $checks[] = ['type' => 'warning', 'msg' => "HTML Minification: DISABLED - Enable for production"];
            }
            
            // Check for Hyva Theme (License Compliance)
            $hyvaModules = [
                'Hyva_Theme',
                'Hyva_Base',
                'Hyva_GraphqlViewModel'
            ];
            
            $hyvaDetected = false;
            foreach ($hyvaModules as $hyvaModule) {
                if ($this->moduleManager->isEnabled($hyvaModule)) {
                    $hyvaDetected = true;
                    break;
                }
            }
            
            if ($hyvaDetected) {
                $checks[] = ['type' => 'error', 'msg' => "Hyva Theme DETECTED - WARNING: Hyva is a commercial theme. Using it with Magento Open Source may violate licensing terms. Ask Adobe to provide a clarification and audit for this case"];
            } else {
                $checks[] = ['type' => 'success', 'msg' => "Hyva Theme: NOT DETECTED"];
            }
            
            // Additional check if module is enabled
            if ($this->moduleManager->isEnabled('React_React')) {
                $checks[] = ['type' => 'success', 'msg' => "React Luma Module: ENABLED"];
            } else {
                $checks[] = ['type' => 'warning', 'msg' => "React Luma Module: NOT INSTALLED - Install this opensource module for frontend Google Page Speed performance benefits"];
            }
            
            // Check for Mage_FPC Performance Module
            if ($this->moduleManager->isEnabled('Mage_FPC')) {
                $checks[] = ['type' => 'success', 'msg' => "Mage FPC Module: ENABLED - Enhanced Full Page Cache performance active"];
            } else {
                // Check if the module is installed but not enabled
                $checks[] = ['type' => 'warning', 'msg' => "Mage FPC Module: NOT INSTALLED - Recommended for improved Full Page Cache performance. Install with: composer require mage/fpc"];
            }
        } catch (\Exception $e) {
            $checks[] = ['type' => 'error', 'msg' => "Magento check error: " . $e->getMessage()];
        }
        
        return $checks;
    }

    /**
     * Check security configuration
     *
     * @return array
     */
    public function checkSecurity(): array
    {
        $checks = [];
        
        // PHP Version - More comprehensive check
        $php_version = PHP_VERSION;
        if (version_compare($php_version, '8.3.0', '<')) {
            if (version_compare($php_version, '8.2.0', '<')) {
                if (version_compare($php_version, '8.1.0', '<')) {
                    $checks[] = ['type' => 'error', 'msg' => "PHP version: {$php_version} - OUTDATED, upgrade to PHP 8.3+ for security and performance"];
                } else {
                    $checks[] = ['type' => 'warning', 'msg' => "PHP version: {$php_version} - Upgrade to PHP 8.3+ for latest features and performance"];
                }
            } else {
                $checks[] = ['type' => 'warning', 'msg' => "PHP version: {$php_version} - Consider upgrading to PHP 8.3+ for optimal performance"];
            }
        } else {
            $checks[] = ['type' => 'success', 'msg' => "PHP version: {$php_version} - LATEST"];
        }
        
        // Dangerous Functions
        $dangerous_functions = ['exec', 'shell_exec', 'system', 'passthru', 'eval'];
        $disabled_functions = array_map('trim', explode(',', ini_get('disable_functions')));
        
        foreach ($dangerous_functions as $func) {
            if (function_exists($func) && !in_array($func, $disabled_functions)) {
                $checks[] = ['type' => 'warning', 'msg' => "Dangerous function {$func}() is ENABLED"];
            } else {
                $checks[] = ['type' => 'success', 'msg' => "Dangerous function {$func}() is DISABLED"];
            }
        }
        
        // File Permissions
        $sensitive_dirs = [
            'app/etc' => 'Configuration directory',
            'var/log' => 'Log directory',
            'pub/media' => 'Media directory'
        ];
        
        foreach ($sensitive_dirs as $dir => $desc) {
            if (is_dir($dir)) {
                $perms = substr(sprintf('%o', fileperms($dir)), -4);
                if ($perms > '0755') {
                    $checks[] = ['type' => 'warning', 'msg' => "{$desc} permissions: {$perms} - too permissive"];
                } else {
                    $checks[] = ['type' => 'success', 'msg' => "{$desc} permissions: {$perms}"];
                }
            }
        }
        
        return $checks;
    }

    /**
     * Display checks in formatted HTML output for the performance console
     * 
     * This method takes an array of check results and formats them into HTML output
     * with appropriate CSS classes for visual styling. Each check result is displayed
     * with color-coded styling based on its severity level.
     *
     * @param string $title The section title to display above the checks
     * @param array $checks Array of check results, each containing 'type' and 'msg' keys
     *                     Expected format:
     *                     [
     *                         ['type' => 'success|error|warning|info', 'msg' => 'Check description'],
     *                         ['type' => 'error', 'msg' => 'Something is wrong'],
     *                         ...
     *                     ]
     * 
     * @return string Formatted HTML output ready for display in the console
     * 
     * @example Basic usage:
     *     ```php
     *     $checks = [
     *         ['type' => 'success', 'msg' => 'OPcache is enabled and working'],
     *         ['type' => 'warning', 'msg' => 'Memory usage is high (85%)'],
     *         ['type' => 'error', 'msg' => 'OPcache validation is enabled in production'],
     *         ['type' => 'info', 'msg' => 'Current hit rate: 96.5%']
     *     ];
     *     echo $block->displayChecks('OPcache Status:', $checks);
     *     ```
     * 
     * @example Advanced usage with dynamic checks:
     *     ```php
     *     // Get checks from another method
     *     $phpChecks = $this->checkPHPConfiguration();
     *     $opcacheChecks = $this->checkOPcacheConfiguration();
     *     $composerChecks = $this->checkComposerOptimization();
     *     
     *     // Display multiple sections
     *     echo $this->displayChecks('PHP Configuration:', $phpChecks);
     *     echo $this->displayChecks('OPcache Status:', $opcacheChecks);
     *     echo $this->displayChecks('Composer Optimization:', $composerChecks);
     *     ```
     * 
     * @example CSS Classes Applied:
     *     - 'test-result' for success type (green with checkmark ✓)
     *     - 'test-error' for error type (red with X ✗)
     *     - 'performance-warning' for warning type (yellow with warning ⚠)
     *     - 'performance-result' for info type (cyan with arrow →)
     * 
     * @example Output HTML Structure:
     *     ```html
     *     <div class='console-prompt'>PHP Configuration:</div>
     *     <div class='test-result'>✓ Memory limit: 2G</div>
     *     <div class='performance-warning'>⚠ WARNING: Max execution time: 30s. Consider 1800+ for deployments</div>
     *     <div class='test-error'>✗ ERROR: Extension curl: MISSING - cURL for API calls</div>
     *     <div class='performance-result'>→ Upload limits: 64M (upload), 64M (post)</div>
     *     ```
     * 
     * @see checkOPcacheConfiguration() For OPcache-specific checks
     * @see checkPHPConfiguration() For PHP configuration checks
     * @see checkComposerOptimization() For Composer autoloader checks
     * @see checkMagentoConfiguration() For Magento-specific checks
     * @see checkSecurity() For security configuration checks
     * @see checkServerConfiguration() For server environment checks
     * 
     * @since 1.0.0
     * @author Genaker OPcache Module
     */
    public function displayChecks(string $title, array $checks): string
    {
        $output = "<div class='console-prompt'>{$title}</div>";
        foreach ($checks as $check) {
            $class = '';
            switch ($check['type']) {
                case 'success':
                    $class = 'test-result';
                    break;
                case 'error':
                    $class = 'test-error';
                    break;
                case 'warning':
                    $class = 'performance-warning';
                    break;
                case 'info':
                default:
                    $class = 'performance-result';
                    break;
            }
            $output .= "<div class='{$class}'>{$check['msg']}</div>";
        }
        return $output;
    }
}
