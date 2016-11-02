<?php
class Ak_NovaPoshta_Model_Api_Client
{
    protected $_httpClient;

    const DELIVERY_TYPE_APARTMENT_APARTMENT = 1;
    const DELIVERY_TYPE_APARTMENT_WAREHOUSE = 2;
    const DELIVERY_TYPE_WAREHOUSE_APARTMENT = 3;
    const DELIVERY_TYPE_WAREHOUSE_WAREHOUSE = 'WarehouseWarehouse';

    const LOAD_TYPE_STANDARD   = 1;
    const LOAD_TYPE_SECURITIES = 4;

    /**
     * @return string
     */
    protected function _getApiUri()
    {
        return Mage::helper('novaposhta')->getStoreConfig('api_url');
    }

    /**
     * @return string
     */
    protected function _getApiKey()
    {
        return Mage::helper('novaposhta')->getStoreConfig('api_key');
    }

    /**
     * @return Zend_Http_Client
     */
    protected function _getHttpClient()
    {
        if (!$this->_httpClient) {
            $this->_httpClient = new Zend_Http_Client($this->_getApiUri());
        }

        return $this->_httpClient;
    }

    /**
     * @param array $array
     * @param SimpleXMLElement $element
     * @return SimpleXMLElement
     */
    protected function _buildXml(array $array, SimpleXMLElement $element = null)
    {
        if (is_null($element)) {
            $element = new SimpleXMLElement('<file/>');
            $element->addChild('apiKey', $this->_getApiKey());
        }

        foreach ($array as $key => $value) {
            if (!is_numeric($key)) {
                if (is_array($value)) {
                    $this->_buildXml($value, $element->addChild($key));
                } else {
                    $element->addChild($key, $value);
                }
            }
        }

        return $element;
    }

    /**
     * @param array $data
     * @return SimpleXMLElement
     */
    protected function _makeRequest(array $data)
    {
        /** @var Ak_NovaPoshta_Helper_Data $helper */
        $helper    = Mage::helper('novaposhta');
        $xmlString = $this->_buildXml($data)->asXML();

        $helper->log('Request XML:' . $xmlString);

        /** @var Zend_Http_Response $response */
        $response = $this->_getHttpClient()
            ->resetParameters(true)
            ->setRawData($xmlString)
            ->request(Zend_Http_Client::POST);

        $helper->log('Response status code:' . $response->getStatus());
        $helper->log('Response body:' . $response->getBody());

        $helper->log(print_r((array) new SimpleXMLElement($response->getBody()), true));

        if (200 != $response->getStatus()) {
            Mage::throwException('Server error, response status:' . $response->getStatus());
        }

        return new SimpleXMLElement($response->getBody());
    }

    /**
     * @return SimpleXMLElement
     */
    public function getCityWarehouses()
    {
        $responseXml = $this->_makeRequest(array(
            'calledMethod' => 'getCities',
            'modelName' => 'Address',
        ));

        return $responseXml->xpath('data');
    }

    /**
     * @return SimpleXMLElement
     */
    public function getWarehouses()
    {
        $responseXml = $this->_makeRequest(array(
            'calledMethod' => 'getWarehouses',
            'modelName' => 'AddressGeneral',
        ));

        return $responseXml->xpath('data');
    }

    public function getShippingCost(
        Ak_NovaPoshta_Model_City $senderCity,
        Ak_NovaPoshta_Model_City $recipientCity,
        $packageWeight,
        $publicPrice,
        $qty = 0,
        $deliveryType = self::DELIVERY_TYPE_WAREHOUSE_WAREHOUSE,
        $loadType = self::LOAD_TYPE_STANDARD
    )
    {
        $response = $this->_makeRequest(array(
            'modelName' => 'InternetDocument',
            'calledMethod' => 'getDocumentPrice',
            'methodProperties' => array(
                'CitySender' => $senderCity->getId(),
                'CityRecipient' => $recipientCity->getId(),
                'ServiceType' => $loadType,
                'Weight' => $packageWeight,
                'Cost' => $publicPrice,
                'Amount' => $qty,
                'ServiceType' => $deliveryType
            )
        ));

        if (1 == (int) $response->error) {
            Mage::throwException('Novaposhta Api error');
        }
        $responseData = $response->xpath('data/item');
        $cost = (string) $responseData[0]->Cost;
        return array (
            'cost' => (float) $cost,
        );
    }
}
