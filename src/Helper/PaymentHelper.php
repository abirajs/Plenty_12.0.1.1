<?php
/**
 * This file is used for retrieve the details from the  shop instance
 *
 * @author       Novalnet AG
 * @copyright(C) Novalnet
 * @license      https://www.novalnet.de/payment-plugins/kostenlos/lizenz
 */
namespace Novalnet\Helper;

use Novalnet\Methods\NovalnetSepaPaymentMethod;
use Novalnet\Methods\NovalnetCcPaymentMethod;
use Novalnet\Methods\NovalnetApplePayPaymentMethod;
use Novalnet\Methods\NovalnetGooglePayPaymentMethod;
use Novalnet\Methods\NovalnetInvoicePaymentMethod;
use Novalnet\Methods\NovalnetPrepaymentPaymentMethod;
use Novalnet\Methods\NovalnetGuaranteedInvoicePaymentMethod;
use Novalnet\Methods\NovalnetGuaranteedSepaPaymentMethod;
use Novalnet\Methods\NovalnetIdealPaymentMethod;
use Novalnet\Methods\NovalnetSofortPaymentMethod;
use Novalnet\Methods\NovalnetGiropayPaymentMethod;
use Novalnet\Methods\NovalnetCashpaymentPaymentMethod;
use Novalnet\Methods\NovalnetPrzelewy24PaymentMethod;
use Novalnet\Methods\NovalnetEpsPaymentMethod;
use Novalnet\Methods\NovalnetPaypalPaymentMethod;
use Novalnet\Methods\NovalnetPostfinanceCardPaymentMethod;
use Novalnet\Methods\NovalnetPostfinanceEfinancePaymentMethod;
use Novalnet\Methods\NovalnetBancontactPaymentMethod;
use Novalnet\Methods\NovalnetMultibancoPaymentMethod;
use Novalnet\Methods\NovalnetOnlineBankTransferPaymentMethod;
use Novalnet\Methods\NovalnetAlipayPaymentMethod;
use Novalnet\Methods\NovalnetWechatPayPaymentMethod;
use Novalnet\Methods\NovalnetTrustlyPaymentMethod;
use Plenty\Modules\Payment\Method\Contracts\PaymentMethodRepositoryContract;
use \Plenty\Modules\Authorization\Services\AuthHelper;
use Plenty\Modules\Account\Address\Contracts\AddressRepositoryContract;
use Plenty\Modules\Order\Shipping\Countries\Contracts\CountryRepositoryContract;
use Plenty\Plugin\Translation\Translator;
use Plenty\Modules\Order\Models\Order;
use Plenty\Modules\Payment\Models\Payment;
use Plenty\Modules\Payment\Models\PaymentProperty;
use Plenty\Modules\Payment\Contracts\PaymentRepositoryContract;
use Plenty\Modules\Order\Contracts\OrderRepositoryContract;
use Plenty\Modules\Payment\Contracts\PaymentOrderRelationRepositoryContract;
use Plenty\Plugin\Log\Loggable;

/**
 * Class PaymentHelper
 *
 * @package Novalnet\Helper
 */
class PaymentHelper
{
    use Loggable;

    /**
     *
     * @var PaymentMethodRepositoryContract
     */
    private $paymentMethodRepository;

    /**
     * @var AddressRepositoryContract
     */
    private $addressRepository;

    /**
     * @var CountryRepositoryContract
     */
    private $countryRepository;

    /**
     *
     * @var PaymentRepositoryContract
     */
    private $paymentRepository;

    /**
     *
     * @var OrderRepositoryContract
     */
    private $orderRepository;

    /**
     *
     * @var PaymentOrderRelationRepositoryContract
     */
    private $paymentOrderRelationRepository;

