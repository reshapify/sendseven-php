<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Resources\Concerns;

use Reshapify\SendSeven\Data\Contact;
use Reshapify\SendSeven\Exceptions\SendSevenException;

/**
 * @internal mixed into Contacts
 */
trait ContactsHelpers
{
    /**
     * The contact whose phone number is this one, ignoring formatting.
     *
     * SendSeven's search is fuzzy and a workspace may be shared with other
     * apps, so only an exact match on the digits counts.
     *
     * @throws SendSevenException
     */
    public function findByPhone(string $phone): ?Contact
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        foreach ($this->list(pageSize: 20, search: $phone) as $contact) {
            if ($contact->phone !== null && preg_replace('/\D/', '', $contact->phone) === $digits) {
                return $contact;
            }
        }

        return null;
    }

    /**
     * The contact with this phone number, created if there isn't one. Pass
     * the number in E.164 (+447700900123).
     *
     * @throws SendSevenException
     */
    public function findOrCreateByPhone(string $phone, ?string $name = null): Contact
    {
        return $this->findByPhone($phone) ?? $this->create(name: $name, phone: $phone);
    }
}
