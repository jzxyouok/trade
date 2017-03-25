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