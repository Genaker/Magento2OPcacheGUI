<?php
/**
 * Copyright ©  All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Genaker\Opcache\Controller\Adminhtml\Index;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;

class Standalone extends \Magento\Backend\App\Action implements HttpGetActionInterface
{
    private RawFactory $rawResultFactory;

    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        RawFactory $rawResultFactory
    ) {
        $this->rawResultFactory = $rawResultFactory;
        parent::__construct($context);
    }

    /**
     * Output standalone OPcache GUI
     */
    public function execute()
    {
        $result = $this->rawResultFactory->create();
        
        // Start output buffering to capture the OPcache GUI
        ob_start();
        
        try {
            // Create a minimal HTML wrapper for the OPcache GUI
            echo '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>OPcache GUI</title>
    <style>
        body { 
            margin: 0; 
            padding: 10px; 
            font-family: Arial, sans-serif; 
            background: #fff;
        }
        .opcache-wrapper {
            width: 100%;
            height: 100vh;
            overflow: auto;
        }
        li.nav-tab.nav-tab-link-realtime {
            display: none !important;
        }
    </style>
</head>
<body>
    <div class="opcache-wrapper">';
            
            // Include the OPcache GUI
            $opcacheGuiPath = BP . '/vendor/amnuts/opcache-gui/index.php';
            if (file_exists($opcacheGuiPath)) {
                require_once $opcacheGuiPath;
            } else {
                echo '<p style="color: red;">OPcache GUI not found. Please install with: composer require amnuts/opcache-gui</p>';
            }
            
            echo '    </div>
</body>
</html>';
            
        } catch (\Exception $e) {
            echo '<p style="color: red;">Error loading OPcache GUI: ' . htmlspecialchars($e->getMessage()) . '</p>';
        }
        
        $content = ob_get_clean();
        
        $result->setHeader('Content-Type', 'text/html');
        $result->setContents($content);
        
        return $result;
    }
}
