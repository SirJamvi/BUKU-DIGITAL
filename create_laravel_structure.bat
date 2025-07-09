@echo off
echo Creating Laravel Project Structure...
echo.




REM Create app subdirectories and files
echo Creating app structure...

REM Console Commands
mkdir "app\Console\Commands" 2>nul
echo. > "app\Console\Commands\GenerateReports.php"
echo. > "app\Console\Commands\BackupDatabase.php"
echo. > "app\Console\Commands\CleanupSessions.php"

REM Exceptions
mkdir "app\Exceptions" 2>nul
echo. > "app\Exceptions\UnauthorizedException.php"
echo. > "app\Exceptions\InsufficientStockException.php"

REM Http Controllers - Admin
mkdir "app\Http\Controllers\Admin" 2>nul
echo. > "app\Http\Controllers\Admin\DashboardController.php"
echo. > "app\Http\Controllers\Admin\UserController.php"
echo. > "app\Http\Controllers\Admin\ProductController.php"
echo. > "app\Http\Controllers\Admin\CategoryController.php"
echo. > "app\Http\Controllers\Admin\InventoryController.php"
echo. > "app\Http\Controllers\Admin\FinancialController.php"
echo. > "app\Http\Controllers\Admin\FundAllocationController.php"
echo. > "app\Http\Controllers\Admin\ReportController.php"
echo. > "app\Http\Controllers\Admin\BusinessIntelligenceController.php"
echo. > "app\Http\Controllers\Admin\SettingsController.php"

REM Http Controllers - Kasir
mkdir "app\Http\Controllers\Kasir" 2>nul
echo. > "app\Http\Controllers\Kasir\DashboardController.php"
echo. > "app\Http\Controllers\Kasir\PosController.php"
echo. > "app\Http\Controllers\Kasir\CustomerController.php"
echo. > "app\Http\Controllers\Kasir\TransactionController.php"
echo. > "app\Http\Controllers\Kasir\ReportController.php"

REM Http Controllers - Auth
mkdir "app\Http\Controllers\Auth" 2>nul
echo. > "app\Http\Controllers\Auth\LoginController.php"
echo. > "app\Http\Controllers\Auth\RegisterController.php"
echo. > "app\Http\Controllers\Auth\ForgotPasswordController.php"
echo. > "app\Http\Controllers\Auth\ResetPasswordController.php"

REM Http Controllers - API V1 Admin
mkdir "app\Http\Controllers\Api\V1\Admin" 2>nul
echo. > "app\Http\Controllers\Api\V1\Admin\DashboardApiController.php"
echo. > "app\Http\Controllers\Api\V1\Admin\ProductApiController.php"
echo. > "app\Http\Controllers\Api\V1\Admin\InventoryApiController.php"
echo. > "app\Http\Controllers\Api\V1\Admin\ReportApiController.php"

REM Http Controllers - API V1 Kasir
mkdir "app\Http\Controllers\Api\V1\Kasir" 2>nul
echo. > "app\Http\Controllers\Api\V1\Kasir\PosApiController.php"
echo. > "app\Http\Controllers\Api\V1\Kasir\CustomerApiController.php"
echo. > "app\Http\Controllers\Api\V1\Kasir\TransactionApiController.php"

REM Http Controllers - API V1 Auth
echo. > "app\Http\Controllers\Api\V1\AuthApiController.php"

REM Http Controllers - Main
echo. > "app\Http\Controllers\HomeController.php"

REM Http Middleware
mkdir "app\Http\Middleware" 2>nul
echo. > "app\Http\Middleware\AdminMiddleware.php"
echo. > "app\Http\Middleware\KasirMiddleware.php"
echo. > "app\Http\Middleware\RoleMiddleware.php"
echo. > "app\Http\Middleware\PermissionMiddleware.php"
echo. > "app\Http\Middleware\SessionTimeout.php"
echo. > "app\Http\Middleware\ActivityLogger.php"

