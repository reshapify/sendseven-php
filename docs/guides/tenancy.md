# Tenancy and plans

## The model

```
Billing account          (one payment relationship)
 ├── Tenant A            (a workspace: its own channels, contacts, conversations, tokens, webhooks)
 ├── Tenant B
 └── ...
```

An API token belongs to **one tenant**, and every call with it happens in that tenant. That's all most integrations need, on any SendSeven plan that includes the API.

## Two integration shapes

**Single tenant.** One token, configured once. Your channels, your contacts. This works on every plan with API access.

```php
$sendseven = SendSeven::client($token);
```

**Tenant per customer.** Platforms and agencies give each customer their own sub-account, for isolation and per-customer billing views. Create the tenant, then either store a token for it or act on it from a parent token:

```php
$tenant = $sendseven->tenants()->create(name: 'Acme', companyEmail: 'ops@acme.test');

// act on it through X-Tenant-ID
$acme = $sendseven->forTenant($tenant->id);
$acme->connectLinks()->create(/* ... */);
$acme->webhooks()->createEndpoint(/* ... */);
```

## What partner features need

| To | You need |
|---|---|
| Create tenants (`tenants()->create()`) | **Multi-tenant management** on the plan, and a token from the **billing account's owner** |
| Act on another tenant (`forTenant()`) | Multi-tenant management, and the **`tenants:manage`** grant |
| Issue a token for a tenant (`apiTokens()->create()` with `forTenant()`) | The same |

Multi-tenant management comes with **Professional, Scale, Enterprise and API Only**, but not Basic. A trial that has ended loses it.

When it's missing, SendSeven refuses with errors the SDK turns into actionable exceptions:

| SendSeven says | You get |
|---|---|
| `{"detail": {"code": "feature_disabled", "feature": "multi_tenant"}}` | `FeatureDisabled`; `feature()` is `"multi_tenant"` |
| `{"detail": "Only billing account owners can create new tenants"}` | `NotBillingAccountOwner` |

## Check first

```php
$capabilities = $sendseven->capabilities();

$capabilities->package();            // "API_ONLY", "BASIC", …
$capabilities->hasMultiTenant();
$capabilities->canCreateTenants();   // multi-tenant + tenants:create scope
$capabilities->canManageTenants();   // multi-tenant + tenants:manage
$capabilities->hasScope('messages:create');
$capabilities->sendsSms();           // SMS enabled on this tenant
$capabilities->sendsRcs();
$capabilities->whyNotCreateTenants(); // a sentence, or null
```

The API can't tell whether the token's user owns the billing account, so `canCreateTenants()` may be true and creation still fail with `NotBillingAccountOwner`.

## Webhooks per tenant

Webhook endpoints belong to a tenant. With a tenant per customer, register one endpoint per tenant (through `forTenant()`). Include the tenant in the URL (`/webhooks/sendseven/{tenant}`) so your handler can find the right secret, since each endpoint has its own.
