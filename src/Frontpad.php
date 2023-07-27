<?php

namespace Kolirt\Frontpad;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Cache;

class Frontpad
{

    private $client;
    private $api_endpoint = 'https://app.frontpad.ru/api/index.php';
    private $secret;
    private $proxy;

    public function __construct($secret, array $proxy_list = [], $proxy_cache = 0)
    {
        $this->secret = $secret;

        $options = [
            'base_uri' => $this->api_endpoint,
            'headers'  => [
                'Accept' => 'application/json'
            ]
        ];

        if (count($proxy_list) > 0) {
            $this->proxy = Cache::remember('frontpad.proxy', $proxy_cache, function () use ($proxy_list) {
                return $proxy_list[array_rand($proxy_list, 1)];
            });
            $options['proxy'] = $this->proxy;
        }

        $this->client = new Client($options);
    }

    /**
     * Метод предназначен для передачи заказа из интернет-магазина или приложения.
     *
     * @param Order $order
     * @return object
     */
    public function newOrder(Order $order)
    {
        return $this->call(function () use ($order) {
            $form_params = $order->render();
            $form_params['secret'] = $this->secret;

            $request = $this->client->post('?new_order', [
                'form_params' => $form_params
            ]);

            return $this->prepareResponse($request);
        });
    }

    /**
     * Метод предназначен для передачи заказа из интернет-магазина или приложения.
     *
     * @param array $order
     * @return object
     */
    public function newOrderFromArray(array $order)
    {
        return $this->call(function () use ($order) {
            $form_params = $order;
            $form_params['secret'] = $this->secret;

            $request = $this->client->post('?new_order', [
                'form_params' => $form_params
            ]);

            return $this->prepareResponse($request);
        });
    }

    /**
     * Метод предназначен для получения информации о клиенте, форма проверки может быть установлена
     * в личном кабинете интернет-магазина.
     * Запрещается циклический поиск клиентов.
     *
     * @param $client_phone
     * @return object
     */
    public function getClient($client_phone)
    {
        return $this->call(function () use ($client_phone) {
            $request = $this->client->post('?get_client', [
                'form_params' => [
                    'secret'       => $this->secret,
                    'client_phone' => $client_phone
                ]
            ]);

            return $this->prepareResponse($request);
        });
    }

    /**
     * Метод предназначен для проверки сертификата, форма проверки может
     * быть установлена в личном кабинете интернет-магазина.
     * Запрещается циклическая проверка сертификата.
     *
     * @param $certificate
     * @return object
     */
    public function getCertificate($certificate)
    {
        return $this->call(function () use ($certificate) {
            $request = $this->client->post('?get_certificate', [
                'form_params' => [
                    'secret'      => $this->secret,
                    'certificate' => $certificate
                ]
            ]);

            return $this->prepareResponse($request);
        });
    }

    /**
     * Метод предназначен для получения списка товаров и синхронизации их с интернет-магазином.
     * Выгружаются только товары, имеющие артикул.
     *
     * @return object
     */
    public function getProducts()
    {
        return $this->call(function () {
            $request = $this->client->post('?get_products', [
                'form_params' => [
                    'secret' => $this->secret,
                ]
            ]);

            return $this->prepareResponse($request);
        });
    }

    private function prepareResponse(Response $response)
    {
        $data = $response->getBody()->getContents();
        $data = is_json($data) ? json_decode($data) : $data;

        return (object)[
            'ok'          => $data->result === 'success',
            'status_code' => $response->getStatusCode(),
            'response'   => $response,
            'data'        => $data
        ];
    }

    private function call($function)
    {
        try {
            return $function();
        } catch (\Exception $exception) {
            $response = $exception->getResponse();
            return $this->prepareResponse($response);
        }
    }

    public function getProxy()
    {
        return $this->proxy;
    }

}
