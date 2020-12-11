<?php
/**
 * Copyright ©  All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Genaker\Opcache\Block\Adminhtml\Index;

use Magento\Backend\Model\UrlInterface;

class Index extends \Magento\Backend\Block\Template
{

    protected $backendUrl;
    
    /**
     * Constructor
     *
     * @param \Magento\Backend\Block\Template\Context  $context
     * @param array $data
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        UrlInterface $backendUrl,
        array $data = []
    ) {
        $this->backendUrl = $backendUrl;
        $this->setData('gui_url', $this->backendUrl->getUrl('opcache_gui/index/gui'));
        parent::__construct($context, $data);
    }
}

