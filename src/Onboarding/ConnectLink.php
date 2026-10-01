<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Onboarding;

use InvalidArgumentException;
use Reshapify\SendSeven\Enums\ChannelType;

/**
 * A link a customer opens to connect their own channel: WhatsApp (Meta's
 * Embedded Signup runs on SendSeven's page), Messenger, Instagram,
 * Telegram, SMS or an email provider. The channel lands in the tenant that
 * creates the link.
 *
 *     $link = $sendseven->connectLinks()->create(
 *         ConnectLink::for(ChannelType::WhatsApp)
 *             ->modes(whatsapp: [WhatsAppMode::Classic])
 *             ->brandedAs('Acme')
 *             ->redirectTo('https://app.acme.test/channels/connected')
 *             ->singleUse()
 *             ->expiresIn(hours: 48),
 *     );
 *
 *     // send the customer to $link->connectUrl; a channel.created webhook
 *     // carrying created_via_connect_token_id === $link->id follows.
 */
final readonly class ConnectLink
{
    /**
     * Channel types SendSeven's connect page accepts. RCS is provisioned by
     * SendSeven after verification, and browser push comes from a widget.
     */
    public const array CONNECTABLE = ['telegram', 'whatsapp', 'instagram', 'messenger', 'sms', 'gmail', 'smtp_imap', 'sendgrid_byok', 'mailgun_byok', 'sendgrid_managed'];

    /**
     * @param  list<string>  $channelTypes
     * @param  array<string, list<string>>  $modes
     */
    private function __construct(
        public array $channelTypes,
        public array $modes = [],
        public ?string $name = null,
        public ?string $partnerName = null,
        public ?string $redirectUrl = null,
        public ?int $maxUses = null,
        public ?int $expiresInHours = null,
        public ?int $useWindowMinutes = null,
    ) {}

    /**
     * The channel types the link may connect (strings for the email providers,
     * e.g. "gmail").
     */
    public static function for(ChannelType|string ...$channelTypes): self
    {
        $types = array_values(array_unique(array_map(static fn (ChannelType|string $type): string => $type instanceof ChannelType ? $type->value : $type, $channelTypes)));

        if ($types === []) {
            throw new InvalidArgumentException('A connect link needs at least one channel type.');
        }

        foreach ($types as $type) {
            if (! in_array($type, self::CONNECTABLE, true)) {
                throw new InvalidArgumentException("SendSeven's connect page can't connect {$type}. It accepts: ".implode(', ', self::CONNECTABLE).'. RCS is set up by SendSeven after verification; browser push comes from a website widget.');
            }
        }

        return new self($types);
    }

    /**
     * Pin the connect modes customers may choose, e.g. WhatsApp without
     * Coexistence. Modes only narrow what the account allows; they never
     * unlock a mode its plan doesn't have.
     *
     * @param  list<WhatsAppMode>|null  $whatsapp
     * @param  list<InstagramMode>|null  $instagram
     * @param  list<MessengerMode>|null  $messenger
     */
    public function modes(?array $whatsapp = null, ?array $instagram = null, ?array $messenger = null): self
    {
        $modes = $this->modes;

        foreach (['whatsapp' => $whatsapp, 'instagram' => $instagram, 'messenger' => $messenger] as $channel => $chosen) {
            if ($chosen === null) {
                continue;
            }

            if ($chosen === []) {
                throw new InvalidArgumentException("List at least one {$channel} mode, or leave it out to allow all of them.");
            }

            $modes[$channel] = array_map(static fn (WhatsAppMode|InstagramMode|MessengerMode $mode): string => $mode->value, $chosen);
        }

        return new self($this->channelTypes, $modes, $this->name, $this->partnerName, $this->redirectUrl, $this->maxUses, $this->expiresInHours, $this->useWindowMinutes);
    }

    /**
     * Your brand on the connect page.
     */
    public function brandedAs(string $partnerName): self
    {
        return new self($this->channelTypes, $this->modes, $this->name, $partnerName, $this->redirectUrl, $this->maxUses, $this->expiresInHours, $this->useWindowMinutes);
    }

    /**
     * Where the customer goes after connecting.
     */
    public function redirectTo(string $url): self
    {
        return new self($this->channelTypes, $this->modes, $this->name, $this->partnerName, $url, $this->maxUses, $this->expiresInHours, $this->useWindowMinutes);
    }

    /**
     * An internal label, e.g. the customer's name.
     */
    public function named(string $name): self
    {
        return new self($this->channelTypes, $this->modes, $name, $this->partnerName, $this->redirectUrl, $this->maxUses, $this->expiresInHours, $this->useWindowMinutes);
    }

    /**
     * One connection only: the right choice for a link sent to one customer.
     */
    public function singleUse(): self
    {
        return $this->maxUses(1);
    }

    public function maxUses(int $uses): self
    {
        if ($uses < 1 || $uses > 100) {
            throw new InvalidArgumentException('A connect link allows 1 to 100 connections.');
        }

        return new self($this->channelTypes, $this->modes, $this->name, $this->partnerName, $this->redirectUrl, $uses, $this->expiresInHours, $this->useWindowMinutes);
    }

    public function expiresIn(int $hours): self
    {
        if ($hours < 1 || $hours > 168) {
            throw new InvalidArgumentException('A connect link lasts 1 to 168 hours (a week).');
        }

        return new self($this->channelTypes, $this->modes, $this->name, $this->partnerName, $this->redirectUrl, $this->maxUses, $hours, $this->useWindowMinutes);
    }

    /**
     * How long the link stays usable after it is first opened (5 to 1440 minutes).
     */
    public function usableForMinutesAfterFirstUse(int $minutes): self
    {
        if ($minutes < 5 || $minutes > 1440) {
            throw new InvalidArgumentException('The window after first use is 5 to 1440 minutes.');
        }

        return new self($this->channelTypes, $this->modes, $this->name, $this->partnerName, $this->redirectUrl, $this->maxUses, $this->expiresInHours, $minutes);
    }
}