REM Http Requests - Admin
mkdir "app\Http\Requests\Admin" 2>nul
echo. > "app\Http\Requests\Admin\StoreProductRequest.php"
echo. > "app\Http\Requests\Admin\UpdateProductRequest.php"
echo. > "app\Http\Requests\Admin\StoreCategoryRequest.php"
echo. > "app\Http\Requests\Admin\StoreUserRequest.php"
echo. > "app\Http\Requests\Admin\UpdateUserRequest.php"
echo. > "app\Http\Requests\Admin\FundAllocationRequest.php"
echo. > "app\Http\Requests\Admin\StoreSupplierRequest.php"

REM Http Requests - Kasir
mkdir "app\Http\Requests\Kasir" 2>nul
echo. > "app\Http\Requests\Kasir\StoreTransactionRequest.php"
echo. > "app\Http\Requests\Kasir\UpdateTransactionRequest.php"
echo. > "app\Http\Requests\Kasir\StoreCustomerRequest.php"

REM Http Requests - Auth
mkdir "app\Http\Requests\Auth" 2>nul
echo. > "app\Http\Requests\Auth\LoginRequest.php"
echo. > "app\Http\Requests\Auth\RegisterRequest.php"
echo. > "app\Http\Requests\Auth\ResetPasswordRequest.php"

REM Http Requests - Base
echo. > "app\Http\Requests\BaseRequest.php"

REM Http Resources - Admin
mkdir "app\Http\Resources\Admin" 2>nul
echo. > "app\Http\Resources\Admin\ProductResource.php"
echo. > "app\Http\Resources\Admin\CategoryResource.php"
echo. > "app\Http\Resources\Admin\InventoryResource.php"
echo. > "app\Http\Resources\Admin\UserResource.php"
echo. > "app\Http\Resources\Admin\TransactionResource.php"
echo. > "app\Http\Resources\Admin\ReportResource.php"

REM Http Resources - Kasir
mkdir "app\Http\Resources\Kasir" 2>nul
echo. > "app\Http\Resources\Kasir\ProductResource.php"
echo. > "app\Http\Resources\Kasir\CustomerResource.php"
echo. > "app\Http\Resources\Kasir\TransactionResource.php"

REM Http Resources - Base
echo. > "app\Http\Resources\BaseResource.php"

REM Jobs
mkdir "app\Jobs" 2>nul
echo. > "app\Jobs\ProcessTransaction.php"
echo. > "app\Jobs\UpdateInventory.php"
echo. > "app\Jobs\GenerateReport.php"
echo. > "app\Jobs\SendNotification.php"
echo. > "app\Jobs\BackupData.php"
echo. > "app\Jobs\CleanupOldData.php"

REM Mail
mkdir "app\Mail" 2>nul
echo. > "app\Mail\ReportGenerated.php"
echo. > "app\Mail\LowStockAlert.php"
echo. > "app\Mail\NewUserCreated.php"
echo. > "app\Mail\TransactionReceipt.php"

REM Models
mkdir "app\Models" 2>nul
echo. > "app\Models\Role.php"
echo. > "app\Models\Permission.php"
echo. > "app\Models\Product.php"
echo. > "app\Models\ProductCategory.php"
echo. > "app\Models\ProductVariant.php"
echo. > "app\Models\Inventory.php"
echo. > "app\Models\StockMovement.php"
echo. > "app\Models\Supplier.php"
echo. > "app\Models\Customer.php"
echo. > "app\Models\CustomerSegment.php"
echo. > "app\Models\Transaction.php"
echo. > "app\Models\TransactionDetail.php"
echo. > "app\Models\Payment.php"
echo. > "app\Models\CashFlow.php"
echo. > "app\Models\ExpenseCategory.php"
echo. > "app\Models\CapitalTracking.php"
echo. > "app\Models\OwnerProfit.php"
echo. > "app\Models\FundAllocationSetting.php"
echo. > "app\Models\FundAllocationHistory.php"
echo. > "app\Models\OwnerExpense.php"
echo. > "app\Models\UserSession.php"
echo. > "app\Models\UserPermission.php"
echo. > "app\Models\UserActivityLog.php"

REM Notifications
mkdir "app\Notifications" 2>nul
echo. > "app\Notifications\LowStockAlert.php"
echo. > "app\Notifications\NewTransaction.php"
echo. > "app\Notifications\DailyReport.php"
echo. > "app\Notifications\UserLogin.php"
echo. > "app\Notifications\SystemAlert.php"

