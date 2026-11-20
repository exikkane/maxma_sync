<?php
/***************************************************************************
 *                             Larionov.tech                               *
 *                          https://larionov.tech                          *
 ***************************************************************************/

use CloudLoyalty\Api\Exception\TransportException;
use CloudLoyalty\Api\Generated\Model\CalculationResult;
use CloudLoyalty\Api\Generated\Model\CalculationResultRow;
use CloudLoyalty\Api\Generated\Model\CalculationResultSummary;
use CloudLoyalty\Api\Generated\Model\V2CalculatePurchaseResponse;
use PHPUnit\Framework\TestCase;
use Tygh\Addons\MaxmaSync\Helpers\FactoryBuilders\SetOrderRequestBuilder;
use Tygh\Addons\MaxmaSync\Helpers\MaxmaLogger;
use Tygh\Addons\MaxmaSync\Service\CartService;
use Tygh\Addons\MaxmaSync\Service\MaxmaClient;
use Tygh\Addons\MaxmaSync\Service\UsersService;

final class CartTest extends TestCase
{
    public function testOrderDoesNotSendAbsentSalePrice(): void
    {
        $query = [
            'client' => ['phoneNumber' => '+79990000000'],
            'shop' => ['code' => 'test', 'name' => 'Test'],
            'rows' => [['id' => '100', 'qty' => 3, 'product' => [
                'externalId' => '10', 'sku' => 'test', 'title' => 'Test', 'blackPrice' => 100,
            ]]],
            'applyBonuses' => 0,
            'collectBonuses' => 0,
        ];
        $builder = new SetOrderRequestBuilder();
        $request = $builder->build(['orderId' => '1', 'calculationQuery' => $query]);
        self::assertNull($request->getCalculationQuery()->getRows()[0]->getProduct()->getRedPrice());
        $query['rows'][0]['product']['redPrice'] = 0.0;
        $request = $builder->build(['orderId' => '1', 'calculationQuery' => $query]);
        self::assertSame(0.0, $request->getCalculationQuery()->getRows()[0]->getProduct()->getRedPrice());
    }

    public function testMoneyAndNumericRowIdSurviveRecalculation(): void
    {
        $service = new CartService([], $this->createMock(UsersService::class), $this->createMock(MaxmaClient::class));
        $row = (new CalculationResultRow())->setId('100')->setTotalDiscount(12.75);
        $summary = (new CalculationResultSummary())->setTotalDiscount(12.75);
        $result = (new CalculationResult())->setRows([$row])->setSummary($summary);
        $response = (new V2CalculatePurchaseResponse())->setCalculationResult($result);
        $cart = ['products' => [100 => ['amount' => 3, 'discount' => 2.0]], 'subtotal_discount' => 3.50];
        $calculation = $service->calculationToArray($response);
        $service->applyExternalBonuses($calculation, $cart);
        $service->applyExternalBonuses($calculation, $cart);

        self::assertSame(12.75, $calculation['total_discount']);
        self::assertSame(6.25, $cart['products'][100]['discount']);
        self::assertSame(16.25, $cart['subtotal_discount']);
        $service->clearExternalBonuses($cart);
        self::assertSame(2.0, $cart['products'][100]['discount']);
        self::assertSame(3.50, $cart['subtotal_discount']);
    }

    public function testOptionPriceAndStoredProductDiscountKeepTheirMeaning(): void
    {
        $service = new CartService(
            ['maxma_shop_code' => 'test', 'maxma_shop_name' => 'Test'],
            $this->createMock(UsersService::class),
            $this->createMock(MaxmaClient::class)
        );
        $cart = ['products' => [100 => [
            'product_id' => 1, 'product_code' => 'sku', 'base_price' => 100,
            'original_price' => 120, 'price' => 115, 'amount' => 1, 'stored_price' => 'Y',
            'discount' => 5.0,
        ]]];
        $product = $service->generateCalculationQuery($cart)['rows'][0]['product'];
        self::assertSame(120.0, $product['blackPrice']);
        self::assertSame(115.0, $product['redPrice']);

        $service->applyExternalBonuses(['rows' => [100 => ['total_discount' => 10.0]], 'total_discount' => 10.0], $cart);
        self::assertSame(5.0, $cart['products'][100]['discount']);
        self::assertSame(10.0, $cart['subtotal_discount']);
    }

