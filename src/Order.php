<?php

namespace Kolirt\Frontpad;

class Order
{

    private $name;
    private $phone;
    private $street;
    private $home;
    private $pod;
    private $et;
    private $apart;
    private $descr;
    private $person;
    private $datetime;
    private $mail;
    private $pay;

    private $products = [];
    private $sale;
    private $sale_amount;
    private $tags = [];
    private $hook_status = [];
    private $hook_url;
    private $affiliate;
    private $point;
    private $channel;
    private $certificate;


    /**
     * @param mixed $name
     * @return Order
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @param mixed $phone
     * @return Order
     */
    public function setPhone($phone)
    {
        $this->phone = $phone;
        return $this;
    }

    /**
     * @param mixed $street
     * @return Order
     */
    public function setStreet($street)
    {
        $this->street = $street;
        return $this;
    }

    /**
     * @param mixed $home
     * @return Order
     */
    public function setHome($home)
    {
        $this->home = $home;
        return $this;
    }

    /**
     * @param mixed $pod
     * @return Order
     */
    public function setPod($pod)
    {
        $this->pod = $pod;
        return $this;
    }

    /**
     * @param mixed $et
     * @return Order
     */
    public function setEt($et)
    {
        $this->et = $et;
        return $this;
    }

    /**
     * @param mixed $apart
     * @return Order
     */
    public function setApart($apart)
    {
        $this->apart = $apart;
        return $this;
    }

    /**
     * @param mixed $descr
     * @return Order
     */
    public function setDescr($descr)
    {
        $this->descr = $descr;
        return $this;
    }

    /**
     * @param mixed $person
     * @return Order
     */
    public function setPerson($person)
    {
        $this->person = $person;
        return $this;
    }

    /**
     * @param mixed $datetime
     * @return Order
     */
    public function setDatetime($datetime)
    {
        $this->datetime = $datetime;
        return $this;
    }

    /**
     * @param mixed $mail
     * @return Order
     */
    public function setMail($mail)
    {
        $this->mail = $mail;
        return $this;
    }

    /**
     * @param mixed $pay
     * @return Order
     */
    public function setPay($pay)
    {
        $this->pay = $pay;
        return $this;
    }

    /**
     * @param $sale
     * @return Order
     */
    public function setSale($sale)
    {
        $this->sale = $sale;
        return $this;
    }

    /**
     * @param $sale_amount
     * @return Order
     */
    public function setSaleAmount($sale_amount)
    {
        $this->sale_amount = $sale_amount;
        return $this;
    }

    /**
     * @param $tag
     * @return Order
     */
    public function adTag($tag)
    {
        $this->tags[] = $tag;
        return $this;
    }

    /**
     * @param $hook_status
     * @return Order
     */
    public function addHookStatus($hook_status)
    {
        $this->hook_status[] = $hook_status;
        return $this;
    }

    /**
     * @param mixed $affiliate
     * @return Order
     */
    public function setAffiliate($affiliate)
    {
        $this->affiliate = $affiliate;
        return $this;
    }

    /**
     * @param mixed $point
     * @return Order
     */
    public function setPoint($point)
    {
        $this->point = $point;
        return $this;
    }

    /**
     * @param mixed $channel
     * @return Order
     */
    public function setChannel($channel)
    {
        $this->channel = $channel;
        return $this;
    }

    /**
     * @param mixed $hook_url
     * @return Order
     */
    public function setHookUrl($hook_url)
    {
        $this->hook_url = $hook_url;
        return $this;
    }

    /**
     * @param mixed $certificate
     * @return Order
     */
    public function setCertificate($certificate)
    {
        $this->certificate = $certificate;
        return $this;
    }

    /**
     * @param Product $product
     * @return $this
     */
    public function addProduct(Product $product)
    {
        $this->products[] = $product;
        return $this;
    }

    public function getNewProductIndex()
    {
        return count($this->products);
    }

    public function render()
    {
        $result = [
            'name'  => $this->name,
            'phone' => $this->phone,

            'product'       => [],
            'product_kol'   => [],
            'product_price' => [],
            'product_mod'   => [],

            'tags'        => $this->tags,
            'hook_status' => $this->hook_status,
            'affiliate'   => $this->affiliate,
            'point'       => $this->point,
            'channel'     => $this->channel,
        ];

        $this->street && $result['street'] = $this->street;
        $this->home && $result['home'] = $this->home;
        $this->pod && $result['pod'] = $this->pod;
        $this->et && $result['et'] = $this->et;
        $this->apart && $result['apart'] = $this->apart;
        $this->descr && $result['descr'] = $this->descr;
        $this->person && $result['person'] = $this->person;
        $this->mail && $result['mail'] = $this->mail;
        $this->pay && $result['pay'] = $this->pay;
        $this->datetime && $result['datetime'] = $this->datetime;
        $this->sale && $result['sale'] = $this->sale;
        $this->sale_amount && $result['sale_amount'] = $this->sale_amount;
        $this->hook_url && $result['hook_url'] = $this->hook_url;
        $this->certificate && $result['certificate'] = $this->certificate;

        $product = [];
        $product_kol = [];
        $product_price = [];
        $product_mod = [];

        foreach ($this->products as $prod) {
            $product[$prod->getIndex()] = $prod->getId();
            $product_kol[$prod->getIndex()] = $prod->getQuantity();

            if ($prod->getPrice() !== null) {
                $product_price[$prod->getIndex()] = $prod->getPrice();
            }

            if ($prod->getModIndex() !== null) {
                $product_mod[$prod->getIndex()] = $prod->getModIndex();
            }
        }

        $result['product'] = $product;
        $result['product_kol'] = $product_kol;
        $result['product_price'] = $product_price;
        $result['product_mod'] = $product_mod;

        return $result;
    }

}
