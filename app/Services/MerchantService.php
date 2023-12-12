<?php

namespace App\Services;

use App\Jobs\PayoutOrderJob;
use App\Models\Affiliate;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;

class MerchantService
{
    /**
     * Register a new user and associated merchant.
     * Hint: Use the password field to store the API key.
     * Hint: Be sure to set the correct user type according to the constants in the User model.
     *
     * @param array{domain: string, name: string, email: string, api_key: string} $data
     * @return Merchant
     */
    public function register(array $data): Merchant
    {
        try {
            $user = $this->createMerchantUser($data);

            $merchant = new Merchant();
            $merchant->user_id = $user->id;
            $merchant->domain = $data['domain'];
            $merchant->display_name = $data['name'];
            $merchant->turn_customers_into_affiliates = true;
            $merchant->default_commission_rate = 0.1;
            $merchant->save();
            return  $merchant;
        } catch (\Exception $e) {
            return response()->json(['error' => $e], 500);
        }
    }

    /**
     * Update the user
     *
     * @param array{domain: string, name: string, email: string, api_key: string} $data
     * @return void
     */
    public function updateMerchant(User $user, array $data)
    {
        $this->updateUserAndMerchant($user, $data);
    }

    /**
     * Find a merchant by their email.
     * Hint: You'll need to look up the user first.
     *
     * @param string $email
     * @return Merchant|null
     */
    public function findMerchantByEmail(string $email): ?Merchant
    {
        $user = User::where([
            ['email', $email],
            ['type', User::TYPE_MERCHANT]
        ])->first();
        return optional($user)->merchant;
    }

    /**
     * Pay out all of an affiliate's orders.
     * Hint: You'll need to dispatch the job for each unpaid order.
     *
     * @param Affiliate $affiliate
     * @return void
     */
    public function payout(Affiliate $affiliate)
    {
        $unpaidOrders = Order::where([
            ['affiliate_id', '=', $affiliate->id],
            ['payout_status', '=', Order::STATUS_UNPAID],
        ])
            ->get();

        $unpaidOrders->each(function ($order) {
            dispatch(new PayoutOrderJob($order));
        });
    }
    /**
     * Get order statistics for a specific time range.
     *
     * @param Carbon $from Start date of the time range.
     * @param Carbon $to   End date of the time range.
     *
     * @return array{
     *     count: int,               // The total number of orders within the specified time range.
     *     commission_owed: float,   // Total commission owed to affiliates for orders within the time range.
     *     revenue: float            // Total revenue generated from orders within the time range.
     * }
     */
    public function getOrderStats(Carbon $from, Carbon $to): array
    {
        $orders = Order::whereBetween('created_at', [$from, $to])->get();

        $affiliateCommissionOwed = $orders->whereNotNull('affiliate_id')->sum('commission_owed');

        return [
            'count' => $orders->count(),
            'commission_owed' => $affiliateCommissionOwed,
            'revenue' => $orders->sum('subtotal'),
        ];
    }
    /**
     * Update the user and associated merchant with the provided data.
     *
     * @param User $user The user to be updated.
     * @param array $data The data to update the user and merchant.
     *
     * @return void
     */
    private function updateUserAndMerchant(User $user, array $data)
    {
        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' =>  Hash::make($data['api_key']),
        ]);

        $user->merchant->update([
            'domain' => $data['domain'],
            'display_name' => $data['name'],
        ]);
    }

    /**
     * Create a new user for the merchant.
     *
     * @param array $data
     * @return User
     */
    private function createMerchantUser(array $data): User
    {

        $user = new User();

        $user->name = $data['name'];
        $user->email =  $data['email'];
        $user->password =  ($data['api_key']);
        $user->type =  User::TYPE_MERCHANT;
        $user->save();

        return $user;
    }
}
