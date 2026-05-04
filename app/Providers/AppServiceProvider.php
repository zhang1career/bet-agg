<?php

namespace App\Providers;

use App\Contracts\InventoryOutboundContract;
use App\Http\Client\OutboundHttpDebugMiddleware;
use App\Infrastructure\ServiceDiscovery\LaravelRedisStringClient;
use App\Logging\monolog\TodayAppLogHandler;
use App\Logging\Processors\XRequestIdLogProcessor;
use App\Queue\Connectors\DatabaseMillisConnector;
use App\Queue\Failed\DatabaseUuidFailedJobProviderMillis;
use App\Services\api_gw\MemoizedServiceDiscoveryUrl;
use App\Services\api_gw\ResolvedApiGatewayBaseUrl;
use App\Services\api_gw\ResolvedXxlJobAdminAddress;
use App\Services\mall\BetCheckoutService;
use App\Services\mall\BetOverdueOrderSweepService;
use App\Services\mall\BetSettlementService;
use App\Services\mall\OrderCommandService;
use App\Services\mall\PointsTccService;
use App\Services\mall\serv_fd\CmsGameClient;
use App\Services\mall\SportMarketCatalogService;
use App\Services\mall\SportSelectionBookService;
use App\Services\outbound\StubInventoryOutboundClient;
use App\Services\XxlJobRegistry;
use DateTimeZone;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;
use Paganini\ServiceDiscovery\Contracts\ServiceUriResolverInterface;
use Paganini\ServiceDiscovery\RedisServiceUriResolver;

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

        $this->app->singleton(SportSelectionBookService::class);
        $this->app->singleton(SportMarketCatalogService::class);
        $this->app->singleton(OrderCommandService::class);
        $this->app->singleton(InventoryOutboundContract::class, StubInventoryOutboundClient::class);
        $this->app->singleton(PointsTccService::class);
        $this->app->singleton(BetCheckoutService::class);
        $this->app->singleton(BetOverdueOrderSweepService::class);
        $this->app->singleton(BetSettlementService::class);

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
