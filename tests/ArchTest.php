<?php

declare(strict_types=1);

arch('no debugging leftovers')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'print_r'])
    ->not->toBeUsed();

arch('strict types everywhere')
    ->expect('Reshapify\SendSeven')
    ->toUseStrictTypes();

arch('the core knows nothing about Laravel')
    ->expect('Reshapify\SendSeven')
    ->not->toUse(['Illuminate', 'Laravel']);

arch('concrete classes are final')
    ->expect('Reshapify\SendSeven')
    ->classes()
    ->toBeFinal()
    ->ignoring([
        Reshapify\SendSeven\Data\Data::class,
        Reshapify\SendSeven\Exceptions\ApiException::class,
        Reshapify\SendSeven\Webhooks\Events\Event::class,
    ]);

arch('every exception can be caught as a SendSevenException')
    ->expect('Reshapify\SendSeven')
    ->classes()
    ->extending(Throwable::class)
    ->toImplement(Reshapify\SendSeven\Exceptions\SendSevenException::class);