REM Policies
mkdir "app\Policies" 2>nul
echo. > "app\Policies\UserPolicy.php"
echo. > "app\Policies\ProductPolicy.php"
echo. > "app\Policies\TransactionPolicy.php"
echo. > "app\Policies\ReportPolicy.php"
echo. > "app\Policies\InventoryPolicy.php"

REM Providers
mkdir "app\Providers" 2>nul
echo. > "app\Providers\PermissionServiceProvider.php"
echo. > "app\Providers\ViewServiceProvider.php"

REM Services - Admin
mkdir "app\Services\Admin" 2>nul
echo. > "app\Services\Admin\DashboardService.php"
echo. > "app\Services\Admin\ProductService.php"
echo. > "app\Services\Admin\InventoryService.php"
echo. > "app\Services\Admin\FinancialService.php"
echo. > "app\Services\Admin\FundAllocationService.php"
echo. > "app\Services\Admin\ReportService.php"
echo. > "app\Services\Admin\UserService.php"
echo. > "app\Services\Admin\BusinessIntelligenceService.php"

REM Services - Kasir
mkdir "app\Services\Kasir" 2>nul
echo. > "app\Services\Kasir\PosService.php"
echo. > "app\Services\Kasir\TransactionService.php"
echo. > "app\Services\Kasir\CustomerService.php"
echo. > "app\Services\Kasir\ReportService.php"

REM Services - Auth
mkdir "app\Services\Auth" 2>nul
echo. > "app\Services\Auth\AuthService.php"
echo. > "app\Services\Auth\PermissionService.php"
echo. > "app\Services\Auth\RoleService.php"

REM Services - Main
echo. > "app\Services\NotificationService.php"
echo. > "app\Services\BackupService.php"
echo. > "app\Services\ExportService.php"

REM Traits
mkdir "app\Traits" 2>nul
echo. > "app\Traits\HasRoles.php"
echo. > "app\Traits\HasPermissions.php"
echo. > "app\Traits\Trackable.php"
echo. > "app\Traits\Searchable.php"
echo. > "app\Traits\Exportable.php"

REM Utils
mkdir "app\Utils" 2>nul
echo. > "app\Utils\ResponseHelper.php"
echo. > "app\Utils\DateHelper.php"
echo. > "app\Utils\NumberHelper.php"
echo. > "app\Utils\ValidationHelper.php"
echo. > "app\Utils\Constants.php"

REM Config files
mkdir "config" 2>nul
echo. > "config\roles.php"
echo. > "config\permissions.php"
echo. > "config\business.php"

REM Database Factories
mkdir "database\factories" 2>nul
echo. > "database\factories\ProductFactory.php"
echo. > "database\factories\CategoryFactory.php"
echo. > "database\factories\CustomerFactory.php"
echo. > "database\factories\TransactionFactory.php"
echo. > "database\factories\InventoryFactory.php"

REM Database Migrations
mkdir "database\migrations" 2>nul
echo. > "database\migrations\2024_01_01_000000_create_users_table.php"
echo. > "database\migrations\2024_01_01_000001_create_roles_table.php"
echo. > "database\migrations\2024_01_01_000002_create_permissions_table.php"
echo. > "database\migrations\2024_01_01_000003_create_user_permissions_table.php"
echo. > "database\migrations\2024_01_01_000004_create_user_sessions_table.php"
echo. > "database\migrations\2024_01_01_000005_create_user_activity_logs_table.php"
echo. > "database\migrations\2024_01_01_000006_create_customers_table.php"
echo. > "database\migrations\2024_01_01_000007_create_customer_segments_table.php"
echo. > "database\migrations\2024_01_01_000008_create_product_categories_table.php"
echo. > "database\migrations\2024_01_01_000009_create_products_table.php"
echo. > "database\migrations\2024_01_01_000010_create_product_variants_table.php"
echo. > "database\migrations\2024_01_01_000011_create_inventory_table.php"
echo. > "database\migrations\2024_01_01_000012_create_stock_movements_table.php"
echo. > "database\migrations\2024_01_01_000013_create_suppliers_table.php"
echo. > "database\migrations\2024_01_01_000014_create_transactions_table.php"
echo. > "database\migrations\2024_01_01_000015_create_transaction_details_table.php"
echo. > "database\migrations\2024_01_01_000016_create_payments_table.php"
echo. > "database\migrations\2024_01_01_000017_create_expense_categories_table.php"
echo. > "database\migrations\2024_01_01_000018_create_cash_flow_table.php"
echo. > "database\migrations\2024_01_01_000019_create_capital_tracking_table.php"
echo. > "database\migrations\2024_01_01_000020_create_owner_profits_table.php"
echo. > "database\migrations\2024_01_01_000021_create_fund_allocation_settings_table.php"
echo. > "database\migrations\2024_01_01_000022_create_fund_allocation_history_table.php"
echo. > "database\migrations\2024_01_01_000023_create_owner_expenses_table.php"

