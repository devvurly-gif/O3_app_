<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Payment $payment): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('payments.create');
    }

    public function delete(User $user, Payment $payment): bool
    {
        return $user->hasPermission('payments.delete');
    }
}
