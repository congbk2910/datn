<?php
namespace Datn\Analytics\Controller\Index;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ResponseInterface;

class Exportxls extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \Magento\Framework\App\Response\Http\FileFactory
     */
    protected $fileFactory;

    /**
     * @var \Magento\Catalog\Model\ProductFactory
     */
    protected $productFactory;

    /**
     * @var \Magento\Framework\View\Result\LayoutFactory
     */
    protected $resultLayoutFactory;

    /**
     * @var \Magento\Framework\File\Csv
     */
    protected $csvProcessor;

    /**
     * @var \Magento\Framework\App\Filesystem\DirectoryList
     */
    protected $directoryList;

    /**
     * @param \Magento\Framework\App\Action\Context            $context
     * @param \Magento\Framework\App\Response\Http\FileFactory $fileFactory
     * @param \Magento\Catalog\Model\ProductFactory            $productFactory
     * @param \Magento\Framework\View\Result\LayoutFactory     $resultLayoutFactory
     * @param \Magento\Framework\File\Csv                      $csvProcessor
     * @param \Magento\Framework\App\Filesystem\DirectoryList  $directoryList
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\App\Response\Http\FileFactory $fileFactory,
        \Magento\Catalog\Model\ProductFactory $productFactory,
        \Magento\Framework\View\Result\LayoutFactory $resultLayoutFactory,
        \Magento\Framework\File\Csv $csvProcessor,
        \Magento\Framework\App\Filesystem\DirectoryList $directoryList,
        \Magento\Customer\Model\Customer $customers,
        \Magento\Customer\Model\AddressFactory $addressFactory,
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory
    ) {
        $this->fileFactory = $fileFactory;
        $this->productFactory = $productFactory;
        $this->resultLayoutFactory = $resultLayoutFactory;
        $this->csvProcessor = $csvProcessor;
        $this->directoryList = $directoryList;
        $this->_customer = $customers;
        $this->addressFactory = $addressFactory;
        $this->_orderCollectionFactory = $orderCollectionFactory;
        parent::__construct($context);
    }

    /**
     * CSV Create and Download
     *
     * @return ResponseInterface
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    public function execute()
    {
    	$type = $this->getRequest()->getParam('type');
    	switch ($type) {
			case 'address':
				// Xuất file theo địa chỉ khách hàng
				$this->exportByAddress();
				break;
			case 'year':
				// Xuất file theo năm
				$this->exportByYear();
				break;
			case 'year2':
				// Xuất file theo năm kiểu 2
				$this->exportByYearSecond();
				break;
			case 'month':
				// Xuất file theo tháng
				$this->exportByMonth();
				break;
			case 'customer':
				// Xuất file theo khách hàng
				$this->exportByCustomer();
				break;
			default:
				$this->exportByAddress();
				break;
    	}
    }

    function exportByAddress() {
    	/** Add yout header name here */
        $content[] = [
            'city_id' => 'Tinh',
            'number_customer_first_buy' => 'So luong khach mua lan dau',
            'order_number' => 'So luong don hang',
            'order_total' => 'Gia tri cac don hang',
            'customer_total' => 'Tong so luong khach hang'
        ];

        $resultLayout = $this->resultLayoutFactory->create();
        $customerAddress = $this->getCustomerAddress();
        $fileName = 'bao-cao-theo-dia-chi.csv'; // Add Your CSV File name
        $filePath =  $this->directoryList->getPath(DirectoryList::MEDIA) . "/" . $fileName;
        $orders = $this->getOrders();
        $cscustomerAddress = [];
        foreach ($orders as $order) {
        	$customer = $this->_customer->load($order->getCustomerId());
        	$csbillingAddressId = $customer->getDefaultBilling();
            $csbillingAddress = $this->addressFactory->create()->load($csbillingAddressId);

            if (isset($cscustomerAddress[$csbillingAddress->getData('city')]) && isset($cscustomerAddress[$csbillingAddress->getData('city')]['order_number'])) {
            	$cscustomerAddress[$csbillingAddress->getData('city')]['order_number'] += 1;
            	$cscustomerAddress[$csbillingAddress->getData('city')]['order_total'] += $order->getData('grand_total');
            } else {
            	$cscustomerAddress[$csbillingAddress->getData('city')]['order_number'] = 1;
            	$cscustomerAddress[$csbillingAddress->getData('city')]['order_total'] = $order->getData('grand_total');
            }

        	$cscustomerAddress[$csbillingAddress->getData('city')]['customer_ids'][] = $order->getCustomerId() ? $order->getCustomerId() : '0';
        }


        foreach($customerAddress as $address) {
            if (!isset($cscustomerAddress[$address['id']]) || !isset($cscustomerAddress[$address['id']]['customer_ids']) ) {
                continue;
            }
        	$arrayCountValues = array_count_values($cscustomerAddress[$address['id']]['customer_ids']);
        	$number_customer_first_buy = array();
			foreach ($arrayCountValues as $key=>$value){
			    if ($value < 2){
			        $number_customer_first_buy[] = $key;
			    }
			}
        	$content[] = [
        		$address['id'],
        		count($number_customer_first_buy),
        		$cscustomerAddress[$address['id']]['order_number'],
        		$cscustomerAddress[$address['id']]['order_total'],
        		$address['number']
        	];
        }

        $this->csvProcessor->setEnclosure('"')->setDelimiter(',')->saveData($filePath, $content);
        return $this->fileFactory->create(
            $fileName,
            [
                'type'  => "filename",
                'value' => $fileName,
                'rm'    => true,
            ],
            DirectoryList::MEDIA,
            'text/csv',
            'charset=UTF-8',
            null
        );
    }

    function exportByYear() {
        $content[] = [
            'month' => 'Thang',
            'number_customer_first_buy' => 'So luong khach mua lan dau',
            'number_customer_next_buy' => 'So luong khach cu quay lai',
            'customer_total' => 'Tong so luong khach hang'
        ];

        $resultLayout = $this->resultLayoutFactory->create();
        $customerAddress = $this->getCustomerAddress();
        $fileName = 'bao-cao-theo-nam.csv'; // Add Your CSV File name
        $filePath =  $this->directoryList->getPath(DirectoryList::MEDIA) . "/" . $fileName;
        $orders = $this->getOrders();

        for ($i = 0; $i < 12; $i++) {
		    $months[] = date("m/Y", strtotime( date( 'Y-m-01' )." -$i months"));
		}


        $cscustomerByYear = [];
        foreach ($orders as $order) {
        	$createdAt = date('m/Y',strtotime($order->getData('created_at')));
        	$cscustomerByYear[$createdAt]['customer_ids'][] = $order->getCustomerId() ? $order->getCustomerId() : '0';
        }

        foreach($months as $month) {
        	$number_customer_first_buy = array();
        	$number_customer_next_buy = array();
        	$total_customer = array();

        	if (isset($cscustomerByYear[$month]) && isset($cscustomerByYear[$month]['customer_ids'])) {
        		$arrayCountValues = array_count_values($cscustomerByYear[$month]['customer_ids']);
				foreach ($arrayCountValues as $key=>$value){
				    if ($value < 2){
				        $number_customer_first_buy[] = $key;
				    }
				}

        		$total_customer = array_unique($cscustomerByYear[$month]['customer_ids']);
        	}

        	$content[] = [
        		$month,
        		count($number_customer_first_buy),
        		count($total_customer) - count($number_customer_first_buy),
        		count($total_customer),
        	];
        }

        $this->csvProcessor->setEnclosure('"')->setDelimiter(',')->saveData($filePath, $content);
        return $this->fileFactory->create(
            $fileName,
            [
                'type'  => "filename",
                'value' => $fileName,
                'rm'    => true,
            ],
            DirectoryList::MEDIA,
            'text/csv',
            null
        );
    }

    function exportByYearSecond() {
        $content[] = [
            'month' => 'Thang',
            'total_item_count' => 'So luong hang ban ra',
            'number_customer' => 'So luong khach hang',
            'tax_amount' => 'Tien thue',
            'grand_total' => 'Doanh thu',
            'average_order_value' => 'TB gia tri don hang'
        ];

        $resultLayout = $this->resultLayoutFactory->create();
        $customerAddress = $this->getCustomerAddress();
        $fileName = 'bao-cao-theo-nam-kieu-2.csv'; // Add Your CSV File name
        $filePath =  $this->directoryList->getPath(DirectoryList::MEDIA) . "/" . $fileName;
        $orders = $this->getOrders();

        for ($i = 0; $i < 12; $i++) {
		    $months[] = date("m/Y", strtotime( date( '2023-1-01' )." -$i months"));
		}


        $cscustomerByYear = [];
        foreach ($orders as $order) {
        	$createdAt = date('m/Y',strtotime($order->getData('created_at')));
        	$cscustomerByYear[$createdAt]['customer_ids'][] = $order->getCustomerId();

        	if (isset($cscustomerByYear[$createdAt]) && isset($cscustomerByYear[$createdAt]['total_item_count'])) {
        		$cscustomerByYear[$createdAt]['total_item_count'] += $order->getData('total_item_count');
        	} else {
        		$cscustomerByYear[$createdAt]['total_item_count'] = $order->getData('total_item_count');
        	}

        	if (isset($cscustomerByYear[$createdAt]) && isset($cscustomerByYear[$createdAt]['tax_amount'])) {
        		$cscustomerByYear[$createdAt]['tax_amount'] += $order->getData('tax_amount');
        	} else {
        		$cscustomerByYear[$createdAt]['tax_amount'] = $order->getData('tax_amount');
        	}

        	if (isset($cscustomerByYear[$createdAt]) && isset($cscustomerByYear[$createdAt]['grand_total'])) {
        		$cscustomerByYear[$createdAt]['grand_total'] += $order->getData('grand_total');
        	} else {
        		$cscustomerByYear[$createdAt]['grand_total'] = $order->getData('grand_total');
        	}

        	if (isset($cscustomerByYear[$createdAt]) && isset($cscustomerByYear[$createdAt]['number_order'])) {
        		$cscustomerByYear[$createdAt]['number_order'] += 1;
        	} else {
        		$cscustomerByYear[$createdAt]['number_order'] = 1;
        	}
        }

        foreach($months as $month) {
        	$total_customer = array();
        	$total_item_count = 0;
        	$tax_amount = 0;
        	$grand_total = 0;
        	$average_order_value = 0;

        	if (isset($cscustomerByYear[$month]) && isset($cscustomerByYear[$month]['customer_ids'])) {
        		$total_customer = array_unique($cscustomerByYear[$month]['customer_ids']);
        	}

        	if (isset($cscustomerByYear[$month]) && isset($cscustomerByYear[$month]['total_item_count'])) {
        		$total_item_count = $cscustomerByYear[$month]['total_item_count'];
        	}

        	if (isset($cscustomerByYear[$month]) && isset($cscustomerByYear[$month]['tax_amount'])) {
        		$tax_amount = $cscustomerByYear[$month]['tax_amount'];
        	}

        	if (isset($cscustomerByYear[$month]) && isset($cscustomerByYear[$month]['grand_total'])) {
        		$grand_total = $cscustomerByYear[$month]['grand_total'];
        	}

        	if (isset($cscustomerByYear[$month]) && isset($cscustomerByYear[$month]['number_order']) && $cscustomerByYear[$month]['number_order']) {
        		$average_order_value = $grand_total / $cscustomerByYear[$month]['number_order'];
        	}

        	$content[] = [
        		$month,
        		$total_item_count,
        		count($total_customer),
        		$tax_amount,
        		$grand_total,
        		$average_order_value,
        	];
        }

        $this->csvProcessor->setEnclosure('"')->setDelimiter(',')->saveData($filePath, $content);
        return $this->fileFactory->create(
            $fileName,
            [
                'type'  => "filename",
                'value' => $fileName,
                'rm'    => true,
            ],
            DirectoryList::MEDIA,
            'text/csv',
            null
        );
    }

    function exportByMonth() {
        $content[] = [
            'day' => 'Ngay',
            'number_order' => 'So luong don hang',
            'shipping_fee' => 'Phi giao hang',
            'total_order' => 'Doanh thu',
        ];

        $resultLayout = $this->resultLayoutFactory->create();
        $customerAddress = $this->getCustomerAddress();
        $fileName = 'bao-cao-theo-thang.csv'; // Add Your CSV File name
        $filePath =  $this->directoryList->getPath(DirectoryList::MEDIA) . "/" . $fileName;
        $orders = $this->getOrders();

        $currentDay = intval(date('d'));

        for ($i = 0; $i < $currentDay; $i++) {
		   $days[] = date("d/m/Y", strtotime( date( 'Y-m-'.$currentDay )." -$i days"));
		}

        $cscustomerByMonth = [];
        foreach ($orders as $order) {
        	$createdAt = date('d/m/Y',strtotime($order->getData('created_at')));
        	if (isset($cscustomerByMonth[$createdAt]) && isset($cscustomerByMonth[$createdAt]['number_order'])) {
        		$cscustomerByMonth[$createdAt]['number_order'] += 1;
        	} else {
        		$cscustomerByMonth[$createdAt]['number_order'] = 1;
        	}

        	if (isset($cscustomerByMonth[$createdAt]) && isset($cscustomerByMonth[$createdAt]['shipping_fee'])) {
        		$cscustomerByMonth[$createdAt]['shipping_fee'] += $order->getData('shipping_amount'); 
        	} else {
        		$cscustomerByMonth[$createdAt]['shipping_fee'] = $order->getData('shipping_amount');
        	}

        	if (isset($cscustomerByMonth[$createdAt]) && isset($cscustomerByMonth[$createdAt]['total_order'])) {
        		$cscustomerByMonth[$createdAt]['total_order'] += $order->getData('grand_total'); 
        	} else {
        		$cscustomerByMonth[$createdAt]['total_order'] = $order->getData('grand_total');
        	}
        }

        foreach($days as $day) {
        	$content[] = [
        		$day,
        		isset($cscustomerByMonth[$day]) ? $cscustomerByMonth[$day]['number_order'] : 0,
        		isset($cscustomerByMonth[$day]) ? $cscustomerByMonth[$day]['shipping_fee'] : 0,
        		isset($cscustomerByMonth[$day]) ? $cscustomerByMonth[$day]['total_order'] : 0
        	];
        }

        $this->csvProcessor->setEnclosure('"')->setDelimiter(',')->saveData($filePath, $content);
        return $this->fileFactory->create(
            $fileName,
            [
                'type'  => "filename",
                'value' => $fileName,
                'rm'    => true,
            ],
            DirectoryList::MEDIA,
            'text/csv',
            null
        );
    }

    function exportByCustomer() {
    	/** Add yout header name here */
        $content[] = [
            'name' => 'Ten khach hang',
            'email' => 'Email khach hang',
            'phone' => 'SDT khach hang',
            'total_item_count' => 'So luong ban ra',
            'grand_total' => 'Doanh thu'
        ];

        $resultLayout = $this->resultLayoutFactory->create();
        $customerAddress = $this->getCustomerAddress();
        $fileName = 'bao-cao-theo-khac-hang.csv'; // Add Your CSV File name
        $filePath =  $this->directoryList->getPath(DirectoryList::MEDIA) . "/" . $fileName;
        $orders = $this->getOrders();
        $cscustomerAddress = [];

        foreach ($orders as $order) {
        	$customer = $this->_customer->load($order->getCustomerId());
        	$csbillingAddressId = $customer->getDefaultBilling();
            $csbillingAddress = $this->addressFactory->create()->load($csbillingAddressId);

            $cscustomerAddress[$order->getCustomerId()]['id'] = $order->getCustomerId();
            $cscustomerAddress[$order->getCustomerId()]['name'] = $customer->getFirstname() . ' ' . $customer->getMiddlename() . ' ' . $customer->getLastname();
            $cscustomerAddress[$order->getCustomerId()]['email'] = $customer->getEmail();
            $cscustomerAddress[$order->getCustomerId()]['phone'] = $csbillingAddress->getTelephone();

            if (isset($cscustomerAddress[$order->getCustomerId()]) && isset($cscustomerAddress[$order->getCustomerId()]['grand_total'])) {
            	$cscustomerAddress[$order->getCustomerId()]['grand_total'] += $order->getData('grand_total');
            } else {
            	$cscustomerAddress[$order->getCustomerId()]['grand_total'] = $order->getData('grand_total');
            }

            if (isset($cscustomerAddress[$order->getCustomerId()]) && isset($cscustomerAddress[$order->getCustomerId()]['total_item_count'])) {
            	$cscustomerAddress[$order->getCustomerId()]['total_item_count'] += $order->getData('total_item_count');
            } else {
            	$cscustomerAddress[$order->getCustomerId()]['total_item_count'] = $order->getData('total_item_count');
            }
        }

        foreach($cscustomerAddress as $customer) {
        	$name = '';
        	$email = '';
        	$phone = '';
        	$total_item_count = 0;
        	$grand_total = 0;

        	if (isset($cscustomerAddress[$customer['id']]) && isset($cscustomerAddress[$customer['id']]['name'])) {
        		$name = $cscustomerAddress[$customer['id']]['name'];
        	}
        	if (isset($cscustomerAddress[$customer['id']]) && isset($cscustomerAddress[$customer['id']]['email'])) {
        		$email = $cscustomerAddress[$customer['id']]['email'];
        	}
        	if (isset($cscustomerAddress[$customer['id']]) && isset($cscustomerAddress[$customer['id']]['phone'])) {
        		$phone = $cscustomerAddress[$customer['id']]['phone'];
        	}
        	if (isset($cscustomerAddress[$customer['id']]) && isset($cscustomerAddress[$customer['id']]['total_item_count'])) {
        		$total_item_count = $cscustomerAddress[$customer['id']]['total_item_count'];
        	}
        	if (isset($cscustomerAddress[$customer['id']]) && isset($cscustomerAddress[$customer['id']]['grand_total'])) {
        		$grand_total = $cscustomerAddress[$customer['id']]['grand_total'];
        	}

        	$content[] = [
        		$name,
        		$email,
        		$phone,
        		$total_item_count,
        		$grand_total,
        	];
        }

        $this->csvProcessor->setEnclosure('"')->setDelimiter(',')->saveData($filePath, $content);
        return $this->fileFactory->create(
            $fileName,
            [
                'type'  => "filename",
                'value' => $fileName,
                'rm'    => true,
            ],
            DirectoryList::MEDIA,
            'text/csv',
            null
        );
    }

    public function getCustomerAddress() {
        $customers = $this->getCustomerCollection();
        $customerAddress = [];
        
        if (count($customers)) {
            foreach($customers as $customer) {
                $billingAddressId = $customer->getDefaultBilling();
                $billingAddress = $this->addressFactory->create()->load($billingAddressId);
                if(isset($customerAddress[$billingAddress->getData('city')]) && isset($customerAddress[$billingAddress->getData('city')]['id']) && $customerAddress[$billingAddress->getData('city')]['id'] == $billingAddress->getData('city')) {
                	$customerAddress[$billingAddress->getData('city')]['number'] += 1;
                } else {
                	$customerAddress[$billingAddress->getData('city')]['id'] = $billingAddress->getData('city');
                	$customerAddress[$billingAddress->getData('city')]['number'] = 1;
                }
            }
        }

        return $customerAddress;
    }

    protected function getCustomerCollection() {
        return $this->_customer->getCollection()
               ->addAttributeToSelect("*")
               ->load();
    }

    protected function getOrders() {
    	$orderCollection = $this->_orderCollectionFactory->create();
        return $orderCollection;
    }
}