REM Database Seeders
mkdir "database\seeders" 2>nul
echo. > "database\seeders\RoleSeeder.php"
echo. > "database\seeders\PermissionSeeder.php"
echo. > "database\seeders\UserSeeder.php"
echo. > "database\seeders\ProductCategorySeeder.php"
echo. > "database\seeders\ProductSeeder.php"
echo. > "database\seeders\CustomerSeeder.php"
echo. > "database\seeders\ExpenseCategorySeeder.php"
echo. > "database\seeders\FundAllocationSeeder.php"

REM Database Schema
mkdir "database\schema" 2>nul
echo. > "database\schema\mysql-schema.sql"

REM Public Assets - CSS Admin
mkdir "public\assets\css\admin" 2>nul
echo. > "public\assets\css\admin\dashboard.css"
echo. > "public\assets\css\admin\products.css"
echo. > "public\assets\css\admin\financial.css"
echo. > "public\assets\css\admin\reports.css"

REM Public Assets - CSS Kasir
mkdir "public\assets\css\kasir" 2>nul
echo. > "public\assets\css\kasir\dashboard.css"
echo. > "public\assets\css\kasir\pos.css"
echo. > "public\assets\css\kasir\transactions.css"

REM Public Assets - CSS Auth
mkdir "public\assets\css\auth" 2>nul
echo. > "public\assets\css\auth\login.css"

REM Public Assets - CSS Common
mkdir "public\assets\css\common" 2>nul
echo. > "public\assets\css\common\app.css"
echo. > "public\assets\css\common\sidebar.css"
echo. > "public\assets\css\common\components.css"

REM Public Assets - JS Admin
mkdir "public\assets\js\admin" 2>nul
echo. > "public\assets\js\admin\dashboard.js"
echo. > "public\assets\js\admin\products.js"
echo. > "public\assets\js\admin\fund-allocation.js"
echo. > "public\assets\js\admin\inventory.js"
echo. > "public\assets\js\admin\reports.js"

REM Public Assets - JS Kasir
mkdir "public\assets\js\kasir" 2>nul
echo. > "public\assets\js\kasir\dashboard.js"
echo. > "public\assets\js\kasir\pos.js"
echo. > "public\assets\js\kasir\transactions.js"

REM Public Assets - JS Auth
mkdir "public\assets\js\auth" 2>nul
echo. > "public\assets\js\auth\login.js"

REM Public Assets - JS Common
mkdir "public\assets\js\common" 2>nul
echo. > "public\assets\js\common\app.js"
echo. > "public\assets\js\common\utils.js"
echo. > "public\assets\js\common\components.js"

REM Public Assets - Images
mkdir "public\assets\images\logos" 2>nul
mkdir "public\assets\images\products" 2>nul
mkdir "public\assets\images\users" 2>nul
mkdir "public\assets\images\icons" 2>nul

REM Public Assets - Fonts
mkdir "public\assets\fonts" 2>nul

REM Public Uploads
mkdir "public\uploads\products" 2>nul
mkdir "public\uploads\users" 2>nul
mkdir "public\uploads\documents" 2>nul

REM Resources Lang Indonesian
mkdir "resources\lang\id" 2>nul
echo. > "resources\lang\id\auth.php"
echo. > "resources\lang\id\pagination.php"
echo. > "resources\lang\id\passwords.php"
echo. > "resources\lang\id\validation.php"