    /**
     * Constructor.
     *
     * @param PaymentMethodRepositoryContract $paymentMethodRepository
     * @param AddressRepositoryContract $addressRepository
     * @param CountryRepositoryContract $countryRepository
     * @param PaymentRepositoryContract $paymentRepository
     * @param OrderRepositoryContract $orderRepository
     * @param PaymentOrderRelationRepositoryContract $paymentOrderRelationRepository
     */
    public function __construct(PaymentMethodRepositoryContract $paymentMethodRepository,
                                AddressRepositoryContract $addressRepository,
                                CountryRepositoryContract $countryRepository,
                                PaymentRepositoryContract $paymentRepository,
                                OrderRepositoryContract $orderRepository,
                                PaymentOrderRelationRepositoryContract $paymentOrderRelationRepository
                                )
    {
        $this->paymentMethodRepository          = $paymentMethodRepository;
        $this->addressRepository                = $addressRepository;
        $this->countryRepository                = $countryRepository;
        $this->paymentRepository                = $paymentRepository;
        $this->orderRepository                  = $orderRepository;
        $this->paymentOrderRelationRepository   = $paymentOrderRelationRepository;
    }

    /**
     * Get the payment method class
     *
     * @return array
     */
    public static function getPaymentMethods()
    {
        return [
            NovalnetCcPaymentMethod::PAYMENT_KEY                    => NovalnetCcPaymentMethod::class,
        ];
    }

    /**
     * Load the ID of the payment method
     * Return the ID for the payment method found
     *
     * @param string $paymentKey
     *
     * @return string|int
     */
    public function getPaymentMethodByKey($paymentKey)
    {
        $paymentMethods = $this->paymentMethodRepository->allForPlugin('plenty_novalnet');
        if(!is_null($paymentMethods)) {
            foreach($paymentMethods as $paymentMethod) {
                if($paymentMethod->paymentKey == $paymentKey) {
                    return [$paymentMethod->id, $paymentMethod->paymentKey, $paymentMethod->name];
                }
            }
        }
        return 'no_paymentmethod_found';
    }

    /**
     * Load the ID of the payment method
     * Return the payment key for the payment method found
     *
     * @param int $mop
     *
     * @return string|bool
     */
    public function getPaymentKeyByMop($mop)
    {
        $paymentMethods = $this->paymentMethodRepository->allForPlugin('plenty_novalnet');
        if(!is_null($paymentMethods)) {
            foreach($paymentMethods as $paymentMethod) {
                if($paymentMethod->id == $mop) {
                    return $paymentMethod->paymentKey;
                }
            }
        }
        return false;
    }

    /**
     * Get the payment method class
     *
     * @return array
     */
    public function getPaymentMethodsKey()
    {
        return [
                    NovalnetCcPaymentMethod::PAYMENT_KEY,
                ];
    }

    /**
     * Get billing/shipping address by its id
     *
     * @param int $addressId
     *
     * @return object
     */
    public function getCustomerAddress(int $addressId)
    {
        try {
            /** @var \Plenty\Modules\Authorization\Services\AuthHelper $authHelper */
            $authHelper = pluginApp(AuthHelper::class);
            $addressDetails = $authHelper->processUnguarded(function () use ($addressId) {
                //unguarded
               return $this->addressRepository->findAddressById($addressId);
            });
            return $addressDetails;
        } catch (\Exception $e) {
            $this->getLogger(__METHOD__)->error('Novalnet::getCustomerAddress', $e);
        }
    }

    /**
     * Get the required billing and shipping details
     *
     * @param object $billingAddress
     * @param object $shippingAddress
     *
     * @return array
     */
    public function getBillingShippingDetails($billingAddress, $shippingAddress)
    {
        $billingShippingDetails['billing']  = [
            'street'       => $billingAddress->street,
            'house_no'     => $billingAddress->houseNumber,
            'city'         => $billingAddress->town,
            'zip'          => $billingAddress->postalCode,
            'country_code' => $this->countryRepository->findIsoCode($billingAddress->countryId, 'iso_code_2'),
        ];
        $billingShippingDetails['shipping'] = [
            'street'       => $shippingAddress->street,
            'house_no'     => $shippingAddress->houseNumber,
            'city'         => $shippingAddress->town,
            'zip'          => $shippingAddress->postalCode,
            'country_code' => $this->countryRepository->findIsoCode($shippingAddress->countryId, 'iso_code_2'),
        ];
        return $billingShippingDetails;
    }

    /**
     * Retrieves the original end-customer address with and without proxy
     *
     * @return string
     */
    public function getRemoteAddress()
    {
        $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
        foreach($ipKeys as $key) {
            if(array_key_exists($key, $_SERVER) === true) {
                foreach(explode(',', $_SERVER[$key]) as $ip) {
                    return $ip;
                }
            }
        }
    }

