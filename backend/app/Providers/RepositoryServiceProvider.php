<?php

namespace App\Providers;

use App\Contracts\Repositories\AccountRepositoryInterface;
use App\Contracts\Repositories\CashRegisterSessionRepositoryInterface;
use App\Contracts\Repositories\DashboardRepositoryInterface;
use App\Contracts\Repositories\InvoiceRepositoryInterface;
use App\Contracts\Repositories\JournalEntryRepositoryInterface;
use App\Contracts\Repositories\ProductRepositoryInterface;
use App\Contracts\Repositories\PurchaseOrderRepositoryInterface;
use App\Contracts\Repositories\ReportRepositoryInterface;
use App\Contracts\Repositories\RoleRepositoryInterface;
use App\Contracts\Repositories\SalesReturnRepositoryInterface;
use App\Contracts\Repositories\SettingRepositoryInterface;
use App\Contracts\Repositories\StockMovementRepositoryInterface;
use App\Contracts\Repositories\SupplierAccountRepositoryInterface;
use App\Contracts\Repositories\SupplierPaymentRepositoryInterface;
use App\Contracts\Repositories\SupplierRepositoryInterface;
use App\Contracts\Repositories\UserRepositoryInterface;
use App\Repositories\AccountRepository;
use App\Repositories\CashRegisterSessionRepository;
use App\Repositories\DashboardRepository;
use App\Repositories\InvoiceRepository;
use App\Repositories\JournalEntryRepository;
use App\Repositories\ProductRepository;
use App\Repositories\PurchaseOrderRepository;
use App\Repositories\ReportRepository;
use App\Repositories\RoleRepository;
use App\Repositories\SalesReturnRepository;
use App\Repositories\SettingRepository;
use App\Repositories\StockMovementRepository;
use App\Repositories\SupplierAccountRepository;
use App\Repositories\SupplierPaymentRepository;
use App\Repositories\SupplierRepository;
use App\Repositories\UserRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $bindings = [
            ProductRepositoryInterface::class => ProductRepository::class,
            InvoiceRepositoryInterface::class => InvoiceRepository::class,
            SalesReturnRepositoryInterface::class => SalesReturnRepository::class,
            PurchaseOrderRepositoryInterface::class => PurchaseOrderRepository::class,
            SupplierRepositoryInterface::class => SupplierRepository::class,
            SupplierPaymentRepositoryInterface::class => SupplierPaymentRepository::class,
            SupplierAccountRepositoryInterface::class => SupplierAccountRepository::class,
            UserRepositoryInterface::class => UserRepository::class,
            RoleRepositoryInterface::class => RoleRepository::class,
            AccountRepositoryInterface::class => AccountRepository::class,
            JournalEntryRepositoryInterface::class => JournalEntryRepository::class,
            SettingRepositoryInterface::class => SettingRepository::class,
            CashRegisterSessionRepositoryInterface::class => CashRegisterSessionRepository::class,
            StockMovementRepositoryInterface::class => StockMovementRepository::class,
            DashboardRepositoryInterface::class => DashboardRepository::class,
            ReportRepositoryInterface::class => ReportRepository::class,
        ];

        foreach ($bindings as $interface => $implementation) {
            $this->app->bind($interface, $implementation);
        }
    }
}
