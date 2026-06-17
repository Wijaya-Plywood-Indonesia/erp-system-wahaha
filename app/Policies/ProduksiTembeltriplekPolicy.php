<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ProduksiTembelTriplek;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProduksiTembelTriplekPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ProduksiTembelTriplek');
    }

    public function view(AuthUser $authUser, ProduksiTembelTriplek $produksiTembelTriplek): bool
    {
        return $authUser->can('View:ProduksiTembelTriplek');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ProduksiTembelTriplek');
    }

    public function update(AuthUser $authUser, ProduksiTembelTriplek $produksiTembelTriplek): bool
    {
        return $authUser->can('Update:ProduksiTembelTriplek');
    }

    public function delete(AuthUser $authUser, ProduksiTembelTriplek $produksiTembelTriplek): bool
    {
        return $authUser->can('Delete:ProduksiTembelTriplek');
    }

    public function restore(AuthUser $authUser, ProduksiTembelTriplek $produksiTembelTriplek): bool
    {
        return $authUser->can('Restore:ProduksiTembelTriplek');
    }

    public function forceDelete(AuthUser $authUser, ProduksiTembelTriplek $produksiTembelTriplek): bool
    {
        return $authUser->can('ForceDelete:ProduksiTembelTriplek');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ProduksiTembelTriplek');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ProduksiTembelTriplek');
    }

    public function replicate(AuthUser $authUser, ProduksiTembelTriplek $produksiTembelTriplek): bool
    {
        return $authUser->can('Replicate:ProduksiTembelTriplek');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ProduksiTembelTriplek');
    }

}