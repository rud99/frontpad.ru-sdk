<?php

namespace Kolirt\Frontpad\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Kolirt\Frontpad\Frontpad;
use Kolirt\Frontpad\Order;
use Kolirt\Frontpad\Product;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class FrontpadTest extends TestCase
{

    /**
     * Запросы, перехваченные history-middleware.
     *
     * @var array
     */
    private $transactions = [];

    /**
     * Собирает Frontpad, транспортом которому служит MockHandler.
     *
     * Клиент подставляется рефлексией: свойство $client приватное, а точки
     * подмены пакет пока не предоставляет (дефект D4 в плане MR).
     *
     * @param array $queue      ответы и/или исключения для MockHandler
     * @param array $middleware дополнительные middleware для стека
     * @return Frontpad
     */
    private function frontpad(array $queue, array $middleware = [])
    {
        $frontpad = new Frontpad('secret-token');

        $stack = HandlerStack::create(new MockHandler($queue));
        $stack->push(Middleware::history($this->transactions));

        foreach ($middleware as $item) {
            $stack->push($item);
        }

        $property = new ReflectionProperty(Frontpad::class, 'client');
        $property->setAccessible(true);
        $property->setValue($frontpad, new Client([
            'base_uri' => 'https://app.frontpad.ru/api/index.php',
            'handler'  => $stack,
        ]));

        return $frontpad;
    }

    /**
     * Кейс 1. Успешный ответ API отдаётся как ok.
     *
     * Значения в фикстуре строковые: именно так их отдаёт FrontPad, см. примеры
     * ответов в документации API.
     */
    public function test_successful_response_is_reported_as_ok()
    {
        $frontpad = $this->frontpad([
            new Response(200, [], '{"result":"success","order_id":"1000020","order_number":"143"}'),
        ]);

        $result = $frontpad->newOrder(new Order);

        $this->assertTrue($result->ok);
        $this->assertSame(200, $result->status_code);
        $this->assertSame('1000020', $result->data->order_id);
        $this->assertSame('143', $result->data->order_number);
    }

    /**
     * Кейс 2. Ошибка уровня API отдаётся как не-ok, полезная нагрузка сохраняется.
     */
    public function test_api_error_is_reported_as_not_ok()
    {
        $frontpad = $this->frontpad([
            new Response(200, [], '{"result":"error","error":"invalid_products"}'),
        ]);

        $result = $frontpad->getProducts();

        $this->assertFalse($result->ok);
        $this->assertSame(200, $result->status_code);
        $this->assertSame('invalid_products', $result->data->error);
    }

    /**
     * Успешный ответ с предупреждениями остаётся успешным.
     *
     * Документация допускает result=success вместе с массивом warnings:
     * заказ создан, но часть артикулов, отметок, дата предзаказа или сертификат
     * были отвергнуты. Вызывающий код должен видеть и ok, и предупреждения.
     */
    public function test_successful_response_with_warnings_is_still_ok()
    {
        $frontpad = $this->frontpad([
            new Response(200, [], '{"result":"success","order_id":"1000020","warnings":{"invalid_product_keys":{"0":"1","1":"5"}}}'),
        ]);

        $result = $frontpad->newOrder(new Order);

        $this->assertTrue($result->ok);
        $this->assertSame('1000020', $result->data->order_id);
        $this->assertSame('1', $result->data->warnings->invalid_product_keys->{'0'});
        $this->assertSame('5', $result->data->warnings->invalid_product_keys->{'1'});
    }

    /**
     * Кейс 3. Не-JSON тело не должно ломать пакет (дефект D2).
     *
     * Воспроизводит страницу 502, отданную обратным прокси перед FrontPad.
     */
    public function test_non_json_body_does_not_break_the_response()
    {
        $frontpad = $this->frontpad([
            new Response(200, [], '<html><body>502 Bad Gateway</body></html>'),
        ]);

        $result = $frontpad->getProducts();

        $this->assertFalse($result->ok);
        $this->assertIsString($result->data);
        $this->assertStringContainsString('502', $result->data);
    }

    /**
     * Кейс 4. Валидный JSON-скаляр декодируется не в объект и тоже не должен
     * ломать ответ (дефект D2).
     */
    public function test_json_scalar_body_does_not_break_the_response()
    {
        $frontpad = $this->frontpad([
            new Response(200, [], '"5"'),
        ]);

        $result = $frontpad->getProducts();

        $this->assertFalse($result->ok);
        $this->assertSame('5', $result->data);
    }

    /**
     * Кейс 5. HTTP-ошибка с JSON-телом сохраняет свой статус-код.
     *
     * Guzzle бросает здесь BadResponseException, так что это единственная
     * ветка call(), которая и должна достать ответ из исключения.
     */
    public function test_http_error_with_json_body_keeps_status_code()
    {
        $frontpad = $this->frontpad([
            new Response(403, [], '{"result":"error","error":"invalid_secret"}'),
        ]);

        $result = $frontpad->getClient('79000000000');

        $this->assertFalse($result->ok);
        $this->assertSame(403, $result->status_code);
        $this->assertSame('invalid_secret', $result->data->error);
    }

    /**
     * Кейс 6. HTTP-ошибка с пустым телом не должна ломать ответ.
     */
    public function test_http_error_with_empty_body_does_not_break_the_response()
    {
        $frontpad = $this->frontpad([
            new Response(500, [], ''),
        ]);

        $result = $frontpad->getProducts();

        $this->assertFalse($result->ok);
        $this->assertSame(500, $result->status_code);
        $this->assertSame('', $result->data);
    }

    /**
     * Кейс 7. Ошибка соединения должна доходить до вызывающего кода
     * перехватываемым исключением Guzzle (дефект D1).
     *
     * Начиная с Guzzle 7 ConnectException унаследован от TransferException
     * и метода getResponse() не имеет, поэтому безусловный вызов этого метода
     * в call() превращает любую сетевую ошибку в фатальный Error.
     */
    public function test_connect_exception_is_rethrown()
    {
        $frontpad = $this->frontpad([
            new ConnectException(
                'cURL error 7: Failed to connect to app.frontpad.ru port 443',
                new Request('POST', '?get_products')
            ),
        ]);

        $this->expectException(ConnectException::class);

        $frontpad->getProducts();
    }

    /**
     * Кейс 8. Исключение вызывающего приложения должно пролетать насквозь
     * без изменений (дефект D1).
     *
     * Guzzle прокручивает процессную очередь промисов внутри своего
     * curl-обработчика, поэтому внутри вызова клиента может всплыть
     * исключение, никак не связанное с FrontPad. Пакет не должен подменять
     * его своим и уничтожать стек вызовов.
     */
    public function test_foreign_exception_is_not_swallowed()
    {
        $frontpad = $this->frontpad([
            new \RuntimeException('duplicate key value violates unique constraint'),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('duplicate key value violates unique constraint');

        $frontpad->getProducts();
    }

    /**
     * Кейс 9. Тело должно читаться независимо от позиции в стриме (дефект D6).
     *
     * Логирующий или retry-middleware мог уже вычитать стрим — тогда
     * getContents() вернёт пустую строку. Фикстура повторяет формат ответа
     * get_products из документации: списки приходят объектами с числовыми
     * ключами, а не массивами.
     */
    public function test_body_consumed_by_middleware_is_still_parsed()
    {
        $drainBody = function (callable $handler) {
            return function ($request, array $options) use ($handler) {
                return $handler($request, $options)->then(function ($response) {
                    // имитируем middleware, который уже прочитал тело ответа
                    $response->getBody()->getContents();

                    return $response;
                });
            };
        };

        $frontpad = $this->frontpad([
            new Response(200, [], '{"result":"success","product_id":{"0":"001"},"name":{"0":"Филадельфия"},"price":{"0":"250"}}'),
        ], [$drainBody]);

        $result = $frontpad->getProducts();

        $this->assertTrue($result->ok);
        $this->assertSame('001', $result->data->product_id->{'0'});
        $this->assertSame('250', $result->data->price->{'0'});
    }

    /**
     * Кейс 10. newOrder() должен и дальше отправлять тот формат формы,
     * который ждёт FrontPad.
     *
     * Фиксирует формат запроса, чтобы правки не изменили его случайно.
     */
    public function test_new_order_sends_expected_form_params()
    {
        $frontpad = $this->frontpad([
            new Response(200, [], '{"result":"success","order_id":"1000020"}'),
        ]);

        $order = new Order;
        $order->setName('Oleh');
        $order->setPhone('79000000000');
        $order->setCertificate('CERT-12345');

        $product = new Product;
        $product->setIndex($order->getNewProductIndex());
        $product->setId(100);
        $product->setQuantity(2);
        $product->setPrice(100);
        $order->addProduct($product);

        $modifier = new Product;
        $modifier->setIndex($order->getNewProductIndex());
        $modifier->setModIndex($product->getIndex());
        $modifier->setId(101);
        $modifier->setQuantity(1);
        $order->addProduct($modifier);

        $frontpad->newOrder($order);

        $this->assertCount(1, $this->transactions);

        $request = $this->transactions[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('new_order', $request->getUri()->getQuery());

        parse_str((string)$request->getBody(), $params);

        $this->assertSame('secret-token', $params['secret']);
        $this->assertSame('Oleh', $params['name']);
        $this->assertSame('79000000000', $params['phone']);
        $this->assertSame('CERT-12345', $params['certificate']);

        $this->assertSame(['100', '101'], $params['product']);
        $this->assertSame(['2', '1'], $params['product_kol']);
        $this->assertSame(['100'], $params['product_price']);
        $this->assertSame([1 => '0'], $params['product_mod']);
    }

}
