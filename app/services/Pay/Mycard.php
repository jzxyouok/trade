<?php
namespace MyApp\Services\Pay;


use Phalcon\DI;
use MyApp\Models\Trade;
use Phalcon\Mvc\Controller;

class Mycard extends Controller
{

    private $tradeModel;


    public function process($transaction = '', $response = [])
    {
        $this->tradeModel = new Trade();

        // Card 支付
        if (empty($response['transactionReference'])) {
            $parameter = [
                'gateway'     => 'mycard',
                'transaction' => $transaction,
                'auth'        => $response['auth_code'],
            ];
            header('Location: /trade/card?' . http_build_query($parameter));
            exit();
        } /**
         *
         * Wallet, Telecom 支付
         */
        else {
            try {
                return $this->tradeModel->setTradeReference(
                    $transaction,
                    [
                        'trade_no'   => $response['transactionReference'],
                        'key_string' => $response['auth_code'],
                    ]
                );
            } catch (\Exception $e) {
                throw new \Exception('MyCard: ' . _('create trade failed'));
            }
        }
    }

}