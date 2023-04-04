<?php

namespace Blackbird\AcceptLanguageRedirection\Plugin;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\App\Request\StorePathInfoValidator;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManager;
use Magento\Store\Model\StoreManagerInterface;

class AcceptLanguageStorePathInfoValidatorPlugin
{
    public const BASE_URL_XML_PATH = 'web/secure/base_url';
    public const ACCEPT_LANGUAGE_REDIRECTION_XML_PATH = 'web/url/accept_language_redirection';
    public const ACCEPT_LANGUAGE_EXPECTED_VALUE_XML_PATH = 'web/url/accept_language_expected_value';
    public const HTTP_ACCEPT_LANGUAGE = 'HTTP_ACCEPT_LANGUAGE';

    public function __construct(
        private ScopeConfigInterface $scopeConfig,
        private StoreManagerInterface $storeManager,
        private RequestInterface $request
    ) {

    }

    /**
     * @param StorePathInfoValidator $subject
     * @param ?string                $result
     * @param Http                   $request
     * @param string                 $pathInfo
     *
     * @return ?string
     */
    public function afterGetValidStoreCode(
        StorePathInfoValidator $subject, ?string $result, Http $request, string $pathInfo = ''
    ): ?string {

        // Check there is a suffix url (/en/ or /fr/) for current store expected
        if (!$result) {
            return $result;
        }

        //Check it is an HTTP PHP Request (from frontend)
        if (!method_exists(
            $this->request,
            'getServer')) {
            return $result;
        }

        //Check we ask for root URL
        if ($this->request->getServer('REQUEST_URI') !== '/') {
            return $result;
        }

        //Get MAGE_RUN_CODE
        $expectedStoreCode = $this->request->getServer(StoreManager::PARAM_RUN_CODE);
        if (is_string($expectedStoreCode) && $expectedStoreCode !== '') {

            //SEARCH FOR STORE ID
            try {
                $store = $this->getStoreByCode($expectedStoreCode);
            } catch (NoSuchEntityException) {
                return $result;
            }

            //CHECK IF REDIRECTION IS ENABLED
            $acceptLanguageRedirection = (string) $this->scopeConfig->getValue(
                self::ACCEPT_LANGUAGE_REDIRECTION_XML_PATH,
                ScopeInterface::SCOPE_STORE,
                $store
            );

            if (!$acceptLanguageRedirection) {
                return $result;
            }

            //Find targeted store for Accepted Language
            $httpAcceptLanguage = $this->request->getServer(self::HTTP_ACCEPT_LANGUAGE);
            foreach ($this->storeManager->getStores() as $targetedStore) {
                if ($store->getWebsiteId() === $targetedStore->getWebsiteId()) {
                    $acceptLanguageExpectedValue = (string) $this->scopeConfig->getValue(
                        self::ACCEPT_LANGUAGE_EXPECTED_VALUE_XML_PATH,
                        ScopeInterface::SCOPE_STORE,
                        $targetedStore
                    );

                    if (preg_match(
                        '/^' . $acceptLanguageExpectedValue . '/',
                        $httpAcceptLanguage)) {
                        $targetedStoreBaseUrl = (string) $this->scopeConfig->getValue(
                            self::BASE_URL_XML_PATH,
                            ScopeInterface::SCOPE_STORE,
                            $targetedStore
                        );

                        header('Location: ' . $targetedStoreBaseUrl);
                        exit;
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Get store object by storeCode
     *
     * @param string $storeCode
     *
     * @return StoreInterface
     * @throws NoSuchEntityException
     */
    private function getStoreByCode(string $storeCode): StoreInterface
    {
        /** @var StoreInterface */
        return $this->storeManager->getStore($storeCode);
    }
}
