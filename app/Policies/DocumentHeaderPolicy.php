<?php

namespace App\Policies;

use App\Models\DocumentHeader;
use App\Models\User;

class DocumentHeaderPolicy
{
    /**
     * All authenticated users can view documents.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, DocumentHeader $document): bool
    {
        return true;
    }

    /**
     * Admin, Manager, and Cashier can create documents.
     * Warehouse role can only create stock documents.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('documents.create');
    }

    /**
     * Only draft documents can be edited.
     * Users with only stock permissions can only edit stock documents.
     */
    public function update(User $user, DocumentHeader $document): bool
    {
        if ($document->status !== 'draft') {
            return false;
        }

        if (!$user->hasPermission('documents.update') && $user->hasPermission('stock.manage')) {
            return $document->isStockDocument();
        }

        return $user->hasPermission('documents.update');
    }

    /**
     * Only users with documents.delete permission can delete drafts.
     */
    public function delete(User $user, DocumentHeader $document): bool
    {
        if ($document->status !== 'draft') {
            return false;
        }

        return $user->hasPermission('documents.delete');
    }

    /**
     * Users with documents.confirm permission can confirm documents.
     */
    public function confirm(User $user, DocumentHeader $document): bool
    {
        if ($document->status !== 'draft') {
            return false;
        }

        return $user->hasPermission('documents.confirm');
    }

    /**
     * Users with documents.cancel permission can cancel documents.
     */
    public function cancel(User $user, DocumentHeader $document): bool
    {
        if (in_array($document->status, ['cancelled', 'paid'])) {
            return false;
        }

        return $user->hasPermission('documents.cancel');
    }
}
