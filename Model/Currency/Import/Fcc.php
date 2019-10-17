<?php
/**
 * Currency convertion API call Model
 *
 * Informatics
 * Informatics Currencyconverter
 *
 * @category   Informatics
 * @package    Informatics_Currencyconverter
 * @copyright  Copyright © 2017-2018
 */

namespace Informatics\Currencyconverter\Model\Currency\Import;

class Fcc extends \Magento\Directory\Model\Currency\Import\AbstractImport
{
    /**
     * @var string
     */
    const CURRENCY_CONVERTER_URL = 'https://free.currencyconverterapi.com/api/v3/convert?q={{CURRENCY_FROM}}_{{CURRENCY_TO}}';
    /**
     * HTTP client
     *
     * @var \Magento\Framework\HTTP\ZendClient
     */
    protected $_httpClient;
    /**
     * Core store config
     *
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $_scopeConfig;
    /**
     * @param \Magento\Directory\Model\CurrencyFactory $currencyFactory
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     */
    protected $jsonHelper;

    public function __construct(
        \Magento\Directory\Model\CurrencyFactory $currencyFactory,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\Json\Helper\Data $jsonHelper
    ) {
        parent::__construct($currencyFactory);
        $this->_scopeConfig = $scopeConfig;
        $this->_httpClient = new \Magento\Framework\HTTP\ZendClient();
        $this->jsonHelper = $jsonHelper;
    }
    /**
     * @param string $currencyFrom
     * @param string $currencyTo
     * @param int $retry
     * @return float|null
     */
    protected function _convert($currencyFrom, $currencyTo, $retry = 0)
    {
        $url = str_replace('{{CURRENCY_FROM}}', $currencyFrom, self::CURRENCY_CONVERTER_URL);
        $url = str_replace('{{CURRENCY_TO}}', $currencyTo, $url);
        try {
            sleep($this->_scopeConfig->getValue(
                'currency/fcc/delay',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE
            ));
            $response = $this->_httpClient->setUri(
                $url
            )->setConfig(
                [
                    'timeout' => $this->_scopeConfig->getValue(
                        'currency/fcc/timeout',
                        \Magento\Store\Model\ScopeInterface::SCOPE_STORE
                    ),
                ]
            )->request(
                'GET'
            )->getBody();
            $resultKey = $currencyFrom.'_'.$currencyTo;
            $data = $this->jsonHelper->jsonDecode($response);
            $results = $data['results'][$resultKey];
            $queryCount = $data['query']['count'];
            if (!$queryCount && !isset($results)) {
                $this->_messages[] = __('We can\'t retrieve a rate from %1.', $url);
                return null;
            }
            return (float)$results['val'];
        } catch (\Exception $e) {
            if ($retry == 0) {
                $this->_convert($currencyFrom, $currencyTo, 1);
            } else {
                $this->_messages[] = __('We can\'t retrieve a rate from %1.', $url);
            }
        }
    }
}
