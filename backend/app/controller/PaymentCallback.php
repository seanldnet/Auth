<?php
declare(strict_types=1);

namespace app\controller;

use app\payment\EpayAdapter;
use app\payment\EpusdtAdapter;
use app\payment\GmpayAdapter;
use app\payment\PaymentCallbackProcessor;
use app\payment\PaymentChannelRegistry;
use app\payment\PaymentCredentialCipher;
use RuntimeException;
use think\facade\Db;
use think\facade\Log;
use think\Response;
use Throwable;

final class PaymentCallback
{
    public function index(string $channel): Response
    {
        $channel = strtoupper($channel);

        try {
            $channelRow = Db::name('payment_channels')
                ->where('channel_code', $channel)
                ->where('enabled', 1)
                ->find();

            if (!is_array($channelRow)) {
                return response('channel unavailable', 404, ['Content-Type' => 'text/plain; charset=utf-8']);
            }

            $configuration = json_decode((string) $channelRow['configuration'], true, 512, JSON_THROW_ON_ERROR);
            $credentialsJson = PaymentCredentialCipher::decrypt((string) $channelRow['credentials_ciphertext']);
            $credentials = json_decode($credentialsJson, true, 512, JSON_THROW_ON_ERROR);

            if (!is_array($configuration) || !is_array($credentials)) {
                throw new RuntimeException('Payment channel configuration is invalid.');
            }

            $configuration = array_merge($configuration, $credentials, [
                'pid' => (string) $channelRow['merchant_pid'],
            ]);

            $request = request();
            $payload = array_merge($request->get(), $request->post());
            $rawBody = (string) $request->getInput();
            if (str_contains(strtolower((string) $request->header('content-type')), 'application/json') && $rawBody !== '') {
                $jsonPayload = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($jsonPayload)) {
                    $payload = array_merge($payload, $jsonPayload);
                }
            }

            $registry = new PaymentChannelRegistry();
            $registry->register(new EpusdtAdapter());
            $registry->register(new GmpayAdapter());
            $registry->register(new EpayAdapter());

            (new PaymentCallbackProcessor($registry))->process(
                $channel,
                $request->header(),
                $payload,
                $configuration,
            );

            return response('ok', 200, ['Content-Type' => 'text/plain; charset=utf-8']);
        } catch (Throwable $exception) {
            Log::error('Payment callback rejected', [
                'channel' => $channel,
                'reason' => $exception->getMessage(),
            ]);

            return response('callback rejected', 400, ['Content-Type' => 'text/plain; charset=utf-8']);
        }
    }
}
