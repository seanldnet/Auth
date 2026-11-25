<?php
declare(strict_types=1);

namespace tests;

use app\payment\EpaySigner;
use app\payment\GmpaySigner;
use PHPUnit\Framework\TestCase;

final class PaymentSignatureTest extends TestCase
{
    public function testGmpayOfficialHmacSha256Example(): void
    {
        $parameters = [
            'pid' => '1000',
            'order_id' => 'ORD202605230001',
            'currency' => 'cny',
            'token' => 'usdt',
            'network' => 'tron',
            'amount' => '100',
            'notify_url' => 'https://merchant.example/notify',
            'redirect_url' => 'https://merchant.example/return',
            'name' => 'VIP',
        ];

        self::assertSame(
            '6f874b1919d95081835e2809b620e354a5866f5a6dbb2e432d1627f1eb10059d',
            GmpaySigner::sign($parameters, 'epusdt_secret_key'),
        );
    }

    public function testGmpaySignatureIgnoresEmptyValuesAndSignatureField(): void
    {
        $parameters = ['z' => 'last', 'a' => 'first', 'empty' => '', 'signature' => 'ignored'];
        self::assertSame('a=first&z=last', GmpaySigner::canonicalize($parameters));
    }

    public function testEpayOfficialMd5Example(): void
    {
        $parameters = [
            'pid' => '1000',
            'money' => '100',
            'name' => 'VIP',
            'notify_url' => 'https://merchant.example/notify',
            'out_trade_no' => 'ORD202605230001',
            'return_url' => 'https://merchant.example/return',
            'type' => 'alipay',
        ];

        self::assertSame(
            'b865b0acbb2b01554c35a1bd33351452',
            EpaySigner::sign($parameters, 'epusdt_secret_key'),
        );
    }
}