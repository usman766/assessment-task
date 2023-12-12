<?php

namespace App\Services;

use App\Exceptions\AffiliateCreateException;
use App\Mail\AffiliateCreated;
use App\Models\Affiliate;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class AffiliateService
{
    public function __construct(
        protected ApiService $apiService
    ) {}

    /**
     * Create a new affiliate for the merchant with the given commission rate.
     *
     * @param  Merchant $merchant
     * @param  string $email
     * @param  string $name
     * @param  float $commissionRate
     * @return Affiliate
     */
    public function register(Merchant $merchant, string $email, string $name, float $commissionRate): Affiliate
    {
        $this->validateEmail($email);

        $affiliate = new Affiliate([
            'merchant_id' => $merchant->id,
            'user_id' => $merchant->user_id,
            'commission_rate' => $commissionRate,
            'discount_code' => $this->apiService->createDiscountCode($merchant)['code'],
        ]);

        $affiliate->save();

        Mail::to($email)->send(new AffiliateCreated($affiliate));

        return $affiliate;
    }

    private function validateEmail(string $email): void
    {
        $merchantCount = $this->getUserCountByEmailAndType($email, User::TYPE_MERCHANT);
        $affiliateCount = $this->getUserCountByEmailAndType($email, User::TYPE_AFFILIATE);

        if ($merchantCount > 0) {
            throw new AffiliateCreateException('Email is already in use as a merchant.');
        }

        if ($affiliateCount > 0) {
            throw new AffiliateCreateException('Email is already in use as an affiliate.');
        }
    }

    private function getUserCountByEmailAndType(string $email, string $type): int
    {
        return User::where('email', $email)->where('type', $type)->count();
    }
}
