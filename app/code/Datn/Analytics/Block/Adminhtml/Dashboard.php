<?php

namespace Datn\Analytics\Block\Adminhtml;

class Dashboard extends \Magento\Backend\Block\Template
{
    /**
     * @var string
     */
    protected $_template = 'Datn_Analytics::dashboard/index.phtml';

    /**
     * Reward constructor.
     * @param \Magento\Framework\App\Action\Context $context
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Locale\CurrencyInterface $localeCurrency,
        \Magento\Customer\Model\Customer $customers,
        array $data = []
    )
    {
        $this->_orderCollectionFactory = $orderCollectionFactory;
        $this->orderFactory = $orderFactory;
        $this->_registry = $registry;
        $this->_storeManager = $context->getStoreManager();
        $this->localeCurrency = $localeCurrency;
        $this->_customer = $customers;
        parent::__construct($context, $data);
    }

    public function getOrders()
    {
        $orderCollection = $this->_orderCollectionFactory->create();
        return $orderCollection;
    }

    public function getLastOrders()
    {
        $orderCollection = $this->getOrders()->setOrder('entity_id', 'DESC')->setPageSize(5);
        return $orderCollection;
    }

    public function getOrderData()
    {
        $data = array();
        $reportrange = $this->getRequest()->getParam('reportrange');
        if ($reportrange) {
            $times = explode("-",$reportrange);

            if (isset($times[0]) && isset($times[1])) {
                $dateFrom = date('Y-m-d', strtotime($times[0]));
                $dateTo   = date('Y-m-d', strtotime($times[1]));
                $datediff = date_diff(date_create($dateFrom), date_create($dateTo))->format('%a');

                for($i=$datediff;$i>=0;$i--){
                    $order = $this->getOrders();
                    $date = date_create($dateTo);
                    date_add($date, date_interval_create_from_date_string(''.-$i.' days'));

                    array_push($data, [date_timestamp_get($date)*1000, count($order->addFieldToFilter('created_at', array('like' => date_format($date, 'Y-m-d') . '%')))]);
                }
            }
            
        } else {
            $dateTo = date('Y-m-d');
            $datediff = 7;
            for($i=$datediff;$i>=0;$i--){
                $order = $this->getOrders();
                $date = date_create($dateTo);
                date_add($date, date_interval_create_from_date_string(''.-$i.' days'));

                array_push($data, [date_timestamp_get($date)*1000, count($order->addFieldToFilter('created_at', array('like' => date_format($date, 'Y-m-d') . '%')))]);
            }
        }

        return $data;
    }

    public function getLifetimeSales()
    {
        $order = $this->getOrders();
        $lifetimeSales = 0;
        foreach ($order as $item) {
            $lifetimeSales = $lifetimeSales + $item['grand_total'];
        }

        return $lifetimeSales;
    }

    public function getAverageOrder()
    {
        $order = $this->getOrders();

        $totalOrder = 0;
        $averageOrder = 0;
        foreach ($order as $item) {
            $totalOrder = $totalOrder + $item['base_grand_total'];
        }
        if (count($order)) {
            $averageOrder = $totalOrder / count($order);
        }

        return $averageOrder;
    }

    public function getCurrencySymbol()
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $currencyCode = $this->_storeManager->getStore()->getCurrentCurrency()->getCode();

        $currency = $objectManager->create('Magento\Directory\Model\CurrencyFactory')->create()->load($currencyCode);
        return $currencySymbol = $currency->getCurrencySymbol();
    }

    protected function getCustomerCollection() {
        return $this->_customer->getCollection()
               ->addAttributeToSelect("*")
               ->load();
    }
    
    public function getCustomerGenderData() {
        $customers = $this->getCustomerCollection();
        $customerGender = [
            'Male' => 0, 
            'Female' => 0,
            'Not Specified' => 0
        ];

        if (count($customers)) {
            foreach($customers as $customer) {
                if ($customer->getGender() == 1) {
                    $customerGender['Male'] += 1;
                } elseif ($customer->getGender() == 2) {
                    $customerGender['Female'] += 1;
                } else {
                    $customerGender['Not Specified'] += 1;
                }
            }
        }

        return array_values($customerGender);
    }
}
