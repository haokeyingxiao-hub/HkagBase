<?php

namespace HkagBase\Checkout\Customer\SalesChannel;


use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractRegisterRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\CustomerResponse;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['store-api']])]
class RegisterRouteDecorator extends AbstractRegisterRoute
{
    public function __construct(
        private readonly AbstractRegisterRoute $abstractRegisterRoute,
        private readonly Connection            $connection,
        private readonly SystemConfigService   $systemConfigService
    )
    {
    }

    public function getDecorated(): AbstractRegisterRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/account/register', name: 'store-api.account.register', methods: ['POST'])]
    public function register(RequestDataBag $data, SalesChannelContext $context, bool $validateStorefrontUrl = true, ?DataValidationDefinition $additionalValidationDefinitions = null): CustomerResponse
    {
        $isCustomerNameAndAddressRequired = $this->systemConfigService->get(
            'core.systemWideLoginRegistration.isCustomerNameAndAddressRequired',
            $context->getSalesChannelId()
        );
        $isCustomerNameAndAddressRequired = !$isCustomerNameAndAddressRequired && !$data->has('billingAddress');
        if ($isCustomerNameAndAddressRequired) {
            $defaultCountry = $this->connection->executeQuery('SELECT id FROM country WHERE active = 1 and iso3="CHN" limit 1')->fetchOne();
            $billingAddress = new RequestDataBag([
                'countryId' => Uuid::fromBytesToHex($defaultCountry),
                'street' => $data->get('email'),
                'city' => $data->get('email'),
                'zipcode' => (string)mt_rand(10000, 99999),
            ]);
            $data->set('billingAddress', $billingAddress);
            $data->set('firstName', (string)mt_rand(10000, 99999));
            $data->set('lastName', CustomerDefinition::ENTITY_NAME);
        }
        return $this->abstractRegisterRoute->register($data,
            $context, $validateStorefrontUrl, $additionalValidationDefinitions);
    }
}
