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
        \Magento\Customer\Model\AddressFactory $addressFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Catalog\Model\Product $product,
        \Magento\Framework\Data\Form\FormKey $formkey,
        \Magento\Quote\Model\QuoteFactory $quote,
        \Magento\Quote\Model\QuoteManagement $quoteManagement,
        \Magento\Customer\Model\CustomerFactory $customerFactory,
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository,
        \Magento\Sales\Model\Service\OrderService $orderService,
        \Magento\Catalog\Model\ProductRepository $productRepository,
        array $data = []
    )
    {
        $this->_orderCollectionFactory = $orderCollectionFactory;
        $this->orderFactory = $orderFactory;
        $this->_registry = $registry;
        $this->_storeManager = $context->getStoreManager();
        $this->localeCurrency = $localeCurrency;
        $this->_customer = $customers;
        $this->addressFactory = $addressFactory;
        $this->_product = $product;
        $this->_formkey = $formkey;
        $this->quote = $quote;
        $this->quoteManagement = $quoteManagement;
        $this->customerFactory = $customerFactory;
        $this->customerRepository = $customerRepository;
        $this->orderService = $orderService;
        $this->_productRepository = $productRepository;
        // $this->createMageOrder();
        parent::__construct($context, $data);
    }

    //Hàm lấy toàn bộ dữ liệu về orders từ database
    public function getOrders()
    {
        $orderCollection = $this->_orderCollectionFactory->create();
        return $orderCollection;
    }

    // Hàm lấy ra 4 orders mới nhất từ database
    public function getLastOrders()
    {
        $orderCollection = $this->getOrders()->setOrder('entity_id', 'DESC')->setPageSize(4);
        return $orderCollection;
    }

    // Hàm xử lý lọc dữ liệu về order từ database theo khoảng thời gian được gửi theo request thông qua parameter reportrange
    // Nếu không có khoảng thời gian được gửi theo request thì sẽ mặc định khoảng thời gian là 7 ngày
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
                    $date = date_create($dateTo);
                    date_add($date, date_interval_create_from_date_string(''.-$i.' days'));
                    $orders = $this->getOrders()->addFieldToFilter('created_at', array('like' => date_format($date, 'Y-m-d') . '%'));
                    if (count($orders)) {
                        $grandTotal = 0;
                        foreach($orders as $order) {
                            $grandTotal += $order->getData('grand_total');
                        }
                        array_push($data, [date_timestamp_get($date)*1000, $grandTotal]);
                    } else {
                         array_push($data, [date_timestamp_get($date)*1000, 0]);
                    }
                }
            }
            
        } else {
            $dateTo = date('Y-m-d');
            $datediff = 7;
            for($i=$datediff;$i>=0;$i--){
                $date = date_create($dateTo);
                date_add($date, date_interval_create_from_date_string(''.-$i.' days'));
                $orders = $this->getOrders()->addFieldToFilter('created_at', array('like' => date_format($date, 'Y-m-d') . '%'));
                if (count($orders)) {
                    $grandTotal = 0;
                    foreach($orders as $order) {
                        $grandTotal += $order->getData('grand_total');
                    }
                    array_push($data, [date_timestamp_get($date)*1000, $grandTotal]);
                } else {
                    array_push($data, [date_timestamp_get($date)*1000, 0]);
                }
            }
        }

        return $data;
    }

    // Hàm xử lý lọc dữ liệu về order theo ids từ database theo khoảng thời gian được gửi theo request thông qua parameter reportrange
    // Nếu không có khoảng thời gian được gửi theo request thì sẽ mặc định khoảng thời gian là 7 ngày
    public function getOrderDataByCategories()
    {
        $categoryId1 = 20;
        $categoryId2 = 17;
        $categoryId3 = 3;
        $categoryId4 = 9;
        $data = array();
        $data['cat1'] = [];
        $data['cat2'] = [];
        $data['cat3'] = [];
        $data['cat4'] = [];
        $reportrange = $this->getRequest()->getParam('reportrange');
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        if ($reportrange) {
            $times = explode("-",$reportrange);

            if (isset($times[0]) && isset($times[1])) {
                $dateFrom = date('Y-m-d', strtotime($times[0]));
                $dateTo   = date('Y-m-d', strtotime($times[1]));
                $datediff = date_diff(date_create($dateFrom), date_create($dateTo))->format('%a');

                for($i=$datediff;$i>=0;$i--){
                    $date = date_create($dateTo);
                    date_add($date, date_interval_create_from_date_string(''.-$i.' days'));
                    $orders = $this->getOrders()->addFieldToFilter('created_at', array('like' => date_format($date, 'Y-m-d') . '%'));
                    if (count($orders)) {
                        $cat1GrandTotal = 0;
                        $cat2GrandTotal = 0;
                        $cat3GrandTotal = 0;
                        $cat4GrandTotal = 0;
                        foreach($orders as $order) {
                            $orderItems = $order->getAllItems();
                            $cat1RowTotal = 0;
                            $cat2RowTotal = 0;
                            $cat3RowTotal = 0;
                            $cat4RowTotal = 0;
                            foreach ($orderItems as $item) {
                                $product = $this->_productRepository->getById($item->getProductId());
                                $categoryIds = $product->getCategoryIds();
                                if (count($categoryIds)) {
                                    $cat = $categoryIds[0];
                                } else {
                                    $cat = 0;
                                }

                                switch ($cat) {
                                    case $categoryId1:
                                        $cat1RowTotal += $item->getData('row_total');
                                        break;
                                    case $categoryId2:
                                        $cat2RowTotal += $item->getData('row_total');
                                        break;
                                    case $categoryId3:
                                        $cat3RowTotal += $item->getData('row_total');
                                        break;
                                    case $categoryId4:
                                        $cat4RowTotal += $item->getData('row_total');
                                        break;
                                    default:
                                        break;
                                }
                            }
                            $cat1GrandTotal += $cat1RowTotal;
                            $cat2GrandTotal += $cat2RowTotal;
                            $cat3GrandTotal += $cat3RowTotal;
                            $cat4GrandTotal += $cat4RowTotal;
                        }
                        array_push($data['cat1'], [date_timestamp_get($date)*1000, $cat1GrandTotal]);
                        array_push($data['cat2'], [date_timestamp_get($date)*1000, $cat2GrandTotal]);
                        array_push($data['cat3'], [date_timestamp_get($date)*1000, $cat3GrandTotal]);
                        array_push($data['cat4'], [date_timestamp_get($date)*1000, $cat4GrandTotal]);
                    } else {
                        array_push($data['cat1'], [date_timestamp_get($date)*1000, 0]);
                        array_push($data['cat2'], [date_timestamp_get($date)*1000, 0]);
                        array_push($data['cat3'], [date_timestamp_get($date)*1000, 0]);
                        array_push($data['cat4'], [date_timestamp_get($date)*1000, 0]);
                    }
                }
            }
            
        } else {
            $dateTo = date('Y-m-d');
            $datediff = 7;
            for($i=$datediff;$i>=0;$i--){
                $date = date_create($dateTo);
                date_add($date, date_interval_create_from_date_string(''.-$i.' days'));
                $orders = $this->getOrders()->addFieldToFilter('created_at', array('like' => date_format($date, 'Y-m-d') . '%'));
                if (count($orders)) {
                    $cat1GrandTotal = 0;
                    $cat2GrandTotal = 0;
                    $cat3GrandTotal = 0;
                    $cat4GrandTotal = 0;
                    foreach($orders as $order) {
                        $orderItems = $order->getAllItems();
                        $cat1RowTotal = 0;
                        $cat2RowTotal = 0;
                        $cat3RowTotal = 0;
                        $cat4RowTotal = 0;
                        foreach ($orderItems as $item) {
                            $product = $this->_productRepository->getById($item->getProductId());
                            $categoryIds = $product->getCategoryIds();
                            if (count($categoryIds)) {
                                $cat = $categoryIds[0];
                            } else {
                                $cat = 0;
                            }

                            switch ($cat) {
                                case $categoryId1:
                                    $cat1RowTotal += $item->getData('row_total');
                                    break;
                                case $categoryId2:
                                    $cat2RowTotal += $item->getData('row_total');
                                    break;
                                case $categoryId3:
                                    $cat3RowTotal += $item->getData('row_total');
                                    break;
                                case $categoryId4:
                                    $cat4RowTotal += $item->getData('row_total');
                                    break;
                                default:
                                    break;
                            }
                        }
                        $cat1GrandTotal += $cat1RowTotal;
                        $cat2GrandTotal += $cat2RowTotal;
                        $cat3GrandTotal += $cat3RowTotal;
                        $cat4GrandTotal += $cat4RowTotal;
                    }
                    array_push($data['cat1'], [date_timestamp_get($date)*1000, $cat1GrandTotal]);
                    array_push($data['cat2'], [date_timestamp_get($date)*1000, $cat2GrandTotal]);
                    array_push($data['cat3'], [date_timestamp_get($date)*1000, $cat3GrandTotal]);
                    array_push($data['cat4'], [date_timestamp_get($date)*1000, $cat4GrandTotal]);
                } else {
                    array_push($data['cat1'], [date_timestamp_get($date)*1000, 0]);
                    array_push($data['cat2'], [date_timestamp_get($date)*1000, 0]);
                    array_push($data['cat3'], [date_timestamp_get($date)*1000, 0]);
                    array_push($data['cat4'], [date_timestamp_get($date)*1000, 0]);
                }
            }
        }

        return $data;
    }

    // Hàm lấy tổng doanh thu là tổng grand_total của toàn bộ orders lấy từ database
    public function getLifetimeSales()
    {
        $order = $this->getOrders();
        $lifetimeSales = 0;
        foreach ($order as $item) {
            $lifetimeSales = $lifetimeSales + $item['grand_total'];
        }

        return $lifetimeSales;
    }

    // Hàm tính doanh thu trung bình = tổng base_grand_total của toàn bộ orders lấy từ database (chia cho) / số lượng order count($order)
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

    // Hàm lấy định dạng tiền tệ để hiển thị lên tầng view (ở đây là dollar $)
    public function getCurrencySymbol()
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $currencyCode = $this->_storeManager->getStore()->getCurrentCurrency()->getCode();

        $currency = $objectManager->create('Magento\Directory\Model\CurrencyFactory')->create()->load($currencyCode);
        return $currencySymbol = $currency->getCurrencySymbol();
    }

    // Hàm lấy toàn bộ dữ liệu về tài khoản khách hàng (customer) trong database
    protected function getCustomerCollection() {
        return $this->_customer->getCollection()
               ->addAttributeToSelect("*")
               ->load();
    }
    
    // hàm tính toán số lượng tài khoản theo giới tính
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

    // hàm tính toán số lượng khách theo độ tuổi
    public function getCustomerAge() {
        $customers = $this->getCustomerCollection();
        $customerAge = [
            '1-20' => 0, 
            '21-30' => 0,
            '31-50' => 0,
            '51->' => 0
        ];
        $today = date("Y-m-d");

        if (count($customers)) {
            foreach($customers as $customer) {
                $dateOfBirth = $customer->getData('dob');
                $age = date_diff(date_create($dateOfBirth), date_create($today))->format('%y');
                if ($age < 20) {
                    $customerAge['1-20'] += 1;
                } elseif ($age > 20 && $age < 31) {
                    $customerAge['21-30'] += 1;
                } elseif ($age > 30 && $age < 51 ) {
                    $customerAge['31-50'] += 1;
                } else {
                    $customerAge['50->'] += 1;
                }
            }
        }

        return array_values($customerAge);
    }

    public function getCustomerAddress() {
        $customers = $this->getCustomerCollection();
        $customerAddress = [];
        
        if (count($customers)) {
            foreach($customers as $customer) {
                $billingAddressId = $customer->getDefaultBilling();
                $billingAddress = $this->addressFactory->create()->load($billingAddressId);
                $customerAddress[] = strtolower($billingAddress->getData('city'));
            }
        }

        return $customerAddress;
    }

    public function createMageOrder() {
        $orderData=[
             'currency_id'  => 'USD',
             'email'        => 'tom_rainer@gmail.com', //buyer email id
             'shipping_address' =>[
                    'firstname'    => 'John', //address Details
                    'lastname'     => 'Doe',
                            'street' => '123 Demo',
                            'city' => 'Mageplaza',
                    'country_id' => 'US',
                    'region' => 'xxx',
                    'region_id' => 1,
                    'postcode' => '10019',
                    'telephone' => '0123456789',
                    'fax' => '32423',
                    'save_in_address_book' => 1
                         ],
           'items'=> [
                      ['product_id'=>'725','qty'=>1],
                      ['product_id'=>'711','qty'=>1]
                    ]
        ];

        $store=$this->_storeManager->getStore();
        $websiteId = $this->_storeManager->getStore()->getWebsiteId();
        $customer=$this->customerFactory->create();
        $customer->setWebsiteId($websiteId);
        $customer->loadByEmail($orderData['email']);// load customet by email address
        if(!$customer->getEntityId()){
            //If not avilable then create this customer 
            $customer->setWebsiteId($websiteId)
                    ->setStore($store)
                    ->setFirstname($orderData['shipping_address']['firstname'])
                    ->setLastname($orderData['shipping_address']['lastname'])
                    ->setEmail($orderData['email']) 
                    ->setPassword($orderData['email']);
            $customer->save();
        }
        $quote=$this->quote->create(); //Create object of quote
        $quote->setStore($store); //set store for which you create quote
        // if you have allready buyer id then you can load customer directly 
        $customer= $this->customerRepository->getById($customer->getEntityId());
        $quote->setCurrency();
        $quote->assignCustomer($customer); //Assign quote to customer
 
        //add items in quote
        foreach($orderData['items'] as $item){
            $product=$this->_product->load($item['product_id']);
            $product->setPrice($product['price']);
            $quote->addProduct(
                $product,
                intval($item['qty'])
            );
        }
 
        //Set Address to quote
        $quote->getBillingAddress()->addData($orderData['shipping_address']);
        $quote->getShippingAddress()->addData($orderData['shipping_address']);
 
        // Collect Rates and Set Shipping & Payment Method
 
        $shippingAddress=$quote->getShippingAddress();
        $shippingAddress->setCollectShippingRates(true)
                        ->collectShippingRates()
                        ->setShippingMethod('freeshipping_freeshipping'); //shipping method
        $quote->setPaymentMethod('checkmo'); //payment method
        $quote->setInventoryProcessed(false); //not effetc inventory
        $quote->save(); //Now Save quote and your quote is ready
 
        // Set Sales Order Payment
        $quote->getPayment()->importData(['method' => 'checkmo']);
 
        // Collect Totals & Save Quote
        $quote->collectTotals()->save();
 
        // Create Order From Quote
        $order = $this->quoteManagement->submit($quote);
        
        $order->setEmailSent(0);
        $increment_id = $order->getRealOrderId();
        if($order->getEntityId()){
            $result['order_id']= $order->getRealOrderId();
        }else{
            $result=['error'=>1,'msg'=>'Your custom message'];
        }
        return $result;
    }
}