REM Resources Views - Admin Layouts
mkdir "resources\views\admin\layouts" 2>nul
echo. > "resources\views\admin\layouts\app.blade.php"
echo. > "resources\views\admin\layouts\sidebar.blade.php"
echo. > "resources\views\admin\layouts\header.blade.php"

REM Resources Views - Admin Dashboard
mkdir "resources\views\admin\dashboard" 2>nul
echo. > "resources\views\admin\dashboard\index.blade.php"

REM Resources Views - Admin Products
mkdir "resources\views\admin\products" 2>nul
echo. > "resources\views\admin\products\index.blade.php"
echo. > "resources\views\admin\products\create.blade.php"
echo. > "resources\views\admin\products\edit.blade.php"
echo. > "resources\views\admin\products\show.blade.php"

REM Resources Views - Admin Categories
mkdir "resources\views\admin\categories" 2>nul
echo. > "resources\views\admin\categories\index.blade.php"
echo. > "resources\views\admin\categories\create.blade.php"
echo. > "resources\views\admin\categories\edit.blade.php"

REM Resources Views - Admin Inventory
mkdir "resources\views\admin\inventory" 2>nul
echo. > "resources\views\admin\inventory\index.blade.php"
echo. > "resources\views\admin\inventory\stock-movements.blade.php"
echo. > "resources\views\admin\inventory\stock-opname.blade.php"

REM Resources Views - Admin Users
mkdir "resources\views\admin\users" 2>nul
echo. > "resources\views\admin\users\index.blade.php"
echo. > "resources\views\admin\users\create.blade.php"
echo. > "resources\views\admin\users\edit.blade.php"

REM Resources Views - Admin Financial
mkdir "resources\views\admin\financial" 2>nul
echo. > "resources\views\admin\financial\index.blade.php"
echo. > "resources\views\admin\financial\cash-flow.blade.php"
echo. > "resources\views\admin\financial\expenses.blade.php"
echo. > "resources\views\admin\financial\roi-analysis.blade.php"

REM Resources Views - Admin Fund Allocation
mkdir "resources\views\admin\fund-allocation" 2>nul
echo. > "resources\views\admin\fund-allocation\index.blade.php"
echo. > "resources\views\admin\fund-allocation\settings.blade.php"
echo. > "resources\views\admin\fund-allocation\history.blade.php"

REM Resources Views - Admin Reports
mkdir "resources\views\admin\reports" 2>nul
echo. > "resources\views\admin\reports\index.blade.php"
echo. > "resources\views\admin\reports\sales.blade.php"
echo. > "resources\views\admin\reports\financial.blade.php"
echo. > "resources\views\admin\reports\inventory.blade.php"

REM Resources Views - Admin Settings
mkdir "resources\views\admin\settings" 2>nul
echo. > "resources\views\admin\settings\index.blade.php"
echo. > "resources\views\admin\settings\profile.blade.php"
echo. > "resources\views\admin\settings\system.blade.php"

REM Resources Views - Kasir Layouts
mkdir "resources\views\kasir\layouts" 2>nul
echo. > "resources\views\kasir\layouts\app.blade.php"
echo. > "resources\views\kasir\layouts\sidebar.blade.php"
echo. > "resources\views\kasir\layouts\header.blade.php"

REM Resources Views - Kasir Dashboard
mkdir "resources\views\kasir\dashboard" 2>nul
echo. > "resources\views\kasir\dashboard\index.blade.php"

REM Resources Views - Kasir POS
mkdir "resources\views\kasir\pos" 2>nul
echo. > "resources\views\kasir\pos\index.blade.php"
echo. > "resources\views\kasir\pos\receipt.blade.php"

REM Resources Views - Kasir Transactions
mkdir "resources\views\kasir\transactions" 2>nul
echo. > "resources\views\kasir\transactions\index.blade.php"
echo. > "resources\views\kasir\transactions\show.blade.php"

REM Resources Views - Kasir Customers
mkdir "resources\views\kasir\customers" 2>nul
echo. > "resources\views\kasir\customers\index.blade.php"
echo. > "resources\views\kasir\customers\create.blade.php"
echo. > "resources\views\kasir\customers\show.blade.php"

