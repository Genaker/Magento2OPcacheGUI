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
}
