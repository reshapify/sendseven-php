<?php

declare(strict_types=1);

namespace Reshapify\SendSeven;

use Reshapify\SendSeven\Account\Capabilities;
use Reshapify\SendSeven\Exceptions\ApiException;
use Reshapify\SendSeven\Http\Connector;
use Reshapify\SendSeven\Http\Method;
use Reshapify\SendSeven\Http\Request;
use Reshapify\SendSeven\Http\Response;
use Reshapify\SendSeven\Resources\ChannelConnect;
use Reshapify\SendSeven\Resources\Concerns\ProvidesResources;

/**
 * The SendSeven API. Each method returns a resource for one area of the API
 * (messages, contacts, channels…); see docs/reference for every endpoint.
 */
final readonly class Client
{
    use ProvidesResources;

    public function __construct(private Connector $connector) {}

    /**
     * The same client acting on another tenant (sub-account) through the
     * X-Tenant-ID header. Needs a partner token: multi-tenant management on
     * the plan, and the tenants:manage grant.
     */
    public function forTenant(string $tenantId): self
    {
        return new self($this->connector->forTenant($tenantId));
    }

    /**
     * The tenant this client acts on through X-Tenant-ID, or null for the
     * token's own tenant.
     */
    public function tenantId(): ?string
    {
        return $this->connector->tenantId();
    }

    /**
     * What this token can do: its tenant, the plan's features and its scopes.
     * Three requests; cache the result if you check it often.
     *
     * @throws ApiException
     */
    public function capabilities(): Capabilities
    {
        $scopes = $this->rolesPermissions()->getMyScopes();
        $tenant = null;

        foreach ($this->tenants()->mine() as $candidate) {
            if ($candidate->id === $scopes->tenantId) {
                $tenant = $candidate;
            }
        }

        return new Capabilities($tenant, $this->tenants()->features(), $scopes);
    }

    /**
     * Links customers open to connect their own channels (WhatsApp Embedded
     * Signup, Messenger, Instagram, Telegram, SMS, email). An alias of
     * channelConnect(), with create(ConnectLink) for building links.
     */
    public function connectLinks(): ChannelConnect
    {
        return $this->channelConnect();
    }

    /**
     * Call any endpoint directly, e.g. one SendSeven added after this SDK
     * was released. Authentication, retries and error handling still apply.
     *
     * @param  array<string, scalar|list<scalar>|null>  $query
     * @param  array<array-key, mixed>|null  $body
     */
    public function request(Method $method, string $path, array $query = [], ?array $body = null): Response
    {
        return $this->connector->send(new Request($method, $path, $query, $body));
    }

    public function connector(): Connector
    {
        return $this->connector;
    }
}