REM Resources Views - Kasir Reports
mkdir "resources\views\kasir\reports" 2>nul
echo. > "resources\views\kasir\reports\index.blade.php"
echo. > "resources\views\kasir\reports\sales.blade.php"

REM Resources Views - Auth
mkdir "resources\views\auth" 2>nul
echo. > "resources\views\auth\login.blade.php"
echo. > "resources\views\auth\register.blade.php"
echo. > "resources\views\auth\forgot-password.blade.php"
echo. > "resources\views\auth\reset-password.blade.php"

REM Resources Views - Components
mkdir "resources\views\components" 2>nul
echo. > "resources\views\components\alert.blade.php"
echo. > "resources\views\components\button.blade.php"
echo. > "resources\views\components\input.blade.php"
echo. > "resources\views\components\select.blade.php"
echo. > "resources\views\components\modal.blade.php"
echo. > "resources\views\components\table.blade.php"
echo. > "resources\views\components\pagination.blade.php"
echo. > "resources\views\components\card.blade.php"

REM Routes
mkdir "routes" 2>nul
echo. > "routes\admin.php"
echo. > "routes\kasir.php"

REM Tests - Feature Admin
mkdir "tests\Feature\Admin" 2>nul
echo. > "tests\Feature\Admin\DashboardTest.php"
echo. > "tests\Feature\Admin\ProductManagementTest.php"
echo. > "tests\Feature\Admin\UserManagementTest.php"
echo. > "tests\Feature\Admin\InventoryTest.php"
echo. > "tests\Feature\Admin\FinancialTest.php"
echo. > "tests\Feature\Admin\FundAllocationTest.php"

REM Tests - Feature Kasir
mkdir "tests\Feature\Kasir" 2>nul
echo. > "tests\Feature\Kasir\DashboardTest.php"
echo. > "tests\Feature\Kasir\PosTest.php"
echo. > "tests\Feature\Kasir\TransactionTest.php"
echo. > "tests\Feature\Kasir\CustomerTest.php"

REM Tests - Feature Auth
mkdir "tests\Feature\Auth" 2>nul
echo. > "tests\Feature\Auth\LoginTest.php"
echo. > "tests\Feature\Auth\RegisterTest.php"
echo. > "tests\Feature\Auth\PasswordResetTest.php"

REM Tests - Feature Api
mkdir "tests\Feature\Api" 2>nul
echo. > "tests\Feature\Api\AdminApiTest.php"
echo. > "tests\Feature\Api\KasirApiTest.php"

REM Tests - Unit Models
mkdir "tests\Unit\Models" 2>nul
echo. > "tests\Unit\Models\UserTest.php"
echo. > "tests\Unit\Models\ProductTest.php"
echo. > "tests\Unit\Models\TransactionTest.php"
echo. > "tests\Unit\Models\InventoryTest.php"

REM Tests - Unit Services
mkdir "tests\Unit\Services" 2>nul
echo. > "tests\Unit\Services\ProductServiceTest.php"
echo. > "tests\Unit\Services\TransactionServiceTest.php"
echo. > "tests\Unit\Services\InventoryServiceTest.php"
echo. > "tests\Unit\Services\FundAllocationServiceTest.php"

REM Tests - Unit Utils
mkdir "tests\Unit\Utils" 2>nul
echo. > "tests\Unit\Utils\ResponseHelperTest.php"
echo. > "tests\Unit\Utils\DateHelperTest.php"

REM Create additional config files
echo. > "tailwind.config.js"
echo. > "vite.config.js"

echo.
echo ✅ Laravel project structure created successfully!
echo.
echo Structure created in 'bd' directory with:
echo - Complete app structure with Controllers, Models, Services, etc.
echo - Database migrations and seeders
echo - Public assets (CSS, JS, Images)
echo - Resources (Views, Lang)
echo - Routes (Admin, Kasir)
echo - Tests (Feature and Unit)
echo - Configuration files
echo.
echo Note: All files are created as empty placeholders. 
echo You can now start implementing your Laravel application logic.
echo.
pause
 