    /**
     * Convert the orderamount to cents
     *
     * @param float $amount
     *
     * @return string
     */
    public function convertAmountToSmallerUnit($amount)
    {
        return sprintf('%0.2f', $amount) * 100;
    }

    /**
     * Get the date value as Y-M-D
     *
     * @param int $days
     *
     * @return string
     */
    public function dateFormatter($days)
    {
        return date( 'Y-m-d', strtotime( date( 'y-m-d' ) . '+ ' . $days . ' days' ) );
    }

    /**
     * Get the translated text for the Novalnet key
     *
     * @param string $key
     * @param string $lang
     *
     * @return string
     */
    public function getTranslatedText($key, $lang = null)
    {
        $translator = pluginApp(Translator::class);
        return $lang == null ? $translator->trans("Novalnet::PaymentMethod.$key") : $translator->trans("Novalnet::PaymentMethod.$key",[], $lang);
    }

    /**
     * Execute curl process
     *
     * @param string $paymentRequestData
     * @param string $paymentUrl
     * @param string $paymentAccessKey
     *
     * @return array
     */
    public function executeCurl($paymentRequestData, $paymentUrl, $paymentAccessKey)
    {
        // Setting up the important information in the headers
        $headers = [
            'Content-Type:application/json',
            'charset:utf-8',
            'X-NN-Access-Key:'. base64_encode($paymentAccessKey),
        ];
        try {
            $curl = curl_init();
            // Set cURL options
            curl_setopt($curl, CURLOPT_URL, $paymentUrl);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($paymentRequestData));
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
            // Execute cURL
            $paymentResponse = curl_exec($curl);
            // Handle cURL error
            if(curl_errno($curl)) {
               $this->getLogger(__METHOD__)->error('Novalnet::executeCurlError', curl_error($curl));
            }
            // Close cURL
            curl_close($curl);
            // Decoding the JSON string to array for further processing
            return json_decode($paymentResponse, true);
        } catch (\Exception $e) {
            $this->getLogger(__METHOD__)->error('Novalnet::executeCurlError', $e);
        }
    }

    /**
     * Reverse the given string
     *
     * @param string $str
     *
     * @return string
     */
    public function reverseString($str)
    {
        $string = '';
        // Find string length
        $len = strlen($str);
        // Loop through it and print it reverse
        for($i=$len-1;$i>=0;$i--) {
            $string .= $str[$i];
        }
        return $string;
    }

    /**
     * Create plenty payment to the order
     *
     * @param array $paymentResponseData
     *
     * @return none
     */
    public function createPlentyPayment($paymentResponseData)
    {
        try {
            /** @var Payment $payment */
            $payment = pluginApp(\Plenty\Modules\Payment\Models\Payment::class);
            $paymentResponseData['result']['status'] = $paymentResponseData['result']['status'] ?? $paymentResponseData['status'];
            // Get the Novalnet payment method Id if it is missing in the response
            if(empty($paymentResponseData['mop'])) {
                $paymentMethodDetail = $this->getPaymentMethodByKey($this->getPaymentKey($paymentResponseData['transaction']['payment_type']));
                $paymentResponseData['mop'] = $paymentMethodDetail[0];
            }
            $payment->mopId           = (int) $paymentResponseData['mop'];
            $payment->transactionType = Payment::TRANSACTION_TYPE_BOOKED_POSTING;
            $payment->status          = ($paymentResponseData['transaction']['status'] == 'ON_HOLD' || ($paymentResponseData['transaction']['status'] == 'PENDING' && !in_array($paymentResponseData['transaction']['payment_type'], ['INVOICE', 'PREPAYMENT', 'CASHPAYMENT', 'MULTIBANCO']))) ? Payment::STATUS_AWAITING_APPROVAL : (($paymentResponseData['result']['status'] == 'FAILURE' || $paymentResponseData['transaction']['status'] == 'DEACTIVATED') ? Payment::STATUS_CANCELED : Payment::STATUS_CAPTURED);
            $payment->currency        = $paymentResponseData['transaction']['currency'];
            $payment->amount          = ($paymentResponseData['result']['status'] == 'SUCCESS' && !empty($paymentResponseData['transaction']['refund']['amount'])) ? ($paymentResponseData['transaction']['refund']['amount'] / 100) : ($paymentResponseData['transaction']['status'] == 'CONFIRMED' ? ($paymentResponseData['transaction']['amount'] / 100) : 0);
            // Set the transaction status
            $txnStatus      = $paymentResponseData['transaction']['status'] ?? $paymentResponseData['result']['status'];
            // Set the booking text
            $bookingText    = isset($paymentResponseData['bookingText']) ? $paymentResponseData['bookingText'] : (!empty($paymentResponseData['transaction']['tid']) ? $paymentResponseData['transaction']['tid'] : $paymentResponseData['tid']);
            // Set the Refund status to the payment if refund was execute
            if(isset($paymentResponseData['refund'])) {
                $payment->type          = 'debit';
                $payment->unaccountable = 1;
                $payment->status        = ($paymentResponseData['refund'] == 'Partial') ? Payment::STATUS_PARTIALLY_REFUNDED : Payment::STATUS_REFUNDED;
            }
            // Not add the payment into account for the additional credits
            if(isset($paymentResponseData['unaccountable']) && !empty($paymentResponseData['unaccountable'])) {
                $payment->unaccountable = 1;
            }

            $paymentProperty     = [];
            $paymentProperty[]   = $this->getPaymentProperty(PaymentProperty::TYPE_BOOKING_TEXT, $bookingText);
            $paymentProperty[]   = $this->getPaymentProperty(PaymentProperty::TYPE_TRANSACTION_ID, $paymentResponseData['transaction']['tid']);
            $paymentProperty[]   = $this->getPaymentProperty(PaymentProperty::TYPE_ORIGIN, Payment::ORIGIN_PLUGIN);
            $paymentProperty[]   = $this->getPaymentProperty(PaymentProperty::TYPE_EXTERNAL_TRANSACTION_STATUS, $txnStatus);

            $payment->properties = $paymentProperty;
            // Create the payment
            $paymentObj = $this->paymentRepository->createPayment($payment);
            // Assign the created payment to the specified order
            $this->assignPlentyPaymentToPlentyOrder($paymentObj, (int)$paymentResponseData['transaction']['order_no']);
        } catch (\Exception $e) {
            $this->getLogger(__METHOD__)->error('createPlentyPayment failed ' . $paymentResponseData['transaction']['order_no'], $e);
        }
    }

    /**
     * Get the payment property object
     *
     * @param mixed $typeId
     * @param mixed $value
     *
     * @return object
     */
    public function getPaymentProperty($typeId, $value)
    {
        /** @var PaymentProperty $paymentProperty */
        $paymentProperty = pluginApp(\Plenty\Modules\Payment\Models\PaymentProperty::class);

        $paymentProperty->typeId = $typeId;
        $paymentProperty->value  = (string) $value;

        return $paymentProperty;
    }

    /**
     * Assign the payment to an order in plentymarkets.
     *
     * @param Payment $payment
     * @param int $orderId
     *
     * @return none
     */
    public function assignPlentyPaymentToPlentyOrder(Payment $payment, int $orderId)
    {
        try {
            /** @var \Plenty\Modules\Authorization\Services\AuthHelper $authHelper */
            $authHelper = pluginApp(AuthHelper::class);
            $authHelper->processUnguarded(function() use ($payment, $orderId) {
                //unguarded
                $order = $this->orderRepository->findById($orderId);
                if(!is_null($order) && $order instanceof Order) {
                    $this->paymentOrderRelationRepository->createOrderRelation($payment, $order);
                }
            });
        } catch (\Exception $e) {
            $this->getLogger(__METHOD__)->error('Novalnet::assignPlentyPaymentToPlentyOrder ' . $orderId, $e);
        }
    }

    /**
     * Get the customized translated text for the Novalnet key
     *
     * @param string $key
     * @param string $lang
     *
     * @return string
     */
    public function getCustomizedTranslatedText($key, $lang = null)
    {
        $translator = pluginApp(Translator::class);
        return $lang == null ? $translator->trans("Novalnet::Customize.$key") : $translator->trans("Novalnet::Customize.$key",[], $lang);
    }

    /**
     * Get the Novalnet payment method key
     *
     * @param string $paymentType
     *
     * @return string
     */
    public function getPaymentKey($paymentType)
    {
        $paymentMethodKey = [
            'DIRECT_DEBIT_SEPA'             => 'NOVALNET_SEPA',
            'CREDITCARD'                    => 'NOVALNET_CC',
            'APPLEPAY'                      => 'NOVALNET_APPLEPAY',
            'INVOICE'                       => 'NOVALNET_INVOICE',
            'PREPAYMENT'                    => 'NOVALNET_PREPAYMENT',
            'GUARANTEED_INVOICE'            => 'NOVALNET_GUARANTEED_INVOICE',
            'GUARANTEED_DIRECT_DEBIT_SEPA'  => 'NOVALNET_GUARANTEED_SEPA',
            'IDEAL'                         => 'NOVALNET_IDEAL',
            'ONLINE_TRANSFER'               => 'NOVALNET_SOFORT',
            'GIROPAY'                       => 'NOVALNET_GIROPAY',
            'CASHPAYMENT'                   => 'NOVALNET_CASHPAYMENT',
            'PRZELEWY24'                    => 'NOVALNET_PRZELEWY24',
            'EPS'                           => 'NOVALNET_EPS',
            'PAYPAL'                        => 'NOVALNET_PAYPAL',
            'POSTFINANCE_CARD'              => 'NOVALNET_POSTFINANCE_CARD',
            'POSTFINANCE'                   => 'NOVALNET_POSTFINANCE_EFINANCE',
            'BANCONTACT'                    => 'NOVALNET_BANCONTACT',
            'MULTIBANCO'                    => 'NOVALNET_MULTIBANCO',
            'ONLINE_BANK_TRANSFER'          => 'NOVALNET_ONLINE_BANK_TRANSFER',
            'ALIPAY'                        => 'NOVALNET_ALIPAY',
            'WECHATPAY'                     => 'NOVALNET_WECHAT_PAY',
            'TRUSTLY'                       => 'NOVALNET_TRUSTLY',
            'GOOGLEPAY'                     => 'NOVALNET_GOOGLEPAY'
        ];
        return $paymentMethodKey[$paymentType];
    }

    /**
     * Creating Payment for credit note order
     *
     * @param object $payments
     * @param array $paymentResponseData
     * @param string $comments
     *
     * @return none
     */
    public function createRefundPayment($payments, $paymentResponseData, $comments)
    {
        // Get the parent order payment Id
        foreach($payments as $payment) {
            $mop = $payment->mopId;
            $currency = $payment->currency;
            $parentPaymentId = $payment->id;
        }
        // Refund TID
        $refundTid = $paymentResponseData['transaction']['refund']['tid'] ?? $paymentResponseData['transaction']['tid'];
        /** @var Payment $payment */
        $payment = pluginApp(\Plenty\Modules\Payment\Models\Payment::class);
        $payment->updateOrderPaymentStatus = true;
        $payment->mopId = (int) $mop;
        $payment->transactionType = Payment::TRANSACTION_TYPE_BOOKED_POSTING;
        $payment->status = Payment::STATUS_CAPTURED;
        $payment->currency = $currency;
        $payment->amount = (float) ($paymentResponseData['transaction']['refund']['amount'] / 100);
        $payment->receivedAt = date('Y-m-d H:i:s');
        $payment->type = 'debit';
        $payment->parentId = $parentPaymentId;
        $payment->unaccountable = 0;
        $paymentProperty     = [];
        $paymentProperty[]   = $this->getPaymentProperty(PaymentProperty::TYPE_BOOKING_TEXT, $comments);
        $paymentProperty[]   = $this->getPaymentProperty(PaymentProperty::TYPE_TRANSACTION_ID, $refundTid);
        $paymentProperty[]   = $this->getPaymentProperty(PaymentProperty::TYPE_ORIGIN, Payment::ORIGIN_PLUGIN);
        $paymentProperty[]   = $this->getPaymentProperty(PaymentProperty::TYPE_EXTERNAL_TRANSACTION_STATUS, $paymentResponseData['transaction']['status']);
        $payment->properties = $paymentProperty;
        $paymentObj = $this->paymentRepository->createPayment($payment);
        $this->assignPlentyPaymentToPlentyOrder($paymentObj, (int)$paymentResponseData['childOrderId']);
    }
}
