<?php declare(strict_types=1);

namespace Stefna\SecretsManager\Provider\AwsSecretsManager;

use AsyncAws\SecretsManager\Exception\ResourceNotFoundException;
use AsyncAws\SecretsManager\SecretsManagerClient;
use Stefna\SecretsManager\Exceptions\SecretNotFoundException;
use Stefna\SecretsManager\Provider\ProviderInterface;
use Stefna\SecretsManager\Values\Secret;

final class AwsSecretsManagerProvider implements ProviderInterface
{
	/** @var array<string, Secret> */
	private array $data = [];

	public function __construct(
		private readonly SecretsManagerClient $client,
	) {}

	public function putSecret(Secret $secret, ?array $options = []): Secret
	{
		$options['SecretId'] = $secret->getKey();
		$options['SecretString'] = json_encode($secret->getValue());
		if (!isset($options['ClientRequestToken'])) {
			$options['ClientRequestToken'] = base64_encode(random_bytes(32));
		}

		try {
			$this->client->updateSecret($options);
		}
		catch (ResourceNotFoundException $e) {
			$options['Name'] = $secret->getKey();
			$this->client->createSecret($options);
		}

		return $secret;
	}

	public function deleteSecret(Secret $secret, ?array $options = []): void
	{
		$options['SecretId'] = $secret->getKey();
		$this->client->deleteSecret($options)->resolve();
	}

	public function getSecret(string $key, ?array $options = []): Secret
	{
		if (isset($this->data[$key])) {
			return $this->data[$key];
		}
		try {
			$options['SecretId'] = $key;
			$value = (string)$this->client->getSecretValue($options)->getSecretString();
			return $this->data[$key] = new Secret($key, json_decode($value, true));
		}
		catch (ResourceNotFoundException $e) {
			throw SecretNotFoundException::withKey($key);
		}
	}
}