    public function testNativePointsAreReplacedByMaxmaDiscount(): void
    {
        $service = new CartService([], $this->createMock(UsersService::class), $this->createMock(MaxmaClient::class));
        $cart = [
            'products' => [100 => ['amount' => 1]],
            'subtotal_discount' => 23.50,
            'points_info' => ['in_use' => ['points' => 20, 'cost' => 20.0]],
        ];
        $service->applyExternalBonuses(['rows' => [], 'total_discount' => 12.75], $cart, 20.0);
        self::assertSame(16.25, $cart['subtotal_discount']);
        $service->clearExternalBonuses($cart);
        self::assertSame(23.50, $cart['subtotal_discount']);
    }

    public function testStoredOrderCanBeRecalculatedWithoutAddingMaxmaDiscountTwice(): void
    {
        require_once DIR_ROOT . '/app/addons/maxma_sync/func.php';
        $cart = ['subtotal_discount' => 16.25, 'original_subtotal_discount' => 16.25];
        fn_maxma_sync_form_cart(['maxma_promotion_data' => serialize(['coupon' => 'CODE', 'total_discount' => 12.75])], $cart, []);
        self::assertSame(3.50, $cart['subtotal_discount']);
        self::assertSame(3.50, $cart['original_subtotal_discount']);
        self::assertSame('CODE', $cart['maxma_promotion_data']['coupon']);
    }

    public function testCheckoutRequiresConfirmationWhenDiscountFalls(): void
    {
        require_once DIR_ROOT . '/tests/fixtures/Registry.php';
        require_once DIR_ROOT . '/app/addons/maxma_sync/func.php';
        Tygh\Registry::set('runtime.maxma_sync.previous_discount', 20.0);
        try {
            $allow = true;
            fn_maxma_sync_pre_place_order(['maxma_promotion_data' => ['total_discount' => 20.0]], $allow);
            self::assertTrue($allow);
            fn_maxma_sync_pre_place_order([], $allow);
            self::assertFalse($allow);
        } finally {
            Tygh\Registry::del('runtime.maxma_sync.previous_discount');
        }
    }

    public function testTransportFailureReturnsNoCalculation(): void
    {
        $client = $this->createMock(MaxmaClient::class);
        $client->method('calculatePurchase')->willThrowException(new TransportException('timeout'));
        $service = new CartService(
            ['maxma_shop_code' => 'test', 'maxma_shop_name' => 'Test'],
            $this->createMock(UsersService::class),
            $client,
            $this->createMock(MaxmaLogger::class)
        );
        self::assertNull($service->calculateCartContent(['products' => [100 => ['price' => 100]]], [], 'CODE'));

        require_once DIR_ROOT . '/app/addons/maxma_sync/func.php';
        $cart = [
            'products' => [100 => ['price' => 100, 'amount' => 1]],
            'subtotal' => 100.0, 'total' => 76.5, 'subtotal_discount' => 23.5,
            'points_info' => ['in_use' => ['points' => 20, 'cost' => 20.0]],
            'maxma_promotion_data' => ['coupon' => 'OLD', 'total_discount' => 20],
            'coupons' => ['OLD' => 1, 'NATIVE' => [123]],
        ];
        fn_maxma_sync_recalculate_cart($service, $cart, []);
        self::assertSame(96.5, $cart['total']);
        self::assertSame(['NATIVE' => [123]], $cart['coupons']);
        self::assertArrayNotHasKey('maxma_promotion_data', $cart);
    }
}
