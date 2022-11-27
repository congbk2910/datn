<?php
namespace Magento\CatalogInventory\Model\Indexer\ProductPriceIndexFilter;

/**
 * Interceptor class for @see \Magento\CatalogInventory\Model\Indexer\ProductPriceIndexFilter
 */
class Interceptor extends \Magento\CatalogInventory\Model\Indexer\ProductPriceIndexFilter implements \Magento\Framework\Interception\InterceptorInterface
{
    use \Magento\Framework\Interception\Interceptor;

    public function __construct(\Magento\CatalogInventory\Api\StockConfigurationInterface $stockConfiguration, \Magento\CatalogInventory\Model\ResourceModel\Stock\Item $stockItem, ?\Magento\Framework\App\ResourceConnection $resourceConnection = null, $connectionName = 'indexer')
    {
        $this->___init();
        parent::__construct($stockConfiguration, $stockItem, $resourceConnection, $connectionName);
    }

    /**
     * {@inheritdoc}
     */
    public function modifyPrice(\Magento\Catalog\Model\ResourceModel\Product\Indexer\Price\IndexTableStructure $priceTable, array $entityIds = []) : void
    {
        $pluginInfo = $this->pluginList->getNext($this->subjectType, 'modifyPrice');
        if (!$pluginInfo) {
            parent::modifyPrice($priceTable, $entityIds);
        } else {
            $this->___callPlugins('modifyPrice', func_get_args(), $pluginInfo);
        }
    }
}
