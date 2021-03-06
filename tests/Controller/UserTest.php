<?php

namespace App\Tests\Controller;


use App\Tests\TestCommon;
use Symfony\Component\HttpFoundation\Response;

class UserTest extends TestCommon
{
    protected function getEmNamesToCreate(): array
    {
        return ['dm'];
    }

    public function testCallServiceWithoutSystemCredentials(): void
    {
        $response = $this->buildService('/collection/issues')->call();
        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testCallServiceWithoutClientVersion(): void
    {
        $this->createUserCollection(self::$defaultTestDmUserName);
        $response = $this->buildService(
            '/collection/issues', [
            'username' => self::$defaultTestDmUserName,
            'password' => sha1(self::$testDmUsers[self::$defaultTestDmUserName])
        ],  [
            'publicationCode' => 'fr/DDD',
            'issueNumbers' => ['3'],
            'condition' => 'bon'
        ], self::getSystemCredentialsNoVersion(self::$dmUser))
            ->call();
        $this->assertEquals(Response::HTTP_VERSION_NOT_SUPPORTED, $response->getStatusCode());
    }

    public function testCallServiceWithoutUserCredentials(): void
    {
        $response = $this->buildAuthenticatedService('/collection/issues', self::$dmUser, [])->call();
        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testCallServiceWithWrongUserCredentials(): void
    {
        $response = $this->buildAuthenticatedService('/collection/issues', self::$dmUser, ['username' => 'dm_test',
            'password' => 'invalid'])->call();
        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testCallServiceWithUserCredentials(): void
    {
        $response = $this->buildAuthenticatedServiceWithTestUser('/ducksmanager/user', self::$dmUser, 'PUT', [
            'username' => self::$defaultTestDmUserName,
            'password' => 'test',
            'password2' => 'test',
            'email' => 'test'
        ])->call();
        $this->assertNotEquals(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $this->assertNotEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }
}
