<?php

namespace Laravel\Tests\Forge;

use Closure;
use Laravel\Forge\Server;
use InvalidArgumentException;
use Laravel\Forge\Sites\Site;
use PHPUnit\Framework\TestCase;
use Laravel\Tests\Forge\Helpers\Api;
use Laravel\Forge\Sites\SitesManager;
use Laravel\Tests\Forge\Helpers\FakeResponse;
use Laravel\Forge\Contracts\ApplicationContract;
use Laravel\Forge\Sites\Applications\GitApplication;
use Laravel\Forge\Sites\Applications\WordPressApplication;

class SitesTest extends TestCase
{
    /**
     * @dataProvider createSiteDataProvider
     */ 
    public function testCreateSite($server, Closure $factory, Closure $assertion)
    {
        $sites = new SitesManager();

        $result = $factory($sites, $server);

        $assertion($result);
    }

    /**
     * @dataProvider listSitesDataProvider
     */
    public function testListSites($server, Closure $assertion)
    {
        $sites = new SitesManager();

        $result = $sites->list()->from($server);

        $assertion($result);
    }

    /**
     * @dataProvider getSiteDataProvider
     */
    public function testGetSite($server, int $siteId, Closure $assertion)
    {
        $sites = new SitesManager();

        $result = $sites->get($siteId)->from($server);

        $assertion($result);
    }

    /**
     * @dataProvider updateSiteDataProvider
     */
    public function testUpdateSite(Site $site, array $payload, $expectedResult, bool $exception = false)
    {
        if ($exception === true) {
            $this->expectException(InvalidArgumentException::class);
        }

        $result = $site->update($payload);

        $this->assertSame($expectedResult, $result);
    }

    /**
     * @dataProvider installApplicationDataProvider
     */
    public function testInstallApplication(Site $site, ApplicationContract $app, $expectedResult, bool $exception = false)
    {
        if ($exception === true) {
            $this->expectException(InvalidArgumentException::class);
        }

        $result = $site->install($app);

        $this->assertSame($expectedResult, $result);
    }

    /**
     * @dataProvider uninstallApplicationDataProvider
     */
    public function testUninstallApplication(Site $site, ApplicationContract $app, $expectedResult, bool $exception = false)
    {
        if ($exception === true) {
            $this->expectException(InvalidArgumentException::class);
        }

        $result = $site->uninstall($app);

        $this->assertSame($expectedResult, $result);
    }

    /**
     * @dataProvider deleteSiteDataProvider
     */
    public function testDeleteSite(Site $site, $expectedResult, bool $exception = false)
    {
        if ($exception === true) {
            $this->expectException(InvalidArgumentException::class);
        }

        $result = $site->delete();

        $this->assertSame($expectedResult, $result);
    }

    public function payload(array $replace = []): array
    {
        return array_merge([
            'domain' => 'example.org',
            'project_type' => 'php',
        ], $replace);
    }

    public function response(array $replace = []): array
    {
        return array_merge([
            'id' => 1,
            'name' => 'example.org',
            'directory' => '/public',
            'wildcards' => false,
            'status' => 'installing',
            'repository' => null,
            'repository_provider' => null,
            'repository_branch' => null,
            'repository_status' => null,
            'quick_deploy' => false,
            'project_type' => 'php',
            'app' => null,
            'app_status' => null,
            'hipchat_room' => null,
            'slack_channel' => null,
            'created_at' => '2016-12-16 16:38:08',
        ], $replace);
    }

    public function createSiteDataProvider(): array
    {
        return [
            [
                'server' => Api::fakeServer(function ($http) {
                    $http->shouldReceive('request')
                        ->with('POST', 'servers/1/sites', [
                            'form_params' => $this->payload(),
                        ])
                        ->andReturn(
                            FakeResponse::fake()->withJson(['site' => $this->response()])->toResponse()
                        );
                }),
                'factory' => function (SitesManager $sites, $server) {
                    return $sites
                        ->create('example.org')
                        ->asPhp()
                        ->on($server);
                },
                'assertion' => function ($site) {
                    $this->assertInstanceOf(Site::class, $site);
                    $this->assertSame('example.org', $site->domain());
                    $this->assertSame('php', $site->projectType());
                }
            ],
        ];
    }

    public function listSitesDataProvider(): array
    {
        return [
            [
                'server' => Api::fakeServer(function ($http) {
                    $http->shouldReceive('request')
                        ->with('GET', 'servers/1/sites', ['form_params' => []])
                        ->andReturn(
                            FakeResponse::fake()
                                ->withJson([
                                    'sites' => [
                                        $this->response(['id' => 1]),
                                        $this->response(['id' => 2]),
                                        $this->response(['id' => 3]),
                                        $this->response(['id' => 4]),
                                    ],
                                ])
                                ->toResponse()
                        );
                }),
                'assertion' => function ($result) {
                    $this->assertInternalType('array', $result);

                    foreach ($result as $site) {
                        $this->assertInstanceOf(Site::class, $site);
                        $this->assertSame('example.org', $site->domain());
                        $this->assertSame('php', $site->projectType());
                    }
                }
            ],
        ];
    }

