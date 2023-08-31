<?php
/**
 * This file is used for customer reinitialize payment process
 *
 * @author       Novalnet AG
 * @copyright(C) Novalnet
 * @license      https://www.novalnet.de/payment-plugins/kostenlos/lizenz
 */
namespace Novalnet\Providers\DataProvider;

use Plenty\Plugin\Templates\Twig;
use Plenty\Modules\Payment\Method\Contracts\PaymentMethodRepositoryContract;
use Plenty\Modules\Payment\Method\Models\PaymentMethod;
use Novalnet\Helper\PaymentHelper;
use Plenty\Plugin\Log\Loggable;
/**
 * Class NovalnetPaymentMethodScriptDataProvider
 *
 * @package Novalnet\Providers\DataProvider
 */
class NovalnetPaymentMethodScriptDataProvider
{
     use Loggable;
    /**
     * Script for displaying the reinitiate payment button
     *
     * @param Twig $twig
     *
     * @return string
     */
    public function call(Twig $twig)
    {
        // Load the all Novalnet payment methods
        $paymentMethodRepository = pluginApp(PaymentMethodRepositoryContract::class);
         $paymentHelper      = pluginApp(PaymentHelper::class);
        $paymentMethods          = $paymentMethodRepository->allForPlugin('plenty_novalnet');
        if(!is_null($paymentMethods)) {
            $paymentMethodIds              = [];
            $nnPaymentMethodKey = $nnPaymentMethodId = '';
            foreach($paymentMethods as $paymentMethod) {
                if($paymentMethod instanceof PaymentMethod) {
                    $paymentMethodIds[] = $paymentMethod->id;
                    if($paymentMethod->paymentKey == 'NOVALNET_CC') {
                        $nnPaymentMethodKey = $paymentMethod->paymentKey;
                        $nnPaymentMethodId = $paymentMethod->id;
                    }
                }
            }
            
             $paymentRequestData['transaction'] = [
							'amount' => 4955,
							'currency' => 'EUR',
							'test_mode' => 1,
						];
						$paymentRequestData['transaction']['hosted_page'] = [
							'type' => 'PAYMENTFORM',
						];
						$paymentRequestData['merchant'] = [
							'signature' => '7ibc7ob5|tuJEH3gNbeWJfIHah||nbobljbnmdli0poys|doU3HJVoym7MQ44qf7cpn7pc',
							'tariff' => '10004',
						];
						$paymentRequestData['customer'] = [
							'first_name' => 'PAYMENTFORM',
							'last_name' => 'PAYMENTFORM',
							'email' => 'test@gmail.com',
							'customer_ip' => '125.21.64.250',
							
						];
						$paymentRequestData['billing'] = [
							'street' => 'test',
							'city' => 'test',
							'country_code' => 'DE',
							'zip' => '54570',
							
						];
						$paymentRequestData['shipping'] = [
							'street' => 'test',
							'city' => 'test',
							'country_code' => 'DE',
							'zip' => '54570',
						];
						$paymentRequestData['custom'] = [
							'lang' => 'EN',

						];
						
						$paymentResponseData = $paymentHelper->executeCurl($paymentRequestData, 'https://payport.novalnet.de/v2/seamless/payment', 'a87ff679a2f3e71d9181a67b7542122c');
						$this->getLogger(__METHOD__)->error('Adding PDF comment failed for order ' , $paymentResponseData);
						$paymentFormUrl = $paymentResponseData['result']['redirect_url'];
        }
        return $twig->render('Novalnet::NovalnetPaymentMethodScriptDataProvider',
                                    [
                                        'paymentMethodIds'      => $paymentMethodIds,
                                        'nnPaymentMethodKey'    => $nnPaymentMethodKey,
                                        'nnPaymentMethodId'     => $nnPaymentMethodId,
			     		'url'                   => $paymentFormUrl
                                    ]);
    }
}
