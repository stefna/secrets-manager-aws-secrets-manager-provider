<?php declare(strict_types=1);

namespace Stefna\SecretsManager\Provider\AwsSecretsManager\Tests;

use AsyncAws\Core\Test\ResultMockFactory;
use AsyncAws\SecretsManager\Exception\ResourceNotFoundException;
use AsyncAws\SecretsManager\Result\CreateSecretResponse;
use AsyncAws\SecretsManager\Result\DeleteSecretResponse;
use AsyncAws\SecretsManager\Result\GetSecretValueResponse;
use AsyncAws\SecretsManager\Result\UpdateSecretResponse;
use AsyncAws\SecretsManager\SecretsManagerClient;
use PHPUnit\Framework\TestCase;
use Stefna\SecretsManager\Provider\AwsSecretsManager\AwsSecretsManagerProvider;
use Stefna\SecretsManager\Values\Secret;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class AwsSecretsManagerProviderTest extends TestCase
{
	public function testRetrieveValue(): void
	{
		$key = 'MyTestDatabaseSecret';
		$client = $this->getMockBuilder(SecretsManagerClient::class)
			->disableOriginalConstructor()
			->onlyMethods(['getSecretValue'])
			->getMock();

		$complexSecretValue = [
			'username' => 'test',
			'password' => 'testpass',
		];
		$result = ResultMockFactory::create(GetSecretValueResponse::class, [
			'ARN' => "arn:aws:secretsmanager:us-west-2:123456789012:secret:$key-a1b2c3",
			'CreatedDate' => 1523477145.713,
			'Name' => $key,
			'SecretString' => json_encode($complexSecretValue),
			'VersionId' => 'EXAMPLE1-90ab-cdef-fedc-ba987SECRET1',
			'VersionStages' => [
				'AWSPREVIOUS',
			],
		]);
		$client
			->expects($this->once())
			->method('getSecretValue')
			->with($this->callback(function (array $args) use ($key) {
				return $args['SecretId'] === $key;
			}))->willReturn($result);

		$provider = new AwsSecretsManagerProvider($client);

		$secret = $provider->getSecret($key);
		$this->assertSame($complexSecretValue, $secret->getValue());
		$this->assertSame($complexSecretValue['username'], $secret['username']);
	}

	public function testPutSecretUpdating(): void
	{
		$testValue = 'value';
		$testKey = 'test-key';
		$client = $this->getMockBuilder(SecretsManagerClient::class)
			->disableOriginalConstructor()
			->onlyMethods(['updateSecret'])
			->getMock();

		$result = ResultMockFactory::create(UpdateSecretResponse::class, [
			'ARN' => "arn:aws:secretsmanager:us-west-2:123456789012:secret:$testKey-a1b2c3",
			'Name' => $testKey,
			'VersionId' => 'EXAMPLE1-90ab-cdef-fedc-ba987SECRET1',
		]);
		$client
			->expects($this->once())
			->method('updateSecret')
			->with($this->callback(function (array $args) use ($testKey, $testValue) {
				if ($args['SecretId'] !== $testKey) {
					return false;
				}
				if ($args['SecretString'] !== json_encode($testValue)) {
					return false;
				}
				return true;
			}))->willReturn($result);

		$provider = new AwsSecretsManagerProvider($client);

		$provider->putSecret(new Secret($testKey, $testValue));
	}

	public function testPutSecretCreate(): void
	{
		$testValue = 'value';
		$testKey = 'test-key';
		$client = $this->getMockBuilder(SecretsManagerClient::class)
			->disableOriginalConstructor()
			->onlyMethods(['updateSecret', 'createSecret'])
			->getMock();

		$result = ResultMockFactory::create(CreateSecretResponse::class, [
			'ARN' => "arn:aws:secretsmanager:us-west-2:123456789012:secret:$testKey-a1b2c3",
			'Name' => $testKey,
			'VersionId' => 'EXAMPLE1-90ab-cdef-fedc-ba987SECRET1',
		]);
		$client
			->expects($this->once())
			->method('updateSecret')
			->willThrowException(new ResourceNotFoundException($this->createMock(ResponseInterface::class)));

		$client
			->expects($this->once())
			->method('createSecret')
			->with($this->callback(function (array $args) use ($testKey, $testValue) {
				if ($args['SecretId'] !== $testKey) {
					return false;
				}
				if ($args['Name'] !== $testKey) {
					return false;
				}
				if ($args['SecretString'] !== json_encode($testValue)) {
					return false;
				}
				return true;
			}))->willReturn($result);

		$provider = new AwsSecretsManagerProvider($client);

		$provider->putSecret(new Secret($testKey, $testValue));
	}

	public function testDeleteSecretPersisting(): void
	{
		$testKey = 'test-key';
		$client = $this->getMockBuilder(SecretsManagerClient::class)
			->disableOriginalConstructor()
			->onlyMethods(['deleteSecret'])
			->getMock();

		$result = ResultMockFactory::create(DeleteSecretResponse::class, [
			'ARN' => "arn:aws:secretsmanager:us-west-2:123456789012:secret:$testKey-a1b2c3",
			'Name' => $testKey,
			'DeletionDate' => 1523477145.713,
		]);
		$client
			->expects($this->once())
			->method('deleteSecret')
			->with($this->callback(function (array $args) use ($testKey) {
				return $args['SecretId'] === $testKey;
			}))->willReturn($result);

		$provider = new AwsSecretsManagerProvider($client);

		$provider->deleteSecret(new Secret($testKey, ''));
	}

	public function testGetSameSecretNotQueryRemote(): void
	{
		$key = 'MyTestDatabaseSecret';
		$client = $this->getMockBuilder(SecretsManagerClient::class)
			->disableOriginalConstructor()
			->onlyMethods(['getSecretValue'])
			->getMock();

		$complexSecretValue = [
			'username' => 'test',
			'password' => 'testpass',
		];
		$result = ResultMockFactory::create(GetSecretValueResponse::class, [
			'ARN' => "arn:aws:secretsmanager:us-west-2:123456789012:secret:$key-a1b2c3",
			'CreatedDate' => 1523477145.713,
			'Name' => $key,
			'SecretString' => json_encode($complexSecretValue),
			'VersionId' => 'EXAMPLE1-90ab-cdef-fedc-ba987SECRET1',
			'VersionStages' => [
				'AWSPREVIOUS',
			],
		]);
		$client
			->expects($this->once())
			->method('getSecretValue')
			->with($this->callback(function (array $args) use ($key) {
				return $args['SecretId'] === $key;
			}))->willReturn($result);

		$provider = new AwsSecretsManagerProvider($client);

		$secret = $provider->getSecret($key);
		$this->assertSame($complexSecretValue, $secret->getValue());
		$this->assertSame($complexSecretValue['username'], $secret['username']);

		$secret2 = $provider->getSecret($key);
		$this->assertSame($secret, $secret2);
	}
}
