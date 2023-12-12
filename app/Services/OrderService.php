<?php

namespace App\Services;

use App\Models\Affiliate;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\User;

class OrderService
{
    public function __construct(
        protected AffiliateService $affiliateService
    ) {
    }

    /**
     * Process an order and log any commissions.
     * This should create a new affiliate if the customer_email is not already associated with one.
     * This method should also ignore duplicates based on order_id.
     *
     * @param  array{order_id: string, subtotal_price: float, merchant_domain: string, discount_code: string, customer_email: string, customer_name: string} $data
     * @return void
     */
    public function processOrder(array $data)
    {
        if (Order::where('id', $data['order_id'])->exists()) {
            return;
        }
        $affiliate = $this->findOrCreateAffiliate($data);

        $this->affiliateService->register(
            Merchant::where('domain', $data['merchant_domain'])->first(),
            $data['customer_email'],
            $data['customer_name'],
            0.1
        );

        Order::create([
            'order_id' => $data['order_id'],
            'subtotal' => $data['subtotal_price'],
            'affiliate_id' => $affiliate->id,
            'merchant_id' => $affiliate->merchant_id,
            'commission_owed' => $data['subtotal_price'] *  0.1
        ]);
    }

    protected function findOrCreateAffiliate(array $data): Affiliate
    {
        return Affiliate::firstOrCreate(
            [
                'user_id' => $this->findOrCreateUser($data)->id,
                'merchant_id' => Merchant::where('domain', $data['merchant_domain'])->value('id'),
            ],
            [
                'discount_code' => $data['discount_code'],
                'commission_rate' => 0.1,
            ]
        );
    }

    protected function findOrCreateUser(array $data): User
    {
        return User::firstOrCreate(
            ['email' => $data['customer_email']],
            [
                'name' => $data['customer_name'],
                'type' => User::TYPE_AFFILIATE,
            ]
        );
    }
}
