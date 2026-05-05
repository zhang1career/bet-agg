<?php

namespace App\Providers;

use App\Http\Client\OutboundHttpDebugMiddleware;
use App\Http\Client\OutboundRequestIdMiddleware;
use App\Infrastructure\service_discovery\LaravelRedisStringClient;
use App\Logging\monolog\TodayAppLogHandler;
use App\Logging\processors\XRequestIdLogProcessor;
use App\Queue\connectors\DatabaseMillisConnector;
use App\Queue\failed\DatabaseUuidFailedJobProviderMillis;
use App\Services\api_gw\MemoizedServiceDiscoveryUrl;
use App\Services\api_gw\ResolvedApiGatewayBaseUrl;
use App\Services\api_gw\ResolvedXxlJobAdminAddress;
use App\Services\mall\BetPlaceService;
use App\Services\mall\BetSettlementService;
use App\Services\mall\PointsAdminService;
use App\Services\mall\serv_fd\CmsGameClient;
use App\Services\mall\settlement\LaravelDbTransactionRunner;
use App\Services\mall\settlement\SettlementBatchItemHandler;
use App\Services\mall\SportMarketCatalogService;
use App\Services\user\UserFoundationGateway;
use App\Services\XxlJobRegistry;
use DateTimeZone;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Connection;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;
use Paganini\Batch\Contracts\BatchJobRepositoryContract;
use Paganini\Batch\Execution\BatchExecutor;
use Paganini\Batch\Persistence\PdoBatchJobRepository;
use Paganini\ServiceDiscovery\Contracts\ServiceUriResolverInterface;
use Paganini\ServiceDiscovery\RedisServiceUriResolver;
use PDO;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(LaravelRedisStringClient::class, function (Application $app) {
            $conn = (string) config('bet_agg.foundation.service_discovery.redis_connection');

            return new LaravelRedisStringClient($app['redis']->connection($conn));
        });

        $this->app->singleton(ServiceUriResolverInterface::class, function (Application $app) {
            return new RedisServiceUriResolver(
                $app->make(LaravelRedisStringClient::class),
                (string) config('bet_agg.foundation.service_discovery.redis_key_prefix')
            );
        });

        $this->app->singleton(MemoizedServiceDiscoveryUrl::class, function (Application $app) {
            return new MemoizedServiceDiscoveryUrl(
                $app,
            );
        });

        $this->app->singleton(ResolvedApiGatewayBaseUrl::class, function (Application $app) {
            return new ResolvedApiGatewayBaseUrl(
                $app->make(MemoizedServiceDiscoveryUrl::class)
            );
        });

        $this->app->singleton(ResolvedXxlJobAdminAddress::class, function (Application $app) {
            return new ResolvedXxlJobAdminAddress(
                $app->make(MemoizedServiceDiscoveryUrl::class)
            );
        });

        $this->app->singleton(CmsGameClient::class, static fn () => CmsGameClient::fromConfig());

        $this->app->singleton(SportMarketCatalogService::class);
        $this->app->singleton(PointsAdminService::class);
        $this->app->singleton(BetPlaceService::class);
        $this->app->singleton(SettlementBatchItemHandler::class);

        // Paganini\Batch wiring for bet settlement: outer & inner phases both run on the default
        // database connection through Laravel's DB facade. The job header lives in `settle_job`
        // (consumer-injected table name; the schema only varies in `cursor_offset` vs the library's
        // default `cursor`, hence the column override below).
        $this->app->singleton(BatchJobRepositoryContract::class, static function (Application $app): BatchJobRepositoryContract {
            /** @var Connection $conn */
            $conn = $app['db']->connection();
            /** @var PDO $pdo */
            $pdo = $conn->getPdo();

            return new PdoBatchJobRepository(
                $pdo,
                'settle_job',
                ['cursor' => 'cursor_offset'],
            );
        });

        $this->app->singleton(BatchExecutor::class, static function (Application $app): BatchExecutor {
            $runner = new LaravelDbTransactionRunner;

            return new BatchExecutor(
                outerTx: $runner,
                innerTx: $runner,
                repository: $app->make(BatchJobRepositoryContract::class),
            );
        });

        $this->app->singleton(BetSettlementService::class);

        $this->app->scoped(UserFoundationGateway::class, static function (Application $app): UserFoundationGateway {
            return new UserFoundationGateway(
                $app->make(ResolvedApiGatewayBaseUrl::class),
                $app->make(CacheRepository::class),
            );
        });

        $this->app->singleton(XxlJobRegistry::class, function () {
            $registry = new XxlJobRegistry;
            $registry->scanAndRegister('Jobs');

            return $registry;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Log::extend('app_today', function ($app, array $config) {
            $handler = new TodayAppLogHandler(
                $config['path'],
                (int) ($config['days'] ?? 0),
                $this->level($config),
                $config['bubble'] ?? true,
                $config['permission'] ?? null,
                $config['locking'] ?? false
            );

            $processors = [];
            if ($config['replace_placeholders'] ?? false) {
                $processors[] = new PsrLogMessageProcessor;
            }
            $processors[] = new XRequestIdLogProcessor;

            $tz = new DateTimeZone((string) config('app.timezone'));

            return new Logger(
                $this->parseChannel($config),
                [$this->prepareHandler($handler, $config)],
                $processors,
                $tz
            );
        });

        // Propagate X-Request-Id to ALL outbound Http facade calls so downstream services
        // (Foundation, CMS, OSS, etc.) share the inbound request's correlation id. Registered
        // unconditionally — debug logging below is opt-in.
        Http::globalRequestMiddleware([OutboundRequestIdMiddleware::class, 'addHeader']);

        if (config('app.debug')) {
            Http::globalRequestMiddleware([OutboundHttpDebugMiddleware::class, 'logRequest']);
            Http::globalResponseMiddleware([OutboundHttpDebugMiddleware::class, 'logResponse']);
        }

        Paginator::useBootstrapFive();

        // Use custom database queue with ct and millisecond timestamps
        $this->app['queue']->addConnector('database', function () {
            return new DatabaseMillisConnector($this->app['db']);
        });

        // Use custom failed job provider with failed_at in milliseconds
        $this->app->extend('queue.failer', function ($failer, $app) {
            $config = $app['config']['queue.failed'];
            if (isset($config['driver']) && $config['driver'] === 'database-uuids') {
                return new DatabaseUuidFailedJobProviderMillis(
                    $app['db'],
                    $config['database'] ?? $app['config']['database.default'],
                    $config['table']
                );
            }

            return $failer;
        });
    }
}
