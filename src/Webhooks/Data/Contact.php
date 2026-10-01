<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Webhooks\Data;

use Reshapify\SendSeven\Data\Data;
use Reshapify\SendSeven\Enums\ContactMethodType;
use Reshapify\SendSeven\Support\Attributes;

final readonly class Contact extends Data
{
    /**
     * @param  list<ContactMethod>  $contactMethods
     * @param  array<array-key, mixed>  $raw
     */
    public function __construct(
        public string $id,
        public ?string $name,
        public ?string $email,
        public ?string $phone,
        public array $contactMethods,
        array $raw = [],
    ) {
        parent::__construct($raw);
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data, string $path = 'contact'): self
    {
        $attributes = new Attributes($data, $path);

        return new self(
            id: $attributes->string('id'),
            name: $attributes->nullableString('name'),
            email: $attributes->nullableString('email'),
            phone: $attributes->nullableString('phone'),
            contactMethods: $attributes->list('contact_methods', ContactMethod::fromArray(...)),
            raw: $data,
        );
    }

    /**
     * The contact's method of a type, e.g. their Telegram chat ID; the primary
     * one when there are several.
     */
    public function method(ContactMethodType|string $type): ?ContactMethod
    {
        $type = $type instanceof ContactMethodType ? $type->value : $type;
        $matches = array_values(array_filter(
            $this->contactMethods,
            static fn (ContactMethod $method): bool => ($method->type instanceof ContactMethodType ? $method->type->value : $method->type) === $type,
        ));

        foreach ($matches as $method) {
            if ($method->isPrimary) {
                return $method;
            }
        }

        return $matches[0] ?? null;
    }
}
