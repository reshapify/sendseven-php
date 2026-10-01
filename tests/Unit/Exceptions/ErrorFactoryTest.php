<?php

declare(strict_types=1);

use Reshapify\SendSeven\Exceptions\AuthenticationFailed;
use Reshapify\SendSeven\Exceptions\ErrorFactory;
use Reshapify\SendSeven\Exceptions\FeatureDisabled;
use Reshapify\SendSeven\Exceptions\InsufficientBalance;
use Reshapify\SendSeven\Exceptions\NotBillingAccountOwner;
use Reshapify\SendSeven\Exceptions\NotFound;
use Reshapify\SendSeven\Exceptions\PermissionDenied;
use Reshapify\SendSeven\Exceptions\UnexpectedStatus;
use Reshapify\SendSeven\Exceptions\ValidationFailed;
use Reshapify\SendSeven\Http\Request;
use Reshapify\SendSeven\Http\Response;

// The bodies below are SendSeven's real refusals, captured 1 Oct 2026.

it('explains a plan without multi-tenant management', function (): void {
    $exception = ErrorFactory::make(Request::post('/tenants'), new Response(403, '{"detail":{"code":"feature_disabled","feature":"multi_tenant"}}', ['x-request-id' => 'req_1']));

    expect($exception)->toBeInstanceOf(FeatureDisabled::class)
        ->and($exception->feature())->toBe('multi_tenant')
        ->and($exception->errorCode)->toBe('feature_disabled')
        ->and($exception->requestId)->toBe('req_1')
        ->and($exception->getMessage())->toBe("feature_disabled (POST /tenants, HTTP 403, request req_1). The account's SendSeven plan doesn't include multi_tenant: it comes with Professional, Scale, Enterprise or API Only. A trial that has ended also loses it.");
});

it('explains that only the billing account owner can create tenants', function (): void {
    $exception = ErrorFactory::make(Request::post('/tenants'), new Response(403, '{"detail":"Only billing account owners can create new tenants"}'));

    expect($exception)->toBeInstanceOf(NotBillingAccountOwner::class)
        ->and($exception->getMessage())->toStartWith('Only billing account owners can create new tenants (POST /tenants, HTTP 403).')
        ->and($exception->hint())->toContain('owner of the SendSeven billing account');
});

it('lists every invalid field', function (): void {
    $body = '{"detail":[{"type":"missing","loc":["body","body_text"],"msg":"Field required"},{"type":"missing","loc":["body","target_language"],"msg":"Field required"}]}';
    $exception = ErrorFactory::make(Request::post('/whatsapp-templates/translate'), new Response(422, $body));

    expect($exception)->toBeInstanceOf(ValidationFailed::class)
        ->and(array_map(strval(...), $exception->fieldErrors()))->toBe(['body.body_text: Field required', 'body.target_language: Field required'])
        ->and($exception->getMessage())->toStartWith('The request was invalid: body.body_text: Field required; body.target_language: Field required');
});

it('maps each status to its exception', function (int $status, string $body, string $class): void {
    expect(ErrorFactory::make(Request::get('/x'), new Response($status, $body)))->toBeInstanceOf($class);
})->with([
    'unauthenticated' => [401, '{"detail":"Not authenticated"}', AuthenticationFailed::class],
    'missing scope' => [403, '{"detail":"Missing scope contacts:read"}', PermissionDenied::class],
    'not found' => [404, '{"detail":"Not Found"}', NotFound::class],
    'payment required' => [402, '{"detail":{"code":"insufficient_rcs_balance","message":"Top up the RCS wallet"}}', InsufficientBalance::class],
    'insufficient code on 400' => [400, '{"detail":{"code":"insufficient_balance"}}', InsufficientBalance::class],
    'teapot' => [418, '', UnexpectedStatus::class],
]);

it('copes with a body that is not JSON', function (): void {
    expect(ErrorFactory::make(Request::get('/x'), new Response(502, '<html>Bad gateway</html>'))->getMessage())
        ->toStartWith('SendSeven had a server error (GET /x, HTTP 502)');
});
