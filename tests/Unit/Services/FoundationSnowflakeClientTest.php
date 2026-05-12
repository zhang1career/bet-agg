<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\ConfigurationMissingException;
use App\Services\user\FoundationSnowflakeClient;
use Illuminate\Support\Facades\Http;
use Paganini\Aggregation\Exceptions\DownstreamServiceException;
use Tests\TestCase;

final class FoundationSnowflakeClientTest extends TestCase
{
    public function test_mint_next_id_accepts_object_payload_with_id_field(): void
    {
        config([
            'api_gw.base_url' => 'http://gw.test',
            'bet_agg.snowflake.access_key' => 'secret-key',
            'bet_agg.snowflake.mint_endpoint' => '/api/snowflake/id',
        ]);

        Http::fake([
            'http://gw.test/api/snowflake/id' => Http::response([
                'errorCode' => 0,
                'message' => '',
                'data' => ['id' => '1709123456789012345'],
            ], 200),
        ]);

        $id = $this->app->make(FoundationSnowflakeClient::class)->mintNextId();

        $this->assertSame('1709123456789012345', $id);
        Http::assertSentCount(1);
    }

    public function test_mint_next_id_accepts_nested_id_key_in_object(): void
    {
        config([
            'api_gw.base_url' => 'http://gw.test',
            'bet_agg.snowflake.access_key' => 'secret-key',
            'bet_agg.snowflake.mint_endpoint' => '/api/snowflake/id',
        ]);

        Http::fake([
            'http://gw.test/api/snowflake/id' => Http::response([
                'errorCode' => 0,
                'message' => '',
                'data' => ['snowflake_id' => '42'],
            ], 200),
        ]);

        $id = $this->app->make(FoundationSnowflakeClient::class)->mintNextId();

        $this->assertSame('42', $id);
    }

    public function test_mint_next_id_throws_when_access_key_missing(): void
    {
        config([
            'api_gw.base_url' => 'http://gw.test',
            'bet_agg.snowflake.access_key' => '',
        ]);

        $this->expectException(ConfigurationMissingException::class);
        $this->expectExceptionMessage('SF_SNOWFLAKE_ACCESS_KEY');

        $this->app->make(FoundationSnowflakeClient::class)->mintNextId();
    }

    public function test_mint_next_id_throws_when_gateway_base_url_missing(): void
    {
        config([
            'api_gw.base_url' => '',
            'bet_agg.snowflake.access_key' => 'x',
        ]);

        $this->expectException(ConfigurationMissingException::class);
        $this->expectExceptionMessage('Missing API gateway base URL.');

        $this->app->make(FoundationSnowflakeClient::class)->mintNextId();
    }

    public function test_mint_next_id_downstream_error_on_http_failure(): void
    {
        config([
            'api_gw.base_url' => 'http://gw.test',
            'bet_agg.snowflake.access_key' => 'secret-key',
            'bet_agg.snowflake.mint_endpoint' => '/api/snowflake/id',
        ]);

        Http::fake([
            'http://gw.test/api/snowflake/id' => Http::response([], 503),
        ]);

        $this->expectException(DownstreamServiceException::class);
        $this->expectExceptionMessage('HTTP 503.');

        $this->app->make(FoundationSnowflakeClient::class)->mintNextId();
    }
}