    public function getSiteDataProvider(): array
    {
        return [
            [
                'server' => Api::fakeServer(function ($http) {
                    $http->shouldReceive('request')
                        ->with('GET', 'servers/1/sites/1', ['form_params' => []])
                        ->andReturn(
                            FakeResponse::fake()->withJson(['site' => $this->response()])->toResponse()
                        );
                }),
                'siteId' => 1,
                'assertion' => function ($site) {
                    $this->assertInstanceOf(Site::class, $site);
                    $this->assertSame('example.org', $site->domain());
                    $this->assertSame('php', $site->projectType());
                }
            ],
        ];
    }

    public function updateSiteDataProvider(): array
    {
        return [
            [
                'site' => new Site(
                    Api::fakeServer(function ($http) {
                        $http->shouldReceive('request')
                            ->with('PUT', 'servers/1/sites/1', [
                                'form_params' => ['directory' => '/some/path']
                            ])
                            ->andReturn(
                                FakeResponse::fake()
                                    ->withJson([
                                        'site' => $this->response(['directory' => '/some/path']),
                                    ])
                                    ->toResponse()
                            );
                    }),
                    $this->response()
                ),
                'payload' => ['directory' => '/some/path'],
                'expectedResult' => true,
            ],
        ];
    }

    public function installApplicationDataProvider(): array
    {
        return [
            [
                'site' => new Site(
                    Api::fakeServer(function ($http) {
                        $http->shouldReceive('request')
                            ->with('POST', 'servers/1/sites/1/git', [
                                'form_params' => [
                                    'provider' => 'github',
                                    'repository' => 'username/repository',
                                ],
                            ])
                            ->andReturn(FakeResponse::fake()->toResponse());
                    }),
                    $this->response()
                ),
                'app' => (new GitApplication())->fromGithub('username/repository'),
                'expectedResult' => true,
            ],
            [
                'site' => new Site(
                    Api::fakeServer(function ($http) {
                        $http->shouldReceive('request')
                            ->with('POST', 'servers/1/sites/1/git', [
                                'form_params' => [
                                    'provider' => 'bitbucket',
                                    'repository' => 'username/repository',
                                ],
                            ])
                            ->andReturn(FakeResponse::fake()->toResponse());
                    }),
                    $this->response()
                ),
                'app' => (new GitApplication())->fromBitbucket('username/repository'),
                'expectedResult' => true,
            ],
            [
                'site' => new Site(
                    Api::fakeServer(function ($http) {
                        $http->shouldReceive('request')
                            ->with('POST', 'servers/1/sites/1/git', [
                                'form_params' => [
                                    'provider' => 'gitlab',
                                    'repository' => 'username/repository',
                                ],
                            ])
                            ->andReturn(FakeResponse::fake()->toResponse());
                    }),
                    $this->response()
                ),
                'app' => (new GitApplication())->fromGitlab('username/repository'),
                'expectedResult' => true,
            ],
            [
                'site' => new Site(
                    Api::fakeServer(function ($http) {
                        $http->shouldReceive('request')
                            ->with('POST', 'servers/1/sites/1/git', [
                                'form_params' => [
                                    'provider' => 'custom',
                                    'repository' => 'git@example.org:username/repository.git',
                                ],
                            ])
                            ->andReturn(FakeResponse::fake()->toResponse());
                    }),
                    $this->response()
                ),
                'app' => (new GitApplication())->fromGit('git@example.org:username/repository.git'),
                'expectedResult' => true,
            ],
            [
                'site' => new Site(
                    Api::fakeServer(function ($http) {
                        $http->shouldReceive('request')
                            ->with('POST', 'servers/1/sites/1/wordpress', [
                                'form_params' => [
                                    'database' => 'forge',
                                    'user' => 'forge',
                                ],
                            ])
                            ->andReturn(FakeResponse::fake()->toResponse());
                    }),
                    $this->response()
                ),
                'app' => (new WordPressApplication())->usingDatabase('forge', 'forge'),
                'expectedResult' => true,
            ],
        ];
    }

    public function uninstallApplicationDataProvider(): array
    {
        return [
            [
                'site' => new Site(
                    Api::fakeServer(function ($http) {
                        $http->shouldReceive('request')
                            ->with('DELETE', 'servers/1/sites/1/git')
                            ->andReturn(FakeResponse::fake()->toResponse());
                    }),
                    $this->response()
                ),
                'app' => new GitApplication(),
                'expectedResult' => true,
            ],
            [
                'site' => new Site(
                    Api::fakeServer(function ($http) {
                        $http->shouldReceive('request')
                            ->with('DELETE', 'servers/1/sites/1/wordpress')
                            ->andReturn(FakeResponse::fake()->toResponse());
                    }),
                    $this->response()
                ),
                'app' => new WordPressApplication(),
                'expectedResult' => true,
            ],
        ];
    }

    public function deleteSiteDataProvider(): array
    {
        return [
            [
                'site' => new Site(
                    Api::fakeServer(function ($http) {
                        $http->shouldReceive('request')
                            ->with('DELETE', 'servers/1/sites/1')
                            ->andReturn(FakeResponse::fake()->toResponse());
                    }),
                    $this->response()
                ),
                'expectedResult' => true,
            ],
        ];
    }
}
