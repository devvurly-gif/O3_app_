<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

use App\Repositories\Contracts\BrandRepositoryInterface;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use App\Repositories\Contracts\DocumentFooterRepositoryInterface;
use App\Repositories\Contracts\DocumentHeaderRepositoryInterface;
use App\Repositories\Contracts\DocumentIncrementorRepositoryInterface;
use App\Repositories\Contracts\DocumentLigneRepositoryInterface;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use App\Repositories\Contracts\PermissionRepositoryInterface;
use App\Repositories\Contracts\PosSessionRepositoryInterface;
use App\Repositories\Contracts\PosTerminalRepositoryInterface;
use App\Repositories\Contracts\RoleRepositoryInterface;
use App\Repositories\Contracts\ProductImageRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\SettingRepositoryInterface;
use App\Repositories\Contracts\StockMouvementRepositoryInterface;
use App\Repositories\Contracts\StructureIncrementorRepositoryInterface;
use App\Repositories\Contracts\ThirdPartnerRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Contracts\WarehouseRepositoryInterface;
use App\Repositories\Contracts\WarehouseStockRepositoryInterface;
use App\Repositories\Contracts\WarehouseTransferRepositoryInterface;

use App\Repositories\Eloquent\BrandRepository;
use App\Repositories\Eloquent\CategoryRepository;
use App\Repositories\Eloquent\DocumentFooterRepository;
use App\Repositories\Eloquent\DocumentHeaderRepository;
use App\Repositories\Eloquent\DocumentIncrementorRepository;
use App\Repositories\Eloquent\DocumentLigneRepository;
use App\Repositories\Eloquent\PaymentRepository;
use App\Repositories\Eloquent\PermissionRepository;
use App\Repositories\Eloquent\PosSessionRepository;
use App\Repositories\Eloquent\PosTerminalRepository;
use App\Repositories\Eloquent\RoleRepository;
use App\Repositories\Eloquent\ProductImageRepository;
use App\Repositories\Eloquent\ProductRepository;
use App\Repositories\Eloquent\SettingRepository;
use App\Repositories\Eloquent\StockMouvementRepository;
use App\Repositories\Eloquent\StructureIncrementorRepository;
use App\Repositories\Eloquent\ThirdPartnerRepository;
use App\Repositories\Eloquent\UserRepository;
use App\Repositories\Eloquent\WarehouseRepository;
use App\Repositories\Eloquent\WarehouseStockRepository;
use App\Repositories\Eloquent\WarehouseTransferRepository;

class RepositoryServiceProvider extends ServiceProvider
{
    protected array $repositories = [
        BrandRepositoryInterface::class               => BrandRepository::class,
        CategoryRepositoryInterface::class             => CategoryRepository::class,
        DocumentFooterRepositoryInterface::class       => DocumentFooterRepository::class,
        DocumentHeaderRepositoryInterface::class       => DocumentHeaderRepository::class,
        DocumentIncrementorRepositoryInterface::class  => DocumentIncrementorRepository::class,
        DocumentLigneRepositoryInterface::class        => DocumentLigneRepository::class,
        PaymentRepositoryInterface::class              => PaymentRepository::class,
        PermissionRepositoryInterface::class           => PermissionRepository::class,
        PosSessionRepositoryInterface::class           => PosSessionRepository::class,
        PosTerminalRepositoryInterface::class          => PosTerminalRepository::class,
        RoleRepositoryInterface::class                 => RoleRepository::class,
        ProductImageRepositoryInterface::class         => ProductImageRepository::class,
        ProductRepositoryInterface::class              => ProductRepository::class,
        SettingRepositoryInterface::class              => SettingRepository::class,
        StockMouvementRepositoryInterface::class       => StockMouvementRepository::class,
        StructureIncrementorRepositoryInterface::class => StructureIncrementorRepository::class,
        ThirdPartnerRepositoryInterface::class         => ThirdPartnerRepository::class,
        UserRepositoryInterface::class                 => UserRepository::class,
        WarehouseRepositoryInterface::class            => WarehouseRepository::class,
        WarehouseStockRepositoryInterface::class       => WarehouseStockRepository::class,
        WarehouseTransferRepositoryInterface::class    => WarehouseTransferRepository::class,
    ];

    public function register(): void
    {
        foreach ($this->repositories as $interface => $implementation) {
            $this->app->bind($interface, $implementation);
        }
    }
}
